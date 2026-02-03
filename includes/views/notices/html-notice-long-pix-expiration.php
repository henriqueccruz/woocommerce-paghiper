<?php
/**
 * Admin Notice: Long PIX Expiration
 *
 * @package PagHiper for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ajax_url = admin_url( 'admin-ajax.php' );
$nonce = wp_create_nonce( 'paghiper_long_expiration_notice' );

?>
<div class="notice notice-warning is-dismissible paghiper-notice" id="paghiper-long-expiration-notice">
    <p>
        <strong><?php _e('Aviso do PagHiper PIX', 'woo-boleto-paghiper'); ?></strong><br>
        <?php _e('O tempo de vencimento do seu PIX está configurado para mais de 24 horas no modo "cronômetro". Isso desativa o cronômetro animado (GIF) enviado nos e-mails. O que você gostaria de fazer?', 'woo-boleto-paghiper'); ?>
    </p>
    <p>
        <button class="button button-secondary" data-action="disable_gif">
            <?php _e('Manter configuração e desativar GIF no e-mail', 'woo-boleto-paghiper'); ?>
        </button>
        <button class="button button-primary" data-action="change_to_days">
            <?php _e('Diminuir o tempo de vencimento (Recomendado)', 'woo-boleto-paghiper'); ?>
        </button>
    </p>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#paghiper-long-expiration-notice button').on('click', function() {
            const noticeDiv = $(this).closest('.paghiper-notice');
            const userAction = $(this).data('action');

            $.post('<?php echo $ajax_url; ?>', {
                action: 'paghiper_handle_long_expiration_notice',
                nonce: '<?php echo $nonce; ?>',
                user_action: userAction
            }, function(response) {
                if(response.success) {
                    noticeDiv.fadeTo(100, 0, function() {
                        noticeDiv.slideUp(100, function() {
                            noticeDiv.remove();
                        });
                    });
                    // Opcional: recarregar a página para ver as novas configurações
                    // window.location.reload(); 
                }
            });
        });
    });
</script>
