# Fiberpay Payment Gateway

Fiberpay payment gateway plugin for WooCommerce allows you to accept payments directly on your store through [Fiberpay's](https://fiberpay.pl) secure payment processing platform. This plugin enables Polish payment methods for your WordPress online store, providing your customers with familiar and trusted payment options.

## Available Payment Methods

With Fiberpay Payment Gateway, your customers can pay using:

- **Quick online transfers** from all major Polish banks (przelewy natychmiastowe)
- **BLIK** - Poland's popular mobile payment method
- **Traditional bank transfers** (przelewy tradycyjne)

**Note:** Credit card payments are not available through this gateway.

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

- Accept instant payments through Polish banking methods
- Support for WooCommerce Blocks
- Sandbox environment for testing
- Seamless integration with WooCommerce checkout
- Automatic payment validation through webhooks
- Compatible with WooCommerce High Performance Order Storage (HPOS)
- Fast and secure payment processing specifically designed for the Polish market

## Installation

1. Upload the plugin files to the `/wp-content/plugins/fiberpay-payment-gateway` directory, or install the plugin through the WordPress plugins screen directly
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the WooCommerce->Settings->Payments screen to configure the plugin

## Configuration

After installation, you need to configure the following settings:

1. **Enable/Disable** - Turn the payment method on or off
2. **Title** - Payment method title visible to customers (default: "Fiberpay quick money transfer")
3. **Description** - Payment method description visible to customers
4. **Test Environment** - Enable for testing with the Fiberpay sandbox environment
5. **API Key** - Your Fiberpay API key obtained from the Fiberpay merchant panel
6. **Secret Key** - Your Fiberpay Secret key for secure communication
7. **Collect Order Code** - Identifier for your payment collection order in Fiberpay

## Currency Support

Currently, the plugin supports payments in the following currencies:
- PLN (Polish Złoty)

## Technical Information

### Webhooks/Callbacks

The plugin automatically configures a callback URL that Fiberpay will use to notify your store about successful payments. This ensures that orders are automatically marked as paid when a customer completes payment through Fiberpay.

### Version Compatibility

- Current Version: 0.1.9 (Updated: December 2025)
- Tested with WordPress 6.9
- Tested with WooCommerce 10.4.3
- Tested with PHP 8.2.30
- WooCommerce Blocks: Compatible with cart and checkout blocks

## Why Choose Fiberpay?

- **Polish Market Specialist**: Payment methods optimized for Polish customers
- **Quick Setup**: Easy integration with your WooCommerce store
- **Competitive Fees**: Visit [Fiberpay's website](https://fiberpay.pl) for current pricing
- **Reliable Service**: Secure and stable payment processing 
- **Excellent Support**: Dedicated customer service team

## Documentation & Support

- [Fiberpay Official Website](https://fiberpay.pl)
- [API Documentation](https://github.com/fiberpay/api/blob/master/fiberpay.md)
- For support queries, please [contact us](mailto:info@fiberpay.pl)

## Contributors

- fiberpaypl

## License

This project is licensed under the GNU General Public License v2 or later - see the [LICENSE](LICENSE) file for details.

## Third-party Libraries

This plugin uses the following third-party libraries:

- Composer - For dependency management (MIT License)
- Firebase PHP-JWT - For secure JWT handling (BSD-3-Clause License)
- Fiberpay PHP Client - For communication with Fiberpay API