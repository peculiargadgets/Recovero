<?php
if (!defined('ABSPATH')) exit;

class Recovero_Analytics {
    public function get_totals() {
        global $wpdb;
        $carts_table = $wpdb->prefix . 'recovero_abandoned_carts';
        $logs_table = $wpdb->prefix . 'recovero_recovery_logs';

        $total = intval($wpdb->get_var("SELECT COUNT(*) FROM {$carts_table}"));
        $abandoned = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$carts_table} WHERE status = %s", 'abandoned')));
        $recovered = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$carts_table} WHERE status = %s", 'recovered')));
        $rate = $total > 0 ? round(($recovered / $total) * 100, 2) : 0;

        return [
            'total' => $total,
            'abandoned' => $abandoned,
            'recovered' => $recovered,
            'rate' => $rate
        ];
    }

    public function get_device_stats($limit = 10) {
        global $wpdb;
        $geo_table = $wpdb->prefix . 'recovero_geo_data';
        $rows = $wpdb->get_results("SELECT device, COUNT(*) as cnt FROM {$geo_table} GROUP BY device ORDER BY cnt DESC LIMIT {$limit}");
        return $rows;
    }

    public function get_country_stats($limit = 20) {
        global $wpdb;
        $geo_table = $wpdb->prefix . 'recovero_geo_data';
        $rows = $wpdb->get_results("SELECT country, COUNT(*) as cnt FROM {$geo_table} GROUP BY country ORDER BY cnt DESC LIMIT {$limit}");
        return $rows;
    }
}
