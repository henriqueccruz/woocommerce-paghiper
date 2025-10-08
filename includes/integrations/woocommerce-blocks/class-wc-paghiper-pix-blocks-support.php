<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class  WC_Paghiper_Pix_Gateway_Blocks_Support extends AbstractPaymentMethodType {
	
	private $gateway;

	protected $name = 'paghiper_pix';

	public function initialize() {
		$this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	public function is_active() {
		//return $this->gateway->is_available();
		return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
	}

	public function get_payment_method_script_handles() {

		$script_path       = wc_paghiper_assets_url('/js/blocks.min.js');
		$script_asset_path = 'blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => [
					'react',
					'wc-blocks-registry',
					'wc-settings',
					'wp-element',
					'wp-html-entities',
				],
				'version'      => '1.2.0'
			);
		$script_url        = $script_path;

		wp_register_script(
			'wc-paghiper-pix-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		return [ 'wc-paghiper-pix-blocks' ];

	}

	public function get_payment_method_data() {

		return [
			'title'        	=> $this->get_setting( 'title' ),
			'description'  	=> $this->get_setting( 'description' ),
			'supports'    	=> array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		];
	}

}