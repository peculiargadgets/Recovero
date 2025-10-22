<?php
/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

global $wpdb;

// Define table names
$table_names = array(
    $wpdb->prefix . 'recovero_abandoned_carts',
    $wpdb->prefix . 'recovero_recovery_logs',
    $wpdb->prefix . 'recovero_geo_data',
    $wpdb->prefix . 'recovero_license_keys'
);

// Drop tables
foreach ( $table_names as $table_name ) {
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}

// Delete options
delete_option( 'recovero_settings' );
delete_option( 'recovero_version' );
delete_option( 'recovero_license_key' );
delete_option( 'recovero_license_status' );

// Clear scheduled cron jobs
wp_clear_scheduled_hook( 'recovero_send_recovery_emails' );
wp_clear_scheduled_hook( 'recovero_cleanup_old_carts' );
wp_clear_scheduled_hook( 'recovero_send_whatsapp_notifications' );

// Delete transients
delete_transient( 'recovero_analytics_cache' );
delete_transient( 'recovero_license_check' );