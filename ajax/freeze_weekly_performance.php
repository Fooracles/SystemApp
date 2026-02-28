<?php
/**
 * Weekly Performance Freeze Script
 * 
 * Freezes (snapshots) performance data for all users for a completed week.
 * Can be called:
 *   1. Via cron/Task Scheduler every Sunday at 00:00
 *   2. Manually by admin via AJAX
 *   3. Lazily on first page load after a week completes (auto-freeze)
 * 
 * Parameters (GET or POST):
 *   - week_start (optional): Monday date in Y-m-d format. Defaults to last completed week.
 *   - mode: 'cron' (no auth required, CLI only), 'manual' (admin auth), 'lazy' (any logged-in user)
 */

// Detect if running from CLI (cron) or web
$is_cli = (php_sapi_name() === 'cli');

// Load includes first (config must come before session activity)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/dashboard_components.php';

// Suppress error display AFTER includes (error_handler.php re-enables it in dev)
error_reporting(0);
ini_set('display_errors', 0);

if (!$is_cli) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
}

// Determine mode
$mode = 'lazy'; // default
if ($is_cli) {
    $mode = 'cron';
} elseif (isset($_GET['mode'])) {
    $mode = trim($_GET['mode']);
} elseif (isset($_POST['mode'])) {
    $mode = trim($_POST['mode']);
}

// Auth check (skip for CLI/cron)
if (!$is_cli) {
    if (!isLoggedIn()) {
        jsonError('Unauthorized', 401);
    }
    // Manual mode requires admin
    if ($mode === 'manual' && !isAdmin()) {
        jsonError('Admin access required', 400);
    }
}

// Ensure the performance_snapshots table exists
ensureSnapshotTable($conn);

/**
 * Get the Monday of the last completed week (the most recent Monday→Sunday that has fully passed).
 */
function getLastCompletedWeekStart() {
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $dayOfWeek = (int)$today->format('N'); // 1=Mon, 7=Sun
    
    // Go to this week's Monday
    $thisMonday = clone $today;
    $thisMonday->modify('-' . ($dayOfWeek - 1) . ' days');
    
    // Last completed week = previous Monday
    $lastMonday = clone $thisMonday;
    $lastMonday->modify('-7 days');
    
    return $lastMonday;
}

/**
 * Create the performance_snapshots table if it doesn't exist.
 */
function ensureSnapshotTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS performance_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        user_name VARCHAR(100) NOT NULL COMMENT 'Display name at time of snapshot',
        week_start DATE NOT NULL COMMENT 'Monday of the frozen week',
        week_end DATE NOT NULL COMMENT 'Sunday of the frozen week',
        total_tasks INT NOT NULL DEFAULT 0,
        completed_tasks INT NOT NULL DEFAULT 0,
        pending_tasks INT NOT NULL DEFAULT 0,
        delayed_tasks INT NOT NULL DEFAULT 0,
        wnd DECIMAL(7,2) NOT NULL DEFAULT -100,
        wnd_on_time DECIMAL(7,2) NOT NULL DEFAULT -100,
        rqc_score DECIMAL(7,2) NOT NULL DEFAULT 0,
        performance_score DECIMAL(7,2) NOT NULL DEFAULT 0,
        frozen_at DATETIME NOT NULL,
        UNIQUE KEY unique_user_week (user_id, week_start),
        INDEX idx_week (week_start, week_end),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    mysqli_query($conn, $sql);
}

/**
 * Check if a specific week has already been frozen for any user.
 */
function isWeekFrozen($conn, $week_start_str) {
    $sql = "SELECT COUNT(*) as cnt FROM performance_snapshots WHERE week_start = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $week_start_str);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return ($row['cnt'] > 0);
    }
    return false;
}

/**
 * Freeze performance data for all active users for a given week.
 * 
 * @param mysqli $conn Database connection
 * @param string $week_start_str Monday date (Y-m-d)
 * @param string $week_end_str Sunday date (Y-m-d)
 * @return array Result with counts
 */
function freezeWeek($conn, $week_start_str, $week_end_str) {
    $frozen_count = 0;
    $skipped_count = 0;
    $error_count = 0;
    $frozen_at = date('Y-m-d H:i:s');
    
    // Get all users (active doers, managers, admins)
    $sql = "SELECT id, username, name, user_type FROM users WHERE user_type IN ('admin', 'manager', 'doer')";
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        return ['success' => false, 'error' => 'Failed to query users: ' . mysqli_error($conn)];
    }
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    foreach ($users as $user) {
        // Check if snapshot already exists for this user+week
        $check_sql = "SELECT id FROM performance_snapshots WHERE user_id = ? AND week_start = ?";
        if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($check_stmt, "is", $user['id'], $week_start_str);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_fetch_assoc($check_result)) {
                // Already frozen — skip
                $skipped_count++;
                mysqli_stmt_close($check_stmt);
                continue;
            }
            mysqli_stmt_close($check_stmt);
        }
        
        // Calculate stats for the week
        $stats = calculatePersonalStats($conn, $user['id'], $user['username'], $week_start_str, $week_end_str);
        
        if (!is_array($stats)) {
            $error_count++;
            continue;
        }
        
        // Get RQC score for the week
        $rqc_score = getRqcScore($conn, $user['name'], $week_start_str, $week_end_str);
        if (!is_numeric($rqc_score)) {
            $rqc_score = 0;
        }
        $rqc_score = floatval($rqc_score);
        
        // Calculate performance score
        $performance_score = calculatePerformanceRate(
            $rqc_score,
            $stats['wnd'] ?? -100,
            $stats['wnd_on_time'] ?? -100
        );
        
        // Insert snapshot
        $insert_sql = "INSERT INTO performance_snapshots 
            (user_id, username, user_name, week_start, week_end, 
             total_tasks, completed_tasks, pending_tasks, delayed_tasks,
             wnd, wnd_on_time, rqc_score, performance_score, frozen_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
            $total = $stats['total_tasks'] ?? 0;
            $completed = $stats['completed_on_time'] ?? 0;
            $pending = $stats['current_pending'] ?? 0;
            $delayed = $stats['current_delayed'] ?? 0;
            $wnd = $stats['wnd'] ?? -100;
            $wnd_ot = $stats['wnd_on_time'] ?? -100;
            
            mysqli_stmt_bind_param($insert_stmt, "issssiiiiiddds",
                $user['id'],
                $user['username'],
                $user['name'],
                $week_start_str,
                $week_end_str,
                $total,
                $completed,
                $pending,
                $delayed,
                $wnd,
                $wnd_ot,
                $rqc_score,
                $performance_score,
                $frozen_at
            );
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $frozen_count++;
            } else {
                $error_count++;
            }
            mysqli_stmt_close($insert_stmt);
        } else {
            $error_count++;
        }
    }
    
    return [
        'success' => true,
        'week_start' => $week_start_str,
        'week_end' => $week_end_str,
        'total_users' => count($users),
        'frozen' => $frozen_count,
        'skipped' => $skipped_count,
        'errors' => $error_count
    ];
}

// ---- Main execution ----

try {
    // Determine the week to freeze
    $week_start_str = isset($_GET['week_start']) ? trim($_GET['week_start']) : null;
    
    if ($week_start_str && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start_str)) {
        // Use provided week_start (ensure it's a Monday)
        $week_start = new DateTime($week_start_str);
        $dayOfWeek = (int)$week_start->format('N');
        if ($dayOfWeek !== 1) {
            // Adjust to Monday
            $week_start->modify('-' . ($dayOfWeek - 1) . ' days');
        }
    } else {
        // Default: last completed week
        $week_start = getLastCompletedWeekStart();
    }
    
    $week_end = clone $week_start;
    $week_end->modify('+6 days');
    
    $week_start_str = $week_start->format('Y-m-d');
    $week_end_str = $week_end->format('Y-m-d');
    
    // For lazy mode: skip if already frozen
    if ($mode === 'lazy' && isWeekFrozen($conn, $week_start_str)) {
        if (!$is_cli) {
            echo json_encode([
                'success' => true,
                'message' => 'Week already frozen',
                'week_start' => $week_start_str,
                'week_end' => $week_end_str,
                'already_frozen' => true
            ]);
        }
        exit;
    }
    
    // Freeze the week
    $result = freezeWeek($conn, $week_start_str, $week_end_str);
    
    if ($is_cli) {
        // CLI output
        echo "Weekly Performance Freeze\n";
        echo "Week: $week_start_str to $week_end_str\n";
        echo "Users: " . ($result['total_users'] ?? 0) . "\n";
        echo "Frozen: " . ($result['frozen'] ?? 0) . "\n";
        echo "Skipped: " . ($result['skipped'] ?? 0) . "\n";
        echo "Errors: " . ($result['errors'] ?? 0) . "\n";
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    $error_msg = 'Freeze error: ' . $e->getMessage();
    if ($is_cli) {
        echo "ERROR: $error_msg\n";
    } else {
        echo json_encode(['success' => false, 'error' => $error_msg]);
    }
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
