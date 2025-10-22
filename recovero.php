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
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants
define('RECOVERO_VERSION', '1.0.0');
define('RECOVERO_PATH', plugin_dir_path(__FILE__));
define('RECOVERO_URL', plugin_dir_url(__FILE__));
define('RECOVERO_BASENAME', plugin_basename(__FILE__));

// Include main loader
require_once RECOVERO_PATH . 'includes/class-recovero-loader.php';
require_once RECOVERO_PATH . 'includes/class-recovero-activator.php';
require_once RECOVERO_PATH . 'includes/class-recovero-deactivator.php';

// Activation / Deactivation hooks
register_activation_hook(__FILE__, ['Recovero_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Recovero_Deactivator', 'deactivate']);

// Run plugin
function run_recovero() {
    $plugin = new Recovero_Loader();
    $plugin->run();
}
run_recovero();
