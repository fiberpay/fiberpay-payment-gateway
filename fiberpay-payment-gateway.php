<?php
/*
 * Plugin Name: Fiberpay Payment Gateway
 * Plugin URI: https://github.com/fiberpay/fiberpay-payment-gateway
 * Description: Take instant payments on your WooCommerce store using Fiberpay payment gateway.
 * Author: Fiberpay Sp. z o.o.
 * Author URI: https://fiberpay.pl
 * Text Domain: fiberpay-payment-gateway
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

function fiberpay_log_debug($message, $context = []) {
    if (!defined('WP_DEBUG') || !WP_DEBUG || !function_exists('wc_get_logger')) {
        return;
    }

    $logger = wc_get_logger();
    $message = 'Fiberpay: ' . $message;
    $context['source'] = 'fiberpay-payment-gateway';
    $context['debug'] = WP_DEBUG;
    $logger->debug($message, $context);
}

// Declare WooCommerce Blocks compatibility
add_action('before_woocommerce_init', function() {
    fiberpay_log_debug('before_woocommerce_init action fired');
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        fiberpay_log_debug('FeaturesUtil class exists, declaring compatibility');
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        fiberpay_log_debug('Compatibility declared for custom_order_tables and cart_checkout_blocks');
    } else {
        fiberpay_log_debug('FeaturesUtil class does not exist');
    }
});

// Register Blocks support
add_action('woocommerce_blocks_loaded', function() {
    fiberpay_log_debug('woocommerce_blocks_loaded action fired', array('source' => 'fiberpay-payment-gateway'));
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        fiberpay_log_debug('AbstractPaymentMethodType class does not exist', array('source' => 'fiberpay-payment-gateway'), 'warning');
        return;
    }

    fiberpay_log_debug('AbstractPaymentMethodType class exists, loading class-fiberpay-blocks.php', array('source' => 'fiberpay-payment-gateway'));
    require_once dirname(__FILE__) . '/includes/blocks/class-fiberpay-blocks.php';
    fiberpay_log_debug('Adding woocommerce_blocks_payment_method_type_registration action', array('source' => 'fiberpay-payment-gateway'));
    
    // Register the payment method with WooCommerce Blocks
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            fiberpay_log_debug('woocommerce_blocks_payment_method_type_registration action fired', array('source' => 'fiberpay-payment-gateway'));
            fiberpay_log_debug('Registering Fiberpay_Blocks_Support with payment_method_registry', array('source' => 'fiberpay-payment-gateway'));
            $payment_method_registry->register(new Fiberpay_Blocks_Support());
            fiberpay_log_debug('Fiberpay_Blocks_Support registered successfully', array('source' => 'fiberpay-payment-gateway'));
        }
    );

    // Register and enqueue our block scripts
    add_action('init', function() {
        fiberpay_log_debug('Registering block scripts', array('source' => 'fiberpay-payment-gateway'));
        
        if (!function_exists('register_block_type')) {
            fiberpay_log_debug('Block editor not available', array('source' => 'fiberpay-payment-gateway'), 'warning');
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
            
            fiberpay_log_debug('Localizing payment data: ' . wp_json_encode($payment_data), array('source' => 'fiberpay-payment-gateway'));
            
            wp_localize_script(
                'fiberpay-blocks',
                'fiberpay_payments_data',
                $payment_data
            );
        }

        fiberpay_log_debug('Block scripts registered successfully', array('source' => 'fiberpay-payment-gateway'));
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
    fiberpay_log_debug('fiberpay_init_gateway_class called', array('source' => 'fiberpay-payment-gateway'));
    
    load_plugin_textdomain( 'fiberpay-payment-gateway', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

    if ( is_admin() ) {
        require_once dirname( __FILE__ ) . '/includes/class-wc-fiberpay-admin-notices.php';
    }

    if(class_exists('WC_Payment_Gateway')) {
        fiberpay_log_debug('WC_Payment_Gateway class exists', array('source' => 'fiberpay-payment-gateway'));
        static $plugin;

        if ( ! isset( $plugin ) ) {
            fiberpay_log_debug('Loading gateway class', array('source' => 'fiberpay-payment-gateway'));
            include_once plugin_dir_path(__FILE__) . '/includes/class-wc-gateway-fiberpay.php';
            fiberpay_log_debug('Gateway class loaded', array('source' => 'fiberpay-payment-gateway'));
        } else {
            fiberpay_log_debug('Plugin already set', array('source' => 'fiberpay-payment-gateway'));
        }

        return $plugin;
    } else {
        fiberpay_log_debug('WC_Payment_Gateway class does not exist', array('source' => 'fiberpay-payment-gateway'), 'warning');
    }
}

// Make sure the gateway class is loaded before blocks attempt to use it
add_action('init', function() {
    fiberpay_log_debug('init action - ensuring gateway class is loaded', array('source' => 'fiberpay-payment-gateway'));
    fiberpay_init_gateway_class();
    fiberpay_log_debug('Gateway initialization complete', array('source' => 'fiberpay-payment-gateway'));
}, 5);
