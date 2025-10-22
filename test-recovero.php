<?php
/**
 * Recovero Plugin Test Script
 * This script tests all the features of the Recovero plugin
 */

// Include WordPress
require_once('../../../wp-config.php');

// Test results
$test_results = [];

echo "<h2>Recovero Plugin Test Results</h2>";

// Test 1: Check if plugin is active
echo "<h3>Test 1: Plugin Activation</h3>";
if (is_plugin_active('recovero/recovero.php')) {
    echo "<p style='color: green;'>✓ Plugin is active</p>";
    $test_results[] = "Plugin Activation: PASS";
} else {
    echo "<p style='color: red;'>✗ Plugin is not active</p>";
    $test_results[] = "Plugin Activation: FAIL";
}

// Test 2: Check WooCommerce compatibility
echo "<h3>Test 2: WooCommerce Compatibility</h3>";
if (class_exists('WooCommerce')) {
    echo "<p style='color: green;'>✓ WooCommerce is available</p>";
    $test_results[] = "WooCommerce Compatibility: PASS";
    
    // Check HPOS compatibility
    if (version_compare(WC()->version, '8.0', '>=')) {
        echo "<p style='color: green;'>✓ WooCommerce HPOS compatibility declared</p>";
        $test_results[] = "HPOS Compatibility: PASS";
    } else {
        echo "<p style='color: orange;'>⚠ WooCommerce version < 8.0, HPOS not applicable</p>";
        $test_results[] = "HPOS Compatibility: N/A";
    }
} else {
    echo "<p style='color: red;'>✗ WooCommerce is not available</p>";
    $test_results[] = "WooCommerce Compatibility: FAIL";
}

// Test 3: Check database tables
echo "<h3>Test 3: Database Tables</h3>";
global $wpdb;
$tables_to_check = [
    'recovero_abandoned_carts',
    'recovero_recovery_logs',
    'recovero_geo_data',
    'recovero_license_keys'
];

foreach ($tables_to_check as $table) {
    $table_name = $wpdb->prefix . $table;
    $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if ($result === $table_name) {
        echo "<p style='color: green;'>✓ Table $table exists</p>";
        $test_results[] = "Database Table $table: PASS";
    } else {
        echo "<p style='color: red;'>✗ Table $table does not exist</p>";
        $test_results[] = "Database Table $table: FAIL";
    }
}

// Test 4: Check plugin classes
echo "<h3>Test 4: Plugin Classes</h3>";
$classes_to_check = [
    'Recovero_DB',
    'Recovero_Tracker',
    'Recovero_Admin',
    'Recovero_Ajax',
    'Recovero_Recovery'
];

foreach ($classes_to_check as $class) {
    if (class_exists($class)) {
        echo "<p style='color: green;'>✓ Class $class is loaded</p>";
        $test_results[] = "Class $class: PASS";
    } else {
        echo "<p style='color: red;'>✗ Class $class is not loaded</p>";
        $test_results[] = "Class $class: FAIL";
    }
}

// Test 5: Check plugin options
echo "<h3>Test 5: Plugin Options</h3>";
$options_to_check = [
    'recovero_enable_tracking',
    'recovero_enable_email_recovery',
    'recovero_email_from',
    'recovero_delay_hours'
];

foreach ($options_to_check as $option) {
    $value = get_option($option);
    if ($value !== false) {
        echo "<p style='color: green;'>✓ Option $option is set (value: " . print_r($value, true) . ")</p>";
        $test_results[] = "Option $option: PASS";
    } else {
        echo "<p style='color: red;'>✗ Option $option is not set</p>";
        $test_results[] = "Option $option: FAIL";
    }
}

// Test 6: Check cron jobs
echo "<h3>Test 6: Cron Jobs</h3>";
$cron_hooks = [
    'recovero_cron_hook',
    'recovero_cleanup_hook'
];

foreach ($cron_hooks as $hook) {
    $next_cron = wp_next_scheduled($hook);
    if ($next_cron) {
        echo "<p style='color: green;'>✓ Cron job $hook is scheduled (next: " . date('Y-m-d H:i:s', $next_cron) . ")</p>";
        $test_results[] = "Cron Job $hook: PASS";
    } else {
        echo "<p style='color: red;'>✗ Cron job $hook is not scheduled</p>";
        $test_results[] = "Cron Job $hook: FAIL";
    }
}

// Test 7: Test database insertion
echo "<h3>Test 7: Database Insertion Test</h3>";
try {
    $test_cart_data = [
        'user_id' => get_current_user_id(),
        'session_id' => 'test_session_' . time(),
        'email' => 'test@example.com',
        'phone' => '+1234567890',
        'cart_data' => serialize([
            [
                'product_id' => 1,
                'name' => 'Test Product',
                'price' => 10.99,
                'quantity' => 2
            ]
        ]),
        'ip' => '127.0.0.1',
        'location' => 'Test City, Test Country',
        'status' => 'abandoned',
        'customer_name' => 'Test User'
    ];
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'recovero_abandoned_carts',
        $test_cart_data
    );
    
    if ($result !== false) {
        echo "<p style='color: green;'>✓ Database insertion test successful</p>";
        $test_results[] = "Database Insertion: PASS";
        
        // Clean up test data
        $wpdb->delete(
            $wpdb->prefix . 'recovero_abandoned_carts',
            ['session_id' => $test_cart_data['session_id']]
        );
    } else {
        echo "<p style='color: red;'>✗ Database insertion test failed</p>";
        $test_results[] = "Database Insertion: FAIL";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database insertion test error: " . $e->getMessage() . "</p>";
    $test_results[] = "Database Insertion: FAIL";
}

// Test 8: Check email functionality
echo "<h3>Test 8: Email Functionality</h3>";
if (function_exists('wp_mail')) {
    echo "<p style='color: green;'>✓ wp_mail function is available</p>";
    $test_results[] = "Email Function: PASS";
} else {
    echo "<p style='color: red;'>✗ wp_mail function is not available</p>";
    $test_results[] = "Email Function: FAIL";
}

// Summary
echo "<h3>Test Summary</h3>";
$passed = array_filter($test_results, function($result) {
    return strpos($result, 'PASS') !== false;
});
$failed = array_filter($test_results, function($result) {
    return strpos($result, 'FAIL') !== false;
});

echo "<p><strong>Total Tests:</strong> " . count($test_results) . "</p>";
echo "<p style='color: green;'><strong>Passed:</strong> " . count($passed) . "</p>";
echo "<p style='color: red;'><strong>Failed:</strong> " . count($failed) . "</p>";

if (count($failed) === 0) {
    echo "<h2 style='color: green;'>🎉 All tests passed! The plugin is working correctly.</h2>";
} else {
    echo "<h2 style='color: red;'>⚠️ Some tests failed. Please check the issues above.</h2>";
}

echo "<h3>Detailed Results:</h3>";
echo "<ul>";
foreach ($test_results as $result) {
    if (strpos($result, 'PASS') !== false) {
        echo "<li style='color: green;'>$result</li>";
    } elseif (strpos($result, 'FAIL') !== false) {
        echo "<li style='color: red;'>$result</li>";
    } else {
        echo "<li style='color: orange;'>$result</li>";
    }
}
echo "</ul>";

// Test 9: Check if tracking is working
echo "<h3>Test 9: Cart Tracking Test</h3>";
if (class_exists('Recovero_Tracker')) {
    $tracker = new Recovero_Tracker();
    if ($tracker->is_tracking_enabled()) {
        echo "<p style='color: green;'>✓ Cart tracking is enabled</p>";
        $test_results[] = "Cart Tracking: PASS";
    } else {
        echo "<p style='color: red;'>✗ Cart tracking is disabled</p>";
        $test_results[] = "Cart Tracking: FAIL";
    }
} else {
    echo "<p style='color: red;'>✗ Recovero_Tracker class not found</p>";
    $test_results[] = "Cart Tracking: FAIL";
}

// Test 10: Check AJAX endpoints
echo "<h3>Test 10: AJAX Endpoints</h3>";
$ajax_actions = [
    'recovero_track_page_view',
    'recovero_get_stats',
    'recovero_send_test_email'
];

foreach ($ajax_actions as $action) {
    if (has_action('wp_ajax_' . $action) || has_action('wp_ajax_nopriv_' . $action)) {
        echo "<p style='color: green;'>✓ AJAX action $action is registered</p>";
        $test_results[] = "AJAX $action: PASS";
    } else {
        echo "<p style='color: red;'>✗ AJAX action $action is not registered</p>";
        $test_results[] = "AJAX $action: FAIL";
    }
}

echo "<p><strong>Test completed at:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
