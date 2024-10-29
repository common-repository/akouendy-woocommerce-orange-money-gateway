<?php

/*
  Plugin Name: WooCommerce Akouendy  Gateway
  Description: Easily integrate Mobile Money payments into your WooCommerce site and start accepting payments from Senegal.
  Version: 4.0.0
  Author: AKOUENDY
  Requires at least: 5.7
  Tested up to: 6.6
  WC requires at least: 3.0.0
  WC tested up to: 9.1
  Author URI: https://www.akouendy.com
 */

if (!defined('ABSPATH')) {
    exit;
}

define( 'LOCAL',  false);
// REMOVE FIELDS FOR TEST
if (LOCAL) {
    require_once dirname( __FILE__ ) . '/dev-utilities.php';
    define( 'WC_AKOUENDY_PAY_SANDBOX_BASE_URL', 'https://akdbilling-locallabs.akouendy.com');
} else {
    define( 'WC_AKOUENDY_PAY_SANDBOX_BASE_URL', 'https://akdbilling-locallabs.akouendy.com');
}

define( 'WC_AKOUENDY_PAY_BASE_URL', 'https://pay.akouendy.com' );
define( 'WC_AKOUENDY_PAY_POST_INIT','/v1/billing/payment/init' );
define( 'WC_AKOUENDY_PAY_GET_STATUS','/v1/payment/{paymenId}/{applicationId}' );
define( 'WC_AKOUENDY_PAY_POST_ONESTEP_PAY','/v1/billing/{provider}/{token}' );
define( 'WC_AKOUENDY_PAY_REDIRECT','/v1/billing/{provider}/{token}' );

define( 'WC_AKOUENDY_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_AKOUENDY_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );



if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}
// Register plugin init function
add_action('plugins_loaded', 'woocommerce_akouendy_init', 0);

// Register css and js
add_action('wp_enqueue_scripts', "woocommerce_akouendy_assets");

//Declare the compatibility with WooCommerce plugin HPOS

add_action('before_woocommerce_init', function(){
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 
            'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks', __FILE__, true);
    }

}); 

add_action( 'woocommerce_blocks_loaded', 'akouendy_gateway_block_support' );


function woocommerce_akouendy_assets() {
    //     wp_register_script( 'custom_jquery', plugins_url('/js/custom-jquery.js', __FILE__), array('jquery'), '2.5.1' );
   // wp_enqueue_script( 'akouendy_js', WC_AKOUENDY_PLUGIN_URL . '/assets/js/akouendy-pay-widget-v1.0.0.js');
    wp_register_style( 'akouendy_link_styles', plugins_url( '/assets/css/akouendy-style.css'));
}

function woocommerce_akouendy_init() {
    if (!class_exists('WC_Payment_Gateway'))
        return;

    // Import Akouendy Payment provider classes
    require_once dirname( __FILE__ ) . '/payment-methods/class-wc-gateway-orange-money-senegal-redirect.php';
    require_once dirname( __FILE__ ) . '/payment-methods/class-wc-gateway-wave-senegal.php';
    add_filter( 'woocommerce_payment_gateways', 'akouendy_gateway_class' );
    add_filter('woocommerce_currencies', 'akouendy_fcfa_currency');
    add_filter('woocommerce_currency_symbol', 'akouendy_fcfa_currency_symbol', 10, 2);
    $plugin = plugin_basename(__FILE__);
    add_filter("plugin_action_links_$plugin", 'woocommerce_add_akouendy_settings_link');
    add_filter('woocommerce_available_payment_gateways', 'akouendy_enable_all_payment_methods');


}

    // Add settings link on plugin page
    function woocommerce_add_akouendy_settings_link($links) {
        $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout">R&egrave;glages</a>';
        array_unshift($links, $settings_link);
        return $links;
    }


// Add Gateway classes
function akouendy_gateway_class( $methods ) {
    $methods[] = 'WC_Akouendy_OrangeMoneySnRedirect'; 
    $methods[] = 'WC_Akouendy_Wave';
    return $methods;
}
function akouendy_gateway_block_support() {

	// if( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
	// 	return;
	// }

	// here we're including our "gateway block support class"
	require_once __DIR__ . '/payment-methods/class-blocks-support.php';

	// registering the PHP class we have just included
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
			$payment_method_registry->register( new WC_OM_SN_Gateway_Blocks_Support );
			$payment_method_registry->register( new WC_WAVE_SN_Gateway_Blocks_Support );
		}
	);
}


function akouendy_fcfa_currency($currencies) {
    $currencies['XOF'] = __('BCEAO XOF.', 'woocommerce');
    return $currencies;
}

function akouendy_fcfa_currency_symbol($currency_symbol, $currency) {
    switch ($currency) {
        case 'XOF': 
            $currency_symbol = 'FCFA';
            break;
    }
    return $currency_symbol;
}

function callback_handler() {
    global $woocommerce;
    $id = explode("_",sanitize_text_field($_POST["REF_CMD"]));
    $wc_order_id = $id[0];
    $order = new WC_Order($wc_order_id);

    

    // checking hmac
    $statut = sanitize_text_field($_POST["STATUT"]);
    $str = $this->site_token."|".sanitize_text_field($_POST["REF_CMD"])."|". $statut;
    $hash = hash('sha512', $str);
    if ($hash === sanitize_text_field($_POST["HASH"])) {
        switch ($statut) {
            case 117:
                //transaction failed
                $message = "Transaction failed";
                $order_status = 'failed';
                break;
            case 200:
                // transaction success
                $order_status = 'completed';
                $woocommerce->cart->empty_cart();
                $message = "Transaction success";
                break;
            case 220:
                // transaction not found
                $message = "Transaction not found";
                $order_status = "pending";
                break;
            case 375:
                //OTP expires or is already used or invalid
                $message = "OTP expires or is already used or invalid";
                $order_status = 'failed';
                break;
            
        }
        if(!empty($order_status)) {
            $order->add_order_note($message);
            $order->update_status($order_status);
            
        }
        
    } 

}

// Ensure all payment methods are available
function akouendy_enable_all_payment_methods($available_gateways) {
    return $available_gateways;
}

