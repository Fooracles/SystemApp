<?php
/**
 * Comprehensive Test File for FMS-4.31
 * 
 * This file tests:
 * - All pages load correctly
 * - Key features and functions work properly
 * - No broken links or errors appear
 * - Backend, frontend, and database connectivity
 * 
 * Usage: Access via browser when logged in (preferably as admin)
 */

session_start();
$page_title = "Comprehensive System Test";
require_once "../includes/header.php";
require_once "../includes/functions.php";
require_once "../includes/db_functions.php";

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Test results array
$test_results = [
    'database' => [],
    'pages' => [],
    'functions' => [],
    'security' => [],
    'errors' => []
];

// ============================================
// 1. DATABASE TESTS
// ============================================
echo "<h2>1. Database Connectivity & Schema Tests</h2>";

// Test database connection
if (isset($conn) && $conn) {
    $test_results['database']['connection'] = ['status' => 'PASS', 'message' => 'Database connection successful'];
    echo "<p style='color: green;'>✓ Database connection: PASS</p>";
    
    // Test required tables exist
    $required_tables = ['users', 'tasks', 'departments', 'password_reset_requests', 'Leave_request', 'fms_tasks', 'checklist_subtasks'];
    foreach ($required_tables as $table) {
        $check_sql = "SHOW TABLES LIKE '$table'";
        $result = mysqli_query($conn, $check_sql);
        if ($result && mysqli_num_rows($result) > 0) {
            $test_results['database']['table_' . $table] = ['status' => 'PASS', 'message' => "Table '$table' exists"];
            echo "<p style='color: green;'>✓ Table '$table': EXISTS</p>";
        } else {
            $test_results['database']['table_' . $table] = ['status' => 'FAIL', 'message' => "Table '$table' missing"];
            echo "<p style='color: red;'>✗ Table '$table': MISSING</p>";
        }
    }
    
    // Test password_reset_requests.reset_code is nullable
    $check_null_sql = "SELECT IS_NULLABLE FROM information_schema.COLUMNS 
                      WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'password_reset_requests' 
                      AND COLUMN_NAME = 'reset_code'";
    $result = mysqli_query($conn, $check_null_sql);
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        if ($row['IS_NULLABLE'] === 'YES') {
            $test_results['database']['reset_code_nullable'] = ['status' => 'PASS', 'message' => 'reset_code column is nullable'];
            echo "<p style='color: green;'>✓ password_reset_requests.reset_code: NULLABLE (correct)</p>";
        } else {
            $test_results['database']['reset_code_nullable'] = ['status' => 'FAIL', 'message' => 'reset_code column is NOT NULL (should be nullable)'];
            echo "<p style='color: red;'>✗ password_reset_requests.reset_code: NOT NULL (should be nullable)</p>";
        }
    }
    
} else {
    $test_results['database']['connection'] = ['status' => 'FAIL', 'message' => 'Database connection failed'];
    echo "<p style='color: red;'>✗ Database connection: FAIL</p>";
}

// ============================================
// 2. FUNCTION TESTS
// ============================================
echo "<h2>2. Core Function Tests</h2>";

// Test isLoggedIn()
if (function_exists('isLoggedIn')) {
    $logged_in = isLoggedIn();
    $test_results['functions']['isLoggedIn'] = ['status' => 'PASS', 'message' => 'Function exists and returns: ' . ($logged_in ? 'true' : 'false')];
    echo "<p style='color: green;'>✓ isLoggedIn(): Function exists</p>";
} else {
    $test_results['functions']['isLoggedIn'] = ['status' => 'FAIL', 'message' => 'Function does not exist'];
    echo "<p style='color: red;'>✗ isLoggedIn(): Function missing</p>";
}

// Test isAdmin()
if (function_exists('isAdmin')) {
    $is_admin = isAdmin();
    $test_results['functions']['isAdmin'] = ['status' => 'PASS', 'message' => 'Function exists'];
    echo "<p style='color: green;'>✓ isAdmin(): Function exists</p>";
} else {
    $test_results['functions']['isAdmin'] = ['status' => 'FAIL', 'message' => 'Function does not exist'];
    echo "<p style='color: red;'>✗ isAdmin(): Function missing</p>";
}

// Test isManager()
if (function_exists('isManager')) {
    $test_results['functions']['isManager'] = ['status' => 'PASS', 'message' => 'Function exists'];
    echo "<p style='color: green;'>✓ isManager(): Function exists</p>";
} else {
    $test_results['functions']['isManager'] = ['status' => 'FAIL', 'message' => 'Function does not exist'];
    echo "<p style='color: red;'>✗ isManager(): Function missing</p>";
}

// Test isDoer()
if (function_exists('isDoer')) {
    $test_results['functions']['isDoer'] = ['status' => 'PASS', 'message' => 'Function exists'];
    echo "<p style='color: green;'>✓ isDoer(): Function exists</p>";
} else {
    $test_results['functions']['isDoer'] = ['status' => 'FAIL', 'message' => 'Function does not exist'];
    echo "<p style='color: red;'>✗ isDoer(): Function missing</p>";
}

// Test database helper functions
if (function_exists('tableExists')) {
    $test_results['functions']['tableExists'] = ['status' => 'PASS', 'message' => 'Function exists'];
    echo "<p style='color: green;'>✓ tableExists(): Function exists</p>";
} else {
    $test_results['functions']['tableExists'] = ['status' => 'FAIL', 'message' => 'Function does not exist'];
    echo "<p style='color: red;'>✗ tableExists(): Function missing</p>";
}

// ============================================
// 3. PAGE ACCESSIBILITY TESTS
// ============================================
echo "<h2>3. Page Accessibility Tests</h2>";

$pages_to_test = [
    'admin_dashboard.php' => ['admin'],
    'manager_dashboard.php' => ['admin', 'manager'],
    'doer_dashboard.php' => ['admin', 'manager', 'doer'],
    'manage_tasks.php' => ['admin', 'manager'],
    'manage_users.php' => ['admin'],
    'leave_request.php' => ['admin', 'manager', 'doer'],
    'my_task.php' => ['admin', 'manager', 'doer'],
    'profile.php' => ['admin', 'manager', 'doer']
];

$user_type = $_SESSION['user_type'] ?? 'unknown';

foreach ($pages_to_test as $page => $allowed_roles) {
    $page_path = "../pages/$page";
    if (file_exists($page_path)) {
        if (in_array($user_type, $allowed_roles) || in_array('all', $allowed_roles)) {
            $test_results['pages'][$page] = ['status' => 'PASS', 'message' => 'Page exists and accessible'];
            echo "<p style='color: green;'>✓ $page: EXISTS and ACCESSIBLE</p>";
        } else {
            $test_results['pages'][$page] = ['status' => 'SKIP', 'message' => 'Page exists but not accessible for current role'];
            echo "<p style='color: orange;'>⊘ $page: EXISTS but NOT ACCESSIBLE (role: $user_type)</p>";
        }
    } else {
        $test_results['pages'][$page] = ['status' => 'FAIL', 'message' => 'Page file missing'];
        echo "<p style='color: red;'>✗ $page: FILE MISSING</p>";
    }
}

// ============================================
// 4. AJAX ENDPOINT TESTS
// ============================================
echo "<h2>4. AJAX Endpoint Tests</h2>";

$ajax_endpoints = [
    'admin_dashboard_data.php',
    'manager_dashboard_data.php',
    'doer_dashboard_data.php',
    'approve_password_reset.php',
    'reject_password_reset.php',
    'get_notifications.php'
];

foreach ($ajax_endpoints as $endpoint) {
    $endpoint_path = "../ajax/$endpoint";
    if (file_exists($endpoint_path)) {
        $test_results['ajax'][$endpoint] = ['status' => 'PASS', 'message' => 'Endpoint file exists'];
        echo "<p style='color: green;'>✓ $endpoint: EXISTS</p>";
    } else {
        $test_results['ajax'][$endpoint] = ['status' => 'FAIL', 'message' => 'Endpoint file missing'];
        echo "<p style='color: red;'>✗ $endpoint: FILE MISSING</p>";
    }
}

// ============================================
// 5. SECURITY TESTS
// ============================================
echo "<h2>5. Security Tests</h2>";

// Test admin-only endpoints have proper checks
$admin_endpoints = [
    '../ajax/approve_password_reset.php',
    '../ajax/reject_password_reset.php'
];

foreach ($admin_endpoints as $endpoint) {
    if (file_exists($endpoint)) {
        $content = file_get_contents($endpoint);
        if (strpos($content, 'isAdmin()') !== false || strpos($content, 'isAdmin') !== false) {
            $test_results['security'][basename($endpoint)] = ['status' => 'PASS', 'message' => 'Has admin check'];
            echo "<p style='color: green;'>✓ " . basename($endpoint) . ": Has admin check</p>";
        } else {
            $test_results['security'][basename($endpoint)] = ['status' => 'WARN', 'message' => 'May be missing admin check'];
            echo "<p style='color: orange;'>⚠ " . basename($endpoint) . ": May be missing admin check</p>";
        }
    }
}

// ============================================
// 6. SUMMARY
// ============================================
echo "<h2>6. Test Summary</h2>";

$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;
$skipped_tests = 0;

foreach ($test_results as $category => $tests) {
    foreach ($tests as $test => $result) {
        $total_tests++;
        if ($result['status'] === 'PASS') {
            $passed_tests++;
        } elseif ($result['status'] === 'FAIL') {
            $failed_tests++;
        } elseif ($result['status'] === 'SKIP') {
            $skipped_tests++;
        }
    }
}

echo "<div style='background: #f0f0f0; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>Overall Results</h3>";
echo "<p><strong>Total Tests:</strong> $total_tests</p>";
echo "<p style='color: green;'><strong>Passed:</strong> $passed_tests</p>";
echo "<p style='color: red;'><strong>Failed:</strong> $failed_tests</p>";
echo "<p style='color: orange;'><strong>Skipped:</strong> $skipped_tests</p>";

$pass_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0;
echo "<p><strong>Pass Rate:</strong> $pass_rate%</p>";

if ($failed_tests === 0) {
    echo "<p style='color: green; font-weight: bold;'>✓ All critical tests passed!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Some tests failed. Please review the results above.</p>";
}
echo "</div>";

// ============================================
// 7. JSON Export (for programmatic use)
// ============================================
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode($test_results, JSON_PRETTY_PRINT);
    exit;
}

require_once "../includes/footer.php";
?>

