jQuery(function ($) {
    'use strict';

    // --- ELEMENTOS E INPUTS HIDDEN ---
    const container = $('#paghiper-due-date-container');
    if (!container.length) return;

    const modeToggle = $('#due-date-mode-toggle');
    const daysSection = $('#days-mode-section');
    const minutesSection = $('#minutes-mode-section');

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
    const daysDisplay = daysSection.find('.days-display');
    const initialDays = paghiper_settings.due_date_mode === 'days' ? parseInt(paghiper_settings.due_date_value, 10) : 3;
    daysDisplay.attr('data-value', initialDays); // Usaremos bounty aqui

    daysSection.find('.chevron-control').on('click', function() {
        let currentValue = parseInt(daysDisplay.attr('data-value'), 10);
        currentValue += $(this).data('action') === 'increment' ? 1 : -1;
        if (currentValue < 1) currentValue = 1; // Mínimo de 1 dia
        
        daysDisplay.attr('data-value', currentValue);
        valueInput.val(currentValue).trigger('change');
    });

    // --- LÓGICA DA SEÇÃO "CRONÔMETRO" ---
    // (Esta é a parte mais complexa e é um pseudo-código/guia)
    // Você vai precisar de uma função para converter o total de minutos (salvo no BD) para D/H/M
    function updateTotalMinutes() {
        const days = parseInt($('#cron-days').text());
        const hours = parseInt($('#cron-hours').text());
        const minutes = parseInt($('#cron-minutes').text());
        const totalMinutes = (days * 24 * 60) + (hours * 60) + minutes;
        valueInput.val(totalMinutes).trigger('change');
    }
    
    // Adicionar listeners nos chevrons ou outros controles para o cronômetro
    // Ex: ao incrementar minutos
    // let min = parseInt($('#cron-minutes').text());
    // min++;
    // if (min > 59) {
    //   min = 0;
    //   // Incrementar horas... e assim por diante
    // }
    // $('#cron-minutes').text(min);
    // updateTotalMinutes();


    // --- ANIMAÇÕES ---
    // Intersection Observer é mais moderno que Waypoints
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Dispara o Bounty.js no elemento visível
                const display = $(entry.target).find('.days-display, .time-display');
                display.each(function() {
                    const el = $(this)[0];
                    bounty.default({ el: el, value: el.getAttribute('data-value') || el.innerText });
                });
                observer.unobserve(entry.target); // Anima só uma vez
            }
        });
    }, { threshold: 0.5 }); // Dispara quando 50% estiver visível

    if(daysSection.hasClass('active')) observer.observe(daysSection[0]);
    if(minutesSection.hasClass('active')) observer.observe(minutesSection[0]);


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