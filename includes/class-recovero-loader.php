<?php
/**
 * Recovero Loader Class
 * Handles loading of all plugin components
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recovero_Loader {
    
    private $plugin_classes = [];
    private $pro_classes = [];
    private $hooks_registered = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->define_classes();
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        if (!$this->is_woocommerce_available()) {
            return;
        }
        
        $this->load_dependencies();
        $this->register_hooks();
        $this->init_classes();
    }
    
    /**
     * Check if WooCommerce is available
     */
    private function is_woocommerce_available() {
        return class_exists('WooCommerce') && function_exists('WC');
    }
    
    /**
     * Define plugin classes
     */
    private function define_classes() {
        $this->plugin_classes = [
            'Recovero_DB' => 'includes/class-recovero-db.php',
            'Recovero_Tracker' => 'includes/class-recovero-tracker.php',
            'Recovero_Ajax' => 'includes/class-recovero-ajax.php',
            'Recovero_Cron' => 'includes/class-recovero-cron.php',
            'Recovero_Admin' => 'includes/class-recovero-admin.php',
            'Recovero_Recovery' => 'includes/class-recovero-recovery.php',
            'Recovero_WhatsApp' => 'includes/class-recovero-whatsapp.php',
            'Recovero_Analytics' => 'includes/class-recovero-analytics.php',
            'Recovero_Public_Loader' => 'includes/class-recovero-public-loader.php'
        ];
        
        $this->pro_classes = [
            'Recovero_Pro' => 'pro/class-recovero-pro.php',
            'Recovero_Advanced_Triggers' => 'pro/class-recovero-advanced-triggers.php',
            'Recovero_Recovery_Coupon' => 'pro/class-recovero-recovery-coupon.php',
            'Recovero_Push' => 'pro/class-recovero-push.php',
            'Recovero_Heatmap' => 'pro/class-recovero-heatmap.php',
        ];
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load core classes first
        foreach ($this->plugin_classes as $class => $file) {
            $this->load_class_file($file);
        }
        
        // Load license class for pro features
        $this->load_class_file('includes/class-recovero-license.php');
    }
    
    /**
     * Load class file safely
     */
    private function load_class_file($file) {
        $filepath = RECOVERO_PATH . $file;
        
        if (file_exists($filepath) && is_readable($filepath)) {
            require_once $filepath;
            return true;
        }
        
        return false;
    }
    
    /**
     * Register plugin hooks
     */
    private function register_hooks() {
        if ($this->hooks_registered) {
            return;
        }
        
        // Handle recovery token
        add_action('init', [$this, 'handle_recovery_token']);
        
        // Load text domain
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        $this->hooks_registered = true;
    }
    
    /**
     * Initialize plugin classes
     */
    private function init_classes() {
        // Initialize core classes
        $this->init_core_classes();
        
        // Initialize pro classes if available
        $this->init_pro_classes();
    }
    
    /**
     * Initialize core classes
     */
    private function init_core_classes() {
        $core_classes = [
            'Recovero_Tracker',
            'Recovero_Ajax',
            'Recovero_Admin',
            'Recovero_Cron',
        ];
        
        foreach ($core_classes as $class) {
            if (class_exists($class)) {
                try {
                    new $class();
                } catch (Exception $e) {
                    error_log("Recovero: Failed to initialize {$class}: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Initialize pro classes
     */
    private function init_pro_classes() {
        // Check if pro features are available
        if (!$this->is_pro_available()) {
            return;
        }
        
        // Load pro classes
        foreach ($this->pro_classes as $class => $file) {
            if ($this->load_class_file($file) && class_exists($class)) {
                try {
                    new $class();
                } catch (Exception $e) {
                    error_log("Recovero: Failed to initialize pro class {$class}: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Check if pro features are available
     */
    private function is_pro_available() {
        // Check if pro directory exists
        if (!is_dir(RECOVERO_PATH . 'pro')) {
            return false;
        }
        
        // Check if license is valid
        if (class_exists('Recovero_License')) {
            try {
                $license = new Recovero_License();
                return $license->is_valid();
            } catch (Exception $e) {
                error_log("Recovero: License check failed: " . $e->getMessage());
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Handle recovery token
     */
    public function handle_recovery_token() {
        if (!isset($_GET['recovero_token']) || !isset($_GET['recovero_cart'])) {
            return;
        }
        
        $token = sanitize_text_field($_GET['recovero_token']);
        $cart_id = absint($_GET['recovero_cart']);
        
        if (empty($token) || empty($cart_id)) {
            return;
        }
        
        try {
            if (class_exists('Recovero_Recovery')) {
                $recovery = new Recovero_Recovery();
                $checkout_url = $recovery->process_recovery($token);
                
                if ($checkout_url) {
                    wp_safe_redirect($checkout_url);
                    exit;
                }
            }
        } catch (Exception $e) {
            error_log("Recovero: Recovery token handling failed: " . $e->getMessage());
        }
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'recovero',
            false,
            dirname(RECOVERO_BASENAME) . '/languages'
        );
    }
    
    /**
     * Get plugin classes
     */
    public function get_plugin_classes() {
        return $this->plugin_classes;
    }
    
    /**
     * Get pro classes
     */
    public function get_pro_classes() {
        return $this->pro_classes;
    }
    
    /**
     * Check if a specific class is loaded
     */
    public function is_class_loaded($class) {
        return class_exists($class);
    }
}
