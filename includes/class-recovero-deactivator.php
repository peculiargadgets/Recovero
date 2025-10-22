<?php
if (!defined('ABSPATH')) exit;

class Recovero_Deactivator {
    public static function deactivate() {
        $timestamp = wp_next_scheduled('recovero_cron_hook');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'recovero_cron_hook');
        }
    }
}
