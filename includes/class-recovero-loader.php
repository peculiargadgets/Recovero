<?php
if (!defined('ABSPATH')) exit;

class Recovero_Loader {
    public function run() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once RECOVERO_PATH . 'includes/class-recovero-db.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-tracker.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-ajax.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-cron.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-admin.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-recovery.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-whatsapp.php';
        require_once RECOVERO_PATH . 'includes/class-recovero-analytics.php';
        require_once RECOVERO_PATH . 'includes/helpers.php';

        if (file_exists(RECOVERO_PATH . 'pro/class-recovero-pro.php')) {
            require_once RECOVERO_PATH . 'pro/class-recovero-pro.php';
        }
    }

    private function init_hooks() {
        new Recovero_Tracker();
        new Recovero_Ajax();
        new Recovero_Admin();
        new Recovero_Cron();

        if (class_exists('Recovero_Pro')) {
            new Recovero_Pro();
        }
    }
}
