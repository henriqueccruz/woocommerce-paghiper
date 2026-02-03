<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Paghiper_Migration_1_1_0 implements WC_Paghiper_Migration_Interface {
	
	public function up() {
		// Busca as configurações legadas
		$legacy_gateway_settings = get_option( 'woocommerce_paghiper_settings' );
		
		// Se não for um array ou estiver vazio, não há o que migrar
		if ( ! is_array( $legacy_gateway_settings ) || empty( $legacy_gateway_settings ) ) {
			return;
		}

		$is_migrated = false;
		$common_options = array(
			'enabled', 'title', 'description', 'api_key', 'token', 
			'paghiper_time', 'debug', 'days_due_date', 'skip_non_workdays', 
			'open_after_day_due', 'replenish_stock', 'fixed_description', 
			'set_status_when_waiting', 'set_status_when_paid', 'set_status_when_cancelled'
		);

		// --- 1. Migração para Boleto ---
		$billet_settings = get_option( 'woocommerce_paghiper_billet_settings' );
		
		// Só migra se as novas configurações ainda não existirem
		if ( empty( $billet_settings ) ) {
			$billet_settings = array();
			foreach ( $common_options as $option_key ) {
				$billet_settings[ $option_key ] = isset( $legacy_gateway_settings[ $option_key ] ) ? $legacy_gateway_settings[ $option_key ] : '';
			}
			update_option( 'woocommerce_paghiper_billet_settings', $billet_settings, 'yes' );
			$is_migrated = true;
		}

		// --- 2. Migração para PIX ---
		$pix_settings = get_option( 'woocommerce_paghiper_pix_settings' );

		if ( empty( $pix_settings ) ) {
			$pix_settings = array();
			foreach ( $common_options as $option_key ) {
				$pix_settings[ $option_key ] = isset( $legacy_gateway_settings[ $option_key ] ) ? $legacy_gateway_settings[ $option_key ] : '';
			}

			// Ajustes específicos para o PIX
			if ( isset( $pix_settings['open_after_day_due'] ) ) {
				unset( $pix_settings['open_after_day_due'] );
			}
			$pix_settings['title']       = 'PIX';
			$pix_settings['description'] = 'Pague de maneira rápida e prática usando PIX';

			update_option( 'woocommerce_paghiper_pix_settings', $pix_settings, 'yes' );
			$is_migrated = true;
		}

		if ( $is_migrated ) {
			set_transient( 'woo_paghiper_notice_2_1', true, ( 5 * 24 * 60 * 60 ) );
		}
	}
}