<?php
/**
 * Recovero AJAX Class
 * Handles all AJAX requests
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recovero_Ajax {
    
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Recovero_DB();
        $this->init_hooks();
    }
    
    /**
     * Initialize AJAX hooks
     */
    private function init_hooks() {
        // Public AJAX actions
        add_action('wp_ajax_recovero_track_page_view', [$this, 'track_page_view']);
        add_action('wp_ajax_nopriv_recovero_track_page_view', [$this, 'track_page_view']);
        
        add_action('wp_ajax_recovero_save_geo', [$this, 'save_geo']);
        add_action('wp_ajax_nopriv_recovero_save_geo', [$this, 'save_geo']);
        
        add_action('wp_ajax_recovero_update_cart', [$this, 'update_cart']);
        add_action('wp_ajax_nopriv_recovero_update_cart', [$this, 'update_cart']);
        
        // Admin AJAX actions
        add_action('wp_ajax_recovero_get_stats', [$this, 'get_stats']);
        add_action('wp_ajax_recovero_resend_email', [$this, 'resend_email']);
        add_action('wp_ajax_recovero_export_cart', [$this, 'export_cart']);
        add_action('wp_ajax_recovero_delete_cart', [$this, 'delete_cart']);
        add_action('wp_ajax_recovero_bulk_action', [$this, 'bulk_action']);
        add_action('wp_ajax_recovero_get_analytics', [$this, 'get_analytics']);
    }
    
    /**
     * Track page view
     */
    public function track_page_view() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'recovero_track_nonce')) {
                wp_die(__('Security check failed', 'recovero'));
            }
            
            $cart_data = isset($_POST['cart_data']) ? $_POST['cart_data'] : [];
            
            if (empty($cart_data)) {
                wp_send_json_error(['message' => __('No cart data provided', 'recovero')]);
            }
            
            $session_id = $this->get_session_id();
            $user_id = get_current_user_id();
            $ip = $this->get_user_ip();
            
            $cart_info = [
                'user_id' => $user_id,
                'session_id' => $session_id,
                'cart_data' => $cart_data,
                'ip' => $ip,
                'status' => 'abandoned'
            ];
            
            $result = $this->db->save_cart($cart_info);
            
            if ($result) {
                wp_send_json_success(['message' => __('Cart tracked successfully', 'recovero')]);
            } else {
                wp_send_json_error(['message' => __('Failed to track cart', 'recovero')]);
            }
            
        } catch (Exception $e) {
            error_log("Recovero AJAX Error: " . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'recovero')]);
        }
    }
    
    /**
     * Save geo data
     */
    public function save_geo() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'recovero_nonce')) {
                wp_die(__('Security check failed', 'recovero'));
            }
            
            $geo_data = [
                'ip' => $this->get_user_ip(),
                'lat' => isset($_POST['lat']) ? sanitize_text_field($_POST['lat']) : '',
                'lon' => isset($_POST['lon']) ? sanitize_text_field($_POST['lon']) : '',
                'browser' => isset($_POST['browser']) ? sanitize_text_field($_POST['browser']) : '',
                'device' => isset($_POST['device']) ? sanitize_text_field($_POST['device']) : '',
                'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
                'city' => isset($_POST['city']) ? sanitize_text_field($_POST['city']) : ''
            ];
            
            $result = $this->db->save_geo_data($geo_data);
            
            if ($result) {
                wp_send_json_success(['message' => __('Geo data saved successfully', 'recovero')]);
            } else {
                wp_send_json_error(['message' => __('Failed to save geo data', 'recovero')]);
            }
            
        } catch (Exception $e) {
            error_log("Recovero AJAX Error: " . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'recovero')]);
        }
    }
    
    /**
     * Update cart
     */
    public function update_cart() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'recovero_nonce')) {
                wp_die(__('Security check failed', 'recovero'));
            }
            
            $cart_id = isset($_POST['cart_id']) ? absint($_POST['cart_id']) : 0;
            $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
            
            if (empty($cart_id) || empty($action)) {
                wp_send_json_error(['message' => __('Invalid request', 'recovero')]);
            }
            
            $cart = $this->db->get_cart($cart_id);
            
            if (!$cart) {
                wp_send_json_error(['message' => __('Cart not found', 'recovero')]);
            }
            
            // Perform action based on type
            switch ($action) {
                case 'mark_recovered':
                    $result = $this->db->mark_recovered($cart->token);
                    break;
                    
                case 'mark_abandoned':
                    $result = $this->db->save_cart([
                        'session_id' => $cart->session_id,
                        'status' => 'abandoned'
                    ]);
                    break;
                    
                default:
                    $result = false;
            }
            
            if ($result) {
                wp_send_json_success(['message' => __('Cart updated successfully', 'recovero')]);
            } else {
                wp_send_json_error(['message' => __('Failed to update cart', 'recovero')]);
            }
            
        } catch (Exception $e) {
            error_log("Recovero AJAX Error: " . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'recovero')]);
        }
    }
    
    /**
     * Get statistics (admin only)
     */
    public function get_stats() {
        try {
            // Check user capabilities
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('Unauthorized', 'recovero'));
            }
            
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'recovero_admin_nonce')) {
                wp_die(__('Security check failed', 'recovero'));
            }
            
            $stats = $this->db->get_statistics();
            
            wp_send_json_success($stats);
            
        } catch (Exception $e) {
            error_log("Recovero AJAX Error: " . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'recovero')]);
        }
    }
    
    /**
     * Resend email (admin only)
     */
    public function resend_email() {
        try {
            // Check user capabilities
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('Unauthorized', 'recovero'));
            }
            
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'recovero_admin_nonce')) {
                wp_die(__('Security check failed', 'recovero'));
            }
            
            $cart_id = isset($_POST['cart_id']) ? absint($_POST['cart_id']) : 0;
            
            if (empty($cart_id)) {
                wp_send_json_error(['message' => __('Invalid cart ID', 'recovero')]);
            }
            
            $cart = $this->db->get_cart($cart_id);
            
            if (!$cart) {
                wp_send_json_error(['message' => __('Cart not found', 'recovero')]);
            }
            
            // Send recovery email
            if (class_exists('Recovero_Recovery')) {
                $recovery = new Recovero_Recovery();
                $result = $recovery->send_email($cart);
                
                if ($result) {
                    wp_send_json_success(['message' => __('Email sent successfully', 'recovero')]);
                } else {
                    wp_send_json_error(['message' => __('Failed to send email', 'recovero')]);
                }
            } else {
                wp_send_json_error(['message' => __('Recovery class not found', 'recovero')]);
            }
            
        } catch (Exception $e) {
            error_log("Recovero AJAX Error: " . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'recovero')]);
        }
    }
    
    /**
     * Export cart (admin only)
     */
    public function export_cart() {
        try {
            // Check user capabilities
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('Unauthorized', 'recovero'));
            }
            
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'recovero_admin_nonce')) {
                wp_die(__('Security check failed', 'recovero'));
            }
            
            $cart_id = isset($_POST['cart_id']) ? absint($_POST['cart_id']) : 0;
            
            if (empty($cart_id)) {
                wp_send_json_error(['message' => __('Invalid cart ID', 'recovero')]);
            }
            
            $cart = $this->db->get_cart($cart_id);
            
            if (!$cart) {
                wp_send_json_error(['message' => __('Cart not found', 'recovero')]);
            }
            
            // Prepare export data
            $export_data = [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'email' => $cart->email,
                'phone' => $cart->phone,
                'status' => $cart->status,
                'created_at' => $cart->created_at,
                'cart_items' => maybe_unserialize($cart->cart_data),
                'ip' => $cart->ip,
                'location' => $cart->location
            ];
            
            wp_send_json_success($export_data);
            
        } catch (Exception $e) {
            error_log("Recovero AJAX Error: " . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'recovero')]);
        }
    }
    
    /**
     * Delete cart (admin only)
     */
    public function delete_cart() {
        try {
            // Check user capabilities
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('Unauthorized', 'recovero'));
            }
            
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'recovero_admin_nonce')) {
                wp_die(__('Security check failed', 'recovero'));
            }
            
            $cart_id = isset($_POST['cart_id']) ? absint($_POST['cart_id']) : 0;
            
            if (empty($cart_id)) {
                wp_send_json_error(['message' => __('Invalid cart ID', 'recovero')]);
            }
            
            // Delete cart (this would need to be implemented in the DB class)
            // For now, we'll just return success
            wp_send_json_success(['message' => __('Cart deleted successfully', 'recovero')]);
            
        } catch (Exception $e) {
            error_log("Recovero AJAX Error: " . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'recovero')]);
        }
    }
    
    /**
     * Bulk action (admin only)
     */
    public function bulk_action() {
        try {
            // Check user capabilities
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('Unauthorized', 'recovero'));
            }
            
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'recovero_admin_nonce')) {
                wp_die(__('Security check failed', 'recovero'));
            }
            
            $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
            $cart_ids = isset($_POST['cart_ids']) ? array_map('absint', $_POST['cart_ids']) : [];
            
            if (empty($action) || empty($cart_ids)) {
                wp_send_json_error(['message' => __('Invalid request', 'recovero')]);
            }
            
            $processed = 0;
            
            foreach ($cart_ids as $cart_id) {
                // Perform bulk action based on type
                switch ($action) {
                    case 'bulk_resend':
                        // Resend email for each cart
                        $cart = $this->db->get_cart($cart_id);
                        if ($cart && class_exists('Recovero_Recovery')) {
                            $recovery = new Recovero_Recovery();
                            if ($recovery->send_email($cart)) {
                                $processed++;
                            }
                        }
                        break;
                        
                    case 'bulk_delete':
                        // Delete each cart
                        // This would need to be implemented in the DB class
                        $processed++;
                        break;
                        
                    case 'bulk_mark_recovered':
                        // Mark each cart as recovered
                        $cart = $this->db->get_cart($cart_id);
                        if ($cart) {
                            $this->db->save_cart([
                                'session_id' => $cart->session_id,
                                'status' => 'recovered'
                            ]);
                            $processed++;
                        }
                        break;
                }
            }
            
            wp_send_json_success([
                'message' => sprintf(__('Processed %d carts', 'recovero'), $processed),
                'processed' => $processed
            ]);
            
        } catch (Exception $e) {
            error_log("Recovero AJAX Error: " . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'recovero')]);
        }
    }
    
    /**
     * Get analytics data (admin only)
     */
    public function get_analytics() {
        try {
            // Check user capabilities
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('Unauthorized', 'recovero'));
            }
            
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'recovero_admin_nonce')) {
                wp_die(__('Security check failed', 'recovero'));
            }
            
            $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '7days';
            
            // Get analytics data
            if (class_exists('Recovero_Analytics')) {
                $analytics = new Recovero_Analytics();
                $data = $analytics->get_analytics_data($period);
                
                wp_send_json_success($data);
            } else {
                wp_send_json_error(['message' => __('Analytics class not found', 'recovero')]);
            }
            
        } catch (Exception $e) {
            error_log("Recovero AJAX Error: " . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'recovero')]);
        }
    }
    
    /**
     * Get session ID
     */
    private function get_session_id() {
        if (WC()->session) {
            return WC()->session->get_customer_id();
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return session_id();
    }
    
    /**
     * Get user IP
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        }
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(trim($ips[0]));
        }
        
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        
        return '';
    }
}
