<?php
/**
 * Recovero Cron Class
 * Handles scheduled tasks and cron jobs
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recovero_Cron {
    
    private $db;
    private $recovery;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Recovero_DB();
        $this->recovery = new Recovero_Recovery();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Main recovery cron
        add_action('recovero_cron_hook', [$this, 'run_recovery']);
        
        // Cleanup cron
        add_action('recovero_cleanup_hook', [$this, 'run_cleanup']);
        
        // Custom cron schedules
        add_filter('cron_schedules', [$this, 'add_custom_schedules']);
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_custom_schedules($schedules) {
        $schedules['recovero_15min'] = [
            'interval' => 15 * 60,
            'display' => __('Every 15 minutes', 'recovero')
        ];
        
        $schedules['recovero_30min'] = [
            'interval' => 30 * 60,
            'display' => __('Every 30 minutes', 'recovero')
        ];
        
        $schedules['recovero_6hours'] = [
            'interval' => 6 * 60 * 60,
            'display' => __('Every 6 hours', 'recovero')
        ];
        
        return $schedules;
    }
    
    /**
     * Run recovery process
     */
    public function run_recovery() {
        try {
            error_log('Recovero: Starting recovery process');
            
            // Get abandoned carts that need recovery
            $carts = $this->get_carts_for_recovery();
            
            if (empty($carts)) {
                error_log('Recovero: No carts to process');
                return;
            }
            
            $processed = 0;
            $sent = 0;
            
            foreach ($carts as $cart) {
                $processed++;
                
                // Check if we should send recovery
                if ($this->should_send_recovery($cart)) {
                    $result = $this->send_recovery($cart);
                    
                    if ($result) {
                        $sent++;
                        error_log("Recovero: Recovery sent for cart {$cart->id}");
                    }
                }
            }
            
            error_log("Recovero: Recovery process completed. Processed: {$processed}, Sent: {$sent}");
            
        } catch (Exception $e) {
            error_log("Recovero Cron Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get carts that need recovery
     */
    private function get_carts_for_recovery() {
        global $wpdb;
        
        $table = $this->db->get_table('carts');
        $logs_table = $this->db->get_table('logs');
        
        $delay_hours = get_option('recovero_delay_hours', 1);
        $time_threshold = date('Y-m-d H:i:s', strtotime("-{$delay_hours} hours", current_time('timestamp')));
        
        // Get carts that haven't received recovery yet
        $sql = "SELECT c.* FROM {$table} c 
                LEFT JOIN {$logs_table} l ON c.id = l.cart_id 
                WHERE c.status = 'abandoned' 
                AND c.created_at <= %s 
                AND l.id IS NULL
                ORDER BY c.created_at ASC
                LIMIT 50";
        
        return $wpdb->get_results($wpdb->prepare($sql, $time_threshold));
    }
    
    /**
     * Check if we should send recovery for this cart
     */
    private function should_send_recovery($cart) {
        // Check if email is available
        if (empty($cart->email)) {
            return false;
        }
        
        // Check if cart is not too old
        $max_age_hours = get_option('recovero_max_cart_age', 168); // 1 week default
        $cart_age = (time() - strtotime($cart->created_at)) / 3600;
        
        if ($cart_age > $max_age_hours) {
            return false;
        }
        
        // Check if user has already completed an order
        if ($cart->user_id > 0) {
            $recent_orders = wc_get_orders([
                'customer_id' => $cart->user_id,
                'status' => 'completed',
                'date_created' => '>' . $cart->created_at,
                'limit' => 1
            ]);
            
            if (!empty($recent_orders)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Send recovery
     */
    private function send_recovery($cart) {
        try {
            // Send email recovery
            if (get_option('recovero_enable_email_recovery', true)) {
                $result = $this->recovery->send_email($cart);
                
                if ($result) {
                    // Log the recovery attempt
                    $this->db->add_recovery_log([
                        'cart_id' => $cart->id,
                        'method' => 'email',
                        'status' => 'sent',
                        'token' => wp_generate_password(20, false),
                        'sent_at' => current_time('mysql')
                    ]);
                    
                    return true;
                }
            }
            
            // Send WhatsApp recovery if enabled
            if (get_option('recovero_enable_whatsapp_recovery', false) && !empty($cart->phone)) {
                $this->send_whatsapp_recovery($cart);
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Recovero: Failed to send recovery for cart {$cart->id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send WhatsApp recovery
     */
    private function send_whatsapp_recovery($cart) {
        try {
            if (class_exists('Recovero_WhatsApp')) {
                $whatsapp = new Recovero_WhatsApp();
                
                $message = $this->get_whatsapp_message($cart);
                $result = $whatsapp->send_text($cart->phone, $message);
                
                if (!is_wp_error($result)) {
                    // Log the WhatsApp recovery
                    $this->db->add_recovery_log([
                        'cart_id' => $cart->id,
                        'method' => 'whatsapp',
                        'status' => 'sent',
                        'token' => wp_generate_password(20, false),
                        'sent_at' => current_time('mysql')
                    ]);
                    
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Recovero: Failed to send WhatsApp recovery: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get WhatsApp message
     */
    private function get_whatsapp_message($cart) {
        $site_name = get_bloginfo('name');
        $recovery_link = $this->get_recovery_link($cart);
        
        $message = sprintf(
            __('Hi from %s! You left some items in your cart. Complete your order here: %s', 'recovero'),
            $site_name,
            $recovery_link
        );
        
        return $message;
    }
    
    /**
     * Get recovery link
     */
    private function get_recovery_link($cart) {
        $token = wp_generate_password(20, false);
        
        // Save token to logs
        $this->db->add_recovery_log([
            'cart_id' => $cart->id,
            'method' => 'link',
            'status' => 'generated',
            'token' => $token,
            'sent_at' => current_time('mysql')
        ]);
        
        return add_query_arg([
            'recovero_token' => $token,
            'recovero_cart' => $cart->id
        ], home_url());
    }
    
    /**
     * Run cleanup process
     */
    public function run_cleanup() {
        try {
            error_log('Recovero: Starting cleanup process');
            
            $cleanup_days = get_option('recovero_purge_days', 90);
            $deleted = $this->db->delete_old_data($cleanup_days);
            
            error_log("Recovero: Cleanup completed. Deleted {$deleted} old records");
            
            // Clear expired transients
            $this->clear_expired_transients();
            
        } catch (Exception $e) {
            error_log("Recovero Cleanup Error: " . $e->getMessage());
        }
    }
    
    /**
     * Clear expired transients
     */
    private function clear_expired_transients() {
        global $wpdb;
        
        $transients = [
            'recovero_stats_cache',
            'recovero_analytics_cache',
            'recovero_license_cache',
            'recovero_dashboard_cache'
        ];
        
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
    }
    
    /**
     * Schedule custom cron job
     */
    public function schedule_custom_cron($hook, $interval = 'hourly') {
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $interval, $hook);
        }
    }
    
    /**
     * Unschedule cron job
     */
    public function unschedule_cron($hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }
    
    /**
     * Get cron status
     */
    public function get_cron_status() {
        $status = [];
        
        $crons = [
            'recovero_cron_hook' => __('Recovery Process', 'recovero'),
            'recovero_cleanup_hook' => __('Cleanup Process', 'recovero')
        ];
        
        foreach ($crons as $hook => $name) {
            $timestamp = wp_next_scheduled($hook);
            $status[$hook] = [
                'name' => $name,
                'scheduled' => $timestamp ? true : false,
                'next_run' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : __('Not scheduled', 'recovero')
            ];
        }
        
        return $status;
    }
    
    /**
     * Run manual recovery
     */
    public function run_manual_recovery($cart_id = null) {
        try {
            if ($cart_id) {
                // Process specific cart
                $cart = $this->db->get_cart($cart_id);
                
                if ($cart && $this->should_send_recovery($cart)) {
                    $result = $this->send_recovery($cart);
                    return $result;
                }
                
                return false;
            } else {
                // Process all pending carts
                $this->run_recovery();
                return true;
            }
            
        } catch (Exception $e) {
            error_log("Recovero Manual Recovery Error: " . $e->getMessage());
            return false;
        }
    }
}
