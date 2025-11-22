<?php
/**
 * Amadeus Flight Search
 *
 * @package           Amadeus_Flight_Search
 * @author            Hessam
 * @copyright         2025 Hessam
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Amadeus Flight Search Pro
 * Plugin URI:        https://yourwebsite.com/amadeus-flight-search
 * Description:       Integrates Amadeus Self-Service Flight API for flight search and booking via Gravity Forms.
 * Version:           3.2.3
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Hessam
 * Author URI:        https://yourwebsite.com
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       amadeus-flight-search
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define Constants
 */
define( 'AFS_VERSION', '1.0.0' );
define( 'AFS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AFS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AFS_TEXT_DOMAIN', 'amadeus-flight-search' );
define( 'AFS_SETTINGS_SLUG', 'amadeus_api_settings' ); // Used for wp_options

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-amadeus-flight-search-activator.php
 */
function afs_activate() {
    // Require an activator class if you have complex activation tasks
    // require_once AFS_PLUGIN_DIR . 'includes/class-amadeus-flight-search-activator.php';
    // Amadeus_Flight_Search_Activator::activate();

    // Load settings class to add default options
    if ( ! class_exists( 'Amadeus_Flight_Search_Settings' ) ) {
        require_once AFS_PLUGIN_DIR . 'includes/class-settings.php';
    }
    Amadeus_Flight_Search_Settings::add_default_options();

    // You might want to flush rewrite rules if you add custom post types or taxonomies
    // flush_rewrite_rules();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-amadeus-flight-search-deactivator.php
 */
function afs_deactivate() {
    // Require a deactivator class if you have complex deactivation tasks
    // require_once AFS_PLUGIN_DIR . 'includes/class-amadeus-flight-search-deactivator.php';
    // Amadeus_Flight_Search_Deactivator::deactivate();

    // Clear scheduled cron jobs if any
    // wp_clear_scheduled_hook('my_hourly_event');

    // You might want to flush rewrite rules
    // flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'afs_activate' );
register_deactivation_hook( __FILE__, 'afs_deactivate' );

/**
 * Load core plugin files early.
 * Settings and Helpers are needed globally.
 */
if ( file_exists( AFS_PLUGIN_DIR . 'includes/class-settings.php' ) ) {
    require_once AFS_PLUGIN_DIR . 'includes/class-settings.php';
} else {
    // Optional: Add admin notice if critical file is missing
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p>' . esc_html__( 'Amadeus Flight Search plugin critical file missing: class-settings.php', 'amadeus-flight-search' ) . '</p></div>';
    });
    return; // Stop further execution if critical file is missing
}

if ( file_exists( AFS_PLUGIN_DIR . 'includes/helpers.php' ) ) {
    require_once AFS_PLUGIN_DIR . 'includes/helpers.php';
} else {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p>' . esc_html__( 'Amadeus Flight Search plugin critical file missing: helpers.php', 'amadeus-flight-search' ) . '</p></div>';
    });
    return;
}


/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
if ( file_exists( AFS_PLUGIN_DIR . 'includes/class-amadeus-flight-search-main.php' ) ) {
    require_once AFS_PLUGIN_DIR . 'includes/class-amadeus-flight-search-main.php';
} else {
     add_action( 'admin_notices', function() {
        echo '<div class="error"><p>' . esc_html__( 'Amadeus Flight Search plugin critical file missing: class-amadeus-flight-search-main.php', 'amadeus-flight-search' ) . '</p></div>';
    });
    return;
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_amadeus_flight_search() {
	$plugin = new Amadeus_Flight_Search_Main();
	$plugin->run();
}

// Run the plugin
run_amadeus_flight_search();

?>