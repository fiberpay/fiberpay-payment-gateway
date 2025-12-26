# AGENTS.md - Fiberpay Payment Gateway Development Guide

This file contains development guidelines and commands for agentic coding agents working on the Fiberpay Payment Gateway plugin for WooCommerce.

## Project Overview

This is a WordPress plugin that implements the Fiberpay payment gateway for WooCommerce. It supports both traditional checkout and WooCommerce Blocks integration, specifically designed for the Polish market.

**Key Technologies:**
- WordPress 5.0+, WooCommerce 6.0+ (tested up to WooCommerce 8.5)
- PHP 7.2+ with Composer dependency management
- React hooks for WooCommerce Blocks integration
- JWT authentication for webhooks
- Polish payment methods: BLIK, quick bank transfers, traditional transfers

**Current Version:** 0.1.9 (Updated: December 2025)

## Build & Development Commands

### Composer Commands
```bash
# Install dependencies
composer install

# Install for production (used in CI/CD)
composer install --no-dev --optimize-autoloader

# Update dependencies
composer update
```

### Testing Commands
```bash
# Currently no test suite exists - this is a known gap
# To add testing, use:
wp scaffold plugin-tests fiberpay-payment-gateway
composer install --dev
./vendor/bin/phpunit
```

### Build & Distribution
```bash
# Create distribution package (excludes .distignore files)
zip -r fiberpay-payment-gateway.zip . -x@.distignore

# Local development - no build process required
# JavaScript files are served directly
```

### Docker Development
```bash
# Start development environment
docker-compose up

# Build Docker image
docker build -t fiberpay-payment-gateway .
```

## Code Style Guidelines

### PHP Standards (Following WordPress Coding Standards)

**Formatting:**
- Use real tabs for indentation (not spaces)
- Opening braces for functions/classes on next line
- Single quotes for string declarations
- Omit closing `?>` tag in pure PHP files

**Naming Conventions:**
- Classes: `FIBERPAYGW_` prefix for main components, `Fiberpay_` for blocks
- Functions: `fiberpaygw_` prefix with underscores
- Constants: `FIBERPAYGW_` prefix with descriptive names
- Variables: snake_case for local variables, camelCase for properties

**Security Patterns:**
```php
// Input sanitization
$sanitized_value = sanitize_text_field(wp_unslash($_POST['field_name'] ?? ''));

// Output escaping
echo esc_html($gateway->get_title());

// Nonce verification
if (isset($_GET['_wpnonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'fiberpay_action')) {
    return;
}

// Capability checks
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('Permission denied', 'fiberpay-payment-gateway'));
}
```

### JavaScript Standards (WooCommerce Blocks)

**React Hooks Usage:**
```javascript
// Use modern React hooks
const [registered, setRegistered] = useState(false);
const paymentSetupHandler = useCallback(() => {
    // Handler logic
}, [emitResponse]);

useEffect(() => {
    // Effect logic
}, [dependencies]);
```

**WooCommerce Blocks Integration:**
- Use `wc.wcBlocksRegistry` and `wc.wcSettings`
- Follow the `registerPaymentMethod` API
- Implement proper event handling with `eventRegistration`

## File Organization & Architecture

### Core Structure
```
fiberpay-payment-gateway.php          # Main plugin file
includes/
├── class-fiberpaygw-gateway.php      # Main payment gateway class
├── class-fiberpaygw-admin-notices.php # Admin notifications
└── blocks/
    └── class-fiberpay-blocks.php     # WooCommerce Blocks integration
assets/js/blocks.js                   # React component for blocks
languages/                            # Translation files (.pot, .mo files)
vendor/                               # Composer dependencies
.github/workflows/                    # CI/CD pipelines
├── build.yml                         # Distribution package creation
└── wordpress-svn-publish.yml          # WordPress SVN deployment
```

### Class Patterns

**Singleton Pattern (Gateway):**
```php
class FIBERPAYGW_Payment_Gateway extends WC_Payment_Gateway {
    private static $instance;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

**WooCommerce Integration:**
- Extend `WC_Payment_Gateway` for core functionality
- Extend `AbstractPaymentMethodType` for Blocks support
- Use WooCommerce hooks: `woocommerce_payment_gateways`, `before_woocommerce_init`

## Error Handling & Logging

### Debug Logging
```php
// Use the centralized logging function
fiberpaygw_log_debug('Message', ['context' => 'data']);

// Only logs when WP_DEBUG is enabled and WooCommerce logger is available
```

### Error Handling Patterns
```php
try {
    // API call or risky operation
    $result = $api_client->call();
} catch (Exception $e) {
    fiberpaygw_log_debug('API call failed: ' . $e->getMessage());
    wc_add_notice(__('Payment error occurred', 'fiberpay-payment-gateway'), 'error');
    return;
}
```

## Security Requirements

### Input Validation
- Always sanitize user input with `sanitize_text_field()` and `wp_unslash()`
- Validate API keys and order codes before processing
- Use WordPress nonces for form submissions

### Output Escaping
- Escape all output with appropriate functions: `esc_html()`, `esc_attr()`, `esc_url()`
- Use WordPress i18n functions: `__()`, `_e()`, `_x()`

### API Security
- Validate JWT signatures for webhook callbacks
- Use HTTPS for all API communications
- Implement proper error responses without exposing sensitive data

## WooCommerce Integration

### Gateway Registration
```php
function fiberpaygw_add_gateway_class($gateways) {
    $gateways[] = 'FIBERPAYGW_Payment_Gateway';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'fiberpaygw_add_gateway_class');
```

### Blocks Compatibility
- Declare compatibility with `custom_order_tables` and `cart_checkout_blocks`
- Register payment method type with WooCommerce Blocks registry
- Handle both traditional and block-based checkout flows

## Dependencies Management

### PHP Dependencies (composer.json)
```json
{
    "name": "fiberpay/fiberpay-payment-gateway",
    "description": "Fiberpay payment gateway",
    "version": "0.1.8",
    "require": {
        "fiberpay/fiberpay-php": "^0.1.5",
        "firebase/php-jwt": "^6.0"
    }
}
```

### WordPress/WooCommerce Requirements
- WordPress 5.0+ (tested up to 6.8)
- WooCommerce 6.0+ (tested up to WooCommerce 8.5)
- PHP 7.2+
- Compatible with WooCommerce High Performance Order Storage (HPOS)

## Development Workflow

### Local Development
1. Use Docker Compose for consistent environment
2. Enable WP_DEBUG for detailed logging
3. Use Fiberpay sandbox environment for testing
4. Test both traditional checkout and WooCommerce Blocks

### CI/CD Pipeline
- GitHub Actions builds on push to main branch
- Creates distribution ZIP excluding development files
- Uploads artifacts and release assets automatically
- **WordPress SVN Deployment**: Automatic deployment to WordPress.org plugin repository on releases
- Uses `.distignore` file to exclude development files from distribution packages

### Code Quality
- Follow WordPress Coding Standards
- Use proper error handling and logging
- Implement security best practices
- Test thoroughly in sandbox environment

## Common Patterns

### Admin Notices
```php
// Use the admin notices class for consistent messaging
FIBERPAYGW_Admin_Notices::add_notice('message', 'type', 'dismissible');
```

### API Client Usage
```php
// Initialize FiberPay client
$client = new \FiberPay\FiberPayClient($api_key, $secret_key, $is_test_env);
```

### Localization
```php
// Always use text domain for translatable strings
__('Your string here', 'fiberpay-payment-gateway');
```

### Translation Files
- Text domain: `fiberpay-payment-gateway`
- `.pot` file in `languages/` directory for translators
- Polish translation available: `fiberpay-payment-gateway-pl_PL.mo`
- Use standard WordPress i18n functions: `__()`, `_e()`, `_x()`

## Testing Guidelines

### Manual Testing
- Test in WordPress sandbox environment
- Verify both production and sandbox modes
- Test WooCommerce Blocks integration
- Validate payment flow end-to-end

### Integration Testing
- Use Fiberpay sandbox for real payment testing
- Test webhook callback handling
- Verify error handling scenarios

## Supported Payment Methods

This plugin specifically supports Polish payment methods:
- **Quick online transfers** from all major Polish banks (przelewy natychmiastowe)
- **BLIK** - Poland's popular mobile payment method
- **Traditional bank transfers** (przelewy tradycyjne)
- **Currency Support**: PLN (Polish Złoty) only

**Note:** Credit card payments are not available through this gateway.

## Known Limitations & Improvement Areas

1. **No automated test suite** - Add PHPUnit testing
2. **Manual JavaScript build** - Consider @wordpress/scripts
3. **Limited error handling** - Enhance edge case coverage
4. **No code quality tools** - Add PHPCS, PHPStan

## Plugin Activation & Deactivation

### Activation Hooks
- Plugin automatically registers with WooCommerce
- Blocks compatibility declared on initialization
- Admin notices system initialized

### Deactivation Hooks
- Cleans up transients: `fiberpaygw_delete_order_data_transients()`
- Removes scheduled tasks if any

This guide should help agentic coding agents understand the codebase structure, follow established patterns, and maintain consistency when working on this WordPress payment gateway plugin.