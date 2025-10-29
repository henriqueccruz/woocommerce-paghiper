jQuery(document).ready( function($){
	
	// Deal with dismissable notices
	$( '.paghiper-dismiss-notice' ).on( 'click', '.notice-dismiss', function() {
		let noticeId = $(this).parent().data('notice-id');
		var data = {
			action: 'paghiper_dismiss_notice',
			notice: noticeId
		};
		
		$.post( notice_params.ajaxurl, data, function() {
		});
	});

	$( '.paghiper-notice' ).on( 'click', '.ajax-action', function() {

		let noticeId 		= $(this).data('notice-key'),
			noticeAction 	= $(this).data('action');

		var data = {
			'action'	: 'paghiper_answer_notice',
			'noticeId'	: noticeId,
			'userAction': noticeAction
		};
		
		$.post( notice_params.ajaxurl, data, function() {
		});

		$(".paghiper-review-nag").hide();

	});

	// Provides maskable fields for date operations
	if(typeof $.fn.mask === 'function') {
		$(".date").mask("00/00/0000", {placeholder: "__/__/____", clearIfNotMatch: true});
	}

	// Copy Transaction ID to clipboard
	$('.paghiper-copy-transaction-id').on('click', function() {
		const transactionId = $(this).data('transaction-id');
		if (transactionId) {
			copyTextToClipboard(transactionId);
			showPhNotification({ message: 'ID da transação copiado!', type: 'success' });
		}
	});

    // --- ODOMETER ROTOR LOGIC ---
    const container = $('#paghiper-due-date-container');
    if (container.length) {

        // The hidden input that stores the final calculated value in minutes.
        const valueInput = $('#woo_paghiper_expiration_date');

        /**
         * Manages the animation and state of a single digit rotor in the odometer.
         */
        class OdometerDigit {
            constructor(initialValue) {
                this.element = $('<div class="odometer-digit"></div>');
                this.currentValue = parseInt(initialValue, 10);
                
                this.rotor = $('<div class="odometer-rotor"></div>');
                // Create the digit strip: 0-9 and then another 0 for wrap-around animation.
                for (let i = 0; i < 10; i++) {
                    this.rotor.append($('<div class="odometer-digit-value"></div>').text(i));
                }
                this.rotor.append($('<div class="odometer-digit-value"></div>').text(0));

                this.element.append(this.rotor);
            }

            /**
             * Sets the initial vertical position of the rotor without animation.
             */
            initializePosition() {
                const digitHeight = this.element.height();
                if (digitHeight === 0) return;
                this.rotor.css({ transition: 'none', transform: `translateY(-${this.currentValue * digitHeight}px)` });
            }

            /**
             * Animates the rotor to a new digit value.
             * @param {number} newValue The new digit (0-9).
             * @param {number} direction The direction of the change (1 for up, -1 for down).
             */
            setValue(newValue, direction) {
                if (newValue === this.currentValue) return;

                const digitHeight = this.element.height();
                if (digitHeight === 0) return;
                const rotor = this.rotor;

                const isWrappingUp = this.currentValue === 9 && newValue === 0 && direction > 0;
                const isWrappingDown = this.currentValue === 0 && newValue === 9 && direction < 0;

                if (isWrappingUp) {
                    // Animate to the 10th frame (which shows '0')
                    rotor.css({ transition: 'transform 0.4s ease-in-out' });
                    rotor.css({ transform: `translateY(-${10 * digitHeight}px)` });
                    // After animation, reset to the top '0' frame without animation
                    setTimeout(() => {
                        rotor.css({ transition: 'none', transform: 'translateY(0px)' });
                    }, 400);
                } else if (isWrappingDown) {
                    // Jump to the bottom '0' frame without animation to prepare for animating "up"
                    rotor.css({ transition: 'none', transform: `translateY(-${10 * digitHeight}px)` });
                    // Then, with a tiny delay, animate "up" to the 9th frame
                    setTimeout(() => {
                        rotor.css({ transition: 'transform 0.4s ease-in-out' });
                        rotor.css({ transform: `translateY(-${9 * digitHeight}px)` });
                    }, 20);
                } else {
                    // Normal transition
                    rotor.css({ transition: 'transform 0.4s ease-in-out' });
                    rotor.css({ transform: `translateY(-${newValue * digitHeight}px)` });
                }

                this.currentValue = newValue;
            }
        }

        /**
         * Manages a complete odometer display for a unit (e.g., days, hours, minutes).
         * Handles dynamic digit expansion, user clicks for direct editing, and value changes.
         */
        class OdometerDisplay {
            static LIMITS = {
                'pix-days':    { min: 0, max: 400,  wrap: true },
                'hours':       { min: 0, max: 23, wrap: true },
                'minutes':     { min: 0, max: 59, wrap: true }
            };

            constructor(element, initialValue, type) {
                this.container = $(element);
                this.config = OdometerDisplay.LIMITS[type];
                this.value = Math.max(this.config.min, Math.min(initialValue, this.config.max));
                this.digits = [];
                this.isEditing = false;
                
                this.wrapper = $('<div class="odometer-display"></div>');
                this.container.html(this.wrapper);
                
                const minDigits = 2;
                const valueStr = this.value.toString().padStart(minDigits, '0');

                for (let i = 0; i < valueStr.length; i++) {
                    const digit = new OdometerDigit(valueStr[i]);
                    this.digits.push(digit);
                    this.wrapper.append(digit.element);
                    digit.initializePosition();
                }
                // Set initial class based on the number of digits.
                if (this.digits.length === 3) {
                    this.wrapper.addClass('has-three-digits');
                } else {
                    this.wrapper.removeClass('has-three-digits');
                }

                // Make the display clickable to enable editing.
                this.wrapper.on('click', () => {
                    if (!this.isEditing) {
                        this.showInput();
                    }
                });
            }

            /**
             * Hides the odometer display and shows a number input for direct editing.
             */
            showInput() {

                let wrapperWidth = this.wrapper.outerWidth(),
                    wrapperHeight = this.wrapper.outerHeight();

                this.isEditing = true;
                this.wrapper.hide();
                const input = $('<input type="number" class="odometer-input" />');
                
                // Apply some basic styling to make the input fit in.
                input.css({
                    'width': wrapperWidth + 'px',
                    'height': wrapperHeight + 'px',
                    'font-size': '1.8em',
                    'text-align': 'center',
                    'border': '1px solid #999',
                    'background-color': '#fff',
                    'box-sizing': 'border-box',
                    'margin': 0,
                    'padding': 0,
                });

                input.val(this.value);
                this.container.append(input);
                input.focus().select();

                const ghost = $('<span style="display:none"></span>');
                ghost.css({
                    'font-size': input.css('font-size'),
                    'font-family': input.css('font-family'),
                });
                $('body').append(ghost);

                input.on('input', function() {
                    ghost.text($(this).val());
                    $(this).css('width', ghost.width() + 10 + 'px');
                }).trigger('input');

                // Creates a handler function to process the user's input.
                const handleInput = () => {
                    let newValue = parseInt(input.val(), 10);

                    // Validate the entered value, clamping it within the min/max limits.
                    if (isNaN(newValue)) {
                        newValue = this.value; // Revert if not a number
                    } else {
                        newValue = Math.max(this.config.min, Math.min(newValue, this.config.max));
                    }

                    input.remove();
                    this.wrapper.show();
                    this.isEditing = false;

                    // If the value changed, update the display and the total minutes.
                    if (newValue !== this.value) {
                        const direction = newValue > this.value ? 1 : -1;
                        this.setValue(newValue, direction);
                        enforceMaxLimit();
                        updateTotalMinutes();
                    }
                };

                // Handlers for when the input is submitted or cancelled.
                input.on('blur', handleInput);
                input.on('keydown', (e) => {
                    if (e.key === 'Enter') {
                        handleInput();
                    } else if (e.key === 'Escape') {
                        input.remove();
                        this.wrapper.show();
                        this.isEditing = false;
                    }
                });
            }

            /**
             * Sets the display to a new value, handling animations and dynamic digit resizing.
             * @param {number} newValue The new integer value for the display.
             * @param {number} direction The direction of change for animation purposes (1 for up, -1 for down).
             */
            setValue(newValue, direction) {
                if (newValue === this.value) return;

                const minDigits = 2;
                const oldStr = this.value.toString();
                const newStr = newValue.toString();
                const oldLen = this.digits.length; // Use the actual number of digits
                const newLen = Math.max(minDigits, newStr.length);

                // Add or remove digit elements if the number of digits has changed.
                if (newLen > oldLen) {
                    for (let i = oldLen; i < newLen; i++) {
                        const digit = new OdometerDigit('0');
                        this.digits.unshift(digit);
                        this.wrapper.prepend(digit.element);
                        digit.initializePosition();
                    }
                } else if (newLen < oldLen) {
                    for (let i = newLen; i < oldLen; i++) {
                        this.digits[0].element.remove();
                        this.digits.shift();
                    }
                }

                // Add/remove class based on the number of digits.
                if (newLen === 3) {
                    this.wrapper.addClass('has-three-digits');
                } else {
                    this.wrapper.removeClass('has-three-digits');
                }

                // Pad strings to the current number of digits for comparison.
                const paddedOldStr = oldStr.padStart(this.digits.length, '0');
                const paddedNewStr = newStr.padStart(this.digits.length, '0');

                // Update each digit individually.
                for (let i = 0; i < this.digits.length; i++) {
                    const oldDigit = parseInt(paddedOldStr[i], 10);
                    const newDigit = parseInt(paddedNewStr[i], 10);
                    if (oldDigit !== newDigit) {
                        this.digits[i].setValue(newDigit, direction);
                    }
                }

                this.value = newValue;
            }

            /**
             * Increments the odometer's value by one, handling wrap-around logic.
             * @returns {boolean} True if the value wrapped around (e.g., 59 to 0).
             */
            increment() {
                let wrapped = false;
                let newValue = this.value + 1;
                if (newValue > this.config.max) {
                    if (this.config.wrap) {
                        newValue = this.config.min;
                        wrapped = true;
                    } else {
                        newValue = this.config.max;
                    }
                }
                this.setValue(newValue, 1);
                return wrapped;
            }

            /**
             * Decrements the odometer's value by one, handling wrap-around logic.
             * @returns {boolean} True if the value wrapped around (e.g., 0 to 59).
             */
            decrement() {
                let wrapped = false;
                let newValue = this.value - 1;
                if (newValue < this.config.min) {
                    if (this.config.wrap) {
                        newValue = this.config.max;
                        wrapped = true;
                    }
                }
                this.setValue(newValue, -1);
                return wrapped;
            }
        }

        let chronoOdometers = {};
        let daysOdometer;

        function updateTotalMinutes() {
            if (paghiper_backend_settings.due_date_mode === 'minutes') {
                const days = chronoOdometers.days.value;
                const hours = chronoOdometers.hours.value;
                const minutes = chronoOdometers.minutes.value;
                const totalMinutes = (days * 24 * 60) + (hours * 60) + minutes;
                valueInput.val(totalMinutes).trigger('change');
            } else {
                valueInput.val(daysOdometer.value * 24 * 60).trigger('change');
            }
        }

        function enforceMaxLimit() {
            if (paghiper_backend_settings.due_date_mode === 'minutes') {
                const daysOdometer = chronoOdometers.days;
                if (daysOdometer.value === daysOdometer.config.max) {
                    if (chronoOdometers.hours.value !== 0) {
                        chronoOdometers.hours.setValue(0, -1);
                    }
                    if (chronoOdometers.minutes.value !== 0) {
                        chronoOdometers.minutes.setValue(0, -1);
                    }
                }
            }
        }

        function initChronoDisplays() {
            let initialValues = { days: 0, hours: 0, minutes: 0 };
            const hiddenInputDate = paghiper_backend_settings.initial_datetime_value;
    
            if (hiddenInputDate) {
                const dateParts = hiddenInputDate.match(/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/);
                if (dateParts) {
                    const year = parseInt(dateParts[1], 10);
                    const month = parseInt(dateParts[2], 10) - 1;
                    const day = parseInt(dateParts[3], 10);
                    const hours = parseInt(dateParts[4], 10);
                    const minutes = parseInt(dateParts[5], 10);
    
                    const targetDate = new Date(year, month, day, hours, minutes, 0);
                    const now = new Date();
                    const diffMs = targetDate.getTime() - now.getTime();
                    const diffMinutes = Math.round(diffMs / (1000 * 60));
    
                    if (diffMinutes > 0) {
                        initialValues = {
                            days: Math.floor(diffMinutes / (24 * 60)),
                            hours: Math.floor((diffMinutes % (24 * 60)) / 60),
                            minutes: diffMinutes % 60
                        };
                    }
                }
            }
    
            if (paghiper_backend_settings.due_date_mode === 'minutes') {
                chronoOdometers.days = new OdometerDisplay($('#cron-days-backend-minutes'), initialValues.days, 'pix-days');
                chronoOdometers.hours = new OdometerDisplay($('#cron-hours-backend'), initialValues.hours, 'hours');
                chronoOdometers.minutes = new OdometerDisplay($('#cron-minutes-backend'), initialValues.minutes, 'minutes');
            } else {
                daysOdometer = new OdometerDisplay($('#cron-days-backend'), initialValues.days, 'pix-days');
                console.log('Doing alternative init');
            }
            
            enforceMaxLimit();
            updateTotalMinutes();
        }

        initChronoDisplays();

        $('#paghiper-due-date-container .chevron-control').on('click', function() {
            if ($('.odometer-input').length) {
                $('.odometer-input').trigger('blur');
                return;
            }
            const $this = $(this);
            const unit = $this.data('unit');
            const action = $this.data('action');

            if (paghiper_backend_settings.due_date_mode === 'minutes') {
                if (action === 'increment') {
                    if (chronoOdometers[unit].increment()) {
                        if (unit === 'minutes') {
                            if (chronoOdometers.hours.increment()) {
                                chronoOdometers.days.increment();
                            }
                        } else if (unit === 'hours') {
                            chronoOdometers.days.increment();
                        }
                    }
                } else { // decrement
                    if (chronoOdometers[unit].decrement()) {
                        if (unit === 'minutes') {
                            if (chronoOdometers.hours.decrement()) {
                                chronoOdometers.days.decrement();
                            }
                        } else if (unit === 'hours') {
                            chronoOdometers.days.decrement();
                        }
                    }
                }
            } else {
                if (action === 'increment') {
                    daysOdometer.increment();
                } else {
                    daysOdometer.decrement();
                }
            }

            enforceMaxLimit();
            updateTotalMinutes();
        });
    }

    // AJAX submission for new due date
    $('body').on('click', '#paghiper-resend-ajax-button', function(e) {
        e.preventDefault();

        const button = $(this);
        const orderId = button.data('order-id');
        const totalMinutes = $('#woo_paghiper_expiration_date').val();

        if (paghiper_backend_settings.due_date_mode === 'minutes' && totalMinutes < 5) {
            if (!confirm(`A transação gerada vai expirar em apenas ${totalMinutes} minutos. Continuar?`)) {
                return;
            }
        }

        const totalHours = totalMinutes / 60;

        if (totalHours > 24) {
            if (!confirm('A data de vencimento é maior que 24 horas a partir de agora. O cronômetro de contagem regressiva não será exibido no e-mail do cliente. Deseja continuar?')) {
                return;
            }
        }

        button.prop('disabled', true).text('Enviando...');

        const data = {
            action: 'paghiper_resend_payment',
            order_id: orderId,
            total_minutes: totalMinutes,
            security: $('#paghiper_resend_nonce').val()
        };

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                showPhNotification({ message: response.data.message, type: 'success' });
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showPhNotification({ message: response.data.message, type: 'error' });
            }
            button.prop('disabled', false).text('Definir e Reenviar');
        }).fail(function() {
            showPhNotification({ message: 'Ocorreu um erro inesperado. Tente novamente.', type: 'error' });
            button.prop('disabled', false).text('Definir e Reenviar');
        });
    });

});

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

window.copyPaghiperEmv = function() {
	// Start with objects to be selected
	let paghiperEmvBlock 	= document.querySelector('.paghiper-pix-code');
	let targetPixCode 		= paghiperEmvBlock.querySelector('textarea');
	let targetButton 		= paghiperEmvBlock.querySelector('button');

	// Select the text field
	targetPixCode.select();
	targetPixCode.setSelectionRange(0, 99999); /* For mobile devices */
  
	// Copy the text inside the text field
	navigator.clipboard.writeText(targetPixCode.value);

	// Store selection range insie button dataset
	targetButton.dataset.originalText = targetButton.innerHTML;
	targetButton.innerHTML = 'Copiado!';

	setTimeout(function(targetButton) {
		// Restore original text from dataset store value
		let originalText = targetButton.dataset.originalText;
		targetButton.innerHTML = originalText;

		// Remove selection range
		document.getSelection().removeAllRanges();
	}, 2000, targetButton,);
};
