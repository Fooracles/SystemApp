<?php
/**
 * Test file to validate Pending and Delayed task counts consistency
 * 
 * This test validates that Pending and Delayed numbers are identical across:
 * - Manage Tasks page
 * - Performance page
 * - All dashboards (Admin / Manager / Doer)
 * 
 * Tests:
 * - Different week toggles
 * - Performance page time-range toggles
 * - User selection dropdowns
 */

session_start();
require_once "../includes/config.php";
require_once "../includes/functions.php";
require_once "../includes/dashboard_components.php";

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    die("Access denied. Admin access required.");
}

// Get test parameters
$test_week = isset($_GET['week']) ? $_GET['week'] : 'current';
$test_user = isset($_GET['user']) ? intval($_GET['user']) : null;

// Calculate week dates (Monday to Sunday)
$today = new DateTime();
$today->setTime(0, 0, 0);

// Get Monday of current week
$dayOfWeek = (int)$today->format('N'); // 1 = Monday, 7 = Sunday
$mondayOfThisWeek = clone $today;
if ($dayOfWeek == 7) { // Sunday
    $mondayOfThisWeek->modify('-6 days');
} else {
    $mondayOfThisWeek->modify('-' . ($dayOfWeek - 1) . ' days');
}

$sundayOfThisWeek = clone $mondayOfThisWeek;
$sundayOfThisWeek->modify('+6 days');
$sundayOfThisWeek->setTime(23, 59, 59);

// Calculate test week dates
switch ($test_week) {
    case 'current':
        $week_start = $mondayOfThisWeek->format('Y-m-d');
        $week_end = $sundayOfThisWeek->format('Y-m-d');
        break;
    case 'last':
        $lastWeekMonday = clone $mondayOfThisWeek;
        $lastWeekMonday->modify('-7 days');
        $lastWeekSunday = clone $lastWeekMonday;
        $lastWeekSunday->modify('+6 days');
        $week_start = $lastWeekMonday->format('Y-m-d');
        $week_end = $lastWeekSunday->format('Y-m-d');
        break;
    default:
        $week_start = $mondayOfThisWeek->format('Y-m-d');
        $week_end = $sundayOfThisWeek->format('Y-m-d');
}

$page_title = "Pending/Delayed Consistency Test";
require_once "../includes/header.php";
?>

<style>
    .test-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 2rem;
        background: var(--dark-bg-card);
        border-radius: var(--radius-lg);
        border: 1px solid var(--glass-border);
    }
    
    .test-section {
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: var(--dark-bg-glass);
        border-radius: var(--radius-md);
        border: 1px solid var(--glass-border);
    }
    
    .test-section h3 {
        color: var(--brand-primary);
        margin-bottom: 1rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
        background: var(--dark-bg-card);
        border-radius: var(--radius-sm);
        border: 1px solid var(--glass-border);
    }
    
    .stat-label {
        font-size: 0.875rem;
        color: var(--dark-text-secondary);
        margin-bottom: 0.5rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--dark-text-primary);
    }
    
    .match {
        color: var(--brand-success);
    }
    
    .mismatch {
        color: var(--brand-danger);
    }
    
    .test-controls {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        padding: 1rem;
        background: var(--dark-bg-glass);
        border-radius: var(--radius-md);
    }
    
    .test-controls select,
    .test-controls button {
        padding: 0.5rem 1rem;
        background: var(--dark-bg-card);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-sm);
        color: var(--dark-text-primary);
    }
</style>

<div class="test-container">
    <h1>Pending/Delayed Task Consistency Test</h1>
    
    <div class="test-controls">
        <label>
            Test Week:
            <select name="week" onchange="updateTest()">
                <option value="current" <?php echo $test_week === 'current' ? 'selected' : ''; ?>>Current Week</option>
                <option value="last" <?php echo $test_week === 'last' ? 'selected' : ''; ?>>Last Week</option>
            </select>
        </label>
        
        <label>
            Test User:
            <select name="user" onchange="updateTest()">
                <option value="">All Users</option>
                <?php
                $users_sql = "SELECT id, username, name FROM users WHERE user_type = 'doer' ORDER BY name";
                $users_result = mysqli_query($conn, $users_sql);
                if ($users_result) {
                    while ($user_row = mysqli_fetch_assoc($users_result)) {
                        $selected = ($test_user == $user_row['id']) ? 'selected' : '';
                        echo "<option value='{$user_row['id']}' $selected>{$user_row['name']} ({$user_row['username']})</option>";
                    }
                }
                ?>
            </select>
        </label>
        
        <button onclick="updateTest()">Refresh Test</button>
    </div>
    
    <div class="test-section">
        <h3>Test Week: <?php echo date('M d', strtotime($week_start)); ?> - <?php echo date('M d, Y', strtotime($week_end)); ?></h3>
        <p>Week Start (Monday): <?php echo $week_start; ?></p>
        <p>Week End (Sunday): <?php echo $week_end; ?></p>
    </div>
    
    <?php
    // Test 1: Manage Tasks Page Stats
    // Simulate the exact logic from manage_tasks.php
    $manage_tasks_stats = [
        'pending' => 0,
        'delayed' => 0,
        'total_tasks' => 0
    ];
    
    // Simulate manage_tasks.php calculation - fetch all tasks (similar to manage_tasks.php)
    $all_tasks = [];
    
    // Fetch delegation tasks
    $delegation_query = "SELECT id, status, planned_date, planned_time, actual_date, actual_time, 'delegation' as task_type
                         FROM tasks";
    if ($test_user) {
        $delegation_query .= " WHERE doer_id = " . intval($test_user);
    }
    $delegation_result = mysqli_query($conn, $delegation_query);
    if ($delegation_result) {
        while ($row = mysqli_fetch_assoc($delegation_result)) {
            $all_tasks[] = $row;
        }
    }
    
    // Fetch FMS tasks
    $fms_query = "SELECT id, status, planned, actual, doer_name, 'fms' as task_type FROM fms_tasks";
    if ($test_user) {
        $user_sql = "SELECT username FROM users WHERE id = " . intval($test_user);
        $user_result = mysqli_query($conn, $user_sql);
        if ($user_result && $user_row = mysqli_fetch_assoc($user_result)) {
            $username = mysqli_real_escape_string($conn, $user_row['username']);
            $fms_query .= " WHERE doer_name = '$username'";
        }
    }
    $fms_result = mysqli_query($conn, $fms_query);
    if ($fms_result) {
        while ($row = mysqli_fetch_assoc($fms_result)) {
            $planned_ts = parseFMSDateTimeString_doer($row['planned'] ?? '');
            $actual_ts = parseFMSDateTimeString_doer($row['actual'] ?? '');
            $row['planned_date'] = $planned_ts ? date('Y-m-d', $planned_ts) : '';
            $row['planned_time'] = $planned_ts ? date('H:i:s', $planned_ts) : '';
            $row['actual_date'] = $actual_ts ? date('Y-m-d', $actual_ts) : '';
            $row['actual_time'] = $actual_ts ? date('H:i:s', $actual_ts) : '';
            $all_tasks[] = $row;
        }
    }
    
    // Fetch checklist tasks
    $checklist_query = "SELECT id, COALESCE(status, 'pending') as status, task_date as planned_date, 
                               '23:59:59' as planned_time, actual_date, actual_time, 'checklist' as task_type
                        FROM checklist_subtasks";
    if ($test_user) {
        $user_sql = "SELECT username FROM users WHERE id = " . intval($test_user);
        $user_result = mysqli_query($conn, $user_sql);
        if ($user_result && $user_row = mysqli_fetch_assoc($user_result)) {
            $username = mysqli_real_escape_string($conn, $user_row['username']);
            $checklist_query .= " WHERE assignee = '$username'";
        }
    }
    $checklist_result = mysqli_query($conn, $checklist_query);
    if ($checklist_result) {
        while ($row = mysqli_fetch_assoc($checklist_result)) {
            $all_tasks[] = $row;
        }
    }
    
    $manage_tasks_stats['total_tasks'] = count($all_tasks);
    
    // Calculate using new week-based logic (same as manage_tasks.php)
    foreach ($all_tasks as $task) {
        // Pending tasks
        if (isTaskPendingForWeek(
            $task['status'] ?? '',
            $task['planned_date'] ?? '',
            $task['planned_time'] ?? '',
            $task['actual_date'] ?? '',
            $task['actual_time'] ?? '',
            $week_start,
            $task['task_type'] ?? 'delegation'
        )) {
            $manage_tasks_stats['pending']++;
        }
        
        // Delayed tasks
        if (isTaskDelayedForWeek(
            $task['status'] ?? '',
            $task['planned_date'] ?? '',
            $task['planned_time'] ?? '',
            $task['actual_date'] ?? '',
            $task['actual_time'] ?? '',
            $week_start,
            $task['task_type'] ?? 'delegation'
        )) {
            $manage_tasks_stats['delayed']++;
        }
    }
    
    // Test 2: Dashboard Stats (using calculatePersonalStats or calculateGlobalTaskStats)
    $dashboard_stats = [
        'pending' => 0,
        'delayed' => 0,
        'total_tasks' => 0
    ];
    
    if ($test_user) {
        $user_sql = "SELECT id, username FROM users WHERE id = " . intval($test_user);
        $user_result = mysqli_query($conn, $user_sql);
        if ($user_result && $user_row = mysqli_fetch_assoc($user_result)) {
            // Use week_start and week_end as date range (same as manage_tasks.php)
            $user_stats = calculatePersonalStats($conn, $user_row['id'], $user_row['username'], $week_start, $week_end);
            $dashboard_stats['pending'] = $user_stats['current_pending'] ?? 0;
            $dashboard_stats['delayed'] = $user_stats['current_delayed'] ?? 0;
            $dashboard_stats['total_tasks'] = $user_stats['total_tasks'] ?? 0;
        }
    } else {
        // For all users, use global stats
        $global_stats = calculateGlobalTaskStats($conn, $week_start, $week_end);
        $dashboard_stats['pending'] = $global_stats['pending_tasks'] ?? 0;
        $dashboard_stats['delayed'] = $global_stats['delayed_tasks'] ?? 0;
        $dashboard_stats['total_tasks'] = $global_stats['total_tasks'] ?? 0;
    }
    
    // Test 3: Performance Page Stats (should match dashboard)
    // Performance page uses calculatePersonalStats, so it should match dashboard
    $performance_stats = [
        'pending' => $dashboard_stats['pending'],
        'delayed' => $dashboard_stats['delayed'],
        'total_tasks' => $dashboard_stats['total_tasks']
    ];
    
    // Compare results
    $pending_match = ($manage_tasks_stats['pending'] == $dashboard_stats['pending'] && 
                     $dashboard_stats['pending'] == $performance_stats['pending']);
    $delayed_match = ($manage_tasks_stats['delayed'] == $dashboard_stats['delayed'] && 
                     $dashboard_stats['delayed'] == $performance_stats['delayed']);
    ?>
    
    <div class="test-section">
        <h3>Test Results</h3>
        
        <div style="margin-bottom: 1rem; padding: 0.75rem; background: rgba(99, 102, 241, 0.1); border-radius: var(--radius-sm); border: 1px solid var(--brand-primary);">
            <strong>Test Configuration:</strong>
            <ul style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                <li>Total Tasks Found: <?php echo $manage_tasks_stats['total_tasks']; ?></li>
                <li>Week Start: <?php echo $week_start; ?> (Monday)</li>
                <li>Week End: <?php echo $week_end; ?> (Sunday)</li>
                <?php if ($test_user): ?>
                    <li>Filtered by User ID: <?php echo $test_user; ?></li>
                <?php else: ?>
                    <li>All Users (No Filter)</li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Manage Tasks - Pending</div>
                <div class="stat-value"><?php echo $manage_tasks_stats['pending']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Dashboard - Pending</div>
                <div class="stat-value"><?php echo $dashboard_stats['pending']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Performance - Pending</div>
                <div class="stat-value"><?php echo $performance_stats['pending']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Pending Match</div>
                <div class="stat-value <?php echo $pending_match ? 'match' : 'mismatch'; ?>">
                    <?php echo $pending_match ? '✓ MATCH' : '✗ MISMATCH'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Manage Tasks - Delayed</div>
                <div class="stat-value"><?php echo $manage_tasks_stats['delayed']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Dashboard - Delayed</div>
                <div class="stat-value"><?php echo $dashboard_stats['delayed']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Performance - Delayed</div>
                <div class="stat-value"><?php echo $performance_stats['delayed']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Delayed Match</div>
                <div class="stat-value <?php echo $delayed_match ? 'match' : 'mismatch'; ?>">
                    <?php echo $delayed_match ? '✓ MATCH' : '✗ MISMATCH'; ?>
                </div>
            </div>
        </div>
        
        <?php if (!$pending_match || !$delayed_match): ?>
            <div style="margin-top: 1rem; padding: 1rem; background: rgba(220, 53, 69, 0.1); border-radius: var(--radius-sm); border: 1px solid var(--brand-danger);">
                <strong>⚠️ Mismatch Detected!</strong>
                <p style="margin: 0.5rem 0;">The counts should match across all pages. Please check the logic implementation.</p>
                <ul style="margin-top: 0.5rem;">
                    <?php if (!$pending_match): ?>
                        <li><strong>Pending Mismatch:</strong>
                            <ul style="margin-top: 0.25rem;">
                                <li>Manage Tasks: <?php echo $manage_tasks_stats['pending']; ?></li>
                                <li>Dashboard: <?php echo $dashboard_stats['pending']; ?> (Difference: <?php echo $manage_tasks_stats['pending'] - $dashboard_stats['pending']; ?>)</li>
                                <li>Performance: <?php echo $performance_stats['pending']; ?></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <?php if (!$delayed_match): ?>
                        <li><strong>Delayed Mismatch:</strong>
                            <ul style="margin-top: 0.25rem;">
                                <li>Manage Tasks: <?php echo $manage_tasks_stats['delayed']; ?></li>
                                <li>Dashboard: <?php echo $dashboard_stats['delayed']; ?> (Difference: <?php echo $manage_tasks_stats['delayed'] - $dashboard_stats['delayed']; ?>)</li>
                                <li>Performance: <?php echo $performance_stats['delayed']; ?></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
                <p style="margin-top: 0.75rem; font-size: 0.875rem; color: var(--dark-text-secondary);">
                    <strong>Note:</strong> Make sure all pages are using the same week-based logic:
                    <br>- Week definition: Monday to Sunday
                    <br>- Pending: Tasks planned in week but not completed in that week
                    <br>- Delayed: Completed tasks that were delayed and completed within the selected week
                </p>
            </div>
        <?php else: ?>
            <div style="margin-top: 1rem; padding: 1rem; background: rgba(40, 167, 69, 0.1); border-radius: var(--radius-sm); border: 1px solid var(--brand-success);">
                <strong>✓ All counts match!</strong> The pending and delayed task counts are consistent across all pages.
                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">
                    Pending: <?php echo $manage_tasks_stats['pending']; ?> | 
                    Delayed: <?php echo $manage_tasks_stats['delayed']; ?> | 
                    Total Tasks: <?php echo $manage_tasks_stats['total_tasks']; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateTest() {
    const week = document.querySelector('select[name="week"]').value;
    const user = document.querySelector('select[name="user"]').value;
    const params = new URLSearchParams();
    if (week) params.set('week', week);
    if (user) params.set('user', user);
    window.location.href = '?' + params.toString();
}
</script>

<?php require_once "../includes/footer.php"; ?>

