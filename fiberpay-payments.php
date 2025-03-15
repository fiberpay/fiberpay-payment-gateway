<?php
/*
 * Plugin Name: Fiberpay Payment Gateway
 * Plugin URI: https://github.com/fiberpay/fiberpay-payment-gateway
 * Description: Take instant payments on your WooCommerce store using Fiberpay payment gateway.
 * Author: Fiberpay Sp. z o.o.
 * Author URI: https://fiberpay.pl
 * Text Domain: fiberpay-payments
 * Domain Path: /languages
 * Version: 0.1.2
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
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
    wc_get_logger()->debug('Fiberpay: before_woocommerce_init action fired', array('source' => 'fiberpay-payments'));
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        wc_get_logger()->debug('Fiberpay: FeaturesUtil class exists, declaring compatibility', array('source' => 'fiberpay-payments'));
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        wc_get_logger()->debug('Fiberpay: Compatibility declared for custom_order_tables and cart_checkout_blocks', array('source' => 'fiberpay-payments'));
    } else {
        wc_get_logger()->warning('Fiberpay: FeaturesUtil class does not exist', array('source' => 'fiberpay-payments'));
    }
});

// Register Blocks support
add_action('woocommerce_blocks_loaded', function() {
    wc_get_logger()->debug('Fiberpay: woocommerce_blocks_loaded action fired', array('source' => 'fiberpay-payments'));
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        wc_get_logger()->warning('Fiberpay: AbstractPaymentMethodType class does not exist', array('source' => 'fiberpay-payments'));
        return;
    }

    wc_get_logger()->debug('Fiberpay: AbstractPaymentMethodType class exists, loading class-fiberpay-blocks.php', array('source' => 'fiberpay-payments'));
    require_once dirname(__FILE__) . '/includes/blocks/class-fiberpay-blocks.php';
    wc_get_logger()->debug('Fiberpay: Adding woocommerce_blocks_payment_method_type_registration action', array('source' => 'fiberpay-payments'));
    
    // Register the payment method with WooCommerce Blocks
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            wc_get_logger()->debug('Fiberpay: woocommerce_blocks_payment_method_type_registration action fired', array('source' => 'fiberpay-payments'));
            wc_get_logger()->debug('Fiberpay: Registering Fiberpay_Blocks_Support with payment_method_registry', array('source' => 'fiberpay-payments'));
            $payment_method_registry->register(new Fiberpay_Blocks_Support());
            wc_get_logger()->debug('Fiberpay: Fiberpay_Blocks_Support registered successfully', array('source' => 'fiberpay-payments'));
        }
    );

    // Register and enqueue our block scripts
    add_action('init', function() {
        wc_get_logger()->debug('Fiberpay: Registering block scripts', array('source' => 'fiberpay-payments'));
        
        if (!function_exists('register_block_type')) {
            wc_get_logger()->warning('Fiberpay: Block editor not available', array('source' => 'fiberpay-payments'));
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
            
            wc_get_logger()->debug('Fiberpay: Localizing payment data: ' . wp_json_encode($payment_data), array('source' => 'fiberpay-payments'));
            
            wp_localize_script(
                'fiberpay-blocks',
                'fiberpay_payments_data',
                $payment_data
            );
        }

        wc_get_logger()->debug('Fiberpay: Block scripts registered successfully', array('source' => 'fiberpay-payments'));
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
    wc_get_logger()->debug('Fiberpay: fiberpay_init_gateway_class called', array('source' => 'fiberpay-payments'));
    
    load_plugin_textdomain( 'fiberpay-payments', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

    if ( is_admin() ) {
        require_once dirname( __FILE__ ) . '/includes/class-wc-fiberpay-admin-notices.php';
    }

    if(class_exists('WC_Payment_Gateway')) {
        wc_get_logger()->debug('Fiberpay: WC_Payment_Gateway class exists', array('source' => 'fiberpay-payments'));
        static $plugin;

        if ( ! isset( $plugin ) ) {
            wc_get_logger()->debug('Fiberpay: Loading gateway class', array('source' => 'fiberpay-payments'));
            include_once plugin_dir_path(__FILE__) . '/includes/class-wc-gateway-fiberpay.php';
            wc_get_logger()->debug('Fiberpay: Gateway class loaded', array('source' => 'fiberpay-payments'));
        } else {
            wc_get_logger()->debug('Fiberpay: Plugin already set', array('source' => 'fiberpay-payments'));
        }

        return $plugin;
    } else {
        wc_get_logger()->warning('Fiberpay: WC_Payment_Gateway class does not exist', array('source' => 'fiberpay-payments'));
    }
}

// Make sure the gateway class is loaded before blocks attempt to use it
add_action('init', function() {
    wc_get_logger()->debug('Fiberpay: init action - ensuring gateway class is loaded', array('source' => 'fiberpay-payments'));
    fiberpay_init_gateway_class();
    wc_get_logger()->debug('Fiberpay: Gateway initialization complete', array('source' => 'fiberpay-payments'));
}, 5);
