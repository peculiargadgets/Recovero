<?php
/**
 * Recovero Recovery Class
 * Handles cart recovery functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recovero_Recovery {
    
    private $db;
    private $email_template;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Recovero_DB();
        $this->email_template = $this->get_email_template_path();
    }
    
    /**
     * Send recovery email
     */
    public function send_email($cart) {
        try {
            if (empty($cart->email)) {
                return false;
            }
            
            // Check if email is already sent
            if ($this->is_email_already_sent($cart->id)) {
                return false;
            }
            
            $subject = $this->get_email_subject($cart);
            $message = $this->get_email_message($cart);
            $headers = $this->get_email_headers();
            
            $result = wp_mail($cart->email, $subject, $message, $headers);
            
            if ($result) {
                // Log successful email send
                $this->log_email_sent($cart);
                error_log("Recovero: Recovery email sent to {$cart->email} for cart {$cart->id}");
            } else {
                error_log("Recovero: Failed to send recovery email to {$cart->email}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Recovero Email Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if email is already sent
     */
    private function is_email_already_sent($cart_id) {
        global $wpdb;
        
        $logs_table = $this->db->get_table('logs');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$logs_table} 
             WHERE cart_id = %d AND method = 'email' AND status = 'sent'",
            $cart_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Get email subject
     */
    private function get_email_subject($cart) {
        $subject = get_option('recovero_email_subject', __('Complete your purchase', 'recovero'));
        
        // Personalize subject if possible
        if ($cart->user_id > 0) {
            $user = get_userdata($cart->user_id);
            if ($user) {
                $subject = sprintf(__('Hi %s, you left something behind!', 'recovero'), $user->first_name);
            }
        }
        
        return $subject;
    }
    
    /**
     * Get email message
     */
    private function get_email_message($cart) {
        $cart_items = $this->get_cart_items_html($cart);
        $recovery_link = $this->get_recovery_link($cart);
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        // Start output buffering
        ob_start();
        
        // Include email template
        if (file_exists($this->email_template)) {
            include $this->email_template;
        } else {
            // Fallback to inline template
            echo $this->get_fallback_email_template($cart_items, $recovery_link, $site_name, $site_url);
        }
        
        return ob_get_clean();
    }
    
    /**
     * Get cart items HTML
     */
    private function get_cart_items_html($cart) {
        $cart_data = maybe_unserialize($cart->cart_data);
        
        if (!is_array($cart_data) || empty($cart_data)) {
            return '';
        }
        
        $items_html = '';
        $total = 0;
        
        foreach ($cart_data as $item) {
            $product_name = isset($item['name']) ? esc_html($item['name']) : __('Product', 'recovero');
            $quantity = isset($item['quantity']) ? absint($item['quantity']) : 1;
            $price = isset($item['price']) ? floatval($item['price']) : 0;
            $subtotal = $price * $quantity;
            $total += $subtotal;
            
            $items_html .= sprintf(
                '<tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">%s</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">%d</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">%s</td>
                </tr>',
                $product_name,
                $quantity,
                wc_price($price)
            );
        }
        
        // Add total row
        $items_html .= sprintf(
            '<tr>
                <td colspan="2" style="padding: 10px; font-weight: bold;">%s</td>
                <td style="padding: 10px; text-align: right; font-weight: bold;">%s</td>
            </tr>',
            __('Total', 'recovero'),
            wc_price($total)
        );
        
        return $items_html;
    }
    
    /**
     * Get recovery link
     */
    private function get_recovery_link($cart) {
        $token = wp_generate_password(20, false);
        
        // Save token to database
        $this->db->add_recovery_log([
            'cart_id' => $cart->id,
            'method' => 'email',
            'status' => 'sent',
            'token' => $token,
            'sent_at' => current_time('mysql')
        ]);
        
        return add_query_arg([
            'recovero_token' => $token,
            'recovero_cart' => $cart->id
        ], home_url());
    }
    
    /**
     * Get email headers
     */
    private function get_email_headers() {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $from_email = get_option('recovero_email_from', get_option('admin_email'));
        $from_name = get_bloginfo('name');
        
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        $headers[] = 'Reply-To: ' . $from_name . ' <' . $from_email . '>';
        
        return $headers;
    }
    
    /**
     * Log email sent
     */
    private function log_email_sent($cart) {
        // This is handled in get_recovery_link method
        // Keeping this method for consistency
    }
    
    /**
     * Process recovery from token
     */
    public function process_recovery($token) {
        try {
            if (empty($token)) {
                return false;
            }
            
            // Get cart ID from token
            $cart_id = $this->get_cart_id_by_token($token);
            
            if (!$cart_id) {
                return false;
            }
            
            // Mark cart as recovered
            $this->db->mark_recovered($token);
            
            // Restore cart
            $this->restore_cart($cart_id);
            
            // Log recovery
            $this->log_recovery($cart_id, $token);
            
            return wc_get_checkout_url();
            
        } catch (Exception $e) {
            error_log("Recovero Recovery Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cart ID by token
     */
    private function get_cart_id_by_token($token) {
        global $wpdb;
        
        $logs_table = $this->db->get_table('logs');
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT cart_id FROM {$logs_table} WHERE token = %s",
            $token
        ));
        
        return $result ? absint($result) : 0;
    }
    
    /**
     * Restore cart
     */
    private function restore_cart($cart_id) {
        try {
            $cart = $this->db->get_cart($cart_id);
            
            if (!$cart || empty($cart->cart_data)) {
                return false;
            }
            
            $cart_data = maybe_unserialize($cart->cart_data);
            
            if (!is_array($cart_data) || empty($cart_data)) {
                return false;
            }
            
            // Clear current cart
            WC()->cart->empty_cart();
            
            // Add items to cart
            foreach ($cart_data as $item) {
                $product_id = isset($item['product_id']) ? absint($item['product_id']) : 0;
                $quantity = isset($item['quantity']) ? absint($item['quantity']) : 1;
                $variation_id = isset($item['variation_id']) ? absint($item['variation_id']) : 0;
                
                if ($product_id > 0) {
                    WC()->cart->add_to_cart($product_id, $quantity, $variation_id);
                }
            }
            
            // Restore customer data if available
            $this->restore_customer_data($cart);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Recovero Cart Restore Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Restore customer data
     */
    private function restore_customer_data($cart) {
        if (WC()->session && !empty($cart->email)) {
            WC()->session->set('billing_email', $cart->email);
        }
        
        if (WC()->session && !empty($cart->phone)) {
            WC()->session->set('billing_phone', $cart->phone);
        }
    }
    
    /**
     * Log recovery
     */
    private function log_recovery($cart_id, $token) {
        $this->db->add_recovery_log([
            'cart_id' => $cart_id,
            'method' => 'recovery',
            'status' => 'recovered',
            'token' => $token,
            'sent_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Get email template path
     */
    private function get_email_template_path() {
        $template_path = RECOVERO_PATH . 'assets/views/email-template.php';
        
        // Check if custom template exists in theme
        $custom_template = locate_template(['recovero/email-template.php']);
        
        if ($custom_template) {
            return $custom_template;
        }
        
        return $template_path;
    }
    
    /**
     * Get fallback email template
     */
    private function get_fallback_email_template($cart_items, $recovery_link, $site_name, $site_url) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Complete Your Purchase', 'recovero'); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                
                <header style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #2c3e50; margin: 0; font-size: 24px;"><?php _e('You left something behind!', 'recovero'); ?></h1>
                </header>
                
                <section style="margin-bottom: 30px;">
                    <p style="font-size: 16px; color: #555;"><?php _e('Looks like you forgot items in your cart. Complete your order before they\'re gone!', 'recovero'); ?></p>
                    
                    <?php if (!empty($cart_items)): ?>
                    <h2 style="color: #2c3e50; margin-bottom: 15px;"><?php _e('Items in your cart:', 'recovero'); ?></h2>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <thead>
                            <tr style="background-color: #f8f9fa;">
                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;"><?php _e('Product', 'recovero'); ?></th>
                                <th style="padding: 10px; text-align: center; border-bottom: 2px solid #dee2e6;"><?php _e('Quantity', 'recovero'); ?></th>
                                <th style="padding: 10px; text-align: right; border-bottom: 2px solid #dee2e6;"><?php _e('Price', 'recovero'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php echo $cart_items; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </section>
                
                <section style="text-align: center; margin-bottom: 30px;">
                    <a href="<?php echo esc_url($recovery_link); ?>" 
                       style="display: inline-block; background-color: #0073aa; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                        <?php _e('Recover My Cart', 'recovero'); ?>
                    </a>
                </section>
                
                <footer style="text-align: center; color: #777; font-size: 14px;">
                    <p><?php _e('Thanks for shopping with us!', 'recovero'); ?></p>
                    <p><?php echo sprintf(__('© %s %s. All rights reserved.', 'recovero'), date('Y'), $site_name); ?></p>
                    <p><small><?php _e('If you didn\'t request this email, you can safely ignore it.', 'recovero'); ?></small></p>
                </footer>
                
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Send test email
     */
    public function send_test_email($email) {
        try {
            $subject = __('Test Recovery Email', 'recovero');
            $message = $this->get_test_email_message();
            $headers = $this->get_email_headers();
            
            return wp_mail($email, $subject, $message, $headers);
            
        } catch (Exception $e) {
            error_log("Recovero Test Email Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get test email message
     */
    private function get_test_email_message() {
        $site_name = get_bloginfo('name');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                
                <header style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #2c3e50; margin: 0; font-size: 24px;"><?php _e('Test Recovery Email', 'recovero'); ?></h1>
                </header>
                
                <section style="margin-bottom: 30px;">
                    <p style="font-size: 16px; color: #555;">
                        <?php echo sprintf(__('This is a test email from %s to verify that the recovery email system is working correctly.', 'recovero'), $site_name); ?>
                    </p>
                    <p style="font-size: 16px; color: #555;">
                        <?php _e('If you received this email, the system is configured properly and ready to send recovery emails to customers who abandon their carts.', 'recovero'); ?>
                    </p>
                </section>
                
                <footer style="text-align: center; color: #777; font-size: 14px;">
                    <p><?php echo sprintf(__('© %s %s. All rights reserved.', 'recovero'), date('Y'), $site_name); ?></p>
                </footer>
                
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
