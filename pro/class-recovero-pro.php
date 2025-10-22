<?php
if (!defined('ABSPATH')) exit;

class Recovero_Pro {
    private $license;

    public function __construct() {
        require_once RECOVERO_PATH . 'includes/class-recovero-license.php';
        $this->license = new Recovero_License();

        // Admin license page actions
        add_action('admin_menu', [$this, 'add_license_menu']);
        add_action('admin_post_recovero_license_activate', [$this, 'handle_activate']);
        add_action('admin_post_recovero_license_deactivate', [$this, 'handle_deactivate']);

        // Only load pro modules when license valid
        if ($this->license->is_valid()) {
            // load advanced features
            require_once RECOVERO_PATH . 'pro/class-recovero-advanced-triggers.php';
            require_once RECOVERO_PATH . 'pro/class-recovero-recovery-coupon.php';
            require_once RECOVERO_PATH . 'pro/class-recovero-push.php';
            require_once RECOVERO_PATH . 'pro/class-recovero-heatmap.php';

            new Recovero_Advanced_Triggers();
            new Recovero_Recovery_Coupon();
            new Recovero_Push();
            new Recovero_Heatmap();

            add_action('admin_notices', [$this, 'admin_pro_notice']);
        } else {
            add_action('admin_notices', [$this, 'admin_pro_notice']);
        }
    }

    public function add_license_menu() {
        add_submenu_page('recovero', 'Recovero License', 'License', 'manage_woocommerce', 'recovero-license', [$this, 'page_license']);
    }

    public function page_license() {
        $opt = $this->license->get_option();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Recovero Pro License', 'recovero'); ?></h1>
            <p><?php esc_html_e('Activate your Recovero Pro license to unlock WhatsApp, Coupons, Exit-Intent and Heatmap features.', 'recovero'); ?></p>

            <?php if (!empty($opt['status']) && $opt['status'] === 'active'): ?>
                <p><strong><?php esc_html_e('Status: Active', 'recovero'); ?></strong></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('recovero_license_action'); ?>
                    <input type="hidden" name="action" value="recovero_license_deactivate">
                    <input type="hidden" name="license_key" value="<?php echo esc_attr($opt['key']); ?>">
                    <?php submit_button(__('Deactivate License', 'recovero'), 'secondary'); ?>
                </form>
            <?php else: ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('recovero_license_action'); ?>
                    <input type="hidden" name="action" value="recovero_license_activate">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('License Key', 'recovero'); ?></th>
                            <td><input type="text" name="license_key" value="" class="regular-text" /></td>
                        </tr>
                    </table>
                    <?php submit_button(__('Activate License', 'recovero'), 'primary'); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_activate() {
        if (!current_user_can('manage_woocommerce')) wp_die(__('Unauthorized', 'recovero'));
        check_admin_referer('recovero_license_action');

        $key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        $res = $this->license->activate($key);
        if (is_wp_error($res)) {
            wp_safe_redirect(add_query_arg('recovero_license_error', urlencode($res->get_error_message()), admin_url('admin.php?page=recovero-license')));
        } else {
            wp_safe_redirect(admin_url('admin.php?page=recovero-license&activated=1'));
        }
        exit;
    }

    public function handle_deactivate() {
        if (!current_user_can('manage_woocommerce')) wp_die(__('Unauthorized', 'recovero'));
        check_admin_referer('recovero_license_action');
        $opt = $this->license->get_option();
        if (!empty($opt['key'])) {
            $this->license->deactivate($opt['key']);
        }
        wp_safe_redirect(admin_url('admin.php?page=recovero-license&deactivated=1'));
        exit;
    }

    public function admin_pro_notice() {
        if (!$this->license->is_valid()) {
            ?>
            <div class="notice notice-warning">
                <p><?php printf(esc_html__('Recovero Pro is inactive. %sActivate your license%s to enable Pro features.', 'recovero'), '<a href="'.esc_url(admin_url('admin.php?page=recovero-license')).'">', '</a>'); ?></p>
            </div>
            <?php
        }
    }
}
