<?php
if (!defined('ABSPATH')) exit;

class Recovero_Push {
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_sw']);
        add_action('wp_ajax_recovero_save_subscription', [$this, 'save_subscription']);
        add_action('wp_ajax_nopriv_recovero_save_subscription', [$this, 'save_subscription']);
    }

    public function enqueue_sw() {
        wp_enqueue_script('recovero-push', RECOVERO_URL . 'pro/assets/js/push.js', ['jquery'], RECOVERO_VERSION, true);
        wp_localize_script('recovero-push', 'recoveroPush', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('recovero_push_nonce'),
            'vapid_public' => get_option('recovero_vapid_public', '')
        ]);
        // register service worker
        add_action('wp_footer', function(){
            if (function_exists('is_user_logged_in')) {
                echo "<script>
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('" . esc_url(RECOVERO_URL . "pro/assets/js/sw.js") . "').then(function(){ console.log('sw registered'); });
                }
                </script>";
            }
        });
    }

    public function save_subscription() {
        check_ajax_referer('recovero_push_nonce', 'nonce');
        $sub = isset($_POST['subscription']) ? wp_unslash($_POST['subscription']) : '';
        $sid = isset($_POST['sid']) ? sanitize_text_field($_POST['sid']) : '';

        if (empty($sub)) wp_send_json_error('no_subscription');

        global $wpdb;
        $table = $wpdb->prefix . 'recovero_geo_data'; // reuse geo table or create separate table
        $wpdb->insert($table, [
            'ip' => recovero_get_client_ip(),
            'country' => null,
            'city' => null,
            'lat' => null,
            'lon' => null,
            'browser' => maybe_serialize($sub),
            'device' => 'push_sub',
            'last_seen' => current_time('mysql')
        ]);

        wp_send_json_success(['saved' => true]);
    }

    /**
     * stub function to send push
     * For real production, integrate web-push-php library and VAPID keys
     */
    public function send_push($subscription, $payload) {
        // placeholder: a real Web Push service call required
        return new WP_Error('not_implemented', 'Push sending not implemented. Use web-push library and VAPID keys.');
    }
}
