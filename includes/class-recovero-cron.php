<?php
if (!defined('ABSPATH')) exit;

class Recovero_Cron {
    public function __construct() {
        add_action('recovero_cron_hook', [$this, 'run_recovery']);
    }

    public function run_recovery() {
        $db = new Recovero_DB();
        $recovery = new Recovero_Recovery();

        $carts = $db->get_abandoned_carts();
        foreach ($carts as $cart) {
            $hours_passed = (time() - strtotime($cart->created_at)) / 3600;
            if ($hours_passed >= 1 && $hours_passed < 2) {
                $recovery->send_email($cart);
            }
        }
    }
}
