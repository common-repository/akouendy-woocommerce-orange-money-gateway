<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

// docs
// https://github.com/woocommerce/woocommerce-blocks/blob/trunk/docs/third-party-developers/extensibility/checkout-payment-methods/payment-method-integration.md#server-side-integration
// https://sevengits.com/woocommerce-checkout-blocks/
final class WC_OM_SN_Gateway_Blocks_Support extends AbstractPaymentMethodType {
	
	private $gateway;
	
	protected $name = 'akd-orange-money-sn'; // payment gateway id

	public function initialize() {
		// get payment gateway settings
		$this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
		// you can also initialize your payment gateway here
		// $gateways = WC()->payment_gateways->payment_gateways();
		// $this->gateway  = $gateways[ $this->name ];
	}

	public function is_active() {
		return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
	}

	public function get_payment_method_script_handles() {

		wp_register_script(
			'wc-omsn-blocks-integration',
			plugin_dir_url( __DIR__ ) . 'assets/js/om_sn-gateway-blocks.js',
			array(
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
			),
            time(), // null or time() or filemtime( ... ) to skip caching
			true
		);

		return array( 'wc-omsn-blocks-integration' );

	}

	public function get_payment_method_data() {
		return array(
			'title'        => $this->get_setting( 'title' ),
			// almost the same way:
			// 'title'     => isset( $this->settings[ 'title' ] ) ? $this->settings[ 'title' ] : 'Default value';
			'description'  => $this->get_setting( 'description' ),
			// if $this->gateway was initialized on line 15
			// 'supports'  => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),

			// example of getting a public key
			// 'publicKey' => $this->get_publishable_key(),
			'icon'               => WC_AKOUENDY_PLUGIN_URL . '/assets/images/orange-money.png',
		);
	}

	//private function get_publishable_key() {
	//	$test_mode   = ( ! empty( $this->settings[ 'testmode' ] ) && 'yes' === $this->settings[ 'testmode' ] );
	//	$setting_key = $test_mode ? 'test_publishable_key' : 'publishable_key';
	//	return ! empty( $this->settings[ $setting_key ] ) ? $this->settings[ $setting_key ] : '';
	//}

}


final class WC_WAVE_SN_Gateway_Blocks_Support extends AbstractPaymentMethodType {
	
	private $gateway;
	
	protected $name = 'akd-wave'; // payment gateway id

	public function initialize() {
		// get payment gateway settings
		$this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
		// you can also initialize your payment gateway here
		// $gateways = WC()->payment_gateways->payment_gateways();
		// $this->gateway  = $gateways[ $this->name ];
	}

	public function is_active() {
		return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
	}

	public function get_payment_method_script_handles() {

		wp_register_script(
			'wc-wavesn-blocks-integration',
			plugin_dir_url( __DIR__ ) . 'assets/js/wave_sn-gateway-blocks.js',
			array(
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
			),
            time(), // null or time() or filemtime( ... ) to skip caching
			true
		);

		return array( 'wc-wavesn-blocks-integration' );

	}

	public function get_payment_method_data() {
		return array(
			'title'        => $this->get_setting( 'title' ),
			// almost the same way:
			// 'title'     => isset( $this->settings[ 'title' ] ) ? $this->settings[ 'title' ] : 'Default value';
			'description'  => $this->get_setting( 'description' ),
			// if $this->gateway was initialized on line 15
			// 'supports'  => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),

			// example of getting a public key
			// 'publicKey' => $this->get_publishable_key(),
		);
	}

	//private function get_publishable_key() {
	//	$test_mode   = ( ! empty( $this->settings[ 'testmode' ] ) && 'yes' === $this->settings[ 'testmode' ] );
	//	$setting_key = $test_mode ? 'test_publishable_key' : 'publishable_key';
	//	return ! empty( $this->settings[ $setting_key ] ) ? $this->settings[ $setting_key ] : '';
	//}

}
