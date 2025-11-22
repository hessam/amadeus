<?php
/**
 * Handles AJAX requests for the Amadeus Flight Search plugin.
 *
 * @package Amadeus_Flight_Search
 * @subpackage Amadeus_Flight_Search/includes
 * @author Your Name
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Amadeus_AJAX {

    /**
     * Instance of Amadeus_API.
     * @var Amadeus_API
     */
    private $amadeus_api;

    /**
     * Nonce action name.
     * Should match the nonce created in Amadeus_Flight_Search_Main::enqueue_scripts
     */
    const NONCE_ACTION = 'amadeus_flight_search_nonce';

    /**
     * Transient prefix for selected flight data.
     */
    const SELECTED_FLIGHT_TRANSIENT_PREFIX = 'afs_selected_flight_';


    /**
     * Constructor.
     */
    public function __construct() {
        $this->amadeus_api = new Amadeus_API();
    }

    /**
     * Get a unique identifier for the current user (logged-in or guest).
     * Used for transient keys.
     *
     * @since 1.0.0
     * @return string The user identifier.
     */
    private function get_user_identifier() {
        if (function_exists('afs_get_user_identifier')) {
            return afs_get_user_identifier();
        }
        // Fallback
        if ( is_user_logged_in() ) {
            return 'user_' . get_current_user_id();
        } else {
            $cookie_name = 'amadeus_session_id';
            if ( isset( $_COOKIE[ $cookie_name ] ) && ! empty( $_COOKIE[ $cookie_name ] ) ) {
                if ( preg_match( '/^[a-zA-Z0-9\-]+$/', $_COOKIE[ $cookie_name ] ) ) {
                    return 'guest_' . sanitize_text_field( $_COOKIE[ $cookie_name ] );
                }
            }
            return 'guest_' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid());
        }
    }

    /**
     * Check rate limit for the current user IP.
     *
     * @param string $action The action being performed.
     * @param int    $limit  Max requests allowed.
     * @param int    $window Time window in seconds.
     * @return bool True if request is allowed, false if rate limited.
     */
    private function check_rate_limit( $action, $limit = 10, $window = 60 ) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $transient_key = 'afs_rate_limit_' . md5( $ip . $action );
        $current_count = get_transient( $transient_key );

        if ( false === $current_count ) {
            set_transient( $transient_key, 1, $window );
            return true;
        }

        if ( $current_count >= $limit ) {
            return false;
        }

        set_transient( $transient_key, $current_count + 1, $window );
        return true;
    }

    /**
     * Handle AJAX request for searching locations (airport/city autocomplete).
     *
     * @since 1.0.1
     */
    public function search_locations() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! $this->check_rate_limit( 'search_locations', 20, 60 ) ) {
            wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'amadeus-flight-search' ) ) );
            return;
        }

        $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

        if ( strlen($keyword) < 3 ) { // Also check length on the server
            wp_send_json_error( array( 'message' => 'Search term must be at least 3 characters.' ) );
            return;
        }

        // --- START: Final Advanced Logic ---

        // 1. Load the world cities translation map
        $translation_map = array();
        $map_file_path = AFS_PLUGIN_DIR . 'data/tr-en-world-cities.json';
        if ( file_exists( $map_file_path ) ) {
            $map_content = file_get_contents( $map_file_path );
            $translation_map = json_decode( $map_content, true ) ?: array();
        }

        $search_keyword = ''; // This will be the final term we send to the API
        $keyword_lower = strtolower( $keyword );

        // 2. Check if the partial keyword matches the START of a Turkish key
        foreach ( $translation_map as $turkish_key => $english_value ) {
            // Use strpos() to check if the Turkish key starts with the user's input
            if ( strpos( $turkish_key, $keyword_lower ) === 0 ) {
                $search_keyword = $english_value; // We found a match! (e.g., 'lon' matches 'londra', so use 'london')
                afs_debug_log( "Partially matched '{$keyword}' to '{$turkish_key}' and translated to '{$search_keyword}'.", __CLASS__ );
                break; // Stop searching the map
            }
        }

        // 3. If no translation was found, fallback to character transliteration
        if ( empty( $search_keyword ) ) {
            $turkish_chars    = array( 'İ', 'ı', 'Ş', 'ş', 'Ğ', 'ğ', 'Ü', 'ü', 'Ö', 'ö', 'Ç', 'ç' );
            $english_chars    = array( 'I', 'i', 'S', 's', 'G', 'g', 'U', 'u', 'O', 'o', 'C', 'c' );
            $search_keyword = str_replace( $turkish_chars, $english_chars, $keyword );
        }

        // --- END: Final Advanced Logic ---
        
        afs_debug_log( 'AJAX search_locations called with final keyword for API: ' . $search_keyword, __CLASS__ );
        
        // Use the final, processed keyword for the API call
        $result = $this->amadeus_api->search_locations( $search_keyword );

        // The rest of your function for processing results stays the same...
        if ( is_wp_error( $result ) ) {
            afs_debug_log( 'API Error in search_locations: ' . $result->get_error_message(), __CLASS__ );
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } elseif ( isset( $result['data'] ) && !empty($result['data']) ) {
            $suggestions = array();
            foreach ( $result['data'] as $location ) {
                $label = $location['name'];
                if (isset($location['iataCode'])) {
                    $label .= ' (' . $location['iataCode'] . ')';
                }
                if (isset($location['address']['cityName'])) {
                    $label .= ', ' . $location['address']['cityName'];
                }
                
                $suggestions[] = array(
                    'label'     => $label,
                    'value'     => $location['iataCode'],
                    'name'      => $location['name'],
                    'iataCode'  => $location['iataCode'],
                    'subType'   => $location['subType'],
                    'full_data' => $location
                );
            }
            afs_debug_log( 'AJAX search_locations success. Suggestions: ' . count($suggestions), __CLASS__ );
            wp_send_json_success( $suggestions );
        } else {
            afs_debug_log( 'API Error in search_locations: No data found or unexpected format.', __CLASS__ );
            wp_send_json_error( array( 'message' => __( 'No locations found or API error.', 'amadeus-flight-search' ) ) );
        }
    }

    /**
     * Handle AJAX request for searching flight offers.
     *
     * @since 1.0.0
     */
    public function search_flights() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! $this->check_rate_limit( 'search_flights', 5, 60 ) ) {
            wp_send_json_error( array( 'message' => __( 'Too many search requests. Please wait a moment.', 'amadeus-flight-search' ) ) );
            return;
        }

        $params = isset( $_POST['params'] ) ? wp_unslash( $_POST['params'] ) : array();

        if ( empty( $params ) || !is_array($params) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid search parameters.', 'amadeus-flight-search' ) ) );
            return;
        }

        $origin_location_code = isset( $params['originLocationCode'] ) ? sanitize_text_field( $params['originLocationCode'] ) : '';
        $destination_location_code = isset( $params['destinationLocationCode'] ) ? sanitize_text_field( $params['destinationLocationCode'] ) : '';
        $departure_date = isset( $params['departureDate'] ) ? sanitize_text_field( $params['departureDate'] ) : '';
        $return_date = isset( $params['returnDate'] ) ? sanitize_text_field( $params['returnDate'] ) : '';
        $adults = isset( $params['adults'] ) ? absint( $params['adults'] ) : 1;
        $children = isset( $params['children'] ) ? absint( $params['children'] ) : 0;
        $infants = isset( $params['infants'] ) ? absint( $params['infants'] ) : 0;
        $travel_class = isset( $params['travelClass'] ) ? sanitize_text_field( strtoupper($params['travelClass']) ) : 'ECONOMY';

        if ( empty( $origin_location_code ) || empty( $destination_location_code ) || empty( $departure_date ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required search fields (origin, destination, departure date).', 'amadeus-flight-search' ) ) );
            return;
        }

        $origin_destinations = array();
        $origin_destinations[] = array(
            'id' => '1',
            'originLocationCode' => $origin_location_code,
            'destinationLocationCode' => $destination_location_code,
            'departureDateTimeRange' => array( 'date' => $departure_date )
        );

        if ( ! empty( $return_date ) ) {
            $origin_destinations[] = array(
                'id' => '2',
                'originLocationCode' => $destination_location_code,
                'destinationLocationCode' => $origin_location_code,
                'departureDateTimeRange' => array( 'date' => $return_date )
            );
        }

        // --- START: CORRECTED TRAVELER LOGIC ---
        $travelers = array();
        $adult_ids = array(); // New array to store the ID of each adult
        $traveler_id = 1;

        // First, add all the ADULTS and store their unique IDs
        for ( $i = 0; $i < $adults; $i++ ) {
            $current_adult_id = (string)$traveler_id++;
            $travelers[] = array( 'id' => $current_adult_id, 'travelerType' => 'ADULT' );
            $adult_ids[] = $current_adult_id; // Save the ID for later
        }

        // Second, add all the CHILDREN
        for ( $i = 0; $i < $children; $i++ ) {
            $travelers[] = array( 'id' => (string)$traveler_id++, 'travelerType' => 'CHILD' );
        }

        // Third, add all the INFANTS and link each one to an adult
        for ( $i = 0; $i < $infants; $i++ ) {
            // This associates the first infant with the first adult, second with second, etc.
            // It falls back to the last adult if there are more infants than adults (an edge case).
            $associated_adult_id = isset($adult_ids[$i]) ? $adult_ids[$i] : end($adult_ids);

            $travelers[] = array(
                'id' => (string)$traveler_id++,
                'travelerType' => 'HELD_INFANT',
                'associatedAdultId' => $associated_adult_id // THE FIX IS HERE
            );
        }

        // A final check in case the search was for 0 passengers
        if (empty($travelers)) {
            $travelers[] = array( 'id' => '1', 'travelerType' => 'ADULT');
        }
        // --- END: CORRECTED TRAVELER LOGIC ---

        $search_criteria_payload = array(
            'currencyCode' => Amadeus_Flight_Search_Settings::get_setting( 'currency_code', 'USD' ),
            'originDestinations' => $origin_destinations,
            'travelers' => $travelers,
            'sources' => array( 'GDS' ),
            'searchCriteria' => array(
                'maxFlightOffers' => apply_filters('afs_max_flight_offers_results', 25),
                'flightFilters' => array(
                    'cabinRestrictions' => array(
                        array(
                            'cabin' => $travel_class,
                            'coverage' => 'MOST_SEGMENTS',
                            'originDestinationIds' => array_map(function($od){ return $od['id']; }, $origin_destinations)
                        )
                    )
                )
            )
        );
        
        if (isset($params['nonStop']) && ($params['nonStop'] === 'true' || $params['nonStop'] === true || $params['nonStop'] === 1 || $params['nonStop'] === '1')) {
            if (!isset($search_criteria_payload['searchCriteria']['flightFilters']['connectionRestriction'])) {
                $search_criteria_payload['searchCriteria']['flightFilters']['connectionRestriction'] = [];
            }
            $search_criteria_payload['searchCriteria']['flightFilters']['connectionRestriction']['maxNumberOfConnections'] = 0;
        }

        afs_debug_log( 'AJAX search_flights called with criteria: ' . wp_json_encode($search_criteria_payload), __CLASS__ );
        $result = $this->amadeus_api->search_flight_offers( $search_criteria_payload );

        if ( is_wp_error( $result ) ) {
            afs_debug_log( 'API Error in search_flights: ' . $result->get_error_message(), __CLASS__ );
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'details' => $result->get_error_data() ) );
        } elseif ( isset( $result['data'] ) && !empty($result['data']) ) {
            afs_debug_log( 'AJAX search_flights success. Offers found: ' . count($result['data']), __CLASS__ );
            wp_send_json_success( $result );
        } elseif (isset( $result['data'] ) && empty($result['data'])) {
            afs_debug_log( 'AJAX search_flights success. No offers found.', __CLASS__ );
            wp_send_json_success( array('data' => array(), 'dictionaries' => array(), 'message' => __('No flights found matching your criteria.', 'amadeus-flight-search') ) );
        }
        else {
            afs_debug_log( 'API Error in search_flights: Unexpected response format.', __CLASS__ );
            wp_send_json_error( array( 'message' => __( 'No flights found or an API error occurred.', 'amadeus-flight-search' ) ) );
        }
    }

    /**
     * Handle AJAX request for selecting a flight offer.
     * Stores the selected flight data in a transient after augmenting it with full location names.
     *
     * @since 1.0.1
     */
    public function select_flight() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $flight_offer_json = isset( $_POST['flightOffer'] ) ? wp_unslash( $_POST['flightOffer'] ) : '';
        if ( empty( $flight_offer_json ) ) {
            wp_send_json_error( array( 'message' => __( 'No flight offer data received.', 'amadeus-flight-search' ) ) );
            return;
        }

        $flight_offer_data = json_decode( $flight_offer_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE || !is_array($flight_offer_data) ) {
            afs_debug_log( 'Error decoding flight offer JSON: ' . json_last_error_msg() . ' Data: ' . $flight_offer_json, __CLASS__ );
            wp_send_json_error( array( 'message' => __( 'Invalid flight offer data format.', 'amadeus-flight-search' ) ) );
            return;
        }

        // *** LOGIC TO FETCH AIRPORT NAMES RELIABLY ON THE SERVER ***
        if ( isset( $flight_offer_data['itineraries'][0]['segments'] ) ) {
            $outbound_segments = $flight_offer_data['itineraries'][0]['segments'];
            
            if ( ! empty( $outbound_segments ) ) {
                // Get origin IATA from the first segment
                $origin_iata = $outbound_segments[0]['departure']['iataCode'];

                // Get destination IATA from the last segment of the first itinerary
                $last_segment = end( $outbound_segments );
                $destination_iata = $last_segment['arrival']['iataCode'];

                // Look up origin location name using the API
                $origin_location_result = $this->amadeus_api->search_locations( $origin_iata );
                if ( ! is_wp_error( $origin_location_result ) && ! empty( $origin_location_result['data'] ) ) {
                    // Find the exact match for the IATA code in the results
                    foreach($origin_location_result['data'] as $location) {
                        if ($location['iataCode'] === $origin_iata) {
                            $flight_offer_data['originLocationName'] = $location['name'];
                            break;
                        }
                    }
                }
                // If not found after loop, fallback to IATA code
                if (!isset($flight_offer_data['originLocationName'])) {
                    $flight_offer_data['originLocationName'] = $origin_iata; 
                }


                // Look up destination location name using the API
                $destination_location_result = $this->amadeus_api->search_locations( $destination_iata );
                if ( ! is_wp_error( $destination_location_result ) && ! empty( $destination_location_result['data'] ) ) {
                     // Find the exact match for the IATA code in the results
                     foreach($destination_location_result['data'] as $location) {
                        if ($location['iataCode'] === $destination_iata) {
                            $flight_offer_data['destinationLocationName'] = $location['name'];
                            break;
                        }
                    }
                }
                // If not found after loop, fallback to IATA code
                 if (!isset($flight_offer_data['destinationLocationName'])) {
                    $flight_offer_data['destinationLocationName'] = $destination_iata;
                }
            }
        }
        // *** END OF SERVER-SIDE NAME FETCH ***


        $user_identifier = $this->get_user_identifier();
        $transient_key = self::SELECTED_FLIGHT_TRANSIENT_PREFIX . $user_identifier;

        // Store the newly augmented data for 1 hour.
        set_transient( $transient_key, $flight_offer_data, HOUR_IN_SECONDS );
        afs_debug_log( 'Flight offer stored in transient: ' . $transient_key, __CLASS__ );

        $booking_page_url_setting = Amadeus_Flight_Search_Settings::get_setting( 'booking_page_url' );
        $booking_page_url = afs_get_page_url_from_setting($booking_page_url_setting);

        if ( empty( $booking_page_url ) ) {
            afs_debug_log( 'Booking page URL not configured.', __CLASS__ );
            wp_send_json_error( array( 'message' => __( 'Booking page URL is not configured in plugin settings.', 'amadeus-flight-search' ) ) );
            return;
        }

        // New Code
        // This sends the flight data back to the JavaScript along with the redirect URL.
        wp_send_json_success( array( 
            'redirectUrl' => esc_url( $booking_page_url ),
            'flightOffer' => $flight_offer_data 
        ) );
    }

    // New Function
    /**
     * AJAX action to clear the selected flight data transient.
     * This is called by JS after the data has been used to pre-fill the form.
     */
    public function clear_flight_transient() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        $user_identifier = $this->get_user_identifier();
        $transient_key = self::SELECTED_FLIGHT_TRANSIENT_PREFIX . $user_identifier;
        delete_transient( $transient_key );
        wp_send_json_success( array( 'message' => 'Transient cleared.' ) );
    }

}