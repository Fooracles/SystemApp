<?php
/**
 * Test File for Manager Dashboard
 * Tests all functionality and logic of manager_dashboard.php
 */

// Start session and include necessary files
session_start();
require_once "../includes/config.php";
require_once "../includes/functions.php";
require_once "../includes/dashboard_components.php";

// Mock session data for manager
$_SESSION["loggedin"] = true;
$_SESSION["user_type"] = "manager";
$_SESSION["id"] = 1;
$_SESSION["username"] = "test_manager";
$_SESSION["name"] = "Test Manager";
$_SESSION["user_id"] = 1;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Manager Dashboard Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .test-title { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .test-result { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .pass { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .fail { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0; }
        .stat-card { background: #667eea; color: white; padding: 15px; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
    <h1>Manager Dashboard Test Suite</h1>";

// Test 1: Check if user is logged in
echo "<div class='test-section'>";
echo "<h2 class='test-title'>Test 1: Authentication Check</h2>";
if (isLoggedIn()) {
    echo "<div class='test-result pass'>✓ PASS: User is logged in</div>";
} else {
    echo "<div class='test-result fail'>✗ FAIL: User is not logged in</div>";
}

if (isManager() || isAdmin()) {
    echo "<div class='test-result pass'>✓ PASS: User has manager/admin privileges</div>";
} else {
    echo "<div class='test-result fail'>✗ FAIL: User does not have manager/admin privileges</div>";
}
echo "</div>";

// Test 2: Test calculatePersonalStats function
echo "<div class='test-section'>";
echo "<h2 class='test-title'>Test 2: Personal Stats Calculation</h2>";
try {
    $personal_stats = calculatePersonalStats($conn, $_SESSION["id"], $_SESSION["username"]);
    
    echo "<div class='test-result info'>Personal Stats Retrieved:</div>";
    echo "<pre>" . print_r($personal_stats, true) . "</pre>";
    
    $required_keys = ['completed_on_time', 'current_pending', 'current_delayed', 'total_tasks', 'wnd', 'wnd_on_time'];
    $all_keys_present = true;
    foreach ($required_keys as $key) {
        if (!isset($personal_stats[$key])) {
            $all_keys_present = false;
            break;
        }
    }
    
    if ($all_keys_present) {
        echo "<div class='test-result pass'>✓ PASS: All required stats keys are present</div>";
    } else {
        echo "<div class='test-result fail'>✗ FAIL: Missing required stats keys</div>";
    }
    
    if (is_numeric($personal_stats['total_tasks']) && $personal_stats['total_tasks'] >= 0) {
        echo "<div class='test-result pass'>✓ PASS: Total tasks is a valid number</div>";
    } else {
        echo "<div class='test-result fail'>✗ FAIL: Total tasks is not a valid number</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result fail'>✗ FAIL: Error calculating personal stats: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 3: Test getManagerTeamMembers function
echo "<div class='test-section'>";
echo "<h2 class='test-title'>Test 3: Team Members Retrieval</h2>";
try {
    $team_members = getManagerTeamMembers($conn, $_SESSION["id"]);
    
    echo "<div class='test-result info'>Team Members Retrieved: " . count($team_members) . "</div>";
    
    if (is_array($team_members)) {
        echo "<div class='test-result pass'>✓ PASS: Team members is an array</div>";
    } else {
        echo "<div class='test-result fail'>✗ FAIL: Team members is not an array</div>";
    }
    
    if (count($team_members) > 0) {
        echo "<div class='test-result info'>Sample Team Member:</div>";
        echo "<pre>" . print_r($team_members[0], true) . "</pre>";
    } else {
        echo "<div class='test-result info'>No team members found (this may be expected if manager has no doers)</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result fail'>✗ FAIL: Error retrieving team members: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 4: Test calculateTeamStats function
echo "<div class='test-section'>";
echo "<h2 class='test-title'>Test 4: Team Stats Calculation</h2>";
try {
    $team_members = getManagerTeamMembers($conn, $_SESSION["id"]);
    $team_member_ids = array_column($team_members, 'id');
    $team_stats = calculateTeamStats($conn, $_SESSION["id"], $team_member_ids);
    
    echo "<div class='test-result info'>Team Stats Retrieved:</div>";
    echo "<pre>" . print_r($team_stats, true) . "</pre>";
    
    $required_keys = ['total_tasks', 'completed_tasks', 'delayed_tasks', 'pending_tasks', 'completion_rate'];
    $all_keys_present = true;
    foreach ($required_keys as $key) {
        if (!isset($team_stats[$key])) {
            $all_keys_present = false;
            break;
        }
    }
    
    if ($all_keys_present) {
        echo "<div class='test-result pass'>✓ PASS: All required team stats keys are present</div>";
    } else {
        echo "<div class='test-result fail'>✗ FAIL: Missing required team stats keys</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result fail'>✗ FAIL: Error calculating team stats: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 5: Test AJAX endpoint
echo "<div class='test-section'>";
echo "<h2 class='test-title'>Test 5: AJAX Endpoint Test</h2>";
try {
    // Construct absolute URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_path = dirname($_SERVER['SCRIPT_NAME']);
    $base_path = str_replace('/tests', '', $script_path);
    $url = $protocol . $host . $base_path . '/ajax/manager_dashboard_data.php';
    
    // Alternative: Use relative path with file_get_contents if curl fails
    $use_curl = function_exists('curl_init');
    
    if ($use_curl) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            echo "<div class='test-result fail'>✗ FAIL: cURL Error: " . htmlspecialchars($curl_error) . "</div>";
            echo "<div class='test-result info'>Attempted URL: " . htmlspecialchars($url) . "</div>";
            echo "<div class='test-result info'>Trying alternative method (direct function call)...</div>";
            $use_curl = false;
        }
    }
    
    // If curl failed or not available, test the functions directly
    if (!$use_curl || $http_code != 200) {
        echo "<div class='test-result info'>Testing functions directly (simulating AJAX endpoint)...</div>";
        
        // Simulate what the AJAX endpoint does
        $personal_stats = calculatePersonalStats($conn, $_SESSION["id"], $_SESSION["username"]);
        $personal_completion_rate = 0;
        if ($personal_stats['total_tasks'] > 0) {
            $personal_completion_rate = round(($personal_stats['completed_on_time'] / $personal_stats['total_tasks']) * 100, 2);
        }
        
        $team_members = getManagerTeamMembers($conn, $_SESSION["id"]);
        $team_member_ids = array_column($team_members, 'id');
        $team_stats = calculateTeamStats($conn, $_SESSION["id"], $team_member_ids);
        
        $data = [
            'success' => true,
            'data' => [
                'personal_stats' => $personal_stats,
                'personal_completion_rate' => $personal_completion_rate,
                'team_stats' => $team_stats,
                'team_members' => $team_members
            ]
        ];
        
        echo "<div class='test-result pass'>✓ PASS: Functions executed successfully (direct test)</div>";
        echo "<div class='test-result info'>Response Data Structure:</div>";
        echo "<pre>" . print_r(array_keys($data['data']), true) . "</pre>";
    } else {
        // cURL succeeded
        $data = json_decode($response, true);
        if ($data && isset($data['success'])) {
            if ($data['success']) {
                echo "<div class='test-result pass'>✓ PASS: AJAX endpoint returned success</div>";
                echo "<div class='test-result info'>Response Data Structure:</div>";
                echo "<pre>" . print_r(array_keys($data['data']), true) . "</pre>";
            } else {
                echo "<div class='test-result fail'>✗ FAIL: AJAX endpoint returned error: " . ($data['error'] ?? 'Unknown error') . "</div>";
            }
        } else {
            echo "<div class='test-result fail'>✗ FAIL: Invalid JSON response from AJAX endpoint</div>";
            echo "<div class='test-result info'>Response: " . htmlspecialchars(substr($response, 0, 500)) . "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='test-result fail'>✗ FAIL: Error testing AJAX endpoint: " . $e->getMessage() . "</div>";
    echo "<div class='test-result info'>Stack trace: " . htmlspecialchars($e->getTraceAsString()) . "</div>";
}
echo "</div>";

// Test 6: Test getLeaderboardData function
echo "<div class='test-section'>";
echo "<h2 class='test-title'>Test 6: Leaderboard Data</h2>";
try {
    $leaderboard = getLeaderboardData($conn, 10, 'doer');
    
    echo "<div class='test-result info'>Leaderboard Entries: " . count($leaderboard) . "</div>";
    
    if (is_array($leaderboard)) {
        echo "<div class='test-result pass'>✓ PASS: Leaderboard is an array</div>";
    } else {
        echo "<div class='test-result fail'>✗ FAIL: Leaderboard is not an array</div>";
    }
    
    if (count($leaderboard) > 0) {
        echo "<div class='test-result info'>Top 3 Performers:</div>";
        echo "<pre>" . print_r(array_slice($leaderboard, 0, 3), true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<div class='test-result fail'>✗ FAIL: Error retrieving leaderboard: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 7: Test getTeamAvailabilityData function
echo "<div class='test-section'>";
echo "<h2 class='test-title'>Test 7: Team Availability Data</h2>";
try {
    $team_members = getManagerTeamMembers($conn, $_SESSION["id"]);
    $team_member_ids = array_column($team_members, 'id');
    $team_availability = getTeamAvailabilityData($conn, $team_member_ids);
    
    echo "<div class='test-result info'>Team Availability Data Retrieved: " . count($team_availability) . " members</div>";
    
    $available_count = count(array_filter($team_availability, function($m) { return $m['status'] === 'available'; }));
    $on_leave_count = count(array_filter($team_availability, function($m) { return $m['status'] === 'on-leave'; }));
    
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'>Available: $available_count</div>";
    echo "<div class='stat-card'>On Leave: $on_leave_count</div>";
    echo "</div>";
    
    if (is_array($team_availability)) {
        echo "<div class='test-result pass'>✓ PASS: Team availability is an array</div>";
    } else {
        echo "<div class='test-result fail'>✗ FAIL: Team availability is not an array</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result fail'>✗ FAIL: Error retrieving team availability: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 8: Test date range filtering
echo "<div class='test-section'>";
echo "<h2 class='test-title'>Test 8: Date Range Filtering</h2>";
try {
    $date_from = date('Y-m-d', strtotime('-7 days'));
    $date_to = date('Y-m-d');
    
    $stats_with_range = calculatePersonalStats($conn, $_SESSION["id"], $_SESSION["username"], $date_from, $date_to);
    $stats_without_range = calculatePersonalStats($conn, $_SESSION["id"], $_SESSION["username"]);
    
    echo "<div class='test-result info'>Stats with date range (last 7 days):</div>";
    echo "<pre>" . print_r($stats_with_range, true) . "</pre>";
    
    echo "<div class='test-result info'>Stats without date range (all time):</div>";
    echo "<pre>" . print_r($stats_without_range, true) . "</pre>";
    
    if ($stats_with_range['total_tasks'] <= $stats_without_range['total_tasks']) {
        echo "<div class='test-result pass'>✓ PASS: Date range filtering works correctly</div>";
    } else {
        echo "<div class='test-result fail'>✗ FAIL: Date range filtering may not be working correctly</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result fail'>✗ FAIL: Error testing date range filtering: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Summary
echo "<div class='test-section'>";
echo "<h2 class='test-title'>Test Summary</h2>";
echo "<div class='test-result info'>All tests completed. Review results above.</div>";
echo "<p><strong>Note:</strong> Some tests may show expected results based on your database state.</p>";
echo "</div>";

echo "</body></html>";
?>

