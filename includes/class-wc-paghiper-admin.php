<?php
/* * PagHiper Admin Class
 *
 * @package PagHiper for WooCommerce
 */

// For the WP team: error_log() is used only on emergency type of errors.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PagHiper\PagHiper;

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

		// AJAX handler for copying credentials
		add_action( 'wp_ajax_paghiper_copy_credentials', array( $this, 'ajax_copy_credentials' ) );

		// AJAX handler for testing credentials
		add_action( 'wp_ajax_paghiper_test_credentials', array( $this, 'ajax_test_credentials' ) );
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
	                        'nonce'          => wp_create_nonce('paghiper-admin-ajax-nonce'),
                            'gateway_id'     => $gateway_id,
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

		public function ajax_copy_credentials() {
			check_ajax_referer( 'paghiper-admin-ajax-nonce', 'nonce' );

			$to_gateway = isset( $_POST['to'] ) ? sanitize_text_field( $_POST['to'] ) : '';
			$from_gateway = ( $to_gateway === 'paghiper_pix' ) ? 'paghiper_billet' : 'paghiper_pix';

			$from_settings = get_option( 'woocommerce_' . $from_gateway . '_settings' );
			$to_settings   = get_option( 'woocommerce_' . $to_gateway . '_settings' );

			if(!is_array($from_settings)) {
				wp_send_json_error( array( 'message' => 'Configurações do gateway de origem inválidas.' ) );
			}

			if(!is_array($to_settings)) {
				$to_settings = [];
			}

			if ( ! empty( $from_settings['api_key'] ) && ! empty( $from_settings['token'] ) ) {
				$to_settings['api_key'] = $from_settings['api_key'];
				$to_settings['token']   = $from_settings['token'];

				update_option( 'woocommerce_' . $to_gateway . '_settings', $from_settings );

				wp_send_json_success();
			} else {
				wp_send_json_error( array( 'message' => 'O gateway de origem não possui credenciais para copiar.' ) );
			}
		}

		public function ajax_test_credentials() {
			check_ajax_referer( 'paghiper-admin-ajax-nonce', 'nonce' );

			$api_key = isset( $_POST['apiKey'] ) ? sanitize_text_field( $_POST['apiKey'] ) : '';
			$token   = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';

			if ( empty( $api_key ) || empty( $token ) ) {
				wp_send_json_error( array( 'message' => 'API Key e Token são obrigatórios.' ) );
			}

			wc_paghiper_initialize_sdk();

			try {
				$PagHiperAPI = new PagHiper($api_key.'a', $token);
				$response = $PagHiperAPI->transaction()->status('0000000000000000');

			} catch(Exception $e) {

				$errors = [];

				if (str_contains($e->getMessage(), 'token')) {
					$errors[] = 'Token';
				}
				if (str_contains($e->getMessage(), 'apiKey')) {
					$errors[] = 'API Key';
				}

				if (!empty($errors)) {
					$error_message = 'Credenciais inválidas: ' . implode(' e ', $errors) . ' incorreto(s).';
					wp_send_json_error( array( 'message' => $error_message ) );
				} else {
					wp_send_json_success( array( 'message' => 'Credenciais válidas!' ) );
				}
			}
			
			wp_send_json_success( array( 'message' => 'Credenciais válidas!' ) );

		}
}

new WC_Paghiper_Admin();