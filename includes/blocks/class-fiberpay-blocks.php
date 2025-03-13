<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Fiberpay_Blocks_Support extends AbstractPaymentMethodType {
    private $gateway;
    
    public function __construct() {
        error_log('Fiberpay_Blocks_Support: Constructor called');
        
        // Make sure the gateway class is loaded
        if (!class_exists('Fiberpay_WC_Payment_Gateway')) {
            error_log('Fiberpay_Blocks_Support: Fiberpay_WC_Payment_Gateway class not found, loading it now');
            include_once plugin_dir_path(__FILE__) . '/../../includes/class-wc-gateway-fiberpay.php';
        }
        
        // Get WooCommerce's payment gateways to make sure our gateway is properly initialized
        $payment_gateways = WC()->payment_gateways()->payment_gateways();
        
        if (isset($payment_gateways['fiberpay_payments'])) {
            error_log('Fiberpay_Blocks_Support: Found gateway in WC payment gateways');
            $this->gateway = $payment_gateways['fiberpay_payments'];
        } else {
            error_log('Fiberpay_Blocks_Support: Gateway not found in WC payment gateways, creating new instance');
            $this->gateway = new Fiberpay_WC_Payment_Gateway();
        }
        
        error_log('Fiberpay_Blocks_Support: Gateway instance created, ID: ' . $this->gateway->id);
        error_log('Fiberpay_Blocks_Support: Gateway enabled: ' . ($this->gateway->enabled === 'yes' ? 'yes' : 'no'));
    }

    public function get_name() {
        error_log('Fiberpay_Blocks_Support: get_name called, returning: ' . $this->gateway->id);
        return $this->gateway->id;
    }

    public function get_payment_method_script_handles() {
        error_log('Fiberpay_Blocks_Support: get_payment_method_script_handles called');
        wp_register_script(
            'fiberpay-blocks',
            plugins_url('../../assets/js/blocks.js', __FILE__),
            array('wc-blocks-registry', 'wc-settings', 'wp-element'),
            '1.0.0',
            true
        );

        error_log('Fiberpay_Blocks_Support: Script registered');
        return ['fiberpay-blocks'];
    }

    public function get_payment_method_data() {
        error_log('Fiberpay_Blocks_Support: get_payment_method_data called');
        $data = [
            'title' => $this->gateway->get_title(),
            'description' => $this->gateway->get_description(),
            'supports' => $this->get_supported_features(),
            'icon' => $this->gateway->icon,
            'enabled' => $this->gateway->enabled === 'yes',
            'is_test_env' => $this->gateway->is_test_env === 'yes',
        ];
        error_log('Fiberpay_Blocks_Support: Returning data: ' . wp_json_encode($data));
        return $data;
    }

    public function get_supported_features() {
        error_log('Fiberpay_Blocks_Support: get_supported_features called');
        return [
            'products'
        ];
    }

    public function is_active() {
        $active = $this->gateway->enabled === 'yes';
        error_log('Fiberpay_Blocks_Support: is_active called, returning: ' . ($active ? 'true' : 'false'));
        return $active;
    }

    public function initialize() {
        error_log('Fiberpay_Blocks_Support: initialize called');
        // This method can be used to register hooks or do other setup tasks
    }
}
