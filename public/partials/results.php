<?php
/**
 * Template for displaying flight search results.
 *
 * This template is included via the [amadeus_flight_search] shortcode
 * and is populated by JavaScript after an AJAX call.
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

<div id="afs-results-wrapper" class="afs-results-wrapper" aria-live="polite">
    <?php // The h2 title for results could be added here or dynamically with JS ?>
    <div id="afs-flight-results-container" class="afs-flight-results-container">
        <?php // Flight offers will be injected here by JavaScript. ?>
        <?php // Initial message or placeholder can go here if desired, e.g.: ?>
        </div>

    <div id="afs-results-error-message" class="afs-results-error-message" style="display:none;">
        <?php // Error messages related to results will be shown here by JavaScript. ?>
    </div>

    <div id="afs-results-loading-more" class="afs-results-loading-more" style="display:none;">
         <?php // Optional: for pagination or "load more" functionality if implemented later ?>
        <p><?php esc_html_e( 'Loading more results...', 'amadeus-flight-search' ); ?></p>
    </div>
</div>