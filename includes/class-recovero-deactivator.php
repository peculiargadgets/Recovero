<?php
/**
 * Recovero Deactivator Class
 * Handles plugin deactivation and cleanup
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recovero_Deactivator {
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Unschedule cron jobs
        self::unschedule_cron_jobs();
        
        // Clear scheduled hooks
        self::clear_scheduled_hooks();
        
        // Clear temporary data
        self::clear_temporary_data();
        
        // Add deactivation notice
        add_option('recovero_deactivated', true);
        
        // Log deactivation
        error_log('Recovero plugin deactivated');
    }
    
    /**
     * Unschedule cron jobs
     */
    private static function unschedule_cron_jobs() {
        $cron_hooks = [
            'recovero_cron_hook',
            'recovero_cleanup_hook'
        ];
        
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
        
        // Clear all scheduled events for this plugin
        wp_clear_scheduled_hook('recovero_cron_hook');
        wp_clear_scheduled_hook('recovero_cleanup_hook');
    }
    
    /**
     * Clear scheduled hooks
     */
    private static function clear_scheduled_hooks() {
        // Remove any scheduled hooks
        $hooks = [
            'recovero_hourly_check',
            'recovero_daily_cleanup',
            'recovero_weekly_report'
        ];
        
        foreach ($hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
    
    /**
     * Clear temporary data
     */
    private static function clear_temporary_data() {
        // Clear transient data
        $transients = [
            'recovero_stats_cache',
            'recovero_analytics_cache',
            'recovero_license_cache'
        ];
        
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
        
        // Clear any temporary options
        $temp_options = [
            'recovero_activation_error',
            'recovero_update_notice',
            'recovero_temp_data'
        ];
        
        foreach ($temp_options as $option) {
            delete_option($option);
        }
    }
    
    /**
     * Clean up old data (optional)
     */
    public static function cleanup_old_data() {
        global $wpdb;
        
        $days = absint(get_option('recovero_purge_days', 90));
        $threshold = date('Y-m-d H:i:s', strtotime("-{$days} days", current_time('timestamp')));
        
        // Clean old abandoned carts
        $carts_table = $wpdb->prefix . 'recovero_abandoned_carts';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$carts_table} WHERE created_at < %s",
            $threshold
        ));
        
        // Clean old recovery logs
        $logs_table = $wpdb->prefix . 'recovero_recovery_logs';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$logs_table} WHERE sent_at < %s",
            $threshold
        ));
        
        // Clean old geo data
        $geo_table = $wpdb->prefix . 'recovero_geo_data';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$geo_table} WHERE last_seen < %s",
            $threshold
        ));
    }
    
    /**
     * Get cleanup statistics
     */
    public static function get_cleanup_stats() {
        global $wpdb;
        
        $stats = [];
        
        // Count abandoned carts
        $carts_table = $wpdb->prefix . 'recovero_abandoned_carts';
        $stats['carts_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$carts_table}");
        
        // Count recovery logs
        $logs_table = $wpdb->prefix . 'recovero_recovery_logs';
        $stats['logs_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table}");
        
        // Count geo data
        $geo_table = $wpdb->prefix . 'recovero_geo_data';
        $stats['geo_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$geo_table}");
        
        return $stats;
    }
    
    /**
     * Check if plugin is being deactivated
     */
    public static function is_deactivating() {
        return isset($_GET['action']) && $_GET['action'] === 'deactivate' && 
               isset($_GET['plugin']) && $_GET['plugin'] === RECOVERO_BASENAME;
    }
}
