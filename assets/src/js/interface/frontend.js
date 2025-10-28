jQuery( document ).ready(function($) {

	// Masking function for out TaxID field
	if(typeof $('.paghiper_tax_id').mask === "function") {

		function initializeMask() {
			var taxIdMaskBehavior = function (val) {
				return val.replace(/\D/g, '').length > 11 ? '00.000.000/0000-00' : '000.000.000-009';
			}
	
			$('.paghiper_tax_id').mask(taxIdMaskBehavior, {
				clearIfNotMatch: true,
				placeholder: "___.___.___-__",
				onKeyPress: function(val, e, field, options) {
					field.mask(taxIdMaskBehavior.apply({}, arguments), options);
				}
			});
		}

		$( document.body ).on('updated_checkout', function (event) {
			initializeMask();
		});

		initializeMask();
	}

	// Fallback for when AJAX cart update is not available.
	$( document.body ).on('updated_checkout', function (event) {
		checkForTaxIdFields()
	});

	checkForTaxIdFields();

});

function checkForTaxIdFields() {

	let otherTaxIdFields 		= document.querySelectorAll('[name="billing_cpf"], [name="billing_cnpj"]'),
		otherPayerNameFields 	= document.querySelectorAll('[name="billing_first_name"], [name="billing_company"]');

	let paghiperFieldsetContainers = document.querySelectorAll('.wc-paghiper-form');

	[].forEach.call(paghiperFieldsetContainers, (paghiperFieldsetContainer) => {

		let ownTaxIdField 		= paghiperFieldsetContainer.querySelector('.paghiper-taxid-fieldset'),
			ownPayerNameField 	= paghiperFieldsetContainer.querySelector('.paghiper-payername-fieldset');

		let hasTaxField = false,
			hasPayerNameField = false;

			if(ownTaxIdField) {
				if(otherTaxIdFields.length > 0) {
					ownTaxIdField.classList.add('paghiper-hidden');
				} else {
					ownTaxIdField.classList.remove('paghiper-hidden');	
					hasTaxField = true;
				}
			}

			if(ownPayerNameField) {
				if(otherPayerNameFields.length > 0) {
					ownPayerNameField.classList.add('paghiper-hidden');
				} else {
					ownPayerNameField.classList.remove('paghiper-hidden');
					hasPayerNameField = true;
				}
			}

			if(!hasTaxField && !hasPayerNameField) {
				paghiperFieldsetContainer.classList.add('paghiper-hidden');
			} else {
				paghiperFieldsetContainer.classList.remove('paghiper-hidden');
			}
	});

}

/**
 * [LEGACY] Copia o código EMV para a área de transferência.
 */
window.copyPaghiperEmv = function() {
	let paghiperEmvBlock 	= document.querySelector('.paghiper-pix-code');
	let targetPixCode 		= paghiperEmvBlock.querySelector('textarea');
	let targetButton 		= paghiperEmvBlock.querySelector('button');

	targetPixCode.select();
	targetPixCode.setSelectionRange(0, 99999); // For mobile devices
  
	navigator.clipboard.writeText(targetPixCode.value);

	targetButton.dataset.originalText = targetButton.innerHTML;
	targetButton.innerHTML = 'Copiado!';

	setTimeout(function(targetButton) {
		let originalText = targetButton.dataset.originalText;
		targetButton.innerHTML = originalText;
		document.getSelection().removeAllRanges();
	}, 2000, targetButton,);
};

/**
 * Copia um texto para a área de transferência de forma robusta.
 * @param {string} text O texto a ser copiado.
 * @returns {Promise<boolean>} Retorna true se bem-sucedido, e false caso contrário.
 */
async function copyTextToClipboard(text) {
  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (err) {
      console.error('Falha ao copiar com a API moderna: ', err);
    }
  }

  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.top = "-9999px";
  textArea.style.left = "-9999px";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();

  let success = false;
  try {
    success = document.execCommand('copy');
  } catch (err) {
    console.error('Erro ao tentar copiar com o método de fallback: ', err);
  }

  document.body.removeChild(textArea);
  return success;
}

/**
 * Exibe uma notificação flutuante.
 * @param {object} options Opções para a notificação.
 * @param {string} options.message A mensagem em texto ou HTML.
 * @param {string} [options.type='info'] O tipo de notificação (success, error, info, warning).
 * @param {number} [options.duration=3000] Duração em ms. Se 0, a notificação é fixa.
 * @returns {HTMLElement|null} O elemento da notificação criado ou null.
 */
function showPhNotification(options) {
  const { message, type = 'info', duration = 3000 } = options;
  const container = document.getElementById('ph-reusable-notifications');
  if (!container) return null;

  const notification = document.createElement('div');
  notification.className = `ph-notification ${type}`;
  notification.innerHTML = message; // Permite HTML para ícones

  container.appendChild(notification);

  if (duration > 0) {
    setTimeout(() => {
      notification.classList.add('fade-out');
      notification.addEventListener('animationend', () => {
        notification.remove();
      });
    }, duration);
  }

  return notification;
}

/**
 * Atualiza a área de checkout via AJAX para refletir o status mais recente do pedido.
 */
async function refreshCheckoutContent() {
    const loadingSpinner = '<span class="ph-notification-spinner"></span>';
    showPhNotification({
        message: `${loadingSpinner} Por favor aguarde...`,
        type: 'warning',
        duration: 5000 // Mostra por 5s enquanto carrega
    });

    try {
        const response = await fetch(window.location.href);
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        const newContent = doc.querySelector('.ph-checkout-v2');
        const currentContent = document.querySelector('.ph-checkout-v2');

        if (newContent && currentContent) {
            currentContent.innerHTML = newContent.innerHTML;
            // Aqui, podemos reinicializar event listeners se necessário no futuro
        } else {
            window.location.reload(); // Recarrega a página se a análise falhar
        }
    } catch (error) {
        console.error('Falha ao atualizar o conteúdo do checkout:', error);
        window.location.reload(); // Recarrega em caso de erro
    }
}

// Event Listeners para Checkout v2
document.addEventListener('DOMContentLoaded', () => {
  const copyButton = document.querySelector('.ph-checkout-v2__copy-code-button');
  const qrContainer = document.querySelector('.ph-checkout-v2__qr-container');
  const textContainer = document.querySelector('.ph-checkout-v2__copy-code-container .textarea-container');
  
  const handleCopyClick = async () => {
    const textToCopy = document.querySelector('.ph-checkout-v2__copy-code-container .digitable_line_container')?.textContent.trim();
    if (!textToCopy) return;

    const success = await copyTextToClipboard(textToCopy);
    if (success) {
      showPhNotification({ message: 'Código PIX copiado com sucesso!', type: 'success' });
      if(copyButton) {
        const originalText = copyButton.dataset.originalText || copyButton.textContent;
        if (!copyButton.dataset.originalText) {
            copyButton.dataset.originalText = originalText;
        }
        copyButton.textContent = 'Copiado!';
        setTimeout(() => {
          copyButton.textContent = originalText;
        }, 2000);
      }
    }
  };

  const handleSelectClick = (e) => {
    const textDiv = e.currentTarget.querySelector('.digitable_line_container');
    if (window.getSelection && document.createRange) {
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(textDiv);
        selection.removeAllRanges();
        selection.addRange(range);
    }
  };

  if (copyButton) {
    copyButton.addEventListener('click', handleCopyClick);
  }
  if (qrContainer) {
    qrContainer.addEventListener('click', handleCopyClick);
  }
  if (textContainer) {
    textContainer.addEventListener('click', handleSelectClick);
  }

  // Inicia a verificação de status de pagamento
  const startPaymentStatusCheck = () => {
    let notificationElement = null;
    const orderId = ph_checkout_params?.order_id;
    const nonce = ph_checkout_params?.nonce;

    if (!orderId || !nonce) return;

    const paymentCheckInterval = setInterval(() => {
        
        if (!notificationElement || !document.body.contains(notificationElement)) {
            const loadingSpinner = '<span class="ph-notification-spinner"></span>';
            notificationElement = showPhNotification({
                message: `${loadingSpinner} Aguardando confirmação do pagamento...`,
                type: 'info',
                duration: 0 // Notificação Fixa
            });
        }

        jQuery.post(ph_checkout_params.ajax_url, {
            action: 'paghiper_check_payment_status',
            security: nonce,
            order_id: orderId
        }, function(response) {
            if (response.success) {
                if (response.data.status === 'paid') {
                    clearInterval(paymentCheckInterval);
                    if (notificationElement) {
                        notificationElement.remove();
                    }
                    showPhNotification({ 
                        message: 'Pagamento confirmado com sucesso!', 
                        type: 'success', 
                        duration: 5000 
                    });
                    
                    setTimeout(() => {
                        refreshCheckoutContent();
                    }, 1000);
                }
            } else {
                clearInterval(paymentCheckInterval);
                console.error('Erro ao verificar status do pagamento:', response.data.message);
            }
        });

    }, 10000);
  };

  // Inicia o polling apenas na página de checkout v2 com status pendente
  if (document.querySelector('.ph-checkout-v2[data-status="pending"]') && typeof ph_checkout_params !== 'undefined') {
      startPaymentStatusCheck();
  }

  const iPaidButton = document.getElementById('ph-i-paid-button');
  if (iPaidButton) {
      iPaidButton.addEventListener('click', () => {
          manualPaymentCheck();
      });
  }

  const restoreCartButton = document.getElementById('ph-restore-cart-button');
  if (restoreCartButton) {
    restoreCartButton.addEventListener('click', () => {
        const orderId = ph_checkout_params?.order_id;
        const nonce = ph_checkout_params?.nonce;
        if (!orderId || !nonce) return;

        const loadingSpinner = '<span class="ph-notification-spinner" style="border-top-color: #fff;"></span>';
        
        const notification = showPhNotification({
            message: `${loadingSpinner} Restaurando seu carrinho...`,
            type: 'info',
            duration: 0 // Fixa
        });

        jQuery.post(ph_checkout_params.ajax_url, {
            action: 'paghiper_restore_cart',
            security: nonce,
            order_id: orderId
        }, function(response) {
            if (response.success) {
                if (notification) {
                    notification.innerHTML = `${loadingSpinner} Redirecionando você para a tela de checkout...`;
                }
                
                setTimeout(() => {
                    window.location.href = response.data.redirect_url;
                }, 2000);

            } else {
                if (notification) notification.remove();
                showPhNotification({
                    message: 'Ocorreu um erro ao tentar restaurar seu carrinho. Por favor, tente novamente.',
                    type: 'error'
                });
            }
        });
    });
  }
});

let isCheckingManually = false;

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function animateNotificationText(notificationElement, textElement, messages, displayDuration, deleteDuration, typeDuration) {
    let currentIndex = 0;
    const loadingSpinner = '<span class="ph-notification-spinner"></span>';

    const cycle = async () => {
        if (!document.body.contains(notificationElement)) return;

        await sleep(displayDuration);
        if (!document.body.contains(notificationElement)) return;

        const currentText = messages[currentIndex];
        for (let i = currentText.length; i >= 0; i--) {
            if (!document.body.contains(notificationElement)) return;
            textElement.textContent = currentText.substring(0, i);
            await sleep(deleteDuration / currentText.length);
        }

        currentIndex = (currentIndex + 1) % messages.length;
        const nextText = messages[currentIndex];

        for (let i = 0; i < nextText.length; i++) {
            if (!document.body.contains(notificationElement)) return;
            textElement.textContent += nextText[i];
            await sleep(typeDuration / nextText.length);
        }

        cycle();
    };

    cycle();
}

const manualPaymentCheck = () => {
    if (isCheckingManually) return;
    isCheckingManually = true;

    const iPaidButton = document.getElementById('ph-i-paid-button');
    if (iPaidButton) iPaidButton.disabled = true;

    const orderId = ph_checkout_params?.order_id;
    const nonce = ph_checkout_params?.nonce;
    if (!orderId || !nonce) {
        isCheckingManually = false;
        if (iPaidButton) iPaidButton.disabled = false;
        return;
    }

    const loadingSpinner = '<span class="ph-notification-spinner"></span>';
    const textMessages = [
        'Estamos confirmando seu pagamento, só um instante...',
        'Às vezes o banco leva um tempinho para nos notificar.',
        'Não se preocupe, vamos continuar verificando por aqui.'
    ];

    const notification = showPhNotification({
        message: `${loadingSpinner} <span>${textMessages[0]}</span>`,
        type: 'warning',
        duration: 0 // Fixa
    });

    const textElement = notification.querySelector('span:last-child');
    animateNotificationText(notification, textElement, textMessages, 6000, 250, 1000);

    const checkRunner = () => {
        jQuery.post(ph_checkout_params.ajax_url, {
            action: 'paghiper_check_payment_status',
            security: nonce,
            order_id: orderId
        }, function(response) {
            if (response.success && response.data.status === 'paid') {
                clearInterval(manualCheckInterval);
                clearTimeout(manualCheckTimeout);
                notification.remove(); // Isso irá parar o loop de animação
                showPhNotification({ message: 'Pagamento confirmado!', type: 'success' });
                setTimeout(() => {
                    if (typeof refreshCheckoutContent === 'function') {
                        refreshCheckoutContent();
                    } else {
                        window.location.reload();
                    }
                }, 1000);
            }
        });
    };

    const manualCheckInterval = setInterval(checkRunner, 15000);

    const manualCheckTimeout = setTimeout(() => {
        clearInterval(manualCheckInterval);
        notification.remove(); // Isso irá parar o loop de animação
        showPhNotification({ message: 'Ainda não recebemos a confirmação do seu pagamento.', type: 'error' });
        isCheckingManually = false;
        if (iPaidButton) iPaidButton.disabled = false;
    }, 1800000); // 30 minutos

    checkRunner();
};