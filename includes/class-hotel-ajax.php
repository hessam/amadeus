<?php
/**
 * Handles AJAX requests for the Hotel Search feature.
 *
 * @package Amadeus_Flight_Search
 * @subpackage Amadeus_Flight_Search/includes
 * @author Your Name
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Amadeus_Hotel_AJAX {

    private $amadeus_api;
    const NONCE_ACTION = 'amadeus_hotel_search_nonce';
    const SELECTED_HOTEL_TRANSIENT_PREFIX = 'ahs_selected_hotel_';
    
    // Maximum number of hotels to search for offers (to avoid URI length issues)
    const MAX_HOTELS_PER_REQUEST = 50;

    public function __construct() {
        $this->amadeus_api = new Amadeus_API();
    }

    /**
     * Handle AJAX request for searching hotel locations (city autocomplete).
     */
    public function search_hotel_locations() {
        if ( ! Amadeus_Flight_Search_Settings::get_setting('hotel_search_enabled') ) {
            wp_send_json_error( array( 'message' => 'Hotel search feature is disabled.' ) );
            return;
        }
        
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

        if ( strlen($keyword) < 2 ) {
            wp_send_json_error( array( 'message' => 'Search term must be at least 2 characters.' ) );
            return;
        }
        
        // Correctly call the new dedicated method for city search
        $result = $this->amadeus_api->search_hotel_cities( $keyword );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            return;
        } elseif ( isset( $result['data'] ) && !empty($result['data']) ) {
            $suggestions = array();
            foreach ( $result['data'] as $location ) {
                if ($location['subType'] === 'CITY') {
                    $suggestions[] = array(
                        'label'     => $location['name'] . ', ' . $location['address']['countryCode'],
                        'value'     => $location['name'],
                        'iataCode'  => $location['iataCode'],
                    );
                }
            }
            wp_send_json_success( $suggestions );
        } else {
            wp_send_json_error( array( 'message' => __( 'No locations found.', 'amadeus-flight-search' ) ) );
        }
    }

    /**
     * Handle AJAX request for searching hotel offers using the 2-step process with batching.
     */
    public function search_hotels() {
        if ( ! Amadeus_Flight_Search_Settings::get_setting('hotel_search_enabled') ) {
            wp_send_json_error( array( 'message' => 'Hotel search feature is disabled.' ) );
            return;
        }
        
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        $params = isset( $_POST['params'] ) ? wp_unslash( $_POST['params'] ) : array();

        $city_code = isset( $params['cityCode'] ) ? sanitize_text_field( $params['cityCode'] ) : '';
        $check_in_date = isset( $params['checkInDate'] ) ? sanitize_text_field( $params['checkInDate'] ) : '';
        $check_out_date = isset( $params['checkOutDate'] ) ? sanitize_text_field( $params['checkOutDate'] ) : '';
        $adults = isset( $params['adults'] ) ? absint( $params['adults'] ) : 1;

        if ( empty( $city_code ) || empty( $check_in_date ) || empty( $check_out_date ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required search fields.', 'amadeus-flight-search' ) ) );
            return;
        }

        // --- STEP 1: Get Hotel IDs from City Code ---
        $hotel_list_result = $this->amadeus_api->get_hotel_ids_by_city( $city_code );

        if ( is_wp_error( $hotel_list_result ) || empty($hotel_list_result['data']) ) {
            wp_send_json_error( array( 'message' => __('Could not find any hotels in the selected city.', 'amadeus-flight-search') ) );
            return;
        }

        // Extract just the hotelId from the response
        $all_hotel_ids = wp_list_pluck( $hotel_list_result['data'], 'hotelId' );

        if ( empty($all_hotel_ids) ) {
            wp_send_json_error( array( 'message' => __('No hotel IDs found to search for offers.', 'amadeus-flight-search') ) );
            return;
        }

        // --- STEP 2: Limit the number of hotels to avoid URI length issues ---
        // Take only the first batch of hotels or use pagination/batching
        $hotel_ids = array_slice( $all_hotel_ids, 0, self::MAX_HOTELS_PER_REQUEST );
        
        // Log for debugging
        error_log( sprintf( 
            'Hotel search: Found %d total hotels, searching offers for first %d hotels', 
            count($all_hotel_ids), 
            count($hotel_ids) 
        ));

        // --- STEP 3: Get Offers for the limited Hotel IDs ---
        $offers_result = $this->amadeus_api->search_hotel_offers( $hotel_ids, $check_in_date, $check_out_date, $adults );

        if ( is_wp_error( $offers_result ) ) {
            // If still getting URI length error, try with even fewer hotels
            if ( strpos( $offers_result->get_error_message(), 'exceeds 2048 bytes' ) !== false ) {
                $reduced_hotel_ids = array_slice( $hotel_ids, 0, 20 );
                error_log( 'Retrying with only ' . count($reduced_hotel_ids) . ' hotels due to URI length limit' );
                
                $offers_result = $this->amadeus_api->search_hotel_offers( $reduced_hotel_ids, $check_in_date, $check_out_date, $adults );
                
                if ( is_wp_error( $offers_result ) ) {
                    wp_send_json_error( array( 'message' => $offers_result->get_error_message() ) );
                    return;
                }
            } else {
                wp_send_json_error( array( 'message' => $offers_result->get_error_message() ) );
                return;
            }
        }

        if ( isset( $offers_result['data'] ) && !empty($offers_result['data']) ) {
            // Add metadata about the search
            $response_data = array(
                'offers' => $offers_result['data'],
                'meta' => array(
                    'total_hotels_in_city' => count($all_hotel_ids),
                    'hotels_searched' => count($hotel_ids),
                    'offers_found' => count($offers_result['data'])
                )
            );
            wp_send_json_success( $response_data );
        } else {
            wp_send_json_error( array( 'message' => __('No available hotel offers found for your criteria.', 'amadeus-flight-search') ) );
        }
    }

    /**
     * Alternative method: Search hotels with pagination support
     */
    public function search_hotels_paginated() {
        if ( ! Amadeus_Flight_Search_Settings::get_setting('hotel_search_enabled') ) {
            wp_send_json_error( array( 'message' => 'Hotel search feature is disabled.' ) );
            return;
        }
        
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        $params = isset( $_POST['params'] ) ? wp_unslash( $_POST['params'] ) : array();

        $city_code = isset( $params['cityCode'] ) ? sanitize_text_field( $params['cityCode'] ) : '';
        $check_in_date = isset( $params['checkInDate'] ) ? sanitize_text_field( $params['checkInDate'] ) : '';
        $check_out_date = isset( $params['checkOutDate'] ) ? sanitize_text_field( $params['checkOutDate'] ) : '';
        $adults = isset( $params['adults'] ) ? absint( $params['adults'] ) : 1;
        $page = isset( $params['page'] ) ? absint( $params['page'] ) : 1;

        if ( empty( $city_code ) || empty( $check_in_date ) || empty( $check_out_date ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required search fields.', 'amadeus-flight-search' ) ) );
            return;
        }

        // Get all hotel IDs for the city
        $hotel_list_result = $this->amadeus_api->get_hotel_ids_by_city( $city_code );

        if ( is_wp_error( $hotel_list_result ) || empty($hotel_list_result['data']) ) {
            wp_send_json_error( array( 'message' => __('Could not find any hotels in the selected city.', 'amadeus-flight-search') ) );
            return;
        }

        $all_hotel_ids = wp_list_pluck( $hotel_list_result['data'], 'hotelId' );
        $total_hotels = count($all_hotel_ids);
        $hotels_per_page = self::MAX_HOTELS_PER_REQUEST;
        
        // Calculate pagination
        $offset = ($page - 1) * $hotels_per_page;
        $hotel_ids = array_slice( $all_hotel_ids, $offset, $hotels_per_page );
        
        if ( empty($hotel_ids) ) {
            wp_send_json_error( array( 'message' => __('No more hotels to search.', 'amadeus-flight-search') ) );
            return;
        }

        // Search for offers
        $offers_result = $this->amadeus_api->search_hotel_offers( $hotel_ids, $check_in_date, $check_out_date, $adults );

        if ( is_wp_error( $offers_result ) ) {
            wp_send_json_error( array( 'message' => $offers_result->get_error_message() ) );
            return;
        }

        $response_data = array(
            'offers' => isset( $offers_result['data'] ) ? $offers_result['data'] : array(),
            'pagination' => array(
                'current_page' => $page,
                'total_hotels' => $total_hotels,
                'hotels_per_page' => $hotels_per_page,
                'total_pages' => ceil( $total_hotels / $hotels_per_page ),
                'has_more' => ($offset + $hotels_per_page) < $total_hotels
            )
        );

        wp_send_json_success( $response_data );
    }

    public function select_hotel() {
        if ( ! Amadeus_Flight_Search_Settings::get_setting('hotel_search_enabled') ) {
            wp_send_json_error( array( 'message' => 'Hotel search feature is disabled.' ) );
            return;
        }
        
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $hotel_offer_json = isset( $_POST['hotelOffer'] ) ? wp_unslash( $_POST['hotelOffer'] ) : '';
        if ( empty( $hotel_offer_json ) ) {
            wp_send_json_error( array( 'message' => __( 'No hotel offer data received.', 'amadeus-flight-search' ) ) );
            return;
        }

        $hotel_offer_data = json_decode( $hotel_offer_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE || !is_array($hotel_offer_data) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid hotel offer data format.', 'amadeus-flight-search' ) ) );
            return;
        }

        $user_identifier = afs_get_user_identifier();
        $transient_key = self::SELECTED_HOTEL_TRANSIENT_PREFIX . $user_identifier;

        set_transient( $transient_key, $hotel_offer_data, HOUR_IN_SECONDS );

        wp_send_json_success( array( 
            'message' => 'Hotel selected successfully.',
            'hotelData' => $hotel_offer_data
        ) );
    }
}