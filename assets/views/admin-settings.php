<?php
/**
 * Recovero Admin Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$email_from = get_option('recovero_email_from', get_option('admin_email'));
$delay_hours = get_option('recovero_delay_hours', 1);
$purge_days = get_option('recovero_purge_days', 90);
$enable_tracking = get_option('recovero_enable_tracking', true);
$enable_email_recovery = get_option('recovero_enable_email_recovery', true);
$enable_whatsapp_recovery = get_option('recovero_enable_whatsapp_recovery', false);
$enable_geo_tracking = get_option('recovero_enable_geo_tracking', true);
$email_subject = get_option('recovero_email_subject', __('Complete your purchase', 'recovero'));
$max_cart_age = get_option('recovero_max_cart_age', 168);
?>

<div class="wrap recovero-admin recovero-settings-page">
    <h1><?php esc_html_e('Recovero Settings', 'recovero'); ?></h1>
    <p><?php esc_html_e('Configure your abandoned cart recovery settings and preferences.', 'recovero'); ?></p>
    
    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully!', 'recovero'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="recovero-settings-form">
        <?php wp_nonce_field('recovero_settings_save'); ?>
        <input type="hidden" name="action" value="recovero_settings_save">
        
        <!-- Email Settings -->
        <div class="recovero-section">
            <h2 class="recovero-section-title">
                <i class="dashicons dashicons-email-alt"></i>
                <?php esc_html_e('Email Settings', 'recovero'); ?>
            </h2>
            
            <div class="recovero-form-row">
                <div class="recovero-form-label">
                    <label for="recovero_email_from"><?php esc_html_e('From Email', 'recovero'); ?></label>
                </div>
                <div class="recovero-form-field">
                    <input type="email" id="recovero_email_from" name="recovero_email_from" 
                           class="recovero-form-input regular-text" 
                           value="<?php echo esc_attr($email_from); ?>">
                    <div class="recovero-form-description">
                        <?php esc_html_e('Email address that will appear as sender for recovery emails.', 'recovero'); ?>
                    </div>
                </div>
            </div>
            
            <div class="recovero-form-row">
                <div class="recovero-form-label">
                    <label for="recovero_email_subject"><?php esc_html_e('Email Subject', 'recovero'); ?></label>
                </div>
                <div class="recovero-form-field">
                    <input type="text" id="recovero_email_subject" name="recovero_email_subject" 
                           class="recovero-form-input regular-text" 
                           value="<?php echo esc_attr($email_subject); ?>">
                    <div class="recovero-form-description">
                        <?php esc_html_e('Subject line for recovery emails.', 'recovero'); ?>
                    </div>
                </div>
            </div>
            
            <div class="recovero-form-row">
                <div class="recovero-form-label">
                    <label for="recovero_delay_hours"><?php esc_html_e('Send After', 'recovero'); ?></label>
                </div>
                <div class="recovero-form-field">
                    <input type="number" id="recovero_delay_hours" name="recovero_delay_hours" 
                           class="recovero-form-input small-text" 
                           value="<?php echo esc_attr($delay_hours); ?>" 
                           min="1" max="168">
                    <span style="margin-left: 10px;"><?php esc_html_e('hours', 'recovero'); ?></span>
                    <div class="recovero-form-description">
                        <?php esc_html_e('How long to wait before sending recovery email (1-168 hours).', 'recovero'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tracking Settings -->
        <div class="recovero-section">
            <h2 class="recovero-section-title">
                <i class="dashicons dashicons-chart-line"></i>
                <?php esc_html_e('Tracking Settings', 'recovero'); ?>
            </h2>
            
            <div class="recovero-form-row">
                <div class="recovero-form-label">
                    <label><?php esc_html_e('Cart Tracking', 'recovero'); ?></label>
                </div>
                <div class="recovero-form-field">
                    <label class="recovero-checkbox-label">
                        <input type="checkbox" name="recovero_enable_tracking" 
                               class="recovero-form-checkbox" 
                               <?php checked($enable_tracking); ?>>
                        <?php esc_html_e('Enable cart abandonment tracking', 'recovero'); ?>
                    </label>
                    <div class="recovero-form-description">
                        <?php esc_html_e('Track when users abandon their carts for recovery.', 'recovero'); ?>
                    </div>
                </div>
            </div>
            
            <div class="recovero-form-row">
                <div class="recovero-form-label">
                    <label><?php esc_html_e('Geo Tracking', 'recovero'); ?></label>
                </div>
                <div class="recovero-form-field">
                    <label class="recovero-checkbox-label">
                        <input type="checkbox" name="recovero_enable_geo_tracking" 
                               class="recovero-form-checkbox" 
                               <?php checked($enable_geo_tracking); ?>>
                        <?php esc_html_e('Enable geographic tracking', 'recovero'); ?>
                    </label>
                    <div class="recovero-form-description">
                        <?php esc_html_e('Track user location for analytics (requires user permission).', 'recovero'); ?>
                    </div>
                </div>
            </div>
            
            <div class="recovero-form-row">
                <div class="recovero-form-label">
                    <label for="recovero_max_cart_age"><?php esc_html_e('Max Cart Age', 'recovero'); ?></label>
                </div>
                <div class="recovero-form-field">
                    <input type="number" id="recovero_max_cart_age" name="recovero_max_cart_age" 
                           class="recovero-form-input small-text" 
                           value="<?php echo esc_attr($max_cart_age); ?>" 
                           min="1" max="720">
                    <span style="margin-left: 10px;"><?php esc_html_e('hours', 'recovero'); ?></span>
                    <div class="recovero-form-description">
                        <?php esc_html_e('Maximum age of carts to consider for recovery (1-720 hours).', 'recovero'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recovery Methods -->
        <div class="recovero-section">
            <h2 class="recovero-section-title">
                <i class="dashicons dashicons-backup"></i>
                <?php esc_html_e('Recovery Methods', 'recovero'); ?>
            </h2>
            
            <div class="recovero-form-row">
                <div class="recovero-form-label">
                    <label><?php esc_html_e('Email Recovery', 'recovero'); ?></label>
                </div>
                <div class="recovero-form-field">
                    <label class="recovero-checkbox-label">
                        <input type="checkbox" name="recovero_enable_email_recovery" 
                               class="recovero-form-checkbox" 
                               <?php checked($enable_email_recovery); ?>>
                        <?php esc_html_e('Enable email recovery', 'recovero'); ?>
                    </label>
                    <div class="recovero-form-description">
                        <?php esc_html_e('Send recovery emails to customers who abandon their carts.', 'recovero'); ?>
                    </div>
                </div>
            </div>
            
            <div class="recovero-form-row">
                <div class="recovero-form-label">
                    <label><?php esc_html_e('WhatsApp Recovery', 'recovero'); ?></label>
                </div>
                <div class="recovero-form-field">
                    <label class="recovero-checkbox-label">
                        <input type="checkbox" name="recovero_enable_whatsapp_recovery" 
                               class="recovero-form-checkbox" 
                               <?php checked($enable_whatsapp_recovery); ?>>
                        <?php esc_html_e('Enable WhatsApp recovery (Pro)', 'recovero'); ?>
                    </label>
                    <div class="recovero-form-description">
                        <?php esc_html_e('Send WhatsApp messages for cart recovery (requires Pro license).', 'recovero'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Data Management -->
        <div class="recovero-section">
            <h2 class="recovero-section-title">
                <i class="dashicons dashicons-database"></i>
                <?php esc_html_e('Data Management', 'recovero'); ?>
            </h2>
            
            <div class="recovero-form-row">
                <div class="recovero-form-label">
                    <label for="recovero_purge_days"><?php esc_html_e('Data Retention', 'recovero'); ?></label>
                </div>
                <div class="recovero-form-field">
                    <input type="number" id="recovero_purge_days" name="recovero_purge_days" 
                           class="recovero-form-input small-text" 
                           value="<?php echo esc_attr($purge_days); ?>" 
                           min="1" max="365">
                    <span style="margin-left: 10px;"><?php esc_html_e('days', 'recovero'); ?></span>
                    <div class="recovero-form-description">
                        <?php esc_html_e('Automatically delete old data after this many days (1-365 days).', 'recovero'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Test Email -->
        <div class="recovero-section">
            <h2 class="recovero-section-title">
                <i class="dashicons dashicons-email-alt2"></i>
                <?php esc_html_e('Test Email', 'recovero'); ?>
            </h2>
            
            <div class="recovero-form-row">
                <div class="recovero-form-label">
                    <label for="recovero_test_email"><?php esc_html_e('Test Email', 'recovero'); ?></label>
                </div>
                <div class="recovero-form-field">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="email" id="recovero_test_email" name="recovero_test_email" 
                               class="recovero-form-input regular-text" 
                               placeholder="<?php esc_attr_e('Enter email to test', 'recovero'); ?>">
                        <button type="button" id="recovero-send-test-email" class="recovero-button secondary">
                            <?php esc_html_e('Send Test', 'recovero'); ?>
                        </button>
                    </div>
                    <div class="recovero-form-description">
                        <?php esc_html_e('Send a test recovery email to verify your settings.', 'recovero'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Save Button -->
        <div class="recovero-section">
            <p class="submit">
                <button type="submit" class="recovero-button primary">
                    <i class="dashicons dashicons-saved"></i>
                    <?php esc_html_e('Save Settings', 'recovero'); ?>
                </button>
            </p>
        </div>
    </form>
</div>

<style>
.recovero-section {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.recovero-section-title {
    font-size: 1.2em;
    font-weight: 600;
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.recovero-section-title .dashicons {
    margin-right: 10px;
    vertical-align: middle;
}

.recovero-form-row {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 20px;
    margin-bottom: 20px;
    align-items: center;
}

.recovero-form-label {
    font-weight: 600;
    color: #333;
}

.recovero-form-field {
    display: flex;
    flex-direction: column;
}

.recovero-form-input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9em;
    width: 100%;
    max-width: 400px;
}

.recovero-form-input:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 2px rgba(0,115,170,0.2);
}

.recovero-checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.recovero-form-checkbox {
    margin-right: 8px;
}

.recovero-form-description {
    font-size: 0.8em;
    color: #666;
    margin-top: 5px;
    max-width: 500px;
}

@media (max-width: 768px) {
    .recovero-form-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .recovero-form-input {
        max-width: 100%;
    }
}
</style>
