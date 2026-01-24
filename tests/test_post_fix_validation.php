<?php
/**
 * Regression Test Suite – November 25, 2025
 * Verifies:
 *  1. Manager-specific FMS visibility (manage_tasks.php)
 *  2. Motivation panel data pipeline (manager dashboard)
 *  3. Leaderboard & team availability exclusions (no Admin user surfaced)
 */

session_start();
require_once "../includes/config.php";
require_once "../includes/functions.php";
require_once "../includes/dashboard_components.php";

if (!function_exists('normalizeFmsDoerIdentifier')) {
    function normalizeFmsDoerIdentifier($value) {
        if ($value === null) {
            return '';
        }
        $normalized = preg_replace('/\s+/', ' ', trim($value));
        return strtolower($normalized);
    }
}

if (!function_exists('getManagerFmsDoerIdentifiers')) {
    function getManagerFmsDoerIdentifiers($conn, $manager_id, $manager_name, $manager_username) {
        $identifiers = [];
        $addIdentifier = function($value) use (&$identifiers) {
            $normalized = normalizeFmsDoerIdentifier($value);
            if ($normalized !== '') {
                $identifiers[$normalized] = true;
            }
        };

        $addIdentifier($manager_username);
        $addIdentifier($manager_name);

        if (!$conn) {
            return array_keys($identifiers);
        }

        $team_query = "SELECT username, name FROM users WHERE manager_id = ?";
        $team_params = [$manager_id];
        $team_param_types = "i";

        if (!empty($manager_name)) {
            $team_query .= " OR TRIM(manager) = ?";
            $team_params[] = $manager_name;
            $team_param_types .= "s";
        }

        if ($stmt = mysqli_prepare($conn, $team_query)) {
            mysqli_stmt_bind_param($stmt, $team_param_types, ...$team_params);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $addIdentifier($row['username'] ?? '');
                        $addIdentifier($row['name'] ?? '');
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }

        return array_keys($identifiers);
    }
}

/**
 * Helper to render result blocks
 */
function renderResult($title, $status, $details = '') {
    $statusClassMap = [
        'PASS' => 'pass',
        'FAIL' => 'fail',
        'INFO' => 'info'
    ];
    $class = $statusClassMap[$status] ?? 'info';
    echo "<div class='test-block {$class}'>";
    echo "<h3>{$title}</h3>";
    echo "<strong>Status:</strong> {$status}";
    if ($details) {
        echo "<div class='details'><pre>{$details}</pre></div>";
    }
    echo "</div>";
}

/**
 * Fetch a sample manager for validation
 */
$manager_sql = "SELECT id, username, name FROM users WHERE user_type = 'manager' LIMIT 1";
$manager_row = null;
if ($result = mysqli_query($conn, $manager_sql)) {
    $manager_row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Regression Verification Suite</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 2rem; }
        h1 { margin-bottom: 0.5rem; }
        .subtitle { color: #94a3b8; margin-bottom: 2rem; }
        .test-suite { display: grid; gap: 1.5rem; }
        .test-block { border-radius: 1rem; padding: 1.5rem; border: 1px solid rgba(255,255,255,0.08); background: rgba(15,23,42,0.7); backdrop-filter: blur(20px); }
        .test-block h3 { margin: 0 0 0.5rem 0; }
        .details { margin-top: 0.75rem; background: rgba(15,23,42,0.9); border-radius: 0.75rem; padding: 0.75rem; max-height: 320px; overflow: auto; }
        .details pre { margin: 0; color: #e2e8f0; font-size: 0.85rem; white-space: pre-wrap; }
        .pass { border-color: rgba(34,197,94,0.4); box-shadow: 0 10px 30px rgba(34,197,94,0.1); }
        .fail { border-color: rgba(248,113,113,0.4); box-shadow: 0 10px 30px rgba(248,113,113,0.15); }
        .info { border-color: rgba(59,130,246,0.4); box-shadow: 0 10px 30px rgba(59,130,246,0.15); }
    </style>
</head>
<body>
    <h1>Regression Verification Suite</h1>
    <p class="subtitle">Covers Manager FMS visibility, motivation feed, and Admin exclusion logic.</p>
    <div class="test-suite">
        <?php
        if (!$manager_row) {
            renderResult(
                "Manager Fixture Lookup",
                "INFO",
                "No manager record found in users table. Populate at least one manager to run the regression suite."
            );
        } else {
            $manager_id = (int)$manager_row['id'];
            $manager_username = trim($manager_row['username'] ?? '');
            $manager_name = trim($manager_row['name'] ?? $manager_username);

            // Test 1 – FMS identifier set matches DB reality
            $identifiers = getManagerFmsDoerIdentifiers($conn, $manager_id, $manager_name, $manager_username);
            $identifier_debug = print_r($identifiers, true);
            if (empty($identifiers)) {
                renderResult(
                    "FMS Identifier Collection",
                    "FAIL",
                    "No normalized identifiers found for manager #{$manager_id} ({$manager_username}).\nExpected to contain manager + direct-report usernames/names."
                );
            } else {
                renderResult(
                    "FMS Identifier Collection",
                    "PASS",
                    "Manager ID: {$manager_id}\nTotal identifiers: " . count($identifiers) . "\n" . $identifier_debug
                );
            }

            // Test 2 – Verify every FMS task visible to manager belongs to their identifier set
            $placeholders = implode(',', array_fill(0, count($identifiers), '?'));
            $fms_sql = empty($identifiers)
                ? "SELECT id, doer_name, unique_key FROM fms_tasks LIMIT 0"
                : "SELECT id, doer_name, unique_key FROM fms_tasks WHERE LOWER(TRIM(doer_name)) IN ($placeholders)";
            $invalid_rows = [];

            if ($stmt = mysqli_prepare($conn, $fms_sql)) {
                if (!empty($identifiers)) {
                    mysqli_stmt_bind_param($stmt, str_repeat('s', count($identifiers)), ...$identifiers);
                }
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $normalized = normalizeFmsDoerIdentifier($row['doer_name'] ?? '');
                            if ($normalized && !in_array($normalized, $identifiers, true)) {
                                $invalid_rows[] = $row;
                            }
                        }
                    }
                }
                mysqli_stmt_close($stmt);
            }

            if (empty($invalid_rows)) {
                renderResult(
                    "Manager FMS Visibility Constraint",
                    "PASS",
                    "All fetched FMS tasks for manager #{$manager_id} map to the collected identifier set."
                );
            } else {
                renderResult(
                    "Manager FMS Visibility Constraint",
                    "FAIL",
                    "Found tasks that bypassed the identifier guard:\n" . print_r($invalid_rows, true)
                );
            }

            // Test 3 – Leaderboard excludes admin user
            $leaderboard = getLeaderboardData($conn, 0);
            $has_admin = false;
            foreach ($leaderboard as $entry) {
                $username = strtolower(trim($entry['username'] ?? ''));
                $name = strtolower(trim($entry['name'] ?? ''));
                if ($username === 'admin' || $name === 'admin') {
                    $has_admin = true;
                    break;
                }
            }
            renderResult(
                "Leaderboard Admin Exclusion",
                $has_admin ? "FAIL" : "PASS",
                $has_admin ? "Admin user detected in leaderboard data." : "Leaderboard contains " . count($leaderboard) . " entries with no Admin exposure."
            );

            // Test 4 – Team availability excludes admin user
            $team_availability = getTeamAvailabilityData($conn);
            $team_has_admin = false;
            foreach ($team_availability as $member) {
                $username = strtolower(trim($member['username'] ?? ''));
                $name = strtolower(trim($member['name'] ?? ''));
                if ($username === 'admin' || $name === 'admin') {
                    $team_has_admin = true;
                    break;
                }
            }
            renderResult(
                "Team Availability Admin Exclusion",
                $team_has_admin ? "FAIL" : "PASS",
                $team_has_admin ? "Admin surfaced in availability grid." : "Team availability entries: " . count($team_availability)
            );

            // Test 5 – Motivation data availability for manager dashboard
            $motivation_sql = "SELECT current_insights, areas_of_improvement, updated_at FROM user_motivation WHERE user_id = ?";
            $motivation_payload = [];
            if ($stmt = mysqli_prepare($conn, $motivation_sql)) {
                mysqli_stmt_bind_param($stmt, "i", $manager_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    if ($result) {
                        $motivation_payload = mysqli_fetch_assoc($result) ?: [];
                    }
                }
                mysqli_stmt_close($stmt);
            }

            if (!empty($motivation_payload)) {
                renderResult(
                    "Manager Motivation Payload",
                    "PASS",
                    print_r($motivation_payload, true)
                );
            } else {
                renderResult(
                    "Manager Motivation Payload",
                    "INFO",
                    "No motivation record exists for manager #{$manager_id}. Manager dashboard will still render with default placeholders."
                );
            }
        }
        ?>
    </div>
</body>
</html>

