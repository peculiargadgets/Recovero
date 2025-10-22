<?php
/**
 * Recovero Activator Class
 * Handles plugin activation and database setup
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recovero_Activator {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Check if WooCommerce is active
        if (!self::is_woocommerce_active()) {
            wp_die(__('Recovero requires WooCommerce to be installed and activated.', 'recovero'));
        }
        
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Schedule cron jobs
        self::schedule_cron_jobs();
        
        // Add activation notice
        add_option('recovero_activated', true);
        
        // Log activation
        error_log('Recovero plugin activated successfully');
    }
    
    /**
     * Check if WooCommerce is active
     */
    private static function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        
        // Define table schemas
        $tables = [
            // Abandoned carts table
            "CREATE TABLE IF NOT EXISTS {$prefix}recovero_abandoned_carts (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT(20) DEFAULT NULL,
                session_id VARCHAR(255) DEFAULT NULL,
                email VARCHAR(255) DEFAULT NULL,
                phone VARCHAR(100) DEFAULT NULL,
                cart_data LONGTEXT DEFAULT NULL,
                ip VARCHAR(45) DEFAULT NULL,
                location TEXT DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'abandoned',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                customer_name VARCHAR(255) DEFAULT NULL,
                billing_address VARCHAR(255) DEFAULT NULL,
                billing_city VARCHAR(100) DEFAULT NULL,
                billing_country VARCHAR(100) DEFAULT NULL,
                billing_postcode VARCHAR(20) DEFAULT NULL,
                INDEX idx_status (status),
                INDEX idx_email (email),
                INDEX idx_created_at (created_at),
                INDEX idx_session_id (session_id),
                INDEX idx_customer_name (customer_name)
            ) $charset_collate",
            
            // Recovery logs table
            "CREATE TABLE IF NOT EXISTS {$prefix}recovero_recovery_logs (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cart_id BIGINT(20) DEFAULT NULL,
                method VARCHAR(50) DEFAULT NULL,
                status VARCHAR(50) DEFAULT NULL,
                token VARCHAR(100) DEFAULT NULL,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cart_id (cart_id),
                INDEX idx_token (token),
                INDEX idx_status (status)
            ) $charset_collate",
            
            // Geo data table
            "CREATE TABLE IF NOT EXISTS {$prefix}recovero_geo_data (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) DEFAULT NULL,
                country VARCHAR(100) DEFAULT NULL,
                city VARCHAR(100) DEFAULT NULL,
                lat VARCHAR(50) DEFAULT NULL,
                lon VARCHAR(50) DEFAULT NULL,
                browser VARCHAR(100) DEFAULT NULL,
                device VARCHAR(100) DEFAULT NULL,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip (ip),
                INDEX idx_country (country),
                INDEX idx_last_seen (last_seen)
            ) $charset_collate",
            
            // License keys table
            "CREATE TABLE IF NOT EXISTS {$prefix}recovero_license_keys (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                license_key VARCHAR(255) DEFAULT NULL,
                domain VARCHAR(255) DEFAULT NULL,
                status VARCHAR(50) DEFAULT NULL,
                expiry_date DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_license_key (license_key),
                INDEX idx_domain (domain),
                INDEX idx_status (status)
            ) $charset_collate"
        ];
        
        // Include WordPress upgrade functions
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Create tables
        foreach ($tables as $sql) {
            try {
                dbDelta($sql);
            } catch (Exception $e) {
                error_log("Recovero: Failed to create table: " . $e->getMessage());
            }
        }
        
        // Verify tables were created
        self::verify_tables_created();
    }
    
    /**
     * Verify tables were created successfully
     */
    private static function verify_tables_created() {
        global $wpdb;
        
        $tables = [
            'recovero_abandoned_carts',
            'recovero_recovery_logs',
            'recovero_geo_data',
            'recovero_license_keys'
        ];
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($result !== $table_name) {
                error_log("Recovero: Table $table_name was not created");
            }
        }
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        $default_options = [
            'recovero_email_from' => get_option('admin_email'),
            'recovero_delay_hours' => 1,
            'recovero_purge_days' => 90,
            'recovero_enable_tracking' => true,
            'recovero_enable_email_recovery' => true,
            'recovero_enable_whatsapp_recovery' => false,
            'recovero_enable_geo_tracking' => true,
            'recovero_email_subject' => __('Complete your purchase', 'recovero'),
            'recovero_version' => RECOVERO_VERSION,
        ];
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs() {
        // Schedule recovery cron job
        if (!wp_next_scheduled('recovero_cron_hook')) {
            wp_schedule_event(time(), 'hourly', 'recovero_cron_hook');
        }
        
        // Schedule cleanup cron job
        if (!wp_next_scheduled('recovero_cleanup_hook')) {
            wp_schedule_event(time(), 'daily', 'recovero_cleanup_hook');
        }
    }
    
    /**
     * Get current database version
     */
    public static function get_db_version() {
        return get_option('recovero_db_version', '1.0.0');
    }
    
    /**
     * Update database version
     */
    public static function update_db_version($version) {
        update_option('recovero_db_version', $version);
    }
    
    /**
     * Check if plugin is being activated for the first time
     */
    public static function is_first_activation() {
        return !get_option('recovero_db_version');
    }
}
