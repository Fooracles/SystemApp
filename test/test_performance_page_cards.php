<?php
/**
 * Performance Page Cards Test
 * Tests that performance page cards (Delayed, Pending, WND_On_Time) are working correctly
 * 
 * This test verifies:
 * 1. Delayed card displays all_delayed_tasks correctly
 * 2. Pending card displays current_pending correctly
 * 3. WND_On_Time card displays wnd_on_time correctly
 * 4. Data is returned for different date ranges
 * 
 * Usage: Run this file in a browser after logging in
 * Make sure you're logged in before running this test
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
$current_user_type = $_SESSION['user_type'] ?? '';

if (empty($current_user_id)) {
    die("❌ ERROR: Could not determine user ID from session.");
}

// Get test username from query parameter or use current user
$test_username = isset($_GET['username']) ? trim($_GET['username']) : $current_username;

// Get user list for selector (if admin/manager)
$available_users = [];
if (isAdmin() || isManager()) {
    if (isAdmin()) {
        $sql = "SELECT id, username, name, user_type FROM users WHERE user_type IN ('manager', 'doer') ORDER BY name";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $available_users[] = $row;
            }
        }
    } else {
        // Manager: get themselves + their doers
        $sql_self = "SELECT id, username, name, user_type FROM users WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_self)) {
            mysqli_stmt_bind_param($stmt, "i", $current_user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $available_users[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        
        require_once "../includes/dashboard_components.php";
        $team_members = getManagerTeamMembers($conn, $current_user_id);
        if (is_array($team_members)) {
            $available_users = array_merge($available_users, $team_members);
        }
    }
} else {
    // Doer: only themselves
    $sql = "SELECT id, username, name, user_type FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $current_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $available_users[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Page Cards Test</title>
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
        .controls {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #e0e0e0;
        }
        .controls label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #555;
        }
        .controls select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            width: 100%;
            max-width: 300px;
        }
        .controls button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 10px;
            transition: transform 0.2s;
        }
        .controls button:hover {
            transform: translateY(-2px);
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
        .card-display {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .card {
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .card.delayed {
            border-color: #ef4444;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        }
        .card.pending {
            border-color: #fbbf24;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        }
        .card.wnd-on-time {
            border-color: #78909c;
            background: linear-gradient(135deg, #eceff1 0%, #cfd8dc 100%);
        }
        .card h4 {
            margin-bottom: 10px;
            color: #333;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin: 10px 0;
        }
        .card-label {
            font-size: 0.85rem;
            color: #666;
        }
        .loading {
            text-align: center;
            padding: 20px;
            color: #667eea;
        }
        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .summary h2 {
            color: white;
            border-bottom: 2px solid rgba(255,255,255,0.3);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .summary-stat {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        .summary-stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .summary-stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 Performance Page Cards Test</h1>
        
        <div class="user-info">
            <strong>Current User:</strong> <?php echo htmlspecialchars($current_username); ?> 
            (<?php echo htmlspecialchars($current_user_type); ?>) | 
            <strong>User ID:</strong> <?php echo $current_user_id; ?>
        </div>

        <div class="controls">
            <label for="userSelect">Select User to Test:</label>
            <select id="userSelect">
                <?php foreach ($available_users as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['username']); ?>" 
                            <?php echo ($user['username'] === $test_username) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name'] . ' (' . $user['username'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button onclick="runAllTests()">Run All Tests</button>
        </div>

        <div id="testResults"></div>
    </div>

    <script>
        const currentUsername = '<?php echo htmlspecialchars($test_username); ?>';
        let testResults = [];

        // Helper function to get Monday of a given week
        function getMondayOfWeek(date) {
            const d = new Date(date);
            d.setHours(0, 0, 0, 0);
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? -6 : 1);
            const monday = new Date(d);
            monday.setDate(diff);
            return monday;
        }

        // Calculate date range based on time range type
        function calculateDateRange(rangeType) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const thisWeekMonday = getMondayOfWeek(today);
            
            let fromDate, toDate;
            
            switch(rangeType) {
                case 'last_week':
                    const lastWeekMonday = new Date(thisWeekMonday);
                    lastWeekMonday.setDate(thisWeekMonday.getDate() - 7);
                    toDate = new Date(lastWeekMonday);
                    toDate.setDate(lastWeekMonday.getDate() + 6);
                    fromDate = lastWeekMonday;
                    break;
                case '2w':
                    const twoWeeksAgoMonday = new Date(thisWeekMonday);
                    twoWeeksAgoMonday.setDate(thisWeekMonday.getDate() - 14);
                    const lastWeekSunday2w = new Date(thisWeekMonday);
                    lastWeekSunday2w.setDate(thisWeekMonday.getDate() - 1);
                    fromDate = twoWeeksAgoMonday;
                    toDate = lastWeekSunday2w;
                    break;
                case '4w':
                    const fourWeeksAgoMonday = new Date(thisWeekMonday);
                    fourWeeksAgoMonday.setDate(thisWeekMonday.getDate() - 28);
                    const lastWeekSunday = new Date(thisWeekMonday);
                    lastWeekSunday.setDate(thisWeekMonday.getDate() - 1);
                    fromDate = fourWeeksAgoMonday;
                    toDate = lastWeekSunday;
                    break;
                case '8w':
                    const eightWeeksAgoMonday = new Date(thisWeekMonday);
                    eightWeeksAgoMonday.setDate(thisWeekMonday.getDate() - 56);
                    const lastWeekSunday8w = new Date(thisWeekMonday);
                    lastWeekSunday8w.setDate(thisWeekMonday.getDate() - 1);
                    fromDate = eightWeeksAgoMonday;
                    toDate = lastWeekSunday8w;
                    break;
                case '12w':
                    const twelveWeeksAgoMonday = new Date(thisWeekMonday);
                    twelveWeeksAgoMonday.setDate(thisWeekMonday.getDate() - 84);
                    const lastWeekSunday12w = new Date(thisWeekMonday);
                    lastWeekSunday12w.setDate(thisWeekMonday.getDate() - 1);
                    fromDate = twelveWeeksAgoMonday;
                    toDate = lastWeekSunday12w;
                    break;
                default:
                    const defaultLastWeekMonday = new Date(thisWeekMonday);
                    defaultLastWeekMonday.setDate(thisWeekMonday.getDate() - 7);
                    toDate = new Date(defaultLastWeekMonday);
                    toDate.setDate(defaultLastWeekMonday.getDate() + 6);
                    fromDate = defaultLastWeekMonday;
            }
            
            return {
                from: fromDate.toISOString().split('T')[0],
                to: toDate.toISOString().split('T')[0]
            };
        }

        function addTestResult(title, passed, message, data = null) {
            testResults.push({ title, passed, message, data });
        }

        function displayResults() {
            const container = document.getElementById('testResults');
            if (!container) return;

            const passed = testResults.filter(r => r.passed).length;
            const failed = testResults.filter(r => !r.passed).length;
            const total = testResults.length;

            let html = `
                <div class="summary">
                    <h2>Test Summary</h2>
                    <div class="summary-stats">
                        <div class="summary-stat">
                            <div class="summary-stat-value">${total}</div>
                            <div class="summary-stat-label">Total Tests</div>
                        </div>
                        <div class="summary-stat" style="background: rgba(22, 163, 74, 0.3);">
                            <div class="summary-stat-value">${passed}</div>
                            <div class="summary-stat-label">Passed</div>
                        </div>
                        <div class="summary-stat" style="background: rgba(239, 68, 68, 0.3);">
                            <div class="summary-stat-value">${failed}</div>
                            <div class="summary-stat-label">Failed</div>
                        </div>
                    </div>
                </div>
            `;

            // Group results by date range
            const groupedResults = {};
            testResults.forEach(result => {
                const key = result.title.split(':')[0] || 'Other';
                if (!groupedResults[key]) {
                    groupedResults[key] = [];
                }
                groupedResults[key].push(result);
            });

            Object.keys(groupedResults).forEach(group => {
                html += `<div class="test-section">`;
                html += `<h2>${group}</h2>`;
                
                groupedResults[group].forEach(result => {
                    const resultClass = result.passed ? 'pass' : 'fail';
                    html += `
                        <div class="test-case">
                            <h3>${result.title}</h3>
                            <div class="test-result ${resultClass}">
                                ${result.message}
                            </div>
                    `;
                    
                    if (result.data) {
                        // Display card data if available
                        if (result.data.stats) {
                            const stats = result.data.stats;
                            html += `<div class="card-display">`;
                            
                            // Delayed Card
                            const delayedValue = stats.all_delayed_tasks !== undefined ? stats.all_delayed_tasks : (stats.current_delayed !== undefined ? stats.current_delayed : 'N/A');
                            html += `
                                <div class="card delayed">
                                    <h4>Delayed</h4>
                                    <div class="card-value">${delayedValue}</div>
                                    <div class="card-label">all_delayed_tasks: ${stats.all_delayed_tasks !== undefined ? stats.all_delayed_tasks : 'MISSING'}</div>
                                    <div class="card-label">current_delayed: ${stats.current_delayed !== undefined ? stats.current_delayed : 'N/A'}</div>
                                </div>
                            `;
                            
                            // Pending Card
                            const pendingValue = stats.current_pending !== undefined ? stats.current_pending : 'N/A';
                            html += `
                                <div class="card pending">
                                    <h4>Pending</h4>
                                    <div class="card-value">${pendingValue}</div>
                                    <div class="card-label">current_pending: ${stats.current_pending !== undefined ? stats.current_pending : 'MISSING'}</div>
                                </div>
                            `;
                            
                            // WND On Time Card
                            const wndOnTimeValue = stats.wnd_on_time !== undefined ? stats.wnd_on_time.toFixed(1) + '%' : 'N/A';
                            html += `
                                <div class="card wnd-on-time">
                                    <h4>WND On Time</h4>
                                    <div class="card-value">${wndOnTimeValue}</div>
                                    <div class="card-label">wnd_on_time: ${stats.wnd_on_time !== undefined ? stats.wnd_on_time : 'MISSING'}</div>
                                </div>
                            `;
                            
                            html += `</div>`;
                        }
                        
                        // Display raw data
                        html += `<pre style="margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 4px; overflow-x: auto;">${JSON.stringify(result.data, null, 2)}</pre>`;
                    }
                    
                    html += `</div>`;
                });
                
                html += `</div>`;
            });

            container.innerHTML = html;
        }

        async function testDateRange(rangeType, rangeLabel) {
            const username = document.getElementById('userSelect').value || currentUsername;
            const dateRange = calculateDateRange(rangeType);
            
            try {
                const url = `../ajax/team_performance_data.php?username=${encodeURIComponent(username)}&date_from=${dateRange.from}&date_to=${dateRange.to}`;
                const response = await fetch(url);
                const responseText = await response.text();
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    addTestResult(
                        `${rangeLabel}: ${rangeType.toUpperCase()}`,
                        false,
                        `❌ Invalid JSON response: ${parseError.message}. Response: ${responseText.substring(0, 200)}`,
                        null
                    );
                    return;
                }

                if (!data.success) {
                    addTestResult(
                        `${rangeLabel}: ${rangeType.toUpperCase()}`,
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
                        `${rangeLabel}: ${rangeType.toUpperCase()}`,
                        false,
                        `❌ Response missing data or stats object`,
                        data
                    );
                    return;
                }

                const stats = data.data.stats || {};
                
                // Check for required fields
                const requiredFields = {
                    'all_delayed_tasks': 'Delayed card (all_delayed_tasks)',
                    'current_pending': 'Pending card (current_pending)',
                    'wnd_on_time': 'WND On Time card (wnd_on_time)'
                };

                const missingFields = [];
                const invalidFields = [];
                
                Object.keys(requiredFields).forEach(field => {
                    if (stats[field] === undefined) {
                        missingFields.push(field);
                    } else if (field === 'wnd_on_time') {
                        // WND_On_Time can be 0, null, or a number
                        if (stats[field] !== null && stats[field] !== 0 && (isNaN(stats[field]) || !isFinite(stats[field]))) {
                            invalidFields.push(`${field}=${stats[field]}`);
                        }
                    } else {
                        // Delayed and Pending should be numbers (can be 0)
                        if (isNaN(stats[field]) || !isFinite(stats[field])) {
                            invalidFields.push(`${field}=${stats[field]}`);
                        }
                    }
                });

                if (missingFields.length > 0) {
                    addTestResult(
                        `${rangeLabel}: ${rangeType.toUpperCase()}`,
                        false,
                        `❌ Missing required fields: ${missingFields.join(', ')}`,
                        { stats: stats, fullData: data.data }
                    );
                    return;
                }

                if (invalidFields.length > 0) {
                    addTestResult(
                        `${rangeLabel}: ${rangeType.toUpperCase()}`,
                        false,
                        `❌ Invalid numeric values: ${invalidFields.join(', ')}`,
                        { stats: stats, fullData: data.data }
                    );
                    return;
                }

                // All checks passed
                const delayedValue = stats.all_delayed_tasks;
                const pendingValue = stats.current_pending;
                const wndOnTimeValue = stats.wnd_on_time;
                
                let message = `✅ All cards have valid data:\n`;
                message += `   • Delayed: ${delayedValue}\n`;
                message += `   • Pending: ${pendingValue}\n`;
                message += `   • WND On Time: ${wndOnTimeValue !== null && wndOnTimeValue !== undefined ? wndOnTimeValue.toFixed(1) + '%' : 'N/A'}\n`;
                message += `   • Date Range: ${dateRange.from} to ${dateRange.to}`;
                
                addTestResult(
                    `${rangeLabel}: ${rangeType.toUpperCase()}`,
                    true,
                    message,
                    { stats: stats, dateRange: dateRange, fullData: data.data }
                );

            } catch (error) {
                addTestResult(
                    `${rangeLabel}: ${rangeType.toUpperCase()}`,
                    false,
                    `❌ Network error: ${error.message}`,
                    null
                );
            }
        }

        async function runAllTests() {
            const username = document.getElementById('userSelect').value || currentUsername;
            testResults = [];
            
            const container = document.getElementById('testResults');
            container.innerHTML = '<div class="loading">🔄 Running tests...</div>';

            const dateRanges = [
                { type: 'last_week', label: 'Last Week' },
                { type: '2w', label: '2 Weeks' },
                { type: '4w', label: '4 Weeks' },
                { type: '8w', label: '8 Weeks' },
                { type: '12w', label: '12 Weeks' }
            ];

            for (const range of dateRanges) {
                await testDateRange(range.type, range.label);
                // Small delay between requests
                await new Promise(resolve => setTimeout(resolve, 200));
            }

            displayResults();
        }

        // Auto-run tests on page load
        window.addEventListener('DOMContentLoaded', function() {
            runAllTests();
        });
    </script>
</body>
</html>

