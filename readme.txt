=== Fiberpay Payment Gateway ===
Contributors: fiberpay
Tags: payment, gateway, woocommerce, fiberpay, payments
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 0.1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Take instant payments on your WooCommerce store using Fiberpay payment gateway.

== Description ==

Fiberpay payment gateway plugin for WooCommerce allows you to accept payments directly on your store through Fiberpay's secure payment processing platform.

= Features =
* Accept instant payments
* Support for WooCommerce Blocks
* Sandbox environment for testing
* Seamless integration with WooCommerce checkout

= Requirements =
* WordPress 5.0 or higher
* WooCommerce 6.0 or higher
* PHP 7.2 or higher
* Fiberpay merchant account

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/fiberpay-payment-gateway` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Create an account in the Fiberpay service if you haven't already.
4. Generate API keys in the Fiberpay user panel.
5. Create a payment order of type Collect.
6. Use the WooCommerce->Settings->Payments screen to configure the plugin:
   * Enter your API keys
   * Enter the Collect order identifier (code)
   * Configure other settings as needed

= Testing =
Prior to production usage, it is highly recommended to create a [sandbox account](https://test.fiberpay.pl) for integration tests in a safe environment.

== Frequently Asked Questions ==

= Where can I get support? =

For support queries, please contact us at info@fiberpay.pl or visit our [documentation](https://fiberpay.gitbook.io/fiberpay/fiberpay).

= Is this plugin secure? =

Yes, the plugin uses Fiberpay's secure API endpoints and doesn't store sensitive payment data on your server.

== Changelog ==

= 0.1.1 =
* Added support for WooCommerce Blocks
* Improved error logging
* Bug fixes and performance improvements

= 0.1.0 =
* Initial release

== Upgrade Notice ==

= 0.1.1 =
This version adds support for WooCommerce Blocks and includes important bug fixes. Upgrade recommended.

== Third-party Libraries ==

This plugin uses the following third-party libraries:
* Composer - For dependency management (MIT License)
