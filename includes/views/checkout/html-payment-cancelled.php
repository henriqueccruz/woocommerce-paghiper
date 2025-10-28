<?php
/**
 * Payment Cancelled template.
 *
 * @package PagHiper for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ph-checkout-v2">
    <div class="ph-checkout-v2__container">
        <div class="ph-checkout-v2__main">
            <div class="ph-checkout-v2__status-placeholder cancelled">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z" clip-rule="evenodd" />
                </svg>
            </div>

            <div class="ph-checkout-v2__details">
                <p class="ph-checkout-v2__amount"><?php _e('Pagamento Cancelado', 'woo-boleto-paghiper'); ?></p>
                <p class="ph-checkout-v2__description">
                    <?php _e('Seu pagamento foi cancelado. Se você acredita que isso é um erro, por favor, entre em contato conosco.', 'woo-boleto-paghiper'); ?>
                </p>
                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="ph-checkout-v2__copy-code-button">
                        <?php _e('Ver Meus Pedidos', 'woo-boleto-paghiper'); ?>
                    </a>
                </p>
            </div>
        </div>
        <div class="ph-checkout-v2__powered-by">
            <?php _e('Pagamento processado por', 'woo-boleto-paghiper'); ?> <img src="<?php echo wc_paghiper_assets_url() . 'images/paghiper.png'; ?>">
        </div>
    </div>
</div>
