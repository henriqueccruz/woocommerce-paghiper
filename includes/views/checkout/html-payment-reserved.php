<?php
/**
 * Payment Reserved template.
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
            <div class="ph-checkout-v2__status-placeholder reserved">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img" aria-hidden="true">
                    <path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd" />
                </svg>
            </div>

            <div class="ph-checkout-v2__details">
                <p class="ph-checkout-v2__amount"><?php _e('Pagamento Reservado', 'woo-boleto-paghiper'); ?></p>
                <p class="ph-checkout-v2__description">
                    <?php _e('Seu pagamento foi reservado e está aguardando processamento. Você receberá um e-mail assim que for confirmado.', 'woo-boleto-paghiper'); ?>
                </p>
                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="ph-checkout-v2__copy-code-button">
                        <?php _e('Ver Meus Pedidos', 'woo-boleto-paghiper'); ?>
                    </a>
                </p>
            </div>
        </div>
        <div class="ph-checkout-v2__powered-by">
            <?php _e('Pagamento processado por', 'woo-boleto-paghiper'); ?> <a href="https://www.paghiper.com" target="_blank"><img src="<?php echo wc_paghiper_assets_url('images/paghiper.svg'); ?>"></a>
        </div>
    </div>
</div>
