<?php
/**
 * Team Performance Data API — Snapshot-Only
 *
 * Fetches aggregated performance data exclusively from the
 * performance_snapshots table. No live task recalculation.
 *
 * Parameters (GET):
 *   - username (required): Target user's username
 *   - weeks   (optional):  Number of past completed weeks to aggregate (1|2|4|8|12, default 1)
 */

if (ob_get_level() == 0) {
    ob_start();
} else {
    ob_clean();
}

require_once "../includes/config.php";
require_once "../includes/functions.php";
require_once "../includes/dashboard_components.php";

ini_set('display_errors', 0);
set_time_limit(120);

if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
}

if (!isLoggedIn()) {
    jsonError('Unauthorized', 401);
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
$target_username = isset($_GET['username']) ? trim($_GET['username']) : '';
$weeks = isset($_GET['weeks']) ? (int)$_GET['weeks'] : 1;

if (empty($target_username)) {
    jsonError('Username parameter is required', 400);
}
if (!in_array($weeks, [1, 2, 4, 8, 12])) {
    $weeks = 1;
}

try {
    // ── Get target user ──────────────────────────────────────────────
    $target_user = null;
    $sql_user = "SELECT id, username, name, user_type, manager_id, department_id FROM users WHERE username = ?";
    if ($stmt = mysqli_prepare($conn, $sql_user)) {
        mysqli_stmt_bind_param($stmt, "s", $target_username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $target_user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }

    if (!$target_user) {
        jsonError('User not found', 404);
    }

    // ── Access control ───────────────────────────────────────────────
    if (isAdmin()) {
        // OK
    } else if (isManager()) {
        if ($target_user['id'] != $current_user_id) {
            if ($target_user['user_type'] !== 'doer' || $target_user['manager_id'] != $current_user_id) {
                jsonError('Access denied', 403);
            }
        }
    } else if (isDoer()) {
        if ($target_user['id'] != $current_user_id) {
            jsonError('Access denied', 403);
        }
    } else {
        jsonError('Unauthorized', 401);
    }

    // ── Department name ──────────────────────────────────────────────
    $department_name = 'N/A';
    if (!empty($target_user['department_id'])) {
        $sql_dept = "SELECT name FROM departments WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_dept)) {
            mysqli_stmt_bind_param($stmt, "i", $target_user['department_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $department_name = $row['name'];
            }
            mysqli_stmt_close($stmt);
        }
    }

    // ── Ensure snapshots exist for the last N weeks ──────────────────
    ensureWeeksFrozen($conn, $weeks);

    // ── Fetch last N weekly snapshots for this user ──────────────────
    $sql_snap = "SELECT * FROM performance_snapshots
                 WHERE user_id = ?
                 ORDER BY week_start DESC
                 LIMIT ?";
    $snapshots = [];
    if ($stmt = mysqli_prepare($conn, $sql_snap)) {
        mysqli_stmt_bind_param($stmt, "ii", $target_user['id'], $weeks);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $snapshots[] = $row;
        }
        mysqli_stmt_close($stmt);
    }

    // Reverse to chronological order (oldest week first)
    $snapshots = array_reverse($snapshots);

    // ── Aggregate across all fetched snapshots ───────────────────────
    $total_tasks     = 0;
    $completed_tasks = 0;
    $pending_tasks   = 0;
    $delayed_tasks   = 0;
    $rqc_sum         = 0;
    $rqc_count       = 0;
    $perf_sum        = 0;

    $date_from = null;
    $date_to   = null;
    $weekly_trend = [];

    foreach ($snapshots as $s) {
        $total_tasks     += (int)$s['total_tasks'];
        $completed_tasks += (int)$s['completed_tasks'];
        $pending_tasks   += (int)$s['pending_tasks'];
        $delayed_tasks   += (int)$s['delayed_tasks'];

        $rqc_val = (float)$s['rqc_score'];
        if ($rqc_val > 0) {
            $rqc_sum += $rqc_val;
            $rqc_count++;
        }
        $perf_sum += (float)$s['performance_score'];

        if ($date_from === null || $s['week_start'] < $date_from) {
            $date_from = $s['week_start'];
        }
        if ($date_to === null || $s['week_end'] > $date_to) {
            $date_to = $s['week_end'];
        }

        $ws = new DateTime($s['week_start']);
        $we = new DateTime($s['week_end']);
        $weekly_trend[] = [
            'week'              => $ws->format('M d') . ' - ' . $we->format('M d'),
            'week_start'        => $s['week_start'],
            'week_end'          => $s['week_end'],
            'total_tasks'       => (int)$s['total_tasks'],
            'completed_tasks'   => (int)$s['completed_tasks'],
            'pending_tasks'     => (int)$s['pending_tasks'],
            'delayed_tasks'     => (int)$s['delayed_tasks'],
            'wnd'               => (float)$s['wnd'],
            'wnd_on_time'       => (float)$s['wnd_on_time'],
            'rqc'               => (float)$s['rqc_score'],
            'performance_score' => (float)$s['performance_score']
        ];
    }

    // Recalculate WND / WNDOT from summed counts (weighted, not averaged)
    $agg_wnd   = ($total_tasks > 0) ? max(-100, min(0, round(-1 * ($pending_tasks / $total_tasks) * 100, 2))) : -100;
    $agg_wndot = ($total_tasks > 0) ? max(-100, min(0, round(-1 * ($delayed_tasks / $total_tasks) * 100, 2))) : -100;
    $agg_rqc   = ($rqc_count > 0) ? round($rqc_sum / $rqc_count, 2) : 0;
    $agg_perf  = (count($snapshots) > 0) ? round($perf_sum / count($snapshots), 2) : 0;
    $completion_rate = ($total_tasks > 0) ? round(($completed_tasks / $total_tasks) * 100, 2) : 0;

    // ── Build response ───────────────────────────────────────────────
    $response = [
        'success' => true,
        'data' => [
            'user' => [
                'id'        => $target_user['id'],
                'username'  => $target_user['username'],
                'name'      => $target_user['name'],
                'department' => $department_name,
                'user_type' => $target_user['user_type']
            ],
            'date_range' => [
                'from' => $date_from,
                'to'   => $date_to
            ],
            'stats' => [
                'total_tasks'       => $total_tasks,
                'completed_on_time' => $completed_tasks,
                'current_pending'   => $pending_tasks,
                'current_delayed'   => $delayed_tasks,
                'all_delayed_tasks' => $delayed_tasks,
                'wnd'               => $agg_wnd,
                'wnd_on_time'       => $agg_wndot
            ],
            'rqc_score'        => $agg_rqc,
            'performance_score' => $agg_perf,
            'completion_rate'  => $completion_rate,
            'weekly_trend'     => $weekly_trend,
            'weeks_available'  => count($snapshots),
            'weeks_requested'  => $weeks
        ]
    ];

    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode($response);

} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    handleException($e, 'team_performance_data');
} catch (Error $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine()
    ]);
}

if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
