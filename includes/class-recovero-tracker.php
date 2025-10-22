<?php
/**
 * Recovero Tracker Class
 * Handles cart tracking functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recovero_Tracker {
    
    private $db;
    private $is_tracking_enabled;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Recovero_DB();
        $this->is_tracking_enabled = get_option('recovero_enable_tracking', true);
        
        if ($this->is_tracking_enabled) {
            $this->init_hooks();
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Track cart updates
        add_action('woocommerce_cart_updated', [$this, 'track_cart']);
        
        // Track cart item addition
        add_action('woocommerce_add_to_cart', [$this, 'track_cart_addition'], 10, 6);
        
        // Track cart item removal
        add_action('woocommerce_cart_item_removed', [$this, 'track_cart_removal'], 10, 2);
        
        // Track cart item quantity change
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'track_quantity_change'], 10, 4);
        
        // Track user logout
        add_action('wp_logout', [$this, 'clear_session']);
        
        // Track checkout process
        add_action('woocommerce_before_checkout_process', [$this, 'track_checkout_start']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'track_checkout_progress'], 10, 2);
        
        // Track order completion
        add_action('woocommerce_thankyou', [$this, 'track_order_completion']);
        
        // Track page views for cart abandonment
        add_action('wp_footer', [$this, 'track_page_view']);
        
        // Track form submissions
        add_action('woocommerce_checkout_process', [$this, 'track_checkout_form_submission']);
        
        // Track customer registration
        add_action('user_register', [$this, 'track_customer_registration']);
        
        // Track guest checkout attempt
        add_action('woocommerce_before_checkout_form', [$this, 'track_guest_checkout_attempt']);
    }
    
    /**
     * Track cart changes
     */
    public function track_cart() {
        try {
            if (!WC()->cart || WC()->cart->is_empty()) {
                return;
            }
            
            $cart_data = $this->get_cart_data();
            if (empty($cart_data)) {
                return;
            }
            
            $this->save_cart_data($cart_data);
            
        } catch (Exception $e) {
            error_log("Recovero Tracker Error: " . $e->getMessage());
        }
    }
    
    /**
     * Track cart item addition
     */
    public function track_cart_addition($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        $this->track_cart();
    }
    
    /**
     * Track cart item removal
     */
    public function track_cart_removal($cart_item_key, $cart) {
        $this->track_cart();
    }
    
    /**
     * Track quantity change
     */
    public function track_quantity_change($cart_item_key, $quantity, $old_quantity, $cart) {
        $this->track_cart();
    }
    
    /**
     * Get session ID
     */
    private function get_session_id() {
        if (WC()->session) {
            return WC()->session->get_customer_id();
        }
        return session_id();
    }
    
    /**
     * Get cart data
     */
    private function get_cart_data() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return [];
        }
        
        $cart_items = [];
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $cart_items[] = [
                'product_id' => $product->get_id(),
                'name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
                'price' => $product->get_price(),
                'subtotal' => $cart_item['line_subtotal'],
                'total' => $cart_item['line_total'],
                'image' => wp_get_attachment_url($product->get_image_id()),
                'category' => wp_get_post_terms($product->get_id(), 'product_cat')[0]->name ?? ''
            ];
        }
        
        return $cart_items;
    }
    
    /**
     * Save cart data
     */
    private function save_cart_data($cart_data) {
        $session_id = $this->get_session_id();
        $user_id = get_current_user_id();
        
        $cart_info = [
            'session_id' => $session_id,
            'user_id' => $user_id > 0 ? $user_id : null,
            'email' => $this->get_user_email(),
            'phone' => $this->get_user_phone(),
            'customer_name' => $this->get_user_name(),
            'cart_data' => json_encode($cart_data),
            'cart_total' => WC()->cart->get_total('edit'),
            'currency' => get_woocommerce_currency(),
            'ip_address' => $this->get_user_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'status' => 'active',
            'created_at' => current_time('mysql')
        ];
        
        $this->db->save_cart($cart_info);
    }
    
    /**
     * Get user email
     */
    private function get_user_email() {
        $user_id = get_current_user_id();
        
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            return $user ? $user->user_email : '';
        }
        
        // Check for guest email in checkout form
        if (isset($_POST['billing_email'])) {
            return sanitize_email($_POST['billing_email']);
        }
        
        // Check for email in session
        if (WC()->session && WC()->session->get('billing_email')) {
            return WC()->session->get('billing_email');
        }
        
        // Check for email in URL parameters
        if (isset($_GET['email'])) {
            return sanitize_email($_GET['email']);
        }
        
        return '';
    }
    
    /**
     * Get user name
     */
    private function get_user_name() {
        $user_id = get_current_user_id();
        
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            if ($user) {
                return $user->first_name . ' ' . $user->last_name;
            }
        }
        
        // Check for guest name in checkout form
        if (isset($_POST['billing_first_name']) && isset($_POST['billing_last_name'])) {
            return sanitize_text_field($_POST['billing_first_name']) . ' ' . sanitize_text_field($_POST['billing_last_name']);
        }
        
        // Check for name in session
        if (WC()->session) {
            $first_name = WC()->session->get('billing_first_name');
            $last_name = WC()->session->get('billing_last_name');
            if ($first_name && $last_name) {
                return $first_name . ' ' . $last_name;
            }
        }
        
        return '';
    }
    
    /**
     * Get user phone
     */
    private function get_user_phone() {
        $user_id = get_current_user_id();
        
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            $phone = get_user_meta($user_id, 'billing_phone', true);
            return $phone ? sanitize_text_field($phone) : '';
        }
        
        // Check for guest phone in checkout form
        if (isset($_POST['billing_phone'])) {
            return sanitize_text_field($_POST['billing_phone']);
        }
        
        // Check for phone in session
        if (WC()->session && WC()->session->get('billing_phone')) {
            return WC()->session->get('billing_phone');
        }
        
        return '';
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
    
    /**
     * Get user location
     */
    private function get_user_location($ip) {
        $location = '';
        
        // Try to get location from IP
        if (function_exists('wp_remote_get')) {
            $response = wp_remote_get("http://ip-api.com/json/{$ip}");
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['country']) && isset($data['city'])) {
                    $location = $data['city'] . ', ' . $data['country'];
                }
            }
        }
        
        return $location;
    }
    
    /**
     * Clear session data
     */
    public function clear_session() {
        if (WC()->session) {
            WC()->session->__unset('recovero_tracking');
            WC()->session->__unset('billing_email');
            WC()->session->__unset('billing_phone');
        }
    }
    
    /**
     * Track checkout start
     */
    public function track_checkout_start() {
        $session_id = $this->get_session_id();
        $cart = $this->db->get_cart_by_session($session_id);
        
        if ($cart) {
            // Update cart status to checkout
            $this->db->save_cart([
                'session_id' => $session_id,
                'status' => 'checkout',
                'email' => $this->get_user_email(),
                'phone' => $this->get_user_phone(),
                'customer_name' => $this->get_user_name()
            ]);
        }
    }
    
    /**
     * Track checkout progress
     */
    public function track_checkout_progress($order_id, $data) {
        $session_id = $this->get_session_id();
        $cart = $this->db->get_cart_by_session($session_id);
        
        if ($cart) {
            // Update cart with checkout data
            $checkout_data = [
                'session_id' => $session_id,
                'status' => 'checkout',
                'email' => isset($data['billing_email']) ? sanitize_email($data['billing_email']) : $this->get_user_email(),
                'phone' => isset($data['billing_phone']) ? sanitize_text_field($data['billing_phone']) : $this->get_user_phone(),
                'customer_name' => isset($data['billing_first_name']) && isset($data['billing_last_name']) ? 
                    sanitize_text_field($data['billing_first_name']) . ' ' . sanitize_text_field($data['billing_last_name']) : $this->get_user_name(),
                'billing_address' => isset($data['billing_address_1']) ? sanitize_text_field($data['billing_address_1']) : '',
                'billing_city' => isset($data['billing_city']) ? sanitize_text_field($data['billing_city']) : '',
                'billing_country' => isset($data['billing_country']) ? sanitize_text_field($data['billing_country']) : '',
                'billing_postcode' => isset($data['billing_postcode']) ? sanitize_text_field($data['billing_postcode']) : ''
            ];
            
            $this->db->save_cart($checkout_data);
        }
    }
    
    /**
     * Track checkout form submission
     */
    public function track_checkout_form_submission() {
        $session_id = $this->get_session_id();
        $cart = $this->db->get_cart_by_session($session_id);
        
        if ($cart) {
            // Update cart with form submission data
            $this->db->save_cart([
                'session_id' => $session_id,
                'status' => 'checkout',
                'email' => $this->get_user_email(),
                'phone' => $this->get_user_phone(),
                'customer_name' => $this->get_user_name()
            ]);
        }
    }
    
    /**
     * Track customer registration
     */
    public function track_customer_registration($user_id) {
        $session_id = $this->get_session_id();
        $cart = $this->db->get_cart_by_session($session_id);
        
        if ($cart) {
            // Update cart with registered user data
            $user = get_userdata($user_id);
            if ($user) {
                $this->db->save_cart([
                    'session_id' => $session_id,
                    'user_id' => $user_id,
                    'email' => $user->user_email,
                    'customer_name' => $user->first_name . ' ' . $user->last_name,
                    'phone' => get_user_meta($user_id, 'billing_phone', true)
                ]);
            }
        }
    }
    
    /**
     * Track guest checkout attempt
     */
    public function track_guest_checkout_attempt() {
        $session_id = $this->get_session_id();
        $cart = $this->db->get_cart_by_session($session_id);
        
        if (!$cart) {
            // Create cart entry for guest checkout attempt
            $this->save_cart_data($this->get_cart_data());
        }
    }
    
    /**
     * Track order completion
     */
    public function track_order_completion($order_id) {
        $session_id = $this->get_session_id();
        $cart = $this->db->get_cart_by_session($session_id);
        
        if ($cart) {
            // Update cart status to completed
            $this->db->save_cart([
                'session_id' => $session_id,
                'status' => 'completed'
            ]);
        }
    }
    
    /**
     * Track page view for abandonment detection
     */
    public function track_page_view() {
        if (!is_cart() && !is_checkout() && !WC()->cart->is_empty()) {
            // Add JavaScript to track page exit
            $cart_data = $this->get_cart_data();
            $ajax_url = admin_url('admin-ajax.php');
            $nonce = wp_create_nonce('recovero_track_nonce');
            
            echo '<script type="text/javascript">
                jQuery(document).ready(function($) {
                    var cartData = ' . json_encode($cart_data) . ';
                    
                    if (cartData && cartData.length > 0) {
                        // Track page view for potential abandonment
                        $.ajax({
                            url: "' . $ajax_url . '",
                            type: "POST",
                            data: {
                                action: "recovero_track_page_view",
                                cart_data: cartData,
                                nonce: "' . $nonce . '"
                            }
                        });
                    }
                });
            </script>';
        }
    }
    
    /**
     * Check if tracking is enabled
     */
    public function is_tracking_enabled() {
        return $this->is_tracking_enabled;
    }
    
    /**
     * Enable/disable tracking
     */
    public function set_tracking_enabled($enabled) {
        $this->is_tracking_enabled = $enabled;
        update_option('recovero_enable_tracking', $enabled);
    }
    
    /**
     * Get tracking statistics
     */
    public function get_tracking_stats() {
        return $this->db->get_statistics();
    }
}
