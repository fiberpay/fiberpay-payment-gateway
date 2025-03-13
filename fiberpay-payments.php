<?php
/*
 * Plugin Name: Fiberpay Payment Gateway
 * Plugin URI: https://github.com/fiberpay/fiberpay-payment-gateway
 * Description: Take instant payments on your store.
 * Author: Fiberpay Sp. z o.o.
 * Author URI: https://fiberpay.pl
 * Text Domain: fiberpay-payments
 * Domain Path: /languages
 * Version: 0.1.1
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
    error_log('Fiberpay: before_woocommerce_init action fired');
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        error_log('Fiberpay: FeaturesUtil class exists, declaring compatibility');
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        error_log('Fiberpay: Compatibility declared for custom_order_tables and cart_checkout_blocks');
    } else {
        error_log('Fiberpay: FeaturesUtil class does not exist');
    }
});

// Register Blocks support
add_action('woocommerce_blocks_loaded', function() {
    error_log('Fiberpay: woocommerce_blocks_loaded action fired');
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        error_log('Fiberpay: AbstractPaymentMethodType class does not exist');
        return;
    }

    error_log('Fiberpay: AbstractPaymentMethodType class exists, loading class-fiberpay-blocks.php');
    require_once dirname(__FILE__) . '/includes/blocks/class-fiberpay-blocks.php';
    error_log('Fiberpay: Adding woocommerce_blocks_payment_method_type_registration action');
    
    // Register the payment method with WooCommerce Blocks
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            error_log('Fiberpay: woocommerce_blocks_payment_method_type_registration action fired');
            error_log('Fiberpay: Registering Fiberpay_Blocks_Support with payment_method_registry');
            $payment_method_registry->register(new Fiberpay_Blocks_Support());
            error_log('Fiberpay: Fiberpay_Blocks_Support registered successfully');
        }
    );

    // Register and enqueue our block scripts
    add_action('init', function() {
        error_log('Fiberpay: Registering block scripts');
        
        if (!function_exists('register_block_type')) {
            error_log('Fiberpay: Block editor not available');
            return;
        }

        wp_register_script(
            'fiberpay-blocks',
            plugins_url('assets/js/blocks.js', __FILE__),
            ['wp-element', 'wp-components', 'wp-blocks', 'wp-block-editor', 'wc-blocks-registry', 'wc-settings'],
            '1.0.0',
            true
        );

        // Get payment gateway instance to access settings
        if (class_exists('Fiberpay_WC_Payment_Gateway')) {
            $gateway = new Fiberpay_WC_Payment_Gateway();
            $payment_data = [
                'title' => $gateway->get_title(),
                'description' => $gateway->get_description(),
                'enabled' => $gateway->enabled === 'yes',
                'is_test_env' => $gateway->is_test_env === 'yes',
                'gateway_id' => $gateway->id,
            ];
            
            error_log('Fiberpay: Localizing payment data: ' . wp_json_encode($payment_data));
            
            wp_localize_script(
                'fiberpay-blocks',
                'fiberpay_payments_data',
                $payment_data
            );
        }

        error_log('Fiberpay: Block scripts registered successfully');
    }, 5);
});

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
    error_log('Fiberpay: fiberpay_init_gateway_class called');
    
    load_plugin_textdomain( 'fiberpay-payments', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

    if ( is_admin() ) {
        require_once dirname( __FILE__ ) . '/includes/class-wc-fiberpay-admin-notices.php';
    }

    if(class_exists('WC_Payment_Gateway')) {
        error_log('Fiberpay: WC_Payment_Gateway class exists');
        static $plugin;

        if ( ! isset( $plugin ) ) {
            error_log('Fiberpay: Loading gateway class');
            include_once plugin_dir_path(__FILE__) . '/includes/class-wc-gateway-fiberpay.php';
            error_log('Fiberpay: Gateway class loaded');
        } else {
            error_log('Fiberpay: Plugin already set');
        }

        return $plugin;
    } else {
        error_log('Fiberpay: WC_Payment_Gateway class does not exist');
    }
}

// Make sure the gateway class is loaded before blocks attempt to use it
add_action('init', function() {
    error_log('Fiberpay: init action - ensuring gateway class is loaded');
    fiberpay_init_gateway_class();
    error_log('Fiberpay: Gateway initialization complete');
}, 5);
