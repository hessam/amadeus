<?php
/**
 * Template for the hotel search form.
 *
 * This template is included via the [amadeus_hotel_search] shortcode.
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
<div id="amadeus-hotel-search-wrapper" class="afs-wrapper">
    <form id="ahs-hotel-search-form" class="afs-flight-search-form">
        <div class="afs-form-header">
            <h2 class="afs-form-title"><?php esc_html_e( 'Search Hotels', 'amadeus-flight-search' ); ?></h2>
        </div>
        <!-- NEW: Single Grid Container for all controls -->
        <div class="ahs-controls-grid">
            <div class="afs-form-group ahs-destination-group">
                <label for="ahs-city-text" class="afs-label"><?php esc_html_e( 'Destination City', 'amadeus-flight-search' ); ?></label>
                <input type="text" id="ahs-city-text" name="ahs_city_text" class="afs-input" placeholder="<?php esc_attr_e( 'e.g. Milan', 'amadeus-flight-search' ); ?>" required>
                <input type="hidden" id="ahs-city-code" name="ahs_city_code">
            </div>
            <div class="afs-form-group ahs-checkin-group">
                <label for="ahs-checkin-date" class="afs-label"><?php esc_html_e( 'Check-in', 'amadeus-flight-search' ); ?></label>
                <input type="text" id="ahs-checkin-date" name="ahs_checkin_date" class="afs-input afs-datepicker" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'amadeus-flight-search' ); ?>" required autocomplete="off">
            </div>
            <div class="afs-form-group ahs-checkout-group">
                <label for="ahs-checkout-date" class="afs-label"><?php esc_html_e( 'Check-out', 'amadeus-flight-search' ); ?></label>
                <input type="text" id="ahs-checkout-date" name="ahs_checkout_date" class="afs-input afs-datepicker" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'amadeus-flight-search' ); ?>" required autocomplete="off">
            </div>
            <div class="afs-form-group ahs-guests-group">
                <label for="ahs-adults" class="afs-label"><?php esc_html_e( 'Guests', 'amadeus-flight-search' ); ?></label>
                <input type="number" id="ahs-adults" name="ahs_adults" class="afs-input" value="1" min="1" max="9">
            </div>
            <div class="afs-form-actions ahs-submit-group">
                <button type="submit" id="ahs-search-hotels-button" class="afs-button afs-button-primary">
                    <?php esc_html_e( 'Search', 'amadeus-flight-search' ); ?>
                    <span class="afs-spinner" style="display: none;"></span>
                </button>
            </div>
        </div>
    </form>
    <div id="ahs-loading-overlay" class="afs-loading-overlay" style="display: none;">
        <div class="afs-loading-indicator">
            <div class="afs-spinner-large"></div>
            <p><?php esc_html_e( 'Searching for the best hotels...', 'amadeus-flight-search' ); ?></p>
        </div>
    </div>
</div>