<?php
if (!defined('ABSPATH')) exit;

class Recovero_Recovery_Coupon {
    public function __construct() {
        // nothing
    }

    /**
     * Create a coupon tailored to cart
     * returns coupon code
     */
    public function create_coupon_for_cart($cart) {
        if (!class_exists('WC_Coupon')) {
            return false;
        }

        $amount = floatval(get_option('recovero_coupon_amount', 10)); // fixed amount discount by default
        $code = 'RECOVERO-' . strtoupper(wp_generate_password(6, false, false));

        $coupon = new WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_amount($amount);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);
        $coupon->set_date_expires( date('Y-m-d', strtotime('+7 days')) );

        // optionally restrict to cart products — skipping complexity
        $coupon->save();

        // store meta linking to recovero
        update_post_meta($coupon->get_id(), '_recovero_coupon', '1');

        return $code;
    }

    /**
     * Try auto-apply coupon when user visits with token
     * Hook: template_redirect in core tracker's token handler could call this when token present.
     */
    public function maybe_apply_coupon_from_token($token) {
        if (!isset($_GET['recovero_token'])) return;
        $code = sanitize_text_field($_GET['coupon'] ?? '');
        if (!empty($code) && class_exists('WC_Cart')) {
            if (!WC()->cart->has_discount($code)) {
                WC()->cart->add_discount(sanitize_text_field($code));
            }
        }
    }
}
