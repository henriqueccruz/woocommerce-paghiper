/*import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';*/
const { __ } = window.wp.i18n;
const { useState, useEffect, useRef } = window.wp.element;

import { useElementOptions } from './use-element-options';
import { isCPF, isCNPJ } from 'validation-br';

const baseTextInputStyles = 'wc-block-gateway-input paghiper_tax_id p-Input-input Input p-Input-input--textRight';

/**
 * InlineTaxIdField component
 *
 * @param {Object} props Incoming props for the component.
 * @param {React.ReactElement} props.inputErrorComponent
 * @param {function(any):any} props.onChange
 */
export const InlineTaxIdField = ( {
	inputErrorComponent: ValidationInputError,
	onChange,
    gatewayName,
    value,
    errorMessage
} ) => {
	const [ isEmpty, setIsEmpty ] = useState( true );
	const [ isInvalid, setIsInvalid ] = useState( false );
    const [ isComplete, setIsComplete ] = useState( false );
	const [ fieldLabel, setFieldLabel ] = useState(__('CPF do Pagador', 'woo-boleto-paghiper'));
    const [ fieldInput, setFieldInput ] = useState( value || '' );
    const inputRef = useRef(null);

	const { options, isActive, isFocus, onActive, error, setError } = useElementOptions( {
		hideIcon: true,
	} );

    // Helper to format value manually (syncs React state with Mask expectation)
    const formatTaxId = (val) => {
        if (!val) return '';
        const clean = val.toString().replace(/\D/g, '');
        if (clean.length > 11) {
            // CNPJ Mask: 00.000.000/0000-00
            return clean.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, '$1.$2.$3/$4-$5');
        } else {
            // CPF Mask: 000.000.000-00
            return clean.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
        }
    }
	
    // Sync internal state with prop value (handling mask formatting)
    useEffect(() => {
        if ( value !== undefined ) {
            const cleanPropValue = value.toString().replace(/\D/g, '');
            const cleanInternalValue = fieldInput.toString().replace(/\D/g, '');

            // Only update if the numbers are actually different
            if ( cleanPropValue !== cleanInternalValue ) {
                 setFieldInput( formatTaxId(cleanPropValue) );
            }
        }
    }, [ value ]);

    // Keep isEmpty in sync with fieldInput reactively
    useEffect(() => {
        setIsEmpty( !fieldInput );
    }, [ fieldInput ]);

    // Handle external error messages
    useEffect(() => {
        if ( errorMessage ) {
            setError( errorMessage );
            setIsInvalid( true );
        }
    }, [ errorMessage ]);

    const handleChange = ( event ) => {
        const newValue = event.target.value;
        setFieldInput( newValue );
        setError( '' ); // Reset error state on change
        onChange( event );
    }

    // Handle Autocomplete/Paste cleanup on Blur
    const handleBlur = () => {
        onActive( !fieldInput, false );
        
        if ( inputRef.current ) {
            const currentVal = inputRef.current.value;
            if ( currentVal !== fieldInput ) {
                const formatted = formatTaxId(currentVal);
                setFieldInput(formatted);
                onChange({ target: { value: formatted } });
            }
        }
    }

    useEffect(() => {
        const cleanInput = fieldInput.replace(/\D/g, '');

        // Reset states before validation
        setIsInvalid(false);
        setIsComplete(false);
        
        if(cleanInput.length > 11) {
            setFieldLabel(__('CNPJ do Pagador', 'woo-boleto-paghiper'));
        } else {
            setFieldLabel(__('CPF do Pagador', 'woo-boleto-paghiper'));
        }

        if(!isEmpty && cleanInput.length > 0) {
            
            // Common validation logic for both focus and blur (checks completeness and validity)
            if(cleanInput.length === 11) {
                // Valida CPF
                if(!isCPF(cleanInput)) {
                    setError(__('O número do seu CPF não está correto.', 'woo-boleto-paghiper'));
                    setIsInvalid(true);
                } else {
                    setIsComplete(true);
                    setError(''); // Clear error if valid
                }
            } else if(cleanInput.length === 14) {
                // Valida CNPJ
                if(!isCNPJ(cleanInput)) {
                    setError(__('O número do seu CNPJ não está correto.', 'woo-boleto-paghiper'));
                    setIsInvalid(true);
                } else {
                    setIsComplete(true);
                    setError(''); // Clear error if valid
                }
            } else {
                // Incomplete length logic...
                 if(!isFocus) {
                    // Only show "incomplete" error on blur
                    if(cleanInput.length > 11 && cleanInput.length < 14) {
                        setError(__('O número do seu CNPJ está incompleto.', 'woo-boleto-paghiper'));
                        setIsInvalid(true);
                    } else if (cleanInput.length < 11) {
                        setError(__('O número do seu CPF está incompleto.', 'woo-boleto-paghiper'));
                        setIsInvalid(true);
                    }
                }
            }

        } else {
            setIsInvalid(false);
            setIsComplete(false);
        }


    }, [fieldInput, isFocus, isEmpty]);

    const taxIdMaskBehavior = (val) => {
        return val.replace(/\D/g, '').length > 11 ? '00.000.000/0000-00' : '000.000.000-009';
    }

    // Initialize mask using ref
    useEffect(() => {
        if(inputRef.current && typeof jQuery(inputRef.current).mask === "function") {
            const $input = jQuery(inputRef.current);
            
            $input.mask(taxIdMaskBehavior, {
                onKeyPress: function(val, e, field, options) {
                    field.mask(taxIdMaskBehavior.apply({}, arguments), options);
                }
            });
        } else {
            // Fallback logging or global init if ref fails (unlikely)
             console.log('Paghiper block: initializing mask via ref');
        }
    }, [])

	return (
		<>
            <div className="wc-block-components-form">
                <div className={"wc-block-gateway-container wc-block-components-text-input wc-inline-tax-id-element paghiper-taxid-fieldset" + (isActive || !isEmpty ? ' is-active' : '') + (isInvalid ? ' has-error invalid' : '')}>
                    <input 
                        ref={inputRef}
                        type="text"
                        id={`wc-paghiper-inline-tax-id-element-${gatewayName}`}
                        name={"_" + gatewayName + "_cpf_cnpj"}
                        className={ baseTextInputStyles + (isEmpty ? ' empty Input--empty' : '') + (isInvalid ? ' has-error invalid' : '') + (isComplete ? ' valid' : '')}
                        onBlur={ handleBlur }
                        onFocus={ () => onActive( isEmpty, true ) }
                        onChange={ handleChange }
                        aria-label={ fieldLabel }
                        value={ fieldInput }
                        required
                        title
                    />
                    <label htmlFor={`wc-paghiper-inline-tax-id-element-${gatewayName}`}>{ fieldLabel }</label>
                    <ValidationInputError errorMessage={ error } />
                </div>
            </div>
		</>
	);
};