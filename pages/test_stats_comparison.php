<?php
/**
 * Test File: Compare Stats Between manage_tasks.php and doer_dashboard.php
 * 
 * This file fetches stats from both sources and compares them to verify accuracy.
 * Usage: Access via browser when logged in as a doer user.
 */

session_start();
$page_title = "Stats Comparison Test";
require_once "../includes/header.php";
require_once "../includes/status_column_helpers.php";
require_once "../includes/db_functions.php";

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Get current user info
$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
$current_username = htmlspecialchars($_SESSION["username"] ?? '');

// ============================================
// FETCH STATS FROM DOER_DASHBOARD.PHP LOGIC
// ============================================

// Update delay status for all tasks (same as manage_tasks.php line 313 and doer_dashboard.php)
if (function_exists('updateAllTasksDelayStatus')) {
    updateAllTasksDelayStatus($conn);
}

$doer_stats = [
    'completed_on_time' => 0,
    'current_pending' => 0,
    'current_delayed' => 0,
    'total_tasks' => 0,
    'wnd' => 0,
    'wnd_on_time' => 0
];

$doer_task_details = [
    'delegation' => [],
    'fms' => [],
    'checklist' => []
];

// Helper functions (same as doer_dashboard.php)
function isPastPlannedDateTime($planned_date, $planned_time) {
    if (empty($planned_date)) return false;
    $planned_datetime = $planned_date;
    if (!empty($planned_time)) {
        $planned_datetime .= ' ' . $planned_time;
    } else {
        $planned_datetime .= ' 23:59:59';
    }
    $planned_timestamp = strtotime($planned_datetime);
    return $planned_timestamp !== false && $planned_timestamp < time();
}

function isActualAfterPlanned($planned_date, $planned_time, $actual_date, $actual_time) {
    if (empty($planned_date) || empty($actual_date)) return false;
    $planned_datetime = $planned_date;
    if (!empty($planned_time)) {
        $planned_datetime .= ' ' . $planned_time;
    } else {
        $planned_datetime .= ' 23:59:59';
    }
    $actual_datetime = $actual_date;
    if (!empty($actual_time)) {
        $actual_datetime .= ' ' . $actual_time;
    } else {
        $actual_datetime .= ' 23:59:59';
    }
    $planned_timestamp = strtotime($planned_datetime);
    $actual_timestamp = strtotime($actual_datetime);
    return $planned_timestamp !== false && $actual_timestamp !== false && $actual_timestamp > $planned_timestamp;
}

function shouldExcludeFromWND($status, $planned_date, $planned_time, $actual_date = null, $task_type = 'delegation') {
    $status = strtolower($status ?? '');
    
    if ($task_type === 'fms') {
        if (in_array($status, ['completed', 'done', 'not done', 'can not be done'])) {
            return true;
        }
    } else {
        if (in_array($status, ['completed', 'done'])) {
            return true;
        }
    }
    
    if ($status === 'shifted') {
        if (!empty($actual_date) && !empty($planned_date)) {
            $actual_timestamp = strtotime($actual_date . ' 23:59:59');
            $planned_datetime = $planned_date;
            if (!empty($planned_time)) {
                $planned_datetime .= ' ' . $planned_time;
            } else {
                $planned_datetime .= ' 23:59:59';
            }
            $planned_timestamp = strtotime($planned_datetime);
            
            if ($planned_timestamp !== false && $actual_timestamp !== false && $actual_timestamp < $planned_timestamp) {
                return true;
            }
        }
        if (empty($actual_date) && !isPastPlannedDateTime($planned_date, $planned_time)) {
            return true;
        }
    }
    
    return false;
}

// Fetch delegation tasks
$delegation_query = "SELECT t.id, t.status, t.is_delayed, t.delay_duration, t.planned_date, t.planned_time, t.actual_date, t.actual_time
                    FROM tasks t 
                    WHERE t.doer_id = ?";
if ($stmt_delegation = mysqli_prepare($conn, $delegation_query)) {
    mysqli_stmt_bind_param($stmt_delegation, "i", $current_user_id);
    if (mysqli_stmt_execute($stmt_delegation)) {
        $delegation_result = mysqli_stmt_get_result($stmt_delegation);
        if ($delegation_result) {
            while ($row = mysqli_fetch_assoc($delegation_result)) {
                $doer_stats['total_tasks']++;
                
                $status = strtolower($row['status'] ?? '');
                $planned_date = $row['planned_date'] ?? '';
                $planned_time = $row['planned_time'] ?? '';
                $actual_date = $row['actual_date'] ?? '';
                $actual_time = $row['actual_time'] ?? '';
                $is_completed = in_array($status, ['completed', 'done']);
                
                // Use DB is_delayed field (updated by updateAllTasksDelayStatus) - same as manage_tasks.php and doer_dashboard.php
                $is_delayed = $row['is_delayed'] == 1 || !empty($row['delay_duration']);
                
                // Store task details
                $doer_task_details['delegation'][] = [
                    'id' => $row['id'],
                    'status' => $status,
                    'is_delayed' => $is_delayed,
                    'planned_date' => $planned_date,
                    'planned_time' => $planned_time,
                    'actual_date' => $actual_date,
                    'actual_time' => $actual_time
                ];
                
                // Calculate stats
                if (!shouldExcludeFromWND($status, $planned_date, $planned_time, $actual_date, 'delegation') 
                    && isPastPlannedDateTime($planned_date, $planned_time)) {
                    $doer_stats['wnd']++;
                }
                
                if ($is_completed && isActualAfterPlanned($planned_date, $planned_time, $actual_date, $actual_time)) {
                    $doer_stats['wnd_on_time']++;
                }
                
                if ($is_completed) {
                    if ($is_delayed) {
                        $doer_stats['current_delayed']++;
                    } else {
                        $doer_stats['completed_on_time']++;
                    }
                } else {
                    if ($is_delayed) {
                        $doer_stats['current_delayed']++;
                    } else {
                        $doer_stats['current_pending']++;
                    }
                }
            }
        }
    }
    mysqli_stmt_close($stmt_delegation);
}

// Fetch FMS tasks
$fms_query = "SELECT id, status, is_delayed, delay_duration, planned, actual 
              FROM fms_tasks 
              WHERE doer_name = ?";
if ($stmt_fms = mysqli_prepare($conn, $fms_query)) {
    mysqli_stmt_bind_param($stmt_fms, "s", $current_username);
    if (mysqli_stmt_execute($stmt_fms)) {
        $fms_result = mysqli_stmt_get_result($stmt_fms);
        if ($fms_result) {
            while ($row = mysqli_fetch_assoc($fms_result)) {
                $doer_stats['total_tasks']++;
                
                $status = strtolower($row['status'] ?? '');
                $planned_timestamp = !empty($row['planned']) ? parseFMSDateTimeString_doer($row['planned']) : null;
                $actual_timestamp = !empty($row['actual']) ? parseFMSDateTimeString_doer($row['actual']) : null;
                
                $planned_date = $planned_timestamp ? date('Y-m-d', $planned_timestamp) : '';
                $planned_time = $planned_timestamp ? date('H:i:s', $planned_timestamp) : '';
                $actual_date = $actual_timestamp ? date('Y-m-d', $actual_timestamp) : '';
                $actual_time = $actual_timestamp ? date('H:i:s', $actual_timestamp) : '';
                
                $completed_statuses = ['completed', 'done', 'not done', 'can not be done'];
                $is_task_completed = in_array($status, $completed_statuses);
                
                // Recalculate delay
                $is_delayed = false;
                if ($is_task_completed) {
                    if ($actual_timestamp && $planned_timestamp && $actual_timestamp > $planned_timestamp) {
                        $is_delayed = true;
                    }
                } else {
                    if ($planned_timestamp && time() > $planned_timestamp) {
                        $is_delayed = true;
                    }
                }
                
                // Store task details
                $doer_task_details['fms'][] = [
                    'id' => $row['id'],
                    'status' => $status,
                    'is_delayed' => $is_delayed,
                    'planned' => $row['planned'],
                    'actual' => $row['actual'],
                    'planned_date' => $planned_date,
                    'planned_time' => $planned_time,
                    'actual_date' => $actual_date,
                    'actual_time' => $actual_time
                ];
                
                // Calculate stats
                if (!shouldExcludeFromWND($status, $planned_date, $planned_time, $actual_date, 'fms') 
                    && $planned_timestamp && $planned_timestamp < time()) {
                    $doer_stats['wnd']++;
                }
                
                if ($is_task_completed && $planned_timestamp && $actual_timestamp && $actual_timestamp > $planned_timestamp) {
                    $doer_stats['wnd_on_time']++;
                }
                
                if ($is_task_completed) {
                    if ($is_delayed) {
                        $doer_stats['current_delayed']++;
                    } else {
                        $doer_stats['completed_on_time']++;
                    }
                } else {
                    if ($is_delayed) {
                        $doer_stats['current_delayed']++;
                    } else {
                        $doer_stats['current_pending']++;
                    }
                }
            }
        }
    }
    mysqli_stmt_close($stmt_fms);
}

// Fetch checklist tasks
$today = date('Y-m-d');
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

$checklist_query = "SELECT id, status, is_delayed, delay_duration, task_date, actual_date, actual_time, frequency
                   FROM checklist_subtasks 
                   WHERE assignee = ? AND (
                       (frequency = 'Daily' AND task_date = ?)
                       OR
                       (frequency != 'Daily' AND task_date >= ? AND task_date <= ?)
                   )";
if ($stmt_checklist = mysqli_prepare($conn, $checklist_query)) {
    mysqli_stmt_bind_param($stmt_checklist, "ssss", $current_username, $today, $current_week_start, $current_week_end);
    if (mysqli_stmt_execute($stmt_checklist)) {
        $checklist_result = mysqli_stmt_get_result($stmt_checklist);
        if ($checklist_result) {
            while ($row = mysqli_fetch_assoc($checklist_result)) {
                $doer_stats['total_tasks']++;
                
                $status = strtolower($row['status'] ?? 'pending');
                $task_date = $row['task_date'] ?? '';
                $actual_date = $row['actual_date'] ?? '';
                $actual_time = $row['actual_time'] ?? '';
                $planned_date = $task_date;
                $planned_time = '23:59:59';
                
                $is_completed = in_array($status, ['completed', 'done']);
                
                // Use DB is_delayed field (updated by updateAllTasksDelayStatus) - same as manage_tasks.php and doer_dashboard.php
                $is_delayed = $row['is_delayed'] == 1 || !empty($row['delay_duration']);
                
                // Store task details
                $doer_task_details['checklist'][] = [
                    'id' => $row['id'],
                    'status' => $status,
                    'is_delayed' => $is_delayed,
                    'task_date' => $task_date,
                    'actual_date' => $actual_date,
                    'actual_time' => $actual_time
                ];
                
                // Calculate stats
                if (!shouldExcludeFromWND($status, $planned_date, $planned_time, $actual_date, 'checklist') 
                    && isPastPlannedDateTime($planned_date, $planned_time)) {
                    $doer_stats['wnd']++;
                }
                
                if ($is_completed && !empty($actual_date) && isActualAfterPlanned($planned_date, $planned_time, $actual_date, $actual_time ?? '23:59:59')) {
                    $doer_stats['wnd_on_time']++;
                }
                
                if ($is_completed) {
                    if ($is_delayed) {
                        $doer_stats['current_delayed']++;
                    } else {
                        $doer_stats['completed_on_time']++;
                    }
                } else {
                    if ($is_delayed) {
                        $doer_stats['current_delayed']++;
                    } else {
                        $doer_stats['current_pending']++;
                    }
                }
            }
        }
    }
    mysqli_stmt_close($stmt_checklist);
}

// ============================================
// FETCH STATS FROM MANAGE_TASKS.PHP LOGIC
// ============================================

$manage_tasks_stats = [
    'total_tasks' => 0,
    'completed_tasks' => 0,
    'delayed_tasks' => 0,
    'pending_tasks' => 0,
    'shifted_tasks' => 0,
    'delayed_completed' => 0,
    'delayed_pending' => 0
];

$manage_tasks_details = [];

// Fetch all tasks for this doer (same logic as manage_tasks.php)
$all_tasks = [];

// 1. Delegation Tasks
$delegation_query_mt = "SELECT t.id, t.unique_id, t.description, t.planned_date, t.planned_time, 
                                t.actual_date, t.actual_time, t.status, t.is_delayed, t.delay_duration, 
                                t.duration, 'delegation' as task_type
                         FROM tasks t 
                         WHERE t.doer_id = ?";
if ($stmt_del = mysqli_prepare($conn, $delegation_query_mt)) {
    mysqli_stmt_bind_param($stmt_del, "i", $current_user_id);
    if (mysqli_stmt_execute($stmt_del)) {
        $result = mysqli_stmt_get_result($stmt_del);
        while ($row = mysqli_fetch_assoc($result)) {
            $all_tasks[] = $row;
        }
    }
    mysqli_stmt_close($stmt_del);
}

// 2. Checklist Tasks
$today = date('Y-m-d');
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

$checklist_query_mt = "SELECT cs.id as task_id, cs.task_code as unique_id, cs.task_description as description,
                               cs.task_date as planned_date, CONCAT(cs.task_date, ' 23:59:59') as planned_time,
                               cs.actual_date, cs.actual_time, COALESCE(cs.status, 'pending') as status,
                               cs.is_delayed, cs.delay_duration, cs.duration, 'checklist' as task_type
                        FROM checklist_subtasks cs
                        WHERE cs.assignee = ? AND (
                            (cs.frequency = 'Daily' AND cs.task_date = ?)
                            OR
                            (cs.frequency != 'Daily' AND cs.task_date >= ? AND cs.task_date <= ?)
                        )";
if ($stmt_chk = mysqli_prepare($conn, $checklist_query_mt)) {
    mysqli_stmt_bind_param($stmt_chk, 'ssss', $current_username, $today, $current_week_start, $current_week_end);
    if (mysqli_stmt_execute($stmt_chk)) {
        $result = mysqli_stmt_get_result($stmt_chk);
        while ($row = mysqli_fetch_assoc($result)) {
            $row['id'] = $row['task_id'];
            $all_tasks[] = $row;
        }
    }
    mysqli_stmt_close($stmt_chk);
}

// 3. FMS Tasks
$fms_query_mt = "SELECT id, unique_key, step_name, planned, actual, status, duration, 
                        doer_name, 'fms' as task_type
                 FROM fms_tasks
                 WHERE doer_name = ?";
if ($stmt_fms_mt = mysqli_prepare($conn, $fms_query_mt)) {
    mysqli_stmt_bind_param($stmt_fms_mt, "s", $current_username);
    if (mysqli_stmt_execute($stmt_fms_mt)) {
        $result = mysqli_stmt_get_result($stmt_fms_mt);
        while ($row = mysqli_fetch_assoc($result)) {
            $planned_timestamp = parseFMSDateTimeString_doer($row['planned']);
            $actual_timestamp = parseFMSDateTimeString_doer($row['actual']);
            
            $is_delayed = 0;
            $delay_duration = null;
            
            $completed_statuses = ['completed', 'done', 'not done', 'can not be done'];
            $is_task_completed = in_array(strtolower($row['status'] ?? ''), $completed_statuses);
            
            if ($is_task_completed) {
                if ($actual_timestamp && $planned_timestamp && $actual_timestamp > $planned_timestamp) {
                    $is_delayed = 1;
                }
            } else {
                $current_time = time();
                if ($planned_timestamp && $current_time > $planned_timestamp) {
                    $is_delayed = 1;
                }
            }
            
            $row['planned_date'] = $planned_timestamp ? date('Y-m-d', $planned_timestamp) : '';
            $row['planned_time'] = $planned_timestamp ? date('H:i:s', $planned_timestamp) : '';
            $row['actual_date'] = $actual_timestamp ? date('Y-m-d', $actual_timestamp) : '';
            $row['actual_time'] = $actual_timestamp ? date('H:i:s', $actual_timestamp) : '';
            $row['is_delayed'] = $is_delayed;
            $row['delay_duration'] = $delay_duration;
            
            $all_tasks[] = $row;
        }
    }
    mysqli_stmt_close($stmt_fms_mt);
}

// Calculate manage_tasks.php stats
$manage_tasks_stats['total_tasks'] = count($all_tasks);

foreach ($all_tasks as $task) {
    $status = strtolower($task['status'] ?? '');
    $task_type = $task['task_type'] ?? '';
    
    // Determine if task is completed (for delayed_completed vs delayed_pending classification)
    // FMS tasks: ['completed', 'done', 'not done', 'can not be done'] are considered completed
    // Other tasks: ['completed', 'done'] are considered completed
    if ($task_type === 'fms') {
        $is_completed_for_delay = in_array($status, ['completed', 'done', 'not done', 'can not be done']);
    } else {
        $is_completed_for_delay = in_array($status, ['completed', 'done']);
    }
    
    // Completed tasks (for counting - matches manage_tasks.php line 525)
    $is_completed_for_count = in_array($status, ['completed', 'done']);
    if ($is_completed_for_count) {
        $manage_tasks_stats['completed_tasks']++;
    }
    
    // Delayed tasks (using is_delayed field or delay_duration)
    $is_delayed_task = $task['is_delayed'] == 1 || !empty($task['delay_duration']);
    if ($is_delayed_task) {
        $manage_tasks_stats['delayed_tasks']++;
        // Track delayed completed vs delayed pending
        if ($is_completed_for_delay) {
            $manage_tasks_stats['delayed_completed']++;
        } else {
            $manage_tasks_stats['delayed_pending']++;
        }
    }
    
    // Pending tasks
    if ($status === 'pending') {
        $manage_tasks_stats['pending_tasks']++;
    }
    
    // Shifted tasks
    if ($status === 'shifted') {
        $manage_tasks_stats['shifted_tasks']++;
    }
    
    // Store details
    $manage_tasks_details[] = [
        'id' => $task['id'] ?? $task['task_id'] ?? '',
        'type' => $task['task_type'],
        'status' => $status,
        'is_delayed' => $is_delayed_task,
        'planned_date' => $task['planned_date'] ?? '',
        'planned_time' => $task['planned_time'] ?? '',
        'actual_date' => $task['actual_date'] ?? '',
        'actual_time' => $task['actual_time'] ?? ''
    ];
}

// Calculate completion rate
$completion_rate_doer = $doer_stats['total_tasks'] > 0 ? round(($doer_stats['completed_on_time'] / $doer_stats['total_tasks']) * 100, 2) : 0;
$completion_rate_mt = $manage_tasks_stats['total_tasks'] > 0 ? round(($manage_tasks_stats['completed_tasks'] / $manage_tasks_stats['total_tasks']) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Stats Comparison Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 10px;
        }
        h2 {
            color: #6366f1;
            margin-top: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 20px 0;
        }
        .stat-box {
            border: 2px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .stat-box h3 {
            margin-top: 0;
            color: #555;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .stat-label {
            font-weight: 600;
            color: #666;
        }
        .stat-value {
            font-weight: bold;
            color: #333;
        }
        .match {
            color: #10b981;
        }
        .mismatch {
            color: #ef4444;
        }
        .comparison {
            margin-top: 30px;
            padding: 20px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }
        .task-details {
            margin-top: 20px;
        }
        .task-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .task-table th,
        .task-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .task-table th {
            background: #6366f1;
            color: white;
        }
        .task-table tr:hover {
            background: #f5f5f5;
        }
        .summary {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .summary h3 {
            margin-top: 0;
            color: #1976d2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Stats Comparison Test</h1>
        <p><strong>User:</strong> <?php echo $current_username; ?> (ID: <?php echo $current_user_id; ?>)</p>
        <p><strong>Test Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

        <div class="stats-grid">
            <!-- Doer Dashboard Stats -->
            <div class="stat-box">
                <h3>🎯 Doer Dashboard Stats</h3>
                <div class="stat-item">
                    <span class="stat-label">Total Tasks:</span>
                    <span class="stat-value"><?php echo $doer_stats['total_tasks']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Completed On Time:</span>
                    <span class="stat-value"><?php echo $doer_stats['completed_on_time']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Current Pending:</span>
                    <span class="stat-value"><?php echo $doer_stats['current_pending']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Current Delayed:</span>
                    <span class="stat-value"><?php echo $doer_stats['current_delayed']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">WND (Work Not Done):</span>
                    <span class="stat-value"><?php echo $doer_stats['wnd']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">WND On Time:</span>
                    <span class="stat-value"><?php echo $doer_stats['wnd_on_time']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Delayed Completed:</span>
                    <span class="stat-value"><?php 
                        $dc = 0;
                        foreach ($doer_task_details['delegation'] as $t) {
                            if (($t['is_delayed']) && in_array($t['status'], ['completed', 'done'])) $dc++;
                        }
                        foreach ($doer_task_details['fms'] as $t) {
                            if (($t['is_delayed']) && in_array($t['status'], ['completed', 'done', 'not done', 'can not be done'])) $dc++;
                        }
                        foreach ($doer_task_details['checklist'] as $t) {
                            if (($t['is_delayed']) && in_array($t['status'], ['completed', 'done'])) $dc++;
                        }
                        echo $dc;
                    ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Delayed Pending:</span>
                    <span class="stat-value"><?php 
                        $dp = 0;
                        foreach ($doer_task_details['delegation'] as $t) {
                            if (($t['is_delayed']) && !in_array($t['status'], ['completed', 'done'])) $dp++;
                        }
                        foreach ($doer_task_details['fms'] as $t) {
                            if (($t['is_delayed']) && !in_array($t['status'], ['completed', 'done', 'not done', 'can not be done'])) $dp++;
                        }
                        foreach ($doer_task_details['checklist'] as $t) {
                            if (($t['is_delayed']) && !in_array($t['status'], ['completed', 'done'])) $dp++;
                        }
                        echo $dp;
                    ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Completion Rate:</span>
                    <span class="stat-value"><?php echo $completion_rate_doer; ?>%</span>
                </div>
            </div>

            <!-- Manage Tasks Stats -->
            <div class="stat-box">
                <h3>📋 Manage Tasks Stats</h3>
                <div class="stat-item">
                    <span class="stat-label">Total Tasks:</span>
                    <span class="stat-value"><?php echo $manage_tasks_stats['total_tasks']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Completed Tasks:</span>
                    <span class="stat-value"><?php echo $manage_tasks_stats['completed_tasks']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Pending Tasks:</span>
                    <span class="stat-value"><?php echo $manage_tasks_stats['pending_tasks']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Delayed Tasks:</span>
                    <span class="stat-value"><?php echo $manage_tasks_stats['delayed_tasks']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Shifted Tasks:</span>
                    <span class="stat-value"><?php echo $manage_tasks_stats['shifted_tasks']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Delayed Completed:</span>
                    <span class="stat-value"><?php echo $manage_tasks_stats['delayed_completed']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Delayed Pending:</span>
                    <span class="stat-value"><?php echo $manage_tasks_stats['delayed_pending']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Completion Rate:</span>
                    <span class="stat-value"><?php echo $completion_rate_mt; ?>%</span>
                </div>
            </div>
        </div>

        <!-- Comparison Summary -->
        <div class="summary">
            <h3>🔍 Comparison Summary</h3>
            <?php
            // Calculate derived stats for doer dashboard
            $doer_delayed_completed = 0;
            $doer_delayed_pending = 0;
            
            // Calculate delayed completed and delayed pending from doer_stats
            // Delayed completed = tasks that are completed AND delayed
            // Delayed pending = tasks that are pending AND delayed
            foreach ($doer_task_details['delegation'] as $task) {
                $is_completed = in_array($task['status'], ['completed', 'done']);
                if ($task['is_delayed']) {
                    if ($is_completed) {
                        $doer_delayed_completed++;
                    } else {
                        $doer_delayed_pending++;
                    }
                }
            }
            foreach ($doer_task_details['fms'] as $task) {
                $is_completed = in_array($task['status'], ['completed', 'done', 'not done', 'can not be done']);
                if ($task['is_delayed']) {
                    if ($is_completed) {
                        $doer_delayed_completed++;
                    } else {
                        $doer_delayed_pending++;
                    }
                }
            }
            foreach ($doer_task_details['checklist'] as $task) {
                $is_completed = in_array($task['status'], ['completed', 'done']);
                if ($task['is_delayed']) {
                    if ($is_completed) {
                        $doer_delayed_completed++;
                    } else {
                        $doer_delayed_pending++;
                    }
                }
            }
            
            $total_match = ($doer_stats['total_tasks'] == $manage_tasks_stats['total_tasks']);
            $doer_total_completed = $doer_stats['completed_on_time'] + $doer_delayed_completed;
            $completed_match = ($doer_total_completed == $manage_tasks_stats['completed_tasks']);
            $delayed_match = ($doer_stats['current_delayed'] == $manage_tasks_stats['delayed_tasks']);
            $delayed_completed_match = ($doer_delayed_completed == $manage_tasks_stats['delayed_completed']);
            $delayed_pending_match = ($doer_delayed_pending == $manage_tasks_stats['delayed_pending']);
            
            echo "<p><strong>Total Tasks Match:</strong> ";
            echo $total_match ? '<span class="match">✅ MATCH</span>' : '<span class="mismatch">❌ MISMATCH (Doer: ' . $doer_stats['total_tasks'] . ' vs Manage: ' . $manage_tasks_stats['total_tasks'] . ')</span>';
            echo "</p>";
            
            echo "<p><strong>Completed Tasks Match:</strong> ";
            echo "<br>Doer: completed_on_time ({$doer_stats['completed_on_time']}) + delayed_completed ({$doer_delayed_completed}) = <strong>{$doer_total_completed}</strong>";
            echo "<br>Manage: completed_tasks = <strong>{$manage_tasks_stats['completed_tasks']}</strong>";
            echo "<br>";
            echo $completed_match ? '<span class="match">✅ MATCH</span>' : '<span class="mismatch">❌ MISMATCH</span>';
            echo "</p>";
            
            echo "<p><strong>Delayed Tasks Match:</strong> ";
            echo "<br>Doer: current_delayed = <strong>{$doer_stats['current_delayed']}</strong> (Completed: {$doer_delayed_completed}, Pending: {$doer_delayed_pending})";
            echo "<br>Manage: delayed_tasks = <strong>{$manage_tasks_stats['delayed_tasks']}</strong> (Completed: {$manage_tasks_stats['delayed_completed']}, Pending: {$manage_tasks_stats['delayed_pending']})";
            echo "<br>";
            echo $delayed_match ? '<span class="match">✅ MATCH</span>' : '<span class="mismatch">❌ MISMATCH</span>';
            echo "</p>";
            
            echo "<p><strong>Delayed Completed Match:</strong> ";
            echo $delayed_completed_match ? '<span class="match">✅ MATCH</span>' : '<span class="mismatch">❌ MISMATCH (Doer: ' . $doer_delayed_completed . ' vs Manage: ' . $manage_tasks_stats['delayed_completed'] . ')</span>';
            echo "</p>";
            
            echo "<p><strong>Delayed Pending Match:</strong> ";
            echo $delayed_pending_match ? '<span class="match">✅ MATCH</span>' : '<span class="mismatch">❌ MISMATCH (Doer: ' . $doer_delayed_pending . ' vs Manage: ' . $manage_tasks_stats['delayed_pending'] . ')</span>';
            echo "</p>";
            
            $all_match = $total_match && $completed_match && $delayed_match && $delayed_completed_match && $delayed_pending_match;
            
            if ($all_match) {
                echo "<p style='color: #10b981; font-weight: bold; font-size: 18px; margin-top: 15px;'>✅ All stats are matching correctly!</p>";
            } else {
                echo "<p style='color: #ef4444; font-weight: bold; font-size: 18px; margin-top: 15px;'>⚠️ Some stats are not matching. Check details below.</p>";
            }
            ?>
        </div>

        <!-- Task Breakdown by Type -->
        <h2>📊 Task Breakdown</h2>
        <div class="task-details">
            <h3>Delegation Tasks: <?php echo count($doer_task_details['delegation']); ?></h3>
            <table class="task-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Is Delayed</th>
                        <th>Planned Date/Time</th>
                        <th>Actual Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($doer_task_details['delegation'], 0, 20) as $task): ?>
                    <tr>
                        <td><?php echo $task['id']; ?></td>
                        <td><?php echo $task['status']; ?></td>
                        <td><?php echo $task['is_delayed'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $task['planned_date'] . ' ' . $task['planned_time']; ?></td>
                        <td><?php echo ($task['actual_date'] ?? 'N/A') . ' ' . ($task['actual_time'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($doer_task_details['delegation']) > 20): ?>
                    <tr><td colspan="5"><em>... and <?php echo count($doer_task_details['delegation']) - 20; ?> more tasks</em></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3>FMS Tasks: <?php echo count($doer_task_details['fms']); ?></h3>
            <table class="task-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Is Delayed</th>
                        <th>Planned</th>
                        <th>Actual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($doer_task_details['fms'], 0, 20) as $task): ?>
                    <tr>
                        <td><?php echo $task['id']; ?></td>
                        <td><?php echo $task['status']; ?></td>
                        <td><?php echo $task['is_delayed'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $task['planned'] ?? 'N/A'; ?></td>
                        <td><?php echo $task['actual'] ?? 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($doer_task_details['fms']) > 20): ?>
                    <tr><td colspan="5"><em>... and <?php echo count($doer_task_details['fms']) - 20; ?> more tasks</em></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3>Checklist Tasks: <?php echo count($doer_task_details['checklist']); ?></h3>
            <table class="task-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Is Delayed</th>
                        <th>Task Date</th>
                        <th>Actual Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($doer_task_details['checklist'], 0, 20) as $task): ?>
                    <tr>
                        <td><?php echo $task['id']; ?></td>
                        <td><?php echo $task['status']; ?></td>
                        <td><?php echo $task['is_delayed'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $task['task_date']; ?></td>
                        <td><?php echo $task['actual_date'] ?? 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($doer_task_details['checklist']) > 20): ?>
                    <tr><td colspan="5"><em>... and <?php echo count($doer_task_details['checklist']) - 20; ?> more tasks</em></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Detailed Comparison -->
        <h2>🔬 Detailed Analysis</h2>
        <div class="comparison">
            <h3>Key Differences to Check:</h3>
            <ul>
                <li><strong>Total Tasks:</strong> Doer Dashboard counts all tasks for the user. Manage Tasks may have different filtering.</li>
                <li><strong>Completed Tasks:</strong> Doer Dashboard splits into "Completed On Time" and "Delayed Completed". Manage Tasks counts all completed.</li>
                <li><strong>Delayed Tasks:</strong> Both should use dynamic delay calculation. Check if delays are recalculated correctly.</li>
                <li><strong>Checklist Tasks:</strong> Both should filter by frequency (Daily = today, Others = current week).</li>
                <li><strong>FMS Tasks:</strong> Both should recalculate delays dynamically (not use DB is_delayed field).</li>
            </ul>
            
            <h3>Expected Relationships:</h3>
            <ul>
                <li><strong>Doer Dashboard:</strong> completed_on_time + current_delayed (completed) = Manage Tasks completed_tasks</li>
                <li><strong>Doer Dashboard:</strong> current_delayed (all) = Manage Tasks delayed_tasks</li>
                <li><strong>Both:</strong> Should use same date filtering for checklist tasks</li>
                <li><strong>Both:</strong> Should recalculate delays dynamically for FMS and completed delegation tasks</li>
            </ul>
        </div>
    </div>
</body>
</html>

<?php require_once "../includes/footer.php"; ?>

