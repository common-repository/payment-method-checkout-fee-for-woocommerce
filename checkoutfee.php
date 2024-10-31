<?php
/**
 * Plugin Name: Payment and Shipping Method Checkout Fee for WooCommerce
 * Plugin URI:
 * Description: Payment and Shipping Method Checkout Fee for WooCommerce
 * Version: 2.0.1
 * Author: Ivan Popov
 * Author URI: https://vipestudio.com/en/
 **/

require __DIR__ . '/vendor/autoload.php';

if (!defined('ABSPATH')) exit;  

function pmcf_migrate_settings() {
     $fee_type_to_set = 'percentage';  

     if (is_plugin_active('payment-method-checkout-fee-for-woocommerce/checkoutfee.php')) {
        
        if (class_exists('WooCommerce')) {
            
             $gateways = WC()->payment_gateways->get_available_payment_gateways();

            if ($gateways) {
                
                foreach ($gateways as $gateway) {
                    $methodslug = $gateway->id;
                    $settings_keys = ['_name_enabled', '_name_label', '_name_percent'];

                    foreach ($settings_keys as $key_suffix) {
                        $old_key = $methodslug . $key_suffix;
                        $value = get_option($old_key);

                        if ($value !== false) {
                            $update_success = update_option($old_key, $value);
                            if ($update_success) {
                                $delete_success = delete_option($old_key);
                            }
                        }
                    }

                    $fee_type_key = $methodslug . '_fee_type';
                    $fee_type_update_success = update_option($fee_type_key, $fee_type_to_set);
                }
            }
            
        }
   
    }
}

register_activation_hook(__FILE__, 'pmcf_migrate_settings');


// Admin Css
class AdminCss {
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'pmcf_load_wp_admin_style'));
    }
    public function pmcf_load_wp_admin_style() {
        if (class_exists('WooCommerce')) {
            wp_register_style('checkoutfee_wp_admin_css', plugins_url('assets/css/admin_css.css', __FILE__), false, '1.0.0', 'all');
            wp_enqueue_style('checkoutfee_wp_admin_css');
            wp_enqueue_script('checkoutfee_wp_admin_js', plugins_url('assets/js/admin-settings.js', __FILE__), array('jquery'), '1.0.0', true);
        }
    }
}

class WC_Custom_Settings_Tab {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_checkout_fee', array($this, 'settings_tab_output'));
        add_action('woocommerce_update_options_checkout_fee', array($this, 'update_settings'));
    }

    public function add_settings_tab($settings_tabs) {
        $settings_tabs['checkout_fee'] = __('Checkout Fee', 'woocommerce');
        return $settings_tabs;
    }

    public function settings_tab_output() {
        $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
        woocommerce_admin_fields($this->get_settings($current_section));
    }

    private function get_settings($section) {
        $settings = array();
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        $active_shipping_methods = $this->get_active_shipping_methods();

        foreach ($gateways as $gateway) {
            $settings = array_merge($settings, $this->generate_settings_for_method($gateway, $section));
        }

        foreach ($active_shipping_methods as $method_id => $method_title) {
            $method = (object) ['id' => $method_id, 'title' => $method_title];
            $settings = array_merge($settings, $this->generate_settings_for_method($method, $section));
        }

        $settings[] = array('type' => 'sectionend', 'id' => 'wc_settings_tab_demo_section_end');
        return $settings;
    }

    private function get_active_shipping_methods() {
        $methods = array();
        $shipping_zones = WC_Shipping_Zones::get_zones();
        $default_zone = WC_Shipping_Zones::get_zone(0);

        foreach ($shipping_zones as $zone) {
            foreach ($zone['shipping_methods'] as $method) {
                if (!array_key_exists($method->id, $methods)) {
                    $methods[$method->id] = $method->title;
                }
            }
        }

        if ($default_zone) {
            foreach ($default_zone->get_shipping_methods() as $method) {
                if (!array_key_exists($method->id, $methods)) {
                    $methods[$method->id] = $method->title;
                }
            }
        }

        return $methods;
    }

private function generate_settings_for_method($method) {
    $settings = array(
        array(
            'title' => sprintf(__('Fee settings for %s', 'woocommerce'), $method->title),
            'type' => 'title',
            'id' => 'wc_settings_' . $method->id . '_fee_settings'
        ),
        array(
            'title' => __('Fee Label', 'woocommerce'),
            'id' => $method->id . '_name_label',
            'type' => 'text',
            'desc_tip' => __('This controls the label which the user sees during checkout.', 'woocommerce'),
            'default' => get_option($method->id . '_name_label')
        ),
        array(
            'title' => __('Fee Type', 'woocommerce'),
            'id' => $method->id . '_fee_type',
            'type' => 'select',
            'options' => array(
                'no_fee' => __('No Fee', 'woocommerce'),
                'percentage' => __('Enable Percentage Fee', 'woocommerce'),
                'fixed' => __('Enable Fixed Fee', 'woocommerce')
            ),
            'desc' => __('Select the type of fee to enable.', 'woocommerce'),
            'default' => 'percentage' // Default to percentage
        ),
        array(
            'title' => __('Fee Amount', 'woocommerce'),
            'id' => $method->id . '_name_percent',
            'type' => 'number',
            'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            'desc_tip' => __('Enter the fee amount or percentage, depending on the selected type.', 'woocommerce'),
            'default' => get_option($method->id . '_name_percent')
        ),
        array(
            'type' => 'sectionend',
            'id' => 'wc_settings_' . $method->id . '_fee_settings'
        )
    );
    return $settings;
}


    public function update_settings() {
        $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
        $settings = $this->get_settings($current_section);
        woocommerce_update_options($settings);
    }
}

class PMCF_Checkout_Fees {
    public function __construct() {
        add_action('wp_loaded', array($this, 'pmcf_add_checkout_fees'));
    }

    public function pmcf_payment_method_checkout_fee() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $payment_method = WC()->session->get('chosen_payment_method');
        $gateways = WC()->payment_gateways->get_available_payment_gateways();

        foreach ($gateways as $gateway) {
            if ($gateway->id === $payment_method && $gateway->enabled == 'yes') {
                $this->apply_fee_for_method($gateway);
            }
        }

        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        if (!empty($chosen_methods)) {
            foreach ($chosen_methods as $chosen_method) {
                $method_id = current(explode(':', $chosen_method));
                $shipping_method = $this->get_shipping_method_by_id($method_id);
                if ($shipping_method) {
                    $this->apply_fee_for_method($shipping_method);
                }
            }
        }
    }

    private function apply_fee_for_method($method) {
        $method_id = $method->id;
        $fee_type = get_option($method_id . '_fee_type');
        $fee_amount = get_option($method_id . '_fee_amount');
        $fee_label = get_option($method_id . '_name_label') ?: $method->title . ' Fee';

        if ($fee_type != 'no_fee' && $fee_amount > 0) {
            $fee_to_add = ($fee_type == 'percentage') ? (WC()->cart->cart_contents_total * ($fee_amount / 100)) : $fee_amount;
            WC()->cart->add_fee($fee_label, $fee_to_add, true);
        }
    }

    private function get_shipping_method_by_id($method_id) {
        foreach (WC_Shipping_Zones::get_zones() as $zone) {
            foreach ($zone['shipping_methods'] as $method) {
                if ($method->id === $method_id) {
                    return $method;
                }
            }
        }
        $default_zone = WC_Shipping_Zones::get_zone(0);
        foreach ($default_zone->get_shipping_methods() as $method) {
            if ($method->id === $method_id) {
                return $method;
            }
        }
        return null;
    }

    public function pmcf_add_checkout_fees() {
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_cart_calculate_fees', array($this, 'pmcf_payment_method_checkout_fee'));
        }
    }
}

class PMCF_JSCalculation {
    public function __construct() {
        add_action('woocommerce_after_checkout_form', array($this, 'pmcf_js_calculation'));
    }

    public function pmcf_js_calculation() {
        if (class_exists('WooCommerce')) {
            if (is_checkout()) {
                wp_enqueue_script('jquery');
                wp_enqueue_script('checkoutfee_checkout_js', plugins_url('assets/js/checkout.js', __FILE__), array('jquery'), '2.0.0', true);
            }
            
        }
    }
}

new AdminCss();
new WC_Custom_Settings_Tab();
new PMCF_Checkout_Fees();
new PMCF_JSCalculation();
?>
