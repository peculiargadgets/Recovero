<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('recovero_sanitize_text')) {
    function recovero_sanitize_text($text) {
        return sanitize_text_field(wp_unslash($text));
    }
}

if (!function_exists('recovero_generate_token')) {
    function recovero_generate_token($len = 40) {
        return bin2hex(random_bytes(max(8, intval($len/2))));
    }
}

if (!function_exists('recovero_get_client_ip')) {
    function recovero_get_client_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return sanitize_text_field(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (!empty($_SERVER['REMOTE_ADDR'])) return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        return '';
    }
}
