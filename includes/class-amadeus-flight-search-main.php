<?php
/**
 * The file that defines the core plugin class
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

class Amadeus_Flight_Search_Main {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		if ( defined( 'AFS_VERSION' ) ) {
			$this->version = AFS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'amadeus-flight-search';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_ajax_hooks(); // AJAX hooks are still needed for search, etc.
		$this->define_shortcodes();
	}

	private function load_dependencies() {
		require_once AFS_PLUGIN_DIR . 'includes/class-amadeus-flight-search-loader.php';
		require_once AFS_PLUGIN_DIR . 'includes/class-amadeus-flight-search-i18n.php';
		require_once AFS_PLUGIN_DIR . 'admin/class-amadeus-flight-search-admin.php';
		require_once AFS_PLUGIN_DIR . 'includes/class-amadeus-api.php';
		require_once AFS_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once AFS_PLUGIN_DIR . 'includes/class-hotel-ajax.php'; 
		$this->loader = new Amadeus_Flight_Search_Loader();
	}

	private function set_locale() {
		$plugin_i18n = new Amadeus_Flight_Search_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	private function define_admin_hooks() {
		$plugin_admin = new Amadeus_Flight_Search_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		// $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' ); // Uncomment if admin CSS needed
		// $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' ); // Uncomment if admin JS needed
	}

	private function define_public_hooks() {
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_scripts' );
        $this->loader->add_action( 'wp_enqueue_scripts', $this, 'maybe_enqueue_hotel_booking_scripts' );
        $this->loader->add_action( 'init', $this, 'set_guest_session_cookie', 1 );
	}

	private function define_ajax_hooks() {
		$ajax_handler = new Amadeus_AJAX();

		// Search Flights
		$this->loader->add_action( 'wp_ajax_afs_search_flights', $ajax_handler, 'search_flights' );
		$this->loader->add_action( 'wp_ajax_nopriv_afs_search_flights', $ajax_handler, 'search_flights' );

		// Select Flight
		$this->loader->add_action( 'wp_ajax_afs_select_flight', $ajax_handler, 'select_flight' );
		$this->loader->add_action( 'wp_ajax_nopriv_afs_select_flight', $ajax_handler, 'select_flight' );

    	// **NEW**: Add AJAX hook to clear the transient after use.
        $this->loader->add_action( 'wp_ajax_afs_clear_flight_transient', $ajax_handler, 'clear_flight_transient' );
        $this->loader->add_action( 'wp_ajax_nopriv_afs_clear_flight_transient', $ajax_handler, 'clear_flight_transient' );

        // --- START: ADD HOTEL AJAX HOOKS (Feature Flagged) ---
        if (Amadeus_Flight_Search_Settings::get_setting('enable_hotel_search', false)) {
            $hotel_ajax_handler = new Amadeus_Hotel_AJAX();
            
            // Hotel location autocomplete
            $this->loader->add_action( 'wp_ajax_ahs_search_hotel_locations', $hotel_ajax_handler, 'search_hotel_locations' );
            $this->loader->add_action( 'wp_ajax_nopriv_ahs_search_hotel_locations', $hotel_ajax_handler, 'search_hotel_locations' );
            
            // Hotel offers search
            $this->loader->add_action( 'wp_ajax_ahs_search_hotels', $hotel_ajax_handler, 'search_hotels' );
            $this->loader->add_action( 'wp_ajax_nopriv_ahs_search_hotels', $hotel_ajax_handler, 'search_hotels' );
            
            // Select Hotel (for transient and GF pre-fill)
            $this->loader->add_action( 'wp_ajax_ahs_select_hotel', $hotel_ajax_handler, 'select_hotel' );
            $this->loader->add_action( 'wp_ajax_nopriv_ahs_select_hotel', $hotel_ajax_handler, 'select_hotel' );
        }
        // --- END: ADD HOTEL AJAX HOOKS ---


	}
	

	private function define_shortcodes() {
		$this->loader->add_shortcode( 'amadeus_flight_search', $this, 'display_search_form_and_results' );
		$this->loader->add_shortcode( 'amadeus_flight_booking', $this, 'display_booking_area_placeholder' ); // Renamed for clarity
        $this->loader->add_shortcode( 'amadeus_hotel_search', $this, 'display_hotel_search_form_and_results' );
	}

	public function display_search_form_and_results() {
		ob_start();
		$search_form_path = AFS_PLUGIN_DIR . 'public/partials/search-form.php';
		$results_path = AFS_PLUGIN_DIR . 'public/partials/results.php';

		if ( file_exists( $search_form_path ) ) include $search_form_path;
		else afs_debug_log('Search form template missing: ' . $search_form_path);
		
		if ( file_exists( $results_path ) ) include $results_path;
		else afs_debug_log('Results template missing: ' . $results_path);
		
		return ob_get_clean();
	}

		public function display_booking_area_placeholder() {
		// This shortcode no longer loads the form.
		// It can be used to output a placeholder div if needed by JS, or a summary.
		// For now, it will just include the simplified booking-form.php template.
		ob_start();
		$booking_form_path = AFS_PLUGIN_DIR . 'public/partials/booking-form.php';
		if ( file_exists( $booking_form_path ) ) {
			include $booking_form_path;
		} else {
            afs_debug_log('Booking form placeholder template missing: ' . $booking_form_path);
			echo '<p>' . esc_html__( 'Booking area placeholder template not found.', 'amadeus-flight-search' ) . '</p>';
		}
		return ob_get_clean();
	}

	public function enqueue_styles() {
		// Enqueue simplified jQuery UI CSS without background images to avoid 404 errors
		wp_enqueue_style( 'jquery-ui-datepicker-style', AFS_PLUGIN_URL . 'public/css/vendor/jquery-ui-datepicker.css', array(), '1.0.0' );

		// --- Cache-Busting for your main plugin CSS file ---
		// Get the server path to the CSS file.
		$css_file_path = AFS_PLUGIN_DIR . 'public/css/amadeus-flight-search.css';
		// Get the file's last modified time as the version number. Fallback to current plugin version.
		$css_version = file_exists( $css_file_path ) ? filemtime( $css_file_path ) : $this->version;

		// Enqueue the style with the new dynamic version.
		wp_enqueue_style( $this->plugin_name, AFS_PLUGIN_URL . 'public/css/amadeus-flight-search.css', array('jquery-ui-datepicker-style'), $css_version, 'all' );
	}



    /**
     * Checks if the current page is the hotel booking page and enqueues scripts if necessary.
     * This ensures the pre-fill logic runs on the correct page.
     */
    public function maybe_enqueue_hotel_booking_scripts() {
        // Feature flag check
        if (!Amadeus_Flight_Search_Settings::get_setting('enable_hotel_search', false)) {
            return;
        }
        
        // Get the configured hotel booking page URL/ID
        $hotel_booking_page_setting = Amadeus_Flight_Search_Settings::get_setting('hotel_booking_page_url');
        if ( empty( $hotel_booking_page_setting ) ) {
            return; // Exit if no booking page is set
        }
    
        // Get the ID of the current page being viewed
        $current_page_id = get_queried_object_id();
    
        // Check if the setting is a numeric ID and if it matches the current page
        if ( is_numeric( $hotel_booking_page_setting ) && absint( $hotel_booking_page_setting ) === $current_page_id ) {
            $this->enqueue_hotel_scripts();
        }
        // Alternatively, check if the setting is a URL and if it matches the current URL
        elseif ( ! is_numeric( $hotel_booking_page_setting ) ) {
            $booking_page_url = afs_get_page_url_from_setting( $hotel_booking_page_setting );
            $current_url = home_url( add_query_arg( null, null ) );
            if ( $booking_page_url === $current_url ) {
                $this->enqueue_hotel_scripts();
            }
        }
    }
    public function enqueue_scripts() {
        // --- 1. Enqueue Core & Vendor Scripts ---
        // These are your existing scripts, now with cache-busting on your custom file.
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_script( 'jquery-ui-autocomplete' );
        
        // Keep your Turkish datepicker file
        $datepicker_tr_path = AFS_PLUGIN_DIR . 'public/js/datepicker-tr.js';
        $datepicker_tr_version = file_exists( $datepicker_tr_path ) ? filemtime( $datepicker_tr_path ) : $this->version;
        wp_enqueue_script(
            'jquery-ui-datepicker-tr',
            AFS_PLUGIN_URL . 'public/js/datepicker-tr.js',
            array( 'jquery-ui-datepicker' ),
            $datepicker_tr_version,
            true
        );

        // --- 2. Load the NEW Master Airport Data File ---
        // This REPLACES the old 'popular-airports.json' and 'tr-en-world-cities.json'
        $airports_data = array();
        $airports_file_path = AFS_PLUGIN_DIR . 'data/autocomplete-airports.json';
        if ( file_exists( $airports_file_path ) && is_readable( $airports_file_path ) ) {
            $file_contents = file_get_contents( $airports_file_path );
            if ( $file_contents !== false ) {
                $airports_data = json_decode( $file_contents, true );
                if ( json_last_error() !== JSON_ERROR_NONE ) {
                    afs_debug_log( 'Error decoding airports JSON: ' . json_last_error_msg(), __CLASS__ );
                    $airports_data = array();
                }
            } else {
                afs_debug_log( 'Failed to read airports file: ' . $airports_file_path, __CLASS__ );
            }
        } else {
            afs_debug_log( 'Airports file not found or not readable: ' . $airports_file_path, __CLASS__ );
        }

        // --- 3. Enqueue Your Main JavaScript File ---
        wp_enqueue_script(
            $this->plugin_name,
            AFS_PLUGIN_URL . 'public/js/amadeus-flight-search.js',
            array( 'jquery', 'jquery-ui-datepicker', 'jquery-ui-autocomplete', 'jquery-ui-datepicker-tr' ),
            filemtime( AFS_PLUGIN_DIR . 'public/js/amadeus-flight-search.js' ),
            true
        );

        // --- 4. Build the Data Array for JavaScript ---
        $localized_data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'amadeus_flight_search_nonce' ),
            'airports' => $airports_data,
            'text'     => array(
              'loading'               => 'Yükleniyor...',
              'error_generic'         => 'Beklenmedik bir hata oluştu. Lütfen tekrar deneyin.',
              'error_no_flights_found'=> 'Arama kriterlerinize uygun uçuş bulunamadı.',
              'select_flight'         => 'Uçuş Seç',
              'outbound_flight'       => 'Gidiş',
              'return_flight'         => 'Dönüş',
              'total_duration'        => 'Toplam Süre',
              'aircraft'              => 'Uçak',
              'duration'              => 'Süre',
              'layover'               => 'Aktarma',
              // --- Turkish translations for new strings ---
              'adult'                 => 'Yetişkin',
              'adults'                => 'Yetişkin',
              'child'                 => 'Çocuk',
              'children'              => 'Çocuk',
              'infant'                => 'Bebek',
              'infants'               => 'Bebek',
              'summary_outbound'      => 'Gidiş:',
              'summary_departure'     => 'Kalkış:',
              'summary_return'        => 'Dönüş:',
              'summary_return_departure' => 'Dönüş Kalkışı:',
              'summary_total_price'   => 'Toplam Fiyat:',
              'summary_passengers'    => 'Yolcular:',
              'select_offer'          => 'Seç ve İlerle',
              'stops_direct'          => 'Direkt',
              'stops_one'             => '1 aktarma',
              'stops_multi'           => '%d aktarma', // %d sayı için yer tutucu
              'layover_in'            => 'Aktarma yeri',
              'error_no_hotels_found'   => __('Aradığınız kriterlere uygun otel bulunamadı.', 'amadeus-flight-search'),
              'select_hotel'            => __('Otel Seç', 'amadeus-flight-search'),
              'per_night'               => __('per night', 'amadeus-flight-search'),
              'error_missing_fields'    => __('Please fill all required fields.', 'amadeus-flight-search'),
              'error_no_booking_page'   => __('Hotel booking page URL is not configured in plugin settings.', 'amadeus-flight-search'),
              'error_select_hotel'      => __('Could not select hotel.', 'amadeus-flight-search'),
          ),
            'settings' => array(
                // This URL is still needed for the redirect
                'booking_page_url'          => afs_get_page_url_from_setting( Amadeus_Flight_Search_Settings::get_setting('booking_page_url') ),
                // NEW: Pass the configurable settings to JavaScript
                'airline_logo_url_template' => Amadeus_Flight_Search_Settings::get_setting('airline_logo_base_url'),
                'fixed_dummy_price'         => Amadeus_Flight_Search_Settings::get_setting('fixed_dummy_price'), // <-- ADD THIS LINE
                'currency_code'             => Amadeus_Flight_Search_Settings::get_setting('currency_code', 'USD'), // <-- ADD THIS
                'gf_mappings' => array(
                    'flight_number'          => Amadeus_Flight_Search_Settings::get_setting('gf_map_flight_number'),
                    'departure_airport'      => Amadeus_Flight_Search_Settings::get_setting('gf_map_departure_airport'),
                    'departure_time'         => Amadeus_Flight_Search_Settings::get_setting('gf_map_departure_time'),
                    'arrival_airport'        => Amadeus_Flight_Search_Settings::get_setting('gf_map_arrival_airport'),
                    'arrival_time'           => Amadeus_Flight_Search_Settings::get_setting('gf_map_arrival_time'),
                    'origin_airport_name'    => Amadeus_Flight_Search_Settings::get_setting('gf_map_origin_name'),
                    'destination_airport_name' => Amadeus_Flight_Search_Settings::get_setting('gf_map_destination_name'),
                    'return_origin_airport_name'    => Amadeus_Flight_Search_Settings::get_setting('gf_map_return_origin_name'),
                    'return_destination_airport_name' => Amadeus_Flight_Search_Settings::get_setting('gf_map_return_destination_name'),
                    'return_date'                   => Amadeus_Flight_Search_Settings::get_setting('gf_map_return_date'), // <-- ADD THIS LINE

                )
            ),
            // This is no longer needed as data is passed via sessionStorage
            'selected_flight_data' => null 
        );
        
        // --- 6. Final Step: Pass all data to JavaScript ---
        wp_localize_script( $this->plugin_name, 'amadeus_vars', $localized_data );
    }

    // Add these two new methods to the class
    
    /**
     * A flag to ensure hotel scripts are only enqueued once per page load.
     * @var boolean
     */
    private static $hotel_scripts_enqueued = false;
    
    /**
     * Displays the hotel search form and results container.
     * This is the callback for the [amadeus_hotel_search] shortcode.
     */
    public function display_hotel_search_form_and_results() {
        // Only enqueue scripts if the feature is enabled in settings
        if ( ! Amadeus_Flight_Search_Settings::get_setting('hotel_search_enabled') ) {
            return '<p>' . esc_html__('Hotel search is currently disabled.', 'amadeus-flight-search') . '</p>';
        }
    
        // Enqueue hotel-specific scripts and styles
        $this->enqueue_hotel_scripts();
    
        ob_start();
        $search_form_path = AFS_PLUGIN_DIR . 'public/partials/hotel-search-form.php';
        $results_path = AFS_PLUGIN_DIR . 'public/partials/hotel-results.php';
    
        if ( file_exists( $search_form_path ) ) include $search_form_path;
        else afs_debug_log('Hotel search form template missing: ' . $search_form_path);
        
        if ( file_exists( $results_path ) ) include $results_path;
        else afs_debug_log('Hotel results template missing: ' . $results_path);
        
        return ob_get_clean();
    }
    
    /**
     * Enqueues scripts and localized data specifically for the hotel search feature.
     */
    // In class-amadeus-flight-search-main.php
    
    public function enqueue_hotel_scripts() {
        if (self::$hotel_scripts_enqueued) {
            return; // Exit if scripts have already been enqueued on this page load
        }
    
        // --- START: New Fallback Logic ---
        $selected_hotel_data = null;
        $booking_page_url = afs_get_page_url_from_setting( Amadeus_Flight_Search_Settings::get_setting('hotel_booking_page_url') );
        
        // Check if the current page is the hotel booking page
        if ( ! empty( $booking_page_url ) && strpos( home_url( $_SERVER['REQUEST_URI'] ), $booking_page_url ) !== false ) {
            $user_identifier = afs_get_user_identifier();
            $transient_key = 'ahs_selected_hotel_' . $user_identifier;
            $selected_hotel_data = get_transient( $transient_key );
            
            // Clean up the transient immediately after reading it
            if ( $selected_hotel_data ) {
                delete_transient( $transient_key );
            }
        }
        // --- END: New Fallback Logic ---
    
        wp_enqueue_script(
            $this->plugin_name . '-hotel-search',
            AFS_PLUGIN_URL . 'public/js/amadeus-hotel-search.js',
            array( 'jquery', 'jquery-ui-datepicker', 'jquery-ui-autocomplete' ),
            filemtime( AFS_PLUGIN_DIR . 'public/js/amadeus-hotel-search.js' ),
            true
        );
    
        $hotel_localized_data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'amadeus_hotel_search_nonce' ),
            'text'     => array(
                'loading'       => __('Loading...', 'amadeus-flight-search'),
                'error_generic' => __('An unexpected error occurred. Please try again.', 'amadeus-flight-search'),
                'error_no_hotels_found' => __('No hotels found matching your criteria.', 'amadeus-flight-search'),
                'select_hotel'  => __('Select Hotel', 'amadeus-flight-search'),
                'per_night'     => __('per night', 'amadeus-flight-search'),
            ),
            'settings' => array(
                'booking_page_url'  => $booking_page_url,
                'fixed_dummy_price' => Amadeus_Flight_Search_Settings::get_setting('hotel_fixed_dummy_price'),
                'currency_code'     => Amadeus_Flight_Search_Settings::get_setting('currency_code', 'USD'),
                'gf_mappings'       => array(
                    'form_id'   => Amadeus_Flight_Search_Settings::get_setting('hotel_gravity_form_id'),
                    'hotel_name'  => Amadeus_Flight_Search_Settings::get_setting('gf_map_hotel_name'),
                    'hotel_city'  => Amadeus_Flight_Search_Settings::get_setting('gf_map_hotel_city'),
                    'check_in'    => Amadeus_Flight_Search_Settings::get_setting('gf_map_hotel_check_in'),
                    'check_out'   => Amadeus_Flight_Search_Settings::get_setting('gf_map_hotel_check_out'),
                    'guests'      => Amadeus_Flight_Search_Settings::get_setting('gf_map_hotel_guests'),
                )
            ),
            // Pass the fallback data to JavaScript
            'selected_hotel_data' => $selected_hotel_data
        );
    
        wp_localize_script( $this->plugin_name . '-hotel-search', 'amadeus_hotel_vars', $hotel_localized_data );
    
        self::$hotel_scripts_enqueued = true;
    }
  
	public function set_guest_session_cookie() {
		if ( ! is_user_logged_in() && ! headers_sent() ) {
			$cookie_name = 'amadeus_session_id';
			if ( ! isset( $_COOKIE[ $cookie_name ] ) || empty( $_COOKIE[ $cookie_name ] ) ) {
				$session_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'afs_guest_', true );
                if ($session_id) {
                    $expires = time() + ( DAY_IN_SECONDS * 30 );
                    $secure = ( 'https' === parse_url( get_site_url(), PHP_URL_SCHEME ) );
                    setcookie( $cookie_name, $session_id, array(
                        'expires' => $expires, 'path' => COOKIEPATH ? COOKIEPATH : '/',
                        'domain' => COOKIE_DOMAIN, 'secure' => $secure,
                        'httponly' => true, 'samesite' => 'Lax'
                    ) );
                    $_COOKIE[$cookie_name] = $session_id;
                }
			}
		}
	}

	public function run() {
		$this->loader->run();
	}
	public function get_plugin_name() { return $this->plugin_name; }
	public function get_loader() { return $this->loader; }
	public function get_version() { return $this->version; }
}

// Helper function to get current page URL (you might need a more robust version depending on your setup)
// This is a placeholder. WordPress has functions like get_permalink(get_the_ID()) for posts/pages.
// For generic URL: (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
if (!function_exists('현재_페이지_URL_얻기_함수')) {
    function 현재_페이지_URL_얻기_함수() {
        global $wp;
        if (isset($wp)) {
            return home_url(add_query_arg(array(), $wp->request));
        }
        // Fallback for environments where $wp might not be set (e.g., very early hooks, though unlikely for enqueue_scripts)
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }
}