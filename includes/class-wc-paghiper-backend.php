<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Boleto Admin.
 */
class WC_Paghiper_Backend {

	private $timezone;

	/**
	 * Initialize the admin.
	 */
	public function __construct() {

		// Add metabox.
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );

		// Save Metabox.
		add_action( 'save_post', array( $this, 'save' ) );

		// AJAX handler for resending payment
		add_action( 'wp_ajax_paghiper_resend_payment', array( $this, 'ajax_resend_payment' ) );

		// Update.
		//add_action( 'admin_init', array( $this, 'update' ), 5 );

		// Define our default offset
		$this->timezone = new DateTimeZone('America/Sao_Paulo');

		// Enqueue styles and assets
		add_action( 'admin_enqueue_scripts', array( $this, 'load_plugin_assets' ) );
	}

	/**
	 * Register paghiper metabox.
	 */
	public function register_metabox() {

		global $post;
		if($post && $post->post_type == 'shop_order') {

			$order = new WC_Order( $post->ID );

		} else {

			$current_page 	= $_GET['page'];
			$current_action = $_GET['action'];

			if( $current_page == 'wc-orders' && $current_action == 'edit' ) {
				$order_id = absint( $_GET['id'] );
				$order = new WC_Order( $order_id );

			} else {
				return;
			}

		}
		
		if(!$order) {
			return;
		}

		$payment_method = $order->get_payment_method();
		
		if(!in_array($payment_method, ['paghiper', 'paghiper_billet', 'paghiper_pix'])) {
			return;
		}

		$method_title = ($payment_method == 'paghiper_pix') ? "PIX" : "Boleto";

		$target_screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) &&
			wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ? 
			wc_get_page_screen_id( 'shop-order' ) : 
			'shop_order';

		add_meta_box(
			'paghiper-boleto',
			__( "Configurações do {$method_title}", 'woo_paghiper' ),
			array( $this, 'metabox_content' ),
			$target_screen,
			'side',
			'high'
		);
		
	}

	/**
	 * Banking Ticket metabox content.
	 *
	 * @param  object $post order_shop data.
	 *
	 * @return string       Metabox HTML.
	 */
	public function metabox_content( $post_or_order_object ) {
		// Get order data.
		$order = ( $post_or_order_object instanceof WP_Post ) ? new WC_Order( $post_or_order_object->ID ) : $post_or_order_object;

		// Use nonce for verification.
		wp_nonce_field( basename( __FILE__ ), 'woo_paghiper_metabox_nonce' );
		$gateway_name = $order->get_payment_method();

		if ( !in_array($gateway_name, ['paghiper', 'paghiper_pix', 'paghiper_billet']) ) {
			echo '<p>' . __( 'Este pedido não foi efetuado com um método de pagamento PagHiper.', 'woo_paghiper' ) . '</p>';
			return;
		}

		$paghiper_data = $order->get_meta( 'wc_paghiper_data' );
		$settings = ($gateway_name == 'paghiper_pix') ? get_option( 'woocommerce_paghiper_pix_settings' ) : get_option( 'woocommerce_paghiper_billet_settings' );
		$due_date_mode = isset($settings['due_date_mode']) ? $settings['due_date_mode'] : 'days';
		$due_date_value = isset($settings['due_date_value']) ? intval($settings['due_date_value']) : 3;

		require_once WC_Paghiper::get_plugin_path() . 'includes/class-wc-paghiper-transaction.php';
		$paghiperTransaction = new WC_PagHiper_Transaction( $order->get_id() );

		// Determine Status
		$paghiper_status = isset($paghiper_data['status']) ? $paghiper_data['status'] : 'pending';
		$paid_statuses = ['paid', 'completed', 'processing', 'available', 'received'];
		if (!empty($settings['set_status_when_paid'])) {
			$paid_statuses[] = $settings['set_status_when_paid'];
		}
		$paid_statuses = apply_filters('woo_paghiper_paid_statuses', array_unique($paid_statuses), $order);

		$is_paid = in_array($paghiper_status, $paid_statuses);
		$is_expired = !$is_paid && $paghiperTransaction->is_payment_expired();

		$html = '';

		// Inline styles for status placeholders
		$html .= '<style>
			.ph-checkout-v2__status-placeholder { margin: 20px auto; width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; border-radius: .25rem; }
			.ph-checkout-v2__status-placeholder svg { width: 60px; height: 60px; }
			.ph-checkout-v2__status-placeholder.completed { background-color: #E6F2E8; color: #28a745; }
			.ph-checkout-v2__status-placeholder.expired { background-color: #FFF0F0; color: #dc3545; }
		</style>';

		if ($is_paid) {
			$html .= '<div class="ph-checkout-v2__status-placeholder completed"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img" aria-hidden="true"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd" /></svg></div>';
			$html .= '<p style="text-align:center; font-weight: bold;">' . __('Pagamento Aprovado', 'woo_paghiper') . '</p>';
		} else {
			if ($is_expired) {
				$html .= '<div class="ph-checkout-v2__status-placeholder expired"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img" aria-hidden="true"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z" clip-rule="evenodd" /></svg></div>';
			} else {
				$html .= $paghiperTransaction->printBarCode(false, true, ['code', 'digitable']);
			}

        if (isset($paghiper_data['transaction_id'])) {
            $html .= '<p class="paghiper-copy-transaction-id" style="text-align:center; font-size: 12px; color: #777; margin: -10px 0 10px; cursor: pointer;" data-transaction-id="' . esc_attr($paghiper_data['transaction_id']) . '">';
            $html .= __('ID da Transação:', 'woo_paghiper') . ' ' . esc_html($paghiper_data['transaction_id']);
            $html .= ' <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-copy"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
            $html .= '</p>';
        }

			if ($gateway_name === 'paghiper_pix' && $due_date_mode === 'minutes' && !$is_expired) {
				if (!empty($paghiper_data['order_transaction_due_datetime'])) {
					wp_enqueue_script('simply-countdown');
					$due_datetime = new DateTime($paghiper_data['order_transaction_due_datetime'], $this->timezone);
					$year = $due_datetime->format('Y'); $month = $due_datetime->format('m'); $day = $due_datetime->format('d');
					$hour = $due_datetime->format('H'); $minute = $due_datetime->format('i'); $second = $due_datetime->format('s');

			$html .= '<p style="border-top: 1px solid #ccc;"></p>';

					$html .= '<p><strong>' . __( 'Vencimento do PIX:', 'woo_paghiper' ) . '</strong></p>';
					$html .= '<div id="paghiper-pix-countdown" class="include-fonts"></div>';
					$html .= "<script>document.addEventListener('DOMContentLoaded',function(){
						simplyCountdown('#paghiper-pix-countdown', {
							year:{$year},
							month:{$month},
							day:{$day},
							hours:{$hour},
							minutes:{$minute},
							seconds:{$second},
							plural:true,
							zeroPad:true,
							enableUtc:false,
                            words: {
                                days: { root: 'dia', lambda: (root, n) => n > 1 ? root + 's' : root },
                                hours: { root: 'hora', lambda: (root, n) => n > 1 ? root + 's' : root },
                                minutes: { root: 'min', lambda: (root, n) => n > 1 ? root + 's' : root },
                                seconds: { root: 'seg', lambda: (root, n) => n > 1 ? root + 's' : root }
                            },
							sectionClass: 'paghiper-chrono-section',
							amountClass: 'paghiper-chrono-amount',
							wordClass: 'paghiper-chrono-label',
                            onEnd: function() {
                                // Aguarda 10 segundos após o término do cronômetro
                                setTimeout(function() {
									location.reload();
                                }, 10000); // 10 segundos de espera
                            }
						});
					});
					</script>";
				}
			} else {
				$due_date_to_format = $is_expired ? $paghiper_data['current_transaction_due_date'] : $paghiper_data['order_transaction_due_date'];
				$order_transaction_due_date = DateTime::createFromFormat('Y-m-d', $due_date_to_format, $this->timezone);
				$formatted_due_date = ($order_transaction_due_date) ? $order_transaction_due_date->format('d/m/Y H:i') : '--';
				$html .= '<p><strong>' . ($is_expired ? __('Expirou em:', 'woo_paghiper') : __('Data de Vencimento:', 'woo_paghiper')) . '</strong> ' . $formatted_due_date . '</p>';
			}

			if($gateway_name !== 'paghiper_pix') {
				$html .= '<p><strong>' . __( 'URL:', 'woo_paghiper' ) . '</strong> <a target="_blank" href="' . esc_url( wc_paghiper_get_paghiper_url( $order->get_order_key() ) ) . '">' . __( 'Visualizar boleto', 'woo_paghiper' ) . '</a></p>';
				$html .= '<p><strong>' . __( 'URL:', 'woo_paghiper' ) . '</strong> <a target="_blank" href="' . esc_url( $paghiperTransaction['download_url'] ) . '">' . __( 'Baixar boleto', 'woo_paghiper' ) . '</a></p>';
			}

			$html .= '<p style="border-top: 1px solid #ccc;"></p>';

			$html .= '<label for="woo_paghiper_expiration_date">' . sprintf( __( 'Redefinir vencimento do %s', 'woo_paghiper' ), (($gateway_name == 'paghiper_pix') ? __('PIX', 'woo_paghiper') : __('boleto', 'woo_paghiper'))) . '</label><br />';

			if ($gateway_name === 'paghiper_pix' && $due_date_mode === 'minutes') {

				$html .= '<div id="paghiper-due-date-container">
					<div id="minutes-mode-section" class="active">
						<div class="cronometro-wrapper">
							<div class="time-unit">
								<span class="chevron-control" data-action="increment" data-unit="days">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><path d="m12 6.586-8.707 8.707 1.414 1.414L12 9.414l7.293 7.293 1.414-1.414L12 6.586z"/></svg>
								</span>
								<div class="days-display" id="cron-days-backend"></div>
								<label>Dias</label>
								<span class="chevron-control" data-action="decrement" data-unit="days">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><path d="M12 17.414 3.293 8.707l1.414-1.414L12 14.586l7.293-7.293 1.414 1.414L12 17.414z"/></svg>
								</span>
							</div>
							<span class="time-separator">:</span>
							<div class="time-unit">
								<span class="chevron-control" data-action="increment" data-unit="hours">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><path d="m12 6.586-8.707 8.707 1.414 1.414L12 9.414l7.293 7.293 1.414-1.414L12 6.586z"/></svg>
								</span>
								<div class="hours-display" id="cron-hours-backend"></div>
								<label>Horas</label>
								<span class="chevron-control" data-action="decrement" data-unit="hours">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><path d="M12 17.414 3.293 8.707l1.414-1.414L12 14.586l7.293-7.293 1.414 1.414L12 17.414z"/></svg>
								</span>
							</div>
							<span class="time-separator">:</span>
							<div class="time-unit">
								<span class="chevron-control" data-action="increment" data-unit="minutes">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><path d="m12 6.586-8.707 8.707 1.414 1.414L12 9.414l7.293 7.293 1.414-1.414L12 6.586z"/></svg>
								</span>
								<div class="minutes-display" id="cron-minutes-backend"></div>
								<label>Minutos</label>
								<span class="chevron-control" data-action="decrement" data-unit="minutes">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><path d="M12 17.414 3.293 8.707l1.414-1.414L12 14.586l7.293-7.293 1.414 1.414L12 17.414z"/></svg>
								</span>
							</div>
						</div>
					</div>
										<input type="hidden" id="woo_paghiper_expiration_date" name="woo_paghiper_expiration_date" value="" />
									</div>
									<div id="paghiper-due-date-warning" class="notice notice-warning inline" style="display: none; margin: 10px 0;"><p></p></div>
									<span class="description">' . __( 'Use os controles para definir a nova data e hora de vencimento. Ao configurar, o PIX é re-enviado ao cliente.', 'woo_paghiper' ) . '</span>';
			} else {
				$html .= '<input type="text" id="woo_paghiper_expiration_date" name="woo_paghiper_expiration_date" class="date" style="width: 100%;" />';
				$html .= '<span class="description">' . sprintf(__( 'Ao configurar uma nova data de vencimento, o %s é re-enviado ao cliente por e-mail.', 'woo_paghiper' ), (($gateway_name !== 'paghiper_pix') ? 'boleto' : 'PIX')) . '</span>';
			}

			$html .= '<div id="ph-reusable-notifications"></div>';
			wp_nonce_field( 'paghiper_resend_payment_nonce', 'paghiper_resend_nonce', false );
			$html .= '<p class="submit" style="text-align: center;">';
			$html .= '<button type="button" id="paghiper-resend-ajax-button" data-order-id="' . $order->get_id() . '" class="button button-primary">' . __('Definir e Reenviar', 'woo_paghiper') . '</button>';
			$html .= '</p>';
		}

		if ( $error = get_transient( "woo_paghiper_save_order_errors_{$order->get_id()}" ) ) {
			$html .= sprintf('<div class="error"><p>%s</p></div>', $error); 
			delete_transient("woo_paghiper_save_order_errors_{$order->get_id()}");
		}

		if ( $error = get_transient( "woo_paghiper_due_date_order_errors_{$order->get_id()}" ) ) {
			$html .= sprintf('<div class="error"><p>%s</p></div>', $error); 
		}

		echo $html;
	}

	public function ajax_resend_payment() {
		check_ajax_referer( 'paghiper_resend_payment_nonce', 'security' );

		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
		$total_minutes = isset($_POST['total_minutes']) ? intval($_POST['total_minutes']) : 0;

		if ( ! $order_id || $total_minutes <= 0 ) {
			wp_send_json_error( array( 'message' => 'Dados inválidos. O vencimento deve ser no futuro.' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Pedido não encontrado.' ) );
		}

		$new_due_date = new \DateTime('now', $this->timezone);
        $new_due_date->add(new DateInterval('PT' . $total_minutes . 'M'));

		$paghiper_data = $order->get_meta( 'wc_paghiper_data' );
		$gateway_name = $order->get_payment_method();

		$paghiper_data['order_transaction_due_datetime'] = $new_due_date->format('Y-m-d H:i:s');
		$paghiper_data['order_transaction_due_date'] = $new_due_date->format('Y-m-d');
		$order->update_meta_data( 'wc_paghiper_data', $paghiper_data );
		$order->save();

		$formatted_date = $new_due_date->format('d/m/Y H:i');
		$order->add_order_note( sprintf( __( 'Data de vencimento alterada para %s e notificação reenviada ao cliente.', 'woo_paghiper' ), $formatted_date ) );

		$this->email_notification( $order, $formatted_date );

		wp_send_json_success( array( 'message' => 'Nova data de vencimento definida e notificação enviada com sucesso!' ) );
	}

	/**
	 * Save metabox data.
	 *
	 * @param int $post_id Current post type ID.
	 */
	public function save( $post_id ) {
		// Verify nonce.
		if ( ! isset( $_POST['woo_paghiper_metabox_nonce'] ) || ! wp_verify_nonce( $_POST['woo_paghiper_metabox_nonce'], basename( __FILE__ ) ) ) {
			return $post_id;
		}

		// Verify if this is an auto save routine.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check permissions.
		if ( OrderUtil::is_order( $post_id, wc_get_order_types() ) && ! current_user_can( 'edit_page', $post_id ) ) {
			return $post_id;
		}

		// The due date logic is now handled via AJAX in `ajax_resend_payment`.
		return $post_id;
	}

	/**
	 * New expiration date email notification.
	 *
	 * @param object $order           Order data.
	 * @param string $expiration_date Ticket expiration date.
	 */
	protected function email_notification( $order, $expiration_date ) {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$mailer = WC()->mailer();
		} else {
			global $woocommerce;
			$mailer = $woocommerce->mailer();
		}

		$gateway_name = $order->get_payment_method();
		$billing_email = (property_exists($order, "get_billing_email")) ? $order->get_billing_email : $order->get_billing_email();

		if(!$billing_email)
			return;

		$subject = sprintf( __( 'O %s do seu pedido foi atualizado (%s)', 'woo_paghiper' ), (($gateway_name !== 'paghiper_pix') ? 'boleto' : 'PIX'), $order->get_order_number() );

		// Mail headers.
		$headers = array();
		$headers[] = "Content-Type: text/html\r\n";

		// Billet re-emission
		require_once WC_Paghiper::get_plugin_path() . 'includes/class-wc-paghiper-transaction.php';

		$paghiperTransaction = new WC_PagHiper_Transaction( $order->get_id() );

		// Body message.
		$main_message = '<p>' . sprintf( __( 'A data de vencimento do seu %s foi atualizada para: %s', 'woo_paghiper' ), ((($gateway_name !== 'paghiper_pix') ? 'boleto' : 'PIX')), '<code>' . $expiration_date . '</code>' ) . '</p>';
		$main_message .= $paghiperTransaction->printBarCode();
		$main_message .= '<p>' . sprintf( '<a class="button" href="%s" target="_blank">%s</a>', esc_url( wc_paghiper_get_paghiper_url( $order->get_order_key() ) ), __( 'Pagar o boleto &rarr;', 'woo_paghiper' ) ) . '</p>';

		// Sets message template.
		$message = $mailer->wrap_message( sprintf(__( 'Nova data de vencimento para o seu %s', 'woo_paghiper' ), ((($gateway_name !== 'paghiper_pix') ? 'boleto' : 'PIX'))), $main_message );

		// Send email.
		$mailer->send( $billing_email, $subject, $message, $headers, '' );
	}

	/**
	 * Register and enqueue assets
	 */

	public function load_plugin_assets() {

		if( !wp_script_is( 'jquery-mask', 'registered' ) ) {
			wp_register_script( 'jquery-mask', wc_paghiper_assets_url() . 'js/libs/jquery.mask/jquery.mask.min.js', array( 'jquery' ), '1.14.16', false );
		}

		if( !wp_script_is( 'paghiper-backend-js', 'registered' ) ) {
			wp_register_script( 'paghiper-backend-js', wc_paghiper_assets_url() . 'js/backend.min.js', array( 'jquery' ),'1.1', true );
		}

		wp_register_style( 'paghiper-backend-css', wc_paghiper_assets_url() . 'css/backend.min.css', false, '1.0.0' );

		if(is_admin()) {
			
			global $current_screen;
			$req_action = empty( $_REQUEST[ 'action' ] ) ? false : $_REQUEST[ 'action' ];
			if ($current_screen->post_type =='shop_order' && $req_action == 'edit') {
				
				wp_enqueue_script(  'jquery-mask' );
				wp_enqueue_script( 'paghiper-backend-js' );
				wp_enqueue_style( 'paghiper-backend-css' );

				// Localize settings for backend.js
				$order_id = absint( $_GET['id'] );
				$order = new WC_Order( $order_id );
				$gateway_name = $order->get_payment_method();
				$settings = ($gateway_name == 'paghiper_pix') ? get_option( 'woocommerce_paghiper_pix_settings' ) : get_option( 'woocommerce_paghiper_billet_settings' );
				$due_date_mode = isset($settings['due_date_mode']) ? $settings['due_date_mode'] : 'days';
				$due_date_value = isset($settings['due_date_value']) ? intval($settings['due_date_value']) : 3;

				$initial_datetime_value = '';
				if ($gateway_name === 'paghiper_pix' && $due_date_mode === 'minutes') {
					$current_time = new DateTime('now', $this->timezone);
					$future_time = clone $current_time;
					$future_time->modify("+{$due_date_value} minutes");
					$initial_datetime_value = $future_time->format('Y-m-d H:i:s');
				} else {
					// For day-based, use the existing order_transaction_due_date if available, or calculate from now
					$paghiper_data = $order->get_meta( 'wc_paghiper_data' );
					if (isset($paghiper_data['order_transaction_due_date'])) {
						$date_obj = DateTime::createFromFormat('Y-m-d', $paghiper_data['order_transaction_due_date'], $this->timezone);
						if ($date_obj) {
							$initial_datetime_value = $date_obj->format('Y-m-d 23:59:59'); // Default to end of day
						}
					}
					if (empty($initial_datetime_value)) {
						$current_time = new DateTime('now', $this->timezone);
						$future_time = clone $current_time;
						$future_time->modify("+{$due_date_value} days");
						$initial_datetime_value = $future_time->format('Y-m-d 23:59:59');
					}
				}

				wp_localize_script('paghiper-backend-js', 'paghiper_backend_settings', array(
					'due_date_mode'       		=> $due_date_mode,
					'due_date_value'      		=> $due_date_value,
					'is_pix'              		=> ($gateway_name == 'paghiper_pix'),
					'initial_datetime_value' 	=> $initial_datetime_value,
				));
		
			}
			
			
		}
	}
}
	
new WC_Paghiper_Backend();