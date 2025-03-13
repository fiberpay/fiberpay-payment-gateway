<?php
/*
 * Plugin Name: Fiberpay Payment Gateway
 * Plugin URI: https://github.com/fiberpay/fiberpay-payment-gateway
 * Description: Take instant payments on your store.
 * Author: Fiberpay Sp. z o.o.
 * Author URI: https://fiberpay.pl
 * Text Domain: fiberpay-payments
 * Domain Path: /languages
 * Version: 0.1.0
 */

if (!defined( 'ABSPATH')) {
	exit; // Exit if accessed directly.
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	return;
}

$loader = require_once dirname( __FILE__ ) . '/vendor/autoload.php';

if ( ! $loader ) {
	throw new Exception( 'vendor/autoload.php missing please run `composer install`' );
}


// Declare WooCommerce Blocks compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Register Blocks support
add_action('woocommerce_blocks_loaded', function() {
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once dirname(__FILE__) . '/includes/blocks/class-fiberpay-blocks.php';
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new Fiberpay_Blocks_Support());
        }
    );
});

function enqueue_payment_gateway_script() {
    // Ensure the WooCommerce Blocks script is loaded before this one
    wp_register_script(
        'your-payment-gateway-blocks',
        plugins_url('assets/js/your-payment-gateway-blocks.js', __FILE__),
        array('wc-blocks-registry'), // This ensures wc-blocks-registry is loaded first
        '1.0.0',
        true
    );
    wp_enqueue_script('your-payment-gateway-blocks');
}

add_action('wp_enqueue_scripts', 'enqueue_payment_gateway_script');

function fiberpay_add_gateway_class($gateways) {
	$gateways[] = 'Fiberpay_WC_Payment_Gateway';
	return $gateways;
}
add_filter('woocommerce_payment_gateways', 'fiberpay_add_gateway_class');

add_action('plugins_loaded', 'fiberpay_init_gateway_class', 11);

register_deactivation_hook( __FILE__, 'delete_order_data_transients' );

function delete_order_data_transients() {
    delete_transient( 'wc_fiberpay_order_data_prod' );
    delete_transient( 'wc_fiberpay_order_data_test' );
}

function fiberpay_init_gateway_class() {
	load_plugin_textdomain( 'fiberpay-payments', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( is_admin() ) {
		require_once dirname( __FILE__ ) . '/includes/class-wc-fiberpay-admin-notices.php';
	}

	if(class_exists('WC_Payment_Gateway')) {
		static $plugin;

		if ( ! isset( $plugin ) ) {
			include_once plugin_dir_path(__FILE__) . '/includes/class-wc-gateway-fiberpay.php';
		}

		return $plugin;
	}
}
