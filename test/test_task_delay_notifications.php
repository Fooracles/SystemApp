<?php
/**
 * Test File for Task Delay Notifications
 * 
 * This file tests the notification triggers for task delay warnings.
 * It verifies:
 * - Delegation task notifications (5 minutes before planned time)
 * - FMS task notifications (5 minutes before planned time)
 * - Deduplication logic
 * - Timezone handling
 * - Error handling
 * 
 * Usage:
 * php test/test_task_delay_notifications.php
 * 
 * Or access via browser:
 * http://your-domain/test/test_task_delay_notifications.php
 */

// Set timezone first
date_default_timezone_set('Asia/Kolkata');

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_triggers.php';

// Define a flag to prevent cron execution when included
define('CRON_INCLUDE_MODE', true);

// Include the cron file to access its functions
// The cron file should check for this flag before executing main logic
require_once __DIR__ . '/../cron/task_delay_notifications.php';

// Force MySQL timezone
if (isset($conn) && $conn) {
    mysqli_query($conn, "SET time_zone = '+05:30'");
} else {
    die("ERROR: Database connection not available");
}

// Set output for browser or CLI
$is_cli = php_sapi_name() === 'cli';
if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Task Delay Notifications Test</title>";
    echo "<style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #569cd6; }
        .section { margin: 20px 0; padding: 15px; background: #252526; border-left: 3px solid #007acc; }
        .test-result { margin: 10px 0; padding: 10px; background: #1e1e1e; border-radius: 4px; }
        h1, h2 { color: #4ec9b0; }
        pre { background: #1e1e1e; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #3e3e42; }
        th { background: #2d2d30; color: #4ec9b0; }
    </style></head><body>";
}

function testOutput($message, $type = 'info') {
    global $is_cli;
    $colors = [
        'success' => $is_cli ? "\033[32m" : '',
        'error' => $is_cli ? "\033[31m" : '',
        'warning' => $is_cli ? "\033[33m" : '',
        'info' => $is_cli ? "\033[36m" : '',
        'reset' => $is_cli ? "\033[0m" : ''
    ];
    
    if ($is_cli) {
        echo $colors[$type] . $message . $colors['reset'] . "\n";
    } else {
        $class = $type;
        echo "<div class='test-result {$class}'>{$message}</div>";
    }
}

function testSection($title) {
    global $is_cli;
    if ($is_cli) {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "  " . $title . "\n";
        echo str_repeat("=", 60) . "\n\n";
    } else {
        echo "<div class='section'><h2>{$title}</h2>";
    }
}

function endSection() {
    global $is_cli;
    if (!$is_cli) {
        echo "</div>";
    }
}

// Start test
testOutput("=== TASK DELAY NOTIFICATIONS TEST ===", 'info');
testOutput("Test started at: " . date('Y-m-d H:i:s T'), 'info');
testOutput("PHP Timezone: " . date_default_timezone_get(), 'info');

// Test 1: Timezone Verification
testSection("Test 1: Timezone Verification");
$php_tz = date_default_timezone_get();
$mysql_tz_result = mysqli_query($conn, "SELECT @@session.time_zone as tz");
$mysql_tz_row = mysqli_fetch_assoc($mysql_tz_result);
$mysql_tz = $mysql_tz_row['tz'] ?? 'unknown';

if ($php_tz === 'Asia/Kolkata') {
    testOutput("✓ PHP timezone is correct: {$php_tz}", 'success');
} else {
    testOutput("✗ PHP timezone is incorrect: {$php_tz} (expected: Asia/Kolkata)", 'error');
}

if ($mysql_tz === '+05:30') {
    testOutput("✓ MySQL timezone is correct: {$mysql_tz}", 'success');
} else {
    testOutput("✗ MySQL timezone is incorrect: {$mysql_tz} (expected: +05:30)", 'error');
}
endSection();

// Test 2: Check Current Time and Warning Window
testSection("Test 2: Current Time and Warning Window");
$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$warning_start = clone $now;
$warning_end = clone $now;
$warning_end->modify('+5 minutes');

testOutput("Current time: " . $now->format('Y-m-d H:i:s T'), 'info');
testOutput("Warning window start: " . $warning_start->format('Y-m-d H:i:s T'), 'info');
testOutput("Warning window end: " . $warning_end->format('Y-m-d H:i:s T'), 'info');
testOutput("Window: Tasks planned between " . $warning_start->format('H:i:s') . " and " . $warning_end->format('H:i:s') . " will trigger notifications", 'info');
endSection();

// Test 3: Test Delegation Tasks Query
testSection("Test 3: Delegation Tasks Query");
$today = $now->format('Y-m-d');
$warning_start_str = $warning_start->format('Y-m-d H:i:s');
$warning_end_str = $warning_end->format('Y-m-d H:i:s');

$query = "SELECT 
            t.id,
            t.unique_id,
            t.description,
            t.planned_date,
            t.planned_time,
            t.doer_id,
            t.status,
            TIMESTAMP(t.planned_date, t.planned_time) as planned_datetime,
            COALESCE(t.doer_name, u.username, u.name, 'Unknown') as user_name
          FROM tasks t
          LEFT JOIN users u ON t.doer_id = u.id
          WHERE t.planned_date = ?
            AND t.planned_date IS NOT NULL
            AND t.planned_time IS NOT NULL
            AND t.doer_id IS NOT NULL
            AND t.status NOT IN ('completed', 'not done', 'can not be done', 'shifted')
            AND TIMESTAMP(t.planned_date, t.planned_time) > ?
            AND TIMESTAMP(t.planned_date, t.planned_time) <= ?
          ORDER BY TIMESTAMP(t.planned_date, t.planned_time) ASC
          LIMIT 10";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'sss', $today, $warning_start_str, $warning_end_str);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $delegation_tasks = [];
    while ($task = mysqli_fetch_assoc($result)) {
        $delegation_tasks[] = $task;
    }
    mysqli_stmt_close($stmt);
    
    testOutput("Found " . count($delegation_tasks) . " delegation tasks in warning window", count($delegation_tasks) > 0 ? 'success' : 'warning');
    
    if (count($delegation_tasks) > 0) {
        if (!$is_cli) {
            echo "<table><tr><th>ID</th><th>Unique ID</th><th>Description</th><th>Planned DateTime</th><th>User</th><th>Status</th></tr>";
        }
        foreach ($delegation_tasks as $task) {
            $desc = substr($task['description'] ?? '', 0, 30);
            if (strlen($task['description'] ?? '') > 30) $desc .= '...';
            if ($is_cli) {
                testOutput("  - Task #{$task['id']} ({$task['unique_id']}): {$desc} | Planned: {$task['planned_datetime']} | User: {$task['user_name']}", 'info');
            } else {
                echo "<tr>";
                echo "<td>{$task['id']}</td>";
                echo "<td>{$task['unique_id']}</td>";
                echo "<td>{$desc}</td>";
                echo "<td>{$task['planned_datetime']}</td>";
                echo "<td>{$task['user_name']}</td>";
                echo "<td>{$task['status']}</td>";
                echo "</tr>";
            }
        }
        if (!$is_cli) {
            echo "</table>";
        }
    } else {
        testOutput("No delegation tasks found in warning window. This is normal if no tasks are scheduled for the next 5 minutes.", 'warning');
    }
} else {
    testOutput("✗ Failed to prepare delegation tasks query: " . mysqli_error($conn), 'error');
}
endSection();

// Test 4: Test FMS Tasks Query
testSection("Test 4: FMS Tasks Query");
$fms_query = "SELECT 
                ft.id,
                ft.unique_key,
                ft.step_name,
                ft.planned,
                ft.doer_name,
                ft.status
              FROM fms_tasks ft
              WHERE ft.planned IS NOT NULL
                AND ft.planned != ''
                AND ft.doer_name IS NOT NULL
                AND ft.doer_name != ''
                AND ft.status NOT IN ('completed', 'not done', 'can not be done', 'done', 'yes')
                AND ft.actual IS NULL
              LIMIT 20";

$fms_stmt = mysqli_prepare($conn, $fms_query);
if ($fms_stmt) {
    mysqli_stmt_execute($fms_stmt);
    $fms_result = mysqli_stmt_get_result($fms_stmt);
    
    $fms_tasks = [];
    $fms_tasks_in_window = [];
    $parse_failures = 0;
    
    while ($task = mysqli_fetch_assoc($fms_result)) {
        $fms_tasks[] = $task;
        
        // Try to parse the planned datetime
        $planned_timestamp = null;
        if (function_exists('parseFMSDateTimeString_doer')) {
            $parsed_result = parseFMSDateTimeString_doer($task['planned']);
            
            if ($parsed_result instanceof DateTime) {
                $planned_datetime = $parsed_result;
            } elseif (is_numeric($parsed_result) && $parsed_result > 0) {
                $planned_datetime = DateTime::createFromFormat('U', $parsed_result);
            } else {
                $parse_failures++;
                continue;
            }
        } else {
            $timestamp = strtotime($task['planned']);
            if ($timestamp !== false && $timestamp > 0) {
                $planned_datetime = DateTime::createFromFormat('U', $timestamp);
            } else {
                $parse_failures++;
                continue;
            }
        }
        
        if (!$planned_datetime) {
            $parse_failures++;
            continue;
        }
        
        $planned_datetime->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $planned_date = $planned_datetime->format('Y-m-d');
        
        // Check if it's today and in warning window
        if ($planned_date === $today) {
            if ($planned_datetime > $warning_start && $planned_datetime <= $warning_end) {
                $fms_tasks_in_window[] = [
                    'task' => $task,
                    'planned_datetime' => $planned_datetime->format('Y-m-d H:i:s'),
                    'user_id' => resolveUserIdFromDoerName($conn, $task['doer_name'])
                ];
            }
        }
    }
    mysqli_stmt_close($fms_stmt);
    
    testOutput("Total FMS tasks checked: " . count($fms_tasks), 'info');
    testOutput("FMS tasks in warning window: " . count($fms_tasks_in_window), count($fms_tasks_in_window) > 0 ? 'success' : 'warning');
    testOutput("Parse failures: " . $parse_failures, $parse_failures > 0 ? 'warning' : 'success');
    
    if (count($fms_tasks_in_window) > 0) {
        if (!$is_cli) {
            echo "<table><tr><th>ID</th><th>Unique Key</th><th>Step Name</th><th>Planned DateTime</th><th>Doer Name</th><th>User ID</th></tr>";
        }
        foreach ($fms_tasks_in_window as $item) {
            $task = $item['task'];
            $step_name = substr($task['step_name'] ?? '', 0, 30);
            if (strlen($task['step_name'] ?? '') > 30) $step_name .= '...';
            if ($is_cli) {
                $user_info = $item['user_id'] ? "User ID: {$item['user_id']}" : "User NOT FOUND";
                testOutput("  - FMS Task #{$task['id']} ({$task['unique_key']}): {$step_name} | Planned: {$item['planned_datetime']} | {$user_info}", 'info');
            } else {
                echo "<tr>";
                echo "<td>{$task['id']}</td>";
                echo "<td>{$task['unique_key']}</td>";
                echo "<td>{$step_name}</td>";
                echo "<td>{$item['planned_datetime']}</td>";
                echo "<td>{$task['doer_name']}</td>";
                echo "<td>" . ($item['user_id'] ? $item['user_id'] : '<span class="error">NOT FOUND</span>') . "</td>";
                echo "</tr>";
            }
        }
        if (!$is_cli) {
            echo "</table>";
        }
    } else {
        testOutput("No FMS tasks found in warning window. This is normal if no tasks are scheduled for the next 5 minutes.", 'warning');
    }
} else {
    testOutput("✗ Failed to prepare FMS tasks query: " . mysqli_error($conn), 'error');
}
endSection();

// Test 5: Test Deduplication Logic
testSection("Test 5: Deduplication Logic");
$test_user_id = 1; // Change this to a valid user ID for testing
$test_related_id = '999999';
$test_related_type = 'task';

// Check if notificationExists function works
if (function_exists('notificationExists')) {
    $exists = notificationExists($conn, $test_user_id, $test_related_id, $test_related_type);
    testOutput("Deduplication check for user {$test_user_id}, task {$test_related_id}: " . ($exists ? "EXISTS (would skip)" : "NOT EXISTS (would create)"), $exists ? 'warning' : 'success');
} else {
    testOutput("✗ notificationExists function not found", 'error');
}

// Check recent notifications
$recent_query = "SELECT COUNT(*) as count FROM notifications 
                 WHERE type = 'task_delay' 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
$recent_result = mysqli_query($conn, $recent_query);
$recent_row = mysqli_fetch_assoc($recent_result);
$recent_count = $recent_row['count'] ?? 0;

testOutput("Recent task_delay notifications (last 10 minutes): " . $recent_count, 'info');
endSection();

// Test 6: Run Actual Notification Check
testSection("Test 6: Run Actual Notification Check");
testOutput("Running checkDelegationTaskNotifications()...", 'info');
checkDelegationTaskNotifications($conn);

testOutput("Running checkFMSTaskNotifications()...", 'info');
checkFMSTaskNotifications($conn);

// Check if new notifications were created
$new_query = "SELECT COUNT(*) as count FROM notifications 
              WHERE type = 'task_delay' 
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
$new_result = mysqli_query($conn, $new_query);
$new_row = mysqli_fetch_assoc($new_result);
$new_count = $new_row['count'] ?? 0;

testOutput("New notifications created in last minute: " . $new_count, $new_count > 0 ? 'success' : 'warning');

// Show recent notifications
if ($new_count > 0) {
    $notif_query = "SELECT n.id, n.user_id, n.title, n.message, n.related_id, n.related_type, n.created_at,
                     u.username, u.name
                     FROM notifications n
                     LEFT JOIN users u ON n.user_id = u.id
                     WHERE n.type = 'task_delay' 
                     AND n.created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                     ORDER BY n.created_at DESC
                     LIMIT 10";
    $notif_result = mysqli_query($conn, $notif_query);
    
    if (!$is_cli) {
        echo "<table><tr><th>ID</th><th>User</th><th>Title</th><th>Message</th><th>Related ID</th><th>Type</th><th>Created At</th></tr>";
    }
    while ($notif = mysqli_fetch_assoc($notif_result)) {
        $user_display = ($notif['name'] ?? $notif['username'] ?? 'Unknown') . " (ID: {$notif['user_id']})";
        $message_short = substr($notif['message'] ?? '', 0, 50);
        if (strlen($notif['message'] ?? '') > 50) $message_short .= '...';
        
        if ($is_cli) {
            testOutput("  - Notification #{$notif['id']}: {$notif['title']} | User: {$user_display} | {$message_short}", 'info');
        } else {
            echo "<tr>";
            echo "<td>{$notif['id']}</td>";
            echo "<td>{$user_display}</td>";
            echo "<td>{$notif['title']}</td>";
            echo "<td>{$message_short}</td>";
            echo "<td>{$notif['related_id']}</td>";
            echo "<td>{$notif['related_type']}</td>";
            echo "<td>{$notif['created_at']}</td>";
            echo "</tr>";
        }
    }
    if (!$is_cli) {
        echo "</table>";
    }
}
endSection();

// Test 7: Summary
testSection("Test 7: Summary");
testOutput("Test completed at: " . date('Y-m-d H:i:s T'), 'info');
testOutput("Total delegation tasks in window: " . (isset($delegation_tasks) ? count($delegation_tasks) : 0), 'info');
testOutput("Total FMS tasks in window: " . (isset($fms_tasks_in_window) ? count($fms_tasks_in_window) : 0), 'info');
testOutput("New notifications created: " . $new_count, $new_count > 0 ? 'success' : 'warning');

if ($new_count === 0 && (count($delegation_tasks ?? []) === 0 && count($fms_tasks_in_window ?? []) === 0)) {
    testOutput("", 'info');
    testOutput("NOTE: No notifications were created because no tasks are scheduled", 'warning');
    testOutput("      in the 5-minute warning window (next 5 minutes).", 'warning');
    testOutput("", 'info');
    testOutput("To test with actual data:", 'info');
    testOutput("1. Create a task with planned_datetime = current_time + 3 minutes", 'info');
    testOutput("2. Run this test again", 'info');
    testOutput("3. The notification should be created", 'info');
}
endSection();

if (!$is_cli) {
    echo "</body></html>";
}

