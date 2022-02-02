<?php
/*
 * Plugin Name: Fiberpay Payment Plugin
 * Plugin URI: https://fiberpay.pl
 * Description: Take instant payments on your store.
 * Author: Fiberpay
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
