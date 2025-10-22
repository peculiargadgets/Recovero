<?php
/**
 * Recovero Public Loader
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recovero_Public_Loader {
    
    private $plugin_url;
    private $version;
    
    public function __construct() {
        $this->plugin_url = RECOVERO_URL;
        $this->version = RECOVERO_VERSION;
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_footer', [$this, 'add_public_variables']);
    }
    
    public function enqueue_scripts() {
        // Only load on cart/checkout pages or if tracking is enabled
        if ($this->should_load_assets()) {
            // Styles
            wp_enqueue_style(
                'recovero-public',
                $this->plugin_url . 'assets/css/public.css',
                [],
                $this->version
            );
            
            // Scripts
            wp_enqueue_script(
                'recovero-public',
                $this->plugin_url . 'assets/js/public.js',
                ['jquery'],
                $this->version,
                true
            );
        }
    }
    
    public function add_public_variables() {
        if (!$this->should_load_assets()) {
            return;
        }
        
        $variables = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('recovero_nonce'),
            'exit_intent_enabled' => get_option('recovero_enable_tracking', true),
            'push_enabled' => get_option('recovero_enable_push_notifications', false),
            'whatsapp_enabled' => get_option('recovero_enable_whatsapp_recovery', false),
            'cart_tracking_enabled' => get_option('recovero_enable_tracking', true),
            'plugin_url' => $this->plugin_url,
            'cart_url' => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url()
        ];
        
        wp_localize_script('recovero-public', 'recovero_public', $variables);
    }
    
    private function should_load_assets() {
        // Load on cart and checkout pages
        if (is_cart() || is_checkout()) {
            return true;
        }
        
        // Load if tracking is enabled and user has items in cart
        if (get_option('recovero_enable_tracking', true) && WC()->cart && !WC()->cart->is_empty()) {
            return true;
        }
        
        // Load on shop pages if tracking is enabled
        if (get_option('recovero_enable_tracking', true) && (is_shop() || is_product_category() || is_product())) {
            return true;
        }
        
        return false;
    }
}

// Initialize the public loader
new Recovero_Public_Loader();
