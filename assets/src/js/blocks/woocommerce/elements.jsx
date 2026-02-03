/*import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';*/
const { __ } = window.wp.i18n;
const { useState, useEffect } = window.wp.element;

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
	const { options, isActive, isFocus, onActive, error, setError } = useElementOptions( {
		hideIcon: true,
	} );
	
    // Sync internal state with prop value
    useEffect(() => {
        if ( value !== undefined && value !== fieldInput ) {
            setFieldInput( value );
        }
    }, [ value ]);

    // Handle external error messages
    useEffect(() => {
        if ( errorMessage ) {
            setError( errorMessage );
            setIsInvalid( true );
        }
    }, [ errorMessage ]);

    const errorCallback = ( event ) => {
		if ( event.error ) {
			setError( event.error.message );
		} else {
			setError( '' );
		}
		setIsEmpty( event.empty );
		onChange( event );

        if( !event.target.value ) {
            setIsEmpty( true );
        }
    }

    useEffect(() => {
        const cleanInput = fieldInput.replace(/\D/g, '');

        // Reset states before validation
        setIsInvalid(false);
        setIsComplete(false);
        setError('');

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
                }
            } else if(cleanInput.length === 14) {
                // Valida CNPJ
                if(!isCNPJ(cleanInput)) {
                    setError(__('O número do seu CNPJ não está correto.', 'woo-boleto-paghiper'));
                    setIsInvalid(true);
                } else {
                    setIsComplete(true);
                }
            } else {
                // Incomplete length
                 if(!isFocus) {
                    // Only show "incomplete" error on blur to avoid annoying user while typing
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

    const taxIdMaskBehavior = (val, e) => {
        return val.replace(/\D/g, '').length > 11 ? '00.000.000/0000-00' : '000.000.000-009';
    }

    // Initialize mask everytime we render the component
    useEffect(() => {

        if(typeof jQuery('.paghiper_tax_id').mask === "function") {

            jQuery('.paghiper_tax_id').mask(taxIdMaskBehavior, {
                onKeyPress: function(val, e, field, options) {
                    field.mask(taxIdMaskBehavior.apply({}, arguments), options);
                }
            });

        } else {
            console.log('Paghiper block failed to initialize TaxID mask')
        }
	
    }, [])

	return (
		<>
            <div className="wc-block-components-form">
                <div className={"wc-block-gateway-container wc-block-components-text-input wc-inline-tax-id-element paghiper-taxid-fieldset" + (isActive || !isEmpty ? ' is-active' : '')}>
                    <input 
                        type="text"
                        id="wc-paghiper-inline-tax-id-element"
                        name={"_" + gatewayName + "_cpf_cnpj"}
                        className={ baseTextInputStyles + (isEmpty ? ' empty Input--empty' : '') + (isInvalid ? ' invalid' : '') + (isComplete ? ' valid' : '')}
                        onBlur={ () => onActive( isEmpty, false ) }
                        onFocus={ () => onActive( isEmpty, true ) }
                        onChange={ errorCallback }
                        onInput={ e => setFieldInput(e.target.value) }
                        aria-label={ fieldLabel }
                        value={ fieldInput }
                        required
                        title
                    />
                    <label htmlFor="wc-paghiper-inline-tax-id-element">{ fieldLabel }</label>
                    <ValidationInputError errorMessage={ error } />
                </div>
            </div>
		</>
	);
};