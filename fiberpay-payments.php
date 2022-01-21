<?php
/*
 * Plugin Name: Fiberpay Payment Plugin
 * Plugin URI: https://fiberpay.pl
 * Description: Take instant payments on your store.
 * Author: Fiberpay
 * Author URI: https://fiberpay.pl
 * Version: 0.1.0
 */

if (!defined( 'ABSPATH')) {
	exit; // Exit if accessed directly.
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	return;
}

function fiberpay_add_gateway_class($gateways) {
	$gateways[] = 'Fiberpay_WC_Payment_Gateway';
	return $gateways;
}
add_filter('woocommerce_payment_gateways', 'fiberpay_add_gateway_class');

add_action('plugins_loaded', 'fiberpay_init_gateway_class', 11);

function fiberpay_init_gateway_class() {
	if(class_exists('WC_Payment_Gateway')) {
		require_once plugin_dir_path(__FILE__) . '/vendor/fiberpay/fiberpay-php/lib/FiberPayClient.php';
		require_once plugin_dir_path(__FILE__) . '/includes/class-wc-gateway-fiberpay.php';
	}
}
