<?php
/**
 * Notification Triggers
 * Functions to create notifications for various events
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/dashboard_components.php';

/**
 * Create notification when a meeting is requested
 */
function triggerMeetingRequestNotification($conn, $meeting_id, $doer_name, $doer_email) {
    // Get meeting details to extract date and time
    $meeting_query = "SELECT preferred_date, preferred_time FROM meeting_requests WHERE id = ?";
    $meeting_stmt = mysqli_prepare($conn, $meeting_query);
    if (!$meeting_stmt) {
        error_log("Failed to prepare meeting query: " . mysqli_error($conn));
        return;
    }
    
    mysqli_stmt_bind_param($meeting_stmt, 'i', $meeting_id);
    mysqli_stmt_execute($meeting_stmt);
    $meeting_result = mysqli_stmt_get_result($meeting_stmt);
    $meeting = mysqli_fetch_assoc($meeting_result);
    mysqli_stmt_close($meeting_stmt);
    
    // Format date and time
    $formatted_date = '';
    $formatted_time = '';
    if ($meeting) {
        $formatted_date = date('d/m/Y', strtotime($meeting['preferred_date']));
        $formatted_time = date('h:i A', strtotime($meeting['preferred_time']));
    }
    
    // Get admin user IDs
    $admin_query = "SELECT id FROM users WHERE user_type = 'admin'";
    $admin_result = mysqli_query($conn, $admin_query);
    
    while ($admin = mysqli_fetch_assoc($admin_result)) {
        createNotification(
            $conn,
            $admin['id'],
            'meeting_request',
            "New Meeting Request by {$doer_name}",
            "Meeting request for {$formatted_date}, {$formatted_time}",
            $meeting_id,
            'meeting',
            true,
            [
                'actions' => [
                    [
                        'type' => 'approve_meeting',
                        'label' => 'Approve',
                        'icon' => 'fas fa-check',
                        'color' => 'success',
                        'tooltip' => 'Approve Meeting'
                    ],
                    [
                        'type' => 'reschedule_meeting',
                        'label' => 'Re-schedule',
                        'icon' => 'fas fa-calendar-alt',
                        'color' => 'primary',
                        'tooltip' => 'Re-schedule Meeting'
                    ]
                ]
            ]
        );
    }
}

/**
 * Create notification when a meeting is rescheduled
 */
function triggerMeetingRescheduledNotification($conn, $meeting_id, $user_id, $new_date, $new_time) {
    $formatted_date = date('d/m/Y', strtotime($new_date));
    $formatted_time = date('h:i A', strtotime($new_time));
    
    createNotification(
        $conn,
        $user_id,
        'meeting_rescheduled',
        'Meeting Rescheduled',
        "Your meeting has been rescheduled to {$formatted_date} at {$formatted_time}",
        $meeting_id,
        'meeting',
        false,
        null
    );
}

/**
 * Create notification for day special events (birthdays, work anniversaries)
 */
function triggerDaySpecialNotifications($conn) {
    $today = date('m-d');
    $year = date('Y');
    
    // Get all users with birthdays or work anniversaries today
    // Cast DATE_FORMAT to ensure consistent collation for comparison
    $query = "SELECT id, name, date_of_birth, joining_date FROM users WHERE 
              (CAST(DATE_FORMAT(date_of_birth, '%m-%d') AS CHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) = ? AND date_of_birth IS NOT NULL) OR
              (CAST(DATE_FORMAT(joining_date, '%m-%d') AS CHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) = ? AND joining_date IS NOT NULL)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ss', $today, $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $special_users = [];
    while ($user = mysqli_fetch_assoc($result)) {
        $special_users[] = $user;
    }
    
    if (empty($special_users)) {
        return;
    }
    
    // Get all user IDs to notify
    $all_users_query = "SELECT id FROM users";
    $all_users_result = mysqli_query($conn, $all_users_query);
    $all_user_ids = [];
    while ($user = mysqli_fetch_assoc($all_users_result)) {
        $all_user_ids[] = $user['id'];
    }
    
    // Create notifications for each special event
    // Use a flag file to ensure we only run once per day
    $flag_file = sys_get_temp_dir() . '/day_special_notifications_' . date('Y-m-d') . '.flag';
    
    // Check if we've already run today
    if (file_exists($flag_file)) {
        return; // Already processed today
    }
    
    foreach ($special_users as $special_user) {
        $is_birthday = ($special_user['date_of_birth'] && date('m-d', strtotime($special_user['date_of_birth'])) === $today);
        $is_anniversary = ($special_user['joining_date'] && date('m-d', strtotime($special_user['joining_date'])) === $today);
        
        foreach ($all_user_ids as $user_id) {
            if ($is_birthday) {
                // createNotification will check for duplicates internally
                createNotification(
                    $conn,
                    $user_id,
                    'day_special',
                    'Birthday Today 🎉',
                    "Today is {$special_user['name']}'s Birthday 🎉",
                    $special_user['id'],
                    'user',
                    false,
                    null
                );
            }
            
            if ($is_anniversary) {
                $years = $year - date('Y', strtotime($special_user['joining_date']));
                // createNotification will check for duplicates internally
                
                // Handle 0 year anniversary with a welcoming message
                if ($years == 0) {
                    $anniversary_message = "Welcome {$special_user['name']}! 🎉 Today marks your first day with us. We're excited to have you on board!";
                    $anniversary_title = 'Welcome! 🎉';
                } else {
                    $anniversary_message = "Today is {$special_user['name']}'s Work Anniversary 🎉 ({$years} year" . ($years > 1 ? 's' : '') . ")";
                    $anniversary_title = 'Work Anniversary 🎉';
                }
                
                createNotification(
                    $conn,
                    $user_id,
                    'day_special',
                    $anniversary_title,
                    $anniversary_message,
                    $special_user['id'],
                    'user',
                    false,
                    null
                );
            }
        }
    }
    
    // Create flag file to mark that we've processed today
    file_put_contents($flag_file, date('Y-m-d H:i:s'));
}

/**
 * Create notification for notes reminder
 */
function triggerNotesReminderNotification($conn, $note_id, $user_id, $note_title) {
    return createNotification(
        $conn,
        $user_id,
        'notes_reminder',
        'Reminder',
        "Reminder: {$note_title}",
        $note_id,
        'note',
        false,
        null
    );
}

/**
 * Create notification when leave is requested
 * Notifies manager and admin users who have the employee in their team
 */
function triggerLeaveRequestNotification($conn, $leave_id, $employee_name, $manager_email = null, $manager_name = null) {
    // Get employee user ID
    $employee_query = "SELECT id, manager_id, manager FROM users WHERE name = ? OR username = ? LIMIT 1";
    $employee_stmt = mysqli_prepare($conn, $employee_query);
    $employee_user_id = null;
    
    if ($employee_stmt) {
        mysqli_stmt_bind_param($employee_stmt, 'ss', $employee_name, $employee_name);
        mysqli_stmt_execute($employee_stmt);
        $employee_result = mysqli_stmt_get_result($employee_stmt);
        $employee = mysqli_fetch_assoc($employee_result);
        mysqli_stmt_close($employee_stmt);
        
        if ($employee) {
            $employee_user_id = $employee['id'];
        }
    }
    
    // Get managers/admins to notify
    $managers_to_notify = [];
    
    // 1. Get direct manager from employee record
    if ($employee_user_id && !empty($employee['manager_id'])) {
        $manager_query = "SELECT id, name, email FROM users WHERE id = ? AND user_type IN ('manager', 'admin')";
        $manager_stmt = mysqli_prepare($conn, $manager_query);
        if ($manager_stmt) {
            mysqli_stmt_bind_param($manager_stmt, 'i', $employee['manager_id']);
            mysqli_stmt_execute($manager_stmt);
            $manager_result = mysqli_stmt_get_result($manager_stmt);
            $manager = mysqli_fetch_assoc($manager_result);
            mysqli_stmt_close($manager_stmt);
            
            if ($manager) {
                $managers_to_notify[$manager['id']] = $manager;
            }
        }
    }
    
    // 2. Get manager by name/email if provided
    if ($manager_name || $manager_email) {
        $manager_query = "SELECT id, name, email FROM users WHERE user_type IN ('manager', 'admin')";
        $conditions = [];
        $params = [];
        $types = '';
        
        if ($manager_name) {
            $conditions[] = "name = ?";
            $params[] = $manager_name;
            $types .= 's';
        }
        if ($manager_email) {
            $conditions[] = "email = ?";
            $params[] = $manager_email;
            $types .= 's';
        }
        
        if (!empty($conditions)) {
            $manager_query .= " AND (" . implode(" OR ", $conditions) . ")";
            $manager_stmt = mysqli_prepare($conn, $manager_query);
            if ($manager_stmt) {
                mysqli_stmt_bind_param($manager_stmt, $types, ...$params);
                mysqli_stmt_execute($manager_stmt);
                $manager_result = mysqli_stmt_get_result($manager_stmt);
                while ($manager = mysqli_fetch_assoc($manager_result)) {
                    $managers_to_notify[$manager['id']] = $manager;
                }
                mysqli_stmt_close($manager_stmt);
            }
        }
    }
    
    // 3. Get all admins (admins should see all leave requests)
    $admin_query = "SELECT id, name, email FROM users WHERE user_type = 'admin'";
    $admin_result = mysqli_query($conn, $admin_query);
    while ($admin = mysqli_fetch_assoc($admin_result)) {
        $managers_to_notify[$admin['id']] = $admin;
    }
    
    // 4. Get managers who have this employee in their team (using getManagerTeamMembers function if available)
    if ($employee_user_id && function_exists('getManagerTeamMembers')) {
        $all_managers_query = "SELECT id FROM users WHERE user_type = 'manager'";
        $all_managers_result = mysqli_query($conn, $all_managers_query);
        while ($manager_row = mysqli_fetch_assoc($all_managers_result)) {
            $team_members = getManagerTeamMembers($conn, (int)$manager_row['id']);
            foreach ($team_members as $member) {
                if (($member['id'] ?? null) == $employee_user_id || 
                    ($member['username'] ?? '') === $employee_name ||
                    ($member['name'] ?? '') === $employee_name) {
                    // This manager has this employee in their team
                    $manager_info_query = "SELECT id, name, email FROM users WHERE id = ?";
                    $manager_info_stmt = mysqli_prepare($conn, $manager_info_query);
                    if ($manager_info_stmt) {
                        mysqli_stmt_bind_param($manager_info_stmt, 'i', $manager_row['id']);
                        mysqli_stmt_execute($manager_info_stmt);
                        $manager_info_result = mysqli_stmt_get_result($manager_info_stmt);
                        $manager_info = mysqli_fetch_assoc($manager_info_result);
                        mysqli_stmt_close($manager_info_stmt);
                        
                        if ($manager_info) {
                            $managers_to_notify[$manager_info['id']] = $manager_info;
                        }
                    }
                    break;
                }
            }
        }
    }
    
    // Log if no managers found
    if (empty($managers_to_notify)) {
        error_log("Leave notification: No managers found to notify for employee: {$employee_name}, leave_id: {$leave_id}");
        if (!$employee_user_id) {
            error_log("Leave notification: Employee not found in users table: {$employee_name}");
        } else {
            error_log("Leave notification: Employee found (ID: {$employee_user_id}) but no manager assigned");
        }
        return;
    }
    
    // Create notifications for all managers/admins
    $notifications_created = 0;
    foreach ($managers_to_notify as $manager_id => $manager) {
        // Check if notification already exists (check for any notification, not just unread)
        // This prevents duplicates but allows re-notification if the previous one was deleted
        $notif_check = "SELECT id FROM notifications 
                       WHERE type = 'leave_request' 
                       AND related_id = ? 
                       AND user_id = ?";
        $notif_check_stmt = mysqli_prepare($conn, $notif_check);
        if ($notif_check_stmt) {
            mysqli_stmt_bind_param($notif_check_stmt, 'si', $leave_id, $manager_id);
            mysqli_stmt_execute($notif_check_stmt);
            $notif_check_result = mysqli_stmt_get_result($notif_check_stmt);
            
            if (mysqli_num_rows($notif_check_result) == 0) {
                $notification_id = createNotification(
                    $conn,
                    $manager_id,
                    'leave_request',
                    'Leave Request',
                    "{$employee_name} has applied for leave",
                    $leave_id,
                    'leave',
                    true,
                    [
                        'actions' => [
                            [
                                'type' => 'approve_leave',
                                'label' => 'Approve',
                                'icon' => 'fas fa-check',
                                'color' => 'success',
                                'tooltip' => 'Approve Leave'
                            ],
                            [
                                'type' => 'reject_leave',
                                'label' => 'Reject',
                                'icon' => 'fas fa-times',
                                'color' => 'danger',
                                'tooltip' => 'Reject Leave'
                            ]
                        ]
                    ]
                );
                
                if ($notification_id) {
                    $notifications_created++;
                    error_log("Leave notification: Created notification ID {$notification_id} for manager ID {$manager_id} (leave_id: {$leave_id}, employee: {$employee_name})");
                } else {
                    error_log("Leave notification: Failed to create notification for manager ID {$manager_id} (leave_id: {$leave_id}, employee: {$employee_name})");
                }
            } else {
                error_log("Leave notification: Notification already exists for manager ID {$manager_id} (leave_id: {$leave_id})");
            }
            mysqli_stmt_close($notif_check_stmt);
        } else {
            error_log("Leave notification: Failed to prepare notification check query for manager ID {$manager_id}");
        }
    }
    
    if ($notifications_created > 0) {
        error_log("Leave notification: Successfully created {$notifications_created} notification(s) for leave_id: {$leave_id}, employee: {$employee_name}");
    }
}

/**
 * Check and create task delay warnings (5 minutes before delay)
 * This should be called periodically (e.g., via cron job or scheduled task)
 * Checks delegation tasks, checklist tasks, and FMS tasks
 */
function checkTaskDelayWarnings($conn) {
    $now = date('Y-m-d H:i:s');
    $warning_time = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    // 1. Check delegation tasks (tasks table)
    $query = "SELECT t.id, t.description, t.planned_date, t.planned_time, t.doer_id, 
                     COALESCE(t.doer_name, u.name, u.username) as user_name, t.unique_id
              FROM tasks t 
              LEFT JOIN users u ON t.doer_id = u.id 
              WHERE t.status NOT IN ('completed', 'not done', 'can not be done', 'shifted')
              AND t.planned_date IS NOT NULL 
              AND t.planned_time IS NOT NULL
              AND t.doer_id IS NOT NULL
              AND TIMESTAMP(t.planned_date, t.planned_time) > ? 
              AND TIMESTAMP(t.planned_date, t.planned_time) <= ?
              AND NOT EXISTS (
                  SELECT 1 FROM notifications n 
                  WHERE n.type = 'task_delay' 
                  AND n.related_id = CAST(t.id AS CHAR)
                  AND n.user_id = t.doer_id
                  AND n.related_type = 'task'
                  AND DATE(n.created_at) = CURDATE()
                  AND n.is_read = 0
              )";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $now, $warning_time);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($task = mysqli_fetch_assoc($result)) {
            $planned_time = date('h:i A', strtotime($task['planned_time']));
            $task_id_display = !empty($task['unique_id']) ? $task['unique_id'] : 'ID: ' . $task['id'];
            createNotification(
                $conn,
                $task['doer_id'],
                'task_delay',
                'Task Delay Warning',
                "Task may get delayed soon. {$task_id_display} - Planned: {$planned_time}",
                (string)$task['id'],
                'task',
                false,
                null
            );
        }
        mysqli_stmt_close($stmt);
    }
    
    // 2. Check checklist tasks
    $checklist_query = "SELECT cs.id, cs.task_description as description, cs.task_date, cs.assignee, cs.doer_name, cs.task_code
                        FROM checklist_subtasks cs
                        WHERE cs.status NOT IN ('completed', 'not done', 'can not be done')
                        AND cs.task_date IS NOT NULL
                        AND (cs.assignee IS NOT NULL OR cs.doer_name IS NOT NULL)
                        AND TIMESTAMP(cs.task_date, '23:59:59') > ?
                        AND TIMESTAMP(cs.task_date, '23:59:59') <= ?
                        AND NOT EXISTS (
                            SELECT 1 FROM notifications n 
                            WHERE n.type = 'task_delay' 
                            AND n.related_id = CAST(cs.id AS CHAR)
                            AND n.user_id = (SELECT id FROM users WHERE username COLLATE utf8mb4_unicode_ci = cs.assignee COLLATE utf8mb4_unicode_ci OR username COLLATE utf8mb4_unicode_ci = cs.doer_name COLLATE utf8mb4_unicode_ci LIMIT 1)
                            AND n.related_type = 'checklist'
                            AND DATE(n.created_at) = CURDATE()
                            AND n.is_read = 0
                        )";
    
    $checklist_stmt = mysqli_prepare($conn, $checklist_query);
    if ($checklist_stmt) {
        mysqli_stmt_bind_param($checklist_stmt, 'ss', $now, $warning_time);
        mysqli_stmt_execute($checklist_stmt);
        $checklist_result = mysqli_stmt_get_result($checklist_stmt);
        
        while ($task = mysqli_fetch_assoc($checklist_result)) {
            // Get user ID from assignee or doer_name
            $user_identifier = $task['assignee'] ?? $task['doer_name'];
            if ($user_identifier) {
                $user_query = "SELECT id FROM users WHERE username = ? LIMIT 1";
                $user_stmt = mysqli_prepare($conn, $user_query);
                if ($user_stmt) {
                    mysqli_stmt_bind_param($user_stmt, 's', $user_identifier);
                    mysqli_stmt_execute($user_stmt);
                    $user_result = mysqli_stmt_get_result($user_stmt);
                    $user = mysqli_fetch_assoc($user_result);
                    mysqli_stmt_close($user_stmt);
                    
                    if ($user) {
                        $task_id_display = !empty($task['task_code']) ? $task['task_code'] : 'ID: ' . $task['id'];
                        $planned_date = date('d M Y', strtotime($task['task_date']));
                        createNotification(
                            $conn,
                            $user['id'],
                            'task_delay',
                            'Task Delay Warning',
                            "Checklist task may get delayed soon. {$task_id_display} - Planned: {$planned_date}",
                            (string)$task['id'],
                            'checklist',
                            false,
                            null
                        );
                    }
                }
            }
        }
        mysqli_stmt_close($checklist_stmt);
    }
    
    // 3. Check FMS tasks (simplified - FMS tasks use different date format)
    // FMS tasks are checked in my_task.php when page loads, but we can add a basic check here too
    $fms_query = "SELECT ft.id, ft.step_name as description, ft.planned, ft.doer_name, ft.unique_key
                  FROM fms_tasks ft
                  WHERE ft.status NOT IN ('completed', 'not done', 'can not be done', 'done', 'yes')
                  AND ft.planned IS NOT NULL
                  AND ft.doer_name IS NOT NULL
                  AND ft.actual IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM notifications n 
                      WHERE n.type = 'task_delay' 
                      AND n.related_id = CAST(ft.id AS CHAR)
                      AND n.user_id = (SELECT id FROM users WHERE username COLLATE utf8mb4_unicode_ci = ft.doer_name COLLATE utf8mb4_unicode_ci LIMIT 1)
                      AND n.related_type = 'fms'
                      AND DATE(n.created_at) = CURDATE()
                      AND n.is_read = 0
                  )";
    
    $fms_stmt = mysqli_prepare($conn, $fms_query);
    if ($fms_stmt) {
        mysqli_stmt_execute($fms_stmt);
        $fms_result = mysqli_stmt_get_result($fms_stmt);
        
        while ($task = mysqli_fetch_assoc($fms_result)) {
            // Parse FMS planned datetime string
            $planned_timestamp = null;
            if (!empty($task['planned'])) {
                // Try to parse various FMS date formats
                $planned_timestamp = strtotime($task['planned']);
                if ($planned_timestamp === false) {
                    // Try alternative parsing
                    $planned_timestamp = strtotime(str_replace('/', '-', $task['planned']));
                }
            }
            
            if ($planned_timestamp) {
                $planned_datetime = date('Y-m-d H:i:s', $planned_timestamp);
                $now_timestamp = strtotime($now);
                $warning_timestamp = strtotime($warning_time);
                
                // Check if planned time is within warning window
                if ($planned_timestamp > $now_timestamp && $planned_timestamp <= $warning_timestamp) {
                    // Get user ID
                    $user_query = "SELECT id FROM users WHERE username = ? LIMIT 1";
                    $user_stmt = mysqli_prepare($conn, $user_query);
                    if ($user_stmt) {
                        mysqli_stmt_bind_param($user_stmt, 's', $task['doer_name']);
                        mysqli_stmt_execute($user_stmt);
                        $user_result = mysqli_stmt_get_result($user_stmt);
                        $user = mysqli_fetch_assoc($user_result);
                        mysqli_stmt_close($user_stmt);
                        
                        if ($user) {
                            $task_id_display = !empty($task['unique_key']) ? $task['unique_key'] : 'ID: ' . $task['id'];
                            $planned_display = date('d M Y h:i A', $planned_timestamp);
                            createNotification(
                                $conn,
                                $user['id'],
                                'task_delay',
                                'Task Delay Warning',
                                "FMS task may get delayed soon. {$task_id_display} - Planned: {$planned_display}",
                                (string)$task['id'],
                                'fms',
                                false,
                                null
                            );
                        }
                    }
                }
            }
        }
        mysqli_stmt_close($fms_stmt);
    }
}

/**
 * Check for overdue checklist tasks and notify managers
 * This function checks for checklist tasks that were due yesterday and are not completed
 * It sends a notification to the manager of the user who owns the task
 * 
 * Trigger: Should be called once per day (ideally at the start of the day)
 * Logic:
 * - Find checklist tasks where task_date is yesterday
 * - Status is NOT "Done" or "Completed"
 * - Send notification to the manager of the assignee
 */
function checkOverdueChecklistTasks($conn) {
    // Get yesterday's date (the day after the task's due date)
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // Find checklist tasks that were due yesterday and are not completed
    $query = "SELECT cs.id, cs.task_code, cs.task_description, cs.task_date, cs.assignee, 
                     cs.status, cs.assigned_by
              FROM checklist_subtasks cs
              WHERE cs.task_date = ?
              AND cs.status NOT IN ('Done', 'Completed', 'completed', 'done')
              AND cs.assignee IS NOT NULL
              AND cs.assignee != ''
              AND NOT EXISTS (
                  SELECT 1 FROM notifications n 
                  WHERE n.type = 'checklist_overdue' 
                  AND n.related_id = CAST(cs.id AS CHAR)
                  AND n.related_type = 'checklist'
                  AND DATE(n.created_at) = CURDATE()
              )";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Failed to prepare overdue checklist tasks query: " . mysqli_error($conn));
        return;
    }
    
    mysqli_stmt_bind_param($stmt, 's', $yesterday);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notifications_created = 0;
    $errors = [];
    
    while ($task = mysqli_fetch_assoc($result)) {
        // Get the assignee's user information
        $assignee_username = $task['assignee'];
        
        // Get user record to find their manager
        $user_query = "SELECT id, username, name, manager_id, manager FROM users 
                      WHERE username = ? OR name = ? LIMIT 1";
        $user_stmt = mysqli_prepare($conn, $user_query);
        
        if (!$user_stmt) {
            $errors[] = "Failed to prepare user query for assignee: {$assignee_username}";
            continue;
        }
        
        mysqli_stmt_bind_param($user_stmt, 'ss', $assignee_username, $assignee_username);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $user = mysqli_fetch_assoc($user_result);
        mysqli_stmt_close($user_stmt);
        
        if (!$user) {
            $errors[] = "User not found for assignee: {$assignee_username}";
            continue;
        }
        
        // Get the manager ID
        $manager_id = null;
        
        // First, try to get manager_id from user record
        if (!empty($user['manager_id'])) {
            $manager_id = (int)$user['manager_id'];
        } 
        // If manager_id is not available, try to get manager by name
        else if (!empty($user['manager'])) {
            $manager_query = "SELECT id FROM users WHERE name = ? AND user_type IN ('manager', 'admin') LIMIT 1";
            $manager_stmt = mysqli_prepare($conn, $manager_query);
            if ($manager_stmt) {
                mysqli_stmt_bind_param($manager_stmt, 's', $user['manager']);
                mysqli_stmt_execute($manager_stmt);
                $manager_result = mysqli_stmt_get_result($manager_stmt);
                $manager = mysqli_fetch_assoc($manager_result);
                mysqli_stmt_close($manager_stmt);
                
                if ($manager) {
                    $manager_id = (int)$manager['id'];
                }
            }
        }
        
        // If no manager found, skip this task
        if (!$manager_id) {
            $errors[] = "No manager found for assignee: {$assignee_username} (Task ID: {$task['id']})";
            continue;
        }
        
        // Format the task date for display
        $task_date_formatted = date('d/m/Y', strtotime($task['task_date']));
        
        // Get task identifier for display
        $task_identifier = !empty($task['task_code']) ? $task['task_code'] : 'ID: ' . $task['id'];
        
        // Get assignee name for display
        $assignee_name = !empty($user['name']) ? $user['name'] : $user['username'];
        
        // Create notification message
        $message = "The checklist task '{$task['task_description']}' ({$task_identifier}) assigned to {$assignee_name} was not completed on its scheduled date ({$task_date_formatted}).";
        
        // Check if notification already exists (double-check to prevent duplicates)
        $notif_check = "SELECT id FROM notifications 
                       WHERE type = 'checklist_overdue' 
                       AND related_id = ? 
                       AND user_id = ? 
                       AND related_type = 'checklist'
                       AND DATE(created_at) = CURDATE()";
        $notif_check_stmt = mysqli_prepare($conn, $notif_check);
        if ($notif_check_stmt) {
            $task_id_str = (string)$task['id'];
            mysqli_stmt_bind_param($notif_check_stmt, 'si', $task_id_str, $manager_id);
            mysqli_stmt_execute($notif_check_stmt);
            $notif_check_result = mysqli_stmt_get_result($notif_check_stmt);
            
            if (mysqli_num_rows($notif_check_result) == 0) {
                // Create notification for the manager
                $notification_id = createNotification(
                    $conn,
                    $manager_id,
                    'checklist_overdue',
                    'Checklist Task Overdue',
                    $message,
                    (string)$task['id'],
                    'checklist',
                    false,
                    null
                );
                
                if ($notification_id) {
                    $notifications_created++;
                } else {
                    $errors[] = "Failed to create notification for task ID: {$task['id']}, Manager ID: {$manager_id}";
                }
            }
            mysqli_stmt_close($notif_check_stmt);
        }
    }
    
    mysqli_stmt_close($stmt);
    
    // Log results
    if ($notifications_created > 0) {
        error_log("Created {$notifications_created} overdue checklist task notifications for tasks due on {$yesterday}");
    }
    
    if (!empty($errors)) {
        error_log("Errors while checking overdue checklist tasks: " . implode('; ', $errors));
    }
}

