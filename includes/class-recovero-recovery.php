<?php
if (!defined('ABSPATH')) exit;

class Recovero_Recovery {
    public function send_email($cart) {
        $token = wp_generate_password(12, false);
        $db = new Recovero_DB();
        $db->add_recovery_log([
            'cart_id' => $cart->id,
            'method' => 'email',
            'status' => 'sent',
            'token' => $token,
            'sent_at' => current_time('mysql')
        ]);

        $subject = __('We saved your cart for you 🛒', 'recovero');
        $link = add_query_arg(['recovero_token' => $token], wc_get_cart_url());
        $message = "Hi there! You left some items in your cart. Click below to complete your order:\n\n";
        $message .= "<a href='{$link}'>Recover your cart</a>";

        wp_mail($cart->email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }
}
