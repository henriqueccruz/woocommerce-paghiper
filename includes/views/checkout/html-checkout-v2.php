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
        'nonce': '<?php echo wp_create_nonce('paghiper_payment_status_nonce'); ?>'
    };
</script>
<?php
$gateway_id = $order->get_payment_method();

// This template is designed for PIX. You can expand it to handle billets as well.
if ($gateway_id !== 'paghiper_pix') {
    // For non-PIX payments, you can include the v1 template or show a message.
    $template_v1_path = WC_Paghiper::get_plugin_path() . 'includes/views/checkout/html-checkout-v1.php';
    if ( file_exists( $template_v1_path ) ) {
        include $template_v1_path;
    }
    return;
}

$order_data = $paghiperTransaction->_get_order_data();
$digitable_line = $paghiperTransaction->_get_digitable_line();
$barcode_url = $paghiperTransaction->_get_barcode();
$total = $order->get_formatted_order_total();

if(!$digitable_line || !$barcode_url) {
    echo '<div class="woocommerce-error">' . __('Ocorreu um erro ao gerar os dados de pagamento. Por favor, entre em contato com o suporte.', 'woo-boleto-paghiper') . '</div>';
    return;
}

// Logic for due date strings
$due_date_display_str = '';
$due_date_timer_str = '';
$gateway_settings = $paghiperTransaction->_get_gateway_settings();
$due_date_mode_pix = isset($gateway_settings['due_date_mode']) ? $gateway_settings['due_date_mode'] : 'days';

if ($gateway_id === 'paghiper_pix' && $due_date_mode_pix === 'minutes') {
    // Minute-based PIX
    $original_minutes = intval($gateway_settings['due_date_value']);
    $due_date_timer_str = $original_minutes . 'min';
    
    $order_datetime = $order->get_date_created();
    if ($order_datetime) {
        $order_datetime->setTimezone($timezone);
        $due_datetime = clone $order_datetime;
        $due_datetime->modify("+{$original_minutes} minutes");
        
        $timestamp = $due_datetime->getTimestamp();
        $date_part = wp_date( 'j \d\e F \d\e Y', $timestamp, $timezone );
        $time_part = wp_date( 'H:i', $timestamp, $timezone );
        $due_date_display_str = $date_part . ', ' . $time_part;

    } else {
        // Fallback if order date is not available
        $due_date_display_str = $paghiperTransaction->_get_due_date();
    }
} else {
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
}

?>
<div class="ph-checkout-v2" data-status="pending">
    <div class="ph-checkout-v2__container">
        
        <?php
        // Countdown timer for PIX
        if ($gateway_id === 'paghiper_pix' && $due_date_mode_pix === 'minutes') {
            if (!empty($order_data['order_transaction_due_datetime'])) {
                
                wp_enqueue_script('simply-countdown');

                $due_datetime_obj = new DateTime($order_data['order_transaction_due_datetime'], $timezone);
                
                $year = $due_datetime_obj->format('Y');
                $month = $due_datetime_obj->format('m');
                $day = $due_datetime_obj->format('d');
                $hour = $due_datetime_obj->format('H');
                $minute = $due_datetime_obj->format('i');
                $second = $due_datetime_obj->format('s');
        ?>
                <div class="paghiper-countdown-wrapper">
                    <p><?php _e('Seu pedido está reservado para você por:', 'woo-boleto-paghiper'); ?></p>
                    <div id="paghiper-pix-countdown" class="include-fonts"></div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        simplyCountdown('#paghiper-pix-countdown', {
                            year: <?php echo $year; ?>,
                            month: <?php echo $month; ?>,
                            day: <?php echo $day; ?>,
                            hours: <?php echo $hour; ?>,
                            minutes: <?php echo $minute; ?>,
                            seconds: <?php echo $second; ?>,
                            words: {
                                days: { root: 'dia', lambda: (root, n) => n > 1 ? root + 's' : root },
                                hours: { root: 'hora', lambda: (root, n) => n > 1 ? root + 's' : root },
                                minutes: { root: 'minuto', lambda: (root, n) => n > 1 ? root + 's' : root },
                                seconds: { root: 'segundo', lambda: (root, n) => n > 1 ? root + 's' : root }
                            },
                            plural: true,
                            zeroPad: true,
                                                enableUtc: false,
                                                sectionClass: 'paghiper-chrono-section',
                                                amountClass: 'paghiper-chrono-amount',
                                                wordClass: 'paghiper-chrono-label',
                                                onEnd: function() {
                                                    if (typeof refreshCheckoutContent === 'function') {
                                                        refreshCheckoutContent();
                                                    }
                                                }
                                            });                    });
                </script>
        <?php
            }
        }
        ?>

        <div class="ph-checkout-v2__main">
            <div class="ph-checkout-v2__qr-container">
                <div class="ph-checkout-v2__qr-box">
                    <img src="<?php echo esc_url($barcode_url); ?>" title="<?php _e('QR Code do PIX deste pedido.', 'woo-boleto-paghiper'); ?>">
                    <br>
                    <?php _e('Escanear para pagar', 'woo-boleto-paghiper'); ?>
                </div>
            </div>

            <div class="ph-checkout-v2__details">
                <p class="ph-checkout-v2__amount"><?php echo $total; ?></p>
                <p class="ph-checkout-v2__description">
                    <?php printf(__('Vence em %s', 'woo-boleto-paghiper'), $due_date_display_str); ?>
                    <?php if (!empty($due_date_timer_str)) : ?>
                        <span class="ph-checkout-v2__due-date-timer"><?php echo esc_html($due_date_timer_str); ?></span>
                    <?php endif; ?>
                </p>
                
                <div class="ph-checkout-v2__copy-code-container">
                    <div class="textarea-container">
                        <div class="digitable_line_container">
                            <?php echo esc_html($digitable_line); ?>
                        </div>
                    </div>
                </div>

                <div class="ph-checkout-v2__button-group">
                    <button type="button" id="ph-i-paid-button" class="ph-checkout-v2__secondary-button">
                        <?php _e('Já fiz meu pagamento', 'woo-boleto-paghiper'); ?>
                    </button>
                    <button type="button" class="ph-checkout-v2__copy-code-button">
                        <?php _e('Copiar PIX', 'woo-boleto-paghiper'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="ph-checkout-v2__powered-by">
            <?php _e('Pagamento processado por', 'woo-boleto-paghiper'); ?> <img src="<?php echo wc_paghiper_assets_url() . 'images/paghiper.png'; ?>">
        </div>
</div>

<div id="ph-reusable-notifications"></div>
