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
        add_action('wp_ajax_paghiper_restore_cart', array($this, 'ajax_restore_cart'));
        add_action('wp_ajax_nopriv_paghiper_restore_cart', array($this, 'ajax_restore_cart'));
    }

    public function ajax_restore_cart() {
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

        // Limpa o carrinho atual
        WC()->cart->empty_cart();

        // Adiciona os produtos de volta ao carrinho, incluindo meta dados customizados
        foreach ($order->get_items() as $item_id => $item) {
            $product_id   = $item->get_product_id();
            $quantity     = $item->get_quantity();
            $variation_id = $item->get_variation_id();
            
            $variations = [];
            if ($variation_id) {
                $product_variation = wc_get_product($variation_id);
                if ($product_variation) {
                    $variations = $product_variation->get_variation_attributes();
                }
            }

            // Recria os dados customizados do item (essencial para add-ons)
            $cart_item_data = [];
            foreach ($item->get_meta_data() as $meta) {
                $meta_data = $meta->get_data();
                if (substr($meta_data['key'], 0, 1) !== '_' && $meta_data['key'] !== 'variation') {
                    $cart_item_data[$meta_data['key']] = $meta_data['value'];
                }
            }
            
            WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations, $cart_item_data);
        }

        // Re-aplica os cupons
        foreach ($order->get_coupon_codes() as $coupon_code) {
            WC()->cart->apply_coupon($coupon_code);
        }

        // Recalcula os totais
        WC()->cart->calculate_totals();

        // Retorna a URL do checkout para redirecionamento
        wp_send_json_success(['redirect_url' => wc_get_checkout_url()]);
    }

    public function enqueue_scripts() {
        // A localização dos scripts agora é feita diretamente nos templates de view
        // para garantir que os dados estejam sempre presentes quando o script for executado.
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