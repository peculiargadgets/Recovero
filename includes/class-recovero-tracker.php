<?php
if (!defined('ABSPATH')) exit;

class Recovero_Tracker {
    private $db;

    public function __construct() {
        $this->db = new Recovero_DB();
        add_action('woocommerce_cart_updated', [$this, 'track_cart']);
        add_action('wp_logout', [$this, 'clear_session']);
    }

    public function track_cart() {
        if (!WC()->cart || WC()->cart->is_empty()) return;

        $user_id = get_current_user_id();
        $session_id = WC()->session->get_customer_id() ?: session_id();

        $email = '';
        if ($user_id) {
            $user = get_userdata($user_id);
            $email = $user ? $user->user_email : '';
        } elseif (!empty($_POST['billing_email'])) {
            $email = sanitize_email($_POST['billing_email']);
        }

        $cart_data = WC()->cart->get_cart();
        $ip = $_SERVER['REMOTE_ADDR'];

        $this->db->save_cart([
            'user_id' => $user_id,
            'session_id' => $session_id,
            'email' => $email,
            'cart_data' => maybe_serialize($cart_data),
            'ip' => $ip,
            'created_at' => current_time('mysql')
        ]);
    }

    public function clear_session() {
        WC()->session->__unset('recovero_tracking');
    }
}
