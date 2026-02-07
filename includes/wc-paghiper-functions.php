<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets URL.
 *
 * @return string
 */
function wc_paghiper_assets_url( $asset = NULL ) {

	$asset = ($asset) ? trim($asset, '/') : '';

	if(!function_exists('wc_paghiper_get_dev_asset_url')) {
		// In production, load from plugin assets
		return plugin_dir_url( dirname( __FILE__ ) ) . 'assets/dist/'.$asset;
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG && @fsockopen( 'wordpress.sandbox.local', 5173 ) ) {
		// When in development, load from Vite dev server
        return wc_paghiper_get_dev_asset_url( $asset );
    }

}

/**
 * Get paghiper URL from order key.
 *
 * @param  string $code
 *
 * @return string
 */
function wc_paghiper_get_paghiper_url( $code ) {
	return WC_Paghiper::get_paghiper_url( $code );
}

/**
 * Get paghiper URL from order key.
 *
 * @param  int $order_id
 *
 * @return string
 */
function wc_paghiper_get_paghiper_url_by_order_id( $order_id ) {
	$order_id = trim(str_replace('#', '', $order_id ));
	$order    = new WC_Order( $order_id );

	if ($order && !is_wp_error($order)) {
		return wc_paghiper_get_paghiper_url( $order->get_order_key() );
	}

	return '';
}

/**
 * Activate logs, if enabled from config
 *
 * @param  int $order_id
 *
 * @return string
 */
function wc_paghiper_initialize_log( $debug_settings ) {
	return ( 'yes' == $debug_settings ) ? (new WC_Logger()) : false;
}

/**
 * Adds an item do log, if enabled from config
 *
 * @return bool
 */
function wc_paghiper_add_log( $logger, $message, $context = [], $level = WC_Log_Levels::INFO ) {

	if($logger) {
		$context['source'] = 'paghiper';
		$context['plugin_version'] = WC_Paghiper::VERSION;
		if($logger->log( $level, $message, $context )) {
			return true;
		}
	}

	return false;
}

/**
 * Adds extra days to the billet due date, if option is properly enabled
 * 
 * @return object
 */
function wc_paghiper_add_workdays( $due_date, $order, $format, $workday_settings = NULL) {

	if($due_date && $workday_settings == 'yes') {

		$due_date_weekday = ($due_date)->format('N');

		if ($due_date_weekday >= 6) {
			$date_diff = (8 - $due_date_weekday);
			$due_date->modify( "+{$date_diff} days" );
			
			$paghiper_data_query = $order->get_meta( 'wc_paghiper_data' );

			$paghiper_data = (is_array($paghiper_data_query)) ? $paghiper_data_query : [];
			$paghiper_data['order_transaction_due_date'] = $due_date->format( 'Y-m-d' );

			$order->update_meta_data( 'wc_paghiper_data', $paghiper_data );
			/* translators: %s: Newly defined transaction due date. May be PIX or billet. For use in order notes */
			$order->add_order_note( sprintf( __( 'Data de vencimento ajustada para %s', 'woo-boleto-paghiper' ), $due_date->format('d/m/Y') ) );
			$order->save();
			
			if(function_exists('update_meta_cache'))
				update_meta_cache( 'shop_order', $order->get_id() );
		}

	}

	if($format == 'days') {

		$today = new DateTime;
		$today->setTimezone(new DateTimeZone('America/Sao_Paulo'));
		$today_date = DateTime::createFromFormat('Y-m-d', $today->format('Y-m-d'), new DateTimeZone('America/Sao_Paulo'));

		$return = (int) $today_date->diff($due_date)->format("%r%a");
	} else {
		$return = $due_date;
	}

	return apply_filters('woo_paghiper_due_date', $return, $order);
}

/**
 * Checks if an autoload include is performed successfully. If not, include necessary files
 * 
 * @return boolean
 */

function wc_paghiper_check_sdk_includes( $log = false ) {

	if (!\function_exists('PagHiperSDK\\GuzzleHttp\\uri_template') || !\function_exists('PagHiperSDK\\GuzzleHttp\\choose_handler')) {

		if($log) {
			wc_paghiper_add_log( $log, sprintf( 'Erro: O PHP SDK não incluiu todos os arquivos necessários por alguma questão relacionada a PSR-4 ou por configuração de ambiente.' ), [], WC_Log_Levels::CRITICAL );
		}

		require_once WC_Paghiper::get_plugin_path() . 'includes/paghiper-php-sdk/build/vendor/ralouphie/getallheaders/src/getallheaders.php';
		require_once WC_Paghiper::get_plugin_path() . 'includes/paghiper-php-sdk/build/vendor/guzzlehttp/promises/src/functions_include.php';
		require_once WC_Paghiper::get_plugin_path() . 'includes/paghiper-php-sdk/build/vendor/guzzlehttp/psr7/src/functions_include.php';
		require_once WC_Paghiper::get_plugin_path() . 'includes/paghiper-php-sdk/build/vendor/guzzlehttp/guzzle/src/functions_include.php';

		if($log) {
			wc_paghiper_add_log( $log, sprintf( 'Erro contornado: O plug-in se recuperou do erro mas talvez você queira verificar questões relacionadas a compilação ou configuração da sua engine PHP.' ) );
		}

	}

	return true;
}

/**
 * Includes the SDK autoload file
 * 
 * @return boolean
 */

function wc_paghiper_initialize_sdk( $log = false ) {

	require_once WC_Paghiper::get_plugin_path() . 'includes/paghiper-php-sdk/build/vendor/scoper-autoload.php';
	return wc_paghiper_check_sdk_includes( $log );

}

/**
 * Generates the HTML for email instructions (Barcode, PIX Code, etc).
 * Unifies logic between Frontend (New Order) and Backend (Resend/Update).
 *
 * @param WC_Order $order Order object.
 * @param string|null $custom_intro_message Optional custom intro message (e.g. for updates).
 * @return string HTML content.
 */
function wc_paghiper_get_email_instructions_html( $order, $custom_intro_message = null ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return '';
	}

	$gateway_id = $order->get_payment_method();
	$is_pix     = ( $gateway_id === 'paghiper_pix' );
	
	// Retrieve settings dynamically
	$settings_key = $is_pix ? 'woocommerce_paghiper_pix_settings' : 'woocommerce_paghiper_billet_settings';
	$settings     = get_option( $settings_key, [] );

	// Initialize Transaction
	require_once WC_Paghiper::get_plugin_path() . 'includes/class-wc-paghiper-transaction.php';
	$paghiperTransaction = new WC_PagHiper_Transaction( $order->get_id() );

	$html  = '<div class="woo-paghiper-boleto-details" style="text-align: center;">';
	$html .= '<h2>' . __( 'Pagamento', 'woo-boleto-paghiper' ) . '</h2>';
	$html .= '<p class="order_details">';

	// Get Barcode/Pix Code HTML
	$barcode_html = $paghiperTransaction->printBarCode();

	// Construct Intro Message
	if ( ! empty( $custom_intro_message ) ) {
		$html .= $custom_intro_message;
	} else {
		// Default message logic (from Base Gateway)
		if ( ! $is_pix ) {
			/* translators: %1$s: HTML opening tag, %2$s: HTML closing tag. */
			$html .= sprintf( __( '%1$sAtenção!%2$s Você NÃO vai receber o boleto pelos Correios.', 'woo-boleto-paghiper' ), '<strong>', '</strong>' ) . '<br />';
			$html .= __( 'Se preferir, você pode imprimir e pagar o boleto em qualquer agência bancária ou lotérica.', 'woo-boleto-paghiper' ) . '<br />';
		}
	}

	// Add Barcode (Filterable)
	$html .= apply_filters( 'woo_paghiper_email_instructions', $barcode_html );

	// Specific Logic for Billet vs PIX
	if ( ! $is_pix ) {
		// Billet: Show Link button and Due Date
		$html .= '<br />' . sprintf( '<a class="button alt" href="%s" target="_blank">%s</a>', esc_url( wc_paghiper_get_paghiper_url( $order->get_order_key() ) ), __( 'Veja o boleto completo &rarr;', 'woo-boleto-paghiper' ) ) . '<br />';

		// Due Date logic: Try to get from meta first (accurate for existing transactions), fallback to settings calculation
		$paghiper_data = $order->get_meta( 'wc_paghiper_data' );
		$due_date      = isset( $paghiper_data['order_transaction_due_date'] ) ? $paghiper_data['order_transaction_due_date'] : '';

		if ( ! $due_date ) {
			// Fallback calculation (less accurate for resends, but matches legacy behavior)
			$days_due_date = isset( $settings['days_due_date'] ) ? intval( $settings['days_due_date'] ) : 3;
			$timezone      = new DateTimeZone( 'America/Sao_Paulo' );
			$date_obj      = new DateTime( 'now', $timezone );
			$date_obj->modify( "+{$days_due_date} days" );
			$due_date      = $date_obj->format( 'Y-m-d' );
		}

		if ( $due_date ) {
			/* translators: %s: Billet due date. */
			$html .= '<strong style="font-size: 0.8em">' . sprintf( __( 'Data de Vencimento: %s.', 'woo-boleto-paghiper' ), $due_date ) . '</strong>';
		}

	} else {
		// PIX: Show Countdown (if enabled)
		$due_date_mode  = isset( $settings['due_date_mode'] ) ? $settings['due_date_mode'] : 'minutes';
		$due_date_value = isset( $settings['due_date_value'] ) ? intval( $settings['due_date_value'] ) : 0;
		$disable_gif    = isset( $settings['disable_email_gif'] ) && $settings['disable_email_gif'] === 'yes';
		$is_over_24h    = $due_date_value > 1440;

		if ( $due_date_mode === 'minutes' && ! $disable_gif && ! $is_over_24h ) {
			$paghiper_data = $order->get_meta( 'wc_paghiper_data' );
			if ( ! empty( $paghiper_data['order_transaction_due_datetime'] ) ) {
				$timezone           = new DateTimeZone( 'America/Sao_Paulo' );
				$due_datetime       = new DateTime( $paghiper_data['order_transaction_due_datetime'], $timezone );
				$timestamp          = $due_datetime->getTimestamp();
				$countdown_url_base = wc_paghiper_assets_url( 'php/countdown.php' );
				$countdown_url      = add_query_arg( 'order_due_time', $timestamp, $countdown_url_base );

				$html .= '<p style="text-align:center;margin-top:15px;"><strong>Seu pedido expira em:</strong><br>';
				$html .= '<img src="' . esc_url( $countdown_url ) . '" alt="Contador de Vencimento" /></p>';
			}
		}
	}

	$html .= '</p></div>';

	return $html;
}