=== Fiberpay Payment Gateway ===
Contributors: fiberpay
Tags: payment gateway, woocommerce, fiberpay, blik, paybylink
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 0.1.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Take instant payments on your WooCommerce store using Fiberpay payment gateway with Polish payment methods including BLIK and quick bank transfers.

== Description ==

Fiberpay payment gateway plugin for WooCommerce allows you to accept payments directly on your store through [Fiberpay Payment Gateway](https://fiberpay.pl) secure payment processing platform. This plugin enables Polish payment methods for your WordPress online store, providing your customers with familiar and trusted payment options.

= Available Payment Methods =
* **Quick online transfers** from all major Polish banks (przelewy natychmiastowe)
* **BLIK** - Poland's popular mobile payment method
* **Traditional bank transfers** (przelewy tradycyjne)

**Note:** Credit card payments are not available through this gateway.

= Features =
* Accept instant payments through Polish banking methods
* Support for WooCommerce Blocks
* Sandbox environment for testing
* Seamless integration with WooCommerce checkout
* Automatic payment validation through webhooks
* Compatible with WooCommerce High Performance Order Storage (HPOS)
* Fast and secure payment processing specifically designed for the Polish market

= Requirements =
* WordPress 5.0 or higher
* WooCommerce 6.0 or higher
* PHP 7.2 or higher
* Fiberpay merchant account

= Why Choose Fiberpay? =
* **Polish Market Specialist**: Payment methods optimized for Polish customers
* **Quick Setup**: Easy integration with your WooCommerce store
* **Competitive Fees**: Visit [Fiberpay website](https://fiberpay.pl) for current pricing
* **Reliable Service**: Secure and stable payment processing
* **Excellent Support**: Dedicated customer service team

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

= What payment methods are supported? =

The plugin supports all major Polish online payment methods including quick bank transfers from all Polish banks and BLIK mobile payments. Credit card payments are not currently supported.

= Does this plugin work with WooCommerce Blocks? =

Yes, this plugin is fully compatible with WooCommerce Blocks, including the new checkout block.

= Is PLN the only supported currency? =

Currently, the plugin only supports Polish Złoty (PLN) as this is the primary currency for the Polish market.

= Can I test the plugin before going live? =

Yes, the plugin includes a test environment option. We strongly recommend testing with a [sandbox account](https://test.fiberpay.pl) before using it in production.

== Changelog ==

= 0.1.8 =
* Improved and streamlined CI/CD workflow
* Added workflow for automatic deployment to WordPress SVN
* Updated technical documentation

= 0.1.7 =
* Improved and streamlined CI/CD workflow
* Added workflow for automatic deployment to WordPress SVN
* Updated technical documentation

= 0.1.6 =
* Enhanced documentation with detailed Polish payment methods information
* Improved debug logging throughout the plugin
* Added comprehensive SEO-focused content
* Updated plugin compatibility information

= 0.1.5 =
* Added support for WooCommerce 8.5
* Improved compatibility with WooCommerce HPOS
* Enhanced error handling and logging

= 0.1.1 =
* Added support for WooCommerce Blocks
* Improved error logging

== Third-party Libraries ==

This plugin uses the following third-party libraries:
* Composer - For dependency management (MIT License)
* Firebase PHP-JWT - For secure JWT handling (BSD-3-Clause License)
* Fiberpay PHP Client - For communication with Fiberpay API
