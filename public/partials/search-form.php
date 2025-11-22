<?php
/**
 * Template for the flight search form.
 *
 * This template is included via the [amadeus_flight_search] shortcode.
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
<div id="amadeus-flight-search-wrapper" class="afs-wrapper">
    <form id="afs-flight-search-form" class="afs-flight-search-form">
        <div class="afs-form-header">
            <h2 class="afs-form-title"><?php esc_html_e( 'Search Flights', 'amadeus-flight-search' ); ?></h2>
        </div>

        <div class="afs-trip-type-selector afs-form-row">
            <label class="afs-radio-label">
                <input type="radio" name="afs_trip_type" value="one_way" checked>
                <?php esc_html_e( 'One Way', 'amadeus-flight-search' ); ?>
            </label>
            <label class="afs-radio-label">
                <input type="radio" name="afs_trip_type" value="round_trip">
                <?php esc_html_e( 'Round Trip', 'amadeus-flight-search' ); ?>
            </label>
        </div>

        <div class="afs-location-inputs afs-form-row afs-grid-responsive">
            <div class="afs-form-group afs-origin-group">
                <label for="afs-origin-location-text" class="afs-label"><?php esc_html_e( 'From', 'amadeus-flight-search' ); ?></label>
                <input type="text" id="afs-origin-location-text" name="afs_origin_location_text" class="afs-input afs-location-input" placeholder="<?php esc_attr_e( 'Origin airport or city', 'amadeus-flight-search' ); ?>" required>
                <input type="hidden" id="afs-origin-location-code" name="afs_origin_location_code">
            </div>

            <div class="afs-form-group afs-destination-group">
                <label for="afs-destination-location-text" class="afs-label"><?php esc_html_e( 'To', 'amadeus-flight-search' ); ?></label>
                <input type="text" id="afs-destination-location-text" name="afs_destination_location_text" class="afs-input afs-location-input" placeholder="<?php esc_attr_e( 'Destination airport or city', 'amadeus-flight-search' ); ?>" required>
                <input type="hidden" id="afs-destination-location-code" name="afs_destination_location_code">
            </div>
        </div>

        <div class="afs-date-inputs afs-form-row afs-grid-responsive">
            <div class="afs-form-group afs-departure-date-group">
                <label for="afs-departure-date" class="afs-label"><?php esc_html_e( 'Depart', 'amadeus-flight-search' ); ?></label>
                <input type="text" id="afs-departure-date" name="afs_departure_date" class="afs-input afs-datepicker" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'amadeus-flight-search' ); ?>" required autocomplete="off">
            </div>

            <div class="afs-form-group afs-return-date-group" style="display: none;"> <label for="afs-return-date" class="afs-label"><?php esc_html_e( 'Return', 'amadeus-flight-search' ); ?></label>
                <input type="text" id="afs-return-date" name="afs_return_date" class="afs-input afs-datepicker" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'amadeus-flight-search' ); ?>" autocomplete="off">
            </div>
        </div>

        <div class="afs-passengers-class-row afs-form-row">
            <div class="afs-form-group afs-passengers-summary-group">
                <label for="afs-passengers-summary" class="afs-label"><?php esc_html_e( 'Passengers & Class', 'amadeus-flight-search' ); ?></label>
                <input type="text" id="afs-passengers-summary" name="afs_passengers_summary" class="afs-input" value="<?php esc_attr_e( '1 Adult, Economy', 'amadeus-flight-search' ); ?>" readonly>
                <input type="hidden" id="afs-travel-class" name="afs_travel_class" value="ECONOMY">
                 <input type="hidden" id="afs-adults" name="afs_adults" value="1">
                <input type="hidden" id="afs-children" name="afs_children" value="0">
                <input type="hidden" id="afs-infants" name="afs_infants" value="0">
            </div>
        </div>
        
        <div id="afs-passenger-class-panel" class="afs-passenger-class-panel" style="display: none;">
            <h4><?php esc_html_e( 'Select Passengers', 'amadeus-flight-search' ); ?></h4>
            <div class="afs-passenger-type">
                <label for="afs-adults-stepper"><?php esc_html_e( 'Adults', 'amadeus-flight-search' ); ?> <small>(12+ yrs)</small></label>
                <div class="afs-stepper">
                    <button type="button" class="afs-stepper-btn afs-stepper-minus" data-type="adults" aria-label="<?php esc_attr_e('Decrease adults', 'amadeus-flight-search'); ?>">-</button>
                    <input type="number" id="afs-adults-stepper" value="1" min="1" max="9" readonly class="afs-stepper-input">
                    <button type="button" class="afs-stepper-btn afs-stepper-plus" data-type="adults" aria-label="<?php esc_attr_e('Increase adults', 'amadeus-flight-search'); ?>">+</button>
                </div>
            </div>
            <div class="afs-passenger-type">
                <label for="afs-children-stepper"><?php esc_html_e( 'Children', 'amadeus-flight-search' ); ?> <small>(2-11 yrs)</small></label>
                <div class="afs-stepper">
                    <button type="button" class="afs-stepper-btn afs-stepper-minus" data-type="children" aria-label="<?php esc_attr_e('Decrease children', 'amadeus-flight-search'); ?>">-</button>
                    <input type="number" id="afs-children-stepper" value="0" min="0" max="8" readonly class="afs-stepper-input">
                    <button type="button" class="afs-stepper-btn afs-stepper-plus" data-type="children" aria-label="<?php esc_attr_e('Increase children', 'amadeus-flight-search'); ?>">+</button>
                </div>
            </div>
            <div class="afs-passenger-type">
                <label for="afs-infants-stepper"><?php esc_html_e( 'Infants', 'amadeus-flight-search' ); ?> <small>(under 2 yrs, on lap)</small></label>
                <div class="afs-stepper">
                    <button type="button" class="afs-stepper-btn afs-stepper-minus" data-type="infants" aria-label="<?php esc_attr_e('Decrease infants', 'amadeus-flight-search'); ?>">-</button>
                    <input type="number" id="afs-infants-stepper" value="0" min="0" max="8" readonly class="afs-stepper-input"> <button type="button" class="afs-stepper-btn afs-stepper-plus" data-type="infants" aria-label="<?php esc_attr_e('Increase infants', 'amadeus-flight-search'); ?>">+</button>
                </div>
            </div>

            <h4><?php esc_html_e( 'Select Travel Class', 'amadeus-flight-search' ); ?></h4>
            <div class="afs-travel-class-selector">
                <select id="afs-travel-class-select" name="afs_travel_class_select" class="afs-select">
                    <option value="ECONOMY" selected><?php esc_html_e( 'Economy', 'amadeus-flight-search' ); ?></option>
                    <option value="PREMIUM_ECONOMY"><?php esc_html_e( 'Premium Economy', 'amadeus-flight-search' ); ?></option>
                    <option value="BUSINESS"><?php esc_html_e( 'Business', 'amadeus-flight-search' ); ?></option>
                    <option value="FIRST"><?php esc_html_e( 'First Class', 'amadeus-flight-search' ); ?></option>
                </select>
            </div>
            <button type="button" id="afs-confirm-passengers-class" class="afs-button afs-button-secondary"><?php esc_html_e( 'Done', 'amadeus-flight-search' ); ?></button>
        </div>

        <div class="afs-form-row afs-nonstop-preference">
             <label class="afs-checkbox-label">
                <input type="checkbox" id="afs-nonstop" name="afs_nonstop" value="true">
                <?php esc_html_e( 'Search non-stop flights only', 'amadeus-flight-search' ); ?>
            </label>
        </div>


        <div class="afs-form-actions afs-form-row">
            <button type="submit" id="afs-search-flights-button" class="afs-button afs-button-primary">
                <?php esc_html_e( 'Search Flights', 'amadeus-flight-search' ); ?>
                <span class="afs-spinner" style="display: none;"></span>
            </button>
        </div>
    </form>

    <div id="afs-loading-overlay" class="afs-loading-overlay" style="display: none;">
        <div class="afs-loading-indicator">
            <div class="afs-spinner-large"></div>
            <p><?php esc_html_e( 'Searching for the best flights...', 'amadeus-flight-search' ); ?></p>
        </div>
    </div>
</div>