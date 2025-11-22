<?php
/**
 * Manages plugin settings.
 *
 * @package Amadeus_Flight_Search
 * @subpackage Amadeus_Flight_Search/includes
 * @author Your Name
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class Amadeus_Flight_Search_Settings {

    /**
     * The single instance of the class.
     * @var Amadeus_Flight_Search_Settings
     * @since 1.0.0
     */
    private static $instance = null;

    /**
     * The array of plugin settings.
     * @var array
     * @since 1.0.0
     */
    private static $settings = null;

    /**
     * The option key in the wp_options table.
     * @var string
     * @since 1.0.0
     */
    const OPTION_NAME = AFS_SETTINGS_SLUG; // Defined in main plugin file

    /**
     * Main Amadeus_Flight_Search_Settings Instance.
     *
     * Ensures only one instance of Amadeus_Flight_Search_Settings is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return Amadeus_Flight_Search_Settings - Main instance.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     * Private to prevent direct object creation.
     * @since 1.0.0
     */
    private function __construct() {
        // Optionally, load settings on instantiation if not already loaded
        // self::get_settings();
    }

    /**
     * Get all plugin settings.
     *
     * Retrieves all settings from the database and caches them.
     *
     * @since 1.0.0
     * @param bool $force_refresh Whether to force a refresh from the database.
     * @return array The array of settings.
     */
    public static function get_settings( $force_refresh = false ) {
        if ( null === self::$settings || $force_refresh ) {
            self::$settings = get_option( self::OPTION_NAME, array() );
            // Merge with defaults to ensure all keys exist
            self::$settings = wp_parse_args( self::$settings, self::get_default_options() );
        }
        return self::$settings;
    }

    /**
     * Get a specific setting.
     *
     * @since 1.0.0
     * @param string $key The setting key.
     * @param mixed  $default Optional. Default value if the setting is not found.
     * @return mixed The value of the setting, or $default if not found.
     */
    public static function get_setting( $key, $default = null ) {
        $settings = self::get_settings();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Update plugin settings.
     *
     * @since 1.0.0
     * @param array $new_settings An array of settings to update.
     * @return bool True if options were updated, false otherwise.
     */
    public static function update_settings( $new_settings ) {
        $current_settings = self::get_settings();
        $updated_settings = wp_parse_args( $new_settings, $current_settings );

        $result = update_option( self::OPTION_NAME, $updated_settings );
        if ( $result ) {
            self::$settings = $updated_settings; // Update static cache
        }
        return $result;
    }

    /**
     * Get the default plugin options.
     *
     * @since 1.0.0
     * @return array Default plugin options.
     */
    public static function get_default_options() {
        return array(
            // General Settings
            'amadeus_api_key'           => '',
            'amadeus_api_secret'        => '',
            'amadeus_api_environment'   => 'test', // 'test' or 'production'
            'search_results_page_url'   => '',     // Can be a page ID or full URL
            'booking_page_url'          => '',     // Can be a page ID or full URL
            'currency_code'             => 'USD',
            'debug_mode'                => false,

            // Gravity Form Settings
            'gravity_form_id'           => '',
            'gf_map_flight_number'      => '',
            'gf_map_departure_airport'  => '',
            'gf_map_departure_time'     => '',
            'gf_map_arrival_airport'    => '',
            'gf_map_arrival_time'       => '',
            'gf_map_origin_name'        => '',
            'gf_map_destination_name'   => '',
            'gf_map_return_origin_name'     => '',
            'gf_map_return_destination_name'  => '',


            // Advanced Settings
            'airline_logo_base_url'     => 'https://dummyticket247.com/airline-logo?logo={{iataCode}}.png&v=2025',
            'enable_hotel_search'       => false, // Feature flag for hotel search functionality
        );
    }

    /**
     * Add default options upon plugin activation.
     *
     * This function is typically called from the plugin activation hook.
     * It ensures that the plugin has a set of default options when it's first activated.
     *
     * @since 1.0.0
     */
    public static function add_default_options() {
        // Check if options already exist
        if ( false === get_option( self::OPTION_NAME ) ) {
            add_option( self::OPTION_NAME, self::get_default_options() );
        } else {
            // If options exist, merge with defaults to add any new default keys
            // that might have been added in a plugin update.
            $existing_options = get_option( self::OPTION_NAME );
            $updated_options = wp_parse_args( $existing_options, self::get_default_options() );
            // If there were new defaults added, update the option
            if ($updated_options !== $existing_options) {
                update_option(self::OPTION_NAME, $updated_options);
            }
        }
    }

    /**
     * Get a specific default option value.
     *
     * @since 1.0.0
     * @param string $key The key of the default option.
     * @return mixed|null The value of the default option, or null if not found.
     */
    public static function get_default_option( $key ) {
        $defaults = self::get_default_options();
        return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
    }
}
