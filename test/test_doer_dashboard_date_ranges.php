<?php
/**
 * Test file for Doer Dashboard Date Range Functionality
 * 
 * This file tests the date range toggle functionality (7D/14D/28D/Custom)
 * with dummy data to verify that stats update correctly based on selected date range.
 * 
 * Usage:
 * 1. Access this file via browser: http://localhost/app-v2/test/test_doer_dashboard_date_ranges.php
 * 2. Test different date ranges and verify stats change accordingly
 */

session_start();
require_once "../includes/config.php";
require_once "../includes/dashboard_components.php";

// Mock session data for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['id'] = 1;
    $_SESSION['username'] = 'test_doer';
    $_SESSION['name'] = 'Test Doer';
}

$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];

// Get date range parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : null;

// Convert date_range preset to actual dates
if ($date_range && (!$date_from || !$date_to)) {
    $range_map = [
        '7d' => 7,
        '14d' => 14,
        '28d' => 28
    ];
    if (isset($range_map[$date_range])) {
        $end = new DateTime();
        $start = clone $end;
        $start->modify('-' . ($range_map[$date_range] - 1) . ' days');
        $date_from = $start->format('Y-m-d');
        $date_to = $end->format('Y-m-d');
    }
}

// Generate dummy data based on date range
function generateDummyStats($date_from, $date_to, $range_type) {
    // Base stats that vary by date range
    $base_multiplier = 1;
    switch($range_type) {
        case '7d':
            $base_multiplier = 1;
            break;
        case '14d':
            $base_multiplier = 2;
            break;
        case '28d':
            $base_multiplier = 4;
            break;
        case 'custom':
            // Calculate days difference
            $start = new DateTime($date_from);
            $end = new DateTime($date_to);
            $days = $start->diff($end)->days + 1;
            $base_multiplier = max(1, round($days / 7));
            break;
    }
    
    // Generate realistic dummy stats
    $completed = 15 * $base_multiplier;
    $pending = 5 * $base_multiplier;
    $delayed = 3 * $base_multiplier;
    $total = $completed + $pending + $delayed;
    
    // Calculate WND: - (Pending + Delayed) / Total * 100
    $wnd = $total > 0 ? round(-(($pending + $delayed) / $total) * 100, 2) : 0;
    
    // Calculate WND On-Time: - (Delayed Completed / Completed) * 100
    $delayed_completed = round($completed * 0.2); // 20% of completed tasks were late
    $wnd_on_time = $completed > 0 ? round(-($delayed_completed / $completed) * 100, 2) : 0;
    
    return [
        'tasks_completed' => $completed,
        'task_pending' => $pending,
        'delayed_task' => $delayed,
        'total' => $total,
        'wnd_percent' => $wnd,
        'wnd_on_time_percent' => $wnd_on_time
    ];
}

// Generate dummy RQC score (varies slightly by range for testing)
// Allow testing N/A behavior with ?test_na=1 parameter
$test_na = isset($_GET['test_na']) && $_GET['test_na'] == '1';
$rqc_score = $test_na ? 0 : 85.5; // Set to 0 to test N/A display
if (!$test_na) {
    if ($date_range === '14d') {
        $rqc_score = 87.2;
    } elseif ($date_range === '28d') {
        $rqc_score = 89.1;
    } elseif ($date_range === 'custom') {
        $rqc_score = 86.8;
    }
}

$dummy_stats = generateDummyStats($date_from, $date_to, $date_range ?: '7d');

// Generate dummy team members
$dummy_team = [
    ['id' => 1, 'name' => 'John Doe', 'username' => 'john', 'status' => 'available'],
    ['id' => 2, 'name' => 'Jane Smith', 'username' => 'jane', 'status' => 'on-leave', 
     'leave_type' => 'Sick Leave', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+2 days'))],
    ['id' => 3, 'name' => 'Bob Wilson', 'username' => 'bob', 'status' => 'available'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doer Dashboard Date Range Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .test-section {
            margin-bottom: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .test-section h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        .date-range-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .date-range-btn {
            padding: 10px 20px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .date-range-btn:hover {
            background: #667eea;
            color: white;
        }
        .date-range-btn.active {
            background: #667eea;
            color: white;
        }
        .custom-date-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .custom-date-inputs input {
            padding: 8px;
            border: 2px solid #667eea;
            border-radius: 6px;
        }
        .custom-date-inputs button {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-card .label {
            color: #999;
            font-size: 0.85rem;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box strong {
            color: #1976d2;
        }
        .response-data {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
        }
        .response-data pre {
            margin: 0;
        }
        .test-results {
            margin-top: 30px;
        }
        .test-results h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .test-item {
            padding: 10px;
            margin-bottom: 10px;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #4caf50;
        }
        .test-item.pass {
            border-left-color: #4caf50;
        }
        .test-item.fail {
            border-left-color: #f44336;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Doer Dashboard Date Range Test</h1>
        <p class="subtitle">Test the date range toggle functionality (7D/14D/28D/Custom) with dummy data</p>
        
        <div class="info-box">
            <strong>Current Test Parameters:</strong><br>
            Date Range: <strong><?php echo $date_range ?: '7d (default)'; ?></strong><br>
            From Date: <strong><?php echo $date_from ?: 'Not set'; ?></strong><br>
            To Date: <strong><?php echo $date_to ?: 'Not set'; ?></strong><br>
            User ID: <strong><?php echo $current_user_id; ?></strong><br>
            Username: <strong><?php echo $current_username; ?></strong><br>
            RQC Score: <strong><?php echo ($rqc_score && $rqc_score > 0) ? $rqc_score . '%' : 'N/A (testing N/A display)'; ?></strong>
            <?php if (!$test_na): ?>
                <br><a href="?test_na=1" style="color: #1976d2; text-decoration: underline;">Test N/A Display</a>
            <?php else: ?>
                <br><a href="?" style="color: #1976d2; text-decoration: underline;">Show Normal RQC</a>
            <?php endif; ?>
        </div>

        <div class="test-section">
            <h2>📅 Date Range Selector</h2>
            <div class="date-range-selector">
                <a href="?date_range=7d" class="date-range-btn <?php echo (!$date_range || $date_range === '7d') ? 'active' : ''; ?>">
                    7 Days
                </a>
                <a href="?date_range=14d" class="date-range-btn <?php echo $date_range === '14d' ? 'active' : ''; ?>">
                    14 Days
                </a>
                <a href="?date_range=28d" class="date-range-btn <?php echo $date_range === '28d' ? 'active' : ''; ?>">
                    28 Days
                </a>
                <div class="custom-date-inputs">
                    <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" required>
                        <span>to</span>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" required>
                        <button type="submit">Custom Range</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2>📊 Generated Stats (Based on Selected Range)</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Tasks Completed</h3>
                    <div class="value"><?php echo $dummy_stats['tasks_completed']; ?></div>
                    <div class="label">Total completed tasks</div>
                </div>
                <div class="stat-card">
                    <h3>Task Pending</h3>
                    <div class="value"><?php echo $dummy_stats['task_pending']; ?></div>
                    <div class="label">Tasks not yet completed</div>
                </div>
                <div class="stat-card">
                    <h3>Delayed Task</h3>
                    <div class="value"><?php echo $dummy_stats['delayed_task']; ?></div>
                    <div class="label">Tasks past deadline</div>
                </div>
                <div class="stat-card">
                    <h3>RQC Score</h3>
                    <div class="value"><?php echo ($rqc_score && $rqc_score > 0) ? $rqc_score . '%' : 'N/A'; ?></div>
                    <div class="label">Quality score</div>
                </div>
                <div class="stat-card">
                    <h3>Work Not Done</h3>
                    <div class="value"><?php echo $dummy_stats['wnd_percent']; ?>%</div>
                    <div class="label">Percentage of incomplete work</div>
                </div>
                <div class="stat-card">
                    <h3>WND On-Time</h3>
                    <div class="value"><?php echo $dummy_stats['wnd_on_time_percent']; ?>%</div>
                    <div class="label">Late completions percentage</div>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2>🔍 Expected Behavior Tests</h2>
            <div class="test-results">
                <h3>Validation Tests:</h3>
                
                <?php
                $tests = [];
                
                // Test 1: Stats should increase with longer date ranges
                if ($date_range === '7d') {
                    $tests[] = ['name' => '7D Range: Base multiplier = 1', 'pass' => true];
                } elseif ($date_range === '14d') {
                    $tests[] = ['name' => '14D Range: Base multiplier = 2 (should show ~2x stats)', 'pass' => true];
                } elseif ($date_range === '28d') {
                    $tests[] = ['name' => '28D Range: Base multiplier = 4 (should show ~4x stats)', 'pass' => true];
                } elseif ($date_from && $date_to) {
                    $start = new DateTime($date_from);
                    $end = new DateTime($date_to);
                    $days = $start->diff($end)->days + 1;
                    $expected_multiplier = max(1, round($days / 7));
                    $tests[] = ['name' => "Custom Range: {$days} days = multiplier {$expected_multiplier}", 'pass' => true];
                }
                
                // Test 2: WND calculation
                $expected_wnd = $dummy_stats['total'] > 0 
                    ? round(-(($dummy_stats['task_pending'] + $dummy_stats['delayed_task']) / $dummy_stats['total']) * 100, 2)
                    : 0;
                $tests[] = [
                    'name' => "WND Calculation: -((Pending + Delayed) / Total) * 100 = {$expected_wnd}%",
                    'pass' => abs($dummy_stats['wnd_percent'] - $expected_wnd) < 0.01
                ];
                
                // Test 3: WND On-Time calculation
                $delayed_completed = round($dummy_stats['tasks_completed'] * 0.2);
                $expected_wnd_on_time = $dummy_stats['tasks_completed'] > 0
                    ? round(-($delayed_completed / $dummy_stats['tasks_completed']) * 100, 2)
                    : 0;
                $tests[] = [
                    'name' => "WND On-Time: -(Delayed Completed / Completed) * 100 = {$expected_wnd_on_time}%",
                    'pass' => abs($dummy_stats['wnd_on_time_percent'] - $expected_wnd_on_time) < 0.01
                ];
                
                // Test 4: Date range validation
                if ($date_from && $date_to) {
                    $from_date = new DateTime($date_from);
                    $to_date = new DateTime($date_to);
                    $tests[] = [
                        'name' => "Date Range: From date ({$date_from}) <= To date ({$date_to})",
                        'pass' => $from_date <= $to_date
                    ];
                }
                
                foreach ($tests as $test) {
                    $class = $test['pass'] ? 'pass' : 'fail';
                    $icon = $test['pass'] ? '✅' : '❌';
                    echo "<div class='test-item {$class}'>{$icon} {$test['name']}</div>";
                }
                ?>
            </div>
        </div>

        <div class="test-section">
            <h2>📤 Simulated AJAX Response</h2>
            <p>This is what the AJAX endpoint would return:</p>
            <div class="response-data">
                <pre><?php
$response = [
    'success' => true,
    'data' => [
        'stats' => $dummy_stats,
        'completion_rate' => $rqc_score,
        'rqc_score' => $rqc_score,
        'trends' => [
            'completed_on_time' => 12,
            'current_pending' => 0,
            'current_delayed' => -25,
            'completion_rate' => 8
        ],
        'team' => $dummy_team,
        'last_updated' => date('M d, Y H:i')
    ]
];
echo json_encode($response, JSON_PRETTY_PRINT);
                ?></pre>
            </div>
        </div>

        <div class="test-section">
            <h2>📝 Test Instructions</h2>
            <ol style="line-height: 2; padding-left: 20px;">
                <li><strong>Test 7D Range:</strong> Click "7 Days" button - stats should show base values (multiplier = 1)</li>
                <li><strong>Test 14D Range:</strong> Click "14 Days" button - stats should approximately double (multiplier = 2)</li>
                <li><strong>Test 28D Range:</strong> Click "28 Days" button - stats should be ~4x base values (multiplier = 4)</li>
                <li><strong>Test Custom Range:</strong> Select custom dates and click "Custom Range" - stats should scale based on number of days</li>
                <li><strong>Verify Calculations:</strong> Check that WND and WND On-Time percentages are calculated correctly</li>
                <li><strong>Check RQC Score:</strong> RQC score should vary slightly by range to simulate real behavior</li>
            </ol>
        </div>

        <div class="test-section">
            <h2>🔗 Integration Test</h2>
            <p>To test with the actual dashboard:</p>
            <ol style="line-height: 2; padding-left: 20px;">
                <li>Open browser console (F12)</li>
                <li>Navigate to: <code>pages/doer_dashboard.php</code></li>
                <li>Click different date range buttons (7D/14D/28D)</li>
                <li>Check console for AJAX requests to <code>ajax/doer_dashboard_data.php</code></li>
                <li>Verify stats update correctly in the dashboard</li>
                <li>Test custom date range picker</li>
            </ol>
        </div>
    </div>
</body>
</html>

