<?php
/**
 * Template for the booking area placeholder.
 *
 * This template is included via the [amadeus_flight_booking] shortcode.
 * The Gravity Form is expected to be manually added to the page (e.g., via Elementor).
 * This template can be used to display a summary of the selected flight if desired,
 * populated by JavaScript using the localized 'selected_flight_data'.
 *
 * @package Amadeus_Flight_Search
 * @subpackage Amadeus_Flight_Search/public/partials
 * @author Your Name
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div id="afs-booking-area-wrapper" class="afs-booking-area-wrapper">
    
    <?php
    // This area is now primarily for JavaScript to potentially interact with,
    // or for you to manually add content if the shortcode is used.
    // The Gravity Form itself is NOT loaded by this template anymore.
    // JavaScript will look for the Gravity Form elsewhere on the page.
    ?>

    <div id="afs-selected-flight-summary-container" style="display:none;">
        <h3 class="afs-summary-title"><?php esc_html_e( 'Your Selected Flight Summary', 'amadeus-flight-search' ); ?></h3>
        <div id="afs-flight-summary-content">
            <p><?php esc_html_e('Loading flight details...', 'amadeus-flight-search'); ?></p>
        </div>
    </div>
    
    <div id="afs-gravity-form-prefill-status" style="display:none; margin-top: 15px; padding: 10px; border: 1px solid #ccc;">
        </div>

    <?php
        // Note to user: The Gravity Form should be added to this page manually
        // using your page builder (e.g., Elementor) or by its own shortcode.
        // This plugin will attempt to pre-fill it if flight data is available.
    ?>
</div>