<?php
if (!defined('ABSPATH')) exit;

class Recovero_DB {
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    // Abandoned cart insert/update
    public function save_cart($data) {
        $table = $this->wpdb->prefix . 'recovero_abandoned_carts';
        $existing = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %s",
            $data['session_id']
        ));

        if ($existing) {
            $this->wpdb->update($table, $data, ['session_id' => $data['session_id']]);
        } else {
            $this->wpdb->insert($table, $data);
        }
    }

    // Get all abandoned carts
    public function get_abandoned_carts() {
        $table = $this->wpdb->prefix . 'recovero_abandoned_carts';
        return $this->wpdb->get_results("SELECT * FROM $table WHERE status = 'abandoned'");
    }

    // Mark cart as recovered
    public function mark_recovered($token) {
        $logs = $this->wpdb->prefix . 'recovero_recovery_logs';
        $cart = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $logs WHERE token = %s", $token));
        if ($cart) {
            $this->wpdb->update(
                $this->wpdb->prefix . 'recovero_abandoned_carts',
                ['status' => 'recovered'],
                ['id' => $cart->cart_id]
            );
        }
    }

    // Save recovery log
    public function add_recovery_log($data) {
        $table = $this->wpdb->prefix . 'recovero_recovery_logs';
        $this->wpdb->insert($table, $data);
    }
}
