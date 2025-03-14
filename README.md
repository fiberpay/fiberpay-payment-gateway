# Fiberpay Payment Gateway

Fiberpay payment gateway plugin for WooCommerce allows you to accept payments directly on your store through Fiberpay's secure payment processing platform.

## Requirements

- WordPress 5.0 or higher
- WooCommerce 6.0 or higher
- PHP 7.2 or higher
- Fiberpay merchant account

## Setup

Setting up plugin requires as follows:
1. Creating an account in the Fiberpay service
2. Generating API keys in the Fiberpay user panel
3. Creating payment order of type Collect
4. Entering API keys and the Collect order identifier (code) on the plugin configuration screen

**Prior to production usage, it is highly recommended to create [sandbox account](https://test.fiberpay.pl) for integration tests in a safe environment.**

## Features

- Accept instant payments
- Support for WooCommerce Blocks
- Sandbox environment for testing
- Seamless integration with WooCommerce checkout

## Installation

1. Upload the plugin files to the `/wp-content/plugins/fiberpay-payment-gateway` directory, or install the plugin through the WordPress plugins screen directly
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the WooCommerce->Settings->Payments screen to configure the plugin

## Documentation

- [API documentation](https://fiberpay.gitbook.io/fiberpay/fiberpay)
- For support queries, please [contact us](mailto:info@fiberpay.pl)

## License

This project is licensed under the GNU General Public License v2 or later - see the [LICENSE](LICENSE) file for details.

## Third-party Libraries

This plugin uses the following third-party libraries:
- Composer - For dependency management (MIT License)
