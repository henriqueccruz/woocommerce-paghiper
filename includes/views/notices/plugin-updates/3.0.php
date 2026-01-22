<?php
/**
 * Admin View: 3.0 version notice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>

<?php

echo sprintf(
	'<div class="notice notice-success paghiper-dismiss-notice is-dismissible" data-notice-id="notice_3_0"><p><strong>%s: </strong>%s <a href="%s">%s</a></p></div>', 
	esc_html__('PIX PagHiper', 'woo-boleto-paghiper'), 
	esc_html__('Agora você pode configurar seu PIX para vencer em minutos ao invés de dias! Configure aqui:', 'woo-boleto-paghiper'), 
	esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_paghiper_pix_gateway')), 
	esc_html__('Configurações do PIX PagHiper', 'woo-boleto-paghiper')
);