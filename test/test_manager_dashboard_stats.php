<?php
/**
 * Manager Dashboard Stats Test
 * Tests that manager dashboard stats are working correctly with date range toggles
 * 
 * Usage: Run this file in a browser or via CLI after logging in as a manager
 * Make sure you're logged in as a manager user before running this test
 */

session_start();
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if user is logged in and is a manager
if (!isLoggedIn() || (!isManager() && !isAdmin())) {
    die("❌ ERROR: You must be logged in as a manager to run this test. Please log in first.");
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$current_username = $_SESSION['username'] ?? '';

if (empty($current_user_id)) {
    die("❌ ERROR: Could not determine user ID from session.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard Stats Test</title>
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
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        .user-info {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        .test-section {
            margin-bottom: 30px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            background: #fafafa;
        }
        .test-section h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.5rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .test-case {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .test-case h3 {
            color: #555;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        .test-result {
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .test-result.pass {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .test-result.fail {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .test-result.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .stat-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #667eea;
        }
        .stat-label {
            font-weight: bold;
            color: #555;
            font-size: 0.85rem;
        }
        .stat-value {
            font-size: 1.2rem;
            color: #333;
            margin-top: 5px;
        }
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            margin: 5px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .summary h2 {
            color: white;
            border-bottom: 2px solid rgba(255,255,255,0.3);
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .summary-stat {
            text-align: center;
        }
        .summary-stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .summary-stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Manager Dashboard Stats Test</h1>
        <div class="user-info">
            <strong>User:</strong> <?php echo htmlspecialchars($current_username); ?> (ID: <?php echo $current_user_id; ?>)<br>
            <strong>User Type:</strong> <?php echo isManager() ? 'Manager' : (isAdmin() ? 'Admin' : 'Unknown'); ?><br>
            <strong>Test Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
        </div>

        <div class="test-section">
            <h2>📊 Date Range Toggle Tests</h2>
            <p>Testing all date range options: 7D, 2W, 4W, All, and Custom Date Range</p>
            
            <div id="test-results"></div>
            
            <button class="btn" onclick="runAllTests()">🚀 Run All Tests</button>
            <button class="btn" onclick="testDateRange('7d')">Test 7D</button>
            <button class="btn" onclick="testDateRange('14d')">Test 2W</button>
            <button class="btn" onclick="testDateRange('28d')">Test 4W</button>
            <button class="btn" onclick="testDateRange('all')">Test All</button>
            <button class="btn" onclick="testCustomDateRange()">Test Custom Range</button>
        </div>

        <div class="summary" id="summary" style="display: none;">
            <h2>📈 Test Summary</h2>
            <div class="summary-stats" id="summary-stats"></div>
        </div>
    </div>

    <script>
        let testResults = {
            total: 0,
            passed: 0,
            failed: 0
        };

        function updateSummary() {
            const summary = document.getElementById('summary');
            const summaryStats = document.getElementById('summary-stats');
            
            summary.style.display = 'block';
            summaryStats.innerHTML = `
                <div class="summary-stat">
                    <div class="summary-stat-value">${testResults.total}</div>
                    <div class="summary-stat-label">Total Tests</div>
                </div>
                <div class="summary-stat">
                    <div class="summary-stat-value" style="color: #4caf50;">${testResults.passed}</div>
                    <div class="summary-stat-label">Passed</div>
                </div>
                <div class="summary-stat">
                    <div class="summary-stat-value" style="color: #f44336;">${testResults.failed}</div>
                    <div class="summary-stat-label">Failed</div>
                </div>
                <div class="summary-stat">
                    <div class="summary-stat-value" style="color: #2196f3;">${Math.round((testResults.passed / testResults.total) * 100)}%</div>
                    <div class="summary-stat-label">Success Rate</div>
                </div>
            `;
        }

        function addTestResult(title, passed, details, data = null) {
            testResults.total++;
            if (passed) {
                testResults.passed++;
            } else {
                testResults.failed++;
            }

            const resultsDiv = document.getElementById('test-results');
            const testCase = document.createElement('div');
            testCase.className = 'test-case';
            
            let html = `<h3>${passed ? '✅' : '❌'} ${title}</h3>`;
            html += `<div class="test-result ${passed ? 'pass' : 'fail'}">${details}</div>`;
            
            if (data) {
                html += `<div class="stat-grid">`;
                if (data.stats) {
                    Object.keys(data.stats).forEach(key => {
                        html += `
                            <div class="stat-item">
                                <div class="stat-label">${key}</div>
                                <div class="stat-value">${data.stats[key] ?? 'N/A'}</div>
                            </div>
                        `;
                    });
                }
                if (data.personal_stats) {
                    Object.keys(data.personal_stats).forEach(key => {
                        html += `
                            <div class="stat-item">
                                <div class="stat-label">${key}</div>
                                <div class="stat-value">${data.personal_stats[key] ?? 'N/A'}</div>
                            </div>
                        `;
                    });
                }
                html += `</div>`;
            }
            
            testCase.innerHTML = html;
            resultsDiv.appendChild(testCase);
            updateSummary();
        }

        async function testDateRange(range) {
            const resultsDiv = document.getElementById('test-results');
            const loading = document.createElement('div');
            loading.className = 'test-result info';
            loading.innerHTML = `<div class="loading"></div> Testing ${range.toUpperCase()} date range...`;
            resultsDiv.appendChild(loading);

            try {
                const url = `../ajax/manager_dashboard_data.php?date_range=${range}`;
                const response = await fetch(url);
                const data = await response.json();

                loading.remove();

                if (!data.success) {
                    addTestResult(
                        `Date Range: ${range.toUpperCase()}`,
                        false,
                        `❌ API returned error: ${data.error || 'Unknown error'}`,
                        null
                    );
                    return;
                }

                // Validate response structure
                const hasData = data.data && typeof data.data === 'object';
                const hasStats = hasData && (data.data.stats || data.data.personal_stats);
                
                if (!hasData) {
                    addTestResult(
                        `Date Range: ${range.toUpperCase()}`,
                        false,
                        `❌ Response missing data object`,
                        null
                    );
                    return;
                }

                // Check required stats fields
                const personalStats = data.data.personal_stats || {};
                const requiredFields = [
                    'completed_on_time',
                    'current_pending',
                    'current_delayed',
                    'total_tasks',
                    'total_tasks_all',
                    'shifted_tasks',
                    'wnd',
                    'wnd_on_time'
                ];

                const missingFields = requiredFields.filter(field => 
                    personalStats[field] === undefined && personalStats[field] !== 0
                );

                if (missingFields.length > 0) {
                    addTestResult(
                        `Date Range: ${range.toUpperCase()}`,
                        false,
                        `❌ Missing required fields: ${missingFields.join(', ')}`,
                        { personal_stats: personalStats }
                    );
                    return;
                }

                // Validate stat values are numbers
                const invalidValues = [];
                requiredFields.forEach(field => {
                    const value = personalStats[field];
                    if (value !== null && value !== undefined && (isNaN(value) || !isFinite(value))) {
                        invalidValues.push(`${field}=${value}`);
                    }
                });

                if (invalidValues.length > 0) {
                    addTestResult(
                        `Date Range: ${range.toUpperCase()}`,
                        false,
                        `❌ Invalid numeric values: ${invalidValues.join(', ')}`,
                        { personal_stats: personalStats }
                    );
                    return;
                }

                // Check RQC score
                const rqcScore = data.data.rqc_score || data.data.completion_rate;
                const hasValidRqc = rqcScore === null || rqcScore === undefined || 
                                   (!isNaN(rqcScore) && isFinite(rqcScore));

                if (!hasValidRqc) {
                    addTestResult(
                        `Date Range: ${range.toUpperCase()}`,
                        false,
                        `❌ Invalid RQC score: ${rqcScore}`,
                        { personal_stats: personalStats, rqc_score: rqcScore }
                    );
                    return;
                }

                // All checks passed
                addTestResult(
                    `Date Range: ${range.toUpperCase()}`,
                    true,
                    `✅ All stats validated successfully. RQC Score: ${rqcScore !== null && rqcScore !== undefined ? rqcScore + '%' : 'N/A'}`,
                    { personal_stats: personalStats, rqc_score: rqcScore }
                );

            } catch (error) {
                loading.remove();
                addTestResult(
                    `Date Range: ${range.toUpperCase()}`,
                    false,
                    `❌ Error: ${error.message}`,
                    null
                );
            }
        }

        async function testCustomDateRange() {
            // Test with a custom date range (last 10 days)
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 9);

            const dateFrom = startDate.toISOString().split('T')[0];
            const dateTo = endDate.toISOString().split('T')[0];

            const resultsDiv = document.getElementById('test-results');
            const loading = document.createElement('div');
            loading.className = 'test-result info';
            loading.innerHTML = `<div class="loading"></div> Testing Custom Date Range (${dateFrom} to ${dateTo})...`;
            resultsDiv.appendChild(loading);

            try {
                const url = `../ajax/manager_dashboard_data.php?date_from=${dateFrom}&date_to=${dateTo}`;
                const response = await fetch(url);
                const data = await response.json();

                loading.remove();

                if (!data.success) {
                    addTestResult(
                        `Custom Date Range: ${dateFrom} to ${dateTo}`,
                        false,
                        `❌ API returned error: ${data.error || 'Unknown error'}`,
                        null
                    );
                    return;
                }

                // Validate response (same checks as above)
                const hasData = data.data && typeof data.data === 'object';
                const personalStats = data.data.personal_stats || {};
                const requiredFields = [
                    'completed_on_time',
                    'current_pending',
                    'current_delayed',
                    'total_tasks',
                    'total_tasks_all',
                    'shifted_tasks',
                    'wnd',
                    'wnd_on_time'
                ];

                const missingFields = requiredFields.filter(field => 
                    personalStats[field] === undefined && personalStats[field] !== 0
                );

                if (missingFields.length > 0 || !hasData) {
                    addTestResult(
                        `Custom Date Range: ${dateFrom} to ${dateTo}`,
                        false,
                        `❌ Missing required fields or invalid response structure`,
                        { personal_stats: personalStats }
                    );
                    return;
                }

                addTestResult(
                    `Custom Date Range: ${dateFrom} to ${dateTo}`,
                    true,
                    `✅ Custom date range test passed. All stats validated.`,
                    { personal_stats: personalStats }
                );

            } catch (error) {
                loading.remove();
                addTestResult(
                    `Custom Date Range: ${dateFrom} to ${dateTo}`,
                    false,
                    `❌ Error: ${error.message}`,
                    null
                );
            }
        }

        async function runAllTests() {
            // Clear previous results
            document.getElementById('test-results').innerHTML = '';
            testResults = { total: 0, passed: 0, failed: 0 };
            document.getElementById('summary').style.display = 'none';

            // Run all tests sequentially
            await testDateRange('7d');
            await new Promise(resolve => setTimeout(resolve, 500));
            
            await testDateRange('14d');
            await new Promise(resolve => setTimeout(resolve, 500));
            
            await testDateRange('28d');
            await new Promise(resolve => setTimeout(resolve, 500));
            
            await testDateRange('all');
            await new Promise(resolve => setTimeout(resolve, 500));
            
            await testCustomDateRange();
        }

        // Auto-run on page load
        window.addEventListener('DOMContentLoaded', () => {
            console.log('Manager Dashboard Stats Test Page Loaded');
        });
    </script>
</body>
</html>

