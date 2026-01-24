<?php
/**
 * Test File: Admin Dashboard Pending Tasks Logic
 * This file tests how pending tasks are calculated in the admin dashboard
 */

session_start();
require_once "../includes/config.php";
require_once "../includes/functions.php";
require_once "../includes/dashboard_components.php";

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    die("Access denied. Admin access required.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test: Admin Dashboard Pending Tasks Logic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #1a1a1a;
            color: #e0e0e0;
        }
        .test-section {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .test-section h2 {
            color: #6366f1;
            margin-top: 0;
        }
        .test-case {
            background: #333;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #6366f1;
            border-radius: 4px;
        }
        .test-case h3 {
            color: #8b5cf6;
            margin-top: 0;
        }
        .result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .result.pass {
            background: rgba(16, 185, 129, 0.2);
            border-left: 4px solid #10b981;
            color: #10b981;
        }
        .result.fail {
            background: rgba(239, 68, 68, 0.2);
            border-left: 4px solid #ef4444;
            color: #ef4444;
        }
        .result.info {
            background: rgba(59, 130, 246, 0.2);
            border-left: 4px solid #3b82f6;
            color: #3b82f6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #444;
        }
        th {
            background: #333;
            color: #6366f1;
        }
        .code {
            background: #1a1a1a;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            color: #f59e0b;
        }
    </style>
</head>
<body>
    <h1>🔍 Test: Admin Dashboard Pending Tasks Logic</h1>
    
    <?php
    // Test 1: isTaskInDateRange Function
    echo '<div class="test-section">';
    echo '<h2>Test 1: isTaskInDateRange Function</h2>';
    echo '<p>This function determines if a task should be included in date range filtering.</p>';
    
    $test_cases = [
        [
            'name' => 'Task with planned_date in range, actual_date outside',
            'planned_date' => '2024-01-15',
            'actual_date' => '2024-02-20',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'expected' => true,
            'reason' => 'Should include because planned_date is in range'
        ],
        [
            'name' => 'Task with planned_date outside, actual_date in range',
            'planned_date' => '2024-02-15',
            'actual_date' => '2024-01-20',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'expected' => true,
            'reason' => 'Should include because actual_date is in range (CURRENT BEHAVIOR)'
        ],
        [
            'name' => 'Task with both dates outside range',
            'planned_date' => '2024-02-15',
            'actual_date' => '2024-02-20',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'expected' => false,
            'reason' => 'Should exclude because both dates are outside range'
        ],
        [
            'name' => 'Task with planned_date in range, no actual_date',
            'planned_date' => '2024-01-15',
            'actual_date' => '',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'expected' => true,
            'reason' => 'Should include because planned_date is in range'
        ],
        [
            'name' => 'Task with no planned_date, actual_date in range',
            'planned_date' => '',
            'actual_date' => '2024-01-20',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'expected' => true,
            'reason' => 'Should include because actual_date is in range (CURRENT BEHAVIOR)'
        ]
    ];
    
    foreach ($test_cases as $test) {
        $result = isTaskInDateRange($test['planned_date'], $test['actual_date'], $test['date_from'], $test['date_to']);
        $passed = $result === $test['expected'];
        
        echo '<div class="test-case">';
        echo '<h3>' . htmlspecialchars($test['name']) . '</h3>';
        echo '<p><strong>Planned Date:</strong> <span class="code">' . htmlspecialchars($test['planned_date'] ?: 'NULL') . '</span></p>';
        echo '<p><strong>Actual Date:</strong> <span class="code">' . htmlspecialchars($test['actual_date'] ?: 'NULL') . '</span></p>';
        echo '<p><strong>Date Range:</strong> <span class="code">' . htmlspecialchars($test['date_from']) . ' to ' . htmlspecialchars($test['date_to']) . '</span></p>';
        echo '<p><strong>Expected:</strong> ' . ($test['expected'] ? 'TRUE (included)' : 'FALSE (excluded)') . '</p>';
        echo '<p><strong>Actual:</strong> ' . ($result ? 'TRUE (included)' : 'FALSE (excluded)') . '</p>';
        echo '<p><strong>Reason:</strong> ' . htmlspecialchars($test['reason']) . '</p>';
        echo '<div class="result ' . ($passed ? 'pass' : 'fail') . '">';
        echo $passed ? '✅ PASS' : '❌ FAIL';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    
    // Test 2: classifyTaskForStats Function
    echo '<div class="test-section">';
    echo '<h2>Test 2: classifyTaskForStats Function</h2>';
    echo '<p>This function classifies tasks as pending, delayed, or completed.</p>';
    
    $now = time();
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    $classification_tests = [
        [
            'name' => 'Pending task with future planned date',
            'status' => 'pending',
            'planned_date' => $tomorrow,
            'planned_time' => '10:00:00',
            'actual_date' => '',
            'actual_time' => '',
            'expected_pending' => true,
            'expected_delayed' => false
        ],
        [
            'name' => 'Pending task with past planned date (should be delayed)',
            'status' => 'pending',
            'planned_date' => $yesterday,
            'planned_time' => '10:00:00',
            'actual_date' => '',
            'actual_time' => '',
            'expected_pending' => false,
            'expected_delayed' => true
        ],
        [
            'name' => 'Completed task',
            'status' => 'completed',
            'planned_date' => $yesterday,
            'planned_time' => '10:00:00',
            'actual_date' => $today,
            'actual_time' => '14:00:00',
            'expected_pending' => false,
            'expected_delayed' => false
        ],
        [
            'name' => 'Shifted task not delayed',
            'status' => 'shifted',
            'planned_date' => $tomorrow,
            'planned_time' => '10:00:00',
            'actual_date' => '',
            'actual_time' => '',
            'expected_pending' => false,
            'expected_delayed' => false
        ],
        [
            'name' => 'Shifted task with past planned date (should be delayed)',
            'status' => 'shifted',
            'planned_date' => $yesterday,
            'planned_time' => '10:00:00',
            'actual_date' => '',
            'actual_time' => '',
            'expected_pending' => false,
            'expected_delayed' => true
        ]
    ];
    
    foreach ($classification_tests as $test) {
        $classification = classifyTaskForStats(
            $test['status'],
            $test['planned_date'],
            $test['planned_time'],
            $test['actual_date'],
            $test['actual_time'],
            $now
        );
        
        $pending_match = $classification['is_pending'] === $test['expected_pending'];
        $delayed_match = $classification['is_delayed'] === $test['expected_delayed'];
        $passed = $pending_match && $delayed_match;
        
        echo '<div class="test-case">';
        echo '<h3>' . htmlspecialchars($test['name']) . '</h3>';
        echo '<p><strong>Status:</strong> <span class="code">' . htmlspecialchars($test['status']) . '</span></p>';
        echo '<p><strong>Planned:</strong> <span class="code">' . htmlspecialchars($test['planned_date'] . ' ' . $test['planned_time']) . '</span></p>';
        echo '<p><strong>Actual:</strong> <span class="code">' . htmlspecialchars($test['actual_date'] ? ($test['actual_date'] . ' ' . $test['actual_time']) : 'NULL') . '</span></p>';
        echo '<p><strong>Expected Pending:</strong> ' . ($test['expected_pending'] ? 'TRUE' : 'FALSE') . ' | <strong>Actual:</strong> ' . ($classification['is_pending'] ? 'TRUE' : 'FALSE') . '</p>';
        echo '<p><strong>Expected Delayed:</strong> ' . ($test['expected_delayed'] ? 'TRUE' : 'FALSE') . ' | <strong>Actual:</strong> ' . ($classification['is_delayed'] ? 'TRUE' : 'FALSE') . '</p>';
        echo '<div class="result ' . ($passed ? 'pass' : 'fail') . '">';
        echo $passed ? '✅ PASS' : '❌ FAIL';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    
    // Test 3: Real Database Query Test
    echo '<div class="test-section">';
    echo '<h2>Test 3: Real Database Query - calculateGlobalTaskStats</h2>';
    echo '<p>Testing with actual data from the database.</p>';
    
    // Test with different date ranges
    $date_ranges = [
        [
            'name' => 'Current Month',
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t')
        ],
        [
            'name' => 'Last 7 Days',
            'date_from' => date('Y-m-d', strtotime('-7 days')),
            'date_to' => date('Y-m-d')
        ],
        [
            'name' => 'No Date Range (All Tasks)',
            'date_from' => null,
            'date_to' => null
        ]
    ];
    
    foreach ($date_ranges as $range) {
        echo '<div class="test-case">';
        echo '<h3>' . htmlspecialchars($range['name']) . '</h3>';
        
        if ($range['date_from'] && $range['date_to']) {
            echo '<p><strong>Date Range:</strong> <span class="code">' . htmlspecialchars($range['date_from']) . ' to ' . htmlspecialchars($range['date_to']) . '</span></p>';
        } else {
            echo '<p><strong>Date Range:</strong> <span class="code">ALL TASKS (no filter)</span></p>';
        }
        
        $stats = calculateGlobalTaskStats($conn, $range['date_from'], $range['date_to']);
        
        echo '<table>';
        echo '<tr><th>Metric</th><th>Value</th></tr>';
        echo '<tr><td>Total Tasks</td><td>' . number_format($stats['total_tasks']) . '</td></tr>';
        echo '<tr><td>Completed Tasks</td><td>' . number_format($stats['completed_tasks']) . '</td></tr>';
        echo '<tr><td><strong>Pending Tasks</strong></td><td><strong>' . number_format($stats['pending_tasks']) . '</strong></td></tr>';
        echo '<tr><td>Delayed Tasks</td><td>' . number_format($stats['delayed_tasks']) . '</td></tr>';
        echo '<tr><td>Shifted Tasks</td><td>' . number_format($stats['shifted_tasks']) . '</td></tr>';
        echo '<tr><td>Total Tasks (All)</td><td>' . number_format($stats['total_tasks_all']) . '</td></tr>';
        echo '</table>';
        
        echo '<div class="result info">';
        echo '<strong>Pending Tasks Breakdown:</strong><br>';
        echo 'Pending Tasks = task_pending_count + delayed_task_count + shifted_not_delayed_count<br>';
        echo 'This includes tasks that are pending, delayed, or shifted (not delayed).';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    
    // Test 4: Detailed Task Analysis
    echo '<div class="test-section">';
    echo '<h2>Test 4: Detailed Task Analysis</h2>';
    echo '<p>Analyzing actual tasks to see which ones are counted as pending.</p>';
    
    $date_from = date('Y-m-01'); // First day of current month
    $date_to = date('Y-m-t');    // Last day of current month
    
    echo '<p><strong>Date Range:</strong> <span class="code">' . htmlspecialchars($date_from) . ' to ' . htmlspecialchars($date_to) . '</span></p>';
    
    // Get sample tasks
    $sample_tasks = [];
    
    // Delegation tasks
    $delegation_sql = "SELECT id, unique_id, status, planned_date, planned_time, actual_date, actual_time 
                      FROM tasks 
                      ORDER BY planned_date DESC 
                      LIMIT 10";
    $result = mysqli_query($conn, $delegation_sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $sample_tasks[] = array_merge($row, ['task_type' => 'delegation']);
        }
    }
    
    // Checklist tasks
    $checklist_sql = "SELECT id, task_code as unique_id, COALESCE(status, 'pending') as status, 
                      task_date as planned_date, actual_date, actual_time 
                      FROM checklist_subtasks 
                      ORDER BY task_date DESC 
                      LIMIT 10";
    $result = mysqli_query($conn, $checklist_sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $sample_tasks[] = array_merge($row, ['task_type' => 'checklist', 'planned_time' => '23:59:59']);
        }
    }
    
    echo '<table>';
    echo '<tr>';
    echo '<th>Task Type</th>';
    echo '<th>ID</th>';
    echo '<th>Status</th>';
    echo '<th>Planned Date</th>';
    echo '<th>Actual Date</th>';
    echo '<th>In Date Range?</th>';
    echo '<th>Classification</th>';
    echo '<th>Counted as Pending?</th>';
    echo '</tr>';
    
    $pending_count = 0;
    $in_range_count = 0;
    
    foreach ($sample_tasks as $task) {
        $planned_date = $task['planned_date'] ?? '';
        $actual_date = $task['actual_date'] ?? '';
        $status = $task['status'] ?? 'pending';
        $planned_time = $task['planned_time'] ?? '23:59:59';
        $actual_time = $task['actual_time'] ?? '';
        
        $in_range = isTaskInDateRange($planned_date, $actual_date, $date_from, $date_to);
        if ($in_range) {
            $in_range_count++;
        }
        
        $classification = classifyTaskForStats($status, $planned_date, $planned_time, $actual_date, $actual_time);
        
        $counted_as_pending = false;
        if ($in_range && !$classification['is_completed']) {
            if ($classification['is_pending'] || $classification['is_delayed']) {
                $counted_as_pending = true;
                $pending_count++;
            }
            // Also check for shifted tasks
            $normalized_status = normalizeTaskStatus($status);
            if (($normalized_status === 'shifted' || $status === '🔁') && !$classification['is_delayed']) {
                $counted_as_pending = true;
                $pending_count++;
            }
        }
        
        $in_range_reason = '';
        if ($in_range) {
            if (!empty($planned_date)) {
                $planned_ts = strtotime($planned_date . ' 00:00:00');
                $from_ts = strtotime($date_from . ' 00:00:00');
                $to_ts = strtotime($date_to . ' 23:59:59');
                if ($planned_ts >= $from_ts && $planned_ts <= $to_ts) {
                    $in_range_reason = 'planned_date in range';
                }
            }
            if (!empty($actual_date)) {
                $actual_ts = strtotime($actual_date . ' 00:00:00');
                $from_ts = strtotime($date_from . ' 00:00:00');
                $to_ts = strtotime($date_to . ' 23:59:59');
                if ($actual_ts >= $from_ts && $actual_ts <= $to_ts) {
                    $in_range_reason .= ($in_range_reason ? ' OR ' : '') . 'actual_date in range';
                }
            }
        }
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($task['task_type']) . '</td>';
        echo '<td>' . htmlspecialchars($task['unique_id'] ?? $task['id']) . '</td>';
        echo '<td>' . htmlspecialchars($status) . '</td>';
        echo '<td>' . htmlspecialchars($planned_date ?: 'NULL') . '</td>';
        echo '<td>' . htmlspecialchars($actual_date ?: 'NULL') . '</td>';
        echo '<td>' . ($in_range ? '✅ YES' : '❌ NO') . '<br><small>' . htmlspecialchars($in_range_reason) . '</small></td>';
        echo '<td>';
        echo 'Pending: ' . ($classification['is_pending'] ? 'YES' : 'NO') . '<br>';
        echo 'Delayed: ' . ($classification['is_delayed'] ? 'YES' : 'NO') . '<br>';
        echo 'Completed: ' . ($classification['is_completed'] ? 'YES' : 'NO');
        echo '</td>';
        echo '<td>' . ($counted_as_pending ? '✅ YES' : '❌ NO') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    echo '<div class="result info">';
    echo '<strong>Summary:</strong><br>';
    echo 'Tasks in date range: ' . $in_range_count . '<br>';
    echo 'Tasks counted as pending: ' . $pending_count . '<br>';
    echo '<br><strong>Note:</strong> Tasks are included in date range if EITHER planned_date OR actual_date falls in the range.';
    echo '</div>';
    echo '</div>';
    
    // Test 5: Comparison - Planned Date Only vs Current Logic
    echo '<div class="test-section">';
    echo '<h2>Test 5: Comparison - Planned Date Only vs Current Logic</h2>';
    echo '<p>Comparing what would happen if we only used planned_date vs current logic (planned_date OR actual_date).</p>';
    
    // Create a modified version of isTaskInDateRange that only checks planned_date
    function isTaskInDateRangePlannedOnly($planned_date, $actual_date, $date_from, $date_to) {
        if (!$date_from || !$date_to) {
            return true;
        }
        
        $from_ts = strtotime($date_from . ' 00:00:00');
        $to_ts = strtotime($date_to . ' 23:59:59');
        
        if (!empty($planned_date)) {
            $planned_ts = strtotime($planned_date . ' 00:00:00');
            if ($planned_ts >= $from_ts && $planned_ts <= $to_ts) {
                return true;
            }
        }
        
        return false;
    }
    
    $comparison_tasks = [];
    $delegation_sql = "SELECT id, unique_id, status, planned_date, planned_time, actual_date, actual_time 
                      FROM tasks 
                      WHERE planned_date IS NOT NULL OR actual_date IS NOT NULL
                      ORDER BY planned_date DESC 
                      LIMIT 20";
    $result = mysqli_query($conn, $delegation_sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $comparison_tasks[] = $row;
        }
    }
    
    $current_logic_count = 0;
    $planned_only_count = 0;
    $differences = [];
    
    foreach ($comparison_tasks as $task) {
        $planned_date = $task['planned_date'] ?? '';
        $actual_date = $task['actual_date'] ?? '';
        
        $current_in_range = isTaskInDateRange($planned_date, $actual_date, $date_from, $date_to);
        $planned_only_in_range = isTaskInDateRangePlannedOnly($planned_date, $actual_date, $date_from, $date_to);
        
        if ($current_in_range) {
            $current_logic_count++;
        }
        if ($planned_only_in_range) {
            $planned_only_count++;
        }
        
        if ($current_in_range !== $planned_only_in_range) {
            $differences[] = [
                'task_id' => $task['unique_id'] ?? $task['id'],
                'planned_date' => $planned_date,
                'actual_date' => $actual_date,
                'current_logic' => $current_in_range,
                'planned_only' => $planned_only_in_range
            ];
        }
    }
    
    echo '<div class="result info">';
    echo '<strong>Comparison Results:</strong><br>';
    echo 'Current Logic (planned_date OR actual_date): ' . $current_logic_count . ' tasks included<br>';
    echo 'Planned Date Only Logic: ' . $planned_only_count . ' tasks included<br>';
    echo 'Difference: ' . ($current_logic_count - $planned_only_count) . ' tasks<br>';
    echo '</div>';
    
    if (!empty($differences)) {
        echo '<h3>Tasks with Different Results:</h3>';
        echo '<table>';
        echo '<tr>';
        echo '<th>Task ID</th>';
        echo '<th>Planned Date</th>';
        echo '<th>Actual Date</th>';
        echo '<th>Current Logic</th>';
        echo '<th>Planned Only</th>';
        echo '<th>Reason</th>';
        echo '</tr>';
        
        foreach ($differences as $diff) {
            $reason = '';
            if ($diff['current_logic'] && !$diff['planned_only']) {
                $reason = 'Included because actual_date is in range (but planned_date is not)';
            } elseif (!$diff['current_logic'] && $diff['planned_only']) {
                $reason = 'Excluded in current logic but would be included with planned_date only';
            }
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($diff['task_id']) . '</td>';
            echo '<td>' . htmlspecialchars($diff['planned_date'] ?: 'NULL') . '</td>';
            echo '<td>' . htmlspecialchars($diff['actual_date'] ?: 'NULL') . '</td>';
            echo '<td>' . ($diff['current_logic'] ? '✅ Included' : '❌ Excluded') . '</td>';
            echo '<td>' . ($diff['planned_only'] ? '✅ Included' : '❌ Excluded') . '</td>';
            echo '<td>' . htmlspecialchars($reason) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="result pass">✅ No differences found - both logics produce the same results for these tasks.</div>';
    }
    echo '</div>';
    ?>
    
    <div class="test-section">
        <h2>Summary</h2>
        <div class="result info">
            <strong>Key Findings:</strong>
            <ul>
                <li><strong>isTaskInDateRange</strong> currently checks BOTH planned_date OR actual_date</li>
                <li>This means tasks can be included even if their planned_date is outside the range, as long as actual_date is in range</li>
                <li><strong>classifyTaskForStats</strong> determines if a task is pending based on status and planned date/time</li>
                <li><strong>Pending count</strong> = task_pending_count + delayed_task_count + shifted_not_delayed_count</li>
                <li>If you want to filter based on planned_date only (like manage_tasks.php), the isTaskInDateRange function needs to be modified</li>
            </ul>
        </div>
    </div>
</body>
</html>

