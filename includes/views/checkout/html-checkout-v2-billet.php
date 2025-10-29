<?php
/**
 * Checkout v2 template.
 *
 * @package PagHiper for WooCommerce
 *
 * @var WC_Order $order The WooCommerce order object.
 * @var string $order_payment_method The payment method for the order.
 * @var string $due_date_mode The due date mode (days or minutes).
 * @var DateTimeZone $timezone The timezone object.
 * @var int $days_due_date The number of days for the due date (for billets).
 * @var WC_PagHiper_Transaction $paghiperTransaction The PagHiper transaction object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $wp;
$order_id = isset($wp->query_vars['order-received']) ? $wp->query_vars['order-received'] : 0;
?>
<script>
    var ph_checkout_params = {
        'ajax_url': '<?php echo admin_url('admin-ajax.php'); ?>',
        'order_id': '<?php echo $order_id; ?>',
        'nonce': '<?php echo wp_create_nonce('paghiper_payment_status_nonce'); ?>',
        'is_pix': false
    };
</script>
<?php
$gateway_id = $order->get_payment_method();

$order_data = $paghiperTransaction->_get_order_data();
$digitable_line = $paghiperTransaction->_get_digitable_line();
$barcode = $paghiperTransaction->_get_barcode();
$total = $order->get_formatted_order_total();

if(!$digitable_line || !$barcode) {
    echo '<div class="woocommerce-error">' . __('Ocorreu um erro ao gerar os dados de pagamento. Por favor, entre em contato com o suporte.', 'woo-boleto-paghiper') . '</div>';
    return;
}

$barcode_url_base = wc_paghiper_assets_url('php/barcode.php');
$barcode_url = add_query_arg('codigo', $barcode, $barcode_url_base);

// Logic for due date strings
$due_date_display_str = '';
$due_date_timer_str = array_key_exists('current_transaction_days_due_date', $order_data) ? $order_data['current_transaction_days_due_date'] : '';
if($due_date_timer_str === 0) {
    $due_date_timer_str = __('Mesmo dia', 'woo-boleto-paghiper');
} else {
    $due_date_timer_str = sprintf( __('%d %s', 'woo-boleto-paghiper'), $due_date_timer_str, _n( 'dia', 'dias', $due_date_timer_str, ''    ) );
}

// Day-based Billet or PIX
$due_date_str = $paghiperTransaction->_get_due_date(); // Format: d/m/Y
$date_obj = DateTime::createFromFormat('d/m/Y', $due_date_str, $timezone);

if ($date_obj) {
    $timestamp = $date_obj->getTimestamp();
    $date_part = wp_date( 'j \d\e F \d\e Y', $timestamp, $timezone );
    $due_date_display_str = $date_part . ', 23:59';
} else {
    // Fallback
    $due_date_display_str = $due_date_str . ', 23:59';
}

?>
<div class="ph-checkout-v2" data-status="pending">
    <div class="ph-checkout-v2__container">

        <div class="ph-checkout-v2__main">

            <div class="ph-checkout-v2__details for-billet">
                <p class="ph-checkout-v2__amount"><?php echo $total; ?></p>
                <p class="ph-checkout-v2__due-date">
                    <?php printf(__('Vence em %s', 'woo-boleto-paghiper'), $due_date_display_str); ?>
                    <?php if (!empty($due_date_timer_str)) : ?>
                        <span class="ph-checkout-v2__due-date-timer"><?php echo esc_html($due_date_timer_str); ?></span>
                    <?php endif; ?>
                </p>

                <?php if(!empty($barcode_url)) : ?>
                <div class="ph-checkout-v2__barcode-container barcode-container">
                    <div class="ph-checkout-v2__barcode-box">
                        <img src="<?php echo esc_url($barcode_url); ?>" title="<?php _e('Código de barras do boleto deste pedido.', 'woo-boleto-paghiper'); ?>">
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="ph-checkout-v2__copy-code-container">
                    <div class="textarea-container">
                        <div class="digitable_line_container">
                            <?php echo esc_html($digitable_line); ?>
                        </div>
                    </div>
                </div>

                <div class="ph-checkout-v2__button-group">
                    <a href="<?php echo esc_url($order_data['url_slip_pdf']); ?>" id="ph-download-pdf-button" class="ph-checkout-v2__secondary-button button" target="_blank" rel="noopener noreferrer">
                        <?php _e('Baixar PDF', 'woo-boleto-paghiper'); ?>
                    </a>
                    <button type="button" class="ph-checkout-v2__copy-code-button">
                        <?php _e('Copiar número', 'woo-boleto-paghiper'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="ph-checkout-v2__powered-by">
            <?php _e('Pagamento processado por', 'woo-boleto-paghiper'); ?> <a href="https://www.paghiper.com" target="_blank"><img src="<?php echo wc_paghiper_assets_url('images/paghiper.svg'); ?>"></a>
        </div>
</div>

<div id="ph-reusable-notifications"></div>
