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
        
        // Track user logout
        add_action('wp_logout', [$this, 'clear_session']);
        
        // Track checkout process
        add_action('woocommerce_before_checkout_process', [$this, 'track_checkout_start']);
        
        // Track order completion
        add_action('woocommerce_thankyou', [$this, 'track_order_completion']);
        
        // Track page views for cart abandonment
        add_action('wp_footer', [$this, 'track_page_view']);
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
                'price' => $product->get_price(),
                'quantity' => $cart_item['quantity'],
                'subtotal' => $cart_item['line_subtotal'],
                'total' => $cart_item['line_total'],
                'variation_id' => isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0,
                'cart_item_key' => $cart_item_key
            ];
        }
        
        return $cart_items;
    }
    
    /**
     * Save cart data
     */
    private function save_cart_data($cart_data) {
        $user_id = get_current_user_id();
        $session_id = $this->get_session_id();
        $email = $this->get_user_email();
        $phone = $this->get_user_phone();
        $ip = $this->get_user_ip();
        
        $cart_info = [
            'user_id' => $user_id,
            'session_id' => $session_id,
            'email' => $email,
            'phone' => $phone,
            'cart_data' => $cart_data,
            'ip' => $ip,
            'location' => $this->get_user_location($ip),
            'status' => 'abandoned'
        ];
        
        $this->db->save_cart($cart_info);
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
                'phone' => $this->get_user_phone()
            ]);
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
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    var cartData = <?php echo json_encode($this->get_cart_data()); ?>;
                    
                    if (cartData && cartData.length > 0) {
                        // Track page view for potential abandonment
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'recovero_track_page_view',
                                cart_data: cartData,
                                nonce: '<?php echo wp_create_nonce('recovero_track_nonce'); ?>'
                            }
                        });
                    }
                });
            </script>
            <?php
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
