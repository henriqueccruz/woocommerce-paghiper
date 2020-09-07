<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boleto Admin.
 */
class WC_Paghiper_Admin {

	private $timezone;

	/**
	 * Initialize the admin.
	 */
	public function __construct() {
		// Add metabox.
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );

		// Save Metabox.
		add_action( 'save_post', array( $this, 'save' ) );

		// Update.
		//add_action( 'admin_init', array( $this, 'update' ), 5 );

		// Define our default offset
		$this->timezone = new DateTimeZone('America/Sao_Paulo');
	}

	/**
	 * Register paghiper metabox.
	 */
	public function register_metabox() {
		add_meta_box(
			'paghiper-boleto',
			__( 'Boleto Bancário - PagHiper', 'woo_paghiper' ),
			array( $this, 'metabox_content' ),
			'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * Banking Ticket metabox content.
	 *
	 * @param  object $post order_shop data.
	 *
	 * @return string       Metabox HTML.
	 */
	public function metabox_content( $post ) {
		// Get order data.
		$order = new WC_Order( $post->ID );

		// Use nonce for verification.
		wp_nonce_field( basename( __FILE__ ), 'woo_paghiper_metabox_nonce' );

		if ( 'paghiper' == $order->payment_method ) {
			$paghiper_data = get_post_meta( $post->ID, 'wc_paghiper_data', true );

			// Save the ticket data if don't have.
			if ( ! isset( $paghiper_data['order_billet_due_date'] ) ) {

				$settings               		= get_option( 'woocommerce_paghiper_settings', array() );
				$order_billet_due_date			= isset( $settings['order_billet_due_date'] ) ? absint( $settings['order_billet_due_date'] ) : 5;
				$data                   		= array();
				$data['order_billet_due_date']  = date( 'Y-m-d', time() + ( $order_billet_due_date * 86400 ) );

				update_post_meta( $post->ID, 'wc_paghiper_data', $data );

				$paghiper_data['order_billet_due_date'] = $data['order_billet_due_date'];
			}

			$html = '<p><strong>' . __( 'Data de Vencimento:', 'woo_paghiper' ) . '</strong> ' . date('d/m/Y', strtotime($paghiper_data['order_billet_due_date'])) . '</p>';
			$html .= '<p><strong>' . __( 'URL:', 'woo_paghiper' ) . '</strong> <a target="_blank" href="' . esc_url( wc_paghiper_get_paghiper_url( $order->order_key ) ) . '">' . __( 'Visualizar boleto', 'woo_paghiper' ) . '</a></p>';

			$html .= '<p style="border-top: 1px solid #ccc;"></p>';

			$html .= '<label for="woo_paghiper_expiration_date">' . __( 'Digite uma nova data de vencimento:', 'woo_paghiper' ) . '</label><br />';
			$html .= '<input type="text" id="woo_paghiper_expiration_date" name="woo_paghiper_expiration_date" class="date" style="width: 100%;" />';
			$html .= '<span class="description">' . __( 'Ao configurar uma nova data de vencimento, o boleto é re-enviado ao cliente por e-mail.', 'woo_paghiper' ) . '</span>';

			$html .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js" type="text/javascript"></script>
			<script type="text/javascript">
			jQuery(document).ready(function($){
				$(".date").mask("00/00/0000", {placeholder: "__/__/____", clearIfNotMatch: true});
			});
			</script>';

			if ( $error = get_transient( "woo_paghiper_save_order_errors_{$post->ID}" ) ) {

				$html .= sprintf('<div class="error"><p>%s</p></div>', $error); 
				delete_transient("woo_paghiper_save_order_errors_{$post->ID}");
			}

		} else {
			$html = '<p>' . __( 'Este pedido não foi efetuado ou pago com boleto.', 'woo_paghiper' ) . '</p>';
			$html .= '<style>#woo_paghiper.postbox {display: none;}</style>';
		}

		echo $html;

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
		if ( 'shop_order' == $_POST['post_type'] && ! current_user_can( 'edit_page', $post_id ) ) {
			return $post_id;
		}

		if ( isset( $_POST['woo_paghiper_expiration_date'] ) && ! empty( $_POST['woo_paghiper_expiration_date'] ) ) {

			// Store our input on a var for later use
			$input_date = trim($_POST['woo_paghiper_expiration_date']);

			// Gets ticket data.
			$paghiper_data = get_post_meta( $post_id, 'wc_paghiper_data', true );
			$date = DateTime::createFromFormat('d/m/Y', sanitize_text_field( $input_date ));
			$formatted_date = ($date) ? $date->format('d/m/Y') : NULL ;

			// Validate data first.
			$today_date = new \DateTime();
			$today_date->setTimezone($this->timezone);

			if(!$date || $formatted_date !== $input_date) {

				$error = __( '<strong>Boleto PagHiper</strong>: Data de vencimento inválida!', 'woo_paghiper' );
				set_transient("woo_paghiper_save_order_errors_{$post_id}", $error, 45);

				return $post_id;

			} elseif($date && $today_date->diff($date)->format("%r%a") < 0) {

				$error = __( '<strong>Boleto PagHiper</strong>: A data de vencimento não pode ser anterior a data de hoje!', 'woo_paghiper' );
				set_transient("woo_paghiper_save_order_errors_{$post_id}", $error, 45);

				return $post_id;

			}

			// Update ticket data.
			$paghiper_data['order_billet_due_date'] = $formatted_date;
			update_post_meta( $post_id, 'wc_paghiper_data', $paghiper_data );

			// Gets order data.
			$order = new WC_Order( $post_id );

			// Add order note.
			$order->add_order_note( sprintf( __( 'Data de vencimento alterada para %s', 'woo_paghiper' ), $paghiper_data['order_billet_due_date'] ) );

			// Send email notification.
			$this->email_notification( $order, $paghiper_data['order_billet_due_date'] );

			return $post_id;

		}
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


		$subject = sprintf( __( 'O boleto do seu pedido foi atualizado (%s)', 'woo_paghiper' ), $order->get_order_number() );

		// Mail headers.
		$headers = array();
		$headers[] = "Content-Type: text/html\r\n";

		// Billet re-emission
		require_once WC_Paghiper::get_plugin_path() . 'includes/class-wc-paghiper-billet.php';

		$paghiperBoleto = new WC_PagHiper_Boleto( $order->id );

		// Body message.
		$main_message = '<p>' . sprintf( __( 'A data de vencimento do seu boleto foi atualizada para: %s', 'woo_paghiper' ), '<code>' . $expiration_date . '</code>' ) . '</p>';
		$main_message .= $paghiperBoleto->printBarCode();
		$main_message .= '<p>' . sprintf( '<a class="button" href="%s" target="_blank">%s</a>', esc_url( wc_paghiper_get_paghiper_url( $order->order_key ) ), __( 'Pagar o boleto &rarr;', 'woo_paghiper' ) ) . '</p>';

		// Sets message template.
		$message = $mailer->wrap_message( __( 'Nova data de vencimento para o seu boleto', 'woo_paghiper' ), $main_message );

		// Send email.
		$mailer->send( $order->billing_email, $subject, $message, $headers, '' );
	}
}

new WC_Paghiper_Admin();
