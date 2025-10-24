<?php
/**
 * Payment Expired template.
 *
 * @package PagHiper for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
<div class="ph-checkout-v2">
    <div class="ph-checkout-v2__container">
        <div class="ph-checkout-v2__main">
            <div class="ph-checkout-v2__status-placeholder expired">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z" clip-rule="evenodd" />
                </svg>
            </div>

            <div class="ph-checkout-v2__details">
                <p class="ph-checkout-v2__amount"><?php _e('Pagamento Expirado', 'woo-boleto-paghiper'); ?></p>
                <p class="ph-checkout-v2__description">
                    <?php _e('O tempo para pagar expirou. Se você já fez o pagamento, não se preocupe, ele ainda pode ser processado. Clique abaixo para fazer uma nova verificação.', 'woo-boleto-paghiper'); ?>
                </p>
                <div class="ph-checkout-v2__button-group">
                    <button type="button" id="ph-i-paid-button" class="ph-checkout-v2__secondary-button">
                        <?php _e('Já paguei, verificar novamente', 'woo-boleto-paghiper'); ?>
                    </button>
                    <button type="button" id="ph-restore-cart-button" class="ph-checkout-v2__copy-code-button">
                        <?php _e('Gerar novo pagamento', 'woo-boleto-paghiper'); ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="ph-checkout-v2__powered-by">
            <?php _e('Pagamento processado por', 'woo-boleto-paghiper'); ?> <img src="<?php echo wc_paghiper_assets_url() . 'images/paghiper.png'; ?>">
        </div>
    </div>
</div>

<div id="ph-reusable-notifications"></div>
