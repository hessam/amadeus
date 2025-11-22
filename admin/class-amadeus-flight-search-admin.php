<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Amadeus_Flight_Search
 * @subpackage Amadeus_Flight_Search/admin
 * @author     Your Name <email@example.com>
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class Amadeus_Flight_Search_Admin {

    private $plugin_name;
    private $version;
    private $settings_page_slug;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->settings_page_slug = $plugin_name . '-settings';
    }

    public function hotel_settings_section_callback() { 
        echo '<p>' . esc_html__( 'Configure settings for the hotel search feature.', 'amadeus-flight-search' ) . '</p>'; 
    }

    // Add this new method anywhere inside the Amadeus_Flight_Search_Admin class

    public function hotel_gf_mapping_section_callback() { 
        echo '<p>' . esc_html__( 'Map hotel data to your Gravity Form field IDs for hotel bookings.', 'amadeus-flight-search' ) . '</p>'; 
    }


    public function add_plugin_admin_menu() {
        add_menu_page(
            __( 'Amadeus Flight Search Settings', 'amadeus-flight-search' ),
            __( 'Flight Search', 'amadeus-flight-search' ),
            'manage_options',
            $this->settings_page_slug,
            array( $this, 'display_plugin_settings_page' ),
            'dashicons-airplane',
            76
        );
    }

    /**
     * Display the plugin settings page with a tabbed interface.
     */
    public function display_plugin_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr($this->settings_page_slug); ?>&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General', 'amadeus-flight-search'); ?></a>
                <a href="?page=<?php echo esc_attr($this->settings_page_slug); ?>&tab=gravity_forms" class="nav-tab <?php echo $active_tab == 'gravity_forms' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Gravity Forms', 'amadeus-flight-search'); ?></a>
                <a href="?page=<?php echo esc_attr($this->settings_page_slug); ?>&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Advanced', 'amadeus-flight-search'); ?></a>
                <a href="?page=<?php echo esc_attr($this->settings_page_slug); ?>&tab=hotels" class="nav-tab <?php echo $active_tab == 'hotels' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Hotels', 'amadeus-flight-search'); ?></a>
            </h2>

            <form action="options.php" method="post">
                <?php
                settings_fields( AFS_SETTINGS_SLUG );

                if ( $active_tab == 'general' ) {
                    do_settings_sections( $this->settings_page_slug . '_general' );
                } elseif ( $active_tab == 'gravity_forms' ) {
                    do_settings_sections( $this->settings_page_slug . '_gravity_forms' );
                } elseif ( $active_tab == 'hotels' ) { // <-- ADD THIS ELSEIF
                    do_settings_sections( $this->settings_page_slug . '_hotels' );
                } else {
                    do_settings_sections( $this->settings_page_slug . '_advanced' );
                }
                
                submit_button( __( 'Save Settings', 'amadeus-flight-search' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register plugin settings, sections, and fields.
     */
    public function register_settings() {
        register_setting(
            AFS_SETTINGS_SLUG,
            AFS_SETTINGS_SLUG,
            array( $this, 'sanitize_settings' )
        );

        // --- General Tab ---
        add_settings_section('afs_api_settings_section', __('Amadeus API Credentials', 'amadeus-flight-search'), array( $this, 'api_settings_section_callback'), $this->settings_page_slug . '_general');
        add_settings_field('amadeus_api_key', __('API Key', 'amadeus-flight-search'), array( $this, 'render_text_input'), $this->settings_page_slug . '_general', 'afs_api_settings_section', ['label_for' => 'amadeus_api_key', 'description' => __('Enter your Amadeus API Key.', 'amadeus-flight-search')]);
        add_settings_field('amadeus_api_secret', __('API Secret', 'amadeus-flight-search'), array( $this, 'render_text_input'), $this->settings_page_slug . '_general', 'afs_api_settings_section', ['label_for' => 'amadeus_api_secret', 'description' => __('Enter your Amadeus API Secret.', 'amadeus-flight-search'), 'type' => 'password']);
        add_settings_field('amadeus_api_environment', __('API Environment', 'amadeus-flight-search'), array( $this, 'render_select_input'), $this->settings_page_slug . '_general', 'afs_api_settings_section', ['label_for' => 'amadeus_api_environment', 'options' => ['test' => __('Test (Sandbox)', 'amadeus-flight-search'), 'production' => __('Production (Live)', 'amadeus-flight-search')], 'description' => __('Select the API environment.', 'amadeus-flight-search')]);
        add_settings_field('gf_map_return_date', __('Return Date Field ID', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_mapping_section', ['label_for' => 'gf_map_return_date']);

        add_settings_section('afs_page_settings_section', __('Page Configuration', 'amadeus-flight-search'), array( $this, 'page_settings_section_callback'), $this->settings_page_slug . '_general');
        add_settings_field('booking_page_url', __('Booking Page URL', 'amadeus-flight-search'), array( $this, 'render_text_input'), $this->settings_page_slug . '_general', 'afs_page_settings_section', ['label_for' => 'booking_page_url', 'description' => __('Enter the full URL or Page ID of the page containing the `[amadeus_flight_booking]` shortcode.', 'amadeus-flight-search')]);
        add_settings_field('currency_code', __('Currency Code', 'amadeus-flight-search'), array( $this, 'render_text_input'), $this->settings_page_slug . '_general', 'afs_page_settings_section', ['label_for' => 'currency_code', 'description' => __('Default currency code for flight searches (e.g., USD, EUR).', 'amadeus-flight-search'), 'default' => 'USD']);
        add_settings_field('fixed_dummy_price', __('Fixed Dummy Ticket Price', 'amadeus-flight-search'), array( $this, 'render_text_input'), $this->settings_page_slug . '_general', 'afs_page_settings_section', ['label_for' => 'fixed_dummy_price', 'description' => __('Enter a fixed price for all tickets (e.g., 25.00). Leave blank to use the price from the API.', 'amadeus-flight-search')]);

      
        // --- Gravity Forms Tab ---
        add_settings_section('afs_gf_config_section', __('Form Configuration', 'amadeus-flight-search'), array( $this, 'gf_config_section_callback'), $this->settings_page_slug . '_gravity_forms');
        add_settings_field('gravity_form_id', __('Gravity Form ID for Booking', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_config_section', ['label_for' => 'gravity_form_id', 'description' => __('Enter the numeric ID of the Gravity Form used for bookings.', 'amadeus-flight-search')]);

        add_settings_section('afs_gf_mapping_section', __('Gravity Form Field Mapping', 'amadeus-flight-search'), array( $this, 'gf_mapping_section_callback'), $this->settings_page_slug . '_gravity_forms');
        add_settings_field('gf_map_flight_number', __('Flight Number Field ID', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_mapping_section', ['label_for' => 'gf_map_flight_number']);
        add_settings_field('gf_map_departure_airport', __('Departure Airport Field ID', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_mapping_section', ['label_for' => 'gf_map_departure_airport']);
        add_settings_field('gf_map_departure_time', __('Departure Time Field ID', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_mapping_section', ['label_for' => 'gf_map_departure_time']);
        add_settings_field('gf_map_arrival_airport', __('Arrival Airport Field ID', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_mapping_section', ['label_for' => 'gf_map_arrival_airport']);
        add_settings_field('gf_map_arrival_time', __('Arrival Time Field ID', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_mapping_section', ['label_for' => 'gf_map_arrival_time']);
        add_settings_field('gf_map_origin_name', __('Origin Airport Name Field ID', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_mapping_section', ['label_for' => 'gf_map_origin_name']);
        add_settings_field('gf_map_destination_name', __('Destination Airport Name Field ID', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_mapping_section', ['label_for' => 'gf_map_destination_name']);
        add_settings_field('gf_map_return_origin_name', __('Return Origin Airport Name Field ID', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_mapping_section', ['label_for' => 'gf_map_return_origin_name']);
        add_settings_field('gf_map_return_destination_name', __('Return Destination Airport Name Field ID', 'amadeus-flight-search'), array( $this, 'render_number_input'), $this->settings_page_slug . '_gravity_forms', 'afs_gf_mapping_section', ['label_for' => 'gf_map_return_destination_name']);


      
        // --- Advanced Tab ---
        add_settings_section('afs_advanced_settings_section', __('Advanced Settings', 'amadeus-flight-search'), array($this, 'advanced_settings_section_callback'), $this->settings_page_slug . '_advanced');
        add_settings_field('airline_logo_base_url', __('Airline Logo Service URL', 'amadeus-flight-search'), array($this, 'render_text_input'), $this->settings_page_slug . '_advanced', 'afs_advanced_settings_section', ['label_for' => 'airline_logo_base_url', 'description' => __('Enter the base URL for the airline logo service. Use `{{iataCode}}` as a placeholder for the airline\'s IATA code.', 'amadeus-flight-search')]);
        add_settings_field('debug_mode', __('Enable Debug Mode', 'amadeus-flight-search'), array($this, 'render_checkbox_input'), $this->settings_page_slug . '_advanced', 'afs_advanced_settings_section', ['label_for' => 'debug_mode', 'description' => __('Enable detailed logging to the PHP error log (requires WP_DEBUG and WP_DEBUG_LOG to be true).', 'amadeus-flight-search')]);


        // --- START: HOTELS TAB ---
        // This section is for general hotel settings
        add_settings_section(
            'ahs_hotel_settings_section', 
            __('Hotel Search Settings', 'amadeus-flight-search'), 
            array($this, 'hotel_settings_section_callback'), 
            $this->settings_page_slug . '_hotels'
        );
        
        add_settings_field(
            'hotel_search_enabled', 
            __('Enable Hotel Search', 'amadeus-flight-search'), 
            array($this, 'render_checkbox_input'), 
            $this->settings_page_slug . '_hotels', 
            'ahs_hotel_settings_section', 
            ['label_for' => 'hotel_search_enabled', 'description' => __('Enable the `[amadeus_hotel_search]` shortcode and feature.', 'amadeus-flight-search')]
        );
        
        add_settings_field(
            'hotel_fixed_dummy_price', 
            __('Hotel Fixed Dummy Price', 'amadeus-flight-search'), 
            array( $this, 'render_text_input'), 
            $this->settings_page_slug . '_hotels', 
            'ahs_hotel_settings_section', 
            ['label_for' => 'hotel_fixed_dummy_price', 'description' => __('Enter a fixed price per night for all hotels (e.g., 25.00). Leave blank to use the price from the API.', 'amadeus-flight-search')]
        );


        add_settings_field(
            'hotel_booking_page_url', // <-- ADD THIS BLOCK
            __('Hotel Booking Page URL', 'amadeus-flight-search'), 
            array($this, 'render_text_input'), 
            $this->settings_page_slug . '_hotels', 
            'ahs_hotel_settings_section', 
            ['label_for' => 'hotel_booking_page_url', 'description' => __('Enter the full URL or Page ID of the page containing your hotel booking Gravity Form.', 'amadeus-flight-search')]
        );
        
        
        // This new section is for Gravity Forms mapping for hotels
        add_settings_section(
            'ahs_gf_mapping_section', 
            __('Hotel Gravity Form Field Mapping', 'amadeus-flight-search'), 
            array( $this, 'hotel_gf_mapping_section_callback'), 
            $this->settings_page_slug . '_hotels'
        );
        
        add_settings_field(
            'hotel_gravity_form_id', 
            __('Gravity Form ID for Hotels', 'amadeus-flight-search'), 
            array( $this, 'render_number_input'), 
            $this->settings_page_slug . '_hotels', 
            'ahs_gf_mapping_section', 
            ['label_for' => 'hotel_gravity_form_id', 'description' => __('Enter the numeric ID of the Gravity Form used for hotel bookings.', 'amadeus-flight-search')]
        );
        
        add_settings_field('gf_map_hotel_name', __('Hotel Name Field ID', 'amadeus-flight-search'), array($this, 'render_number_input'), $this->settings_page_slug . '_hotels', 'ahs_gf_mapping_section', ['label_for' => 'gf_map_hotel_name']);
        add_settings_field('gf_map_hotel_city', __('City Name Field ID', 'amadeus-flight-search'), array($this, 'render_number_input'), $this->settings_page_slug . '_hotels', 'ahs_gf_mapping_section', ['label_for' => 'gf_map_hotel_city']);
        add_settings_field('gf_map_hotel_check_in', __('Check-in Date Field ID', 'amadeus-flight-search'), array($this, 'render_number_input'), $this->settings_page_slug . '_hotels', 'ahs_gf_mapping_section', ['label_for' => 'gf_map_hotel_check_in']);
        add_settings_field('gf_map_hotel_check_out', __('Check-out Date Field ID', 'amadeus-flight-search'), array($this, 'render_number_input'), $this->settings_page_slug . '_hotels', 'ahs_gf_mapping_section', ['label_for' => 'gf_map_hotel_check_out']);
        add_settings_field('gf_map_hotel_guests', __('Guests/Adults Field ID', 'amadeus-flight-search'), array($this, 'render_number_input'), $this->settings_page_slug . '_hotels', 'ahs_gf_mapping_section', ['label_for' => 'gf_map_hotel_guests']);
        
        // --- END: HOTELS TAB ---


    }

    public function api_settings_section_callback() { echo '<p>' . esc_html__( 'Enter your Amadeus API credentials below.', 'amadeus-flight-search' ) . '</p>'; }
    public function page_settings_section_callback() { echo '<p>' . esc_html__( 'Configure the core pages and currency used by the plugin.', 'amadeus-flight-search' ) . '</p>'; }
    public function gf_config_section_callback() { echo '<p>' . esc_html__( 'Specify which Gravity Form to use for the booking process.', 'amadeus-flight-search' ) . '</p>'; }
    public function gf_mapping_section_callback() { echo '<p>' . esc_html__( 'Map flight data to your Gravity Form field IDs. Find the ID next to the field label when editing your form.', 'amadeus-flight-search' ) . '</p>'; }
    public function advanced_settings_section_callback() { echo '<p>' . esc_html__( 'Configure advanced settings for the plugin.', 'amadeus-flight-search' ) . '</p>'; }

    public function render_text_input( $args ) {
        $options = get_option( AFS_SETTINGS_SLUG, Amadeus_Flight_Search_Settings::get_default_options() );
        $value = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : (isset($args['default']) ? $args['default'] : '');
        $type = isset( $args['type'] ) ? $args['type'] : 'text';
        printf(
            '<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" />',
            esc_attr($type), esc_attr($args['label_for']), esc_attr(AFS_SETTINGS_SLUG), esc_attr($args['label_for']), esc_attr($value)
        );
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    public function render_number_input( $args ) {
        $options = get_option( AFS_SETTINGS_SLUG, Amadeus_Flight_Search_Settings::get_default_options() );
        $value = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
        printf(
            '<input type="number" id="%s" name="%s[%s]" value="%s" class="small-text" />',
            esc_attr($args['label_for']), esc_attr(AFS_SETTINGS_SLUG), esc_attr($args['label_for']), esc_attr($value)
        );
    }
    
    public function render_select_input( $args ) {
        $options = get_option( AFS_SETTINGS_SLUG, Amadeus_Flight_Search_Settings::get_default_options() );
        $value = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
        echo '<select id="'.esc_attr($args['label_for']).'" name="'.esc_attr(AFS_SETTINGS_SLUG.'['.$args['label_for'].']').'">';
        foreach ($args['options'] as $val => $label) {
            echo '<option value="'.esc_attr($val).'" '.selected($value, $val, false).'>'.esc_html($label).'</option>';
        }
        echo '</select>';
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_checkbox_input( $args ) {
        $options = get_option( AFS_SETTINGS_SLUG, Amadeus_Flight_Search_Settings::get_default_options() );
        $checked = isset( $options[ $args['label_for'] ] ) ? (bool) $options[ $args['label_for'] ] : false;
        echo '<input type="checkbox" id="'.esc_attr($args['label_for']).'" name="'.esc_attr(AFS_SETTINGS_SLUG.'['.$args['label_for'].']').'" value="1" '.checked($checked, true, false).' />';
        if (isset($args['description'])) {
            printf('<label for="%s"><p class="description">%s</p></label>', esc_attr($args['label_for']), esc_html($args['description']));
        }
    }

    public function sanitize_settings( $input ) {
        $new_input = get_option( AFS_SETTINGS_SLUG, [] ); // Start with existing settings

        $default_options = Amadeus_Flight_Search_Settings::get_default_options();

        // Sanitize text/URL fields from the current submission
        $text_fields = ['amadeus_api_key', 'amadeus_api_secret', 'search_results_page_url', 'booking_page_url', 'currency_code', 'airline_logo_base_url', 'fixed_dummy_price', 'hotel_fixed_dummy_price', 'hotel_booking_page_url'];
        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                if (strpos($field, '_url') !== false) {
                    $new_input[$field] = is_numeric($input[$field]) ? absint($input[$field]) : esc_url_raw(trim($input[$field]));
                } else {
                    $new_input[$field] = sanitize_text_field($input[$field]);
                }
            }
        }

        // Sanitize number fields from the current submission
        $number_fields = ['gravity_form_id', 'gf_map_flight_number', 'gf_map_departure_airport', 'gf_map_departure_time', 'gf_map_arrival_airport', 'gf_map_arrival_time', 'gf_map_origin_name', 'gf_map_destination_name', 'gf_map_return_origin_name', 'gf_map_return_destination_name', 'gf_map_return_date', 'hotel_gravity_form_id', 'gf_map_hotel_name', 'gf_map_hotel_city', 'gf_map_hotel_check_in', 'gf_map_hotel_check_out', 'gf_map_hotel_guests'];
        foreach ($number_fields as $field) {
            if (isset($input[$field])) {
                $new_input[$field] = absint($input[$field]);
            }
        }

        // Sanitize select fields
        if (isset($input['amadeus_api_environment'])) {
            if (array_key_exists($input['amadeus_api_environment'], ['test' => '', 'production' => ''])) {
                $new_input['amadeus_api_environment'] = $input['amadeus_api_environment'];
            }
        }

        // Sanitize checkbox
        $new_input['debug_mode'] = isset($input['debug_mode']);
        $new_input['hotel_search_enabled'] = isset($input['hotel_search_enabled']); // <-- ADD THIS

        // **FIX**: Only validate currency code if it's set to prevent warnings
        if (isset($new_input['currency_code']) && !preg_match('/^[A-Z]{3}$/', $new_input['currency_code'])) {
            $new_input['currency_code'] = $default_options['currency_code'];
            add_settings_error('currency_code', 'invalid_currency_code', __('Invalid currency code. Please use a 3-letter uppercase format (e.g., USD).', 'amadeus-flight-search'), 'error');
        }

        return $new_input;
    }
}
