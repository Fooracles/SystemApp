<?php
/**
 * Task Delay Notifications Cron Job
 * 
 * Production-safe cron-based notification system for time-sensitive tasks.
 * Sends notifications exactly 5 minutes before planned datetime for TODAY's tasks only.
 * 
 * Scope:
 * - Delegation tasks (tasks table)
 * - FMS tasks (fms_tasks table)
 * - EXCLUDES checklist tasks completely
 * 
 * Environment:
 * - Single timezone: Asia/Kolkata (UTC+5:30)
 * - Cron runs every minute
 * - Notifications sent ONLY ONCE per task
 * 
 * Setup cron job (runs every minute):
 * * * * * * php /path/to/cron/task_delay_notifications.php
 * 
 * IMPORTANT: This script must handle inconsistent data gracefully.
 * DO NOT assume clean datetime formats, especially for FMS tasks.
 */

// ============================================================================
// TIMEZONE SAFETY (MANDATORY)
// ============================================================================
// Force PHP timezone to Asia/Kolkata at the very start
date_default_timezone_set('Asia/Kolkata');

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_triggers.php';

// Force MySQL session timezone to +05:30 (must be done inside cron file)
if (isset($conn) && $conn) {
    mysqli_query($conn, "SET time_zone = '+05:30'");
} else {
    error_log("[" . date('Y-m-d H:i:s') . "] ERROR: Database connection not available");
    exit(1);
}

// Set execution time limit for long-running script
set_time_limit(300); // 5 minutes

// ============================================================================
// LOGGING HELPER
// ============================================================================
function logNotification($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$level}] {$message}";
    error_log($log_message);
}

// ============================================================================
// DEDUPLICATION HELPER
// ============================================================================
/**
 * Check if notification already exists within the last 10 minutes
 * This prevents duplicate notifications and handles race conditions
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $related_id Related record ID (task ID)
 * @param string $related_type Related record type ('task' or 'fms')
 * @return bool True if duplicate exists, false otherwise
 */
function notificationExists($conn, $user_id, $related_id, $related_type) {
    // Check for existing notification within last 10 minutes
    // This is more robust than DATE(created_at) = CURDATE() as it handles:
    // - Race conditions
    // - Cron overlaps
    // - Multiple cron runs within the same day
    $query = "SELECT id FROM notifications 
              WHERE user_id = ? 
              AND type = 'task_delay' 
              AND related_id = ? 
              AND related_type = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        logNotification("Failed to prepare duplicate check query: " . mysqli_error($conn), 'ERROR');
        return false; // Assume no duplicate to avoid blocking legitimate notifications
    }
    
    mysqli_stmt_bind_param($stmt, 'iss', $user_id, $related_id, $related_type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    
    return $exists;
}

// ============================================================================
// USER RESOLUTION HELPER
// ============================================================================
/**
 * Resolve user_id from doer_name (username)
 * Used for FMS tasks which use doer_name instead of user_id
 * 
 * @param mysqli $conn Database connection
 * @param string $doer_name Username/doer_name
 * @return int|null User ID or null if not found
 */
function resolveUserIdFromDoerName($conn, $doer_name) {
    if (empty($doer_name)) {
        return null;
    }
    
    $query = "SELECT id FROM users WHERE username = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        logNotification("Failed to prepare user lookup query: " . mysqli_error($conn), 'ERROR');
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

// ============================================================================
// DELEGATION TASKS NOTIFICATION CHECK
// ============================================================================
/**
 * Check and create notifications for delegation tasks
 * Uses SQL TIMESTAMP(planned_date, planned_time) for safe datetime computation
 * Only processes TODAY's tasks within the 5-minute warning window
 */
function checkDelegationTaskNotifications($conn) {
    logNotification("Starting delegation task notification check");
    
    // Get current time in Asia/Kolkata timezone
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $today = $now->format('Y-m-d');
    
    // Calculate 5-minute warning window boundaries
    // Trigger window: planned_datetime - 5 minutes <= current_time < planned_datetime
    // This means: if planned is 10:00, trigger when current_time is between 9:55 and 10:00
    $warning_start = clone $now;
    $warning_end = clone $now;
    $warning_end->modify('+5 minutes'); // planned_datetime can be up to 5 minutes in the future
    
    $warning_start_str = $warning_start->format('Y-m-d H:i:s');
    $warning_end_str = $warning_end->format('Y-m-d H:i:s');
    
    // SQL query using TIMESTAMP() function for safe datetime computation
    // This avoids CONCAT-based comparisons which can be unreliable
    // Only check tasks for TODAY to improve performance
    // Filter: planned_datetime - 5 minutes <= current_time < planned_datetime
    // Which translates to: current_time >= planned_datetime - 5 minutes AND current_time < planned_datetime
    // In SQL: planned_datetime > current_time AND planned_datetime <= current_time + 5 minutes
    $query = "SELECT 
                t.id,
                t.unique_id,
                t.description,
                t.planned_date,
                t.planned_time,
                t.doer_id,
                COALESCE(t.doer_name, u.username, u.name, 'Unknown') as user_name
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
    if (!$stmt) {
        logNotification("Failed to prepare delegation task query: " . mysqli_error($conn), 'ERROR');
        return;
    }
    
    mysqli_stmt_bind_param($stmt, 'sss', $today, $warning_start_str, $warning_end_str);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notifications_created = 0;
    $notifications_skipped = 0;
    
    while ($task = mysqli_fetch_assoc($result)) {
        // Validate required fields
        if (empty($task['doer_id']) || empty($task['id'])) {
            logNotification("Skipping delegation task {$task['id']}: Missing doer_id or id", 'WARN');
            $notifications_skipped++;
            continue;
        }
        
        $user_id = (int)$task['doer_id'];
        $task_id = (string)$task['id'];
        
        // Check for duplicate notification (within last 10 minutes)
        if (notificationExists($conn, $user_id, $task_id, 'task')) {
            logNotification("Skipping delegation task {$task_id}: Duplicate notification exists", 'DEBUG');
            $notifications_skipped++;
            continue;
        }
        
        // Format notification message
        $planned_time = !empty($task['planned_time']) ? date('h:i A', strtotime($task['planned_time'])) : 'Unknown';
        $task_id_display = !empty($task['unique_id']) ? $task['unique_id'] : 'ID: ' . $task_id;
        $description = !empty($task['description']) ? substr($task['description'], 0, 50) : 'Task';
        if (strlen($task['description'] ?? '') > 50) {
            $description .= '...';
        }
        
        $message = "Task may get delayed soon. {$task_id_display} - {$description} - Planned: {$planned_time}";
        
        // Create notification
        $notification_id = createNotification(
            $conn,
            $user_id,
            'task_delay',
            'Task Delay Warning',
            $message,
            $task_id,
            'task',
            false,
            null
        );
        
        if ($notification_id) {
            $notifications_created++;
            logNotification("Created notification for delegation task {$task_id} (user: {$user_id})", 'INFO');
        } else {
            logNotification("Failed to create notification for delegation task {$task_id}", 'ERROR');
        }
    }
    
    mysqli_stmt_close($stmt);
    
    logNotification("Delegation task check completed: {$notifications_created} created, {$notifications_skipped} skipped");
}

// ============================================================================
// FMS TASKS NOTIFICATION CHECK
// ============================================================================
/**
 * Check and create notifications for FMS tasks
 * FMS tasks have dirty datetime data in VARCHAR format
 * Must parse in PHP using parseFMSDateTimeString_doer() helper
 * Handle parsing failures gracefully - log and skip, don't crash
 */
function checkFMSTaskNotifications($conn) {
    logNotification("Starting FMS task notification check");
    
    // Get current time in Asia/Kolkata timezone
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $today = $now->format('Y-m-d');
    
    // Calculate 5-minute warning window boundaries
    // Trigger window: planned_datetime - 5 minutes <= current_time < planned_datetime
    // This means: if planned is 10:00, trigger when current_time is between 9:55 and 10:00
    $warning_start = clone $now;
    $warning_end = clone $now;
    $warning_end->modify('+5 minutes'); // planned_datetime can be up to 5 minutes in the future
    
    // Coarse filtering: Get FMS tasks that might be due today
    // We can't filter by datetime in SQL because planned is VARCHAR with inconsistent formats
    // So we fetch candidates and parse in PHP
    $query = "SELECT 
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
                AND ft.actual IS NULL";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        logNotification("Failed to prepare FMS task query: " . mysqli_error($conn), 'ERROR');
        return;
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notifications_created = 0;
    $notifications_skipped = 0;
    $parse_failures = 0;
    $user_not_found = 0;
    
    while ($task = mysqli_fetch_assoc($result)) {
        // Validate required fields
        if (empty($task['doer_name']) || empty($task['id']) || empty($task['planned'])) {
            logNotification("Skipping FMS task {$task['id']}: Missing required fields", 'WARN');
            $notifications_skipped++;
            continue;
        }
        
        // Parse FMS datetime string using existing helper function
        // This handles inconsistent datetime formats gracefully
        $planned_datetime = null;
        if (function_exists('parseFMSDateTimeString_doer')) {
            $parsed_result = parseFMSDateTimeString_doer($task['planned']);
            
            // Handle different return types (timestamp or DateTime object)
            if ($parsed_result instanceof DateTime) {
                $planned_datetime = $parsed_result;
            } elseif (is_numeric($parsed_result) && $parsed_result > 0) {
                // It's a timestamp, convert to DateTime
                $planned_datetime = DateTime::createFromFormat('U', $parsed_result);
            } else {
                // Fallback: try basic parsing
                $timestamp = strtotime($task['planned']);
                if ($timestamp !== false && $timestamp > 0) {
                    $planned_datetime = DateTime::createFromFormat('U', $timestamp);
                }
            }
        } else {
            // Fallback: try basic parsing
            $timestamp = strtotime($task['planned']);
            if ($timestamp !== false && $timestamp > 0) {
                $planned_datetime = DateTime::createFromFormat('U', $timestamp);
            }
        }
        
        // If parsing failed, log and skip (do NOT crash)
        if (!$planned_datetime) {
            $parse_failures++;
            logNotification("Failed to parse FMS datetime for task {$task['id']}: '{$task['planned']}'", 'WARN');
            continue;
        }
        
        // Set timezone to Asia/Kolkata for accurate comparison
        $planned_datetime->setTimezone(new DateTimeZone('Asia/Kolkata'));
        
        // Check if planned datetime is for TODAY
        $planned_date = $planned_datetime->format('Y-m-d');
        if ($planned_date !== $today) {
            // Skip tasks not due today (performance optimization)
            continue;
        }
        
        // Check if planned datetime is within 5-minute warning window
        // Trigger window: planned_datetime - 5 minutes <= current_time < planned_datetime
        // This means: planned_datetime > current_time AND planned_datetime <= current_time + 5 minutes
        if ($planned_datetime <= $warning_start || $planned_datetime > $warning_end) {
            // Outside warning window, skip
            continue;
        }
        
        // Resolve user_id from doer_name
        $user_id = resolveUserIdFromDoerName($conn, $task['doer_name']);
        if ($user_id === null) {
            $user_not_found++;
            logNotification("User not found for FMS task {$task['id']}: doer_name='{$task['doer_name']}'", 'WARN');
            continue;
        }
        
        $task_id = (string)$task['id'];
        
        // Check for duplicate notification (within last 10 minutes)
        if (notificationExists($conn, $user_id, $task_id, 'fms')) {
            logNotification("Skipping FMS task {$task_id}: Duplicate notification exists", 'DEBUG');
            $notifications_skipped++;
            continue;
        }
        
        // Format notification message
        $planned_display = $planned_datetime->format('d M Y h:i A');
        $task_id_display = !empty($task['unique_key']) ? $task['unique_key'] : 'ID: ' . $task_id;
        $step_name = !empty($task['step_name']) ? substr($task['step_name'], 0, 50) : 'FMS Task';
        if (strlen($task['step_name'] ?? '') > 50) {
            $step_name .= '...';
        }
        
        $message = "FMS task may get delayed soon. {$task_id_display} - {$step_name} - Planned: {$planned_display}";
        
        // Create notification
        $notification_id = createNotification(
            $conn,
            $user_id,
            'task_delay',
            'Task Delay Warning',
            $message,
            $task_id,
            'fms',
            false,
            null
        );
        
        if ($notification_id) {
            $notifications_created++;
            logNotification("Created notification for FMS task {$task_id} (user: {$user_id})", 'INFO');
        } else {
            logNotification("Failed to create notification for FMS task {$task_id}", 'ERROR');
        }
    }
    
    mysqli_stmt_close($stmt);
    
    logNotification("FMS task check completed: {$notifications_created} created, {$notifications_skipped} skipped, {$parse_failures} parse failures, {$user_not_found} users not found");
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================
logNotification("=== TASK DELAY NOTIFICATIONS CRON JOB STARTED ===");

try {
    // Verify timezone settings
    $php_timezone = date_default_timezone_get();
    if ($php_timezone !== 'Asia/Kolkata') {
        logNotification("WARNING: PHP timezone is '{$php_timezone}', expected 'Asia/Kolkata'", 'WARN');
    }
    
    // Verify MySQL timezone
    $mysql_tz_result = mysqli_query($conn, "SELECT @@session.time_zone as tz");
    if ($mysql_tz_result) {
        $mysql_tz = mysqli_fetch_assoc($mysql_tz_result);
        if ($mysql_tz['tz'] !== '+05:30') {
            logNotification("WARNING: MySQL timezone is '{$mysql_tz['tz']}', expected '+05:30'", 'WARN');
        }
    }
    
    // 1. Check delegation tasks
    checkDelegationTaskNotifications($conn);
    
    // 2. Check FMS tasks
    checkFMSTaskNotifications($conn);
    
    logNotification("=== TASK DELAY NOTIFICATIONS CRON JOB COMPLETED SUCCESSFULLY ===");
    
} catch (Exception $e) {
    logNotification("CRITICAL ERROR: " . $e->getMessage(), 'ERROR');
    logNotification("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    exit(1);
} catch (Throwable $e) {
    logNotification("CRITICAL ERROR: " . $e->getMessage(), 'ERROR');
    logNotification("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    exit(1);
}

