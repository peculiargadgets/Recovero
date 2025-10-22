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

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'RECOVERO_VERSION', '1.0.0' );
define( 'RECOVERO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RECOVERO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RECOVERO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function recovero_is_woocommerce_active() {
    return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function recovero_woocommerce_not_active_notice() {
    if ( ! recovero_is_woocommerce_active() ) {
        ?>
        <div class="error">
            <p><?php _e( 'Recovero requires WooCommerce to be installed and active.', 'recovero' ); ?></p>
        </div>
        <?php
    }
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once RECOVERO_PLUGIN_DIR . 'includes/class-recovero-loader.php';

/**
 * Begins execution of the plugin.
 */
function run_recovero() {
    
    // Check WooCommerce dependency
    if ( ! recovero_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'recovero_woocommerce_not_active_notice' );
        return;
    }
    
    $plugin = new Recovero_Loader();
    $plugin->run();
    
}

// Run the plugin
run_recovero();
