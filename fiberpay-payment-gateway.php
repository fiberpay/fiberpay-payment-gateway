<?php
/*
 * Plugin Name: Fiberpay Payment Gateway
 * Plugin URI: https://github.com/fiberpay/fiberpay-payment-gateway
 * Description: Take instant payments on your WooCommerce store using Fiberpay payment gateway.
 * Author: Fiberpay Sp. z o.o.
 * Author URI: https://fiberpay.pl
 * Text Domain: fiberpay-payment-gateway
 * Domain Path: /languages
 * Version: 0.1.4
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

function fiberpaygw_log_debug($message, $context = []) {
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
    fiberpaygw_log_debug('before_woocommerce_init action fired');
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        fiberpaygw_log_debug('FeaturesUtil class exists, declaring compatibility');
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        fiberpaygw_log_debug('Compatibility declared for custom_order_tables and cart_checkout_blocks');
    } else {
        fiberpaygw_log_debug('FeaturesUtil class does not exist');
    }
});

// Register Blocks support
add_action('woocommerce_blocks_loaded', function() {
    fiberpaygw_log_debug('woocommerce_blocks_loaded action fired', array('source' => 'fiberpay-payment-gateway'));
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        fiberpaygw_log_debug('AbstractPaymentMethodType class does not exist', array('source' => 'fiberpay-payment-gateway'), 'warning');
        return;
    }

    fiberpaygw_log_debug('AbstractPaymentMethodType class exists, loading class-fiberpay-blocks.php', array('source' => 'fiberpay-payment-gateway'));
    require_once dirname(__FILE__) . '/includes/blocks/class-fiberpay-blocks.php';
    fiberpaygw_log_debug('Adding woocommerce_blocks_payment_method_type_registration action', array('source' => 'fiberpay-payment-gateway'));
    
    // Register the payment method with WooCommerce Blocks
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            fiberpaygw_log_debug('woocommerce_blocks_payment_method_type_registration action fired', array('source' => 'fiberpay-payment-gateway'));
            fiberpaygw_log_debug('Registering Fiberpay_Blocks_Support with payment_method_registry', array('source' => 'fiberpay-payment-gateway'));
            $payment_method_registry->register(new Fiberpay_Blocks_Support());
            fiberpaygw_log_debug('Fiberpay_Blocks_Support registered successfully', array('source' => 'fiberpay-payment-gateway'));
        }
    );

    // Register and enqueue our block scripts
    add_action('init', function() {
        fiberpaygw_log_debug('Registering block scripts', array('source' => 'fiberpay-payment-gateway'));
        
        if (!function_exists('register_block_type')) {
            fiberpaygw_log_debug('Block editor not available', array('source' => 'fiberpay-payment-gateway'), 'warning');
            return;
        }

        wp_register_script(
            'fiberpay-blocks',
            plugins_url('assets/js/blocks.js', __FILE__),
            ['wp-element', 'wp-components', 'wp-blocks', 'wp-block-editor', 'wc-blocks-registry', 'wc-settings'],
            '1.0.0',
            true
        );

        // Get payment gateway instance to access settings - using WC's gateway API instead of directly instantiating
        if (class_exists('WC_Payment_Gateway') && function_exists('WC')) {
            // Only access gateway data if WooCommerce is fully loaded
            if (isset(WC()->payment_gateways) && WC()->payment_gateways) {
                $gateways = WC()->payment_gateways->payment_gateways();
                if (isset($gateways['fiberpay'])) {
                    $gateway = $gateways['fiberpay'];
                    $payment_data = [
                        'title' => $gateway->get_title(),
                        'description' => $gateway->get_description(),
                        'enabled' => $gateway->enabled === 'yes',
                        'is_test_env' => $gateway->is_test_env === 'yes',
                        'gateway_id' => $gateway->id,
                    ];
                    
                    fiberpaygw_log_debug('Localizing payment data: ' . wp_json_encode($payment_data), array('source' => 'fiberpay-payment-gateway'));
                    
                    wp_localize_script(
                        'fiberpay-blocks',
                        'fiberpaygw_payments_data',
                        $payment_data
                    );
                }
            } else {
                fiberpaygw_log_debug('WooCommerce payment gateways not yet available', array('source' => 'fiberpay-payment-gateway'));
            }
        }

        fiberpaygw_log_debug('Block scripts registered successfully', array('source' => 'fiberpay-payment-gateway'));
    }, 20); // Increased priority to ensure WooCommerce is fully loaded
});

function fiberpaygw_add_gateway_class($gateways) {
	$gateways[] = 'FIBERPAYGW_Payment_Gateway';
	return $gateways;
}
add_filter('woocommerce_payment_gateways', 'fiberpaygw_add_gateway_class');

add_action('plugins_loaded', 'fiberpaygw_init_gateway_class', 11);

register_deactivation_hook( __FILE__, 'fiberpaygw_delete_order_data_transients' );

function fiberpaygw_delete_order_data_transients() {
    delete_transient( 'fiberpaygw_order_data_prod' );
    delete_transient( 'fiberpaygw_order_data_test' );
}

function fiberpaygw_init_gateway_class() {
    fiberpaygw_log_debug('fiberpaygw_init_gateway_class called', array('source' => 'fiberpay-payment-gateway'));
    
    if ( is_admin() ) {
        require_once dirname( __FILE__ ) . '/includes/class-fiberpaygw-admin-notices.php';
    }

    if(class_exists('WC_Payment_Gateway')) {
        fiberpaygw_log_debug('WC_Payment_Gateway class exists', array('source' => 'fiberpay-payment-gateway'));
        static $plugin;

        if ( ! isset( $plugin ) ) {
            fiberpaygw_log_debug('Loading gateway class', array('source' => 'fiberpay-payment-gateway'));
            include_once plugin_dir_path(__FILE__) . '/includes/class-fiberpaygw-gateway.php';
            fiberpaygw_log_debug('Gateway class loaded', array('source' => 'fiberpay-payment-gateway'));
        } else {
            fiberpaygw_log_debug('Plugin already set', array('source' => 'fiberpay-payment-gateway'));
        }

        return $plugin;
    } else {
        fiberpaygw_log_debug('WC_Payment_Gateway class does not exist', array('source' => 'fiberpay-payment-gateway'), 'warning');
    }
}

// Make sure the gateway class is loaded before blocks attempt to use it
add_action('init', function() {
    fiberpaygw_log_debug('init action - ensuring gateway class is loaded', array('source' => 'fiberpay-payment-gateway'));
    fiberpaygw_init_gateway_class();
    fiberpaygw_log_debug('Gateway initialization complete', array('source' => 'fiberpay-payment-gateway'));
}, 5);
