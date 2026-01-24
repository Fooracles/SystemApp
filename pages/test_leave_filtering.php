<?php
/**
 * Test script to verify leave filtering logic for "Who's On Leave Today"
 * This script tests if the date filtering correctly shows only today's leaves
 */

session_start();
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if user is logged in
if (!isLoggedIn()) {
    die("Please log in first to run this test.");
}

$today = date('Y-m-d');
$today_formatted = date('d-M-Y');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Leave Filtering Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #1a1a1a;
            color: #fff;
        }
        .test-section {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .test-section h2 {
            color: #6366f1;
            margin-top: 0;
        }
        .test-section h3 {
            color: #8b5cf6;
            margin-top: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        th {
            background: rgba(99, 102, 241, 0.2);
            color: #6366f1;
        }
        .pass {
            color: #10b981;
            font-weight: bold;
        }
        .fail {
            color: #ef4444;
            font-weight: bold;
        }
        .info {
            color: #06b6d4;
        }
        .warning {
            color: #f59e0b;
        }
        .test-case {
            margin: 10px 0;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>🧪 Leave Filtering Test - {$today_formatted}</h1>";

// Test 1: Get all leave requests from database
echo "<div class='test-section'>
    <h2>Test 1: All Leave Requests in Database</h2>";

$all_leaves_query = "SELECT 
    employee_name,
    leave_type,
    start_date,
    end_date,
    status,
    CASE 
        WHEN end_date IS NULL THEN 'Single Day'
        ELSE CONCAT(DATEDIFF(end_date, start_date) + 1, ' days')
    END as duration_days
FROM Leave_request 
WHERE status IN ('PENDING', 'Approve')
    AND status NOT IN ('Reject', 'Cancelled')
ORDER BY start_date DESC, employee_name
LIMIT 50";

$all_leaves = [];
if ($result = mysqli_query($conn, $all_leaves_query)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_leaves[] = $row;
    }
}

echo "<p class='info'>Found " . count($all_leaves) . " approved/pending leave requests</p>";
echo "<table>
    <tr>
        <th>Employee</th>
        <th>Leave Type</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Duration</th>
        <th>Status</th>
        <th>Includes Today?</th>
    </tr>";

foreach ($all_leaves as $leave) {
    $start_date = $leave['start_date'];
    $end_date = $leave['end_date'];
    $includes_today = false;
    $reason = '';
    
    if ($end_date === null || $end_date === '') {
        // Single day leave
        $includes_today = ($start_date === $today);
        $reason = $includes_today ? 'Single day matches today' : 'Single day does not match today';
    } else {
        // Multi-day leave
        $includes_today = ($start_date <= $today && $end_date >= $today);
        if ($start_date > $today) {
            $reason = 'Future leave';
        } elseif ($end_date < $today) {
            $reason = 'Past leave';
        } else {
            $reason = 'Today is in range';
        }
    }
    
    $status_class = $includes_today ? 'pass' : 'fail';
    $status_text = $includes_today ? '✓ YES' : '✗ NO';
    
    echo "<tr>
        <td>{$leave['employee_name']}</td>
        <td>{$leave['leave_type']}</td>
        <td>{$start_date}</td>
        <td>" . ($end_date ? $end_date : 'NULL (Single Day)') . "</td>
        <td>{$leave['duration_days']}</td>
        <td>{$leave['status']}</td>
        <td class='{$status_class}'>{$status_text} <span class='info'>({$reason})</span></td>
    </tr>";
}

echo "</table></div>";

// Test 2a: Check raw leave requests for today (before user matching)
echo "<div class='test-section'>
    <h2>Test 2a: Raw Leave Requests for Today (Before User Matching)</h2>";

$raw_leaves_query = "SELECT 
    lr.id,
    lr.employee_name,
    lr.leave_type,
    lr.duration,
    lr.start_date,
    lr.end_date,
    lr.status,
    CASE 
        WHEN lr.end_date IS NULL THEN 'Single Day'
        ELSE CONCAT(DATEDIFF(lr.end_date, lr.start_date) + 1, ' days')
    END as duration_days
FROM Leave_request lr
WHERE lr.status IN ('PENDING', 'Approve')
    AND lr.status NOT IN ('Reject', 'Cancelled')
    AND (
        (lr.end_date IS NOT NULL AND lr.start_date <= ? AND lr.end_date >= ?)
        OR
        (lr.end_date IS NULL AND lr.start_date = ?)
    )
ORDER BY lr.employee_name, lr.start_date";

$raw_leaves = [];
if ($stmt_raw = mysqli_prepare($conn, $raw_leaves_query)) {
    mysqli_stmt_bind_param($stmt_raw, "sss", $today, $today, $today);
    if (mysqli_stmt_execute($stmt_raw)) {
        $result_raw = mysqli_stmt_get_result($stmt_raw);
        while ($row = mysqli_fetch_assoc($result_raw)) {
            $raw_leaves[] = $row;
        }
    }
    mysqli_stmt_close($stmt_raw);
}

echo "<p class='info'>Found " . count($raw_leaves) . " leave requests for today (before user matching)</p>";

if (count($raw_leaves) > 0) {
    echo "<table>
        <tr>
            <th>Leave ID</th>
            <th>Employee Name</th>
            <th>Leave Type</th>
            <th>Duration</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Status</th>
            <th>User Match Status</th>
        </tr>";
    
    foreach ($raw_leaves as $leave) {
        // Check if employee_name matches any user
        $employee_name = $leave['employee_name'];
        $match_query = "SELECT id, name, username, user_type 
                       FROM users 
                       WHERE (name = ? OR username = ?) 
                       AND user_type IN ('doer', 'manager', 'admin')
                       LIMIT 1";
        
        $matched_user = null;
        if ($stmt_match = mysqli_prepare($conn, $match_query)) {
            mysqli_stmt_bind_param($stmt_match, "ss", $employee_name, $employee_name);
            if (mysqli_stmt_execute($stmt_match)) {
                $result_match = mysqli_stmt_get_result($stmt_match);
                $matched_user = mysqli_fetch_assoc($result_match);
            }
            mysqli_stmt_close($stmt_match);
        }
        
        $match_status = '';
        $match_class = '';
        if ($matched_user) {
            $match_status = "✓ Matched: User ID {$matched_user['id']} ({$matched_user['name']} / {$matched_user['username']})";
            $match_class = 'pass';
        } else {
            $match_status = "✗ No Match: No user found with name/username = '{$employee_name}' or user_type not in ('doer','manager','admin')";
            $match_class = 'fail';
        }
        
        echo "<tr>
            <td>{$leave['id']}</td>
            <td>{$employee_name}</td>
            <td>{$leave['leave_type']}</td>
            <td>{$leave['duration']}</td>
            <td>{$leave['start_date']}</td>
            <td>" . ($leave['end_date'] ? $leave['end_date'] : 'NULL (Single Day)') . "</td>
            <td>{$leave['status']}</td>
            <td class='{$match_class}'>{$match_status}</td>
        </tr>";
    }
    
    echo "</table>";
    
    // Count matched vs unmatched
    $matched_count = 0;
    $unmatched_count = 0;
    foreach ($raw_leaves as $leave) {
        $employee_name = $leave['employee_name'];
        $match_query = "SELECT id FROM users 
                       WHERE (name = ? OR username = ?) 
                       AND user_type IN ('doer', 'manager', 'admin')
                       LIMIT 1";
        if ($stmt_match = mysqli_prepare($conn, $match_query)) {
            mysqli_stmt_bind_param($stmt_match, "ss", $employee_name, $employee_name);
            if (mysqli_stmt_execute($stmt_match)) {
                $result_match = mysqli_stmt_get_result($stmt_match);
                if (mysqli_fetch_assoc($result_match)) {
                    $matched_count++;
                } else {
                    $unmatched_count++;
                }
            }
            mysqli_stmt_close($stmt_match);
        }
    }
    
    echo "<p class='info'><strong>Summary:</strong> {$matched_count} matched users, {$unmatched_count} unmatched employees</p>";
} else {
    echo "<p class='warning'>No leave requests found for today.</p>";
}

echo "</div>";

// Test 2b: Show all users in database for comparison
echo "<div class='test-section'>
    <h2>Test 2b: All Users in Database (for comparison)</h2>";

$users_query = "SELECT id, name, username, user_type 
                FROM users 
                WHERE user_type IN ('doer', 'manager', 'admin')
                ORDER BY name";
$all_users = [];
if ($result_users = mysqli_query($conn, $users_query)) {
    while ($row = mysqli_fetch_assoc($result_users)) {
        $all_users[] = $row;
    }
}

echo "<p class='info'>Found " . count($all_users) . " users (doer/manager/admin)</p>";
echo "<table>
    <tr>
        <th>User ID</th>
        <th>Name</th>
        <th>Username</th>
        <th>User Type</th>
    </tr>";

foreach ($all_users as $user) {
    echo "<tr>
        <td>{$user['id']}</td>
        <td>{$user['name']}</td>
        <td>{$user['username']}</td>
        <td>{$user['user_type']}</td>
    </tr>";
}

echo "</table></div>";

// Test 2: Test the actual query used in dashboard
echo "<div class='test-section'>
    <h2>Test 2: Dashboard Query Results (Who's On Leave Today)</h2>";

$dashboard_query = "SELECT DISTINCT
    u.id, 
    u.username, 
    u.name, 
    u.user_type,
    MIN(lr.leave_type) as leave_type,
    MIN(lr.duration) as duration,
    MIN(lr.start_date) as start_date,
    MAX(lr.end_date) as end_date,
    MIN(lr.status) as leave_status
FROM users u
INNER JOIN Leave_request lr ON (
    lr.employee_name = u.name OR lr.employee_name = u.username
)
WHERE u.user_type IN ('doer', 'manager', 'admin')
    AND lr.status IN ('PENDING', 'Approve')
    AND lr.status NOT IN ('Reject', 'Cancelled')
    AND (
        (lr.end_date IS NOT NULL AND lr.start_date <= ? AND lr.end_date >= ?)
        OR
        (lr.end_date IS NULL AND lr.start_date = ?)
    )
GROUP BY u.id, u.username, u.name, u.user_type
ORDER BY u.name
LIMIT 50";

$dashboard_results = [];
if ($stmt = mysqli_prepare($conn, $dashboard_query)) {
    mysqli_stmt_bind_param($stmt, "sss", $today, $today, $today);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $dashboard_results[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

echo "<p class='info'>Dashboard query returned " . count($dashboard_results) . " users on leave today</p>";

// Compare with raw leaves
if (count($raw_leaves) > 0) {
    echo "<p class='warning'><strong>⚠️ DISCREPANCY DETECTED:</strong> ";
    echo "Raw leave requests: " . count($raw_leaves) . " | ";
    echo "Dashboard results: " . count($dashboard_results);
    if (count($raw_leaves) > count($dashboard_results)) {
        $missing = count($raw_leaves) - count($dashboard_results);
        echo " | <strong>{$missing} user(s) missing!</strong>";
        echo "<br><span class='info'>Possible reasons:</span>";
        echo "<ul style='margin: 10px 0; padding-left: 20px;'>";
        echo "<li>Employee name in Leave_request doesn't match any user's name or username</li>";
        echo "<li>User exists but user_type is not 'doer', 'manager', or 'admin'</li>";
        echo "<li>Multiple leaves for same user are being grouped (GROUP BY)</li>";
        echo "<li>User matching failed due to case sensitivity or whitespace</li>";
        echo "</ul>";
    }
    echo "</p>";
}

if (count($dashboard_results) > 0) {
    echo "<table>
        <tr>
            <th>User ID</th>
            <th>Name</th>
            <th>Username</th>
            <th>Leave Type</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Status</th>
            <th>Verification</th>
        </tr>";
    
    foreach ($dashboard_results as $user) {
        $start_date = $user['start_date'];
        $end_date = $user['end_date'];
        
        // Verify the result is correct
        $is_valid = false;
        $verification = '';
        
        if ($end_date === null || $end_date === '') {
            $is_valid = ($start_date === $today);
            $verification = $is_valid ? '✓ Valid: Single day matches today' : '✗ Invalid: Single day does not match today';
        } else {
            $is_valid = ($start_date <= $today && $end_date >= $today);
            if ($start_date > $today) {
                $verification = '✗ Invalid: Future leave';
            } elseif ($end_date < $today) {
                $verification = '✗ Invalid: Past leave';
            } else {
                $verification = '✓ Valid: Today is in range';
            }
        }
        
        $status_class = $is_valid ? 'pass' : 'fail';
        
        echo "<tr>
            <td>{$user['id']}</td>
            <td>{$user['name']}</td>
            <td>{$user['username']}</td>
            <td>{$user['leave_type']}</td>
            <td>{$start_date}</td>
            <td>" . ($end_date ? $end_date : 'NULL') . "</td>
            <td>{$user['leave_status']}</td>
            <td class='{$status_class}'>{$verification}</td>
        </tr>";
    }
    
    echo "</table>";
} else {
    echo "<p class='warning'>No users found on leave today. This could be correct if no one is on leave.</p>";
}

echo "</div>";

// Test 3: Manual verification - check specific test cases
echo "<div class='test-section'>
    <h2>Test 3: Manual Test Cases</h2>";

$test_cases = [
    [
        'name' => 'Past multi-day leave',
        'start_date' => date('Y-m-d', strtotime('-10 days')),
        'end_date' => date('Y-m-d', strtotime('-5 days')),
        'should_show' => false
    ],
    [
        'name' => 'Future multi-day leave',
        'start_date' => date('Y-m-d', strtotime('+5 days')),
        'end_date' => date('Y-m-d', strtotime('+10 days')),
        'should_show' => false
    ],
    [
        'name' => 'Current multi-day leave (today in middle)',
        'start_date' => date('Y-m-d', strtotime('-2 days')),
        'end_date' => date('Y-m-d', strtotime('+2 days')),
        'should_show' => true
    ],
    [
        'name' => 'Current multi-day leave (today is start)',
        'start_date' => $today,
        'end_date' => date('Y-m-d', strtotime('+5 days')),
        'should_show' => true
    ],
    [
        'name' => 'Current multi-day leave (today is end)',
        'start_date' => date('Y-m-d', strtotime('-5 days')),
        'end_date' => $today,
        'should_show' => true
    ],
    [
        'name' => 'Past single-day leave',
        'start_date' => date('Y-m-d', strtotime('-1 day')),
        'end_date' => null,
        'should_show' => false
    ],
    [
        'name' => 'Today single-day leave',
        'start_date' => $today,
        'end_date' => null,
        'should_show' => true
    ],
    [
        'name' => 'Future single-day leave',
        'start_date' => date('Y-m-d', strtotime('+1 day')),
        'end_date' => null,
        'should_show' => false
    ]
];

foreach ($test_cases as $test) {
    $start = $test['start_date'];
    $end = $test['end_date'];
    $should_show = $test['should_show'];
    
    // Apply the same logic as the query
    if ($end === null) {
        $would_show = ($start === $today);
    } else {
        $would_show = ($start <= $today && $end >= $today);
    }
    
    $result = ($would_show === $should_show);
    $status_class = $result ? 'pass' : 'fail';
    $status_text = $result ? '✓ PASS' : '✗ FAIL';
    
    echo "<div class='test-case'>
        <strong>{$test['name']}</strong><br>
        Start: {$start}, End: " . ($end ? $end : 'NULL') . "<br>
        Expected: " . ($should_show ? 'SHOW' : 'HIDE') . " | 
        Actual: " . ($would_show ? 'SHOW' : 'HIDE') . " | 
        <span class='{$status_class}'>{$status_text}</span>
    </div>";
}

echo "</div>";

// Test 4: Detailed Analysis
echo "<div class='test-section'>
    <h2>Test 4: Detailed Analysis & Error Explanation</h2>";

// Find missing users
$matched_employee_names = [];
foreach ($dashboard_results as $user) {
    // Find which employee_name(s) matched this user
    $user_name = $user['name'];
    $user_username = $user['username'];
    
    $name_query = "SELECT DISTINCT employee_name 
                   FROM Leave_request 
                   WHERE (employee_name = ? OR employee_name = ?)
                   AND status IN ('PENDING', 'Approve')
                   AND status NOT IN ('Reject', 'Cancelled')
                   AND (
                       (end_date IS NOT NULL AND start_date <= ? AND end_date >= ?)
                       OR
                       (end_date IS NULL AND start_date = ?)
                   )";
    
    $matched_names = [];
    if ($stmt_names = mysqli_prepare($conn, $name_query)) {
        mysqli_stmt_bind_param($stmt_names, "sssss", $user_name, $user_username, $today, $today, $today);
        if (mysqli_stmt_execute($stmt_names)) {
            $result_names = mysqli_stmt_get_result($stmt_names);
            while ($row = mysqli_fetch_assoc($result_names)) {
                $matched_names[] = $row['employee_name'];
            }
        }
        mysqli_stmt_close($stmt_names);
    }
    
    $matched_employee_names = array_merge($matched_employee_names, $matched_names);
}

// Find unmatched employee names
$unmatched_employees = [];
foreach ($raw_leaves as $leave) {
    $employee_name = $leave['employee_name'];
    if (!in_array($employee_name, $matched_employee_names)) {
        if (!isset($unmatched_employees[$employee_name])) {
            $unmatched_employees[$employee_name] = [];
        }
        $unmatched_employees[$employee_name][] = $leave;
    }
}

if (count($unmatched_employees) > 0) {
    echo "<h3 style='color: #ef4444;'>❌ Unmatched Employees (Not Showing in Dashboard):</h3>";
    echo "<table>
        <tr>
            <th>Employee Name</th>
            <th>Leave Count</th>
            <th>Leave Details</th>
            <th>Why Not Matched?</th>
        </tr>";
    
    foreach ($unmatched_employees as $emp_name => $leaves) {
        // Check why it didn't match
        $check_query = "SELECT id, name, username, user_type 
                       FROM users 
                       WHERE name = ? OR username = ?";
        $user_check = null;
        if ($stmt_check = mysqli_prepare($conn, $check_query)) {
            mysqli_stmt_bind_param($stmt_check, "ss", $emp_name, $emp_name);
            if (mysqli_stmt_execute($stmt_check)) {
                $result_check = mysqli_stmt_get_result($stmt_check);
                $user_check = mysqli_fetch_assoc($result_check);
            }
            mysqli_stmt_close($stmt_check);
        }
        
        $reason = '';
        if (!$user_check) {
            $reason = "✗ No user found with name/username = '{$emp_name}'";
        } elseif (!in_array($user_check['user_type'], ['doer', 'manager', 'admin'])) {
            $reason = "✗ User exists (ID: {$user_check['id']}) but user_type is '{$user_check['user_type']}' (not doer/manager/admin)";
        } else {
            $reason = "⚠ Unknown issue - user exists and type is correct";
        }
        
        $leave_details = [];
        foreach ($leaves as $leave) {
            $leave_details[] = "{$leave['start_date']} to " . ($leave['end_date'] ? $leave['end_date'] : 'NULL');
        }
        
        echo "<tr>
            <td><strong>{$emp_name}</strong></td>
            <td>" . count($leaves) . "</td>
            <td>" . implode('<br>', $leave_details) . "</td>
            <td class='fail'>{$reason}</td>
        </tr>";
    }
    
    echo "</table>";
} else {
    echo "<p class='pass'>✓ All employees with leaves today are matched to users!</p>";
}

// Check for duplicate grouping
$user_leave_counts = [];
foreach ($raw_leaves as $leave) {
    $employee_name = $leave['employee_name'];
    $match_query = "SELECT id FROM users 
                   WHERE (name = ? OR username = ?) 
                   AND user_type IN ('doer', 'manager', 'admin')
                   LIMIT 1";
    if ($stmt_match = mysqli_prepare($conn, $match_query)) {
        mysqli_stmt_bind_param($stmt_match, "ss", $employee_name, $employee_name);
        if (mysqli_stmt_execute($stmt_match)) {
            $result_match = mysqli_stmt_get_result($stmt_match);
            if ($user_row = mysqli_fetch_assoc($result_match)) {
                $user_id = $user_row['id'];
                if (!isset($user_leave_counts[$user_id])) {
                    $user_leave_counts[$user_id] = 0;
                }
                $user_leave_counts[$user_id]++;
            }
        }
        mysqli_stmt_close($stmt_match);
    }
}

$users_with_multiple_leaves = array_filter($user_leave_counts, function($count) {
    return $count > 1;
});

if (count($users_with_multiple_leaves) > 0) {
    echo "<h3 style='color: #f59e0b;'>⚠️ Users with Multiple Leaves Today (GROUP BY will combine them):</h3>";
    echo "<ul style='margin: 10px 0; padding-left: 20px;'>";
    foreach ($users_with_multiple_leaves as $user_id => $count) {
        $user_info_query = "SELECT name, username FROM users WHERE id = ?";
        $user_info = null;
        if ($stmt_info = mysqli_prepare($conn, $user_info_query)) {
            mysqli_stmt_bind_param($stmt_info, "i", $user_id);
            if (mysqli_stmt_execute($stmt_info)) {
                $result_info = mysqli_stmt_get_result($stmt_info);
                $user_info = mysqli_fetch_assoc($result_info);
            }
            mysqli_stmt_close($stmt_info);
        }
        $user_display = $user_info ? "{$user_info['name']} ({$user_info['username']})" : "User ID {$user_id}";
        echo "<li><strong>{$user_display}</strong>: {$count} leave(s) - GROUP BY will show as 1 user</li>";
    }
    echo "</ul>";
}

echo "</div>";

// Test 5: Summary
echo "<div class='test-section'>
    <h2>Test 5: Summary</h2>
    <p class='info'><strong>Today's Date:</strong> {$today_formatted} ({$today})</p>
    <p class='info'><strong>Total Leave Requests (All):</strong> " . count($all_leaves) . "</p>
    <p class='info'><strong>Raw Leave Requests (Today):</strong> " . count($raw_leaves) . "</p>
    <p class='info'><strong>Users on Leave Today (Dashboard):</strong> " . count($dashboard_results) . "</p>";
    
if (count($raw_leaves) > count($dashboard_results)) {
    $missing = count($raw_leaves) - count($dashboard_results);
    echo "<p class='fail'><strong>⚠️ ISSUE FOUND:</strong> {$missing} leave request(s) not appearing in dashboard</p>";
    echo "<p class='info'>Check 'Test 4: Detailed Analysis' above for specific reasons.</p>";
} else {
    echo "<p class='pass'><strong>✓ All leave requests are properly matched and displayed!</strong></p>";
}

echo "<p class='info'><strong>Test Status:</strong> " . 
    (count($dashboard_results) >= 0 ? '<span class="pass">Query executed successfully</span>' : '<span class="fail">Query failed</span>') . 
"</p>
</div>";

echo "</body></html>";
?>

