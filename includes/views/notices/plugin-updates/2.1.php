<?php
/**
 * Admin View: 2.1 version notice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>

<?php

echo sprintf(
	'<div class="error notice paghiper-dismiss-notice is-dismissible" data-notice-id="notice_2_1"><p><strong>%s: </strong>%s <a href="%s">%s</a></p></div>', 
	esc_html__('PIX PagHiper', 'woo-boleto-paghiper'), 
	esc_html__('Você ja pode receber pagamentos por PIX! Configure aqui:', 'woo-boleto-paghiper'), 
	esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_paghiper_pix_gateway')), 
	esc_html__('Configurações do PIX PagHiper', 'woo-boleto-paghiper')
);