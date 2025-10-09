jQuery(function ($) {
    'use strict';

    // --- ELEMENTOS E INPUTS HIDDEN ---
    const container = $('#paghiper-due-date-container');
    if (!container.length) return;

    const modeToggle = $('#due-date-mode-toggle');
    const daysSection = $('#days-mode-section');
    const minutesSection = $('#minutes-mode-section');

    // Classe para gerenciar a animação de um único dígito do odômetro
    class OdometerDigit {
        constructor(initialValue) {
            this.element = $('<div class="odometer-digit">');
            this.currentValue = parseInt(initialValue, 10);
            
            // O "rotor" que contém os números de 0 a 9
            this.rotor = $('<div class="odometer-rotor">');
            for (let i = 0; i < 10; i++) {
                this.rotor.append($('<div class="odometer-digit-value">').text(i));
            }
            // Adicionamos uma cópia do 0 no final para a animação de 9 -> 0
            this.rotor.append($('<div class="odometer-digit-value">').text(0));

            this.element.append(this.rotor);
        }

        initializePosition() {
            const digitHeight = this.element.height();
            if (digitHeight === 0) return;
            // Define a posição inicial sem animação
            this.rotor.css({ transition: 'none', transform: `translateY(-${this.currentValue * digitHeight}px)` });
        }

        setValue(newValue, direction) {
            if (newValue === this.currentValue) return;

            // Mede a altura a cada clique para garantir resiliência a mudanças de layout
            const digitHeight = this.element.height();
            if (digitHeight === 0) return; // Não anima se não tiver altura

            const rotor = this.rotor;
            const currentValue = this.currentValue;

            // Garante que a transição está ativa para a animação
            rotor.css({ transition: 'transform 0.4s ease-in-out' });

            // Se a animação "passar pelo zero" (ex: 9 -> 0 ou 0 -> 9), faz um tratamento especial
            if (direction > 0 && newValue < currentValue) { // Rolando para cima, ex: de 9 para 0
                // Move para a posição "10" (que é a cópia do 0)
                rotor.css({ transform: `translateY(-${10 * digitHeight}px)` });
                // Após a animação, reseta para a posição 0 sem animar
                setTimeout(() => {
                    rotor.css({ transition: 'none', transform: `translateY(0px)` });
                }, 400);
            } else if (direction < 0 && newValue > currentValue) { // Rolando para baixo, ex: de 0 para 9
                // Para animar de 0 para 9 para baixo, primeiro movemos o rotor para a posição 10 (o 0 de baixo) sem animar
                rotor.css({ transition: 'none', transform: `translateY(-${10 * digitHeight}px)` });
                // Força o navegador a redesenhar para aplicar o estado inicial
                rotor[0].offsetHeight;
                // Agora, com a transição, movemos para a posição 9
                rotor.css({ transition: 'transform 0.4s ease-in-out', transform: `translateY(-${9 * digitHeight}px)` });
            } else {
                // Animação normal
                rotor.css({ transform: `translateY(-${newValue * digitHeight}px)` });
            }

            this.currentValue = newValue;
        }
    }

    // Classe para gerenciar grupos de dígitos
    class OdometerDisplay {
        // Centraliza a configuração de todos os tipos de odômetros
        static LIMITS = {
            'boleto-days': { min: 1, max: 31, wrap: true }, // De 1 a 31, com virada
            'pix-days':    { min: 0, max: 3,  wrap: true },
            'hours':       { min: 0, max: 23, wrap: true },
            'minutes':     { min: 0, max: 59, wrap: true }
        };

        constructor(element, initialValue, type) {
            this.container = $(element);
            this.config = OdometerDisplay.LIMITS[type];
            this.value = Math.max(this.config.min, Math.min(initialValue, this.config.max)); // Garante valor inicial dentro dos limites
            this.digits = [];
            
            this.wrapper = $('<div class="odometer-display">');
            this.container.html(this.wrapper);
            
            const valueStr = this.value.toString().padStart(2, '0');
            for (let i = 0; i < valueStr.length; i++) {
                const digit = new OdometerDigit(valueStr[i]);
                this.digits.push(digit);
                this.wrapper.append(digit.element);
                digit.initializePosition();
            }
        }

        // Apenas atualiza a parte visual
        setValue(newValue, direction) {
            if (newValue === this.value) return;
            
            const oldStr = this.value.toString().padStart(2, '0');
            const newStr = newValue.toString().padStart(2, '0');
            const animDirection = direction !== undefined ? direction : (newValue > this.value ? 1 : -1);
            
            for (let i = 0; i < this.digits.length; i++) {
                const oldDigit = parseInt(oldStr[i], 10);
                const newDigit = parseInt(newStr[i], 10);
                this.digits[i].setValue(newDigit, animDirection);
            }
            
            this.value = newValue;
        }

        increment() {
            let newValue = this.value + 1;
            if (newValue > this.config.max) {
                newValue = this.config.wrap ? this.config.min : this.config.max;
            }
            this.setValue(newValue, 1);
        }

        decrement() {
            let newValue = this.value - 1;
            if (newValue < this.config.min) {
                newValue = this.config.wrap ? this.config.max : this.config.min;
            }
            this.setValue(newValue, -1);
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
    }).trigger('change');

    // --- LÓGICA DA SEÇÃO "DIAS" (BOLETO) ---
    const initialDays = paghiper_settings.due_date_mode === 'days' ? parseInt(paghiper_settings.due_date_value, 10) : 3;
    const daysOdometer = new OdometerDisplay(daysSection.find('.days-display'), initialDays, 'boleto-days');

    daysSection.find('.chevron-control').on('click', function() {
        const action = $(this).data('action');
        if (action === 'increment') {
            daysOdometer.increment();
        } else {
            daysOdometer.decrement();
        }
        // Atualiza o input hidden correspondente
        valueInput.val(daysOdometer.value).trigger('change');
    });

    // --- LÓGICA DO CRONÔMETRO (PIX) ---
    let chronoOdometers = {};

    // Função para atualizar o valor total em minutos no input hidden
    function updateTotalMinutes() {
        const days = chronoOdometers.days.value;
        const hours = chronoOdometers.hours.value;
        const minutes = chronoOdometers.minutes.value;
        const totalMinutes = (days * 24 * 60) + (hours * 60) + minutes;
        valueInput.val(totalMinutes).trigger('change');
    }

    // Inicializa os displays do cronômetro
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

        chronoOdometers.days = new OdometerDisplay($('#cron-days'), initialValues.days, 'pix-days');
        chronoOdometers.hours = new OdometerDisplay($('#cron-hours'), initialValues.hours, 'hours');
        chronoOdometers.minutes = new OdometerDisplay($('#cron-minutes'), initialValues.minutes, 'minutes');
        
        updateTotalMinutes();
    }

    initChronoDisplays();

    // Manipuladores para os controles do cronômetro
    $('#minutes-mode-section .chevron-control').on('click', function() {
        const $this = $(this);
        const unitContainer = $this.closest('.time-unit');
        const action = $this.data('action');
        let unit;

        if (unitContainer.find('#cron-days').length) unit = 'days';
        else if (unitContainer.find('#cron-hours').length) unit = 'hours';
        else unit = 'minutes';

        if (action === 'increment') {
            chronoOdometers[unit].increment();
        } else {
            chronoOdometers[unit].decrement();
        }
        
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