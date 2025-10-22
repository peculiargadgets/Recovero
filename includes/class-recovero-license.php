<?php
if (!defined('ABSPATH')) exit;

class Recovero_License {
    private $option_key = 'recovero_license';
    private $api_url = 'https://your-license-server.example/api/verify'; // CHANGE to your license server

    public function __construct() {
        // nothing for now
    }

    /**
     * Activate license: contact remote server, store status
     */
    public function activate($key) {
        $resp = $this->remote_call('activate', $key);
        if (is_wp_error($resp)) return $resp;

        if (!empty($resp['success'])) {
            update_option($this->option_key, [
                'key' => $key,
                'status' => 'active',
                'data' => $resp,
                'last_checked' => current_time('mysql')
            ]);
            return true;
        }

        return new WP_Error('invalid_license', isset($resp['message']) ? $resp['message'] : 'License invalid');
    }

    public function deactivate($key) {
        $resp = $this->remote_call('deactivate', $key);
        update_option($this->option_key, [
            'key' => $key,
            'status' => 'inactive',
            'data' => $resp,
            'last_checked' => current_time('mysql')
        ]);
        return true;
    }

    public function is_valid() {
        $opt = get_option($this->option_key, []);
        if (empty($opt['key']) || empty($opt['status'])) return false;
        if ($opt['status'] === 'active') {
            // optionally re-check every 24 hours
            $last = isset($opt['last_checked']) ? strtotime($opt['last_checked']) : 0;
            if ( time() - $last > 24 * HOUR_IN_SECONDS ) {
                $this->verify($opt['key']);
                $opt = get_option($this->option_key, []);
            }
            return isset($opt['status']) && $opt['status'] === 'active';
        }
        return false;
    }

    public function verify($key) {
        $resp = $this->remote_call('verify', $key);
        if (is_wp_error($resp)) return $resp;
        $status = (!empty($resp['success']) && $resp['valid']) ? 'active' : 'inactive';
        update_option($this->option_key, [
            'key' => $key,
            'status' => $status,
            'data' => $resp,
            'last_checked' => current_time('mysql')
        ]);
        return $resp;
    }

    private function remote_call($action, $key) {
        // for demo, we send domain and action
        $body = [
            'action' => $action,
            'license_key' => $key,
            'domain' => parse_url(home_url(), PHP_URL_HOST)
        ];

        $args = [
            'body' => $body,
            'timeout' => 15
        ];

        // wp_remote_post to license server
        $res = wp_remote_post($this->api_url, $args);
        if (is_wp_error($res)) return $res;
        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $json = json_decode($body, true);
        if ($code !== 200 || !is_array($json)) {
            return new WP_Error('license_error', __('License server error', 'recovero'));
        }
        return $json;
    }

    public function get_option() {
        return get_option($this->option_key, []);
    }
}
