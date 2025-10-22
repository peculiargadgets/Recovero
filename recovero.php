<?php
/**
 * Plugin Name: Recovero - Abandoned Cart Recovery for WooCommerce
 * Plugin URI: https://github.com/nabilaminhridoy
 * Description: Recover abandoned carts and incomplete orders with automated email & WhatsApp notifications. Includes advanced analytics and recovery tools.
 * Version: 1.0.0
 * Author: Nabil Amin Hridoy
 * Author URI: https://nabilaminhridoy.vercel.app
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: recovero
 * Domain Path: /languages
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RECOVERO_VERSION', '1.0.0');
define('RECOVERO_FILE', __FILE__);
define('RECOVERO_PATH', plugin_dir_path(__FILE__));
define('RECOVERO_URL', plugin_dir_url(__FILE__));
define('RECOVERO_BASENAME', plugin_basename(__FILE__));
define('RECOVERO_MIN_PHP_VERSION', '7.4');
define('RECOVERO_MIN_WP_VERSION', '5.0');
define('RECOVERO_MIN_WC_VERSION', '3.0');

/**
 * Plugin activation check
 */
function recovero_check_requirements() {
    // Check PHP version
    if (version_compare(PHP_VERSION, RECOVERO_MIN_PHP_VERSION, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(sprintf(
            __('Recovero requires PHP version %s or higher. You are running version %s.', 'recovero'),
            RECOVERO_MIN_PHP_VERSION,
            PHP_VERSION
        ));
    }
    
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), RECOVERO_MIN_WP_VERSION, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(sprintf(
            __('Recovero requires WordPress version %s or higher. You are running version %s.', 'recovero'),
            RECOVERO_MIN_WP_VERSION,
            get_bloginfo('version')
        ));
    }
    
    // Check if WooCommerce is active
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Recovero requires WooCommerce to be installed and activated.', 'recovero'));
    }
}

/**
 * Get plugin instance
 */
function recovero() {
    static $instance = null;
    
    if (null === $instance) {
        $instance = new Recovero_Plugin();
    }
    
    return $instance;
}

/**
 * Main plugin class
 */
class Recovero_Plugin {
    
    private $loader;
    private $is_wc_active = false;
    
    public function __construct() {
        $this->check_woocommerce();
        $this->load_plugin();
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function check_woocommerce() {
        // Check if WooCommerce is active
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $this->is_wc_active = true;
        }
        
        // Also check for WooCommerce loaded via other means
        if (class_exists('WooCommerce')) {
            $this->is_wc_active = true;
        }
    }
    
    /**
     * Load plugin files and initialize
     */
    private function load_plugin() {
        if (!$this->is_wc_active) {
            return;
        }
        
        // Load required files
        $this->load_dependencies();
        
        // Initialize plugin
        $this->init_plugin();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        $files = [
            'includes/class-recovero-loader.php',
            'includes/class-recovero-activator.php',
            'includes/class-recovero-deactivator.php',
            'includes/helpers.php'
        ];
        
        foreach ($files as $file) {
            $filepath = RECOVERO_PATH . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            }
        }
    }
    
    /**
     * Initialize plugin
     */
    private function init_plugin() {
        if (class_exists('Recovero_Loader')) {
            $this->loader = new Recovero_Loader();
            $this->loader->run();
        }
    }
    
    /**
     * Get loader instance
     */
    public function get_loader() {
        return $this->loader;
    }
    
    /**
     * Check if WooCommerce is active
     */
    public function is_wc_active() {
        return $this->is_wc_active;
    }
}

// Activation hook
register_activation_hook(__FILE__, 'recovero_activate_plugin');
function recovero_activate_plugin() {
    // Check requirements
    recovero_check_requirements();
    
    // Run activator
    if (class_exists('Recovero_Activator')) {
        Recovero_Activator::activate();
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'recovero_deactivate_plugin');
function recovero_deactivate_plugin() {
    if (class_exists('Recovero_Deactivator')) {
        Recovero_Deactivator::deactivate();
    }
}

// Initialize plugin
add_action('plugins_loaded', 'recovero_init_plugin');
function recovero_init_plugin() {
    // Check if WooCommerce is available
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Initialize plugin
    recovero();
}

// Admin notice for WooCommerce requirement
add_action('admin_notices', 'recovero_wc_admin_notice');
function recovero_wc_admin_notice() {
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        return;
    }
    
    if (get_current_screen()->id !== 'plugins') {
        return;
    }
    
    ?>
    <div class="error">
        <p>
            <strong><?php _e('Recovero', 'recovero'); ?></strong>
            <?php _e('requires WooCommerce to be installed and activated.', 'recovero'); ?>
            <a href="<?php echo admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'); ?>">
                <?php _e('Install WooCommerce', 'recovero'); ?>
            </a>
        </p>
    </div>
    <?php
}
