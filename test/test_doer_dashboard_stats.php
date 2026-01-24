<?php
/**
 * Doer Dashboard Stats Test
 * Tests that doer dashboard stats are working correctly with date range toggles
 * 
 * Usage: Run this file in a browser or via CLI after logging in as a doer
 * Make sure you're logged in as a doer user before running this test
 */

session_start();
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if user is logged in
if (!isLoggedIn()) {
    die("❌ ERROR: You must be logged in to run this test. Please log in first.");
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
    <title>Doer Dashboard Stats Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            color: #f5576c;
            margin-bottom: 15px;
            font-size: 1.5rem;
            border-bottom: 2px solid #f5576c;
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
            border-left: 4px solid #f5576c;
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
            background: #f5576c;
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
            background: #e0485c;
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
            border-top: 3px solid #f5576c;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .summary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
        .rqc-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Doer Dashboard Stats Test</h1>
        <div class="user-info">
            <strong>User:</strong> <?php echo htmlspecialchars($current_username); ?> (ID: <?php echo $current_user_id; ?>)<br>
            <strong>User Type:</strong> <?php 
                if (isDoer()) echo 'Doer';
                elseif (isManager()) echo 'Manager';
                elseif (isAdmin()) echo 'Admin';
                else echo 'Unknown';
            ?><br>
            <strong>Test Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
        </div>

        <div class="test-section">
            <h2>📊 Date Range Toggle Tests</h2>
            <p>Testing all date range options: 7D, 2W, 4W, All, and Custom Date Range</p>
            <p><strong>Note:</strong> This test validates that all stat cards (Total Tasks, Tasks Completed, Pending, Shifted, Delayed, RQC, WND, WND On-Time) are working correctly.</p>
            
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
                        const value = data.stats[key];
                        const displayValue = (value === null || value === undefined) ? 'N/A' : 
                                           (isNaN(value) ? 'NaN ⚠️' : value);
                        html += `
                            <div class="stat-item">
                                <div class="stat-label">${key}</div>
                                <div class="stat-value">${displayValue}</div>
                            </div>
                        `;
                    });
                }
                if (data.rqc_score !== undefined) {
                    const rqcValue = data.rqc_score;
                    const rqcDisplay = (rqcValue === null || rqcValue === undefined) ? 'N/A' : 
                                     (isNaN(rqcValue) ? 'NaN ⚠️' : rqcValue + '%');
                    html += `
                        <div class="stat-item">
                            <div class="stat-label">RQC Score</div>
                            <div class="stat-value">${rqcDisplay}</div>
                        </div>
                    `;
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
                const url = `../ajax/doer_dashboard_data.php?date_range=${range}`;
                const response = await fetch(url);
                
                // Check if response is OK
                if (!response.ok) {
                    // Try to get error details from response
                    const errorText = await response.text();
                    let errorDetails = `HTTP error! status: ${response.status}`;
                    try {
                        const errorJson = JSON.parse(errorText);
                        errorDetails += `\nError: ${errorJson.error || 'Unknown error'}`;
                        if (errorJson.file) errorDetails += `\nFile: ${errorJson.file}`;
                        if (errorJson.line) errorDetails += `\nLine: ${errorJson.line}`;
                        if (errorJson.type) errorDetails += `\nType: ${errorJson.type}`;
                    } catch (e) {
                        errorDetails += `\nResponse: ${errorText.substring(0, 500)}`;
                    }
                    throw new Error(errorDetails);
                }
                
                // Get response text first to check for errors
                const responseText = await response.text();
                
                // Check if response is empty
                if (!responseText || responseText.trim() === '') {
                    throw new Error('Empty response from server');
                }
                
                // Try to parse JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error(`Invalid JSON response: ${parseError.message}. Response: ${responseText.substring(0, 200)}`);
                }

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
                const hasStats = hasData && data.data.stats;
                
                if (!hasData || !hasStats) {
                    addTestResult(
                        `Date Range: ${range.toUpperCase()}`,
                        false,
                        `❌ Response missing data or stats object`,
                        null
                    );
                    return;
                }

                // Check required stats fields for doer dashboard
                const stats = data.data.stats || {};
                const requiredFields = [
                    'tasks_completed',      // Tasks Completed
                    'task_pending',         // Task Pending
                    'delayed_task',         // Delayed Task
                    'total_tasks_all',      // Total Tasks
                    'shifted_tasks',        // Shifted Tasks
                    'wnd_percent',          // WND
                    'wnd_on_time_percent'   // WND On-Time
                ];

                const missingFields = requiredFields.filter(field => 
                    stats[field] === undefined && stats[field] !== 0
                );

                if (missingFields.length > 0) {
                    addTestResult(
                        `Date Range: ${range.toUpperCase()}`,
                        false,
                        `❌ Missing required fields: ${missingFields.join(', ')}`,
                        { stats: stats }
                    );
                    return;
                }

                // Validate stat values are numbers (not NaN)
                const invalidValues = [];
                requiredFields.forEach(field => {
                    const value = stats[field];
                    if (value !== null && value !== undefined && (isNaN(value) || !isFinite(value))) {
                        invalidValues.push(`${field}=${value}`);
                    }
                });

                if (invalidValues.length > 0) {
                    addTestResult(
                        `Date Range: ${range.toUpperCase()}`,
                        false,
                        `❌ Invalid numeric values (NaN detected): ${invalidValues.join(', ')}`,
                        { stats: stats }
                    );
                    return;
                }

                // Check RQC score - must be valid number, null, or undefined (not NaN)
                const rqcScore = data.data.rqc_score || data.data.completion_rate;
                let rqcValid = true;
                let rqcWarning = '';

                if (rqcScore !== null && rqcScore !== undefined) {
                    if (isNaN(rqcScore) || !isFinite(rqcScore)) {
                        rqcValid = false;
                        rqcWarning = ` ❌ RQC Score is NaN (should be number or null)`;
                    } else if (rqcScore < 0 || rqcScore > 100) {
                        rqcWarning = ` ⚠️ RQC Score out of range: ${rqcScore}% (expected 0-100)`;
                    }
                }

                if (!rqcValid) {
                    addTestResult(
                        `Date Range: ${range.toUpperCase()}`,
                        false,
                        `❌ Invalid RQC score: ${rqcScore}${rqcWarning}`,
                        { stats: stats, rqc_score: rqcScore }
                    );
                    return;
                }

                // Validate percentage fields have reasonable values
                const percentageFields = ['wnd_percent', 'wnd_on_time_percent'];
                const invalidPercentages = [];
                percentageFields.forEach(field => {
                    const value = stats[field];
                    if (value !== null && value !== undefined && !isNaN(value)) {
                        // WND can be negative, but should be reasonable
                        if (value < -100 || value > 100) {
                            invalidPercentages.push(`${field}=${value}%`);
                        }
                    }
                });

                if (invalidPercentages.length > 0) {
                    addTestResult(
                        `Date Range: ${range.toUpperCase()}`,
                        false,
                        `❌ Percentage values out of reasonable range: ${invalidPercentages.join(', ')}`,
                        { stats: stats, rqc_score: rqcScore }
                    );
                    return;
                }

                // All checks passed
                const rqcDisplay = rqcScore !== null && rqcScore !== undefined ? 
                                  `${rqcScore}%${rqcWarning}` : 'N/A';
                addTestResult(
                    `Date Range: ${range.toUpperCase()}`,
                    true,
                    `✅ All stats validated successfully. RQC Score: ${rqcDisplay}`,
                    { stats: stats, rqc_score: rqcScore }
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
                const url = `../ajax/doer_dashboard_data.php?date_from=${dateFrom}&date_to=${dateTo}`;
                const response = await fetch(url);
                
                // Check if response is OK
                if (!response.ok) {
                    // Try to get error details from response
                    const errorText = await response.text();
                    let errorDetails = `HTTP error! status: ${response.status}`;
                    try {
                        const errorJson = JSON.parse(errorText);
                        errorDetails += `\nError: ${errorJson.error || 'Unknown error'}`;
                        if (errorJson.file) errorDetails += `\nFile: ${errorJson.file}`;
                        if (errorJson.line) errorDetails += `\nLine: ${errorJson.line}`;
                        if (errorJson.type) errorDetails += `\nType: ${errorJson.type}`;
                    } catch (e) {
                        errorDetails += `\nResponse: ${errorText.substring(0, 500)}`;
                    }
                    throw new Error(errorDetails);
                }
                
                // Get response text first to check for errors
                const responseText = await response.text();
                
                // Check if response is empty
                if (!responseText || responseText.trim() === '') {
                    throw new Error('Empty response from server');
                }
                
                // Try to parse JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error(`Invalid JSON response: ${parseError.message}. Response: ${responseText.substring(0, 200)}`);
                }

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
                const stats = data.data.stats || {};
                const requiredFields = [
                    'tasks_completed',
                    'task_pending',
                    'delayed_task',
                    'total_tasks_all',
                    'shifted_tasks',
                    'wnd_percent',
                    'wnd_on_time_percent'
                ];

                const missingFields = requiredFields.filter(field => 
                    stats[field] === undefined && stats[field] !== 0
                );

                // Check for NaN values
                const invalidValues = [];
                requiredFields.forEach(field => {
                    const value = stats[field];
                    if (value !== null && value !== undefined && (isNaN(value) || !isFinite(value))) {
                        invalidValues.push(`${field}=${value}`);
                    }
                });

                // Check RQC score
                const rqcScore = data.data.rqc_score || data.data.completion_rate;
                const rqcValid = rqcScore === null || rqcScore === undefined || 
                                (!isNaN(rqcScore) && isFinite(rqcScore));

                if (missingFields.length > 0 || !hasData || invalidValues.length > 0 || !rqcValid) {
                    let errorMsg = [];
                    if (!hasData) errorMsg.push('Missing data object');
                    if (missingFields.length > 0) errorMsg.push(`Missing fields: ${missingFields.join(', ')}`);
                    if (invalidValues.length > 0) errorMsg.push(`NaN values: ${invalidValues.join(', ')}`);
                    if (!rqcValid) errorMsg.push(`Invalid RQC: ${rqcScore}`);

                    addTestResult(
                        `Custom Date Range: ${dateFrom} to ${dateTo}`,
                        false,
                        `❌ ${errorMsg.join('; ')}`,
                        { stats: stats, rqc_score: rqcScore }
                    );
                    return;
                }

                addTestResult(
                    `Custom Date Range: ${dateFrom} to ${dateTo}`,
                    true,
                    `✅ Custom date range test passed. All stats validated.`,
                    { stats: stats, rqc_score: rqcScore }
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
            console.log('Doer Dashboard Stats Test Page Loaded');
        });
    </script>
</body>
</html>

