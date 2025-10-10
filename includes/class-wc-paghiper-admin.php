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

                if($_GET['page'] === 'wc-settings' && in_array($_GET['section'], ['paghiper_boleto', 'paghiper_pix'])) {
                    
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
                    );

                    wp_localize_script('wc-paghiper-admin', 'paghiper_settings', $settings_to_pass);
                    
                    wp_enqueue_style( 'wc-paghiper-admin' );
                    wp_enqueue_script( 'wc-paghiper-admin' );
                }

            }
        }
    }

}

new WC_Paghiper_Admin();