<?php
/* * PagHiper Admin Class
 *
 * @package PagHiper for WooCommerce
 */

// For the WP team: error_log() is used only on emergency type of errors.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Class
 */
class WC_Paghiper_Admin {

	private $timezone;
	private $log;

	/**
	 * Initialize the admin.
	 */
	public function __construct() {

		// Define our default offset
		$this->timezone = new DateTimeZone('America/Sao_Paulo');

		// Enqueue styles and assets
		add_action( 'admin_enqueue_scripts', array( $this, 'load_plugin_assets' ) );

		// Hook for the admin notice
		add_action( 'admin_notices', array( $this, 'check_long_pix_expiration_notice' ) );

		// Hook for the AJAX handler
		add_action( 'wp_ajax_paghiper_handle_long_expiration_notice', array( $this, 'ajax_handle_long_expiration_notice' ) );
	}

	/**
	 * Register and enqueue assets
	 */

	    public function load_plugin_assets() {
	
	        wp_register_style( 
	            'wc-paghiper-admin', 
	            wc_paghiper_assets_url( '/css/admin.min.css' ), [], '1.0.0' );
	
	        wp_register_script( 
	            'wc-paghiper-admin', 
	            wc_paghiper_assets_url( '/js/admin.min.js' ), ['jquery'], '1.0.0', true );
	
	        if(is_admin()) {
	            if(is_array($_GET) && array_key_exists('page', $_GET) && array_key_exists('section', $_GET)) {
	
	                if($_GET['page'] === 'wc-settings' && in_array($_GET['section'], ['paghiper_billet', 'paghiper_pix'])) {
	                    
	                    $gateway_id = sanitize_text_field($_GET['section']);
	                    $settings_key = "woocommerce_{$gateway_id}_settings";
	                    $gateway_settings = get_option($settings_key);
	
	                    $is_pix = ($gateway_id === 'paghiper_pix');
	                    $default_mode = $is_pix ? 'minutes' : 'days';
	                    $default_value = $is_pix ? 30 : 3;
	
	                    $due_date_mode = !empty($gateway_settings['due_date_mode']) ? $gateway_settings['due_date_mode'] : $default_mode;
	                    $due_date_value = !empty($gateway_settings['due_date_value']) ? $gateway_settings['due_date_value'] : $default_value;
	
	                    $settings_to_pass = array(
	                        'due_date_mode'  => $due_date_mode,
	                        'due_date_value' => $due_date_value,
	                        'is_pix'         => $is_pix,
	                    );
	
	                    wp_localize_script('wc-paghiper-admin', 'paghiper_settings', $settings_to_pass);
	                    
	                    wp_enqueue_style( 'wc-paghiper-admin' );
	                    wp_enqueue_script( 'wc-paghiper-admin' );
	                }
	
	            }
	        }
	    }
	
		public function check_long_pix_expiration_notice() {
		    if(!(is_admin() && isset($_GET['page']) && $_GET['page'] === 'wc-settings' && isset($_GET['section']) && $_GET['section'] === 'paghiper_pix')) {
		        return;
		    }
	
		    if (get_transient('paghiper_long_expiration_notice_dismissed')) {
		        return;
		    }
	
		                $settings = get_option('woocommerce_paghiper_pix_settings');
		    		    $due_date_mode = isset($settings['due_date_mode']) ? $settings['due_date_mode'] : 'minutes';
		    		    $due_date_value = isset($settings['due_date_value']) ? intval($settings['due_date_value']) : 0;
		                $disable_email_gif = isset($settings['disable_email_gif']) ? $settings['disable_email_gif'] : 'no';
		    
		    		    // 23h 59m 50s em minutos é ~1439.8. Checamos por valores maiores que 1439.
		    		    if ($due_date_mode === 'minutes' && $due_date_value > 1439 && $disable_email_gif !== 'yes') {		        include_once 'views/notices/html-notice-long-pix-expiration.php';
		    }
		}
	
		public function ajax_handle_long_expiration_notice() {
		    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'paghiper_long_expiration_notice')) {
		        wp_send_json_error('Invalid nonce');
		    }
	
		    $action = isset($_POST['user_action']) ? sanitize_text_field($_POST['user_action']) : '';
		    $settings = get_option('woocommerce_paghiper_pix_settings');
	
		    if ($action === 'disable_gif') {
		        $settings['disable_email_gif'] = 'yes';
		        update_option('woocommerce_paghiper_pix_settings', $settings);
		    } elseif ($action === 'change_to_days') {
		        $settings['due_date_mode'] = 'days';
		        $settings['due_date_value'] = 1; // Sugere 1 dia como padrão
		        update_option('woocommerce_paghiper_pix_settings', $settings);
		    }
	
		    set_transient('paghiper_long_expiration_notice_dismissed', true, YEAR_IN_SECONDS);
		    wp_send_json_success();
		}
}

new WC_Paghiper_Admin();