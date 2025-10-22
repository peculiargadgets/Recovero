<?php
/**
 * Recovero Database Class
 * Handles all database operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recovero_DB {
    
    private $wpdb;
    private $tables = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->init_tables();
    }
    
    /**
     * Initialize table names
     */
    private function init_tables() {
        $prefix = $this->wpdb->prefix;
        
        $this->tables = [
            'carts' => $prefix . 'recovero_abandoned_carts',
            'logs' => $prefix . 'recovero_recovery_logs',
            'geo' => $prefix . 'recovero_geo_data',
            'licenses' => $prefix . 'recovero_license_keys'
        ];
    }
    
    /**
     * Get table name
     */
    public function get_table($name) {
        return isset($this->tables[$name]) ? $this->tables[$name] : null;
    }
    
    /**
     * Save or update cart data
     */
    public function save_cart($data) {
        try {
            $table = $this->tables['carts'];
            
            // Validate required fields
            if (empty($data['session_id'])) {
                return false;
            }
            
            // Sanitize data
            $sanitized_data = $this->sanitize_cart_data($data);
            
            // Check if cart exists
            $existing = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT id FROM {$table} WHERE session_id = %s",
                $sanitized_data['session_id']
            ));
            
            if ($existing) {
                // Update existing cart
                $result = $this->wpdb->update(
                    $table,
                    $sanitized_data,
                    ['session_id' => $sanitized_data['session_id']],
                    $this->get_cart_data_format(),
                    ['%s']
                );
            } else {
                // Insert new cart
                $result = $this->wpdb->insert($table, $sanitized_data, $this->get_cart_data_format());
            }
            
            return $result !== false;
            
        } catch (Exception $e) {
            error_log("Recovero DB Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitize cart data
     */
    private function sanitize_cart_data($data) {
        return [
            'user_id' => isset($data['user_id']) ? absint($data['user_id']) : null,
            'session_id' => sanitize_text_field($data['session_id']),
            'email' => isset($data['email']) ? sanitize_email($data['email']) : null,
            'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : null,
            'cart_data' => isset($data['cart_data']) ? maybe_serialize($data['cart_data']) : null,
            'ip' => isset($data['ip']) ? sanitize_text_field($data['ip']) : null,
            'location' => isset($data['location']) ? sanitize_textarea_field($data['location']) : null,
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'abandoned',
            'created_at' => isset($data['created_at']) ? $data['created_at'] : current_time('mysql'),
            'customer_name' => isset($data['customer_name']) ? sanitize_text_field($data['customer_name']) : null,
            'billing_address' => isset($data['billing_address']) ? sanitize_text_field($data['billing_address']) : null,
            'billing_city' => isset($data['billing_city']) ? sanitize_text_field($data['billing_city']) : null,
            'billing_country' => isset($data['billing_country']) ? sanitize_text_field($data['billing_country']) : null,
            'billing_postcode' => isset($data['billing_postcode']) ? sanitize_text_field($data['billing_postcode']) : null
        ];
    }
    
    /**
     * Get cart data format
     */
    private function get_cart_data_format() {
        return [
            '%d', // user_id
            '%s', // session_id
            '%s', // email
            '%s', // phone
            '%s', // cart_data
            '%s', // ip
            '%s', // location
            '%s', // status
            '%s', // created_at
            '%s', // customer_name
            '%s', // billing_address
            '%s', // billing_city
            '%s', // billing_country
            '%s'  // billing_postcode
        ];
    }
    
    /**
     * Get abandoned carts
     */
    public function get_abandoned_carts($limit = 50, $offset = 0) {
        try {
            $table = $this->tables['carts'];
            
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE status = 'abandoned' 
                 ORDER BY created_at DESC 
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ));
            
        } catch (Exception $e) {
            error_log("Recovero DB Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get cart by ID
     */
    public function get_cart($cart_id) {
        try {
            $table = $this->tables['carts'];
            
            return $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $cart_id
            ));
            
        } catch (Exception $e) {
            error_log("Recovero DB Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get cart by session ID
     */
    public function get_cart_by_session($session_id) {
        try {
            $table = $this->tables['carts'];
            
            return $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE session_id = %s",
                $session_id
            ));
            
        } catch (Exception $e) {
            error_log("Recovero DB Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Mark cart as recovered
     */
    public function mark_recovered($token) {
        try {
            $logs_table = $this->tables['logs'];
            $carts_table = $this->tables['carts'];
            
            // Get cart ID from token
            $log = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT cart_id FROM {$logs_table} WHERE token = %s",
                $token
            ));
            
            if ($log) {
                // Update cart status
                $result = $this->wpdb->update(
                    $carts_table,
                    ['status' => 'recovered'],
                    ['id' => $log->cart_id],
                    ['%s'],
                    ['%d']
                );
                
                return $result !== false;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Recovero DB Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add recovery log
     */
    public function add_recovery_log($data) {
        try {
            $table = $this->tables['logs'];
            
            $sanitized_data = [
                'cart_id' => isset($data['cart_id']) ? absint($data['cart_id']) : null,
                'method' => isset($data['method']) ? sanitize_text_field($data['method']) : null,
                'status' => isset($data['status']) ? sanitize_text_field($data['status']) : null,
                'token' => isset($data['token']) ? sanitize_text_field($data['token']) : null,
                'sent_at' => isset($data['sent_at']) ? $data['sent_at'] : current_time('mysql')
            ];
            
            return $this->wpdb->insert($table, $sanitized_data, ['%d', '%s', '%s', '%s', '%s']);
            
        } catch (Exception $e) {
            error_log("Recovero DB Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get recovery logs
     */
    public function get_recovery_logs($limit = 50, $offset = 0) {
        try {
            $table = $this->tables['logs'];
            
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$table} 
                 ORDER BY sent_at DESC 
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ));
            
        } catch (Exception $e) {
            error_log("Recovero DB Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Save geo data
     */
    public function save_geo_data($data) {
        try {
            $table = $this->tables['geo'];
            
            $sanitized_data = [
                'ip' => isset($data['ip']) ? sanitize_text_field($data['ip']) : null,
                'country' => isset($data['country']) ? sanitize_text_field($data['country']) : null,
                'city' => isset($data['city']) ? sanitize_text_field($data['city']) : null,
                'lat' => isset($data['lat']) ? sanitize_text_field($data['lat']) : null,
                'lon' => isset($data['lon']) ? sanitize_text_field($data['lon']) : null,
                'browser' => isset($data['browser']) ? sanitize_text_field($data['browser']) : null,
                'device' => isset($data['device']) ? sanitize_text_field($data['device']) : null,
                'last_seen' => current_time('mysql')
            ];
            
            // Check if IP exists
            $existing = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT id FROM {$table} WHERE ip = %s",
                $sanitized_data['ip']
            ));
            
            if ($existing) {
                // Update existing record
                return $this->wpdb->update(
                    $table,
                    $sanitized_data,
                    ['ip' => $sanitized_data['ip']],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                    ['%s']
                );
            } else {
                // Insert new record
                return $this->wpdb->insert($table, $sanitized_data, ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            }
            
        } catch (Exception $e) {
            error_log("Recovero DB Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get statistics
     */
    public function get_statistics() {
        try {
            $carts_table = $this->tables['carts'];
            $logs_table = $this->tables['logs'];
            
            $stats = [];
            
            // Total carts
            $stats['total_carts'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$carts_table}");
            
            // Abandoned carts
            $stats['abandoned_carts'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$carts_table} WHERE status = 'abandoned'");
            
            // Recovered carts
            $stats['recovered_carts'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$carts_table} WHERE status = 'recovered'");
            
            // Recovery rate
            $stats['recovery_rate'] = $stats['total_carts'] > 0 ? round(($stats['recovered_carts'] / $stats['total_carts']) * 100, 2) : 0;
            
            // Total emails sent
            $stats['emails_sent'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$logs_table} WHERE method = 'email'");
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Recovero DB Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete old data
     */
    public function delete_old_data($days = 90) {
        try {
            $threshold = date('Y-m-d H:i:s', strtotime("-{$days} days", current_time('timestamp')));
            
            $deleted = 0;
            
            // Delete old carts
            $deleted += $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM {$this->tables['carts']} WHERE created_at < %s",
                $threshold
            ));
            
            // Delete old logs
            $deleted += $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM {$this->tables['logs']} WHERE sent_at < %s",
                $threshold
            ));
            
            // Delete old geo data
            $deleted += $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM {$this->tables['geo']} WHERE last_seen < %s",
                $threshold
            ));
            
            return $deleted;
            
        } catch (Exception $e) {
            error_log("Recovero DB Error: " . $e->getMessage());
            return 0;
        }
    }
}
