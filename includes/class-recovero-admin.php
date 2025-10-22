<?php
if (!defined('ABSPATH')) exit;

/**
 * Recovero Admin - basic dashboard, settings and list table
 */
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Recovero_Carts_List extends WP_List_Table {
    private $db;

    public function __construct($db) {
        parent::__construct([
            'singular' => __('Recovero Cart', 'recovero'),
            'plural'   => __('Recovero Carts', 'recovero'),
            'ajax'     => false
        ]);
        $this->db = $db;
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => 'ID',
            'email' => __('Email', 'recovero'),
            'items' => __('Items', 'recovero'),
            'subtotal' => __('Subtotal', 'recovero'),
            'ip' => __('IP', 'recovero'),
            'created_at' => __('Created At', 'recovero'),
            'status' => __('Status', 'recovero'),
            'actions' => __('Actions', 'recovero')
        ];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="cart_ids[]" value="%d" />', $item->id);
    }

    public function column_items($item) {
        $items = maybe_unserialize($item->cart_data);
        if (is_array($items)) {
            $out = [];
            foreach ($items as $ci) {
                $name = isset($ci['data']) && is_object($ci['data']) ? $ci['data']->get_name() : (isset($ci['product_id']) ? 'Product ' . esc_html($ci['product_id']) : 'Item');
                $qty = isset($ci['quantity']) ? intval($ci['quantity']) : (isset($ci['qty']) ? intval($ci['qty']) : 1);
                $out[] = esc_html($name) . ' x ' . $qty;
            }
            return '<small>' . implode(', ', $out) . '</small>';
        }
        return '-';
    }

    public function column_actions($item) {
        $resend = wp_nonce_url(admin_url('admin.php?page=recovero&action=resend&cart=' . $item->id), 'recovero_admin_action', 'recovero_nonce');
        $export = wp_nonce_url(admin_url('admin.php?page=recovero&action=export&cart=' . $item->id), 'recovero_admin_action', 'recovero_nonce');
        return sprintf('<a class="button" href="%s">%s</a> <a class="button" href="%s">%s</a>',
            esc_url($resend), __('Resend Email', 'recovero'),
            esc_url($export), __('Export', 'recovero')
        );
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];

        // fetch data
        global $wpdb;
        $table = $wpdb->prefix . 'recovero_abandoned_carts';
        $items = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200");

        $this->items = $items;
    }
}

class Recovero_Admin {
    private $db;

    public function __construct() {
        $this->db = new Recovero_DB();

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_recovero_settings_save', [$this, 'save_settings']);
        add_action('admin_init', [$this, 'maybe_handle_actions']);
    }

    public function register_menu() {
        add_menu_page('Recovero', 'Recovero', 'manage_woocommerce', 'recovero', [$this, 'page_dashboard'], 'dashicons-analytics', 56);
        add_submenu_page('recovero', 'Settings', 'Settings', 'manage_woocommerce', 'recovero-settings', [$this, 'page_settings']);
        add_submenu_page('recovero', 'Logs', 'Logs', 'manage_woocommerce', 'recovero-logs', [$this, 'page_logs']);
    }

    public function page_dashboard() {
        ?>
        <div class="wrap recovero-admin">
            <h1><?php esc_html_e('Recovero Dashboard', 'recovero'); ?></h1>
            <p><?php esc_html_e('Quick overview of abandoned carts and recent recovery actions.', 'recovero'); ?></p>

            <h2><?php esc_html_e('Quick Actions', 'recovero'); ?></h2>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=recovero-logs')); ?>"><?php esc_html_e('View Recovery Logs', 'recovero'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=recovero-settings')); ?>"><?php esc_html_e('Settings', 'recovero'); ?></a>
            </p>

            <h2><?php esc_html_e('Recent Abandoned Carts', 'recovero'); ?></h2>
            <?php
            $list = new Recovero_Carts_List($this->db);
            $list->prepare_items();
            $list->display();
            ?>
        </div>
        <?php
    }

    public function page_settings() {
        $email_from = get_option('recovero_email_from', get_option('admin_email'));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Recovero Settings', 'recovero'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('recovero_settings_save'); ?>
                <input type="hidden" name="action" value="recovero_settings_save">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('From Email', 'recovero'); ?></th>
                        <td><input type="email" name="recovero_email_from" class="regular-text" value="<?php echo esc_attr($email_from); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Reminder Delay (hours)', 'recovero'); ?></th>
                        <td><input type="number" name="recovero_delay_hours" value="<?php echo esc_attr(get_option('recovero_delay_hours', 1)); ?>" min="1" max="168"></td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'recovero')); ?>
            </form>
            <hr/>
            <h2><?php esc_html_e('Tools', 'recovero'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=recovero&action=purge')); ?>">
                <?php wp_nonce_field('recovero_admin_action', 'recovero_nonce'); ?>
                <input type="submit" class="button button-secondary" value="<?php esc_attr_e('Purge Old Data', 'recovero'); ?>">
            </form>
        </div>
        <?php
    }

    public function page_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'recovero_recovery_logs';
        $logs = $wpdb->get_results("SELECT * FROM {$table} ORDER BY sent_at DESC LIMIT 200");
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Recovero Recovery Logs', 'recovero'); ?></h1>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'recovero'); ?></th>
                        <th><?php esc_html_e('Cart ID', 'recovero'); ?></th>
                        <th><?php esc_html_e('Method', 'recovero'); ?></th>
                        <th><?php esc_html_e('Status', 'recovero'); ?></th>
                        <th><?php esc_html_e('Token', 'recovero'); ?></th>
                        <th><?php esc_html_e('Sent At', 'recovero'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><?php echo esc_html($log->cart_id); ?></td>
                        <td><?php echo esc_html($log->method); ?></td>
                        <td><?php echo esc_html($log->status); ?></td>
                        <td><?php echo esc_html($log->token); ?></td>
                        <td><?php echo esc_html($log->sent_at); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function save_settings() {
        if (!current_user_can('manage_woocommerce')) wp_die(__('Unauthorized', 'recovero'));
        check_admin_referer('recovero_settings_save');

        $email = isset($_POST['recovero_email_from']) ? sanitize_email($_POST['recovero_email_from']) : '';
        update_option('recovero_email_from', $email);

        $delay = isset($_POST['recovero_delay_hours']) ? absint($_POST['recovero_delay_hours']) : 1;
        update_option('recovero_delay_hours', $delay);

        wp_safe_redirect(admin_url('admin.php?page=recovero-settings&updated=1'));
        exit;
    }

    public function maybe_handle_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'recovero') return;
        if (!isset($_GET['action'])) return;

        if (!isset($_REQUEST['recovero_nonce']) || !wp_verify_nonce($_REQUEST['recovero_nonce'], 'recovero_admin_action')) return;

        $action = sanitize_text_field($_GET['action']);
        if ($action === 'resend' && isset($_GET['cart'])) {
            $cart_id = absint($_GET['cart']);
            $this->resend_email($cart_id);
            wp_safe_redirect(admin_url('admin.php?page=recovero&resent=1'));
            exit;
        }

        if ($action === 'export' && isset($_GET['cart'])) {
            $cart_id = absint($_GET['cart']);
            $this->export_cart($cart_id);
            exit;
        }

        if ($action === 'purge') {
            $this->purge_old();
            wp_safe_redirect(admin_url('admin.php?page=recovero-settings&purged=1'));
            exit;
        }
    }

    private function resend_email($cart_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'recovero_abandoned_carts';
        $cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $cart_id));
        if ($cart) {
            $recovery = new Recovero_Recovery();
            $recovery->send_email($cart);
        }
    }

    private function export_cart($cart_id) {
        if (!current_user_can('manage_woocommerce')) wp_die(__('Unauthorized', 'recovero'));
        global $wpdb;
        $table = $wpdb->prefix . 'recovero_abandoned_carts';
        $cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $cart_id));
        if (!$cart) wp_die(__('Cart not found', 'recovero'));

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=recovero-cart-' . $cart_id . '.json');
        echo json_encode($cart);
        exit;
    }

    private function purge_old() {
        global $wpdb;
        $days = absint(get_option('recovero_purge_days', 90));
        $threshold = date('Y-m-d H:i:s', strtotime("-{$days} days", current_time('timestamp')));
        $table = $wpdb->prefix . 'recovero_abandoned_carts';
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $threshold));
        // also cleanup logs and geo data optionally
    }
}
