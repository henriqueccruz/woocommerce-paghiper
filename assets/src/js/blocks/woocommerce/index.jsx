// WordPress dependencies via window
const { __ } = window.wp.i18n;
const { useState, useEffect } = window.wp.element;
const { decodeEntities } = window.wp.htmlEntities;

import { InlineTaxIdField } from './elements';

/*import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { ValidationInputError } from '@woocommerce/blocks-checkout';
import { getSetting } from '@woocommerce/settings';*/

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { ValidationInputError } 	= window.wc.blocksCheckout;
const { getSetting } 			= window.wc.wcSettings;

// PIX Gateway
const pixSettings 		= getSetting( 'paghiper_pix_data', {} );
const defaultPixLabel 	= __( 'PIX Paghiper', 'woo-boleto-paghiper' )
const label 			= decodeEntities( pixSettings.title ) || defaultPixLabel

const Content = ( props ) => {

	if (typeof wc === 'undefined' || !wc.wcBlocksRegistry) {
		console.error('WooCommerce Blocks registry not found. Make sure WooCommerce Blocks is active and loaded.');
		return null;
	}

	if (typeof wc === 'undefined' || !wc.blocksCheckout) {
		console.error('WooCommerce Blocks Checkout not found. Make sure WooCommerce Blocks is active and loaded.');
		return null;
	}

	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;

	
    const [ taxID, setTaxID ] = useState('');
    const [ errorMessage, setErrorMessage ] = useState('');

	useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {
			// Here we can do any processing we need, and then emit a response.
			// For example, we might validate a custom field, or perform an AJAX request, and then emit a response indicating it is valid or not.

			const paghiperTaxId = taxID;
			const paghiperTaxIdIsValid = paghiperTaxId.length >= 11;
			const paghiperTaxIdFieldName = "_" + props.gatewayName + "_cpf_cnpj";

			if ( paghiperTaxIdIsValid ) {
				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							[ paghiperTaxIdFieldName ]: paghiperTaxId,
						},
					},
				};
			} else {
				console.log(`Paghiper TaxID snatched: ${paghiperTaxId}`);
				console.log(`paghiperTaxIdIsValid returned: ${paghiperTaxIdIsValid}`);
			}

			const errorMsg = __( 'O CPF/CNPJ informado é inválido.', 'woo-boleto-paghiper' );
	        setErrorMessage( errorMsg );     

			return {
				type: emitResponse.responseTypes.ERROR,
				message: errorMsg,
			};
		} );
		// Unsubscribes when this component is unmounted.
		return () => {
			unsubscribe();
		};
	}, [
		taxID,
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		onPaymentSetup,
		props.gatewayName
	] );

	const onChange = ( paymentEvent ) => {
		if ( paymentEvent.error ) {
			console.log('Paghiper: Payment Error');
		}

		setErrorMessage('');
		const newValue = paymentEvent.target ? paymentEvent.target.value : paymentEvent;
		setTaxID(newValue.replace(/\D/g, ''));

		//setTaxID(paymentEvent.target.value.replace(/\D/g, ''));
	}

	return (
		<>
			{decodeEntities(props.gatewayDescription || '')}
			<InlineTaxIdField 
				gatewayName={ props.gatewayName }
				value={ taxID }
				onChange={ onChange }
				errorMessage={ errorMessage }
				inputErrorComponent={ ValidationInputError }
			/>
		</>
	);
}

const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components

	return <PaymentMethodLabel text={ label } />
}

// Define payment methods
const PaghiperPix = {
	name: "paghiper_pix",
	label: <Label />,
	content: <Content gatewayName="paghiper_pix" gatewayDescription={ pixSettings.description } />,
	edit: <Content gatewayName="paghiper_pix" gatewayDescription={ pixSettings.description } />,
	canMakePayment: () => true,
	ariaLabel: label,
	paymentMethodId: "paghiper_pix",
	supports: {
		features: pixSettings.supports,
	}
};

// Billet
const billetSettings 		= getSetting( 'paghiper_billet_data', {} )
const defaultBilletLabel 	= __( 'Boleto Paghiper', 'woo-boleto-paghiper' )
const billetLabel 			= decodeEntities( billetSettings.title ) || defaultBilletLabel

const BilletLabel = ( props ) => {
	const { PaymentMethodLabel } = props.components
	return <PaymentMethodLabel text={ billetLabel } />
}

const PaghiperBillet = {
	name: "paghiper_billet",
	label: <BilletLabel />,
	content: <Content gatewayName="paghiper_billet" gatewayDescription={ billetSettings.description } />,
	edit: <Content gatewayName="paghiper_billet" gatewayDescription={ billetSettings.description } />,
	canMakePayment: () => true,
	ariaLabel: billetLabel,
	supports: {
		features: billetSettings.supports,
	}
};

// Register payment methods
if (typeof window.wc.wcBlocksRegistry !== 'undefined') {
    registerPaymentMethod(PaghiperPix);
    registerPaymentMethod(PaghiperBillet);
}