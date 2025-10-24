<?php
/**
 * PagHiper Frontend
 *
 * @package PagHiper for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Paghiper_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_paghiper_check_payment_status', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_nopriv_paghiper_check_payment_status', array($this, 'ajax_check_payment_status'));
    }

    public function enqueue_scripts() {
        // We only need these scripts on the order received page
        if (!is_wc_endpoint_url('order-received')) {
            return;
        }

        global $wp;
        $order_id = isset($wp->query_vars['order-received']) ? $wp->query_vars['order-received'] : 0;
        $order = wc_get_order($order_id);

        // Don't enqueue if it's not a PagHiper order
        if (!$order || strpos($order->get_payment_method(), 'paghiper') === false) {
            return;
        }

        // Pass data to our script
        wp_localize_script('paghiper-frontend-js', 'ph_checkout_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'order_id' => $order_id,
            'nonce'    => wp_create_nonce('paghiper_payment_status_nonce')
        ));
    }

    public function ajax_check_payment_status() {
        check_ajax_referer('paghiper_payment_status_nonce', 'security');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(['message' => 'Order ID not found.']);
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found.']);
            return;
        }

        require_once WC_Paghiper::get_plugin_path() . 'includes/class-wc-paghiper-transaction.php';
        $transaction = new WC_PagHiper_Transaction($order_id);
        $order_data = $transaction->_get_order_data();
        $gateway_settings = $transaction->_get_gateway_settings();

        $paghiper_status = isset($order_data['status']) ? $order_data['status'] : 'pending';

        // Define all possible "paid" statuses
        $paid_statuses = ['paid', 'completed', 'processing', 'available', 'received'];

        // Add the custom "paid" status from plugin settings
        if (!empty($gateway_settings['set_status_when_paid'])) {
            $paid_statuses[] = $gateway_settings['set_status_when_paid'];
        }
        
        // Allow developers to filter the paid statuses
        $paid_statuses = apply_filters('woo_paghiper_paid_statuses', array_unique($paid_statuses), $order);

        if (in_array($paghiper_status, $paid_statuses)) {
            wp_send_json_success(['status' => 'paid']);
        } else {
            wp_send_json_success(['status' => $paghiper_status]);
        }
    }
}

new WC_Paghiper_Frontend();