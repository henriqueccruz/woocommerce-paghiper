<?php
/**
 * Checkout v1 template.
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

$order_data = $paghiperTransaction->_get_order_data();

// Countdown timer for PIX
if ($order_payment_method === 'paghiper_pix' && $due_date_mode === 'minutes') :
    if (!empty($order_data['order_transaction_due_datetime'])) :
        
        wp_enqueue_script('simply-countdown');

        $due_datetime = new DateTime($order_data['order_transaction_due_datetime'], $timezone);
        
        $year = $due_datetime->format('Y');
        $month = $due_datetime->format('m');
        $day = $due_datetime->format('d');
        $hour = $due_datetime->format('H');
        $minute = $due_datetime->format('i');
        $second = $due_datetime->format('s');
?>
        <div class="paghiper-countdown-wrapper">
            <p>Seu pedido está reservado para você por:</p>
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
                });
            });
        </script>

<?php
    endif;
endif;

// Barcode / PIX QR Code display logic
$digitable_line = $paghiperTransaction->_get_digitable_line();

if(!$digitable_line) {
    echo '<div class="woocommerce-error">Ocorreu um erro ao gerar os dados de pagamento. Por favor, entre em contato com o suporte.</div>';
    return;
}

$due_date = $paghiperTransaction->_get_due_date();
$assets_url = wc_paghiper_assets_url().'images';
$gateway_id = $order->get_payment_method();

if($gateway_id !== 'paghiper_pix') : // Billet HTML
?>
    <div class="woo_paghiper_digitable_line" style="margin-bottom: 40px;">
        <?php 
        $barcode_number = $paghiperTransaction->_get_barcode();
        if(!$barcode_number) : 
        ?>
            <img style="max-width: 200px;" src="<?php echo $assets_url; ?>/billet-cancelled.png">
            <p><strong><?php _e('Não foi possível emitir seu Boleto', 'woo-boleto-paghiper'); ?></strong></p>
            <p><?php _e('Entre em contato com o suporte informando o erro 0x00007b', 'woo-boleto-paghiper'); ?></p>
        <?php else : ?>
            <p style="width: 100%; text-align: center;"><?php _e('Pague seu boleto usando o código de barras ou a linha digitável, se preferir:', 'woo-boleto-paghiper'); ?></p>
            <?php
            $barcode_url_base = wc_paghiper_assets_url('php/barcode.php');
            $barcode_url = add_query_arg('codigo', $barcode_number, $barcode_url_base);
            ?>
            <img src="<?php echo esc_url($barcode_url); ?>" title="<?php _e('Código de barras do boleto deste pedido.', 'woo-boleto-paghiper'); ?>" style="max-width: 100%;">
            <strong style="font-size: 18px;"><p style="width: 100%; text-align: center;"><?php echo $digitable_line; ?></p></strong>
        <?php endif; ?>
    </div>

<?php else : // PIX HTML ?>

    <div class="woo_paghiper_digitable_line" style="max-width: 700px; margin: 0 auto 40px;">
        <?php 
        $barcode_url = $paghiperTransaction->_get_barcode();
        if(!$barcode_url) : 
        ?>
            <img style="max-width: 200px;" src="<?php echo $assets_url; ?>/pix-cancelled.png">
            <p><strong><?php _e('Não foi possível exibir o seu PIX', 'woo-boleto-paghiper'); ?></strong></p>
            <p><?php _e('Entre em contato com o suporte informando o erro 0x0000e9', 'woo-boleto-paghiper'); ?></p>
        <?php else : ?>
            <p style="width: 100%; text-align: center;"><?php _e('Efetue o pagamento PIX usando o <strong>QR Code</strong> ou usando <strong>PIX copia e cola</strong>, se preferir:', 'woo-boleto-paghiper'); ?></p>
            
            <div class="pix-container">
                <div class='qr-code'>
                    <img src="<?php echo esc_url($barcode_url); ?>" title="<?php _e('QR Code do PIX deste pedido.', 'woo-boleto-paghiper'); ?>">
                    <?php
                    $gateway_settings = $paghiperTransaction->_get_gateway_settings();
                    $due_date_mode_pix = isset($gateway_settings['due_date_mode']) ? $gateway_settings['due_date_mode'] : 'days';
                    if ($due_date_mode_pix === 'minutes' && isset($gateway_settings['due_date_value'])) {
                        $minutes_due = intval($gateway_settings['due_date_value']);
                        $order_datetime = $order->get_date_created();
                        if ($order_datetime) {
                            $order_datetime->setTimezone($timezone);
                            $due_datetime = clone $order_datetime;
                            $due_datetime->modify("+{$minutes_due} minutes");
                            $due_time_formatted = $due_datetime->format('d/m/Y H:i');
                            echo "<br>" . __('Válido até:', 'woo-boleto-paghiper') . " <strong>{$due_time_formatted}</strong>";
                        }
                    } else {
                        echo "<br>" . __('Válido até:', 'woo-boleto-paghiper') . " <strong>{$due_date}</strong>";
                    }
                    ?>
                </div>

                <div class="instructions">
                    <ul>
                        <li><span><?php _e('Abra o app do seu banco ou instituição financeira e <strong>entre no ambiente Pix</strong>.', 'woo-boleto-paghiper'); ?></span></li>
                        <li><span><?php _e('Escolha a opção <strong>Pagar com QR Code</strong> e escaneie o código ao lado.', 'woo-boleto-paghiper'); ?></span></li>
                        <li><span><?php _e('Confirme as informações e finalize o pagamento.', 'woo-boleto-paghiper'); ?></span></li>
                    </ul>
                </div>
            </div>

            <div class="paghiper-pix-code" onclick="copyPaghiperEmv()">
                <p>
                    <?php echo __('Pagar com PIX copia e cola - ', 'woo-boleto-paghiper'); ?>
                    <button type="button"><?php _e('Clique para copiar', 'woo-boleto-paghiper'); ?></button>
                </p>
                <div class="textarea-container"><textarea readonly rows="3"><?php echo $digitable_line; ?></textarea></div>
            </div>

            <p style="width: 100%; text-align: center; margin-top: 20px;"><?php _e('Após o pagamento, podemos levar alguns segundos para confirmar o seu pagamento.<br>Você será avisado(a) assim que isso ocorrer!', 'woo-boleto-paghiper'); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
// Billet specific message
if($order->get_payment_method() !== 'paghiper_pix') {
?>
    <div class="woocommerce-message">
        <a class="button button-primary wc-forward" href="<?php echo esc_url( wc_paghiper_get_paghiper_url( $order->get_order_key() ) ); ?>" target="_blank" style="display: block !important; visibility: visible !important;"><?php _e( 'Pagar o Boleto', 'woo-boleto-paghiper' ); ?></a>
        <?php
        $message = sprintf( __( '%1$sAtenção!%2$s Você NÃO vai receber o boleto pelos Correios.', 'woo-boleto-paghiper' ), '<strong>', '</strong>' ) . '<br />';
        $message .= __( 'Clique no link abaixo e pague o boleto pelo seu aplicativo de Internet Banking .', 'woo-boleto-paghiper' ) . '<br />';
        $message .= __( 'Se preferir, você pode imprimir e pagar o boleto em qualquer agência bancária ou lotérica.', 'woo-boleto-paghiper' ) . '<br />';
        echo apply_filters( 'woo_paghiper_thankyou_page_message', $message );

        $transaction_due_date = new DateTime;
        $transaction_due_date->setTimezone($timezone);
        $transaction_due_date->modify( "+{$days_due_date} days" );
        ?>
        <strong style="display: block; margin-top: 15px; font-size: 0.8em">' . <?php echo sprintf( __( 'Data de vencimento do Boleto: %s.', 'woo-boleto-paghiper' ), $transaction_due_date->format('Y-m-d') ); ?> . '</strong>';
    </div>
<?php
}
?>
