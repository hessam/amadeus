<?php
/**
 * Helper functions for the Amadeus Flight Search plugin.
 *
 * @package Amadeus_Flight_Search
 * @subpackage Amadeus_Flight_Search/includes
 * @author Your Name
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Custom debug logging function.
 *
 * Logs messages to the PHP error log if WP_DEBUG and WP_DEBUG_LOG are enabled,
 * and if the plugin's debug mode setting is active.
 *
 * @since 1.0.0
 * @param mixed  $message The message or data to log. Can be a string, array, or object.
 * @param string $context Optional. A context for the log message (e.g., class name, function name).
 * Default is 'Amadeus Flight Search'.
 * @return void
 */
if ( ! function_exists( 'afs_debug_log' ) ) {
	function afs_debug_log( $message, $context = 'Amadeus Flight Search' ) {
		// Check if WP_DEBUG and WP_DEBUG_LOG are enabled
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		// Check if plugin's debug mode is enabled
        // Ensure Amadeus_Flight_Search_Settings class is available
        if ( ! class_exists( 'Amadeus_Flight_Search_Settings' ) ) {
            // Attempt to load it if not already loaded. This is a fallback.
            // Ideally, it's loaded before helpers.php by the main plugin file.
            $settings_file = AFS_PLUGIN_DIR . 'includes/class-settings.php';
            if ( file_exists( $settings_file ) ) {
                require_once $settings_file;
            } else {
                // Settings class not found, cannot check debug_mode. Log a warning.
                error_log( '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] Amadeus Flight Search Debug: NOTICE - Amadeus_Flight_Search_Settings class not found. Cannot check plugin debug mode setting for logging.' );
                // Optionally, you could decide to log anyway or not log at all in this scenario.
                // For now, let's not log if we can't confirm the setting.
                return;
            }
        }

		if ( ! Amadeus_Flight_Search_Settings::get_setting( 'debug_mode', false ) ) {
			return;
		}

		// Format the message
		$formatted_message = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] ';
		if ( ! empty( $context ) ) {
			$formatted_message .= '[' . sanitize_text_field( (string) $context ) . '] ';
		}

		if ( is_array( $message ) || is_object( $message ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$formatted_message .= print_r( $message, true );
		} else {
			$formatted_message .= (string) $message;
		}

		// Log the message
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $formatted_message );
	}
}

/**
 * Helper function to get a user identifier.
 * Used for creating transient keys.
 *
 * This function is also present in Amadeus_AJAX class.
 * It's placed here if needed in other non-AJAX contexts,
 * but ensure consistency or choose a single source of truth.
 * For now, keeping it DRY by potentially calling the AJAX class method if available,
 * or duplicating if this helper is loaded before the AJAX class.
 *
 * Given the loading order, it's safer to duplicate or make this the primary source.
 * Let's make this the primary source.
 *
 * @since 1.0.0
 * @return string The user identifier.
 */
if ( ! function_exists( 'afs_get_user_identifier' ) ) {
    function afs_get_user_identifier() {
        if ( is_user_logged_in() ) {
            return 'user_' . get_current_user_id();
        } else {
            $cookie_name = 'amadeus_session_id';
            if ( isset( $_COOKIE[ $cookie_name ] ) && ! empty( $_COOKIE[ $cookie_name ] ) ) {
                // Sanitize the cookie value - it should be a UUID or similar.
                // A simple alphanumeric check, or more robust validation if needed.
                if ( preg_match( '/^[a-zA-Z0-9\-]+$/', $_COOKIE[ $cookie_name ] ) ) {
                     return 'guest_' . sanitize_text_field( $_COOKIE[ $cookie_name ] );
                }
            }
            // If no valid cookie, generate a new one (though setting it is handled elsewhere)
            // For transient key generation, a temporary UUID can be used if cookie not set yet.
            // However, the cookie should be set by Amadeus_Flight_Search_Main on init.
            // If cookie is expected to be always present for guests, this part might just be a fallback.
            if ( function_exists('wp_generate_uuid4') ) {
                return 'guest_' . wp_generate_uuid4();
            } else {
                // Fallback for older WordPress versions if wp_generate_uuid4 is not available
                return 'guest_' . uniqid( rand(), true );
            }
        }
    }
}

/**
 * Helper function to get page URL by ID or return the string if it's already a URL.
 *
 * @since 1.0.0
 * @param string|int $page_setting The page ID or URL string from settings.
 * @return string The resolved URL or an empty string if invalid.
 */
if ( ! function_exists( 'afs_get_page_url_from_setting' ) ) {
    function afs_get_page_url_from_setting( $page_setting ) {
        if ( empty( $page_setting ) ) {
            return '';
        }

        if ( is_numeric( $page_setting ) ) { // It's a page ID
            $url = get_permalink( absint( $page_setting ) );
            return $url ? esc_url( $url ) : '';
        } elseif ( filter_var( $page_setting, FILTER_VALIDATE_URL ) ) { // It's a URL
            return esc_url( $page_setting );
        }
        return ''; // Invalid setting
    }
}

?>