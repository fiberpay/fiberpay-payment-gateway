<?php

class WC_Payment_Gateway {
    public string $id;
    public string $icon;
    public bool $has_fields;
    public string $method_title;
    public string $method_description;
    public string $title;
    public string $description;
    public string $enabled;
    public array $form_fields;
    
    public function get_option(string $key, $default = null) { return $default; }
    public function get_title(): string { return ''; }
    public function get_description(): string { return ''; }
    public function init_form_fields(): void {}
    public function init_settings(): void {}
    public function process_admin_options(): bool { return true; }
    public function is_available(): bool { return true; }
}
