<?php
/**
 * Shared Dashboard Components
 * Reusable functions for manager and admin dashboards
 */

// Helper functions (declared at file level to prevent redeclaration)

/**
 * Check if planned date/time is in the past
 */
if (!function_exists('isPastPlannedDateTime')) {
    function isPastPlannedDateTime($planned_date, $planned_time) {
        if (empty($planned_date)) return false;
        $planned_datetime = $planned_date;
        if (!empty($planned_time)) {
            $planned_datetime .= ' ' . $planned_time;
        } else {
            $planned_datetime .= ' 23:59:59';
        }
        $planned_timestamp = strtotime($planned_datetime);
        return $planned_timestamp !== false && $planned_timestamp < time();
    }
}

/**
 * Check if actual date/time is after planned date/time
 */
if (!function_exists('isActualAfterPlanned')) {
    function isActualAfterPlanned($planned_date, $planned_time, $actual_date, $actual_time) {
        if (empty($planned_date) || empty($actual_date)) return false;
        $planned_datetime = $planned_date;
        if (!empty($planned_time)) {
            $planned_datetime .= ' ' . $planned_time;
        } else {
            $planned_datetime .= ' 23:59:59';
        }
        $actual_datetime = $actual_date;
        if (!empty($actual_time)) {
            $actual_datetime .= ' ' . $actual_time;
        } else {
            $actual_datetime .= ' 23:59:59';
        }
        $planned_timestamp = strtotime($planned_datetime);
        $actual_timestamp = strtotime($actual_datetime);
        return $planned_timestamp !== false && $actual_timestamp !== false && $actual_timestamp > $planned_timestamp;
    }
}

/**
 * Check if task should be excluded from WND calculation
 */
if (!function_exists('shouldExcludeFromWND')) {
    function shouldExcludeFromWND($status, $planned_date, $planned_time, $actual_date = null, $task_type = 'delegation') {
        $status = strtolower($status ?? '');
        
        if ($task_type === 'fms') {
            if (in_array($status, ['completed', 'done', 'not done', 'can not be done'])) {
                return true;
            }
        } else {
            if (in_array($status, ['completed', 'done'])) {
                return true;
            }
        }
        
        if ($status === 'shifted') {
            if (!empty($actual_date) && !empty($planned_date)) {
                $actual_timestamp = strtotime($actual_date . ' 23:59:59');
                $planned_datetime = $planned_date;
                if (!empty($planned_time)) {
                    $planned_datetime .= ' ' . $planned_time;
                } else {
                    $planned_datetime .= ' 23:59:59';
                }
                $planned_timestamp = strtotime($planned_datetime);
                
                if ($planned_timestamp !== false && $actual_timestamp !== false && $actual_timestamp < $planned_timestamp) {
                    return true;
                }
            }
            if (empty($actual_date) && !isPastPlannedDateTime($planned_date, $planned_time)) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * Check if task falls within date range
 */
if (!function_exists('isTaskInDateRange')) {
    function isTaskInDateRange($planned_date, $actual_date, $date_from, $date_to) {
        if (!$date_from || !$date_to) {
            return true;
        }
        
        $from_ts = strtotime($date_from . ' 00:00:00');
        $to_ts = strtotime($date_to . ' 23:59:59');
        
        if (!empty($planned_date)) {
            $planned_ts = strtotime($planned_date . ' 00:00:00');
            if ($planned_ts >= $from_ts && $planned_ts <= $to_ts) {
                return true;
            }
        }
        
        if (!empty($actual_date)) {
            $actual_ts = strtotime($actual_date . ' 00:00:00');
            if ($actual_ts >= $from_ts && $actual_ts <= $to_ts) {
                return true;
            }
        }
        
        return false;
    }
}

if (!function_exists('normalizeTaskStatus')) {
    function normalizeTaskStatus($status) {
        return strtolower(trim($status ?? ''));
    }
}

if (!function_exists('isStatusCantBeDone')) {
    function isStatusCantBeDone($status) {
        $normalized = normalizeTaskStatus($status);
        return in_array($normalized, ["can't be done", "can not be done", "cant be done"], true);
    }
}

if (!function_exists('isStatusCompleted')) {
    function isStatusCompleted($status) {
        $normalized = normalizeTaskStatus($status);
        return in_array($normalized, ['completed', 'done'], true);
    }
}

if (!function_exists('isStatusPending')) {
    function isStatusPending($status) {
        return normalizeTaskStatus($status) === 'pending';
    }
}

if (!function_exists('isStatusShifted')) {
    function isStatusShifted($status) {
        return normalizeTaskStatus($status) === 'shifted';
    }
}

if (!function_exists('buildDateTimeTimestamp')) {
    function buildDateTimeTimestamp($date, $time = null) {
        if (empty($date)) {
            return null;
        }
        $time_part = ($time !== null && $time !== '') ? $time : '23:59:59';
        $timestamp = strtotime(trim($date) . ' ' . trim($time_part));
        return $timestamp !== false ? $timestamp : null;
    }
}

if (!function_exists('classifyTaskForStats')) {
    function classifyTaskForStats($status, $planned_date, $planned_time, $actual_date, $actual_time, $now = null, $task_type = 'delegation') {
        $normalized_status = normalizeTaskStatus($status);
        if (isStatusCantBeDone($normalized_status)) {
            return ['skip' => true];
        }
        
        $planned_ts = buildDateTimeTimestamp($planned_date, $planned_time);
        $actual_ts = buildDateTimeTimestamp($actual_date, $actual_time);
        $now = $now ?? time();
        
        $classification = [
            'skip' => false,
            'status' => $normalized_status,
            'planned_ts' => $planned_ts,
            'actual_ts' => $actual_ts,
            'is_completed' => false,
            'is_completed_late' => false,
            'is_pending' => false,
            'is_delayed' => false
        ];
        
        // Check completed: standard statuses + FMS "yes"
        $is_completed_status = isStatusCompleted($normalized_status);
        if (!$is_completed_status && $task_type === 'fms' && $normalized_status === 'yes') {
            $is_completed_status = true;
        }
        
        // A task is truly completed only if it has a completed status AND has actual data
        // This matches manage_tasks logic where actual data is required
        $has_actual_data = ($actual_ts !== null);
        $is_completed = $is_completed_status && $has_actual_data;
        
        // "not done" is always treated as pending (matches manage_tasks)
        $is_not_done = in_array($normalized_status, ['not done', 'notdone']);
        
        if ($is_completed) {
            $classification['is_completed'] = true;
            if ($actual_ts !== null && $planned_ts !== null && $actual_ts > $planned_ts) {
                $classification['is_completed_late'] = true;
            }
            return $classification;
        }
        
        $is_pending_status = isStatusPending($normalized_status) || $is_not_done;
        $is_shifted_status = isStatusShifted($normalized_status);
        
        if ($is_pending_status && ($planned_ts === null || $planned_ts >= $now)) {
            $classification['is_pending'] = true;
        }
        
        if (($is_pending_status || $is_shifted_status) && $planned_ts !== null && $planned_ts < $now) {
            $classification['is_delayed'] = true;
        }
        
        return $classification;
    }
}

if (!function_exists('parseFmsTimestampUniversal')) {
    function parseFmsTimestampUniversal($value) {
        if (empty($value)) {
            return null;
        }
        
        $parsers = [
            'parseFMSDateTimeString_doer',
            'parseFMSDateTimeString_my_task',
            'parseFMSDateTimeString_manage'
        ];
        
        foreach ($parsers as $parser) {
            if (function_exists($parser)) {
                $timestamp = $parser($value);
                if ($timestamp) {
                    return $timestamp;
                }
            }
        }
        
        $fallback = strtotime($value);
        return $fallback !== false ? $fallback : null;
    }
}

/**
 * Check if a date is within the specified range
 */
if (!function_exists('isDateInRange')) {
    function isDateInRange($date, $date_from, $date_to) {
        if (!$date_from || !$date_to || empty($date)) {
            return false;
        }
        
        $date_ts = strtotime($date . ' 00:00:00');
        $from_ts = strtotime($date_from . ' 00:00:00');
        $to_ts = strtotime($date_to . ' 23:59:59');
        
        return ($date_ts >= $from_ts && $date_ts <= $to_ts);
    }
}

/**
 * Calculate personal stats for a user (like doer dashboard)
 * Following exact specifications:
 * 1. Completed Tasks: Count tasks WHERE planned_date IN range AND status="Completed" AND actual_date BETWEEN selected_start AND selected_end
 * 2. Pending Tasks: Count tasks WHERE planned_date IN range AND (actual_date IS NULL OR actual_date NOT BETWEEN selected_start AND selected_end)
 * 3. Delayed: Count tasks WHERE planned_date BETWEEN selected_start AND selected_end AND current_datetime > planned_datetime
 *    (excludes completed-early tasks)
 * 4. WND: (Total Pending / Total Planned in range) * 100  — returns -100% if denominator = 0
 * 5. WND On-Time (WNDOT): (Total Delayed / Total Tasks planned in range) * 100  — returns -100% if denominator = 0
 */
function calculatePersonalStats($conn, $user_id, $username, $date_from = null, $date_to = null) {
    static $delay_status_updated = false;
    
    $stats = [
        'completed_on_time' => 0,  // Completed Tasks: status=Completed AND actual_date in selected range
        'current_pending' => 0,     // Pending Tasks: planned_date in range AND (actual_date IS NULL OR actual_date NOT in range)
        'current_delayed' => 0,     // Delayed: planned_date in range AND now > planned_datetime (excl. completed early)
        'total_tasks' => 0,         // Total tasks planned IN range
        'total_tasks_all' => 0,     // All tasks EXCEPT "can't be done"
        'shifted_tasks' => 0,       // Tasks with status = 'Shifted' OR status = '🔁'
        'wnd' => -100,              // (Pending / Total Planned in range) * 100; -100% if no planned tasks
        'wnd_on_time' => -100       // (Delayed / Total Planned in range) * 100; -100% if no planned tasks
    ];
    
    // Trackers for calculations
    $total_tasks_excluding_cant_be_done = 0;
    $completed_tasks_in_range = 0;  // Tasks with status=Completed AND actual_date in selected range
    $pending_tasks = 0;  // Tasks where planned_date in range AND (actual_date IS NULL OR actual_date NOT in range)
    $total_tasks_planned_in_range = 0;  // Total tasks where planned_date IN range (WND/WNDOT denominator)
    $delayed_tasks_count = 0;  // Tasks where planned_date in range AND now > planned_datetime (excl. completed early)
    $shifted_tasks_count = 0;  // Tasks with status = 'Shifted' OR status = '🔁'
    
    require_once __DIR__ . '/functions.php';
    // Only update delay status once per request (avoid N+1 calls for leaderboard)
    if (!$delay_status_updated) {
        updateAllTasksDelayStatus($conn);
        $delay_status_updated = true;
    }
    
    // Determine the week to use for calculations
    // If date_from is provided, use that week; otherwise use current week
    $week_start = null;
    if (!empty($date_from)) {
        $week_start = getWeekStartForDate($date_from);
    } else {
        $week_start = getWeekStartForDate(date('Y-m-d'));
    }
    
    $now = time();
    
    // Process each task
    $processTask = function($status, $planned_date, $planned_time, $actual_date, $actual_time, $task_type = 'delegation') use (
        $date_from,
        $date_to,
        $week_start,
        &$total_tasks_excluding_cant_be_done,
        &$completed_tasks_in_range,
        &$pending_tasks,
        &$total_tasks_planned_in_range,
        &$delayed_tasks_count,
        &$shifted_tasks_count,
        $now
    ) {
        $normalized_status = normalizeTaskStatus($status);
        
        // Count shifted tasks
        if ($normalized_status === 'shifted' || $status === '🔁') {
            $shifted_tasks_count++;
        }
        
        // Check if status is "can't be done" - skip these
        if (isStatusCantBeDone($normalized_status)) {
            return;
        }
        
        $total_tasks_excluding_cant_be_done++;
        
        // Check if we have a date range (if not, it's "lifetime" - process all tasks)
        $has_date_range = ($date_from !== null && $date_to !== null);
        
        // Check if planned_date is in range (or all if no range)
        $planned_in_range = $has_date_range ? isDateInRange($planned_date, $date_from, $date_to) : true;
        
        // Check if actual_date is in range (or all if no range)
        $actual_in_range = $has_date_range ? isDateInRange($actual_date, $date_from, $date_to) : true;
        
        // Check if task is completed (status-wise)
        $is_completed_status = isStatusCompleted($normalized_status);
        // FMS tasks may use "yes" as a completed status
        if (!$is_completed_status && $task_type === 'fms' && $normalized_status === 'yes') {
            $is_completed_status = true;
        }
        
        // A task is truly completed only if it has a completed status AND has actual_date/time
        // This matches manage_tasks logic where actual data is required
        $has_actual_data = !empty($actual_date) || !empty($actual_time);
        $is_completed = $is_completed_status && $has_actual_data;
        
        // "not done" status is always treated as pending (matches manage_tasks)
        $is_not_done = in_array($normalized_status, ['not done', 'notdone']);
        
        // ---------------------------------------------------------------
        // 1. Completed Tasks
        //    a) actual_date IN range → completed
        //    b) actual_date OUTSIDE range but done early (actual <= planned) → completed
        //    c) actual_date OUTSIDE range but done late  (actual >  planned) → pending
        // ---------------------------------------------------------------
        if ($is_completed && $planned_in_range) {
            if (!$has_date_range) {
                $completed_tasks_in_range++;
            } else if ($actual_in_range) {
                $completed_tasks_in_range++;
            } else {
                // Completed but actual_date outside selected range
                $planned_ts_cmp = buildDateTimeTimestamp($planned_date, $planned_time);
                $actual_ts_cmp  = buildDateTimeTimestamp($actual_date, $actual_time);
                if ($planned_ts_cmp !== null && $actual_ts_cmp !== null && $actual_ts_cmp <= $planned_ts_cmp) {
                    // Done early → still counts as completed
                    $completed_tasks_in_range++;
                } else {
                    // Done late → treat as pending for this date range
                    $pending_tasks++;
                }
            }
        }
        
        // ---------------------------------------------------------------
        // Count tasks planned in range (for Total Tasks / WND / WNDOT denominator)
        // ---------------------------------------------------------------
        if ($planned_in_range) {
            $total_tasks_planned_in_range++;
        }
        
        // ---------------------------------------------------------------
        // 2. Pending Tasks
        //    - "not done" status is always pending (matches manage_tasks)
        //    - Otherwise: no actual data = pending
        //    (completed-out-of-range late tasks are already handled above)
        // ---------------------------------------------------------------
        if ($planned_in_range) {
            if ($is_not_done) {
                $pending_tasks++;
            } else if (!$is_completed) {
                if (empty($actual_date) && empty($actual_time)) {
                    $pending_tasks++;
                }
            }
        }
        
        // ---------------------------------------------------------------
        // 3. Delayed Tasks
        //    Conditions (ALL must be true):
        //      a) planned_date BETWEEN selected_start AND selected_end
        //      b) current_datetime > planned_datetime
        //    Exclusion: completed-early tasks (actual < planned) are NOT delayed
        // ---------------------------------------------------------------
        if (!empty($planned_date) && $planned_in_range) {
            $planned_ts = buildDateTimeTimestamp($planned_date, $planned_time);
            
            if ($planned_ts !== null && $now > $planned_ts) {
                // Now is past the planned datetime — potential delay
                // Exclude tasks completed on time or early (actual <= planned)
                $completed_early = false;
                if ($is_completed && !empty($actual_date)) {
                    $actual_ts = buildDateTimeTimestamp($actual_date, $actual_time);
                    if ($actual_ts !== null && $actual_ts <= $planned_ts) {
                        $completed_early = true;
                    }
                }
                
                if (!$completed_early) {
                    $delayed_tasks_count++;
                }
            }
        }
    };
    
    // Fetch Delegation Tasks
    $delegation_query = "SELECT status, planned_date, planned_time, actual_date, actual_time
                        FROM tasks WHERE doer_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $delegation_query)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $processTask(
                    $row['status'] ?? '',
                    $row['planned_date'] ?? '',
                    $row['planned_time'] ?? '',
                    $row['actual_date'] ?? '',
                    $row['actual_time'] ?? '',
                    'delegation'
                );
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Fetch FMS Tasks
    $fms_query = "SELECT status, planned, actual 
                      FROM fms_tasks WHERE doer_name = ?";
        if ($stmt = mysqli_prepare($conn, $fms_query)) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $status = $row['status'] ?? '';
                
                $planned_timestamp = parseFmsTimestampUniversal($row['planned'] ?? '');
                $actual_timestamp = parseFmsTimestampUniversal($row['actual'] ?? '');
                    
                    $planned_date = $planned_timestamp ? date('Y-m-d', $planned_timestamp) : '';
                    $planned_time = $planned_timestamp ? date('H:i:s', $planned_timestamp) : '';
                    $actual_date = $actual_timestamp ? date('Y-m-d', $actual_timestamp) : '';
                    $actual_time = $actual_timestamp ? date('H:i:s', $actual_timestamp) : '';
                    
                $processTask($status, $planned_date, $planned_time, $actual_date, $actual_time, 'fms');
                }
            }
            mysqli_stmt_close($stmt);
    }
    
    // Fetch Checklist Tasks - fetch all tasks for the user to allow proper date range filtering
    $checklist_query = "SELECT status, is_delayed, delay_duration, task_date, actual_date, actual_time, frequency
                       FROM checklist_subtasks 
                       WHERE assignee = ?";
    
    if ($stmt = mysqli_prepare($conn, $checklist_query)) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $processTask(
                    $row['status'] ?? 'pending',
                    $row['task_date'] ?? '',
                    '23:59:59',
                    $row['actual_date'] ?? '',
                    $row['actual_time'] ?? '',
                    'checklist'
                );
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Set final stats
    // 1. Completed Tasks: status=Completed AND actual_date in selected range
    $stats['completed_on_time'] = $completed_tasks_in_range;
    
    // 2. Pending Tasks: actual_date IS NULL OR actual_date NOT in selected range
    $stats['current_pending'] = $pending_tasks;
    
    // 3. Delayed: planned_date in range AND now > planned_datetime (excl. completed early)
    $stats['current_delayed'] = $delayed_tasks_count;
    
    // Total tasks planned IN range (WND/WNDOT denominator)
    $stats['total_tasks'] = $total_tasks_planned_in_range;
    
    // Total tasks all (excluding "can't be done")
    $stats['total_tasks_all'] = $total_tasks_excluding_cant_be_done;
    
    // Shifted tasks count
    $stats['shifted_tasks'] = $shifted_tasks_count;
    
    // 4. WND = -1 * (Total Pending / Total Planned in range) * 100
    //    If Total Planned = 0, return -100% (never N/A)
    //    Capped to range [-100%, 0%] — Pending is scoped to planned_in_range so should never exceed Total
    if ($total_tasks_planned_in_range > 0) {
        $wnd_raw = round(-1 * ($pending_tasks / $total_tasks_planned_in_range) * 100, 2);
        $stats['wnd'] = max(-100, min(0, $wnd_raw));
    } else {
        $stats['wnd'] = -100;
    }
    
    // 5. WNDOT = -1 * (Total Delayed / Total Tasks planned in range) * 100
    //    If Total Tasks = 0, return -100% (never N/A)
    //    Capped to range [-100%, 0%] — Delayed is scoped to planned_in_range so should never exceed Total
    if ($total_tasks_planned_in_range > 0) {
        $wndot_raw = round(-1 * ($delayed_tasks_count / $total_tasks_planned_in_range) * 100, 2);
        $stats['wnd_on_time'] = max(-100, min(0, $wndot_raw));
    } else {
        $stats['wnd_on_time'] = -100;
    }
    
    return $stats;
}

/**
 * Get frozen snapshot stats for a user and date range.
 * Returns snapshot data if ALL weeks in the requested range are frozen,
 * otherwise returns null (caller should fall back to live calculation).
 * 
 * For a single-week request (Mon→Sun), returns the snapshot directly.
 * For multi-week ranges, aggregates all frozen weekly snapshots.
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $date_from Start date (Y-m-d)
 * @param string $date_to End date (Y-m-d)
 * @return array|null Snapshot data or null if not fully frozen
 */
function getSnapshotStats($conn, $user_id, $date_from, $date_to) {
    if (empty($date_from) || empty($date_to)) {
        return null; // No date range = lifetime → always live
    }
    
    // Check if the requested range has ended (all dates are in the past)
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $range_end = new DateTime($date_to);
    $range_end->setTime(23, 59, 59);
    
    if ($range_end >= $today) {
        return null; // Range includes today or future → always live
    }
    
    // Find Monday of the first week and Sunday of the last week in the range
    $range_start_dt = new DateTime($date_from);
    $start_day = (int)$range_start_dt->format('N');
    $range_monday = clone $range_start_dt;
    $range_monday->modify('-' . ($start_day - 1) . ' days');
    
    $range_end_dt = new DateTime($date_to);
    $end_day = (int)$range_end_dt->format('N');
    $range_sunday = clone $range_end_dt;
    $range_sunday->modify('+' . (7 - $end_day) . ' days');
    
    // Query all snapshots for this user within the week range
    $sql = "SELECT * FROM performance_snapshots 
            WHERE user_id = ? AND week_start >= ? AND week_end <= ?
            ORDER BY week_start ASC";
    
    $snapshots = [];
    if ($stmt = mysqli_prepare($conn, $sql)) {
        $monday_str = $range_monday->format('Y-m-d');
        $sunday_str = $range_sunday->format('Y-m-d');
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $monday_str, $sunday_str);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $snapshots[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    
    if (empty($snapshots)) {
        return null; // No snapshots found → fall back to live
    }
    
    // Check that every week in the range is covered
    $expected_weeks = 0;
    $check_monday = clone $range_monday;
    while ($check_monday <= $range_end_dt) {
        $expected_weeks++;
        $check_monday->modify('+7 days');
    }
    
    if (count($snapshots) < $expected_weeks) {
        return null; // Not all weeks frozen → fall back to live for consistency
    }
    
    // Aggregate snapshots
    if (count($snapshots) === 1) {
        // Single week — return directly
        $s = $snapshots[0];
        return [
            'completed_on_time' => (int)$s['completed_tasks'],
            'current_pending' => (int)$s['pending_tasks'],
            'current_delayed' => (int)$s['delayed_tasks'],
            'total_tasks' => (int)$s['total_tasks'],
            'wnd' => (float)$s['wnd'],
            'wnd_on_time' => (float)$s['wnd_on_time'],
            'rqc_score' => (float)$s['rqc_score'],
            'performance_score' => (float)$s['performance_score'],
            'from_snapshot' => true
        ];
    }
    
    // Multi-week: sum counts, average percentages
    $total_tasks = 0;
    $completed = 0;
    $pending = 0;
    $delayed = 0;
    $wnd_sum = 0;
    $wndot_sum = 0;
    $rqc_sum = 0;
    $perf_sum = 0;
    $count = count($snapshots);
    
    foreach ($snapshots as $s) {
        $total_tasks += (int)$s['total_tasks'];
        $completed += (int)$s['completed_tasks'];
        $pending += (int)$s['pending_tasks'];
        $delayed += (int)$s['delayed_tasks'];
        $wnd_sum += (float)$s['wnd'];
        $wndot_sum += (float)$s['wnd_on_time'];
        $rqc_sum += (float)$s['rqc_score'];
        $perf_sum += (float)$s['performance_score'];
    }
    
    // Recalculate WND and WNDOT from aggregated counts for accuracy
    $agg_wnd = ($total_tasks > 0) ? max(-100, min(0, round(-1 * ($pending / $total_tasks) * 100, 2))) : -100;
    $agg_wndot = ($total_tasks > 0) ? max(-100, min(0, round(-1 * ($delayed / $total_tasks) * 100, 2))) : -100;
    
    return [
        'completed_on_time' => $completed,
        'current_pending' => $pending,
        'current_delayed' => $delayed,
        'total_tasks' => $total_tasks,
        'wnd' => $agg_wnd,
        'wnd_on_time' => $agg_wndot,
        'rqc_score' => round($rqc_sum / $count, 2),
        'performance_score' => round($perf_sum / $count, 2),
        'from_snapshot' => true
    ];
}

/**
 * Smart stats: returns frozen snapshot if available, otherwise live calculation.
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $username Username
 * @param string $user_name Display name (for RQC lookup)
 * @param string|null $date_from Start date
 * @param string|null $date_to End date
 * @return array Stats array (same shape as calculatePersonalStats output)
 */
function getPerformanceStats($conn, $user_id, $username, $user_name, $date_from = null, $date_to = null) {
    // One-time purge: clear stale snapshots frozen before the delay-logic fix (v2)
    // After purge, lazyFreezeLastWeek will recreate them with corrected logic
    static $purge_checked = false;
    if (!$purge_checked) {
        $purge_checked = true;
        $marker_file = __DIR__ . '/../sessions/.snapshot_purge_v2';
        if (!file_exists($marker_file)) {
            mysqli_query($conn, "TRUNCATE TABLE performance_snapshots");
            @file_put_contents($marker_file, date('Y-m-d H:i:s') . " purged stale snapshots (delay-logic fix v2)\n");
        }
    }

    // Try snapshot first (only works for fully-past date ranges)
    $snapshot = getSnapshotStats($conn, $user_id, $date_from, $date_to);
    if ($snapshot !== null) {
        return $snapshot;
    }
    
    // Fall back to live calculation
    $stats = calculatePersonalStats($conn, $user_id, $username, $date_from, $date_to);
    if (!is_array($stats)) {
        $stats = [
            'completed_on_time' => 0,
            'current_pending' => 0,
            'current_delayed' => 0,
            'total_tasks' => 0,
            'wnd' => -100,
            'wnd_on_time' => -100
        ];
    }
    
    // Attach RQC and performance score for convenience
    $rqc = getRqcScore($conn, $user_name, $date_from, $date_to);
    $rqc_val = is_numeric($rqc) ? floatval($rqc) : 0;
    $stats['rqc_score'] = $rqc_val;
    $stats['performance_score'] = calculatePerformanceRate($rqc_val, $stats['wnd'], $stats['wnd_on_time']);
    $stats['from_snapshot'] = false;
    
    return $stats;
}

/**
 * Trigger lazy freeze: freezes the last completed week if not already frozen.
 * Designed to be called on page load — fast no-op if already frozen.
 * Self-contained: does not require the freeze script to avoid circular dependencies.
 * 
 * @param mysqli $conn Database connection
 * @return bool True if freeze was performed, false if already frozen or skipped
 */
function lazyFreezeLastWeek($conn) {
    // Ensure table exists
    $create_sql = "CREATE TABLE IF NOT EXISTS performance_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        user_name VARCHAR(100) NOT NULL,
        week_start DATE NOT NULL,
        week_end DATE NOT NULL,
        total_tasks INT NOT NULL DEFAULT 0,
        completed_tasks INT NOT NULL DEFAULT 0,
        pending_tasks INT NOT NULL DEFAULT 0,
        delayed_tasks INT NOT NULL DEFAULT 0,
        wnd DECIMAL(7,2) NOT NULL DEFAULT -100,
        wnd_on_time DECIMAL(7,2) NOT NULL DEFAULT -100,
        rqc_score DECIMAL(7,2) NOT NULL DEFAULT 0,
        performance_score DECIMAL(7,2) NOT NULL DEFAULT 0,
        frozen_at DATETIME NOT NULL,
        UNIQUE KEY unique_user_week (user_id, week_start),
        INDEX idx_week (week_start, week_end),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    mysqli_query($conn, $create_sql);
    
    // Get last completed week's Monday
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $dayOfWeek = (int)$today->format('N');
    
    $thisMonday = clone $today;
    $thisMonday->modify('-' . ($dayOfWeek - 1) . ' days');
    
    $lastMonday = clone $thisMonday;
    $lastMonday->modify('-7 days');
    $lastSunday = clone $lastMonday;
    $lastSunday->modify('+6 days');
    
    $week_start_str = $lastMonday->format('Y-m-d');
    $week_end_str = $lastSunday->format('Y-m-d');
    
    // Quick check: is it already frozen? (single fast query)
    $check_sql = "SELECT COUNT(*) as cnt FROM performance_snapshots WHERE week_start = ?";
    $already_frozen = false;
    if ($stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($stmt, "s", $week_start_str);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $already_frozen = ($row && $row['cnt'] > 0);
        mysqli_stmt_close($stmt);
    }
    
    if ($already_frozen) {
        return false; // Already done — fast path
    }
    
    // Need to freeze — get all users and snapshot their stats
    $frozen_at = date('Y-m-d H:i:s');
    $users_sql = "SELECT id, username, name, user_type FROM users WHERE user_type IN ('admin', 'manager', 'doer')";
    $users_result = mysqli_query($conn, $users_sql);
    if (!$users_result) {
        return false;
    }
    
    $frozen_count = 0;
    while ($user = mysqli_fetch_assoc($users_result)) {
        // Calculate stats for the week
        $stats = calculatePersonalStats($conn, $user['id'], $user['username'], $week_start_str, $week_end_str);
        if (!is_array($stats)) continue;
        
        $rqc = getRqcScore($conn, $user['name'], $week_start_str, $week_end_str);
        $rqc_val = is_numeric($rqc) ? floatval($rqc) : 0;
        $perf = calculatePerformanceRate($rqc_val, $stats['wnd'] ?? -100, $stats['wnd_on_time'] ?? -100);
        
        $insert_sql = "INSERT IGNORE INTO performance_snapshots 
            (user_id, username, user_name, week_start, week_end, 
             total_tasks, completed_tasks, pending_tasks, delayed_tasks,
             wnd, wnd_on_time, rqc_score, performance_score, frozen_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($ins = mysqli_prepare($conn, $insert_sql)) {
            $total = $stats['total_tasks'] ?? 0;
            $completed = $stats['completed_on_time'] ?? 0;
            $pending = $stats['current_pending'] ?? 0;
            $delayed = $stats['current_delayed'] ?? 0;
            $wnd = $stats['wnd'] ?? -100;
            $wndot = $stats['wnd_on_time'] ?? -100;
            
            mysqli_stmt_bind_param($ins, "issssiiiiiddds",
                $user['id'], $user['username'], $user['name'],
                $week_start_str, $week_end_str,
                $total, $completed, $pending, $delayed,
                $wnd, $wndot, $rqc_val, $perf, $frozen_at
            );
            if (mysqli_stmt_execute($ins)) {
                $frozen_count++;
            }
            mysqli_stmt_close($ins);
        }
    }
    
    return ($frozen_count > 0);
}

/**
 * Ensure the last N completed weeks are frozen in performance_snapshots.
 * Checks which weeks already exist and only freezes missing ones.
 * Called by the performance data endpoint before fetching snapshots.
 *
 * @param mysqli $conn Database connection
 * @param int $num_weeks Number of past completed weeks to ensure (1, 2, 4, 8, 12)
 */
function ensureWeeksFrozen($conn, $num_weeks) {
    $create_sql = "CREATE TABLE IF NOT EXISTS performance_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        user_name VARCHAR(100) NOT NULL,
        week_start DATE NOT NULL,
        week_end DATE NOT NULL,
        total_tasks INT NOT NULL DEFAULT 0,
        completed_tasks INT NOT NULL DEFAULT 0,
        pending_tasks INT NOT NULL DEFAULT 0,
        delayed_tasks INT NOT NULL DEFAULT 0,
        wnd DECIMAL(7,2) NOT NULL DEFAULT -100,
        wnd_on_time DECIMAL(7,2) NOT NULL DEFAULT -100,
        rqc_score DECIMAL(7,2) NOT NULL DEFAULT 0,
        performance_score DECIMAL(7,2) NOT NULL DEFAULT 0,
        frozen_at DATETIME NOT NULL,
        UNIQUE KEY unique_user_week (user_id, week_start),
        INDEX idx_week (week_start, week_end),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    mysqli_query($conn, $create_sql);

    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $dayOfWeek = (int)$today->format('N');
    $thisMonday = clone $today;
    $thisMonday->modify('-' . ($dayOfWeek - 1) . ' days');

    $weeks_needed = [];
    for ($i = 1; $i <= $num_weeks; $i++) {
        $monday = clone $thisMonday;
        $monday->modify('-' . ($i * 7) . ' days');
        $sunday = clone $monday;
        $sunday->modify('+6 days');
        $weeks_needed[] = [
            'start' => $monday->format('Y-m-d'),
            'end'   => $sunday->format('Y-m-d')
        ];
    }

    if (empty($weeks_needed)) return;

    $week_starts = array_map(function($w) { return $w['start']; }, $weeks_needed);
    $placeholders = implode(',', array_fill(0, count($week_starts), '?'));
    $types = str_repeat('s', count($week_starts));

    $existing_weeks = [];
    $sql = "SELECT DISTINCT week_start FROM performance_snapshots WHERE week_start IN ($placeholders)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, $types, ...$week_starts);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $existing_weeks[] = $row['week_start'];
        }
        mysqli_stmt_close($stmt);
    }

    $missing_weeks = array_filter($weeks_needed, function($w) use ($existing_weeks) {
        return !in_array($w['start'], $existing_weeks);
    });

    if (empty($missing_weeks)) return;

    $users_sql = "SELECT id, username, name FROM users WHERE user_type IN ('admin', 'manager', 'doer')";
    $users_result = mysqli_query($conn, $users_sql);
    if (!$users_result) return;

    $users = [];
    while ($user = mysqli_fetch_assoc($users_result)) {
        $users[] = $user;
    }

    $frozen_at = date('Y-m-d H:i:s');

    foreach ($missing_weeks as $week) {
        foreach ($users as $user) {
            $stats = calculatePersonalStats($conn, $user['id'], $user['username'], $week['start'], $week['end']);
            if (!is_array($stats)) continue;

            $rqc = getRqcScore($conn, $user['name'], $week['start'], $week['end']);
            $rqc_val = is_numeric($rqc) ? floatval($rqc) : 0;
            $perf = calculatePerformanceRate($rqc_val, $stats['wnd'] ?? -100, $stats['wnd_on_time'] ?? -100);

            $insert_sql = "INSERT IGNORE INTO performance_snapshots
                (user_id, username, user_name, week_start, week_end,
                 total_tasks, completed_tasks, pending_tasks, delayed_tasks,
                 wnd, wnd_on_time, rqc_score, performance_score, frozen_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            if ($ins = mysqli_prepare($conn, $insert_sql)) {
                $total    = $stats['total_tasks'] ?? 0;
                $completed = $stats['completed_on_time'] ?? 0;
                $pending  = $stats['current_pending'] ?? 0;
                $delayed  = $stats['current_delayed'] ?? 0;
                $wnd      = $stats['wnd'] ?? -100;
                $wndot    = $stats['wnd_on_time'] ?? -100;

                mysqli_stmt_bind_param($ins, "issssiiiiiddds",
                    $user['id'], $user['username'], $user['name'],
                    $week['start'], $week['end'],
                    $total, $completed, $pending, $delayed,
                    $wnd, $wndot, $rqc_val, $perf, $frozen_at
                );
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
            }
        }
    }
}

/**
 * Calculate aggregated task stats across the system (optionally filtered by date range)
 */
function calculateGlobalTaskStats($conn, $date_from = null, $date_to = null) {
    $stats = [
        'total_tasks' => 0,
        'completed_tasks' => 0,
        'pending_tasks' => 0,
        'delayed_tasks' => 0,
        'total_tasks_all' => 0,     // All tasks EXCEPT "can't be done"
        'shifted_tasks' => 0        // Tasks with status = 'Shifted' OR status = '🔁'
    ];
    
    // Determine the week to use for calculations
    $week_start = null;
    if (!empty($date_from)) {
        $week_start = getWeekStartForDate($date_from);
    } else {
        $week_start = getWeekStartForDate(date('Y-m-d'));
    }
    
    $total_tasks_excluding_cant_be_done = 0;
    $shifted_tasks_count = 0;
    $task_pending_count = 0;
    $delayed_task_count = 0;
    $shifted_not_delayed_count = 0;
    
    $now = time();
    $processTask = function($status, $planned_date, $planned_time, $actual_date, $actual_time, $task_type = 'delegation') use (
        $date_from,
        $date_to,
        $week_start,
        &$stats,
        &$total_tasks_excluding_cant_be_done,
        &$shifted_tasks_count,
        &$task_pending_count,
        &$delayed_task_count,
        &$shifted_not_delayed_count,
        $now
    ) {
        if (!isTaskInDateRange($planned_date, $actual_date, $date_from, $date_to)) {
            return;
        }
        
        $normalized_status = normalizeTaskStatus($status);
        
        // Count shifted tasks (status = 'Shifted' OR status = '🔁') - count all shifted tasks
        if ($normalized_status === 'shifted' || $status === '🔁') {
            $shifted_tasks_count++;
        }
        
        // Check if status is "can't be done" - skip these for total_tasks_all
        if (isStatusCantBeDone($normalized_status)) {
            return; // Skip this task for total_tasks_all
        }
        
        // Count all tasks excluding "can't be done"
        $total_tasks_excluding_cant_be_done++;
        
        $classification = classifyTaskForStats($status, $planned_date, $planned_time, $actual_date, $actual_time, $now, $task_type);
        if ($classification['skip']) {
            return;
        }
        
        $stats['total_tasks']++;
        
        if ($classification['is_completed']) {
            $stats['completed_tasks']++;
            return;
        }
        
        // Track shifted tasks that are not completed and not past due
        if (($normalized_status === 'shifted' || $status === '🔁') && !$classification['is_delayed']) {
            $shifted_not_delayed_count++;
        }
        
        // Use new week-based logic for pending and delayed
        if (!empty($week_start)) {
            if (isTaskPendingForWeek($status, $planned_date, $planned_time, $actual_date, $actual_time, $week_start, $task_type)) {
                $task_pending_count++;
            }
            if (isTaskDelayedForWeek($status, $planned_date, $planned_time, $actual_date, $actual_time, $week_start, $task_type)) {
                $delayed_task_count++;
            }
        } else {
            // Fallback to old logic
        if ($classification['is_pending']) {
            $task_pending_count++;
        }
        if ($classification['is_delayed']) {
            $delayed_task_count++;
            }
        }
    };
    
    // Delegation tasks
    $delegation_sql = "SELECT status, planned_date, planned_time, actual_date, actual_time FROM tasks";
    $delegation_result = mysqli_query($conn, $delegation_sql);
    if ($delegation_result) {
        while ($row = mysqli_fetch_assoc($delegation_result)) {
            $processTask(
                $row['status'] ?? '',
                $row['planned_date'] ?? '',
                $row['planned_time'] ?? '',
                $row['actual_date'] ?? '',
                $row['actual_time'] ?? '',
                'delegation'
            );
        }
        mysqli_free_result($delegation_result);
    }
    
    // Checklist tasks
    $checklist_sql = "SELECT COALESCE(status, 'pending') as status, task_date as planned_date, actual_date, actual_time 
                      FROM checklist_subtasks";
    $checklist_result = mysqli_query($conn, $checklist_sql);
    if ($checklist_result) {
        while ($row = mysqli_fetch_assoc($checklist_result)) {
            $processTask(
                $row['status'] ?? 'pending',
                $row['planned_date'] ?? '',
                '23:59:59',
                $row['actual_date'] ?? '',
                $row['actual_time'] ?? '',
                'checklist'
            );
        }
        mysqli_free_result($checklist_result);
    }
    
    // FMS tasks
    $fms_sql = "SELECT status, planned, actual FROM fms_tasks";
    $fms_result = mysqli_query($conn, $fms_sql);
    if ($fms_result) {
        while ($row = mysqli_fetch_assoc($fms_result)) {
            $status = $row['status'] ?? '';
            if ($status === '') {
                $status = 'pending';
            }
            $planned_ts = parseFmsTimestampUniversal($row['planned'] ?? '');
            $actual_ts = parseFmsTimestampUniversal($row['actual'] ?? '');
            $planned_date = $planned_ts ? date('Y-m-d', $planned_ts) : '';
            $planned_time = $planned_ts ? date('H:i:s', $planned_ts) : '';
            $actual_date = $actual_ts ? date('Y-m-d', $actual_ts) : '';
            $actual_time = $actual_ts ? date('H:i:s', $actual_ts) : '';
            
            $processTask($status, $planned_date, $planned_time, $actual_date, $actual_time, 'fms');
        }
        mysqli_free_result($fms_result);
    }
    
    // Set final stats - include delayed and shifted tasks in pending count
    $stats['pending_tasks'] = $task_pending_count + $delayed_task_count + $shifted_not_delayed_count;
    $stats['delayed_tasks'] = $delayed_task_count;
    $stats['total_tasks_all'] = $total_tasks_excluding_cant_be_done;
    $stats['shifted_tasks'] = $shifted_tasks_count;
    
    return $stats;
}

/**
 * Get team members for a manager
 */
function getManagerTeamMembers($conn, $manager_id) {
    $team_members = [];
    
    $sql = "SELECT u.id, u.username, u.name, u.user_type, u.department_id, d.name as department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.manager_id = ?
            AND u.user_type = 'doer'
            ORDER BY u.name";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $manager_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $team_members[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    return $team_members;
}

/**
 * Calculate team stats for a manager
 */
function calculateTeamStats($conn, $manager_id, $team_member_ids) {
    $team_stats = [
        'total_tasks' => 0,
        'completed_tasks' => 0,
        'delayed_tasks' => 0,
        'pending_tasks' => 0,
        'completion_rate' => 0
    ];
    
    if (empty($team_member_ids)) {
        return $team_stats;
    }
    
    $ids_str = implode(',', array_map('intval', $team_member_ids));
    $now = time();
    
    // Use current week for calculations
    $week_start = getWeekStartForDate(date('Y-m-d'));
    
    $task_pending_count = 0;
    $delayed_task_count = 0;
    $shifted_not_delayed_count = 0;
    
    $processTask = function($status, $planned_date, $planned_time, $actual_date, $actual_time, $task_type = 'delegation') use (&$team_stats, &$task_pending_count, &$delayed_task_count, &$shifted_not_delayed_count, $now, $week_start) {
        $normalized_status = normalizeTaskStatus($status);
        $classification = classifyTaskForStats($status, $planned_date, $planned_time, $actual_date, $actual_time, $now, $task_type);
        if ($classification['skip']) {
            return;
        }
        
        $team_stats['total_tasks']++;
        
        if ($classification['is_completed']) {
            $team_stats['completed_tasks']++;
            return;
        }
        
        // Track shifted tasks that are not completed and not past due
        if (($normalized_status === 'shifted' || $status === '🔁') && !$classification['is_delayed']) {
            $shifted_not_delayed_count++;
        }
        
        // Use new week-based logic for pending and delayed
        if (!empty($week_start)) {
            if (isTaskPendingForWeek($status, $planned_date, $planned_time, $actual_date, $actual_time, $week_start, $task_type)) {
                $task_pending_count++;
            }
            if (isTaskDelayedForWeek($status, $planned_date, $planned_time, $actual_date, $actual_time, $week_start, $task_type)) {
                $delayed_task_count++;
            }
        } else {
            // Fallback to old logic
        if ($classification['is_pending']) {
            $task_pending_count++;
        }
        if ($classification['is_delayed']) {
            $delayed_task_count++;
            }
        }
    };
    
    // Delegation tasks
    $sql = "SELECT status, planned_date, planned_time, actual_date, actual_time FROM tasks WHERE doer_id IN ($ids_str)";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $processTask(
                $row['status'] ?? '',
                $row['planned_date'] ?? '',
                $row['planned_time'] ?? '',
                $row['actual_date'] ?? '',
                $row['actual_time'] ?? '',
                'delegation'
            );
        }
        mysqli_free_result($result);
    }
    
    // FMS tasks - get usernames of team members
    $usernames = [];
    $user_sql = "SELECT username FROM users WHERE id IN ($ids_str)";
    $user_result = mysqli_query($conn, $user_sql);
    if ($user_result) {
        while ($user_row = mysqli_fetch_assoc($user_result)) {
            $usernames[] = $user_row['username'];
        }
        mysqli_free_result($user_result);
    }
    
    if (!empty($usernames)) {
        $quoted_usernames = array_map(function($username) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $username) . "'";
        }, $usernames);
        $usernames_str = implode(',', $quoted_usernames);
        $fms_sql = "SELECT status, planned, actual FROM fms_tasks WHERE doer_name IN ($usernames_str)";
        $fms_result = mysqli_query($conn, $fms_sql);
        if ($fms_result) {
            while ($fms_row = mysqli_fetch_assoc($fms_result)) {
                $status = $fms_row['status'] ?? '';
                if ($status === '') {
                    $status = 'pending';
                }
                $planned_ts = parseFmsTimestampUniversal($fms_row['planned'] ?? '');
                $actual_ts = parseFmsTimestampUniversal($fms_row['actual'] ?? '');
                $planned_date = $planned_ts ? date('Y-m-d', $planned_ts) : '';
                $planned_time = $planned_ts ? date('H:i:s', $planned_ts) : '';
                $actual_date = $actual_ts ? date('Y-m-d', $actual_ts) : '';
                $actual_time = $actual_ts ? date('H:i:s', $actual_ts) : '';
                
                $processTask($status, $planned_date, $planned_time, $actual_date, $actual_time, 'fms');
            }
            mysqli_free_result($fms_result);
        }
    }
    
    // Checklist tasks
    if (!empty($usernames)) {
        $quoted_usernames = array_map(function($username) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $username) . "'";
        }, $usernames);
        $usernames_str = implode(',', $quoted_usernames);
        $checklist_sql = "SELECT COALESCE(status, 'pending') as status, task_date as planned_date, actual_date, actual_time 
                          FROM checklist_subtasks WHERE assignee IN ($usernames_str)";
        $checklist_result = mysqli_query($conn, $checklist_sql);
        if ($checklist_result) {
            while ($checklist_row = mysqli_fetch_assoc($checklist_result)) {
                $processTask(
                    $checklist_row['status'] ?? 'pending',
                    $checklist_row['planned_date'] ?? '',
                    '23:59:59',
                    $checklist_row['actual_date'] ?? '',
                    $checklist_row['actual_time'] ?? '',
                    'checklist'
                );
                }
            mysqli_free_result($checklist_result);
        }
    }
    
    // Set final pending count - include delayed and shifted tasks
    $team_stats['pending_tasks'] = $task_pending_count + $delayed_task_count + $shifted_not_delayed_count;
    $team_stats['delayed_tasks'] = $delayed_task_count;
    
    if ($team_stats['total_tasks'] > 0) {
        $team_stats['completion_rate'] = round(($team_stats['completed_tasks'] / $team_stats['total_tasks']) * 100, 2);
    }
    
    return $team_stats;
}

/**
 * Calculate Performance Rate from RQC, WND, and WND_On_Time
 * 
 * Step 1: Convert WND and WNDOT into positive scores (for calculation only):
 *   Converted_WND = 100 - WND  (WND is stored as negative, so 100 - abs(WND))
 *   Converted_WNDOT = 100 - WNDOT
 * 
 * Step 2:
 *   If RQC NOT available: Performance Score = (Converted_WND + Converted_WNDOT) / 2
 *   If RQC available:     Performance Score = (Converted_WND + Converted_WNDOT + RQC) / 3
 * 
 * WND and WNDOT are ALWAYS included (even when 0 → score of 100).
 * Only RQC can be absent (null/0).
 * 
 * @param float|null $rqc RQC score (positive, higher is better)
 * @param float|null $wnd WND value (negative percentage, e.g., -60)
 * @param float|null $wnd_on_time WND_On_Time value (negative percentage, e.g., -40)
 * @return float Performance Rate (0-100, higher is better)
 */
function calculatePerformanceRate($rqc = null, $wnd = null, $wnd_on_time = null) {
    // Convert WND and WNDOT from negative to positive scores
    // WND/WNDOT are always included (0 → 100 = perfect score)
    $wnd_value = ($wnd !== null) ? $wnd : 0;
    $wnd_on_time_value = ($wnd_on_time !== null) ? $wnd_on_time : 0;
    
    $converted_wnd = 100 - abs($wnd_value);
    $converted_wndot = 100 - abs($wnd_on_time_value);
    
    // Check if RQC is valid (not null, not 0)
    $rqc_valid = ($rqc !== null && $rqc > 0);
    
    if ($rqc_valid) {
        // All 3: (Converted_WND + Converted_WNDOT + RQC) / 3
        return round(($converted_wnd + $converted_wndot + $rqc) / 3, 2);
    } else {
        // No RQC: (Converted_WND + Converted_WNDOT) / 2
        return round(($converted_wnd + $converted_wndot) / 2, 2);
    }
}

/**
 * Get leaderboard data with Performance Rate ranking
 * @param mysqli $conn Database connection
 * @param int $limit Maximum number of users to return (0 = no limit, only for admins)
 * @param string|null $user_type_filter Filter by user type ('doer' or 'manager')
 * @param string|null $date_from Start date for filtering
 * @param string|null $date_to End date for filtering
 * @param bool $is_admin Whether the requesting user is an admin (defaults to checking session)
 * @return array Leaderboard data with ranks
 */
function getLeaderboardData($conn, $limit = 10, $user_type_filter = null, $date_from = null, $date_to = null, $is_admin = null) {
    $leaderboard = [];
    
    // Check if user is admin (use session if not provided)
    if ($is_admin === null) {
        $is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
    
    $where_clause = "";
    if ($user_type_filter) {
        $where_clause = " AND u.user_type = '" . mysqli_real_escape_string($conn, $user_type_filter) . "'";
    }
    
    // Add date range filter for tasks
    $date_filter = "";
    if ($date_from && $date_to) {
        $date_from_escaped = mysqli_real_escape_string($conn, $date_from);
        $date_to_escaped = mysqli_real_escape_string($conn, $date_to);
        $date_filter = " AND (t.planned_date BETWEEN '$date_from_escaped' AND '$date_to_escaped' OR t.actual_date BETWEEN '$date_from_escaped' AND '$date_to_escaped')";
    }
    
    // Get all users first (exclude inactive users)
    $sql = "SELECT 
                u.id, 
                u.username, 
                u.name, 
                u.user_type,
                u.profile_photo
            FROM users u
            WHERE u.user_type IN ('doer', 'manager') $where_clause
            AND (u.username IS NULL OR LOWER(u.username) <> 'admin')
            AND (u.name IS NULL OR LOWER(u.name) <> 'admin')
            AND COALESCE(u.Status, 'Active') = 'Active'
            ORDER BY u.name";
    
    $users = [];
    if ($stmt = mysqli_prepare($conn, $sql)) {
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $users[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Calculate Performance Rate for each user
    $leaderboard_data = [];
    foreach ($users as $user) {
        // Get personal stats (includes WND and WND_On_Time, excludes "can't be done" tasks)
        $personal_stats = calculatePersonalStats($conn, $user['id'], $user['username'], $date_from, $date_to);
        
        // Get RQC score
        $rqc_score = getRqcScore($conn, $user['name'], $date_from, $date_to);
        
        // Calculate Performance Rate
        $performance_rate = calculatePerformanceRate(
            $rqc_score,
            $personal_stats['wnd'] ?? null,
            $personal_stats['wnd_on_time'] ?? null
        );
        
        // Count total tasks (excluding "can't be done")
        $total_tasks = $personal_stats['total_tasks'] ?? 0;
        $completed_tasks = $personal_stats['completed_on_time'] ?? 0;
        
        // Calculate completion_rate for backward compatibility (not used for ranking)
        $completion_rate = 0;
        if ($total_tasks > 0) {
            $completion_rate = round(($completed_tasks / $total_tasks) * 100, 1);
        }
        
        $leaderboard_data[] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'user_type' => $user['user_type'],
            'profile_photo' => $user['profile_photo'] ?? '',
            'performance_rate' => $performance_rate,
            'rqc_score' => $rqc_score,
            'wnd' => $personal_stats['wnd'] ?? 0,
            'wnd_on_time' => $personal_stats['wnd_on_time'] ?? 0,
            'completion_rate' => $completion_rate, // Kept for backward compatibility
            'total_tasks' => $total_tasks,
            'completed_tasks' => $completed_tasks,
            'is_current_user' => ($user['id'] == ($_SESSION['id'] ?? 0))
        ];
    }
    
    // Sort by Performance Rate (DESC), then by total_tasks (DESC) as tiebreaker
    usort($leaderboard_data, function($a, $b) {
        // Primary sort: Performance Rate (higher is better)
        if ($a['performance_rate'] != $b['performance_rate']) {
            return $b['performance_rate'] <=> $a['performance_rate'];
        }
        // Secondary sort: Total tasks (higher is better)
        return $b['total_tasks'] <=> $a['total_tasks'];
    });
    
    // Assign ranks to all users first
    $rank = 1;
    $all_ranked_data = [];
    foreach ($leaderboard_data as $user_data) {
        $user_data['rank'] = $rank;
        $all_ranked_data[] = $user_data;
        $rank++;
    }
    
    // Filter based on admin status
    if ($is_admin) {
        // Admin can see all ranks - apply limit if specified
        if ($limit > 0) {
            $leaderboard = array_slice($all_ranked_data, 0, $limit);
        } else {
            $leaderboard = $all_ranked_data; // No limit - show all
        }
    } else {
        // Non-admin: Only show top 3 + current user (if not in top 3)
        $top3 = array_slice($all_ranked_data, 0, 3);
        $current_user = null;
        
        // Find current user
        foreach ($all_ranked_data as $user_data) {
            if ($user_data['is_current_user']) {
                $current_user = $user_data;
                break;
            }
        }
        
        // Build display data: Top 3 + current user (if not in Top 3)
        $leaderboard = $top3;
        if ($current_user && $current_user['rank'] > 3) {
            $leaderboard[] = $current_user;
        }
    }
    
    return $leaderboard;
}

/**
 * Get team availability data
 */
function getTeamAvailabilityData($conn, $user_ids = null) {
    $team_members = [];
    $today = date('Y-m-d');
    
    $sql = "SELECT 
                u.id, 
                u.username, 
                u.name, 
                u.user_type,
                u.profile_photo,
                MIN(lr.leave_type) as leave_type,
                MIN(lr.duration) as duration,
                MIN(lr.start_date) as start_date,
                MAX(lr.end_date) as end_date,
                MIN(lr.status) as leave_status
            FROM users u 
            LEFT JOIN Leave_request lr ON (
                (lr.employee_name = u.name OR lr.employee_name = u.username)
                AND lr.status IN ('PENDING', 'Approve')
                AND lr.status NOT IN ('Reject', 'Cancelled')
                AND (
                    (lr.end_date IS NOT NULL AND lr.start_date <= ? AND lr.end_date >= ?)
                    OR
                    (lr.end_date IS NULL AND lr.start_date = ?)
                )
            )
            WHERE u.user_type IN ('doer', 'manager')
            AND (u.username IS NULL OR LOWER(u.username) <> 'admin')
            AND (u.name IS NULL OR LOWER(u.name) <> 'admin')
            AND COALESCE(u.Status, 'Active') = 'Active'";
    
    if ($user_ids && !empty($user_ids)) {
        $ids_str = implode(',', array_map('intval', $user_ids));
        $sql .= " AND u.id IN ($ids_str)";
    }
    
    $sql .= " GROUP BY u.id, u.username, u.name, u.user_type, u.profile_photo
            ORDER BY u.name
            LIMIT 100";
    
    $availabilityPriorities = [
        'on-leave' => 0,
        'remote'   => 1,
        'available'=> 2
    ];
    
    $matchesKeyword = function($value, $keywords) {
        $value = strtolower(trim((string)$value));
        if ($value === '') {
            return false;
        }
        foreach ($keywords as $keyword) {
            if (strpos($value, $keyword) !== false) {
                return true;
            }
        }
        return false;
    };
    
    $determineStatus = function($row) use ($matchesKeyword) {
        $raw_leave_status = trim((string)($row['leave_status'] ?? ''));
        $leave_status = strtolower($raw_leave_status);
        $duration = $row['duration'] ?? '';
        $leave_type = $row['leave_type'] ?? '';
        $has_active_request = ($duration !== null && $duration !== '') 
            || ($leave_type !== null && $leave_type !== '') 
            || $raw_leave_status !== '';
        
        if (!$has_active_request) {
            return 'available';
        }
        
        if ($leave_status === 'pending') {
            return 'available';
        }
        
        $is_approved = in_array($leave_status, ['approved', 'approve'], true);
        $wfh_keywords = ['full day wfh', 'half day wfh', 'work from home', 'wfh'];
        $leave_keywords = ['full day leave', 'half day leave', 'short leave'];
        
        $is_wfh = $matchesKeyword($duration, $wfh_keywords) || $matchesKeyword($leave_type, $wfh_keywords);
        if ($is_approved && $is_wfh) {
            return 'remote';
        }
        
        $is_confirmed_leave = $is_approved && ($matchesKeyword($duration, $leave_keywords) || $matchesKeyword($leave_type, $leave_keywords));
        if ($is_confirmed_leave) {
            return 'on-leave';
        }
        
        return 'available';
    };
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sss", $today, $today, $today);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $user_id = $row['id'];
                    $availability_status = $determineStatus($row);
                    $has_active_request = !empty($row['leave_type']) || !empty($row['duration']) || !empty($row['leave_status']);
                    
                    $team_members[] = [
                        'id' => $user_id,
                        'name' => $row['name'],
                        'username' => $row['username'],
                        'profile_photo' => $row['profile_photo'] ?? '',
                        'status' => $availability_status,
                        'leave_type' => $has_active_request ? ($row['leave_type'] ?? '') : '',
                        'duration' => $has_active_request ? ($row['duration'] ?? '') : '',
                        'start_date' => $has_active_request ? ($row['start_date'] ?? '') : '',
                        'end_date' => $has_active_request ? ($row['end_date'] ?? '') : '',
                        'leave_status' => $has_active_request ? ($row['leave_status'] ?? '') : '',
                        'is_current_user' => ($user_id == ($_SESSION['id'] ?? 0))
                    ];
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    if (!empty($team_members)) {
        usort($team_members, function($a, $b) use ($availabilityPriorities) {
            $priorityA = $availabilityPriorities[$a['status']] ?? 3;
            $priorityB = $availabilityPriorities[$b['status']] ?? 3;
            
            if ($priorityA === $priorityB) {
                return strcasecmp($a['name'], $b['name']);
            }
            
            return $priorityA <=> $priorityB;
        });
    }
    
    return $team_members;
}

/**
 * Get RQC score for a user by name
 * Returns the most recent RQC score from rqc_scores table
 * 
 * @param mysqli $conn Database connection
 * @param string $user_name User's name to match against rqc_scores.name
 * @param string|null $date_from Optional: Start date for date range (Y-m-d format)
 * @param string|null $date_to Optional: End date for date range (Y-m-d format)
 * @return string|int RQC score or 0 if not found
 */
function getRqcScore($conn, $user_name, $date_from = null, $date_to = null) {
    if (empty($user_name)) {
        return 0;
    }
    
    // Build SQL query based on whether date range is provided
    if ($date_from && $date_to) {
        // Get all RQC scores within date range and return the average value
        // Use DISTINCT to avoid duplicate rows when both exact and LIKE match
        $sql = "SELECT DISTINCT score, timestamp FROM rqc_scores 
                WHERE (name = ? OR name LIKE ?)
                AND timestamp >= ? 
                AND timestamp <= ?
                ORDER BY timestamp DESC";
        
        $rqc_score = 0;
        if ($stmt = mysqli_prepare($conn, $sql)) {
            $date_from_dt = $date_from . ' 00:00:00';
            $date_to_dt = $date_to . ' 23:59:59';
            $name_like = $user_name . '%';
            mysqli_stmt_bind_param($stmt, "ssss", $user_name, $name_like, $date_from_dt, $date_to_dt);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $scores = [];
                $seen_scores = []; // Track unique score+timestamp combinations
                while ($row = mysqli_fetch_assoc($result)) {
                    $score = $row['score'] ?? null;
                    $timestamp = $row['timestamp'] ?? '';
                    // Create unique key to avoid duplicates
                    $unique_key = $score . '_' . $timestamp;
                    if ($score !== null && is_numeric($score) && !in_array($unique_key, $seen_scores)) {
                        $scores[] = floatval($score);
                        $seen_scores[] = $unique_key;
                    }
                }
                if (!empty($scores)) {
                    $rqc_score = round(array_sum($scores) / count($scores), 2);
                }
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // Get the most recent RQC score for this user (no date range) - try exact match first, then LIKE match
        // This handles cases where rqc_scores table has "Prateek Kumar" but we're searching for "Prateek"
        $sql = "SELECT score FROM rqc_scores 
                WHERE name = ? OR name LIKE ?
                ORDER BY timestamp DESC 
                LIMIT 1";
        
        $rqc_score = 0;
        if ($stmt = mysqli_prepare($conn, $sql)) {
            $name_like = $user_name . '%'; // Match "Prateek" or "Prateek Kumar", "Prateek Singh", etc.
            mysqli_stmt_bind_param($stmt, "ss", $user_name, $name_like);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if ($row = mysqli_fetch_assoc($result)) {
                    $rqc_score = $row['score'] ?? 0;
                    // Convert to numeric if possible, otherwise return as string
                    if (is_numeric($rqc_score)) {
                        $rqc_score = floatval($rqc_score);
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    return $rqc_score;
}
?>


