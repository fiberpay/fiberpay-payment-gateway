<?php

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

abstract class AbstractPaymentMethodType {
    protected string $name;
    
    abstract public function initialize(): void;
    abstract public function is_active(): bool;
    abstract public function get_payment_method_script_handles(): array;
    abstract public function get_payment_method_data(): array;
    
    public function get_name(): string { return $this->name; }
    public function get_supported_features(): array { return []; }
}
