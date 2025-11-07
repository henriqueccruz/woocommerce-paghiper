jQuery(function ($) {
    'use strict';

    function showPhNotification(options) {
        const { message, type = 'info', duration = 3000 } = options;
        const container = $('#ph-reusable-notifications');
        if (!container.length) {
            $('body').append('<div id="ph-reusable-notifications"></div>');
        }

        const notification = $('<div></div>')
            .addClass(`ph-notification ${type}`)
            .html(message);

        $('#ph-reusable-notifications').append(notification);

        if (duration > 0) {
            setTimeout(() => {
                notification.addClass('fade-out');
                notification.on('animationend', () => {
                    notification.remove();
                });
            }, duration);
        }

        return notification;
    }


    if (typeof paghiper_settings === 'undefined') {
        return;
    }

    const container = $('#paghiper-due-date-container');
    if (!container.length) return;

    const modeInput = $('#woocommerce_paghiper_pix_due_date_mode, #woocommerce_paghiper_billet_due_date_mode');
    const valueInput = $('#woocommerce_paghiper_pix_due_date_value, #woocommerce_paghiper_billet_due_date_value');

    // Move credential actions button
    const apiKeyRow = $('#woocommerce_paghiper_pix_api_key, #woocommerce_paghiper_billet_api_key');
    const credentialActionsRow = $('#copy-credentials, #test-credentials');
    if (apiKeyRow.length && credentialActionsRow.length) {
        credentialActionsRow.insertAfter(apiKeyRow);
    }

    // Odometer logic for both PIX and Boleto
    const modeToggle = $('#due-date-mode-toggle');
    const daysSection = $('#days-mode-section');
    const minutesSection = $('#minutes-mode-section');

    $('.mode-switcher.disabled').on('click', function(e) {
        e.preventDefault();
        showPhNotification({ message: 'Essa opção está disponível apenas para o PIX PagHiper.', type: 'info' });
    });


    if (paghiper_settings.is_pix) {
        modeToggle.on('change', function() {
            const isMinutesMode = $(this).is(':checked');
            daysSection.toggleClass('active', !isMinutesMode);
            minutesSection.toggleClass('active', isMinutesMode);
            modeInput.val(isMinutesMode ? 'minutes' : 'days').trigger('change');
            updateValue();
        });
    } else {
        daysSection.addClass('active');
        minutesSection.removeClass('active');
    modeInput.val('days');
    }

    $('#mainform').on('submit', function(e) {
        if (paghiper_settings.is_pix && modeInput.val() === 'minutes') {
            const totalMinutes = valueInput.val();
            if (totalMinutes < 5) {
                if (!confirm(`A(s) transação(ões) gerada(s) vai(vão) expirar em apenas ${totalMinutes} minutos. Continuar?`)) {
                    e.preventDefault();
                }
            }
        }
    });

    let chronoOdometers = {};
    let daysOdometer;

    function updateValue() {
        if (paghiper_settings.is_pix && modeToggle.is(':checked')) {
            const days = chronoOdometers.days.value;
            const hours = chronoOdometers.hours.value;
            const minutes = chronoOdometers.minutes.value;
            const totalMinutes = (days * 24 * 60) + (hours * 60) + minutes;
            valueInput.val(totalMinutes).trigger('change');
        } else {
            valueInput.val(daysOdometer.value).trigger('change');
        }
    }

    class OdometerDigit {
        constructor(initialValue) {
            this.element = $('<div class="odometer-digit"></div>');
            this.currentValue = parseInt(initialValue, 10);
            
            this.rotor = $('<div class="odometer-rotor"></div>');
            for (let i = 0; i < 10; i++) {
                this.rotor.append($('<div class="odometer-digit-value"></div>').text(i));
            }
            this.rotor.append($('<div class="odometer-digit-value"></div>').text(0));

            this.element.append(this.rotor);
        }

        initializePosition() {
            const digitHeight = this.element.height();
            if (digitHeight === 0) return;
            this.rotor.css({ transition: 'none', transform: `translateY(-${this.currentValue * digitHeight}px)` });
        }

        setValue(newValue, direction) {
            if (newValue === this.currentValue) return;
            const digitHeight = this.element.height();
            if (digitHeight === 0) return;
            const rotor = this.rotor;
            const isWrappingUp = this.currentValue === 9 && newValue === 0 && direction > 0;
            const isWrappingDown = this.currentValue === 0 && newValue === 9 && direction < 0;

            if (isWrappingUp) {
                rotor.css({ transition: 'transform 0.4s ease-in-out', transform: `translateY(-${10 * digitHeight}px)` });
                setTimeout(() => {
                    rotor.css({ transition: 'none', transform: 'translateY(0px)' });
                }, 400);
            } else if (isWrappingDown) {
                rotor.css({ transition: 'none', transform: `translateY(-${10 * digitHeight}px)` });
                setTimeout(() => {
                    rotor.css({ transition: 'transform 0.4s ease-in-out', transform: `translateY(-${9 * digitHeight}px)` });
                }, 20);
            } else {
                rotor.css({ transition: 'transform 0.4s ease-in-out', transform: `translateY(-${newValue * digitHeight}px)` });
            }
            this.currentValue = newValue;
        }
    }

    class OdometerDisplay {
        static LIMITS = {
            'boleto-days': { min: 1, max: 400, wrap: true },
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
            
            const minDigits = (this.value >= 100) ? 3 : 2;
            const valueStr = this.value.toString().padStart(minDigits, '0');

            for (let i = 0; i < valueStr.length; i++) {
                const digit = new OdometerDigit(valueStr[i]);
                this.digits.push(digit);
                this.wrapper.append(digit.element);
                digit.initializePosition();
            }
            if (this.digits.length === 3) {
                this.wrapper.addClass('has-three-digits');
            } else {
                this.wrapper.removeClass('has-three-digits');
            }

            this.wrapper.on('click', () => {
                if (!this.isEditing) {
                    this.showInput();
                }
            });
        }

        showInput() {

            let wrapperWidth = this.wrapper.outerWidth(),
                wrapperHeight = this.wrapper.outerHeight();

            console.log( wrapperWidth, wrapperHeight );

            this.isEditing = true;
            this.wrapper.hide();
            const input = $('<input type="number" class="odometer-input" />');
            
            input.css({
                'min-width': wrapperWidth + 'px',
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

            const handleInput = () => {
                let newValue = parseInt(input.val(), 10);

                if (isNaN(newValue)) {
                    newValue = this.value;
                } else {
                    newValue = Math.max(this.config.min, Math.min(newValue, this.config.max));
                }

                input.remove();
                this.wrapper.show();
                this.isEditing = false;

                if (newValue !== this.value) {
                    const direction = newValue > this.value ? 1 : -1;
                    this.setValue(newValue, direction);
                    updateValue();
                }
            };

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

        setValue(newValue, direction) {
            if (newValue === this.value) return;

            const minDigits = (newValue >= 100) ? 3 : 2;
            const oldStr = this.value.toString();
            const newStr = newValue.toString();
            const oldLen = this.digits.length;
            const newLen = Math.max(minDigits, newStr.length);

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

            if (newLen === 3) {
                this.wrapper.addClass('has-three-digits');
            } else {
                this.wrapper.removeClass('has-three-digits');
            }

            const paddedOldStr = oldStr.padStart(this.digits.length, '0');
            const paddedNewStr = newStr.padStart(this.digits.length, '0');

            for (let i = 0; i < this.digits.length; i++) {
                const oldDigit = parseInt(paddedOldStr[i], 10);
                const newDigit = parseInt(paddedNewStr[i], 10);
                if (oldDigit !== newDigit) {
                    this.digits[i].setValue(newDigit, direction);
                }
            }

            this.value = newValue;
        }

        increment() {
            let wrapped = false;
            let newValue = this.value + 1;
            if (newValue > this.config.max) {
                newValue = this.config.wrap ? this.config.min : this.config.max;
                wrapped = true;
            }
            this.setValue(newValue, 1);
            return wrapped;
        }

        decrement() {
            let wrapped = false;
            let newValue = this.value - 1;
            if (newValue < this.config.min) {
                newValue = this.config.max;
                wrapped = true;
            }
            this.setValue(newValue, -1);
            return wrapped;
        }
    }

    // Init Days Odometer
    const initialDays = paghiper_settings.due_date_mode === 'days' ? parseInt(paghiper_settings.due_date_value, 10) : 1;
    const odometerType = paghiper_settings.is_pix ? 'pix-days' : 'boleto-days';
    daysOdometer = new OdometerDisplay(daysSection.find('.days-display'), initialDays, odometerType);
    daysSection.find('.chevron-control').on('click', function() {
        if ($('.odometer-input').length) {
            $('.odometer-input').trigger('blur');
            return;
        }
        const action = $(this).data('action');
        if (action === 'increment') {
            daysOdometer.increment();
        } else {
            daysOdometer.decrement();
        }
        updateValue();
    });


    // Init Minutes Odometer
    if (paghiper_settings.is_pix) {
        function initChronoDisplays() {
            let initialValues = { days: 0, hours: 0, minutes: 30 };
            if (paghiper_settings.due_date_mode === 'minutes') {
                const totalMinutes = parseInt(paghiper_settings.due_date_value, 10);
                if (!isNaN(totalMinutes) && totalMinutes > 0) {
                    initialValues = {
                        days: Math.floor(totalMinutes / (24 * 60)),
                        hours: Math.floor((totalMinutes % (24 * 60)) / 60),
                        minutes: totalMinutes % 60
                    };
                }
            }
            chronoOdometers.days = new OdometerDisplay($('#cron-days-pix'), initialValues.days, 'pix-days');
            chronoOdometers.hours = new OdometerDisplay($('#cron-hours-pix'), initialValues.hours, 'hours');
            chronoOdometers.minutes = new OdometerDisplay($('#cron-minutes-pix'), initialValues.minutes, 'minutes');
            updateValue();
        }

        initChronoDisplays();

        $('#minutes-mode-section .chevron-control').on('click', function() {
            if ($('.odometer-input').length) {
                $('.odometer-input').trigger('blur');
                return;
            }
            const $this = $(this);
            const unit = $this.data('unit');
            const action = $this.data('action');

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
            } else {
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
            updateValue();
        });

        modeToggle.trigger('change');
    }

    $('#copy-credentials').on('click', function() {

        let notificationElement = null;

        const loadingSpinner = '<span class="ph-notification-spinner"></span>';
        notificationElement = showPhNotification({
            message: `${loadingSpinner} Copiando credenciais...`,
            type: 'info',
            duration: 0 // Notificação Fixa
        });

        $.post(ajaxurl, { action: 'paghiper_copy_credentials', nonce: paghiper_settings.nonce, to: paghiper_settings.gateway_id }, function(response) {
            if (response.success) {


                showPhNotification({ 
                    message: 'Credenciais configuradas com sucesso!', 
                    type: 'success', 
                    duration: 5000 
                });
                
                setTimeout(() => {
                    location.reload();
                }, 1000);

            } else {
                showPhNotification({ message: response.data.message, type: 'error' });
            }

            if (notificationElement) {
                notificationElement.remove();
            }

        });
    });

    $('#test-credentials').on('click', function() {
        const apiKey = $('#woocommerce_paghiper_pix_api_key, #woocommerce_paghiper_billet_api_key').val();
        const token = $('#woocommerce_paghiper_pix_token, #woocommerce_paghiper_billet_token').val();

        let notificationElement = null;

        const loadingSpinner = '<span class="ph-notification-spinner"></span>';
        notificationElement = showPhNotification({
            message: `${loadingSpinner} Testando credenciais...`,
            type: 'info',
            duration: 0 // Notificação Fixa
        });

        $.post(ajaxurl, { action: 'paghiper_test_credentials', nonce: paghiper_settings.nonce, apiKey: apiKey, token: token }, function(response) {
            if (response.success) {
                showPhNotification({ message: response.data.message, type: 'success' });
            } else {
                showPhNotification({ message: response.data.message, type: 'error' });
            }

            if (notificationElement) {
                notificationElement.remove();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            // Handle errors, including 500 Internal Server Error
            if (jqXHR.status === 500) {
                showPhNotification({ message: 'Servidor indisponível (erro 500)', type: 'error' });
                // You can display an error message to the user or log details
            } else {
                showPhNotification({ message: 'Não foi possível checar suas credenciais agora.', type: 'error' });
            }

            if (notificationElement) {
                notificationElement.remove();
            }
        });
    });

    // --- Início da lógica do Downloader de Cronômetros ---
    const downloader = {
        form: $('form#mainform'),
        modal: $('#paghiper-downloader-modal'),
        progressBar: $('#paghiper-downloader-modal .paghiper-progress'),
        statusMessage: $('#paghiper-downloader-modal .paghiper-status-message'),
        initialMessage: $('#paghiper-downloader-modal .paghiper-modal-initial-message'),
        isDownloading: false,

        init: function() {
            this.form.on('submit', this.handleSubmit.bind(this));
        },

        openModal: function() {
            $('body').css('overflow', 'hidden')
            this.modal.dialog({
                modal: true,
                width: 500,
                closeOnEscape: false,
                draggable: false,
                resizable: false,
                open: function(event, ui) {
                    let titlebar        = $(this).parent().find(".ui-dialog-titlebar"),
                        targetContainer = $(this).parent().find(".paghiper-modal-content");

                    titlebar.prependTo(targetContainer);
                    $(this).parent().find('.ui-dialog-titlebar-close').hide();
                }
            });
        },

        closeModal: function() {
            $('body').css('overflow', '');
            if (this.modal.is(":ui-dialog")) {
                this.modal.dialog('close');
            }
        },

        updateProgress: function(percentage, message) {
            this.progressBar.css({
                width: percentage + '%',
                backgroundColor: '#4CAF50',
                color: '#fff'
            }).text(Math.round(percentage) + '%');
            this.statusMessage.text(message);
        },

        showError: function(message) {
            this.isDownloading = false;
            //this.initialMessage.hide();
            this.updateProgress(100, 'Erro.');
            this.progressBar.css('background-color', '#dc3232');
            this.statusMessage.html(message + '<br/><br/><button class="button button-primary" id="close-downloader-modal">Fechar</button>');
            $('#close-downloader-modal').on('click', this.closeModal.bind(this));
        },

        downloadBundle: function(bundleNumber) {
            return $.post(ajaxurl, {
                action: 'paghiper_download_timer_assets',
                nonce: paghiper_settings.nonce,
                bundle: bundleNumber
            });
        },

        downloadSequentially: function(bundles) {
            // 1. Create ONE deferred object for the whole sequence.
            const deferred = $.Deferred();
            const total = bundles.length;
            let currentIndex = 0;

            // 2. Create a recursive function `next`.
            const next = () => {
                // 3. Check for the exit condition.
                if (currentIndex >= total) {
                    deferred.resolve(); // If all downloads are done, resolve the main promise.
                    return;
                }

                // 4. Get the current bundle and update progress.
                const bundle = bundles[currentIndex];
                const percentage = ((currentIndex + 1) / total) * 100;
                this.updateProgress(percentage, `Baixando pacote ${currentIndex + 1} de ${total}...`);

                // 5. Call the download function for the current bundle.
                this.downloadBundle(bundle)
                    .done((response) => {
                        // 6. Manually check the response for WP-style errors.
                        if (response.success) {
                            // If successful, increment index and call next() to download the next bundle.
                            currentIndex++;
                            next();
                        } else {
                            // If WP returned an error, show it and reject the main promise.
                            const errorMsg = response.data && response.data.message ? response.data.message : 'Ocorreu um erro no servidor.';
                            this.showError(errorMsg);
                            deferred.reject();
                        }
                    })
                    .fail((xhr) => {
                        // 7. Handle genuine network/server errors.
                        const errorMsg = 'Falha de comunicação com o servidor. Verifique sua conexão e os logs de erro do PHP.';
                        this.showError(errorMsg);
                        deferred.reject(); // Reject the main promise.
                    });
            };

            // 8. Start the recursive sequence.
            next();

            // 9. Return the promise of the main deferred object.
            return deferred.promise();
        },

        handleSubmit: function(e) {
            // Se já estamos no processo de download, não faça nada
            if (this.isDownloading) {
                e.preventDefault();
                return;
            }

            // Condições para acionar o downloader
            const isPixPage = paghiper_settings.gateway_id === 'paghiper_pix';
            const isMinutesMode = $('#due-date-mode-toggle').is(':checked');
            const isGifDisabled = $('#woocommerce_paghiper_pix_disable_email_gif').is(':checked');

            // Se não for a página do PIX, ou não for modo minutos, ou o GIF estiver desativado, ou o check já passou, deixa salvar normal
            if (!isPixPage || !isMinutesMode || isGifDisabled || this.form.find('#paghiper_assets_checked').length > 0) {
                return;
            }

            e.preventDefault();
            this.isDownloading = true;
            this.openModal();

            // Reset modal UI from previous runs
            this.progressBar.css({
                width: '100%',
                backgroundColor: 'transparent',
                color: '#2c3338'
            });
            
            this.modal.find('.paghiper-modal-initial-message').show();
            this.updateProgress(0, '');

            const totalMinutes = parseInt(valueInput.val(), 10) || 0;
            const bundles_needed = Math.ceil(totalMinutes / 60);

            this.updateProgress(0, 'Verificando pacotes necessários...');

            $.post(ajaxurl, {
                action: 'paghiper_get_timer_asset_status',
                nonce: paghiper_settings.nonce,
                bundles_needed: bundles_needed
            }).done(response => {
                if (!response.success) {
                    this.showError(response.data.message);
                    return;
                }

                const missing = response.data.missing_bundles;
                if (missing.length === 0) {
                    this.updateProgress(100, 'Recursos já estão instalados e verificados!');
                    setTimeout(() => {
                        this.isDownloading = false;
                        this.closeModal();
                        this.form.append('<input type="hidden" id="paghiper_assets_checked" name="paghiper_assets_checked" value="1">');
                        console.log('Submitting with due_date_value:', valueInput.val());
                        this.form.find('button[name="save"]').click();
                    }, 1500);
                    return;
                }

                this.downloadSequentially(missing).done(() => {
                    this.progressBar.css('background-color', '#4CAF50');
                    this.updateProgress(100, 'Todos os pacotes foram instalados com sucesso!');
                    setTimeout(() => {
                        this.isDownloading = false;
                        this.closeModal();
                        this.form.append('<input type="hidden" id="paghiper_assets_checked" name="paghiper_assets_checked" value="1">');
                        this.form.find('button[name="save"]').click();
                    }, 1500);
                }).fail(() => {
                    // This block catches the rejection from the download chain.
                    // The error is already displayed by the handler inside downloadSequentially.
                    // This simply prevents the "unhandled promise rejection" error in the console.
                    console.log("Download sequence failed and was caught.");
                });

            }).fail((xhr) => {
                const errorMsg = 'Falha na comunicação com o servidor ao verificar o status dos pacotes. (Status: ' + xhr.status + ' ' + xhr.statusText + '). Verifique os logs do servidor para mais detalhes.';
                this.showError(errorMsg);
            });
        }
    };

    if (typeof paghiper_settings !== 'undefined' && paghiper_settings.gateway_id === 'paghiper_pix') {
        downloader.init();
    }
    // --- Fim da lógica do Downloader de Cronômetros ---

    window.paghiperDownloader = downloader;
});