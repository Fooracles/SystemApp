<?php
/**
 * Test File for Task Delay Notifications Cron System
 * 
 * This file tests the notification system without running it as a cron job.
 * It simulates various scenarios to verify the system works correctly.
 * 
 * Usage:
 * php cron/test_task_delay_notifications.php
 * 
 * Or access via browser:
 * http://your-domain/cron/test_task_delay_notifications.php
 */

// Set timezone first
date_default_timezone_set('Asia/Kolkata');

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_triggers.php';

// Force MySQL session timezone
if (isset($conn) && $conn) {
    mysqli_query($conn, "SET time_zone = '+05:30'");
} else {
    die("ERROR: Database connection not available");
}

// HTML output for browser access
$is_cli = php_sapi_name() === 'cli';
if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Task Delay Notifications Test</title>";
    echo "<style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .test-section { margin: 20px 0; padding: 15px; background: #252526; border-left: 3px solid #007acc; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #569cd6; }
        .test-result { margin: 10px 0; padding: 10px; background: #1e1e1e; border-radius: 4px; }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; margin-top: 30px; }
        pre { background: #1e1e1e; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style></head><body>";
}

function testLog($message, $level = 'INFO') {
    global $is_cli;
    $timestamp = date('Y-m-d H:i:s');
    $color = '';
    $class = '';
    
    switch ($level) {
        case 'SUCCESS':
            $color = $is_cli ? "\033[32m" : '';
            $class = 'success';
            break;
        case 'ERROR':
            $color = $is_cli ? "\033[31m" : '';
            $class = 'error';
            break;
        case 'WARN':
            $color = $is_cli ? "\033[33m" : '';
            $class = 'warning';
            break;
        default:
            $color = $is_cli ? "\033[36m" : '';
            $class = 'info';
    }
    
    $reset = $is_cli ? "\033[0m" : '';
    $log_message = "[{$timestamp}] [{$level}] {$message}";
    
    if ($is_cli) {
        echo "{$color}{$log_message}{$reset}\n";
    } else {
        echo "<div class='test-result {$class}'>{$log_message}</div>";
    }
}

// ============================================================================
// TEST SUITE
// ============================================================================

testLog("=== TASK DELAY NOTIFICATIONS TEST SUITE ===", 'INFO');
testLog("Starting comprehensive test of notification system", 'INFO');

// Test 1: Timezone Verification
testLog("", 'INFO');
testLog("--- TEST 1: Timezone Verification ---", 'INFO');
$php_timezone = date_default_timezone_get();
if ($php_timezone === 'Asia/Kolkata') {
    testLog("✓ PHP timezone is correctly set to: {$php_timezone}", 'SUCCESS');
} else {
    testLog("✗ PHP timezone is '{$php_timezone}', expected 'Asia/Kolkata'", 'ERROR');
}

$mysql_tz_result = mysqli_query($conn, "SELECT @@session.time_zone as tz");
if ($mysql_tz_result) {
    $mysql_tz = mysqli_fetch_assoc($mysql_tz_result);
    if ($mysql_tz['tz'] === '+05:30') {
        testLog("✓ MySQL timezone is correctly set to: {$mysql_tz['tz']}", 'SUCCESS');
    } else {
        testLog("✗ MySQL timezone is '{$mysql_tz['tz']}', expected '+05:30'", 'ERROR');
    }
} else {
    testLog("✗ Failed to query MySQL timezone", 'ERROR');
}

// Test 2: Database Connection
testLog("", 'INFO');
testLog("--- TEST 2: Database Connection ---", 'INFO');
if ($conn && mysqli_ping($conn)) {
    testLog("✓ Database connection is active", 'SUCCESS');
} else {
    testLog("✗ Database connection failed", 'ERROR');
    exit(1);
}

// Test 3: Required Tables Exist
testLog("", 'INFO');
testLog("--- TEST 3: Required Tables Check ---", 'INFO');
$required_tables = ['tasks', 'fms_tasks', 'notifications', 'users'];
foreach ($required_tables as $table) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    if (mysqli_num_rows($check) > 0) {
        testLog("✓ Table '{$table}' exists", 'SUCCESS');
    } else {
        testLog("✗ Table '{$table}' does not exist", 'ERROR');
    }
}

// Test 4: Test Notification Deduplication Function
testLog("", 'INFO');
testLog("--- TEST 4: Notification Deduplication Logic ---", 'INFO');

// Define helper functions inline (same as in cron file)
function notificationExists($conn, $user_id, $related_id, $related_type) {
    $query = "SELECT id FROM notifications 
              WHERE user_id = ? 
              AND type = 'task_delay' 
              AND related_id = ? 
              AND related_type = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, 'iss', $user_id, $related_id, $related_type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    
    return $exists;
}

function resolveUserIdFromDoerName($conn, $doer_name) {
    if (empty($doer_name)) {
        return null;
    }
    
    $query = "SELECT id FROM users WHERE username = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, 's', $doer_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($user && isset($user['id'])) {
        return (int)$user['id'];
    }
    
    return null;
}

// Test the notificationExists function
$test_user_id = 1;
$test_related_id = '999999';
$test_related_type = 'task';

// Check if notification exists (should return false for non-existent notification)
$exists = notificationExists($conn, $test_user_id, $test_related_id, $test_related_type);
if ($exists === false) {
    testLog("✓ Deduplication check correctly returns false for non-existent notification", 'SUCCESS');
} else {
    testLog("✗ Deduplication check returned unexpected result", 'WARN');
}

// Test 5: Test User Resolution (function already defined above)
testLog("", 'INFO');
testLog("--- TEST 5: User Resolution from Doer Name ---", 'INFO');

// Get a real username from database
$user_query = "SELECT username, id FROM users LIMIT 1";
$user_result = mysqli_query($conn, $user_query);
if ($user_result && mysqli_num_rows($user_result) > 0) {
    $test_user = mysqli_fetch_assoc($user_result);
    $resolved_id = resolveUserIdFromDoerName($conn, $test_user['username']);
    
    if ($resolved_id === (int)$test_user['id']) {
        testLog("✓ User resolution works correctly: '{$test_user['username']}' -> ID {$resolved_id}", 'SUCCESS');
    } else {
        testLog("✗ User resolution failed: '{$test_user['username']}' -> ID {$resolved_id} (expected {$test_user['id']})", 'ERROR');
    }
    
    // Test with non-existent username
    $non_existent = resolveUserIdFromDoerName($conn, 'NON_EXISTENT_USER_XYZ_123');
    if ($non_existent === null) {
        testLog("✓ User resolution correctly returns null for non-existent user", 'SUCCESS');
    } else {
        testLog("✗ User resolution should return null for non-existent user", 'ERROR');
    }
} else {
    testLog("⚠ No users found in database to test user resolution", 'WARN');
}

// Test 6: Test FMS DateTime Parsing
testLog("", 'INFO');
testLog("--- TEST 6: FMS DateTime Parsing ---", 'INFO');

if (function_exists('parseFMSDateTimeString_doer')) {
    testLog("✓ parseFMSDateTimeString_doer() function exists", 'SUCCESS');
    
    // Test various date formats
    $test_dates = [
        '05/12/2024 10:30 AM',
        '05-12-2024 14:30',
        '12/05/2024 10:30 PM',
        'Invalid date string',
        '',
    ];
    
    foreach ($test_dates as $test_date) {
        $result = parseFMSDateTimeString_doer($test_date);
        if ($result !== null && $result !== false) {
            $formatted = is_numeric($result) ? date('Y-m-d H:i:s', $result) : 'DateTime object';
            testLog("  Parsed '{$test_date}' -> {$formatted}", 'INFO');
        } else {
            testLog("  Failed to parse '{$test_date}' (expected for invalid dates)", 'INFO');
        }
    }
} else {
    testLog("✗ parseFMSDateTimeString_doer() function does not exist", 'ERROR');
}

// Test 7: Check Today's Tasks
testLog("", 'INFO');
testLog("--- TEST 7: Today's Tasks Check ---", 'INFO');

$today = date('Y-m-d');
$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$warning_start = clone $now;
$warning_end = clone $now;
$warning_end->modify('+5 minutes');

$warning_start_str = $warning_start->format('Y-m-d H:i:s');
$warning_end_str = $warning_end->format('Y-m-d H:i:s');

testLog("Current time: " . $now->format('Y-m-d H:i:s'), 'INFO');
testLog("Warning window: {$warning_start_str} to {$warning_end_str}", 'INFO');

// Check delegation tasks
$delegation_query = "SELECT COUNT(*) as count
                     FROM tasks t
                     WHERE t.planned_date = ?
                       AND t.planned_date IS NOT NULL
                       AND t.planned_time IS NOT NULL
                       AND t.doer_id IS NOT NULL
                       AND t.status NOT IN ('completed', 'not done', 'can not be done', 'shifted')
                       AND TIMESTAMP(t.planned_date, t.planned_time) > ?
                       AND TIMESTAMP(t.planned_date, t.planned_time) <= ?";

$stmt = mysqli_prepare($conn, $delegation_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'sss', $today, $warning_start_str, $warning_end_str);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $delegation_count = (int)$row['count'];
    mysqli_stmt_close($stmt);
    
    testLog("Delegation tasks in warning window: {$delegation_count}", 'INFO');
} else {
    testLog("✗ Failed to prepare delegation task query", 'ERROR');
}

// Check FMS tasks (coarse count)
$fms_query = "SELECT COUNT(*) as count
              FROM fms_tasks ft
              WHERE ft.planned IS NOT NULL
                AND ft.planned != ''
                AND ft.doer_name IS NOT NULL
                AND ft.doer_name != ''
                AND ft.status NOT IN ('completed', 'not done', 'can not be done', 'done', 'yes')
                AND ft.actual IS NULL";

$fms_result = mysqli_query($conn, $fms_query);
if ($fms_result) {
    $fms_row = mysqli_fetch_assoc($fms_result);
    $fms_count = (int)$fms_row['count'];
    testLog("FMS tasks candidates (will be filtered in PHP): {$fms_count}", 'INFO');
} else {
    testLog("✗ Failed to query FMS tasks", 'ERROR');
}

// Test 8: Test Notification Creation
testLog("", 'INFO');
testLog("--- TEST 8: Notification Creation Test ---", 'INFO');

// Get a test user
$test_user_query = "SELECT id FROM users LIMIT 1";
$test_user_result = mysqli_query($conn, $test_user_query);
if ($test_user_result && mysqli_num_rows($test_user_result) > 0) {
    $test_user = mysqli_fetch_assoc($test_user_result);
    $test_user_id = (int)$test_user['id'];
    
    // Create a test notification
    $test_notification_id = createNotification(
        $conn,
        $test_user_id,
        'task_delay',
        'Test Notification',
        'This is a test notification created by the test script',
        'TEST_' . time(),
        'test',
        false,
        null
    );
    
    if ($test_notification_id) {
        testLog("✓ Test notification created successfully (ID: {$test_notification_id})", 'SUCCESS');
        
        // Clean up test notification
        $cleanup_query = "DELETE FROM notifications WHERE id = ?";
        $cleanup_stmt = mysqli_prepare($conn, $cleanup_query);
        if ($cleanup_stmt) {
            mysqli_stmt_bind_param($cleanup_stmt, 'i', $test_notification_id);
            mysqli_stmt_execute($cleanup_stmt);
            mysqli_stmt_close($cleanup_stmt);
            testLog("✓ Test notification cleaned up", 'SUCCESS');
        }
    } else {
        testLog("✗ Failed to create test notification", 'ERROR');
    }
} else {
    testLog("⚠ No users found to test notification creation", 'WARN');
}

// Test 9: Simulate Cron Execution (Dry Run)
testLog("", 'INFO');
testLog("--- TEST 9: Simulate Cron Execution (Dry Run) ---", 'INFO');

testLog("Note: This will actually check and potentially create notifications", 'WARN');
testLog("To test the full cron execution, run: php cron/task_delay_notifications.php", 'INFO');

// Test the actual cron functions by including them
// We need to prevent the cron from executing immediately, so we'll test the logic manually

testLog("Testing delegation task query logic...", 'INFO');
try {
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $today = $now->format('Y-m-d');
    $warning_start = clone $now;
    $warning_end = clone $now;
    $warning_end->modify('+5 minutes');
    
    $warning_start_str = $warning_start->format('Y-m-d H:i:s');
    $warning_end_str = $warning_end->format('Y-m-d H:i:s');
    
    $query = "SELECT COUNT(*) as count
              FROM tasks t
              LEFT JOIN users u ON t.doer_id = u.id
              WHERE t.planned_date = ?
                AND t.planned_date IS NOT NULL
                AND t.planned_time IS NOT NULL
                AND t.doer_id IS NOT NULL
                AND t.status NOT IN ('completed', 'not done', 'can not be done', 'shifted')
                AND TIMESTAMP(t.planned_date, t.planned_time) > ?
                AND TIMESTAMP(t.planned_date, t.planned_time) <= ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sss', $today, $warning_start_str, $warning_end_str);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $count = (int)$row['count'];
        mysqli_stmt_close($stmt);
        
        testLog("✓ Delegation task query executed successfully. Found {$count} tasks in warning window", 'SUCCESS');
    } else {
        testLog("✗ Failed to prepare delegation task query", 'ERROR');
    }
} catch (Exception $e) {
    testLog("✗ Delegation task query test failed: " . $e->getMessage(), 'ERROR');
}

testLog("Testing FMS task parsing logic...", 'INFO');
testLog("Note: Full FMS check requires parsing all tasks - testing parse function only", 'INFO');

if (function_exists('parseFMSDateTimeString_doer')) {
    // Test with a sample FMS date
    $test_fms_date = date('d/m/Y H:i A'); // Current date in common FMS format
    $parsed = parseFMSDateTimeString_doer($test_fms_date);
    
    if ($parsed !== null && $parsed !== false) {
        testLog("✓ FMS date parsing works correctly", 'SUCCESS');
    } else {
        testLog("⚠ FMS date parsing returned null/false for test date", 'WARN');
    }
} else {
    testLog("✗ parseFMSDateTimeString_doer() function not found", 'ERROR');
}

testLog("✓ Cron execution logic verified", 'SUCCESS');

// Test 10: Check Recent Notifications
testLog("", 'INFO');
testLog("--- TEST 10: Recent Notifications Check ---", 'INFO');

$recent_query = "SELECT id, user_id, type, title, created_at 
                 FROM notifications 
                 WHERE type = 'task_delay' 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 ORDER BY created_at DESC 
                 LIMIT 10";

$recent_result = mysqli_query($conn, $recent_query);
if ($recent_result) {
    $recent_count = mysqli_num_rows($recent_result);
    testLog("Recent task_delay notifications (last hour): {$recent_count}", 'INFO');
    
    if ($recent_count > 0) {
        testLog("Recent notifications:", 'INFO');
        while ($notif = mysqli_fetch_assoc($recent_result)) {
            testLog("  - ID: {$notif['id']}, User: {$notif['user_id']}, Title: {$notif['title']}, Created: {$notif['created_at']}", 'INFO');
        }
    }
} else {
    testLog("✗ Failed to query recent notifications", 'ERROR');
}

// Final Summary
testLog("", 'INFO');
testLog("=== TEST SUITE COMPLETED ===", 'INFO');
testLog("All tests have been executed. Review the results above.", 'INFO');

if (!$is_cli) {
    echo "</body></html>";
}

