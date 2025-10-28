jQuery(function ($) {
    'use strict';

    if (typeof paghiper_settings === 'undefined') {
        return;
    }

    const container = $('#paghiper-due-date-container');
    if (!container.length) return;

    const modeInput = $('#woocommerce_paghiper_pix_due_date_mode, #woocommerce_paghiper_billet_due_date_mode');
    const valueInput = $('#woocommerce_paghiper_pix_due_date_value, #woocommerce_paghiper_billet_due_date_value');

    if (paghiper_settings.is_pix) {
        // PIX: Odometer logic
        const modeToggle = $('#due-date-mode-toggle');
        const daysSection = $('#days-mode-section');
        const minutesSection = $('#minutes-mode-section');

        modeToggle.on('change', function() {
            const isMinutesMode = $(this).is(':checked');
            daysSection.toggleClass('active', !isMinutesMode);
            minutesSection.toggleClass('active', isMinutesMode);
            modeInput.val(isMinutesMode ? 'minutes' : 'days').trigger('change');
            updateValue();
        });

        let chronoOdometers = {};
        let daysOdometer;

        function enforceMaxLimit() {
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

        function updateValue() {
            if (modeToggle.is(':checked')) {
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
                    rotor.css({ transition: 'transform 0.4s ease-in-out' });
                    rotor.css({ transform: `translateY(-${10 * digitHeight}px)` });
                    setTimeout(() => {
                        rotor.css({ transition: 'none', transform: 'translateY(0px)' });
                    }, 400);
                } else if (isWrappingDown) {
                    rotor.css({ transition: 'none', transform: `translateY(-${10 * digitHeight}px)` });
                    setTimeout(() => {
                        rotor.css({ transition: 'transform 0.4s ease-in-out' });
                        rotor.css({ transform: `translateY(-${9 * digitHeight}px)` });
                    }, 20);
                } else {
                    rotor.css({ transition: 'transform 0.4s ease-in-out' });
                    rotor.css({ transform: `translateY(-${newValue * digitHeight}px)` });
                }

                this.currentValue = newValue;
            }
        }

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
                
                this.wrapper = $('<div class="odometer-display">');
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

                this.isEditing = true;
                this.wrapper.hide();
                const input = $('<input type="number" class="odometer-input" />');
                
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
                        enforceMaxLimit();
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

        // Init Days Odometer
        const initialDays = paghiper_settings.due_date_mode === 'days' ? parseInt(paghiper_settings.due_date_value, 10) : 1;
        daysOdometer = new OdometerDisplay(daysSection.find('.days-display'), initialDays, 'pix-days');
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
            enforceMaxLimit();
            updateValue();
        });


        // Init Minutes Odometer
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
            enforceMaxLimit();
            updateValue();
        });

        modeToggle.trigger('change');

    } else {
        // Boleto: Simple number input
        const daysInput = $('<input type="number" class="input-text regular-input" />')
            .attr('name', valueInput.attr('name'))
            .attr('id', valueInput.attr('id'))
            .val(paghiper_settings.due_date_value);
        
        container.find('#days-mode-section').html(daysInput);
        modeInput.val('days');
    }
});