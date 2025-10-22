<?php
/**
 * Recovero Admin Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = $this->db->get_statistics();
?>

<div class="wrap recovero-admin recovero-dashboard-page">
    <h1><?php esc_html_e('Recovero Dashboard', 'recovero'); ?></h1>
    <p><?php esc_html_e('Monitor your abandoned cart recovery performance and analytics.', 'recovero'); ?></p>
    
    <!-- Stats Cards -->
    <div class="recovero-dashboard">
        <div class="recovero-stat-card">
            <div class="recovero-stat-number"><?php echo number_format($stats['total_carts']); ?></div>
            <div class="recovero-stat-label"><?php esc_html_e('Total Carts', 'recovero'); ?></div>
            <div class="recovero-stat-change positive">
                <i class="dashicons dashicons-arrow-up-alt2"></i>
                <?php esc_html_e('All time', 'recovero'); ?>
            </div>
        </div>
        
        <div class="recovero-stat-card">
            <div class="recovero-stat-number"><?php echo number_format($stats['abandoned_carts']); ?></div>
            <div class="recovero-stat-label"><?php esc_html_e('Abandoned Carts', 'recovero'); ?></div>
            <div class="recovero-stat-change negative">
                <i class="dashicons dashicons-arrow-down-alt2"></i>
                <?php 
                $percentage = $stats['total_carts'] > 0 ? round(($stats['abandoned_carts'] / $stats['total_carts']) * 100, 1) : 0;
                echo $percentage . '% ' . esc_html__('abandonment rate', 'recovero');
                ?>
            </div>
        </div>
        
        <div class="recovero-stat-card">
            <div class="recovero-stat-number"><?php echo number_format($stats['recovered_carts']); ?></div>
            <div class="recovero-stat-label"><?php esc_html_e('Recovered Carts', 'recovero'); ?></div>
            <div class="recovero-stat-change positive">
                <i class="dashicons dashicons-arrow-up-alt2"></i>
                <?php echo $stats['recovery_rate'] . '% ' . esc_html__('recovery rate', 'recovero'); ?>
            </div>
        </div>
        
        <div class="recovero-stat-card">
            <div class="recovero-stat-number"><?php echo number_format($stats['emails_sent']); ?></div>
            <div class="recovero-stat-label"><?php esc_html_e('Emails Sent', 'recovero'); ?></div>
            <div class="recovero-stat-change positive">
                <i class="dashicons dashicons-email-alt"></i>
                <?php esc_html_e('Recovery emails', 'recovero'); ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="recovero-chart-container">
        <h2 class="recovero-chart-title"><?php esc_html_e('Quick Actions', 'recovero'); ?></h2>
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <button id="recovero-refresh-stats" class="recovero-button secondary">
                <i class="dashicons dashicons-update"></i>
                <?php esc_html_e('Refresh Stats', 'recovero'); ?>
            </button>
            
            <a href="<?php echo admin_url('admin.php?page=recovero-carts'); ?>" class="recovero-button primary">
                <i class="dashicons dashicons-cart"></i>
                <?php esc_html_e('View All Carts', 'recovero'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=recovero-analytics'); ?>" class="recovero-button secondary">
                <i class="dashicons dashicons-chart-bar"></i>
                <?php esc_html_e('View Analytics', 'recovero'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=recovero-settings'); ?>" class="recovero-button secondary">
                <i class="dashicons dashicons-settings"></i>
                <?php esc_html_e('Settings', 'recovero'); ?>
            </a>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="recovero-chart-grid">
        <div class="recovero-chart-container">
            <h2 class="recovero-chart-title"><?php esc_html_e('Recovery Trends', 'recovero'); ?></h2>
            <canvas id="recovero-recovery-chart" width="400" height="200"></canvas>
        </div>
        
        <div class="recovero-chart-container">
            <h2 class="recovero-chart-title"><?php esc_html_e('Cart Status Distribution', 'recovero'); ?></h2>
            <canvas id="recovero-status-chart" width="400" height="200"></canvas>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="recovero-chart-container">
        <h2 class="recovero-chart-title"><?php esc_html_e('Recent Customer Activity', 'recovero'); ?></h2>
        <div class="recovero-activity-list">
            <?php
            $recent_carts = $this->db->get_abandoned_carts(10);
            if (!empty($recent_carts)) {
                foreach ($recent_carts as $cart) {
                    $time_ago = human_time_diff(strtotime($cart->created_at), current_time('timestamp')) . ' ' . __('ago', 'recovero');
                    $cart_data = maybe_unserialize($cart->cart_data);
                    $item_count = is_array($cart_data) ? count($cart_data) : 0;
                    $total = 0;
                    if (is_array($cart_data)) {
                        foreach ($cart_data as $item) {
                            $price = isset($item['price']) ? floatval($item['price']) : 0;
                            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
                            $total += $price * $quantity;
                        }
                    }
                    ?>
                    <div class="recovero-activity-item">
                        <div class="recovero-activity-icon">
                            <i class="dashicons dashicons-cart"></i>
                        </div>
                        <div class="recovero-activity-content">
                            <div class="recovero-activity-title">
                                <?php 
                                if (!empty($cart->customer_name)) {
                                    echo esc_html($cart->customer_name);
                                } elseif (!empty($cart->email)) {
                                    echo esc_html($cart->email);
                                } else {
                                    esc_html_e('Guest User', 'recovero');
                                }
                                ?>
                            </div>
                            <div class="recovero-activity-description">
                                <?php 
                                printf(esc_html__('%d items • Total: %s', 'recovero'), $item_count, wc_price($total));
                                if (!empty($cart->location)) {
                                    echo ' • ' . esc_html($cart->location);
                                }
                                ?>
                            </div>
                            <?php if (!empty($cart->phone)): ?>
                            <div class="recovero-activity-phone">
                                <i class="dashicons dashicons-phone"></i>
                                <?php echo esc_html($cart->phone); ?>
                            </div>
                            <?php endif; ?>
                            <div class="recovero-activity-time"><?php echo esc_html($time_ago); ?></div>
                        </div>
                        <div class="recovero-activity-status">
                            <span class="recovero-status <?php echo esc_attr($cart->status); ?>">
                                <?php echo esc_html(ucfirst($cart->status)); ?>
                            </span>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<p>' . esc_html__('No recent activity found. Customers will appear here when they add items to cart.', 'recovero') . '</p>';
            }
            ?>
        </div>
    </div>
    
    <!-- System Status -->
    <div class="recovero-chart-container">
        <h2 class="recovero-chart-title"><?php esc_html_e('System Status', 'recovero'); ?></h2>
        <div class="recovero-status-list">
            <div class="recovero-status-item">
                <div class="recovero-status-label"><?php esc_html_e('WooCommerce', 'recovero'); ?></div>
                <div class="recovero-status-value">
                    <?php if (class_exists('WooCommerce')): ?>
                        <span class="recovero-status completed"><?php esc_html_e('Active', 'recovero'); ?></span>
                    <?php else: ?>
                        <span class="recovero-status abandoned"><?php esc_html_e('Not Active', 'recovero'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="recovero-status-item">
                <div class="recovero-status-label"><?php esc_html_e('Cart Tracking', 'recovero'); ?></div>
                <div class="recovero-status-value">
                    <?php if (get_option('recovero_enable_tracking', true)): ?>
                        <span class="recovero-status completed"><?php esc_html_e('Enabled', 'recovero'); ?></span>
                    <?php else: ?>
                        <span class="recovero-status abandoned"><?php esc_html_e('Disabled', 'recovero'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="recovero-status-item">
                <div class="recovero-status-label"><?php esc_html_e('Email Recovery', 'recovero'); ?></div>
                <div class="recovero-status-value">
                    <?php if (get_option('recovero_enable_email_recovery', true)): ?>
                        <span class="recovero-status completed"><?php esc_html_e('Enabled', 'recovero'); ?></span>
                    <?php else: ?>
                        <span class="recovero-status abandoned"><?php esc_html_e('Disabled', 'recovero'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="recovero-status-item">
                <div class="recovero-status-label"><?php esc_html_e('Cron Jobs', 'recovero'); ?></div>
                <div class="recovero-status-value">
                    <?php
                    $next_cron = wp_next_scheduled('recovero_cron_hook');
                    if ($next_cron):
                        ?>
                        <span class="recovero-status completed">
                            <?php esc_html_e('Active', 'recovero'); ?>
                            <small>(<?php echo date_i18n('H:i', $next_cron); ?>)</small>
                        </span>
                    <?php else: ?>
                        <span class="recovero-status abandoned"><?php esc_html_e('Not Scheduled', 'recovero'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.recovero-activity-list {
    max-height: 400px;
    overflow-y: auto;
}

.recovero-activity-item {
    display: flex;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #e1e5e9;
}

.recovero-activity-item:last-child {
    border-bottom: none;
}

.recovero-activity-icon {
    width: 40px;
    height: 40px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: #0073aa;
}

.recovero-activity-content {
    flex: 1;
}

.recovero-activity-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 2px;
}

.recovero-activity-description {
    color: #666;
    font-size: 0.9em;
    margin-bottom: 2px;
}

.recovero-activity-time {
    color: #999;
    font-size: 0.8em;
}

.recovero-activity-phone {
    color: #666;
    font-size: 0.9em;
    margin-bottom: 2px;
}

.recovero-activity-phone i {
    font-size: 12px;
    margin-right: 5px;
}

.recovero-status-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.recovero-status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.recovero-status-label {
    font-weight: 600;
    color: #333;
}

.recovero-status-value small {
    display: block;
    font-size: 0.8em;
    margin-top: 2px;
}

.recovero-chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .recovero-chart-grid {
        grid-template-columns: 1fr;
    }
    
    .recovero-status-list {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
