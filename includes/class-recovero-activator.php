<?php
if (!defined('ABSPATH')) exit;

class Recovero_Activator {
    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            "CREATE TABLE {$wpdb->prefix}recovero_abandoned_carts (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT(20),
                session_id VARCHAR(255),
                email VARCHAR(255),
                phone VARCHAR(100),
                cart_data LONGTEXT,
                ip VARCHAR(45),
                location TEXT,
                status VARCHAR(20) DEFAULT 'abandoned',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",
            
            "CREATE TABLE {$wpdb->prefix}recovero_recovery_logs (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cart_id BIGINT(20),
                method VARCHAR(50),
                status VARCHAR(50),
                token VARCHAR(100),
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}recovero_geo_data (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45),
                country VARCHAR(100),
                city VARCHAR(100),
                lat VARCHAR(50),
                lon VARCHAR(50),
                browser VARCHAR(100),
                device VARCHAR(100),
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}recovero_license_keys (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                license_key VARCHAR(255),
                domain VARCHAR(255),
                status VARCHAR(50),
                expiry_date DATETIME
            ) $charset_collate;"
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        if (!wp_next_scheduled('recovero_cron_hook')) {
            wp_schedule_event(time(), 'hourly', 'recovero_cron_hook');
        }
    }
}
