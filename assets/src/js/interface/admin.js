jQuery(function ($) {
    'use strict';

    // --- ELEMENTOS E INPUTS HIDDEN ---
    const container = $('#paghiper-due-date-container');
    if (!container.length) return;

    const modeToggle = $('#due-date-mode-toggle');
    const daysSection = $('#days-mode-section');
    const minutesSection = $('#minutes-mode-section');

    // Classe para gerenciar dígitos do odômetro
    class OdometerDigit {
        constructor(value, isLast = false) {
            this.element = $('<div class="odometer-digit">');
            this.innerElement = $('<div class="odometer-digit-inner">');
            this.currentValue = parseInt(value, 10);
            this.isLast = isLast; // último dígito pode rolar infinitamente

            this.element.append(this.innerElement);
            this.updateDisplay();
        }

        updateDisplay() {
            this.innerElement.html(`
                <span class="odometer-digit-value">${this.currentValue}</span>
                <span class="odometer-digit-value">${this.getNextValue(1)}</span>
                <span class="odometer-digit-value">${this.getNextValue(-1)}</span>
            `);
        }

        getNextValue(direction) {
            if (this.isLast) {
                let next = this.currentValue + direction;
                if (next > 9) next = 0;
                if (next < 0) next = 9;
                return next;
            }
            return this.currentValue;
        }

        setValue(newValue, direction) {
            if (newValue === this.currentValue) return false;

            const className = direction > 0 ? 'rolling-up' : 'rolling-down';
            this.element.addClass(className);
            
            setTimeout(() => {
                this.currentValue = newValue;
                this.updateDisplay();
                this.element.removeClass(className);
            }, 200);

            return true;
        }
    }

    // Classe para gerenciar grupos de dígitos
    class OdometerDisplay {
        constructor(element, initialValue, maxValue = 99) {
            this.container = $(element);
            this.maxValue = maxValue;
            this.value = initialValue;
            this.digits = [];
            
            // Criar wrapper
            this.wrapper = $('<div class="odometer-display">');
            this.container.html(this.wrapper);
            
            // Criar dígitos
            const valueStr = this.value.toString().padStart(2, '0');
            for (let i = 0; i < valueStr.length; i++) {
                const digit = new OdometerDigit(valueStr[i], i === valueStr.length - 1);
                this.digits.push(digit);
                this.wrapper.append(digit.element);
            }
        }

        setValue(newValue, animate = true) {
            if (newValue === this.value) return;
            
            // Garantir limites
            newValue = Math.max(0, Math.min(newValue, this.maxValue));
            
            const oldStr = this.value.toString().padStart(2, '0');
            const newStr = newValue.toString().padStart(2, '0');
            
            // Atualizar cada dígito
            for (let i = 0; i < this.digits.length; i++) {
                const oldDigit = parseInt(oldStr[i], 10);
                const newDigit = parseInt(newStr[i], 10);
                
                if (oldDigit !== newDigit) {
                    const direction = newValue > this.value ? 1 : -1;
                    this.digits[i].setValue(newDigit, direction);
                }
            }
            
            this.value = newValue;
        }
    }

    // Nossos inputs que serão salvos
    const modeInput = $('#woocommerce_paghiper_pix_due_date_mode');
    const valueInput = $('#woocommerce_paghiper_pix_due_date_value');
    
    // --- LÓGICA DO TOGGLE ---
    modeToggle.on('change', function() {
        const isMinutesMode = $(this).is(':checked');
        daysSection.toggleClass('active', !isMinutesMode);
        minutesSection.toggleClass('active', isMinutesMode);
        modeInput.val(isMinutesMode ? 'minutes' : 'days').trigger('change');
        // Ao trocar, podemos resetar ou converter os valores
    }).trigger('change');

    // --- LÓGICA DA SEÇÃO "DIAS" ---
    const initialDays = paghiper_settings.due_date_mode === 'days' ? parseInt(paghiper_settings.due_date_value, 10) : 3;
    const daysOdometer = new OdometerDisplay(daysSection.find('.days-display'), initialDays, 3);

    daysSection.find('.chevron-control').on('click', function() {
        const direction = $(this).data('action') === 'increment' ? 1 : -1;
        const newValue = Math.max(1, Math.min(3, daysOdometer.value + direction));
        
        if (newValue !== daysOdometer.value) {
            daysOdometer.setValue(newValue);
            valueInput.val(newValue).trigger('change');
        }
    });

    // --- LÓGICA DO CRONÔMETRO ---
    let chronoOdometers = {
        days: null,
        hours: null,
        minutes: null
    };

    const chronoLimits = {
        days: 3,
        hours: 23,
        minutes: 59
    };

    // Função para atualizar o valor total em minutos no input hidden
    function updateTotalMinutes() {
        const days = chronoOdometers.days.value;
        const hours = chronoOdometers.hours.value;
        const minutes = chronoOdometers.minutes.value;
        const totalMinutes = (days * 24 * 60) + (hours * 60) + minutes;
        valueInput.val(totalMinutes).trigger('change');
    }

    // Inicializa os displays do cronômetro com os valores corretos
    function initChronoDisplays() {
        let initialValues = { days: 0, hours: 0, minutes: 30 }; // Default de 30 minutos

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

        chronoOdometers.days = new OdometerDisplay($('#cron-days'), initialValues.days, chronoLimits.days);
        chronoOdometers.hours = new OdometerDisplay($('#cron-hours'), initialValues.hours, chronoLimits.hours);
        chronoOdometers.minutes = new OdometerDisplay($('#cron-minutes'), initialValues.minutes, chronoLimits.minutes);
        
        // Atualiza o valor inicial no input hidden
        updateTotalMinutes();
    }

    initChronoDisplays();

    // Manipuladores para os controles do cronômetro (dias, horas, minutos)
    $('#minutes-mode-section .chevron-control').on('click', function() {
        const $this = $(this);
        const unitContainer = $this.closest('.time-unit');
        let unit;

        // Determina a unidade (dias, horas, minutos) com base no elemento pai
        if (unitContainer.find('#cron-days').length) {
            unit = 'days';
        } else if (unitContainer.find('#cron-hours').length) {
            unit = 'hours';
        } else {
            unit = 'minutes';
        }

        const action = $this.data('action');
        const direction = action === 'increment' ? 1 : -1;
        let newValue = chronoOdometers[unit].value + direction;

        // Aplica os limites (0-3 para dias, 0-23 para horas, 0-59 para minutos) de forma circular
        if (newValue < 0) {
            newValue = chronoLimits[unit];
        } else if (newValue > chronoLimits[unit]) {
            newValue = 0;
        }

        chronoOdometers[unit].setValue(newValue);
        updateTotalMinutes();
    });


    // --- VALIDAÇÃO ANTES DE SALVAR ---
    $('form#mainform').on('submit', function(e) {
        if (modeInput.val() === 'minutes') {
            const totalMinutes = parseInt(valueInput.val(), 10);
            const threeDaysInMinutes = 3 * 24 * 60;

            if (totalMinutes > threeDaysInMinutes) {
                const useDaysInstead = confirm('Configurações de vencimento superiores a 3 dias no modo cronômetro podem usar mais recursos. Deseja converter para o modo "Dias" para melhor performance?');
                
                if (useDaysInstead) {
                    // Prevenir o envio padrão
                    e.preventDefault();
                    
                    // Converter para dias e mudar o modo
                    const totalDays = Math.ceil(totalMinutes / (24 * 60));
                    modeInput.val('days');
                    valueInput.val(totalDays);
                    
                    // Enviar o formulário programaticamente após a alteração
                    // Usar um timeout pequeno pode ajudar a garantir que os valores foram setados
                    setTimeout(() => $(this).submit(), 100);
                }
                // Se o usuário clicar em "Cancelar", o formulário envia normalmente.
            }
        }
    });
});