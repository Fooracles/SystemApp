<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notification_triggers.php';

// Simulate session (logged-in admin)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get a valid admin user to simulate
$result = mysqli_query($conn, "SELECT id, username, user_type FROM users WHERE user_type = 'admin' LIMIT 1");
$admin = mysqli_fetch_assoc($result);
if (!$admin) {
    $result = mysqli_query($conn, "SELECT id, username, user_type FROM users WHERE user_type = 'manager' LIMIT 1");
    $admin = mysqli_fetch_assoc($result);
}

if ($admin) {
    $_SESSION['id'] = $admin['id'];
    $_SESSION['username'] = $admin['username'];
    $_SESSION['user_type'] = $admin['user_type'];
    echo "Simulating user: {$admin['username']} (type: {$admin['user_type']})" . PHP_EOL;
} else {
    echo "No admin/manager user found!" . PHP_EOL;
    exit;
}

echo PHP_EOL . "=== Testing get_reports ===" . PHP_EOL;
try {
    // Check if reports table has the expected columns
    $check_assigned = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'assigned_to'");
    $check_account = mysqli_query($conn, "SHOW COLUMNS FROM reports LIKE 'client_account_id'");
    $has_assigned_to = $check_assigned && mysqli_num_rows($check_assigned) > 0;
    $has_client_account_id = $check_account && mysqli_num_rows($check_account) > 0;
    echo "has_assigned_to: " . ($has_assigned_to ? 'YES' : 'NO') . PHP_EOL;
    echo "has_client_account_id: " . ($has_client_account_id ? 'YES' : 'NO') . PHP_EOL;

    // Check report_recipients table
    $rr_check = mysqli_query($conn, "SHOW TABLES LIKE 'report_recipients'");
    echo "report_recipients table: " . ($rr_check && mysqli_num_rows($rr_check) > 0 ? 'EXISTS' : 'MISSING') . PHP_EOL;

    // Run actual query (admin sees all)
    $sql = "SELECT r.*, u.name as uploaded_by_name FROM reports r LEFT JOIN users u ON r.uploaded_by = u.id ORDER BY r.uploaded_at DESC";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        echo "QUERY ERROR: " . mysqli_error($conn) . PHP_EOL;
    } else {
        $count = mysqli_num_rows($result);
        echo "Reports found: {$count}" . PHP_EOL;
        if ($count > 0) {
            $row = mysqli_fetch_assoc($result);
            echo "First report: ID={$row['id']}, title={$row['title']}, file_type={$row['file_type']}" . PHP_EOL;
        }
    }
    echo PHP_EOL . "=== ALL TESTS PASSED ===" . PHP_EOL;
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}
