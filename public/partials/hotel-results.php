<?php
/**
 * Template for displaying hotel search results.
 *
 * This template is included via the [amadeus_hotel_search] shortcode
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

<div id="ahs-results-wrapper" class="afs-results-wrapper" aria-live="polite">
    <div id="ahs-hotel-results-container" class="afs-flight-results-container" style="display:none;">
        <?php // Hotel offers will be injected here by JavaScript. ?>
    </div>

    <div id="ahs-results-error-message" class="afs-results-error-message" style="display:none;">
        <?php // Error messages related to hotel results will be shown here by JavaScript. ?>
    </div>
</div>