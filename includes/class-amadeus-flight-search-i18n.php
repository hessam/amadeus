<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amadeus_Flight_Search
 * @subpackage Amadeus_Flight_Search/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Amadeus_Flight_Search
 * @subpackage Amadeus_Flight_Search/includes
 * @author     Your Name <email@example.com>
 */
class Amadeus_Flight_Search_i18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			AFS_TEXT_DOMAIN, // Defined in the main plugin file (amadeus-flight-search.php)
			false,
			dirname( plugin_basename( AFS_PLUGIN_DIR ) ) . '/languages/' // Assumes languages folder is at the root of the plugin
		);
	}
}