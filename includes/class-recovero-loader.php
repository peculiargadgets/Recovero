<?php
if (!defined('ABSPATH')) exit;

class Recovero_Loader {
    public function run() {
        // Check if WooCommerce is active
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            return;
        }
        
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once RECOVERO_PATH . 'includes/class-recovero-db.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-tracker.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-ajax.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-cron.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-admin.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-recovery.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-whatsapp.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-analytics.php';
        require_once RECOVERO_PATH . 'includes/helpers.php';

        if (file_exists(RECOVERO_PATH . 'pro/class-recovero-pro.php')) {
            require_once RECOVERO_PATH . 'pro/class-recovero-pro.php';
        }
    }

    private function init_hooks() {
        new Recovero_Tracker();
        new Recovero_Ajax();
        new Recovero_Admin();
        new Recovero_Cron();
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Handle recovery token
        add_action('init', [$this, 'handle_recovery_token']);

        if (class_exists('Recovero_Pro')) {
            new Recovero_Pro();
        }
    }
    
    public function enqueue_public_scripts() {
        wp_enqueue_script('recovero-public', RECOVERO_URL . 'assets/js/public.js', ['jquery'], RECOVERO_VERSION, true);
        wp_localize_script('recovero-public', 'recovero', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('recovero_nonce')
        ]);
        
        wp_enqueue_style('recovero-public', RECOVERO_URL . 'assets/css/public.css', [], RECOVERO_VERSION);
    }
    
    public function enqueue_admin_scripts() {
        wp_enqueue_script('recovero-admin', RECOVERO_URL . 'assets/js/admin.js', ['jquery'], RECOVERO_VERSION, true);
        wp_enqueue_style('recovero-admin', RECOVERO_URL . 'assets/css/admin.css', [], RECOVERO_VERSION);
    }
    
    public function handle_recovery_token() {
        if (isset($_GET['recovero_token']) && isset($_GET['recovero_cart'])) {
            $token = sanitize_text_field($_GET['recovero_token']);
            $cart_id = absint($_GET['recovero_cart']);
            
            $recovery = new Recovero_Recovery();
            $checkout_url = $recovery->process_recovery($token);
            
            wp_safe_redirect($checkout_url);
            exit;
        }
    }
}

