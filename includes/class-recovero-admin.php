<?php
/**
 * Recovero Admin Class
 * Handles admin dashboard and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load WordPress list table class
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Recovero Carts List Table
 */
class Recovero_Carts_List extends WP_List_Table {
    
    private $db;
    private $per_page = 20;
    
    /**
     * Constructor
     */
    public function __construct($db) {
        parent::__construct([
            'singular' => __('Recovero Cart', 'recovero'),
            'plural'   => __('Recovero Carts', 'recovero'),
            'ajax'     => false
        ]);
        
        $this->db = $db;
    }
    
    /**
     * Get columns
     */
    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'recovero'),
            'email' => __('Email', 'recovero'),
            'items' => __('Items', 'recovero'),
            'total' => __('Total', 'recovero'),
            'status' => __('Status', 'recovero'),
            'created_at' => __('Created', 'recovero'),
            'actions' => __('Actions', 'recovero')
        ];
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return [
            'id' => ['id', false],
            'email' => ['email', false],
            'created_at' => ['created_at', true],
            'status' => ['status', false]
        ];
    }
    
    /**
     * Column default
     */
    public function column_default($item, $column_name) {
        return isset($item->$column_name) ? $item->$column_name : '';
    }
    
    /**
     * Column checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="cart_ids[]" value="%d" />',
            $item->id
        );
    }
    
    /**
     * Column email
     */
    public function column_email($item) {
        $email = $item->email;
        if (empty($email)) {
            $email = '<em>' . __('Guest', 'recovero') . '</em>';
        }
        
        return $email;
    }
    
    /**
     * Column items
     */
    public function column_items($item) {
        $cart_data = maybe_unserialize($item->cart_data);
        
        if (!is_array($cart_data) || empty($cart_data)) {
            return '<em>' . __('Empty', 'recovero') . '</em>';
        }
        
        $items = [];
        foreach ($cart_data as $cart_item) {
            $name = isset($cart_item['name']) ? $cart_item['name'] : __('Product', 'recovero');
            $quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 1;
            $items[] = esc_html($name) . ' x' . $quantity;
        }
        
        return '<small>' . implode(', ', array_slice($items, 0, 3)) . (count($items) > 3 ? '...' : '') . '</small>';
    }
    
    /**
     * Column total
     */
    public function column_total($item) {
        $cart_data = maybe_unserialize($item->cart_data);
        
        if (!is_array($cart_data) || empty($cart_data)) {
            return wc_price(0);
        }
        
        $total = 0;
        foreach ($cart_data as $cart_item) {
            $price = isset($cart_item['price']) ? floatval($cart_item['price']) : 0;
            $quantity = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 1;
            $total += $price * $quantity;
        }
        
        return wc_price($total);
    }
    
    /**
     * Column status
     */
    public function column_status($item) {
        $status = $item->status;
        $status_colors = [
            'abandoned' => '#dc3545',
            'checkout' => '#ffc107',
            'recovered' => '#28a745',
            'completed' => '#17a2b8'
        ];
        
        $color = isset($status_colors[$status]) ? $status_colors[$status] : '#6c757d';
        $label = ucfirst($status);
        
        return sprintf(
            '<span style="color: %s; font-weight: bold;">%s</span>',
            $color,
            $label
        );
    }
    
    /**
     * Column created_at
     */
    public function column_created_at($item) {
        $time = strtotime($item->created_at);
        $formatted = date_i18n(get_option('date_format'), $time);
        $time_ago = human_time_diff($time, current_time('timestamp')) . ' ' . __('ago', 'recovero');
        
        return sprintf(
            '%s<br><small style="color: #666;">%s</small>',
            $formatted,
            $time_ago
        );
    }
    
    /**
     * Column actions
     */
    public function column_actions($item) {
        $actions = [];
        
        // Resend email action
        $resend_url = wp_nonce_url(
            admin_url('admin-ajax.php?action=recovero_resend_email&cart_id=' . $item->id),
            'recovero_admin_nonce'
        );
        $actions[] = sprintf(
            '<a href="%s" class="button button-small" onclick="recoveroResendEmail(%d); return false;">%s</a>',
            esc_url($resend_url),
            $item->id,
            __('Resend Email', 'recovero')
        );
        
        // Export action
        $export_url = wp_nonce_url(
            admin_url('admin-ajax.php?action=recovero_export_cart&cart_id=' . $item->id),
            'recovero_admin_nonce'
        );
        $actions[] = sprintf(
            '<a href="%s" class="button button-small" onclick="recoveroExportCart(%d); return false;">%s</a>',
            esc_url($export_url),
            $item->id,
            __('Export', 'recovero')
        );
        
        // Delete action
        $delete_url = wp_nonce_url(
            admin_url('admin-ajax.php?action=recovero_delete_cart&cart_id=' . $item->id),
            'recovero_admin_nonce'
        );
        $actions[] = sprintf(
            '<a href="%s" class="button button-small" onclick="recoveroDeleteCart(%d); return false;" style="color: #dc3545;">%s</a>',
            esc_url($delete_url),
            $item->id,
            __('Delete', 'recovero')
        );
        
        return implode(' ', $actions);
    }
    
    /**
     * Prepare items
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        // Handle bulk actions
        $this->process_bulk_action();
        
        // Get data
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $this->per_page;
        
        $total_items = $this->get_total_items();
        $this->items = $this->get_items($offset, $this->per_page);
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $this->per_page,
            'total_pages' => ceil($total_items / $this->per_page)
        ]);
    }
    
    /**
     * Get total items
     */
    private function get_total_items() {
        global $wpdb;
        
        $table = $this->db->get_table('carts');
        
        return $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }
    
    /**
     * Get items
     */
    private function get_items($offset, $per_page) {
        global $wpdb;
        
        $table = $this->db->get_table('carts');
        
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        
        if (!in_array(strtolower($order), ['asc', 'desc'])) {
            $order = 'DESC';
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
    }
    
    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        return [
            'bulk_resend' => __('Resend Emails', 'recovero'),
            'bulk_delete' => __('Delete', 'recovero'),
            'bulk_mark_recovered' => __('Mark as Recovered', 'recovero')
        ];
    }
    
    /**
     * Process bulk action
     */
    public function process_bulk_action() {
        if (!isset($_POST['action']) && !isset($_POST['action2'])) {
            return;
        }
        
        $action = isset($_POST['action']) ? $_POST['action'] : $_POST['action2'];
        $cart_ids = isset($_POST['cart_ids']) ? array_map('absint', $_POST['cart_ids']) : [];
        
        if (empty($cart_ids) || empty($action)) {
            return;
        }
        
        // Handle bulk actions via AJAX
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var cartIds = <?php echo json_encode($cart_ids); ?>;
            var action = '<?php echo esc_js($action); ?>';
            
            $.post(ajaxurl, {
                action: 'recovero_bulk_action',
                bulk_action: action,
                cart_ids: cartIds,
                nonce: '<?php echo wp_create_nonce("recovero_admin_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        });
        </script>
        <?php
    }
}

/**
 * Main Admin Class
 */
class Recovero_Admin {
    
    private $db;
    private $plugin_path;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Recovero_DB();
        $this->plugin_path = RECOVERO_PATH;
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', [$this, 'register_menu']);
        
        // Settings
        add_action('admin_post_recovero_settings_save', [$this, 'save_settings']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // Admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_recovero_get_stats', [$this, 'get_stats']);
        add_action('wp_ajax_recovero_send_test_email', [$this, 'send_test_email']);
    }
    
    /**
     * Register admin menu
     */
    public function register_menu() {
        // Main menu
        add_menu_page(
            __('Recovero', 'recovero'),
            __('Recovero', 'recovero'),
            'manage_woocommerce',
            'recovero',
            [$this, 'page_dashboard'],
            'dashicons-analytics',
            56
        );
        
        // Submenus
        add_submenu_page(
            'recovero',
            __('Dashboard', 'recovero'),
            __('Dashboard', 'recovero'),
            'manage_woocommerce',
            'recovero',
            [$this, 'page_dashboard']
        );
        
        add_submenu_page(
            'recovero',
            __('Carts', 'recovero'),
            __('Carts', 'recovero'),
            'manage_woocommerce',
            'recovero-carts',
            [$this, 'page_carts']
        );
        
        add_submenu_page(
            'recovero',
            __('Analytics', 'recovero'),
            __('Analytics', 'recovero'),
            'manage_woocommerce',
            'recovero-analytics',
            [$this, 'page_analytics']
        );
        
        add_submenu_page(
            'recovero',
            __('Settings', 'recovero'),
            __('Settings', 'recovero'),
            'manage_woocommerce',
            'recovero-settings',
            [$this, 'page_settings']
        );
    }
    
    /**
     * Dashboard page
     */
    public function page_dashboard() {
        include_once $this->plugin_path . 'assets/views/admin-dashboard.php';
    }
    
    /**
     * Carts page
     */
    public function page_carts() {
        include_once $this->plugin_path . 'assets/views/admin-carts.php';
    }
    
    /**
     * Analytics page
     */
    public function page_analytics() {
        include_once $this->plugin_path . 'assets/views/admin-analytics.php';
    }
    
    /**
     * Settings page
     */
    public function page_settings() {
        include_once $this->plugin_path . 'assets/views/admin-settings.php';
    }
    
    /**
     * Save settings
     */
    public function save_settings() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized', 'recovero'));
        }
        
        check_admin_referer('recovero_settings_save');
        
        $settings = [
            'recovero_email_from' => sanitize_email($_POST['recovero_email_from'] ?? ''),
            'recovero_delay_hours' => absint($_POST['recovero_delay_hours'] ?? 1),
            'recovero_purge_days' => absint($_POST['recovero_purge_days'] ?? 90),
            'recovero_enable_tracking' => isset($_POST['recovero_enable_tracking']),
            'recovero_enable_email_recovery' => isset($_POST['recovero_enable_email_recovery']),
            'recovero_enable_whatsapp_recovery' => isset($_POST['recovero_enable_whatsapp_recovery']),
            'recovero_enable_geo_tracking' => isset($_POST['recovero_enable_geo_tracking']),
            'recovero_email_subject' => sanitize_text_field($_POST['recovero_email_subject'] ?? ''),
            'recovero_max_cart_age' => absint($_POST['recovero_max_cart_age'] ?? 168)
        ];
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        wp_safe_redirect(admin_url('admin.php?page=recovero-settings&updated=1'));
        exit;
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        if (isset($_GET['updated'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved successfully!', 'recovero'); ?></p>
            </div>
            <?php
        }
        
        if (isset($_GET['activated'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Recovero plugin activated successfully!', 'recovero'); ?></p>
            </div>
            <?php
        }
        
        // Check if WooCommerce is active
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('Recovero', 'recovero'); ?></strong>
                    <?php _e('requires WooCommerce to be installed and activated.', 'recovero'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'recovero') === false) {
            return;
        }
        
        wp_enqueue_script('recovero-admin', RECOVERO_URL . 'assets/js/admin.js', ['jquery'], RECOVERO_VERSION, true);
        wp_enqueue_style('recovero-admin', RECOVERO_URL . 'assets/css/admin.css', [], RECOVERO_VERSION);
        
        wp_localize_script('recovero-admin', 'recovero_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('recovero_admin_nonce'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this cart?', 'recovero'),
                'confirm_bulk_delete' => __('Are you sure you want to delete the selected carts?', 'recovero'),
                'sending' => __('Sending...', 'recovero'),
                'sent' => __('Sent', 'recovero'),
                'error' => __('Error', 'recovero')
            ]
        ]);
    }
    
    /**
     * Get stats via AJAX
     */
    public function get_stats() {
        check_ajax_referer('recovero_admin_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized', 'recovero'));
        }
        
        $stats = $this->db->get_statistics();
        wp_send_json_success($stats);
    }
    
    /**
     * Send test email via AJAX
     */
    public function send_test_email() {
        check_ajax_referer('recovero_admin_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized', 'recovero'));
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email)) {
            wp_send_json_error(['message' => __('Please provide a valid email address', 'recovero')]);
        }
        
        if (class_exists('Recovero_Recovery')) {
            $recovery = new Recovero_Recovery();
            $result = $recovery->send_test_email($email);
            
            if ($result) {
                wp_send_json_success(['message' => __('Test email sent successfully!', 'recovero')]);
            } else {
                wp_send_json_error(['message' => __('Failed to send test email', 'recovero')]);
            }
        } else {
            wp_send_json_error(['message' => __('Recovery class not found', 'recovero')]);
        }
    }
}
