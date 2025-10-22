<?php
if (!defined('ABSPATH')) exit;

class Recovero_Ajax {
    public function __construct() {
        add_action('wp_ajax_recovero_save_geo', [$this, 'save_geo']);
        add_action('wp_ajax_nopriv_recovero_save_geo', [$this, 'save_geo']);
    }

    public function save_geo() {
        check_ajax_referer('recovero_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'recovero_geo_data';

        $ip = $_SERVER['REMOTE_ADDR'];
        $lat = sanitize_text_field($_POST['lat'] ?? '');
        $lon = sanitize_text_field($_POST['lon'] ?? '');
        $browser = sanitize_text_field($_POST['browser'] ?? '');
        $device = sanitize_text_field($_POST['device'] ?? '');

        $wpdb->insert($table, [
            'ip' => $ip,
            'lat' => $lat,
            'lon' => $lon,
            'browser' => $browser,
            'device' => $device,
            'last_seen' => current_time('mysql')
        ]);

        wp_send_json_success(['message' => 'Geo data saved']);
    }
}
