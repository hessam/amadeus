<?php
/**
 * Handles all communication with the Amadeus API.
 *
 * @package Amadeus_Flight_Search
 * @subpackage Amadeus_Flight_Search/includes
 * @author Your Name
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Amadeus_API {

    /**
     * Amadeus API Key.
     * @var string
     */
    private $api_key;

    /**
     * Amadeus API Secret.
     * @var string
     */
    private $api_secret;

    /**
     * API Environment ('test' or 'production').
     * @var string
     */
    private $environment;

    /**
     * Base URL for the Amadeus API.
     * @var string
     */
    private $base_url;

    /**
     * API access token.
     * @var string
     */
    private $access_token;

    /**
     * Transient key for caching the API token.
     */
    const TOKEN_TRANSIENT_KEY = 'amadeus_api_token_v2'; // Changed key to avoid conflicts if old one exists

    /**
     * Constructor.
     * Initializes API settings.
     * @since 1.0.0
     */
    public function __construct() {
        $this->api_key     = Amadeus_Flight_Search_Settings::get_setting( 'amadeus_api_key' );
        $this->api_secret  = Amadeus_Flight_Search_Settings::get_setting( 'amadeus_api_secret' );
        $this->environment = Amadeus_Flight_Search_Settings::get_setting( 'amadeus_api_environment', 'test' );

        if ( 'production' === $this->environment ) {
            $this->base_url = 'https://api.amadeus.com';
        } else {
            $this->base_url = 'https://test.api.amadeus.com';
        }
    }

    /**
     * Get an OAuth2 access token from Amadeus.
     * Caches the token in a transient.
     *
     * @since 1.0.0
     * @return string|WP_Error Access token or WP_Error on failure.
     */
    private function get_access_token() {
        // Try to get the token from transient
        $cached_token = get_transient( self::TOKEN_TRANSIENT_KEY );
        if ( false !== $cached_token && !empty($cached_token) ) {
            $this->access_token = $cached_token;
            return $this->access_token;
        }

        if ( empty( $this->api_key ) || empty( $this->api_secret ) ) {
            afs_debug_log( 'API Key or Secret is missing.', __CLASS__ . '::' . __FUNCTION__ );
            return new WP_Error( 'api_credentials_missing', __( 'Amadeus API Key or Secret is not configured.', 'amadeus-flight-search' ) );
        }

        $token_url = $this->base_url . '/v1/security/oauth2/token';
        $body = array(
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->api_key,
            'client_secret' => $this->api_secret,
        );

        afs_debug_log( 'Requesting new Amadeus API token from: ' . $token_url, __CLASS__ . '::' . __FUNCTION__ );

        $response = wp_remote_post(
            $token_url,
            array(
                'method'      => 'POST',
                'headers'     => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body'        => $body,
                'timeout'     => 30, // seconds
                'sslverify'   => true, // Recommended for production
            )
        );

        if ( is_wp_error( $response ) ) {
            afs_debug_log( 'WP_Error during token request: ' . $response->get_error_message(), __CLASS__ . '::' . __FUNCTION__ );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        afs_debug_log( 'Token response code: ' . $response_code, __CLASS__ . '::' . __FUNCTION__ );
        afs_debug_log( 'Token response body: ' . $response_body, __CLASS__ . '::' . __FUNCTION__ );

        if ( 200 === $response_code && isset( $data['access_token'] ) ) {
            $this->access_token = $data['access_token'];
            // Amadeus tokens typically expire in 1799 seconds (just under 30 minutes).
            // Set transient with a slightly shorter expiry time to be safe.
            $expires_in = isset( $data['expires_in'] ) ? intval( $data['expires_in'] ) : 1700;
            set_transient( self::TOKEN_TRANSIENT_KEY, $this->access_token, $expires_in - 60 ); // Cache for slightly less than expiry
            return $this->access_token;
        } else {
            $error_message = __( 'Failed to retrieve Amadeus API access token.', 'amadeus-flight-search' );
            if ( isset( $data['errors'][0]['detail'] ) ) {
                $error_message .= ' ' . $data['errors'][0]['detail'];
            } elseif(isset($data['error_description'])) {
                $error_message .= ' ' . $data['error_description'];
            } elseif(isset($data['title'])) {
                 $error_message .= ' ' . $data['title'];
            }
            afs_debug_log( 'Error retrieving token: ' . $error_message, __CLASS__ . '::' . __FUNCTION__ );
            return new WP_Error( 'token_retrieval_failed', $error_message, $data );
        }
    }

    /**
     * Make a generic request to the Amadeus API.
     *
     * @since 1.0.0
     * @param string $endpoint The API endpoint (e.g., '/v2/shopping/flight-offers').
     * @param array  $params   Query parameters for the request.
     * @param string $method   HTTP method (GET, POST). Default GET.
     * @param array  $body     Request body for POST requests.
     * @return array|WP_Error Decoded JSON response or WP_Error on failure.
     */
    private function make_request( $endpoint, $params = array(), $method = 'GET', $body_data = null ) {
        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token; // Propagate error
        }

        $request_url = $this->base_url . $endpoint;

        if ( 'GET' === strtoupper( $method ) && ! empty( $params ) ) {
            $request_url = add_query_arg( $params, $request_url );
        }

        $args = array(
            'method'  => strtoupper( $method ),
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json;charset=UTF-8', // For POST body
            ),
            'timeout'   => 45, // Increased timeout for potentially long searches
            'sslverify' => true,
        );

        if ( 'POST' === strtoupper( $method ) && ! is_null( $body_data ) ) {
            $args['body'] = wp_json_encode( $body_data );
             $args['headers']['Content-Type'] = 'application/vnd.amadeus+json'; // Required for Flight Offers Search POST
        }


        afs_debug_log( 'Making Amadeus API Request to: ' . $request_url, __CLASS__ . '::' . __FUNCTION__ );
        if ($body_data) {
            afs_debug_log( 'Request Body: ' . wp_json_encode($body_data), __CLASS__ . '::' . __FUNCTION__ );
        }


        $response = wp_remote_request( $request_url, $args );

        if ( is_wp_error( $response ) ) {
            afs_debug_log( 'WP_Error during API request: ' . $response->get_error_message(), __CLASS__ . '::' . __FUNCTION__ );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        afs_debug_log( 'API Response Code: ' . $response_code, __CLASS__ . '::' . __FUNCTION__ );
        afs_debug_log( 'API Response Body: ' . $response_body, __CLASS__ . '::' . __FUNCTION__ );

        if ( $response_code >= 200 && $response_code < 300 ) {
            return $data;
        } else {
            $error_message = sprintf(
                __( 'Amadeus API request failed with status %s.', 'amadeus-flight-search' ),
                $response_code
            );
            if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
                foreach ( $data['errors'] as $error ) {
                    $error_message .= ' ' . (isset($error['title']) ? $error['title'] : '') . (isset($error['detail']) ? ': ' . $error['detail'] : '');
                }
            }
            afs_debug_log( 'API Error: ' . $error_message . ' Response: ' . $response_body, __CLASS__ . '::' . __FUNCTION__ );
            return new WP_Error( 'api_request_failed', $error_message, array( 'status' => $response_code, 'body' => $data ) );
        }
    }

    /**
     * Search for airport/city locations (for autocomplete).
     * https://developers.amadeus.com/references/airport-city-search
     *
     * @since 1.0.0
     * @param string $keyword The search keyword.
     * @return array|WP_Error Array of location suggestions or WP_Error.
     */
    public function search_locations( $keyword ) {
        if ( empty( $keyword ) ) {
            return new WP_Error( 'missing_keyword', __( 'Search keyword cannot be empty.', 'amadeus-flight-search' ) );
        }

        $params = array(
            'subType' => 'AIRPORT,CITY', // Search for both airports and cities
            'keyword' => strtoupper( $keyword ), // API often prefers uppercase
            'page[limit]' => 10, // Max number of results
             // 'view' => 'LIGHT' // For smaller response size, if applicable
        );

        $endpoint = '/v1/reference-data/locations';
        return $this->make_request( $endpoint, $params, 'GET' );
    }

    /**
     * Search for flight offers.
     * Uses POST method as per Amadeus Self-Service API documentation for Flight Offers Search.
     * https://developers.amadeus.com/references/flight-offers-search
     *
     * @since 1.0.0
     * @param array $search_criteria Criteria for flight search.
     * Example structure:
     * {
     * "currencyCode": "USD",
     * "originDestinations": [
     * {
     * "id": "1",
     * "originLocationCode": "SYD",
     * "destinationLocationCode": "BKK",
     * "departureDateTimeRange": {
     * "date": "2024-10-01"
     * }
     * }
     * // Add second element for round trip
     * // {
     * //   "id": "2",
     * //   "originLocationCode": "BKK",
     * //   "destinationLocationCode": "SYD",
     * //   "departureDateTimeRange": {
     * //     "date": "2024-10-15"
     * //   }
     * // }
     * ],
     * "travelers": [
     * {
     * "id": "1",
     * "travelerType": "ADULT"
     * }
     * // Add more travelers as needed
     * ],
     * "sources": [ "GDS" ],
     * "searchCriteria": {
     * "maxFlightOffers": 25, // Or as per user preference/setting
     * "flightFilters": {
     * "cabinRestrictions": [
     * {
     * "cabin": "ECONOMY", // Or BUSINESS, FIRST_CLASS
     * "coverage": "MOST_SEGMENTS",
     * "originDestinationIds": ["1"] // Apply to specific legs
     * }
     * ]
     * // Other filters like "carrierRestrictions" can be added
     * }
     * }
     * }
     * @return array|WP_Error Flight offers data or WP_Error.
     */
    public function search_flight_offers( $search_criteria ) {
        if ( empty( $search_criteria ) ) {
            return new WP_Error( 'missing_search_criteria', __( 'Search criteria cannot be empty.', 'amadeus-flight-search' ) );
        }

        // Ensure currencyCode is set, default if not provided by settings
        if ( !isset( $search_criteria['currencyCode'] ) ) {
            $search_criteria['currencyCode'] = Amadeus_Flight_Search_Settings::get_setting('currency_code', 'USD');
        }

        // Default number of offers if not specified
        if ( !isset($search_criteria['searchCriteria']['maxFlightOffers']) ) {
            if (!isset($search_criteria['searchCriteria'])) {
                $search_criteria['searchCriteria'] = [];
            }
            $search_criteria['searchCriteria']['maxFlightOffers'] = 25; // A sensible default
        }
        
        // Ensure sources is set
        if ( !isset($search_criteria['sources']) ) {
            $search_criteria['sources'] = ['GDS'];
        }


        $endpoint = '/v2/shopping/flight-offers';
        // Flight Offers Search uses POST with a JSON body
        return $this->make_request( $endpoint, array(), 'POST', $search_criteria );
    }

    /**
     * Fetch airline details (e.g., name) by IATA code.
     * This is a common need, but the Self-Service API might not have a direct "get airline by code" endpoint.
     * Airline names are usually included in the flight offers response in the `dictionaries.carriers`.
     * If a separate lookup is needed, you might use:
     * https://developers.amadeus.com/references/airline-code-lookup
     * For simplicity, this plugin will rely on the dictionaries from flight offers.
     * This method is a placeholder if direct lookup becomes necessary.
     *
     * @param string $airline_code IATA code of the airline.
     * @return array|WP_Error Airline data or WP_Error.
     */
    // public function get_airline_details( $airline_code ) {
    //     if ( empty( $airline_code ) ) {
    //         return new WP_Error( 'missing_airline_code', __( 'Airline code cannot be empty.', 'amadeus-flight-search' ) );
    //     }
    //     $params = array(
    // 'airlineCodes' => strtoupper( $airline_code )
    //     );
    //     $endpoint = '/v1/reference-data/airlines';
    // return $this->make_request( $endpoint, $params, 'GET' );
    // }

    // Note: Airline logos are not directly provided by the Amadeus Flight Search API in a structured way.
    // They recommend using third-party services or your own database for logos.
    // Example: https://content.airhex.com/content/logos/airlines/{{iataCode}}_200_200.png
    // Or, you can use a service like ICAO airline logos: https://www.iata.org/contentassets/image/logos/airlines/{{iataCode}}.png (check terms of use)

    // ========================================================================
	// START: HOTEL SEARCH METHODS (Corrected for 2-step process)
	// ========================================================================

	/**
	 * Search for cities for hotel search autocomplete.
	 *
	 * @param string $keyword The search keyword for the city.
	 * @return array|WP_Error Array of location suggestions or WP_Error.
	 */
	public function search_hotel_cities( $keyword ) {
		if ( empty( $keyword ) ) {
			return new WP_Error( 'missing_keyword', __( 'Search keyword cannot be empty.', 'amadeus-flight-search' ) );
		}

		$params = array(
			'subType'     => 'CITY',
			'keyword'     => strtoupper( $keyword ),
			'page[limit]' => 10,
		);

		$endpoint = '/v1/reference-data/locations'; 
		return $this->make_request( $endpoint, $params, 'GET' );
	}

	/**
	 * STEP 1: Get a list of hotel IDs available in a given city.
	 *
	 * @param string $city_code The IATA code for the city.
	 * @return array|WP_Error Array of hotel IDs or WP_Error.
	 */
	public function get_hotel_ids_by_city( $city_code ) {
		if ( empty( $city_code ) ) {
			return new WP_Error( 'missing_city_code', __( 'City code is required.', 'amadeus-flight-search' ) );
		}

		$params = array(
			'cityCode' => $city_code,
			'radius'   => 20,
			'radiusUnit' => 'KM'
		);

		$endpoint = '/v1/reference-data/locations/hotels/by-city';
		return $this->make_request( $endpoint, $params, 'GET' );
	}

	/**
	 * STEP 2: Search for hotel offers using a list of hotel IDs.
	 *
	 * @param array  $hotel_ids Array of Amadeus hotel IDs.
	 * @param string $check_in_date The check-in date (YYYY-MM-DD).
	 * @param string $check_out_date The check-out date (YYYY-MM-DD).
	 * @param int    $adults The number of adults.
	 * @return array|WP_Error Hotel offers data or WP_Error.
	 */
	public function search_hotel_offers( $hotel_ids, $check_in_date, $check_out_date, $adults = 1 ) {
		if ( empty( $hotel_ids ) || empty( $check_in_date ) || empty( 'check_out_date' ) ) {
			return new WP_Error( 'missing_hotel_search_criteria', __( 'Hotel IDs, check-in date, and check-out date are required.', 'amadeus-flight-search' ) );
		}

		$hotel_ids_string = implode( ',', $hotel_ids );

		$params = array(
			'hotelIds'      => $hotel_ids_string,
			'checkInDate'   => $check_in_date,
			'checkOutDate'  => $check_out_date,
			'adults'        => $adults,
			'paymentPolicy' => 'NONE',
			'bestRateOnly'  => true,
			'view'          => 'FULL',
		);

		$endpoint = '/v3/shopping/hotel-offers';
		return $this->make_request( $endpoint, $params, 'GET' );
	}

	// ========================================================================
	// END: HOTEL SEARCH METHODS
	// ========================================================================
  
}