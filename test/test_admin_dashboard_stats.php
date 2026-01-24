<?php
/**
 * Admin Dashboard Stats Test
 * Tests that admin dashboard stats are working correctly with date range toggles
 * Tests both System Overview and Personal Overview sections
 * 
 * Usage: Run this file in a browser or via CLI after logging in as an admin
 * Make sure you're logged in as an admin user before running this test
 */

session_start();
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    die("❌ ERROR: You must be logged in as an admin to run this test. Please log in first.");
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
    <title>Admin Dashboard Stats Test</title>
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
            max-width: 1600px;
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
        .section-divider {
            border-top: 3px solid #667eea;
            margin: 20px 0;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Admin Dashboard Stats Test</h1>
        <div class="user-info">
            <strong>User:</strong> <?php echo htmlspecialchars($current_username); ?> (ID: <?php echo $current_user_id; ?>)<br>
            <strong>User Type:</strong> Admin<br>
            <strong>Test Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
        </div>

        <div class="test-section">
            <h2>📊 System Overview - Date Range Toggle Tests</h2>
            <p>Testing System Overview stats with all date range options: 7D, 2W, 4W, All, and Custom Date Range</p>
            
            <div id="system-test-results"></div>
            
            <button class="btn" onclick="runAllSystemTests()">🚀 Run All System Tests</button>
            <button class="btn" onclick="testSystemDateRange('7d')">Test 7D</button>
            <button class="btn" onclick="testSystemDateRange('14d')">Test 2W</button>
            <button class="btn" onclick="testSystemDateRange('28d')">Test 4W</button>
            <button class="btn" onclick="testSystemDateRange('all')">Test All</button>
            <button class="btn" onclick="testSystemCustomDateRange()">Test Custom Range</button>
        </div>

        <div class="section-divider"></div>

        <div class="test-section">
            <h2>👤 Personal Overview - Date Range Toggle Tests</h2>
            <p>Testing Personal Overview stats with all date range options: 7D, 2W, 4W, All, and Custom Date Range</p>
            
            <div id="personal-test-results"></div>
            
            <button class="btn" onclick="runAllPersonalTests()">🚀 Run All Personal Tests</button>
            <button class="btn" onclick="testPersonalDateRange('7d')">Test 7D</button>
            <button class="btn" onclick="testPersonalDateRange('14d')">Test 2W</button>
            <button class="btn" onclick="testPersonalDateRange('28d')">Test 4W</button>
            <button class="btn" onclick="testPersonalDateRange('all')">Test All</button>
            <button class="btn" onclick="testPersonalCustomDateRange()">Test Custom Range</button>
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

        function addTestResult(section, title, passed, details, data = null) {
            testResults.total++;
            if (passed) {
                testResults.passed++;
            } else {
                testResults.failed++;
            }

            const resultsDiv = document.getElementById(section === 'system' ? 'system-test-results' : 'personal-test-results');
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

        async function testSystemDateRange(range) {
            const resultsDiv = document.getElementById('system-test-results');
            const loading = document.createElement('div');
            loading.className = 'test-result info';
            loading.innerHTML = `<div class="loading"></div> Testing System Overview ${range.toUpperCase()} date range...`;
            resultsDiv.appendChild(loading);

            try {
                const url = `../ajax/admin_dashboard_data.php?date_range=${range}`;
                const response = await fetch(url);
                
                // Check if response is OK
                if (!response.ok) {
                    const errorText = await response.text();
                    let errorDetails = `HTTP error! status: ${response.status}`;
                    try {
                        const errorJson = JSON.parse(errorText);
                        errorDetails += `\nError: ${errorJson.error || 'Unknown error'}`;
                        if (errorJson.file) errorDetails += `\nFile: ${errorJson.file}`;
                        if (errorJson.line) errorDetails += `\nLine: ${errorJson.line}`;
                    } catch (e) {
                        errorDetails += `\nResponse: ${errorText.substring(0, 500)}`;
                    }
                    throw new Error(errorDetails);
                }
                
                const responseText = await response.text();
                if (!responseText || responseText.trim() === '') {
                    throw new Error('Empty response from server');
                }
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error(`Invalid JSON response: ${parseError.message}. Response: ${responseText.substring(0, 200)}`);
                }

                loading.remove();

                if (!data.success) {
                    addTestResult('system', `System Overview: ${range.toUpperCase()}`, false, `❌ API returned error: ${data.error || 'Unknown error'}`, null);
                    return;
                }

                // Validate response structure
                const hasData = data.data && typeof data.data === 'object';
                const hasSystemStats = hasData && data.data.system_stats;
                
                if (!hasData || !hasSystemStats) {
                    addTestResult('system', `System Overview: ${range.toUpperCase()}`, false, `❌ Response missing data or system_stats object`, null);
                    return;
                }

                // Check required system stats fields
                const systemStats = data.data.system_stats || {};
                const requiredFields = [
                    'total_tasks',
                    'completed_tasks',
                    'pending_tasks',
                    'delayed_tasks',
                    'total_tasks_all',
                    'shifted_tasks'
                ];

                const missingFields = requiredFields.filter(field => 
                    systemStats[field] === undefined && systemStats[field] !== 0
                );

                if (missingFields.length > 0) {
                    addTestResult('system', `System Overview: ${range.toUpperCase()}`, false, `❌ Missing required fields: ${missingFields.join(', ')}`, { stats: systemStats });
                    return;
                }

                // Validate stat values are numbers
                const invalidValues = [];
                requiredFields.forEach(field => {
                    const value = systemStats[field];
                    if (value !== null && value !== undefined && (isNaN(value) || !isFinite(value))) {
                        invalidValues.push(`${field}=${value}`);
                    }
                });

                if (invalidValues.length > 0) {
                    addTestResult('system', `System Overview: ${range.toUpperCase()}`, false, `❌ Invalid numeric values: ${invalidValues.join(', ')}`, { stats: systemStats });
                    return;
                }

                // All checks passed
                addTestResult('system', `System Overview: ${range.toUpperCase()}`, true, `✅ All system stats validated successfully.`, { stats: systemStats });

            } catch (error) {
                loading.remove();
                addTestResult('system', `System Overview: ${range.toUpperCase()}`, false, `❌ Error: ${error.message}`, null);
            }
        }

        async function testPersonalDateRange(range) {
            const resultsDiv = document.getElementById('personal-test-results');
            const loading = document.createElement('div');
            loading.className = 'test-result info';
            loading.innerHTML = `<div class="loading"></div> Testing Personal Overview ${range.toUpperCase()} date range...`;
            resultsDiv.appendChild(loading);

            try {
                const url = `../ajax/admin_dashboard_data.php?personal_date_range=${range}`;
                const response = await fetch(url);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    let errorDetails = `HTTP error! status: ${response.status}`;
                    try {
                        const errorJson = JSON.parse(errorText);
                        errorDetails += `\nError: ${errorJson.error || 'Unknown error'}`;
                    } catch (e) {
                        errorDetails += `\nResponse: ${errorText.substring(0, 500)}`;
                    }
                    throw new Error(errorDetails);
                }
                
                const responseText = await response.text();
                if (!responseText || responseText.trim() === '') {
                    throw new Error('Empty response from server');
                }
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error(`Invalid JSON response: ${parseError.message}`);
                }

                loading.remove();

                if (!data.success) {
                    addTestResult('personal', `Personal Overview: ${range.toUpperCase()}`, false, `❌ API returned error: ${data.error || 'Unknown error'}`, null);
                    return;
                }

                // Validate response structure
                const hasData = data.data && typeof data.data === 'object';
                const hasPersonalStats = hasData && data.data.personal_stats;
                
                if (!hasData || !hasPersonalStats) {
                    addTestResult('personal', `Personal Overview: ${range.toUpperCase()}`, false, `❌ Response missing data or personal_stats object`, null);
                    return;
                }

                // Check required personal stats fields
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
                    addTestResult('personal', `Personal Overview: ${range.toUpperCase()}`, false, `❌ Missing required fields: ${missingFields.join(', ')}`, { personal_stats: personalStats });
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
                    addTestResult('personal', `Personal Overview: ${range.toUpperCase()}`, false, `❌ Invalid numeric values: ${invalidValues.join(', ')}`, { personal_stats: personalStats });
                    return;
                }

                // Check RQC score
                const rqcScore = data.data.personal_completion_rate || data.data.personal_rqc_score;
                const hasValidRqc = rqcScore === null || rqcScore === undefined || 
                                   (!isNaN(rqcScore) && isFinite(rqcScore));

                if (!hasValidRqc) {
                    addTestResult('personal', `Personal Overview: ${range.toUpperCase()}`, false, `❌ Invalid RQC score: ${rqcScore}`, { personal_stats: personalStats, rqc_score: rqcScore });
                    return;
                }

                // All checks passed
                addTestResult('personal', `Personal Overview: ${range.toUpperCase()}`, true, `✅ All personal stats validated successfully. RQC Score: ${rqcScore !== null && rqcScore !== undefined ? rqcScore + '%' : 'N/A'}`, { personal_stats: personalStats, rqc_score: rqcScore });

            } catch (error) {
                loading.remove();
                addTestResult('personal', `Personal Overview: ${range.toUpperCase()}`, false, `❌ Error: ${error.message}`, null);
            }
        }

        async function testSystemCustomDateRange() {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 9);

            const dateFrom = startDate.toISOString().split('T')[0];
            const dateTo = endDate.toISOString().split('T')[0];

            const resultsDiv = document.getElementById('system-test-results');
            const loading = document.createElement('div');
            loading.className = 'test-result info';
            loading.innerHTML = `<div class="loading"></div> Testing System Overview Custom Date Range (${dateFrom} to ${dateTo})...`;
            resultsDiv.appendChild(loading);

            try {
                const url = `../ajax/admin_dashboard_data.php?date_from=${dateFrom}&date_to=${dateTo}`;
                const response = await fetch(url);
                const data = await response.json();

                loading.remove();

                if (!data.success) {
                    addTestResult('system', `System Overview: Custom (${dateFrom} to ${dateTo})`, false, `❌ API returned error: ${data.error || 'Unknown error'}`, null);
                    return;
                }

                const systemStats = data.data.system_stats || {};
                const requiredFields = ['total_tasks', 'completed_tasks', 'pending_tasks', 'delayed_tasks', 'total_tasks_all', 'shifted_tasks'];
                const missingFields = requiredFields.filter(field => systemStats[field] === undefined && systemStats[field] !== 0);

                if (missingFields.length > 0 || !data.data || !data.data.system_stats) {
                    addTestResult('system', `System Overview: Custom (${dateFrom} to ${dateTo})`, false, `❌ Missing required fields or invalid response structure`, { stats: systemStats });
                    return;
                }

                addTestResult('system', `System Overview: Custom (${dateFrom} to ${dateTo})`, true, `✅ Custom date range test passed. All stats validated.`, { stats: systemStats });

            } catch (error) {
                loading.remove();
                addTestResult('system', `System Overview: Custom (${dateFrom} to ${dateTo})`, false, `❌ Error: ${error.message}`, null);
            }
        }

        async function testPersonalCustomDateRange() {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 9);

            const dateFrom = startDate.toISOString().split('T')[0];
            const dateTo = endDate.toISOString().split('T')[0];

            const resultsDiv = document.getElementById('personal-test-results');
            const loading = document.createElement('div');
            loading.className = 'test-result info';
            loading.innerHTML = `<div class="loading"></div> Testing Personal Overview Custom Date Range (${dateFrom} to ${dateTo})...`;
            resultsDiv.appendChild(loading);

            try {
                const url = `../ajax/admin_dashboard_data.php?personal_date_from=${dateFrom}&personal_date_to=${dateTo}`;
                const response = await fetch(url);
                const data = await response.json();

                loading.remove();

                if (!data.success) {
                    addTestResult('personal', `Personal Overview: Custom (${dateFrom} to ${dateTo})`, false, `❌ API returned error: ${data.error || 'Unknown error'}`, null);
                    return;
                }

                const personalStats = data.data.personal_stats || {};
                const requiredFields = ['completed_on_time', 'current_pending', 'current_delayed', 'total_tasks', 'total_tasks_all', 'shifted_tasks', 'wnd', 'wnd_on_time'];
                const missingFields = requiredFields.filter(field => personalStats[field] === undefined && personalStats[field] !== 0);

                if (missingFields.length > 0 || !data.data || !data.data.personal_stats) {
                    addTestResult('personal', `Personal Overview: Custom (${dateFrom} to ${dateTo})`, false, `❌ Missing required fields or invalid response structure`, { personal_stats: personalStats });
                    return;
                }

                addTestResult('personal', `Personal Overview: Custom (${dateFrom} to ${dateTo})`, true, `✅ Custom date range test passed. All stats validated.`, { personal_stats: personalStats });

            } catch (error) {
                loading.remove();
                addTestResult('personal', `Personal Overview: Custom (${dateFrom} to ${dateTo})`, false, `❌ Error: ${error.message}`, null);
            }
        }

        async function runAllSystemTests() {
            document.getElementById('system-test-results').innerHTML = '';
            await testSystemDateRange('7d');
            await new Promise(resolve => setTimeout(resolve, 500));
            await testSystemDateRange('14d');
            await new Promise(resolve => setTimeout(resolve, 500));
            await testSystemDateRange('28d');
            await new Promise(resolve => setTimeout(resolve, 500));
            await testSystemDateRange('all');
            await new Promise(resolve => setTimeout(resolve, 500));
            await testSystemCustomDateRange();
        }

        async function runAllPersonalTests() {
            document.getElementById('personal-test-results').innerHTML = '';
            await testPersonalDateRange('7d');
            await new Promise(resolve => setTimeout(resolve, 500));
            await testPersonalDateRange('14d');
            await new Promise(resolve => setTimeout(resolve, 500));
            await testPersonalDateRange('28d');
            await new Promise(resolve => setTimeout(resolve, 500));
            await testPersonalDateRange('all');
            await new Promise(resolve => setTimeout(resolve, 500));
            await testPersonalCustomDateRange();
        }

        window.addEventListener('DOMContentLoaded', () => {
            console.log('Admin Dashboard Stats Test Page Loaded');
        });
    </script>
</body>
</html>

