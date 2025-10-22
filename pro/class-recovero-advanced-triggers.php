<?php
if (!defined('ABSPATH')) exit;

class Recovero_Advanced_Triggers {
    public function __construct() {
        add_action('recovero_cron_hook', [$this, 'process_pro_reminders'], 20);
    }

    public function process_pro_reminders() {
        global $wpdb;
        $carts_table = $wpdb->prefix . 'recovero_abandoned_carts';
        $logs_table = $wpdb->prefix . 'recovero_recovery_logs';

        $carts = $wpdb->get_results("SELECT * FROM {$carts_table} WHERE status = 'abandoned' LIMIT 100");

        foreach ($carts as $cart) {
            $latest = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$logs_table} WHERE cart_id = %d ORDER BY sent_at DESC LIMIT 1", $cart->id));
            $stage = 0;
            $last_sent = 0;
            if ($latest) {
                if ($latest->method === 'email' && $latest->status === 'sent') $stage = 1;
                if ($latest->method === 'whatsapp' && $latest->status === 'sent') $stage = 2;
                if ($latest->method === 'coupon' && $latest->status === 'sent') $stage = 3;
                $last_sent = strtotime($latest->sent_at);
            } else {
                $stage = 0;
                $last_sent = strtotime($cart->created_at);
            }

            $now = time();
            $hours_since = ($now - $last_sent) / 3600;

            $email_delay = floatval(get_option('recovero_delay_hours', 1));
            $whatsapp_delay = floatval(get_option('recovero_whatsapp_delay', 6));
            $coupon_delay = floatval(get_option('recovero_coupon_delay', 24));

            if ($stage === 0 && $hours_since >= $email_delay) {
                $recovery = new Recovero_Recovery();
                $recovery->send_email($cart);
                $wpdb->insert($logs_table, [
                    'cart_id' => $cart->id,
                    'method' => 'email',
                    'status' => 'sent',
                    'token' => recovero_generate_token(24),
                    'sent_at' => current_time('mysql')
                ]);
            } elseif ($stage === 1 && $hours_since >= $whatsapp_delay) {
                $phone = $cart->phone;
                if (!empty($phone) && get_option('recovero_whatsapp_enable')) {
                    $wa = new Recovero_WhatsApp();
                    $msg = $this->build_whatsapp_message($cart);
                    $res = $wa->send_message($phone, $msg);
                    $wpdb->insert($logs_table, [
                        'cart_id' => $cart->id,
                        'method' => 'whatsapp',
                        'status' => is_wp_error($res) ? 'failed' : 'sent',
                        'token' => '',
                        'sent_at' => current_time('mysql')
                    ]);
                }
            } elseif ($stage === 2 && $hours_since >= $coupon_delay) {
                $coupon = new Recovero_Recovery_Coupon();
                $code = $coupon->create_coupon_for_cart($cart);
                $wpdb->insert($logs_table, [
                    'cart_id' => $cart->id,
                    'method' => 'coupon',
                    'status' => 'sent',
                    'token' => $code,
                    'sent_at' => current_time('mysql')
                ]);
                $this->send_coupon_email($cart, $code);
            }
        }
    }

    private function build_whatsapp_message($cart) {
        $items = maybe_unserialize($cart->cart_data);
        $list = [];
        if (is_array($items)) {
            foreach ($items as $i) {
                $name = isset($i['data']) && is_object($i['data']) ? $i['data']->get_name() : (isset($i['product_id']) ? 'Product ' . $i['product_id'] : 'Item');
                $qty = isset($i['quantity']) ? $i['quantity'] : 1;
                $list[] = $name . ' x ' . $qty;
            }
        }
        $link = add_query_arg('recovero_token', recovero_generate_token(24), wc_get_cart_url());
        return "You left items in your cart: " . implode(', ', $list) . ". Complete your order: " . $link;
    }

    private function send_coupon_email($cart, $code) {
        $subject = __('Here is a special coupon for you', 'recovero');
        $link = add_query_arg('recovero_token', recovero_generate_token(24), wc_get_cart_url());
        $message = "<p>We miss you — here's a coupon code: <strong>{$code}</strong></p>";
        $message .= '<p><a href="'.esc_url($link).'">Restore your cart & apply coupon</a></p>';
        wp_mail($cart->email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }
}
