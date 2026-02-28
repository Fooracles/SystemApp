<?php
$page_title = "Manage Tasks";
require_once "../includes/header.php";
require_once "../includes/status_column_helpers.php";
require_once "../includes/db_functions.php";
require_once "../includes/sorting_helpers.php";

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user has manager or admin role
if (!isAdmin() && !isManager()) {
    // Redirect to appropriate dashboard based on role
    if (isDoer()) {
        header("Location: doer_dashboard.php");
    } else {
        header("Location: ../index.php");
    }
    exit();
}

// Session-based success/error message handling
$success_message = '';
$error_message = '';

if (isset($_SESSION['manage_tasks_success_msg'])) {
    $success_message = $_SESSION['manage_tasks_success_msg'];
    unset($_SESSION['manage_tasks_success_msg']);
}

if (isset($_SESSION['manage_tasks_error_msg'])) {
    $error_message = $_SESSION['manage_tasks_error_msg'];
    unset($_SESSION['manage_tasks_error_msg']);
}

// Get current page for navigation
$current_page = basename($_SERVER['PHP_SELF']);

// Pagination settings
$allowed_limits = [25, 50, 100, 250];
$tasks_per_page = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowed_limits) ? (int)$_GET['limit'] : 25;
$current_page_num = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page_num - 1) * $tasks_per_page;

// Helper function to build query string for pagination links
function buildManageTasksQuery($params = [], $exclude = []) {
    $query = $_GET;
    foreach ($exclude as $key) {
        unset($query[$key]);
    }
    $query = array_merge($query, $params);
    return http_build_query($query);
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_doer = isset($_GET['doer']) ? $_GET['doer'] : '';
$filter_id = isset($_GET['task_id']) ? $_GET['task_id'] : '';
$filter_description = isset($_GET['task_name']) ? $_GET['task_name'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Get doer_status filter (default to 'Active', only for Admin/Manager)
$doer_status = 'Active'; // Default value
if (isAdmin() || isManager()) {
    $doer_status = isset($_GET['doer_status']) ? $_GET['doer_status'] : 'Active';
    // Validate doer_status value
    if ($doer_status !== 'Active' && $doer_status !== 'Inactive') {
        $doer_status = 'Active';
    }
}

// Sorting parameters - Default: DESC by Planned date & Time
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'planned_date';
$sort_direction = isset($_GET['dir']) ? $_GET['dir'] : 'desc';

// Validate sort parameters to prevent SQL injection
$allowed_sort_columns = ['unique_id', 'description', 'assigned_by', 'planned_date', 'actual_date', 'status', 'delay_duration', 'duration', 'doer_name'];
if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = 'planned_date';
}
if (!in_array($sort_direction, ['asc', 'desc'])) {
    $sort_direction = 'asc';
}


// Function to calculate delay duration
function calculateDelayDuration($planned, $actual, $status) {
    if (empty($planned)) return '';
    
    $planned_dt = new DateTime($planned);
    $now = new DateTime();
    
    if ($status === 'completed' && !empty($actual)) {
        $actual_dt = new DateTime($actual);
        $diff = $actual_dt->diff($planned_dt);
        if ($actual_dt > $planned_dt) {
            return $diff->format('%a days, %h hours, %i minutes');
        }
    } elseif ($status !== 'completed') {
        $diff = $now->diff($planned_dt);
        if ($now > $planned_dt) {
            return $diff->format('%a days, %h hours, %i minutes');
        }
    }
    
    return '';
}

// --- ADD THIS NEW FUNCTION TO FIX THE FMS DATE READING ---
if (!function_exists('parseFMSDateTimeString_doer')) {
    function parseFMSDateTimeString_doer($date_str) {
        if (empty(trim($date_str))) {
            return false;
        }
        // This specifically handles formats like "Sep 05, 2025 04:30 PM"
        $date_obj = DateTime::createFromFormat('M d, Y h:i A', $date_str);
        if ($date_obj) {
            return $date_obj->getTimestamp();
        }
        // As a fallback, try a more general parse
        return strtotime($date_str);
    }
}

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

        if (empty($conn) || !is_object($conn)) {
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

// Ensure priority columns exist in all task tables
if (isset($conn)) {
    ensureTasksColumns($conn);
    ensureChecklistPriorityColumn($conn);
    ensureFmsTasksPriorityColumn($conn);
}

// Fetch all tasks from different sources
$all_tasks = [];

try {
    // 1. Fetch Delegation Tasks
    $delegation_filter_conditions = "";
    $delegation_filter_params = [];
    $delegation_filter_param_types = "";

    if (isManager() && !isAdmin()) {
        $current_manager_id = $_SESSION["id"];
        $current_manager_name = trim($_SESSION["name"] ?? '');
        $current_manager_username = trim($_SESSION["username"] ?? '');
        
        // Get all team member identifiers (same logic as FMS tasks)
        $manager_team_identifiers = getManagerFmsDoerIdentifiers(
            $conn,
            (int)$current_manager_id,
            $current_manager_name,
            $current_manager_username
        );
        
        // Build filter conditions:
        // 1. Tasks assigned to team members (doer belongs to manager's team)
        // 2. Tasks assigned by this manager (assigned_by = manager_id)
        // 3. Tasks where manager is the task manager (manager_id = manager_id)
        // 4. Tasks created by this manager (created_by = manager_id)
        // 5. System tasks (created_by = 0)
        
        $conditions = [];
        $params = [];
        $param_types = "";
        
        // Condition 1: Doer belongs to manager's team
        if (!empty($manager_team_identifiers)) {
            $placeholders = implode(',', array_fill(0, count($manager_team_identifiers), '?'));
            $conditions[] = "LOWER(TRIM(COALESCE(t.doer_name, u.username, ''))) IN ($placeholders)";
            $params = array_merge($params, $manager_team_identifiers);
            $param_types .= str_repeat('s', count($manager_team_identifiers));
        }
        
        // Condition 2: Task assigned by this manager
        $conditions[] = "t.assigned_by = ?";
        $params[] = $current_manager_id;
        $param_types .= "i";
        
        // Condition 3: Manager is the task manager
        $conditions[] = "t.manager_id = ?";
        $params[] = $current_manager_id;
        $param_types .= "i";
        
        // Condition 4: Task created by this manager
        $conditions[] = "t.created_by = ?";
        $params[] = $current_manager_id;
        $param_types .= "i";
        
        // Condition 5: System tasks
        $conditions[] = "t.created_by = 0";
        
        if (!empty($conditions)) {
            $delegation_filter_conditions = " WHERE (" . implode(" OR ", $conditions) . ")";
            $delegation_filter_params = $params;
            $delegation_filter_param_types = $param_types;
        } else {
            // No team members found, show no tasks
            $delegation_filter_conditions = " WHERE 1 = 0";
        }
    }

    // Note: We'll filter tasks by doer status in PHP after fetching to handle all cases properly
    // This allows us to handle tasks with doer_name but no doer_id (which can't be joined)
    
    $delegation_query = "SELECT t.id, t.unique_id, t.description, t.planned_date, t.planned_time, 
                                t.actual_date, t.actual_time, t.status, t.is_delayed, t.delay_duration, 
                                t.duration, t.shifted_count, t.assigned_by, COALESCE(t.priority, 0) as priority,
                                COALESCE(t.doer_name, u.username, 'N/A') as doer_name, 
                                u.Status as doer_user_status,
                                CASE WHEN u.id IS NULL THEN 0 ELSE 1 END as doer_user_exists,
                                d.name as department_name, m.name as manager_name,
                                COALESCE(a.name, a.username, 'N/A') as assigned_by_name,
                                'delegation' as task_type
                         FROM tasks t 
                         LEFT JOIN users u ON t.doer_id = u.id
                         LEFT JOIN departments d ON t.department_id = d.id
                         LEFT JOIN users m ON t.manager_id = m.id
                         LEFT JOIN users a ON t.assigned_by = a.id" . $delegation_filter_conditions . "
                         ORDER BY IFNULL(t.priority, 0) DESC, t.planned_date DESC, t.id DESC";
    
    if (!empty($delegation_filter_param_types) && !empty($delegation_filter_params)) {
        // Use prepared statement for filtered query
        if ($stmt_delegation = mysqli_prepare($conn, $delegation_query)) {
            mysqli_stmt_bind_param($stmt_delegation, $delegation_filter_param_types, ...$delegation_filter_params);
            if (mysqli_stmt_execute($stmt_delegation)) {
                $delegation_result = mysqli_stmt_get_result($stmt_delegation);
                if ($delegation_result) {
                    while ($row = mysqli_fetch_assoc($delegation_result)) {
                        $row['priority'] = (int)($row['priority'] ?? 0);  // Normalize priority
                        $all_tasks[] = $row;
                    }
                }
            }
            mysqli_stmt_close($stmt_delegation);
        }
    } else {
        // Use regular query for admin or no filters
        $delegation_result = mysqli_query($conn, $delegation_query);
        if ($delegation_result) {
            while ($row = mysqli_fetch_assoc($delegation_result)) {
                $row['priority'] = (int)($row['priority'] ?? 0);  // Normalize priority
                $all_tasks[] = $row;
            }
        }
    }
    
    // 2. Fetch Checklist Tasks
    $checklist_filter_conditions = "";
    $checklist_filter_params = [];
    $checklist_filter_param_types = "";
    
    if (isManager() && !isAdmin()) {
        $current_manager_id = $_SESSION["id"];
        $current_manager_name = trim($_SESSION["name"] ?? '');
        $current_manager_username = trim($_SESSION["username"] ?? '');
        
        // Get all team member identifiers (same logic as FMS tasks)
        $manager_team_identifiers = getManagerFmsDoerIdentifiers(
            $conn,
            (int)$current_manager_id,
            $current_manager_name,
            $current_manager_username
        );
        
        // Build filter conditions:
        // 1. Tasks assigned to team members (assignee belongs to manager's team)
        // 2. Tasks assigned by this manager (assigned_by matches manager name/username)
        
        $conditions = [];
        $params = [];
        $param_types = "";
        
        // Condition 1: Assignee belongs to manager's team
        if (!empty($manager_team_identifiers)) {
            $placeholders = implode(',', array_fill(0, count($manager_team_identifiers), '?'));
            $conditions[] = "LOWER(TRIM(COALESCE(cs.assignee, ''))) IN ($placeholders)";
            $params = array_merge($params, $manager_team_identifiers);
            $param_types .= str_repeat('s', count($manager_team_identifiers));
        }
        
        // Condition 2: Task assigned by this manager (check both name and username)
        if (!empty($current_manager_name)) {
            $conditions[] = "LOWER(TRIM(COALESCE(cs.assigned_by, ''))) = LOWER(TRIM(?))";
            $params[] = $current_manager_name;
            $param_types .= "s";
        }
        if (!empty($current_manager_username) && $current_manager_username !== $current_manager_name) {
            $conditions[] = "LOWER(TRIM(COALESCE(cs.assigned_by, ''))) = LOWER(TRIM(?))";
            $params[] = $current_manager_username;
            $param_types .= "s";
        }
        
        if (!empty($conditions)) {
            $checklist_filter_conditions = " WHERE (" . implode(" OR ", $conditions) . ")";
            $checklist_filter_params = $params;
            $checklist_filter_param_types = $param_types;
        } else {
            // No team members found, show no tasks
            $checklist_filter_conditions = " WHERE 1 = 0";
        }
    }
    
    $checklist_query = "SELECT cs.id as task_id, cs.task_code as unique_id, cs.task_description as description,
                               cs.task_date as planned_date, '23:59:59' as planned_time,
                               cs.actual_date, cs.actual_time, COALESCE(cs.status, 'pending') as status,
                               cs.is_delayed, cs.delay_duration, cs.duration, cs.assignee as doer_name,
                               u.Status as doer_user_status,
                               CASE WHEN u.id IS NULL THEN 0 ELSE 1 END as doer_user_exists,
                               cs.department as department_name, cs.assigned_by, cs.frequency, 
                               COALESCE(cs.priority, 0) as priority, 'checklist' as task_type
                        FROM checklist_subtasks cs
                        LEFT JOIN users u ON LOWER(TRIM(cs.assignee)) = LOWER(TRIM(u.username)) OR LOWER(TRIM(cs.assignee)) = LOWER(TRIM(u.name))" . $checklist_filter_conditions . "
                        ORDER BY cs.priority DESC, cs.task_date DESC, cs.id DESC";
    
    // Execute checklist query
    if (!empty($checklist_filter_param_types) && !empty($checklist_filter_params)) {
        // Use prepared statement for filtered query
        if ($stmt_checklist = mysqli_prepare($conn, $checklist_query)) {
            mysqli_stmt_bind_param($stmt_checklist, $checklist_filter_param_types, ...$checklist_filter_params);
            if (mysqli_stmt_execute($stmt_checklist)) {
                $checklist_result = mysqli_stmt_get_result($stmt_checklist);
                if ($checklist_result) {
                    while ($row = mysqli_fetch_assoc($checklist_result)) {
                        // Ensure consistent id field
                        $row['id'] = $row['task_id'];
                        // Normalize status: trim and collapse spaces for consistent matching
                        if (!empty($row['status'])) {
                            $row['status'] = preg_replace('/\s+/', ' ', trim($row['status']));
                        }
                        // Real-time delay for checklist: pending + planned in past = delayed
                        $planned_date = $row['planned_date'] ?? '';
                        $planned_time = $row['planned_time'] ?? '23:59:59';
                        $actual_date = $row['actual_date'] ?? '';
                        $actual_time = $row['actual_time'] ?? '';
                        $status_lower = strtolower(trim($row['status'] ?? ''));
                        $is_completed = in_array($status_lower, ['completed', 'done']) || !empty($actual_date) || !empty($actual_time);
                        if (!empty($planned_date)) {
                            $planned_ts = strtotime($planned_date . ' ' . $planned_time);
                            if ($planned_ts !== false) {
                                if (!$is_completed) {
                                    $now = time();
                                    if ($now > $planned_ts) {
                                        $row['is_delayed'] = 1;
                                        $row['delay_duration'] = formatSecondsToHHMMSS($now - $planned_ts);
                                    }
                                } else {
                                    if (!empty($actual_date)) {
                                        $actual_ts = strtotime($actual_date . ' ' . (!empty($actual_time) ? $actual_time : '23:59:59'));
                                        if ($actual_ts !== false && $actual_ts > $planned_ts) {
                                            $row['is_delayed'] = 1;
                                            $row['delay_duration'] = formatSecondsToHHMMSS($actual_ts - $planned_ts);
                                        }
                                    }
                                }
                            }
                        }
                        $all_tasks[] = $row;
                    }
                }
            }
            mysqli_stmt_close($stmt_checklist);
        }
    } else {
        // Use regular query for admin or no filters
        $checklist_result = mysqli_query($conn, $checklist_query);
        if ($checklist_result) {
            while ($row = mysqli_fetch_assoc($checklist_result)) {
                // Ensure consistent id field
                $row['id'] = $row['task_id'];
                // Normalize status: trim and collapse spaces for consistent matching
                if (!empty($row['status'])) {
                    $row['status'] = preg_replace('/\s+/', ' ', trim($row['status']));
                }
                // Real-time delay for checklist: pending + planned in past = delayed
                $planned_date = $row['planned_date'] ?? '';
                $planned_time = $row['planned_time'] ?? '23:59:59';
                $actual_date = $row['actual_date'] ?? '';
                $actual_time = $row['actual_time'] ?? '';
                $status_lower = strtolower(trim($row['status'] ?? ''));
                $is_completed = in_array($status_lower, ['completed', 'done']) || !empty($actual_date) || !empty($actual_time);
                if (!empty($planned_date)) {
                    $planned_ts = strtotime($planned_date . ' ' . $planned_time);
                    if ($planned_ts !== false) {
                        if (!$is_completed) {
                            $now = time();
                            if ($now > $planned_ts) {
                                $row['is_delayed'] = 1;
                                $row['delay_duration'] = formatSecondsToHHMMSS($now - $planned_ts);
                            }
                        } else {
                            if (!empty($actual_date)) {
                                $actual_ts = strtotime($actual_date . ' ' . (!empty($actual_time) ? $actual_time : '23:59:59'));
                                if ($actual_ts !== false && $actual_ts > $planned_ts) {
                                    $row['is_delayed'] = 1;
                                    $row['delay_duration'] = formatSecondsToHHMMSS($actual_ts - $planned_ts);
                                }
                            }
                        }
                    }
                }
                $all_tasks[] = $row;
            }
        }
    }
    
    // 3. Fetch FMS Tasks
    $fms_filter_conditions = "";
    $fms_filter_params = [];
    $fms_filter_param_types = "";

    $manager_fms_identifiers = [];
    if (isManager() && !isAdmin()) {
        $current_manager_id = $_SESSION["id"];
        $current_manager_username = trim($_SESSION["username"] ?? '');
        $current_manager_name = trim($_SESSION["name"] ?? $current_manager_username);

        $manager_fms_identifiers = getManagerFmsDoerIdentifiers(
            $conn,
            (int)$current_manager_id,
            $current_manager_name,
            $current_manager_username
        );

        if (!empty($manager_fms_identifiers)) {
            $placeholders = implode(',', array_fill(0, count($manager_fms_identifiers), '?'));
            $fms_filter_conditions = " WHERE LOWER(TRIM(fms_tasks.doer_name)) IN ($placeholders)";
            $fms_filter_params = $manager_fms_identifiers;
            $fms_filter_param_types = str_repeat('s', count($manager_fms_identifiers));
        } else {
            $fms_filter_conditions = " WHERE 1 = 0";
        }
    }

    $fms_query = "SELECT fms_tasks.id, fms_tasks.unique_key, fms_tasks.step_name, fms_tasks.planned, fms_tasks.actual, fms_tasks.status, fms_tasks.duration, 
                         fms_tasks.doer_name, fms_tasks.department, fms_tasks.task_link, fms_tasks.sheet_label, fms_tasks.step_code, fms_tasks.is_delayed, fms_tasks.delay_duration, fms_tasks.client_name,
                         COALESCE(fms_tasks.priority, 0) as priority,
                         u.Status as doer_user_status,
                         CASE WHEN u.id IS NULL THEN 0 ELSE 1 END as doer_user_exists
                  FROM fms_tasks
                  LEFT JOIN users u ON LOWER(TRIM(fms_tasks.doer_name)) = LOWER(TRIM(u.username)) OR LOWER(TRIM(fms_tasks.doer_name)) = LOWER(TRIM(u.name))" . $fms_filter_conditions . "
                  ORDER BY fms_tasks.priority DESC, fms_tasks.imported_at DESC";
    
    if (!empty($fms_filter_param_types) && !empty($fms_filter_params)) {
        // Use prepared statement for filtered query
        if ($stmt_fms = mysqli_prepare($conn, $fms_query)) {
            mysqli_stmt_bind_param($stmt_fms, $fms_filter_param_types, ...$fms_filter_params);
            if (mysqli_stmt_execute($stmt_fms)) {
                $fms_result = mysqli_stmt_get_result($stmt_fms);
            } else {
                $fms_result = false;
            }
            mysqli_stmt_close($stmt_fms);
        } else {
            $fms_result = false;
        }
    } else {
        // Use regular query for admin or no filters
        $fms_result = mysqli_query($conn, $fms_query);
    }
    if ($fms_result) {
        while ($row = mysqli_fetch_assoc($fms_result)) {
            $planned_timestamp = parseFMSDateTimeString_doer($row['planned']);
            $actual_timestamp = parseFMSDateTimeString_doer($row['actual']);
            
            $delay_duration = $row['delay_duration']; // Get delay from DB first
            $is_delayed = $row['is_delayed'];

            // --- START: Full Delay Calculation for ALL FMS Tasks ---
            $delay_duration = null; // Reset for each task
            $is_delayed = 0;        // Reset for each task

            $completed_statuses = ['completed', 'done', 'not done', 'can not be done', 'yes'];
            $is_task_completed = in_array(strtolower($row['status'] ?? ''), $completed_statuses);
            // FMS: treat "has actual data" as completed for delay (matches fms_task.php; handles sheet statuses like "Yes")
            if (!$is_task_completed && $actual_timestamp && $planned_timestamp) {
                $is_task_completed = true;
            }

            if ($is_task_completed) {
                // LOGIC FOR COMPLETED TASKS: Compare actual vs. planned
                if ($actual_timestamp && $planned_timestamp && $actual_timestamp > $planned_timestamp) {
                    $delay_seconds = $actual_timestamp - $planned_timestamp;
                    $delay_duration = formatSecondsToHHMMSS($delay_seconds); // Use your existing formatting function
                    $is_delayed = 1;
                }
            } else {
                // LOGIC FOR PENDING TASKS: Compare current time vs. planned
                $current_time = time();
                if ($planned_timestamp && $current_time > $planned_timestamp) {
                    $delay_seconds = $current_time - $planned_timestamp;
                    $delay_duration = formatSecondsToHHMMSS($delay_seconds);
                    $is_delayed = 1;
                }
            }
            // --- END: Full Delay Calculation ---

            $fms_task = [
                'id' => $row['id'],
                'unique_id' => $row['unique_key'],
                'description' => $row['step_name'],
                'planned_date' => $planned_timestamp ? date('Y-m-d', $planned_timestamp) : '',
                'planned_time' => $planned_timestamp ? date('H:i:s', $planned_timestamp) : '',
                'actual_date' => $actual_timestamp ? date('Y-m-d', $actual_timestamp) : '',
                'actual_time' => $actual_timestamp ? date('H:i:s', $actual_timestamp) : '',
                'status' => $row['status'],
                'is_delayed' => $is_delayed, // Use the updated is_delayed value
                'delay_duration' => $delay_duration, // Use the updated delay_duration value
                'duration' => $row['duration'],
                'doer_name' => $row['doer_name'],
                'doer_user_status' => $row['doer_user_status'] ?? null,
                'doer_user_exists' => isset($row['doer_user_exists']) ? (int)$row['doer_user_exists'] : 0,
                'department_name' => $row['department'],
                'assigned_by' => $row['client_name'] ?? 'FMS System',
                'priority' => (int)($row['priority'] ?? 0),
                'task_type' => 'fms',
                'task_link' => $row['task_link'],
                'planned' => $row['planned'] 
            ];
            
            $all_tasks[] = $fms_task;
        }
    }
    
    // Update delay status for all tasks
    updateAllTasksDelayStatus($conn);
    
    // Debug logging removed - issue resolved
    
    // Filter tasks by doer_status (only for Admin/Manager)
    // This filters the tasks list to only show tasks assigned to users with the selected status
    // Only shows tasks from users that exist in the database and match the selected status
    if (isAdmin() || isManager()) {
        $all_tasks = array_filter($all_tasks, function($task) use ($doer_status) {
            // Get the doer's user status and existence flag from the task
            $task_doer_status = $task['doer_user_status'] ?? null;
            $doer_user_exists = isset($task['doer_user_exists']) ? (int)$task['doer_user_exists'] : 0;
            
            // Exclude tasks from users that don't exist in the database
            // These users won't appear in the dropdown, so their tasks shouldn't be shown
            if ($doer_user_exists === 0) {
                return false; // Exclude unknown users
            }
            
            // User exists in database - normalize and check status
            if (!empty($task_doer_status)) {
                $task_doer_status = ucfirst(strtolower(trim($task_doer_status)));
                // Validate status value
                if ($task_doer_status !== 'Active' && $task_doer_status !== 'Inactive') {
                    // Invalid status - exclude (user exists but has invalid status)
                    return false;
                }
                // Check if status matches the selected filter
                return ($task_doer_status === $doer_status);
            } else {
                // Status is null but user exists - treat as Active (matches COALESCE logic in dropdown query)
                // Only include if Active filter is selected
                return ($doer_status === 'Active');
            }
        });
        // Re-index array after filtering
        $all_tasks = array_values($all_tasks);
    }
    
    // Apply filters
    $filtered_tasks = $all_tasks;
    
    if (!empty($filter_status)) {
        $filtered_tasks = array_filter($filtered_tasks, function($task) use ($filter_status) {
            // Normalize status values for filtering - trim and normalize spaces
            $task_status_raw = $task['status'] ?? '';
            $task_status = strtolower(trim(preg_replace('/\s+/', ' ', $task_status_raw)));
            $filter_status_lower = strtolower(trim(preg_replace('/\s+/', ' ', $filter_status)));
            
            // Handle empty status
            if (empty($task_status) && $filter_status_lower !== 'pending') {
                return false;
            }
            
            // Handle FMS task status mapping
            if ($task['task_type'] === 'fms') {
                // For FMS tasks, map their status values to standard filter values
                $fms_status_mapping = [
                    'done' => 'completed',
                    'completed' => 'completed',
                    'not done' => 'not done',
                    'can not be done' => 'can not be done',
                    'can\'t be done' => 'can not be done',
                    'cant be done' => 'can not be done',
                    'pending' => 'pending',
                    'in progress' => 'pending'
                ];
                
                // If we have a mapping, use it; otherwise use the original status
                $normalized_status = $fms_status_mapping[$task_status] ?? $task_status;
                
                // Special case: FMS tasks with actual data are considered "completed"
                if ($filter_status_lower === 'completed' && $normalized_status !== 'completed') {
                    $has_actual_data = !empty($task['actual_date']) || !empty($task['actual_time']);
                    if ($has_actual_data) {
                        return true;
                    }
                }
                
                // Special case: FMS tasks without actual data are considered "pending"
                if ($filter_status_lower === 'pending' && $normalized_status !== 'pending') {
                    $has_actual_data = !empty($task['actual_date']) || !empty($task['actual_time']);
                    if (!$has_actual_data) {
                        return true;
                    }
                }
                
                return $normalized_status === $filter_status_lower;
            }
            
            // For non-FMS tasks, normalize common variations and use comparison
            // Handle variations like "can't be done" vs "can not be done"
            $status_variations = [
                'can\'t be done' => 'can not be done',
                'cant be done' => 'can not be done',
                'can not be done' => 'can not be done',
                'Can not be done' => 'Can not be done',
                'not done' => 'not done',
                'notdone' => 'not done',
                'not-done' => 'not done',
                'not_done' => 'not done',
                'pending' => 'pending',
                'completed' => 'completed',
                'shifted' => 'shifted'
            ];
            
            // Normalize task status
            $normalized_task_status = $status_variations[$task_status] ?? $task_status;
            
            // Additional flexible check: if status contains both "not" and "done" words, normalize to "not done"
            // This handles variations like "not  done" (double space), "not-done", "not_done", etc.
            if ($normalized_task_status !== 'not done') {
                $status_clean = preg_replace('/[_\-\s]+/', ' ', $task_status); // Replace underscores, hyphens, multiple spaces with single space
                $status_clean = trim($status_clean);
                if (preg_match('/\bnot\s+done\b|\bdone\s+not\b/i', $status_clean)) {
                    $normalized_task_status = 'not done';
                }
            }
            
            // Normalize filter status
            $normalized_filter_status = $status_variations[$filter_status_lower] ?? $filter_status_lower;
            
            // Additional flexible check for filter status too
            if ($normalized_filter_status !== 'not done') {
                $filter_clean = preg_replace('/[_\-\s]+/', ' ', $filter_status_lower);
                $filter_clean = trim($filter_clean);
                if (preg_match('/\bnot\s+done\b|\bdone\s+not\b/i', $filter_clean)) {
                    $normalized_filter_status = 'not done';
                }
            }
            
            return $normalized_task_status === $normalized_filter_status;
        });
    }
    
    if (!empty($filter_doer)) {
        $filtered_tasks = array_filter($filtered_tasks, function($task) use ($filter_doer) {
            // Handle null or empty doer_name values
            $doer_name = $task['doer_name'] ?? '';
            if (empty($doer_name)) {
                return false; // Exclude tasks without doer names when filtering by doer
            }
            return stripos($doer_name, $filter_doer) !== false;
        });
    }
    
    if (!empty($filter_id)) {
        $filtered_tasks = array_filter($filtered_tasks, function($task) use ($filter_id) {
            // Handle null or empty unique_id values
            $unique_id = $task['unique_id'] ?? '';
            if (empty($unique_id)) {
                return false; // Exclude tasks without unique IDs when filtering by ID
            }
            return stripos($unique_id, $filter_id) !== false;
        });
    }
    
    if (!empty($filter_description)) {
        $filtered_tasks = array_filter($filtered_tasks, function($task) use ($filter_description) {
            // Handle null or empty description values
            $description = $task['description'] ?? '';
            if (empty($description)) {
                return false; // Exclude tasks without descriptions when filtering by description
            }
            return stripos($description, $filter_description) !== false;
        });
    }
    
    if (!empty($filter_type)) {
        $filtered_tasks = array_filter($filtered_tasks, function($task) use ($filter_type) {
            return $task['task_type'] === $filter_type;
        });
    }
    
    // Date range filtering - Filter based on planned_date only (not actual_date)
    if (!empty($filter_date_from) || !empty($filter_date_to)) {
        $filtered_tasks = array_filter($filtered_tasks, function($task) use ($filter_date_from, $filter_date_to) {
            // Only check planned_date for filtering
            $from_ts = !empty($filter_date_from) ? strtotime($filter_date_from . ' 00:00:00') : 0;
            $to_ts = !empty($filter_date_to) ? strtotime($filter_date_to . ' 23:59:59') : PHP_INT_MAX;
            
            // Check planned_date only
            if (!empty($task['planned_date'])) {
                $planned_ts = strtotime($task['planned_date'] . ' 00:00:00');
                if ($planned_ts >= $from_ts && $planned_ts <= $to_ts) {
                    return true;
                }
            }
            
            // If no planned_date, exclude the task from filtered results
            return false;
        });
    }
    
    // Custom sorting function
    function customSort($a, $b, $column, $direction) {
        $val_a = $a[$column] ?? '';
        $val_b = $b[$column] ?? '';
        
        // Handle null values
        if (empty($val_a) && empty($val_b)) return 0;
        if (empty($val_a)) return $direction === 'asc' ? 1 : -1;
        if (empty($val_b)) return $direction === 'asc' ? -1 : 1;
        
        // Special handling for different data types
        if ($column === 'planned_date') {
            // Combine date and time for accurate sorting
            $time_a = $a['planned_time'] ?? '';
            $time_b = $b['planned_time'] ?? '';
            
            // Build datetime string: date + time (or default to 00:00:00 if time is empty)
            $datetime_a = $val_a . ' ' . (!empty($time_a) ? $time_a : '00:00:00');
            $datetime_b = $val_b . ' ' . (!empty($time_b) ? $time_b : '00:00:00');
            
            $val_a = strtotime($datetime_a);
            $val_b = strtotime($datetime_b);
            
            // Handle invalid timestamps (fallback to 0)
            if ($val_a === false) $val_a = 0;
            if ($val_b === false) $val_b = 0;
        } elseif ($column === 'actual_date') {
            // Combine date and time for accurate sorting
            $time_a = $a['actual_time'] ?? '';
            $time_b = $b['actual_time'] ?? '';
            
            // Build datetime string: date + time (or default to 00:00:00 if time is empty)
            $datetime_a = $val_a . ' ' . (!empty($time_a) ? $time_a : '00:00:00');
            $datetime_b = $val_b . ' ' . (!empty($time_b) ? $time_b : '00:00:00');
            
            $val_a = strtotime($datetime_a);
            $val_b = strtotime($datetime_b);
            
            // Handle invalid timestamps (fallback to 0)
            if ($val_a === false) $val_a = 0;
            if ($val_b === false) $val_b = 0;
        } elseif ($column === 'delay_duration') {
            // Convert delay strings to seconds for proper sorting
            $val_a = convertDelayToSeconds($val_a);
            $val_b = convertDelayToSeconds($val_b);
        }
        
        if ($val_a == $val_b) return 0;
        return ($val_a < $val_b) ? ($direction === 'asc' ? -1 : 1) : ($direction === 'asc' ? 1 : -1);
    }
    
    function convertDelayToSeconds($delay_str) {
        if (empty($delay_str)) return 0;
        
        // Parse delay string like "2 days, 3 hours, 15 minutes"
        $total_seconds = 0;
        if (preg_match('/(\d+)\s*days?/', $delay_str, $matches)) {
            $total_seconds += (int)$matches[1] * 24 * 3600;
        }
        if (preg_match('/(\d+)\s*hours?/', $delay_str, $matches)) {
            $total_seconds += (int)$matches[1] * 3600;
        }
        if (preg_match('/(\d+)\s*minutes?/', $delay_str, $matches)) {
            $total_seconds += (int)$matches[1] * 60;
        }
        
        return $total_seconds;
    }
    
    // Sort tasks - Priority tasks first, then by selected column
    usort($filtered_tasks, function($a, $b) use ($sort_column, $sort_direction) {
        // First, sort by priority (priority = 1 comes first)
        $priority_a = (int)($a['priority'] ?? 0);
        $priority_b = (int)($b['priority'] ?? 0);
        if ($priority_a != $priority_b) {
            return $priority_b <=> $priority_a; // Descending: 1 comes before 0
        }
        // Then sort by selected column
        return customSort($a, $b, $sort_column, $sort_direction);
    });
    
    // Filter priority tasks separately (for Priority Tasks tab)
    $priority_tasks = array_filter($filtered_tasks, function($task) {
        return (int)($task['priority'] ?? 0) === 1;
    });
    // Re-index array after filtering
    $priority_tasks = array_values($priority_tasks);
    
    // Sort priority tasks - priority tasks already at top, but ensure they're sorted
    usort($priority_tasks, function($a, $b) use ($sort_column, $sort_direction) {
        return customSort($a, $b, $sort_column, $sort_direction);
    });
    
    // Pagination for All Tasks
    $total_filtered_tasks = count($filtered_tasks);
    $total_pages = max(1, ceil($total_filtered_tasks / $tasks_per_page));
    // Ensure current page is within valid range
    if ($current_page_num > $total_pages) {
        $current_page_num = 1;
        $offset = 0;
    }
    $paginated_tasks = array_slice($filtered_tasks, $offset, $tasks_per_page);
    
    // Calculate display range
    $display_start = $total_filtered_tasks > 0 ? $offset + 1 : 0;
    $display_end = min($offset + $tasks_per_page, $total_filtered_tasks);
    
    // Pagination for Priority Tasks
    $priority_current_page = isset($_GET['priority_page']) ? max(1, (int)$_GET['priority_page']) : 1;
    $priority_offset = ($priority_current_page - 1) * $tasks_per_page;
    $total_priority_tasks = count($priority_tasks);
    $priority_total_pages = max(1, ceil($total_priority_tasks / $tasks_per_page));
    // Ensure priority current page is within valid range
    if ($priority_current_page > $priority_total_pages) {
        $priority_current_page = 1;
        $priority_offset = 0;
    }
    $paginated_priority_tasks = array_slice($priority_tasks, $priority_offset, $tasks_per_page);
    
    // Calculate priority display range
    $priority_display_start = $total_priority_tasks > 0 ? $priority_offset + 1 : 0;
    $priority_display_end = min($priority_offset + $tasks_per_page, $total_priority_tasks);
    
    // Get unique values for filter dropdowns
    $statuses = array_unique(array_column($all_tasks, 'status'));
    $statuses = array_filter($statuses, function($status) {
        return !empty($status);
    });
    sort($statuses);
    
    $departments = array_unique(array_column($all_tasks, 'department_name'));
    $departments = array_filter($departments, function($dept) {
        return !empty($dept);
    });
    sort($departments);
    
    // Get doers from users table (filtered by doer_status for Admin/Manager)
    $doers = [];
    if (isAdmin() || isManager()) {
        // Query users table directly, filtered by doer_status
        // Only get username (not name) to show in dropdown
        // Exclude client users from the filter dropdown
        $doers_sql = "SELECT DISTINCT u.username 
                      FROM users u 
                      WHERE COALESCE(u.Status, 'Active') = ? 
                        AND u.username IS NOT NULL 
                        AND u.username != ''
                        AND u.user_type != 'client'
                      ORDER BY u.username ASC";
        if ($stmt_doers = mysqli_prepare($conn, $doers_sql)) {
            mysqli_stmt_bind_param($stmt_doers, "s", $doer_status);
            if (mysqli_stmt_execute($stmt_doers)) {
                $doers_result = mysqli_stmt_get_result($stmt_doers);
                if ($doers_result) {
                    while ($doer_row = mysqli_fetch_assoc($doers_result)) {
                        // Only add username to the list
                        if (!empty($doer_row['username']) && trim($doer_row['username']) !== '') {
                            $doers[] = trim($doer_row['username']);
                        }
                    }
                    sort($doers);
                }
            }
            mysqli_stmt_close($stmt_doers);
        }
    } else {
        // For Doer users, use existing logic (from tasks)
        // Filter out client users by checking users table
        $doers = array_unique(array_column($all_tasks, 'doer_name'));
        $doers = array_filter($doers, function($doer) use ($conn) {
            if (empty($doer) || $doer === null || trim($doer) === '') {
                return false;
            }
            // Check if this doer is a client user
            $check_sql = "SELECT user_type FROM users WHERE (username = ? OR name = ?) AND user_type = 'client' LIMIT 1";
            if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
                $trimmed_doer = trim($doer);
                mysqli_stmt_bind_param($check_stmt, "ss", $trimmed_doer, $trimmed_doer);
                if (mysqli_stmt_execute($check_stmt)) {
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    if (mysqli_num_rows($check_result) > 0) {
                        mysqli_stmt_close($check_stmt);
                        return false; // Exclude client users
                    }
                }
                mysqli_stmt_close($check_stmt);
            }
            return true;
        });
        sort($doers);
    }
    
    // Calculate summary statistics using simple logic (no week-based complexity)
    $summary_stats = [
        'total_tasks' => $total_filtered_tasks,
        'completed_tasks' => count(array_filter($filtered_tasks, function($task) {
            // Must have actual date or time (task was actually completed)
            $actual_date = $task['actual_date'] ?? '';
            $actual_time = $task['actual_time'] ?? '';
            if (empty($actual_date) && empty($actual_time)) {
                return false;
            }
            // Normalize status: trim and collapse spaces
            $status = preg_replace('/\s+/', ' ', strtolower(trim($task['status'] ?? '')));
            $completed_statuses = ['completed', 'done'];
            // FMS sheet may store completion as "Yes"
            if (($task['task_type'] ?? '') === 'fms') {
                $completed_statuses[] = 'yes';
            }
            return in_array($status, $completed_statuses);
        })),
        'delayed_tasks' => count(array_filter($filtered_tasks, function($task) {
            // Exclude "can't be done" tasks
            $status = strtolower(trim($task['status'] ?? ''));
            if (in_array($status, ["can't be done", "can not be done", "cant be done"])) {
                return false;
            }
            
            $planned_date = $task['planned_date'] ?? '';
            $planned_time = $task['planned_time'] ?? '';
            $actual_date = $task['actual_date'] ?? '';
            $actual_time = $task['actual_time'] ?? '';
            
            if (empty($planned_date)) {
                return false;
            }
            
            // Build planned timestamp
            $planned_datetime = $planned_date;
            if (!empty($planned_time)) {
                $planned_datetime .= ' ' . $planned_time;
            } else {
                $planned_datetime .= ' 23:59:59';
            }
            $planned_ts = strtotime($planned_datetime);
            if ($planned_ts === false) {
                return false;
            }
            
            $now = time();
            
            // Delayed if current time is past planned datetime
            if ($now > $planned_ts) {
                // Exclude tasks completed on time or early (actual <= planned)
                $is_completed = in_array($status, ['completed', 'done']);
                if (($task['task_type'] ?? '') === 'fms') {
                    $is_completed = $is_completed || ($status === 'yes');
                }
                if ($is_completed && !empty($actual_date)) {
                    $actual_datetime = $actual_date;
                    if (!empty($actual_time)) {
                        $actual_datetime .= ' ' . $actual_time;
                    } else {
                        $actual_datetime .= ' 23:59:59';
                    }
                    $actual_ts = strtotime($actual_datetime);
                    if ($actual_ts !== false && $actual_ts <= $planned_ts) {
                        return false; // Completed on time or early — not delayed
                    }
                }
                return true;
            }
            
            return false;
        })),
        'pending_tasks' => count(array_filter($filtered_tasks, function($task) {
            // Exclude "can't be done" tasks (all spelling variants)
            $status = preg_replace('/\s+/', ' ', strtolower(trim($task['status'] ?? '')));
            $cant_be_done = ["can't be done", "can not be done", "cant be done", "cannot be done", "cant_be_done"];
            if (in_array($status, $cant_be_done)) {
                return false;
            }
            
            // If task status is "not done", always count it as pending (regardless of task type or actual data)
            $not_done_statuses = ['not done', 'notdone'];
            if (in_array($status, $not_done_statuses)) {
                return true;
            }
            
            // Simple pending logic: no actual date/time yet (not completed)
            $actual_date = $task['actual_date'] ?? '';
            $actual_time = $task['actual_time'] ?? '';
            
            // For FMS tasks, check if they have actual data
            if ($task['task_type'] === 'fms') {
                $has_actual_data = !empty($actual_date) || !empty($actual_time);
                return !$has_actual_data;
            }
            
            // For other tasks, check if there's no actual date/time
            return empty($actual_date) && empty($actual_time);
        }))
    ];
    
    $tasks = $paginated_tasks;
    $total_tasks = $total_filtered_tasks;
    
    // Priority tasks variables for tab
    $priority_tasks_display = $paginated_priority_tasks;
    $priority_total_tasks = $total_priority_tasks;
    $priority_total_pages_var = $priority_total_pages;
    $priority_current_page_var = $priority_current_page;
    
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $total_tasks = 0;
    $total_pages = 0;
    $tasks = [];
    $statuses = [];
    $departments = [];
    $doers = [];
    $summary_stats = [
        'total_tasks' => 0,
        'completed_tasks' => 0,
        'delayed_tasks' => 0,
        'pending_tasks' => 0
    ];
    // Initialize priority tasks variables
    $priority_tasks_display = [];
    $priority_total_tasks = 0;
    $priority_total_pages_var = 0;
    $priority_current_page_var = 1;
}

// Header already included at the top
?>

<style>
    /* Custom styling for Manage Tasks page cards - Dark Theme */
    .manage-tasks-page {
        background: transparent;
        color: var(--dark-text-primary);
        min-height: 100vh;
    }

    .manage-tasks-page .summary-card {
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--glass-shadow);
        transition: var(--transition-normal);
        position: relative;
        overflow: hidden;
        background: var(--dark-bg-card);
        backdrop-filter: var(--glass-blur);
    }

    .manage-tasks-page .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        border-color: var(--brand-primary);
    }

    /* Dark theme color scheme for manage tasks cards */
    .manage-tasks-page .summary-card.bg-primary {
        background: var(--gradient-primary) !important;
        border-color: var(--brand-primary);
    }

    .manage-tasks-page .summary-card.bg-success {
        background: var(--gradient-secondary) !important;
        border-color: var(--brand-success);
    }

    .manage-tasks-page .summary-card.bg-danger {
        background: var(--gradient-accent) !important;
        border-color: var(--brand-danger);
    }

    .manage-tasks-page .summary-card.bg-warning {
        background: linear-gradient(135deg, var(--brand-warning) 0%, #e0a800 100%) !important;
        border-color: var(--brand-warning);
    }

    /* Subtle animation for manage tasks cards */
    .manage-tasks-page .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.6s ease;
    }

    .manage-tasks-page .summary-card:hover::before {
        left: 100%;
    }

    /* Dark theme typography for manage tasks */
    .manage-tasks-page .summary-card .card-title {
        font-size: 1.8rem;
        font-weight: 600;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .summary-card .card-text {
        font-size: 0.9rem;
        font-weight: 500;
        opacity: 0.9;
        color: var(--dark-text-secondary);
    }

    /* Main card styling for manage tasks - Dark Theme */
    .manage-tasks-page .card {
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--glass-shadow);
        background: var(--dark-bg-card);
        backdrop-filter: var(--glass-blur);
    }

    .manage-tasks-page .card-header {
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        border-bottom: 1px solid var(--glass-border);
        background: var(--gradient-primary);
        color: var(--dark-text-primary);
    }

    /* Table styling for manage tasks - Dark Theme */
    .manage-tasks-page .table {
        border-radius: var(--radius-md);
        overflow: hidden;
        box-shadow: none;
        border: 1px solid var(--glass-border);
        background: var(--dark-bg-card);
    }

    .manage-tasks-page .table thead th {
        background: var(--dark-bg-tertiary);
        color: var(--dark-text-primary);
        border-color: var(--glass-border);
        font-weight: 600;
    }

.manage-tasks-page .table thead th a.text-white {
        color: var(--dark-text-primary) !important;
    }

    .manage-tasks-page .table tbody tr {
        background: var(--dark-bg-card);
        border-bottom: 1px solid var(--glass-border);
        transition: var(--transition-normal);
}

    .manage-tasks-page .table tbody tr:hover {
        background: var(--dark-bg-glass-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .manage-tasks-page .table tbody td {
        color: var(--dark-text-secondary);
        border-color: var(--glass-border);
    }

    /* Button styling for manage tasks - Dark Theme */
    .manage-tasks-page .btn-primary {
        background: var(--gradient-primary);
        border: none;
        border-radius: var(--radius-md);
        font-weight: 500;
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .btn-primary:hover {
        background: linear-gradient(135deg, #5b5fdb 0%, #7c3aed 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .btn-light {
        background: var(--dark-bg-glass);
        border: 1px solid var(--glass-border);
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .btn-light:hover {
        background: var(--dark-bg-glass-hover);
        border-color: var(--brand-primary);
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .btn-outline-secondary {
        background: var(--dark-bg-glass);
        border: 1px solid var(--glass-border);
        color: var(--dark-text-secondary);
    }

    .manage-tasks-page .btn-outline-secondary:hover {
        background: var(--dark-bg-glass-hover);
        border-color: var(--brand-primary);
        color: var(--dark-text-primary);
    }

    /* Filter Section Styling - Dark Theme */
    .manage-tasks-page .filter-section {
        background: var(--dark-bg-card);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        backdrop-filter: var(--glass-blur);
    }

    .manage-tasks-page .filter-header {
        background: var(--gradient-primary);
        color: var(--dark-text-primary);
        padding: 0.75rem 1rem;
        border-radius: var(--radius-md);
        margin: -1.5rem -1.5rem 1rem -1.5rem;
    }

    .manage-tasks-page .filter-header h6 {
        color: var(--dark-text-primary) !important;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .manage-tasks-page .filter-header button {
        background: var(--dark-bg-glass);
        border: 1px solid var(--glass-border);
        color: var(--dark-text-primary);
        transition: var(--transition-normal);
    }

    .manage-tasks-page .filter-header button:hover {
        background: var(--brand-primary) !important;
        border-color: var(--brand-primary) !important;
        color: var(--dark-text-primary) !important;
    }

    .manage-tasks-page .filter-content {
        transition: var(--transition-normal);
        overflow: hidden;
        padding: 1rem;
    }

    .manage-tasks-page .filter-content.collapsed {
        max-height: 0;
        padding: 0;
        margin: 0;
    }

    .manage-tasks-page .filter-form .form-label {
        font-weight: 500;
        margin-bottom: 0.25rem;
        color: var(--dark-text-secondary);
    }

    .manage-tasks-page .filter-form .form-control {
        background: var(--dark-bg-glass);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        color: var(--dark-text-primary);
        transition: var(--transition-normal);
        padding: 0.75rem 0.75rem; /* Increased padding for better text visibility */
        line-height: 1.6 !important; /* Increased line-height to prevent text cutoff */
        min-height: 2.5rem; /* Minimum height to prevent text cutoff */
    }

    .manage-tasks-page .filter-form .form-control:focus {
        background: var(--dark-bg-glass-hover);
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .filter-form .form-control::placeholder {
        color: var(--dark-text-muted);
        opacity: 0.8;
        font-style: italic;
        font-size: 0.9rem;
        line-height: 1.5;
    }

    /* Specific styling for small form controls */
    .manage-tasks-page .filter-form .form-control-sm {
        padding: 0.5rem 0.75rem; /* Adequate padding for small controls */
        min-height: 2.25rem; /* Increased minimum height for small controls */
        line-height: 1.6 !important; /* Increased line-height to prevent text cutoff */
    }

    .manage-tasks-page .filter-form .form-control-sm::placeholder {
        color: var(--dark-text-muted);
        opacity: 0.8;
        font-style: italic;
        font-size: 0.85rem;
        line-height: 1.4;
    }

    /* Ensure select elements have proper spacing */
    .manage-tasks-page .filter-form select.form-control {
        padding: 0.75rem 2rem 0.75rem 0.75rem; /* Extra padding for dropdown arrow */
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.75rem center;
        background-repeat: no-repeat;
        background-size: 1rem;
        appearance: none;
    }

    .manage-tasks-page .filter-form select.form-control-sm {
        padding: 0.5rem 1.75rem 0.5rem 0.75rem; /* Adjusted for small select */
        background-position: right 0.5rem center;
        background-size: 0.875rem;
        line-height: 1.6 !important; /* Increased line-height to prevent text cutoff */
        min-height: 2.25rem !important; /* Increased minimum height */
    }

    /* Fix for date inputs */
    .manage-tasks-page .filter-form input[type="date"] {
        padding: 0.75rem 0.75rem;
        min-height: 2.5rem;
        line-height: 1.5;
    }

    .manage-tasks-page .filter-form input[type="date"].form-control-sm {
        padding: 0.5rem 0.75rem;
        min-height: 2rem;
        line-height: 1.4;
    }

    .manage-tasks-page .filter-form .btn-primary {
        background: var(--gradient-primary);
        border: none;
        border-radius: var(--radius-md);
        font-weight: 500;
        padding: 0.5rem 1.5rem;
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .filter-form .btn-outline-secondary {
        background: var(--dark-bg-glass);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        font-weight: 500;
        padding: 0.5rem 1.5rem;
        color: var(--dark-text-secondary);
    }

    .manage-tasks-page .filter-form .btn-outline-secondary:hover {
        background: var(--dark-bg-glass-hover);
        border-color: var(--brand-primary);
        color: var(--dark-text-primary);
    }

    /* Force full width on the Manage Tasks page content area */
.manage-tasks-page .container {
    max-width: 120% !important;
    padding-left: 05px;
    padding-right: 05px;
}

    .manage-tasks-page .container-fluid {
    max-width: 120% !important;
}

    /* Fix for table column widths to prevent overlap */
#manage-tasks-table th[data-column="status"],
#manage-tasks-table td.status-column {
    width: 6% !important; /* Make Status column narrow */
}

#manage-tasks-table th[data-column="delay_duration"],
#manage-tasks-table td:nth-of-type(9) { /* 9th column is Delay (after priority) */
    width: 9% !important; /* Give space to Delay */
}

#manage-tasks-table th[data-column="duration"],
#manage-tasks-table td:nth-of-type(10) { /* 10th column is Duration */
    width: 8% !important; /* Give space to Duration */
}

/* Ensure Action column remains visible with full text */
#manage-tasks-table th:last-child,
#manage-tasks-table td:last-child {
    min-width: 140px !important;
    width: 12% !important;
}

    /* Doer Filter Styling - Dark Theme */
    /* Doer Status Toggle Styling */
    .manage-tasks-page .doer-status-toggle {
        width: 100%;
    }
    
    .manage-tasks-page .doer-status-toggle .btn-group {
        display: flex;
        border-radius: var(--radius-sm);
        overflow: hidden;
    }
    
    .manage-tasks-page .doer-status-toggle .btn {
        border: 1px solid var(--glass-border);
        background: var(--dark-bg-card);
        color: var(--dark-text-primary);
        transition: all 0.3s ease;
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
    }
    
    .manage-tasks-page .doer-status-toggle .btn-outline-primary {
        color: var(--dark-text-secondary);
        border-color: var(--glass-border);
    }
    
    .manage-tasks-page .doer-status-toggle .btn-outline-primary:hover {
        background: var(--dark-bg-hover);
        border-color: var(--brand-primary);
        color: var(--brand-primary);
    }
    
    .manage-tasks-page .doer-status-toggle .btn-primary {
        background: var(--brand-primary);
        border-color: var(--brand-primary);
        color: white;
    }
    
    .manage-tasks-page .doer-status-toggle .btn-primary:hover {
        background: var(--brand-accent);
        border-color: var(--brand-accent);
    }
    
    .manage-tasks-page .doer-status-toggle input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    
    .manage-tasks-page .doer-filter-container {
        position: relative;
    }

    .manage-tasks-page .doer-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--dark-bg-card);
        border: 1px solid var(--glass-border);
        border-top: none;
        border-radius: 0 0 var(--radius-md) var(--radius-md);
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: var(--glass-shadow);
        backdrop-filter: var(--glass-blur);
    }

    .manage-tasks-page .doer-dropdown-item {
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        border-bottom: 1px solid var(--glass-border);
        transition: var(--transition-normal);
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .doer-dropdown-item:hover {
        background: var(--dark-bg-glass-hover);
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .doer-dropdown-item:last-child {
        border-bottom: none;
    }

    .manage-tasks-page .doer-dropdown-item.selected {
        background: rgba(99, 102, 241, 0.2);
        color: var(--brand-primary);
    }

    /* Alert styling - Dark Theme */
    .manage-tasks-page .alert {
        background: var(--dark-bg-card);
        border: 1px solid var(--glass-border);
        color: var(--dark-text-primary);
        border-radius: var(--radius-md);
        backdrop-filter: var(--glass-blur);
    }

    .manage-tasks-page .alert-success {
        background: var(--gradient-secondary);
        border-color: var(--brand-success);
    }

    .manage-tasks-page .alert-danger {
        background: var(--gradient-accent);
        border-color: var(--brand-danger);
    }

    .manage-tasks-page .alert-info {
        background: var(--gradient-primary);
        border-color: var(--brand-primary);
    }

    /* Pagination styling - Dark Theme */
    .manage-tasks-page .pagination .page-link {
        background: var(--dark-bg-glass);
        border: 1px solid var(--glass-border);
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .pagination .page-link:hover {
        background: var(--dark-bg-glass-hover);
        border-color: var(--brand-primary);
        color: var(--dark-text-primary);
    }

    .manage-tasks-page .pagination .page-item.active .page-link {
        background: var(--gradient-primary);
        border-color: var(--brand-primary);
        color: var(--dark-text-primary);
    }

    /* Badge styling - Dark Theme */
    .manage-tasks-page .badge {
        background: var(--gradient-primary);
        color: var(--dark-text-primary);
        font-weight: 500;
        border-radius: var(--radius-sm);
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    /* Status select styling - Dark Theme */
    .manage-tasks-page .status-select {
        background: var(--dark-bg-glass);
        border: 1px solid var(--glass-border);
        color: var(--dark-text-primary);
        border-radius: var(--radius-sm);
    }

    .manage-tasks-page .status-select:focus {
        background: var(--dark-bg-glass-hover);
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
    }

    .manage-tasks-page .status-select option {
        background: var(--dark-bg-card);
        color: var(--dark-text-primary);
    }

    /* Delay hover styling - Dark Theme */
    #manage-tasks-table .delay-hover {
        cursor: pointer;
        border-bottom: 1px dotted var(--brand-danger) !important;
        text-decoration: none !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 80px;
        display: inline-block;
        position: relative;
        color: var(--brand-danger) !important;
    }

    #manage-tasks-table .delay-hover:hover {
        text-decoration: none;
        color: var(--brand-danger) !important;
    }

    /* Custom tooltip box - Dark Theme */
    #manage-tasks-table .delay-hover::after {
        content: attr(data-full-delay);
        position: absolute;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
        color: var(--dark-text-primary);
        padding: 8px 12px;
        border-radius: var(--radius-sm);
        font-size: 13px;
        white-space: nowrap;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition-normal);
        pointer-events: none;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        border: 1px solid var(--glass-border);
    }

    /* Custom tooltip arrow - Dark Theme */
    #manage-tasks-table .delay-hover::before {
        content: '';
        position: absolute;
        bottom: 115%;
        left: 50%;
        transform: translateX(-50%);
        border: 5px solid transparent;
        border-top-color: #1a1a1a;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition-normal);
        pointer-events: none;
    }

    /* Show tooltip on hover */
    #manage-tasks-table .delay-hover:hover::after,
    #manage-tasks-table .delay-hover:hover::before {
        opacity: 1;
        visibility: visible;
    }

    /* Description hover styling - Dark Theme */
    .description-hover {
        cursor: pointer;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 150px;
        display: inline-block;
        position: relative;
        width: 100%;
        color: var(--dark-text-secondary);
    }

    .description-hover:hover {
        text-decoration: none;
        color: var(--dark-text-primary);
    }

    /* Custom tooltip for description - Dark Theme */
    .description-hover::after {
        content: attr(data-full-description);
        position: absolute;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
        color: var(--dark-text-primary);
        padding: 8px 12px;
        border-radius: var(--radius-sm);
        font-size: 13px;
        white-space: nowrap;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition-normal);
        pointer-events: none;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        border: 1px solid var(--glass-border);
    }

    .description-hover::before {
        content: '';
        position: absolute;
        bottom: 115%;
        left: 50%;
        transform: translateX(-50%);
        border: 5px solid transparent;
        border-top-color: #1a1a1a;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition-normal);
        pointer-events: none;
    }

    .description-hover:hover::after,
    .description-hover:hover::before {
        opacity: 1;
        visibility: visible;
    }

    /* Date picker clickable enhancement - Dark Theme */
    .date-picker-clickable {
        cursor: pointer;
        background: var(--dark-bg-glass);
        border: 1px solid var(--glass-border);
        color: var(--dark-text-primary);
    }

    .date-picker-clickable:focus {
        background: var(--dark-bg-glass-hover);
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
    }

    .date-picker-clickable::-webkit-calendar-picker-indicator {
        cursor: pointer;
        opacity: 1;
        filter: invert(1);
    }

    .date-picker-clickable::-webkit-calendar-picker-indicator:hover {
        background-color: var(--dark-bg-glass-hover);
        border-radius: var(--radius-sm);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .manage-tasks-page .summary-card .card-title {
            font-size: 1.5rem;
        }
        
        .manage-tasks-page .summary-card {
            margin-bottom: 1rem;
        }

        .manage-tasks-page .filter-section {
            padding: 1rem;
        }

        .manage-tasks-page .filter-header {
            margin: -1rem -1rem 1rem -1rem;
            padding: 0.5rem 1rem;
        }

        .manage-tasks-page .filter-form .col-md-3 {
            margin-bottom: 1rem;
        }
    }

    /* Text color adjustments for dark theme */
    .manage-tasks-page .text-muted {
        color: var(--dark-text-muted) !important;
    }

    .manage-tasks-page .text-success {
        color: var(--brand-success) !important;
    }

    .manage-tasks-page .text-danger {
        color: var(--brand-danger) !important;
    }

    .manage-tasks-page .text-warning {
        color: var(--brand-warning) !important;
    }

    .manage-tasks-page .text-info {
        color: var(--brand-accent) !important;
    }

    /* Small text styling */
    .manage-tasks-page small {
        color: var(--dark-text-secondary);
    }

    .manage-tasks-page .font-weight-bold {
        color: var(--dark-text-primary);
        font-weight: 600;
    }

    /* Additional fixes for placeholder text visibility */
    .manage-tasks-page .filter-form input[name="task_id"]::placeholder,
    .manage-tasks-page .filter-form input[name="task_name"]::placeholder {
        color: var(--dark-text-muted) !important;
        opacity: 0.8 !important;
        font-style: italic;
        font-size: 0.9rem;
        line-height: 1.5;
        padding-top: 0.25rem; /* Extra padding to prevent cutoff */
    }

    /* Ensure select placeholder options are visible */
    .manage-tasks-page .filter-form select option[value=""] {
        color: var(--dark-text-muted) !important;
        font-style: italic;
        opacity: 0.8;
    }

    /* Fix for all form controls in filter section */
    .manage-tasks-page .filter-form .form-control,
    .manage-tasks-page .filter-form input,
    .manage-tasks-page .filter-form select {
        vertical-align: middle;
        display: flex;
        align-items: center;
    }

    /* Ensure proper text alignment */
    .manage-tasks-page .filter-form .form-control::placeholder {
        vertical-align: middle;
        display: inline-block;
        line-height: normal;
    }

    /* Priority Star Styling - Golden Yellow */
    .manage-tasks-page .priority-star {
        color: rgba(255, 255, 255, 0.3);
        font-size: 1.1rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .manage-tasks-page .priority-star:hover {
        color: #fbbf24;
        transform: scale(1.2);
        filter: drop-shadow(0 0 4px rgba(251, 191, 36, 0.6));
    }

    .manage-tasks-page .priority-star.active {
        color: #fbbf24;
        filter: drop-shadow(0 0 6px rgba(251, 191, 36, 0.8));
        animation: priority-star-pulse 2s ease-in-out infinite;
    }

    .manage-tasks-page .priority-star.active:hover {
        color: #f59e0b;
        transform: scale(1.3);
        filter: drop-shadow(0 0 8px rgba(251, 191, 36, 1));
    }

    @keyframes priority-star-pulse {
        0%, 100% {
            filter: drop-shadow(0 0 6px rgba(251, 191, 36, 0.8));
        }
        50% {
            filter: drop-shadow(0 0 10px rgba(251, 191, 36, 1));
        }
    }

    .manage-tasks-page .priority-star-cell {
        min-width: 40px;
    }

    /* Table layout fixes to prevent horizontal scroll */
    .manage-tasks-page .table-responsive {
        overflow-x: visible !important;
    }
    
    /* Ensure table fits without horizontal scroll */
    #manage-tasks-table,
    #priority-tasks-table {
        table-layout: fixed;
        width: 100%;
    }
    
    #manage-tasks-table td,
    #manage-tasks-table th {
        font-size: 0.95rem !important; /* Increased text size */
        padding: 0.5rem 0.4rem !important; /* Slightly more padding */
    }
    
    /* Table headers - no ellipsis, allow full text visibility */
    #manage-tasks-table thead th {
        font-size: 0.75rem !important; /* Reduced from 0.9rem for better readability and spacing */
        font-weight: 600 !important;
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
    }
    
    /* Ensure sortable headers don't show ellipsis after sort icons */
    #manage-tasks-table thead th .sortable-header {
        font-size: 0.75rem !important; /* Match header font size */
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
        max-width: none !important;
    }
    
    /* Specifically fix Actual and Status columns - no ellipsis after sort icons */
    #manage-tasks-table th[data-column="actual_date"] .sortable-header,
    #manage-tasks-table th[data-column="status"] .sortable-header {
        font-size: 0.75rem !important; /* Match header font size */
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
        max-width: none !important;
    }
    
    #manage-tasks-table th[data-column="actual_date"],
    #manage-tasks-table th[data-column="status"] {
        font-size: 0.75rem !important; /* Match header font size */
        overflow: visible !important;
        text-overflow: clip !important;
    }
    
    /* Table body cells - allow wrapping for description, nowrap for others */
    #manage-tasks-table tbody td {
        white-space: nowrap;
        overflow: visible;
        text-overflow: clip;
    }
    
    /* Increase text size for table body cells */
    #manage-tasks-table tbody td {
        font-size: 0.95rem !important;
    }
    
    /* Increase button text size in Action column and ensure full visibility */
    #manage-tasks-table td:last-child .btn,
    #manage-tasks-table td:last-child .form-control-sm {
        font-size: 0.85rem !important;
        padding: 0.3rem 0.6rem !important;
        min-width: 110px !important;
        width: auto !important;
        white-space: nowrap !important;
        overflow: visible !important;
    }
    
    /* Ensure status select dropdown shows full text - apply to both tables */
    #manage-tasks-table td:last-child .status-select,
    #priority-tasks-table td:last-child .status-select {
        min-width: 120px !important;
        width: auto !important;
        padding-right: 25px !important;
    }
    
    /* Fix clipped text in Priority Tasks table action column dropdown */
    #priority-tasks-table td:last-child .status-select {
        font-size: 0.8rem !important;
        padding: 0.25rem 0.4rem !important;
        padding-right: 20px !important;
        line-height: 1.3 !important;
    }
    
    /* Fix clipped text in "Show:" dropdown toggles */
    #recordLimit,
    #priorityRecordLimit {
        font-size: 0.85rem !important;
        padding: 0.3rem 0.5rem !important;
        padding-right: 20px !important;
        line-height: 1.3 !important;
    }
    
    /* ID column styling - prevent overlap with Description */
    #manage-tasks-table td:nth-child(2),
    #manage-tasks-table th:nth-child(2),
    #priority-tasks-table td:nth-child(2),
    #priority-tasks-table th:nth-child(2) {
        min-width: 120px !important;
        width: 11% !important;
        padding-right: 0.75rem !important;
        white-space: normal !important;
        word-wrap: break-word !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        vertical-align: top !important;
    }
    
    /* ID column header - ensure it doesn't wrap */
    #manage-tasks-table th:nth-child(2),
    #priority-tasks-table th:nth-child(2) {
        white-space: nowrap !important;
    }
    
    /* ID column content - ensure badge and text fit properly */
    #manage-tasks-table td:nth-child(2) small,
    #priority-tasks-table td:nth-child(2) small {
        display: block;
        line-height: 1.4;
        word-break: break-word;
    }
    
    /* Allow description to wrap for better fit - Delegation Tasks Description column */
    #manage-tasks-table td:nth-child(3),
    #manage-tasks-table th:nth-child(3),
    #priority-tasks-table td:nth-child(3),
    #priority-tasks-table th:nth-child(3) {
        white-space: normal !important;
        word-wrap: break-word !important;
        overflow: visible !important;
        text-overflow: clip !important;
        max-width: none !important;
        padding-left: 0.75rem !important;
    }
    
    /* Ensure description header doesn't clip */
    #manage-tasks-table th:nth-child(3),
    #priority-tasks-table th:nth-child(3) {
        font-size: 0.75rem !important; /* Match header font size */
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
    }
    
    /* Ensure action column is always visible - also apply to priority tasks table */
    #manage-tasks-table td:last-child,
    #manage-tasks-table th:last-child,
    #priority-tasks-table td:last-child,
    #priority-tasks-table th:last-child {
        white-space: nowrap;
        min-width: 80px;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* Radio Button Toggle in Table Header (matching client section style) */
    .task-radio-toggle {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 6px;
        padding: 0.25rem;
    }

    .task-radio-label {
        cursor: pointer;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        transition: all 0.3s ease;
        background: transparent;
        color: rgba(255, 255, 255, 0.8);
        border: none;
        outline: none;
        margin: 0;
        font-size: 0.9rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .task-radio-label:hover {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .task-radio-label.active {
        background: rgba(255, 255, 255, 0.95);
        color: #007bff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .task-radio-label input[type="radio"] {
        display: none;
    }

    .task-radio-label i {
        font-size: 0.85rem;
    }

    /* Quick filter dropdowns in table header */
    .header-quick-filter {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 4px;
        color: white;
        font-size: 0.78rem;
        padding: 0.25rem 1.5rem 0.25rem 0.5rem;
        line-height: 1.5;
        min-width: 110px;
        cursor: pointer;
        appearance: auto;
        margin-right: 10px;
    }

    .header-quick-filter:focus {
        background: rgba(255, 255, 255, 0.25);
        border-color: rgba(255, 255, 255, 0.5);
        outline: none;
        color: white;
    }

    .header-quick-filter option {
        background: #1e293b;
        color: #e2e8f0;
    }

    /* Date range toggle (matching performance page) */
    .date-range-toggle {
        display: flex;
        gap: 0.375rem;
        align-items: center;
    }

    .date-range-pill {
        padding: 0.35rem 0.75rem;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        color: var(--dark-text-secondary);
        font-size: 0.8rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .date-range-pill:hover {
        border-color: rgba(99, 102, 241, 0.25);
        color: var(--dark-text-primary);
    }

    .date-range-pill.active {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%);
        border-color: rgba(99, 102, 241, 0.4);
        color: var(--dark-text-primary);
        box-shadow: 0 1px 4px rgba(99, 102, 241, 0.15);
    }

    .date-range-pill span {
        position: relative;
        z-index: 1;
    }

    .date-range-custom-input {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        color: var(--dark-text-secondary);
        font-size: 0.75rem;
        padding: 0.35rem 0.6rem;
        cursor: pointer;
        width: 125px;
    }

    .date-range-custom-input:focus {
        border-color: rgba(99, 102, 241, 0.4);
        outline: none;
        color: var(--dark-text-primary);
    }

    .date-range-custom-input::-webkit-calendar-picker-indicator {
        filter: invert(1);
        cursor: pointer;
    }
</style>

<div class="manage-tasks-page">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4" style="margin-left: 12px; margin-right: 12px;">
            <h2 class="mb-0"><?php echo htmlspecialchars($page_title); ?></h2>
            <div class="d-flex align-items-center gap-3">
                <div class="date-range-toggle" id="dateRangeToggle">
                    <?php
                    $active_range = '';
                    if (!empty($filter_date_from) && !empty($filter_date_to)) {
                        $from = new DateTime($filter_date_from);
                        $to = new DateTime($filter_date_to);
                        $diff_days = (int)$from->diff($to)->days;
                        if ($diff_days <= 8) $active_range = '1w';
                        elseif ($diff_days <= 15) $active_range = '2w';
                        elseif ($diff_days <= 29) $active_range = '4w';
                        elseif ($diff_days <= 57) $active_range = '8w';
                        elseif ($diff_days <= 85) $active_range = '12w';
                        else $active_range = 'custom';
                    }
                    ?>
                    <button class="date-range-pill <?php echo $active_range === '1w' ? 'active' : ''; ?>" data-range="1w" title="Last Week"><span>1W</span></button>
                    <button class="date-range-pill <?php echo $active_range === '2w' ? 'active' : ''; ?>" data-range="2w" title="2 Weeks"><span>2W</span></button>
                    <button class="date-range-pill <?php echo $active_range === '4w' ? 'active' : ''; ?>" data-range="4w" title="4 Weeks"><span>4W</span></button>
                    <button class="date-range-pill <?php echo $active_range === '8w' ? 'active' : ''; ?>" data-range="8w" title="8 Weeks"><span>8W</span></button>
                    <button class="date-range-pill <?php echo $active_range === '12w' ? 'active' : ''; ?>" data-range="12w" title="12 Weeks"><span>12W</span></button>
                    <div class="d-flex align-items-center gap-1">
                        <input type="date" class="date-range-custom-input" id="customDateFrom" value="<?php echo htmlspecialchars($filter_date_from); ?>" title="From date" onclick="this.showPicker()" style="margin-right: 10px;">
                        <input type="date" class="date-range-custom-input" id="customDateTo" value="<?php echo htmlspecialchars($filter_date_to); ?>" title="To date" onclick="this.showPicker()" style="margin-right: 10px;">
                    </div>
                </div>
                <button class="btn btn-outline-primary btn-sm" onclick="refreshTable()" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <!-- Summary Cards Section -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                <div class="stats-card total-tasks">
                    <div class="metric-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo number_format($summary_stats['total_tasks']); ?></div>
                        <div class="metric-label">Total Tasks in View</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                <div class="stats-card completed-tasks">
                    <div class="metric-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo number_format($summary_stats['completed_tasks']); ?></div>
                        <div class="metric-label">Completed Tasks in View</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                <div class="stats-card delayed-tasks">
                    <div class="metric-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo number_format($summary_stats['delayed_tasks']); ?></div>
                        <div class="metric-label">Delayed Tasks in View</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                <div class="stats-card pending-tasks">
                    <div class="metric-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo number_format($summary_stats['pending_tasks']); ?></div>
                        <div class="metric-label">Pending Tasks in View</div>
                                    </div>
                                </div>
                            </div>
                        </div>

        <p class="text-center text-muted small fst-italic mb-4">This is the raw data,<br>For weekly performance <a href="team_performance.php">refer to this</a></p>

        <!-- Alert Messages Section -->
                        <?php if (!empty($success_message)): ?>
            <div class="row mb-4">
                <div class="col-12">
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
            <div class="row mb-4">
                <div class="col-12">
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
                            </div>
                        <?php endif; ?>
                        
        <!-- Debug Information Section -->
                        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
            <div class="row mb-4">
                <div class="col-12">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-bug"></i> Debug Information</h6>
                                <p><strong>Session Status:</strong> <?php echo isLoggedIn() ? 'Logged In' : 'Not Logged In'; ?></p>
                                <p><strong>User Type:</strong> <?php echo $_SESSION['user_type'] ?? 'Not Set'; ?></p>
                                <p><strong>User ID:</strong> <?php echo $_SESSION['id'] ?? 'Not Set'; ?></p>
                                <p><strong>User Name:</strong> <?php echo $_SESSION['name'] ?? $_SESSION['username'] ?? 'Not Set'; ?></p>
                                <p><strong>Is Manager:</strong> <?php echo isManager() ? 'Yes' : 'No'; ?></p>
                                <p><strong>Is Admin:</strong> <?php echo isAdmin() ? 'Yes' : 'No'; ?></p>
                                <p><strong>Total Tasks Fetched:</strong> <?php echo count($all_tasks); ?></p>
                                <p><strong>Filtered Tasks:</strong> <?php echo count($filtered_tasks); ?></p>
                    </div>
                </div>
                            </div>
                        <?php endif; ?>
                        
        <!-- Filter Section -->
        <div class="row mb-4" id="filterSection" style="display: none;">
            <div class="col-12">
                <div class="filter-section">
            <div class="filter-content" id="filterContent">
                                <form method="GET" class="filter-form">
                                    <div class="row g-3">
                                        <!-- Row 1 -->
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">Task ID</label>
                                            <input type="text" class="form-control form-control-sm" name="task_id" 
                                                   placeholder="Search Task ID..." value="<?php echo htmlspecialchars($filter_id ?? ''); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">Task Name</label>
                                            <input type="text" class="form-control form-control-sm" name="task_name" 
                                                   placeholder="Search Task Name..." value="<?php echo htmlspecialchars($filter_description ?? ''); ?>">
                                        </div>
                                        <div class="col-md-3">
    <label class="form-label text-muted small">Doer</label>
    <div class="doer-filter-container">
        <!-- Real Dropdown -->
        <select class="form-control form-control-sm" name="doer" id="doerSelect">
            <option value="">Filter by Doer</option>
            <?php if (!empty($doers) && is_array($doers)): ?>
                <?php foreach ($doers as $doer): ?>
                    <option value="<?php echo htmlspecialchars($doer); ?>"
                        <?php echo (isset($filter_doer) && $filter_doer === $doer) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($doer); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
</div>

                                        <?php if (isAdmin() || isManager()): ?>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">User Status</label>
                                            <div class="doer-status-toggle">
                                                <div class="btn-group btn-group-toggle" data-toggle="buttons" style="width: 100%;">
                                                    <label class="btn btn-sm <?php echo ($doer_status === 'Active') ? 'btn-primary active' : 'btn-outline-primary'; ?>" style="flex: 1;">
                                                        <input type="radio" name="doer_status" value="Active" autocomplete="off" <?php echo ($doer_status === 'Active') ? 'checked' : ''; ?>> Active
                                                    </label>
                                                    <label class="btn btn-sm <?php echo ($doer_status === 'Inactive') ? 'btn-primary active' : 'btn-outline-primary'; ?>" style="flex: 1;">
                                                        <input type="radio" name="doer_status" value="Inactive" autocomplete="off" <?php echo ($doer_status === 'Inactive') ? 'checked' : ''; ?>> Inactive
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">Status</label>
                                            <select class="form-control form-control-sm" name="status">
                                                <option value="">Filter by Status</option>
                                                <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                <option value="not done" <?php echo ($filter_status === 'not done') ? 'selected' : ''; ?>>Not Done</option>
                                                <option value="can not be done" <?php echo ($filter_status === 'can not be done') ? 'selected' : ''; ?>>Can't Be Done</option>
                                                <option value="shifted" <?php echo ($filter_status === 'shifted') ? 'selected' : ''; ?>>Shifted</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Row 2 -->
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">Type</label>
                                            <select class="form-control form-control-sm" name="type">
                                                <option value="">Filter by Type</option>
                                                <option value="delegation" <?php echo (($filter_type ?? '') === 'delegation') ? 'selected' : ''; ?>>Delegation</option>
                                                <option value="fms" <?php echo (($filter_type ?? '') === 'fms') ? 'selected' : ''; ?>>FMS</option>
                                                <option value="checklist" <?php echo (($filter_type ?? '') === 'checklist') ? 'selected' : ''; ?>>Checklist</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">Date From</label>
                                            <input type="date" class="form-control form-control-sm date-picker-clickable" name="date_from" 
                                                   value="<?php echo htmlspecialchars($filter_date_from ?? ''); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">Date To</label>
                                            <input type="date" class="form-control form-control-sm date-picker-clickable" name="date_to" 
                                                   value="<?php echo htmlspecialchars($filter_date_to ?? ''); ?>">
                                        </div>
                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <a href="manage_tasks.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Reset Filters</a>
                                        </div>
                                    </div>
                                </form>
                    </div>
                </div>
                            </div>
                        </div>

        <!-- Tasks Table Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="task-radio-toggle">
                                <label class="task-radio-label active" id="taskRadioAll">
                                    <input type="radio" name="task_view" value="all" checked onchange="switchTasksTab('all')" autocomplete="off">
                                    All Tasks
                                </label>
                                <label class="task-radio-label" id="taskRadioPriority">
                                    <input type="radio" name="task_view" value="priority" onchange="switchTasksTab('priority')" autocomplete="off">
                                    Priority Tasks
                                </label>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <select class="header-quick-filter" id="quickDoerFilter" onchange="applyQuickFilter()">
                                    <option value="">All Doers</option>
                                    <?php if (!empty($doers) && is_array($doers)): ?>
                                        <?php foreach ($doers as $doer): ?>
                                            <option value="<?php echo htmlspecialchars($doer); ?>" <?php echo (isset($filter_doer) && $filter_doer === $doer) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($doer); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <select class="header-quick-filter" id="quickStatusFilter" onchange="applyQuickFilter()">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="not done" <?php echo ($filter_status === 'not done') ? 'selected' : ''; ?>>Not Done</option>
                                    <option value="can not be done" <?php echo ($filter_status === 'can not be done') ? 'selected' : ''; ?>>Can't Be Done</option>
                                    <option value="shifted" <?php echo ($filter_status === 'shifted') ? 'selected' : ''; ?>>Shifted</option>
                                </select>
                                <button type="button" class="btn btn-light btn-sm" id="toggleFilters" style="padding: 0.25rem 0.5rem; line-height: 1.5; font-size: 0.78rem;">
                                    <i class="fas fa-filter"></i> Filters
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- All Tasks Tab Content -->
                    <div class="tab-content active" id="allTasksTab">
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-ultra-compact table-hover" id="manage-tasks-table" style="zoom: 0.97;">
                                <thead class="thead-light">                                    <tr>
                                        <th style="width: 3%; text-align: center;">
                                            <i class="fas fa-star text-warning" title="Priority"></i>
                                        </th>
                                        <th style="width: 11%; min-width: 120px;">
                                            <a href="<?php echo buildSortUrl('unique_id', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-white text-decoration-none sortable-header" data-column="unique_id">
                                                ID <?php echo getSortIcon('unique_id', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th style="width: 14%;">
                                            <a href="<?php echo buildSortUrl('description', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-white text-decoration-none sortable-header" data-column="description">
                                                Description <?php echo getSortIcon('description', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th style="width: 11%;">
                                            <a href="<?php echo buildSortUrl('assigned_by', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-white text-decoration-none sortable-header" data-column="assigned_by">
                                                Assigner <?php echo getSortIcon('assigned_by', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th style="width: 7%;">
                                            <a href="<?php echo buildSortUrl('planned_date', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-white text-decoration-none sortable-header" data-column="planned_date">
                                                Planned <?php echo getSortIcon('planned_date', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th style="width: 7%;">
                                            <a href="<?php echo buildSortUrl('actual_date', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-white text-decoration-none sortable-header" data-column="actual_date">
                                                Actual <?php echo getSortIcon('actual_date', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>

                                        <th style="width: 7%;">
                                            <a href="<?php echo buildSortUrl('status', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-white text-decoration-none sortable-header" data-column="status">
                                                Status <?php echo getSortIcon('status', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        
                                       <th style="width: 7%;">
                                            <a href="<?php echo buildSortUrl('delay_duration', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-white text-decoration-none sortable-header" data-column="delay_duration">
                                                Delay <?php echo getSortIcon('delay_duration', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th style="width: 7%;">
                                            <a href="<?php echo buildSortUrl('duration', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-white text-decoration-none sortable-header" data-column="duration">
                                                Duration <?php echo getSortIcon('duration', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th style="width: 7%;">
                                            <a href="<?php echo buildSortUrl('doer_name', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                               class="text-white text-decoration-none sortable-header" data-column="doer_name">
                                                Doer <?php echo getSortIcon('doer_name', $sort_column, $sort_direction); ?>
                                            </a>
                                        </th>
                                        <th style="width: 12%; min-width: 140px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($tasks)): ?>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr data-task-id="<?php echo htmlspecialchars($task['id']); ?>" data-task-type="<?php echo htmlspecialchars($task['task_type']); ?>">
                                                <td class="text-center priority-star-cell" style="vertical-align: middle;">
                                                    <?php 
                                                    $is_priority = (int)($task['priority'] ?? 0) === 1;
                                                    $priority_class = $is_priority ? 'priority-star active' : 'priority-star';
                                                    if (isAdmin() || isManager()): ?>
                                                        <i class="fas fa-star <?php echo $priority_class; ?>" 
                                                           data-task-id="<?php echo htmlspecialchars($task['id']); ?>"
                                                           data-task-type="<?php echo htmlspecialchars($task['task_type']); ?>"
                                                           title="<?php echo $is_priority ? 'Click to remove priority' : 'Click to mark as priority'; ?>"
                                                           style="cursor: pointer;"></i>
                                                    <?php else: ?>
                                                        <?php if ($is_priority): ?>
                                                            <i class="fas fa-star priority-star active" title="Priority Task"></i>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="badge badge-info badge-sm"><?php echo ucfirst($task['task_type']); ?></small>
                                                    <br><small class="text-muted font-weight-bold">
                                                        <?php echo htmlspecialchars($task['unique_id'] ?? ''); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $description = $task['description'] ?? 'N/A';
                                                    $full_description = htmlspecialchars($description);
                                                    $truncated_description = strlen($description) > 50 ? substr($description, 0, 50) . '..' : $description;
                                                    ?>
                                                    <span class="description-hover" data-full-description="<?php echo htmlspecialchars($full_description); ?>">
                                                        <?php echo htmlspecialchars($truncated_description); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    // Get assigner name based on task type
                                                        if ($task['task_type'] === 'delegation') {
                                                            // For delegation tasks, use assigned_by_name if available, otherwise fallback
                                                        $assigner = $task['assigned_by_name'] ?? $task['assigned_by'] ?? 'N/A';
                                                        } elseif ($task['task_type'] === 'fms') {
                                                            // For FMS tasks, use client_name
                                                        $assigner = $task['assigned_by'] ?? 'N/A';
                                                        } elseif ($task['task_type'] === 'checklist') {
                                                            // For checklist tasks, use assigned_by as is
                                                        $assigner = $task['assigned_by'] ?? 'N/A';
                                                        } else {
                                                        $assigner = $task['assigned_by'] ?? 'N/A';
                                                        }
                                                    
                                                    $full_assigner = htmlspecialchars($assigner);
                                                    $truncated_assigner = strlen($assigner) > 50 ? substr($assigner, 0, 50) . '..' : $assigner;
                                                    ?>
                                                    <small>
                                                        <span class="description-hover" data-full-description="<?php echo $full_assigner; ?>">
                                                            <?php echo htmlspecialchars($truncated_assigner); ?>
                                                        </span>
                                                    </small>
                                                </td>
                                                <td class="text-center">
                                                    <small>
                                                        <?php 
                                                        if (!empty($task['planned_date'])) {
                                                            // For checklist tasks, use the same format as checklist task table
                                                            if ($task['task_type'] === 'checklist') {
                                                                echo date('d M Y', strtotime($task['planned_date']));
                                                            } else {
                                                                // For other task types, keep the original format with time
                                                                echo date('M d, Y', strtotime($task['planned_date']));
                                                                if (!empty($task['planned_time'])) {
                                                                    echo '<br>' . date('H:i', strtotime($task['planned_time']));
                                                                }
                                                            }
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </small>
                                                </td>
                                                <td class="text-center">
                                                    <small>
                                                        <?php 
                                                        if (!empty($task['actual_date'])) {
                                                            echo date('M d, Y', strtotime($task['actual_date']));
                                                            if (!empty($task['actual_time'])) {
                                                                echo '<br>' . date('H:i', strtotime($task['actual_time']));
                                                            }
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </small>
                                                </td>
                                                <?php 
                                                // Custom FMS status logic: ✅ for tasks with actual data, ⏳ for pending tasks without actual data
                                                if ($task['task_type'] === 'fms') {
                                                    $has_actual_data = !empty($task['actual_date']) || !empty($task['actual_time']);
                                                    $status_icon = $has_actual_data ? '✅' : '⏳';
                                                    $status_text = $has_actual_data ? 'Completed' : 'Pending';
                                                    ?>
                                                    <td class="status-column" data-status="<?php echo htmlspecialchars($task['status'] ?? 'pending'); ?>">
                                                        <span class="status-icon" title="<?php echo htmlspecialchars($status_text); ?>"><?php echo $status_icon; ?></span>
                                                    </td>
                                                    <?php
                                                } else {
                                                    echo get_status_column_cell($task['status'] ?? 'pending');
                                                }
                                                ?>
                                      <td class="text-center">
                                                    <?php
                                                    // Get the pre-calculated delay duration
                                                    $delay_duration = $task['delay_duration'] ?? '';
                                                    
                                                    if (!empty($delay_duration)) {
                                                        // 1. Get the full, formatted delay string
                                                        $full_delay = formatDelayForDisplay($delay_duration);

                                                        // 2. Create a truncated version matching the delegation task style
                                                        $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;

                                                        // 3. Echo the final HTML with the hover effect class and data attribute
                                                        echo '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' 
                                                             . htmlspecialchars($truncated_delay) 
                                                             . '</span>';
                                                    } else {
                                                        // If no delay, show "On Time" or "N/A" as before
                                                        $task_status = strtolower(trim($task['status'] ?? ''));
                                                        
                                                        // Check for "Not Done" or "Can't be done" statuses - show N/A
                                                        // Normalize status: remove extra spaces, convert to lowercase
                                                        $task_status_normalized = preg_replace('/\s+/', ' ', strtolower(trim($task_status)));
                                                        
                                                        // Check for "not done" variations (case-insensitive)
                                                        $is_not_done = (preg_match('/\bnot\s+done\b/i', $task_status_normalized) || 
                                                                       $task_status_normalized === 'not done' ||
                                                                       $task_status_normalized === 'notdone' ||
                                                                       $task_status_normalized === 'not-done' ||
                                                                       $task_status_normalized === 'not_done' ||
                                                                       stripos($task_status_normalized, 'not done') !== false);
                                                        
                                                        // Check for "can't be done" variations (case-insensitive)
                                                        $is_cant_be_done = (preg_match('/\bcan[^a-z]*t\s+be\s+done\b/i', $task_status_normalized) ||
                                                                           preg_match('/\bcant\s+be\s+done\b/i', $task_status_normalized) ||
                                                                           preg_match('/\bcannot\s+be\s+done\b/i', $task_status_normalized) ||
                                                                           $task_status_normalized === "can't be done" ||
                                                                           $task_status_normalized === "can not be done" ||
                                                                           $task_status_normalized === "cant be done" ||
                                                                           $task_status_normalized === "cannot be done" ||
                                                                           stripos($task_status_normalized, "can't be done") !== false ||
                                                                           stripos($task_status_normalized, "can not be done") !== false ||
                                                                           stripos($task_status_normalized, "cant be done") !== false);
                                                        
                                                        $is_na_status = $is_not_done || $is_cant_be_done;
                                                        
                                                        if ($is_na_status) {
                                                            // Show N/A for "Not Done" or "Can't be done" tasks
                                                            echo '<span class="text-muted">N/A</span>';
                                                        } else {
                                                            // Only show "On Time" for truly completed tasks
                                                            $completed_statuses = ['completed', 'done'];
                                                            $is_task_completed = in_array($task_status_normalized, $completed_statuses);
                                                        
                                                        if ($is_task_completed) {
                                                            echo '<span class="text-success">On Time</span>';
                                                        } else {
                                                            echo '<span class="text-muted">N/A</span>';
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php 
                                                    $duration_display = "N/A";
                                                    if (!empty($task['duration'])) {
                                                        // Check if duration is stored as minutes (new system) or decimal hours (old system)
                                                        if (is_numeric($task['duration'])) {
                                                            $duration_value = floatval($task['duration']);
                                                            // Duration > 100 means it's stored as minutes (NEW system)
                                                            // Duration ≤ 100 means it's stored as decimal hours (OLD system)
                                                            if ($duration_value > 100) {
                                                                // New system: duration stored as minutes
                                                                $duration_minutes = (int)$duration_value;
                                                                $duration_display = formatMinutesToHHMMSS($duration_minutes);
                                                            } else {
                                                                // Old system: duration stored as decimal hours (backward compatibility)
                                                                $duration_display = formatDecimalDurationToHHMMSS($task['duration']);
                                                            }
                                                        } else {
                                                            // Not numeric, use fallback
                                                            $duration_display = $task['duration'];
                                                        }
                                                    }
                                                    echo '<small class="font-weight-bold">' . htmlspecialchars($duration_display) . '</small>';
                                                    ?>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($task['doer_name'] ?? ''); ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group" role="group">
                                                        <?php if ($task['task_type'] === 'fms' && !empty($task['task_link'])): ?>
                                                            <a href="<?php echo htmlspecialchars($task['task_link']); ?>" 
                                                               target="_blank" class="btn btn-success btn-xs" 
                                                               title="Open Task">
                                                                <i class="fas fa-external-link-alt"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <select class="form-control form-control-sm status-select" 
                                                                    data-task-id="<?php echo $task['id']; ?>" 
                                                                    data-task-type="<?php echo $task['task_type']; ?>">
                                                                <option value="pending" <?php echo ($task['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="completed" <?php echo ($task['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                                <option value="not done" <?php echo ($task['status'] === 'not done') ? 'selected' : ''; ?>>Not Done</option>
                                                                <option value="can not be done" <?php echo ($task['status'] === 'can not be done') ? 'selected' : ''; ?>>Can't Be Done</option>
                                                            </select>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="11" class="text-center text-muted py-4">
                                                <?php if (!empty($error_message)): ?>
                                                    <i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning"></i>
                                                    <br>Database error - please check setup
                                                <?php elseif ($total_tasks == 0): ?>
                                                    <i class="fas fa-database fa-2x mb-2 text-info"></i>
                                                    <br>No tasks found in database
                                                    <br><small>Tasks may need to be imported from Google Sheets first</small>
                                                <?php else: ?>
                                                    <i class="fas fa-search fa-2x mb-2"></i>
                                                    <br>No tasks found matching your criteria
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Bottom Bar: Show selector (left) + Results Info (center) + Pagination (right) -->
                        <div class="d-flex justify-content-between align-items-center mt-3 px-2">
                            <div class="d-flex align-items-center gap-2">
                                <label class="mb-0 small text-muted text-nowrap">Show:</label>
                                <select class="form-select form-select-sm" style="width: 70px; min-width: 70px;" onchange="changeRecordLimit(this.value)">
                                    <option value="25" <?php echo $tasks_per_page == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $tasks_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $tasks_per_page == 100 ? 'selected' : ''; ?>>100</option>
                                    <option value="250" <?php echo $tasks_per_page == 250 ? 'selected' : ''; ?>>250</option>
                                </select>
                            </div>
                            <span class="text-muted small">
                                <?php if ($total_tasks > 0): ?>
                                    Showing <?php echo $display_start; ?>-<?php echo $display_end; ?> of <?php echo $total_tasks; ?> tasks
                                <?php else: ?>
                                    No tasks found
                                <?php endif; ?>
                            </span>
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Tasks pagination" class="mb-0">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($current_page_num > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo buildManageTasksQuery(['page' => $current_page_num - 1]); ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <?php for ($i = max(1, $current_page_num - 2); $i <= min($total_pages, $current_page_num + 2); $i++): ?>
                                            <li class="page-item <?php echo ($i == $current_page_num) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo buildManageTasksQuery(['page' => $i]); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($current_page_num < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo buildManageTasksQuery(['page' => $current_page_num + 1]); ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>
                        </div>
                            </div>
                    </div>

                    <!-- Priority Tasks Tab Content -->
                    <div class="tab-content" id="priorityTasksTab">
                        <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-ultra-compact table-hover" id="priority-tasks-table" style="zoom: 0.97;">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 3%; text-align: center;">
                                                    <i class="fas fa-star text-warning" title="Priority"></i>
                                                </th>
                                                <th style="width: 11%; min-width: 120px;">
                                                    <a href="<?php echo buildSortUrl('unique_id', $sort_column, $sort_direction, array_merge(array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY), ['tab' => 'priority'])); ?>" 
                                                       class="text-white text-decoration-none sortable-header" data-column="unique_id">
                                                        ID <?php echo getSortIcon('unique_id', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th style="width: 14%;">
                                                    <a href="<?php echo buildSortUrl('description', $sort_column, $sort_direction, array_merge(array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY), ['tab' => 'priority'])); ?>" 
                                                       class="text-white text-decoration-none sortable-header" data-column="description">
                                                        Description <?php echo getSortIcon('description', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th style="width: 11%;">
                                                    <a href="<?php echo buildSortUrl('assigned_by', $sort_column, $sort_direction, array_merge(array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY), ['tab' => 'priority'])); ?>" 
                                                       class="text-white text-decoration-none sortable-header" data-column="assigned_by">
                                                        Assigner <?php echo getSortIcon('assigned_by', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th style="width: 7%;">
                                                    <a href="<?php echo buildSortUrl('planned_date', $sort_column, $sort_direction, array_merge(array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY), ['tab' => 'priority'])); ?>" 
                                                       class="text-white text-decoration-none sortable-header" data-column="planned_date">
                                                        Planned <?php echo getSortIcon('planned_date', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th style="width: 7%;">
                                                    <a href="<?php echo buildSortUrl('actual_date', $sort_column, $sort_direction, array_merge(array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY), ['tab' => 'priority'])); ?>" 
                                                       class="text-white text-decoration-none sortable-header" data-column="actual_date">
                                                        Actual <?php echo getSortIcon('actual_date', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th style="width: 7%;">
                                                    <a href="<?php echo buildSortUrl('status', $sort_column, $sort_direction, array_merge(array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY), ['tab' => 'priority'])); ?>" 
                                                       class="text-white text-decoration-none sortable-header" data-column="status">
                                                        Status <?php echo getSortIcon('status', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th style="width: 7%;">
                                                    <a href="<?php echo buildSortUrl('delay_duration', $sort_column, $sort_direction, array_merge(array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY), ['tab' => 'priority'])); ?>" 
                                                       class="text-white text-decoration-none sortable-header" data-column="delay_duration">
                                                        Delay <?php echo getSortIcon('delay_duration', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th style="width: 7%;">
                                                    <a href="<?php echo buildSortUrl('duration', $sort_column, $sort_direction, array_merge(array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY), ['tab' => 'priority'])); ?>" 
                                                       class="text-white text-decoration-none sortable-header" data-column="duration">
                                                        Duration <?php echo getSortIcon('duration', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th style="width: 7%;">
                                                    <a href="<?php echo buildSortUrl('doer_name', $sort_column, $sort_direction, array_merge(array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir'; }, ARRAY_FILTER_USE_KEY), ['tab' => 'priority'])); ?>" 
                                                       class="text-white text-decoration-none sortable-header" data-column="doer_name">
                                                        Doer <?php echo getSortIcon('doer_name', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th style="width: 12%; min-width: 140px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($priority_tasks_display)): ?>
                                                <?php foreach ($priority_tasks_display as $task): ?>
                                                    <?php 
                                                    // Reuse the same row structure as All Tasks
                                                    $is_priority = (int)($task['priority'] ?? 0) === 1;
                                                    $priority_class = $is_priority ? 'priority-star active' : 'priority-star';
                                                    ?>
                                                    <tr data-task-id="<?php echo htmlspecialchars($task['id']); ?>" data-task-type="<?php echo htmlspecialchars($task['task_type']); ?>">
                                                        <td class="text-center priority-star-cell" style="vertical-align: middle;">
                                                            <?php if (isAdmin() || isManager()): ?>
                                                                <i class="fas fa-star <?php echo $priority_class; ?>" 
                                                                   data-task-id="<?php echo htmlspecialchars($task['id']); ?>"
                                                                   data-task-type="<?php echo htmlspecialchars($task['task_type']); ?>"
                                                                   title="<?php echo $is_priority ? 'Click to remove priority' : 'Click to mark as priority'; ?>"
                                                                   style="cursor: pointer;"></i>
                                                            <?php else: ?>
                                                                <?php if ($is_priority): ?>
                                                                    <i class="fas fa-star priority-star active" title="Priority Task"></i>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small class="badge badge-info badge-sm"><?php echo ucfirst($task['task_type']); ?></small>
                                                            <br><small class="text-muted font-weight-bold">
                                                                <?php echo htmlspecialchars($task['unique_id'] ?? ''); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $description = $task['description'] ?? 'N/A';
                                                            $full_description = htmlspecialchars($description);
                                                            $truncated_description = strlen($description) > 50 ? substr($description, 0, 50) . '..' : $description;
                                                            ?>
                                                            <span class="description-hover" data-full-description="<?php echo htmlspecialchars($full_description); ?>">
                                                                <?php echo htmlspecialchars($truncated_description); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            // Get assigner name based on task type
                                                                if ($task['task_type'] === 'delegation') {
                                                                $assigner = $task['assigned_by_name'] ?? $task['assigned_by'] ?? 'N/A';
                                                                } elseif ($task['task_type'] === 'fms') {
                                                                $assigner = $task['assigned_by'] ?? 'N/A';
                                                                } elseif ($task['task_type'] === 'checklist') {
                                                                $assigner = $task['assigned_by'] ?? 'N/A';
                                                                } else {
                                                                $assigner = $task['assigned_by'] ?? 'N/A';
                                                                }
                                                            
                                                            $full_assigner = htmlspecialchars($assigner);
                                                            $truncated_assigner = strlen($assigner) > 50 ? substr($assigner, 0, 50) . '..' : $assigner;
                                                            ?>
                                                            <small>
                                                                <span class="description-hover" data-full-description="<?php echo $full_assigner; ?>">
                                                                    <?php echo htmlspecialchars($truncated_assigner); ?>
                                                                </span>
                                                            </small>
                                                        </td>
                                                        <td class="text-center">
                                                            <small>
                                                                <?php 
                                                                if (!empty($task['planned_date'])) {
                                                                    if ($task['task_type'] === 'checklist') {
                                                                        echo date('d M Y', strtotime($task['planned_date']));
                                                                    } else {
                                                                        echo date('M d, Y', strtotime($task['planned_date']));
                                                                        if (!empty($task['planned_time'])) {
                                                                            echo '<br>' . date('H:i', strtotime($task['planned_time']));
                                                                        }
                                                                    }
                                                                } else {
                                                                    echo '-';
                                                                }
                                                                ?>
                                                            </small>
                                                        </td>
                                                        <td class="text-center">
                                                            <small>
                                                                <?php 
                                                                if (!empty($task['actual_date'])) {
                                                                    echo date('M d, Y', strtotime($task['actual_date']));
                                                                    if (!empty($task['actual_time'])) {
                                                                        echo '<br>' . date('H:i', strtotime($task['actual_time']));
                                                                    }
                                                                } else {
                                                                    echo '-';
                                                                }
                                                                ?>
                                                            </small>
                                                        </td>
                                                        <?php 
                                                        if ($task['task_type'] === 'fms') {
                                                            $has_actual_data = !empty($task['actual_date']) || !empty($task['actual_time']);
                                                            $status_icon = $has_actual_data ? '✅' : '⏳';
                                                            $status_text = $has_actual_data ? 'Completed' : 'Pending';
                                                            ?>
                                                            <td class="status-column" data-status="<?php echo htmlspecialchars($task['status'] ?? 'pending'); ?>">
                                                                <span class="status-icon" title="<?php echo htmlspecialchars($status_text); ?>"><?php echo $status_icon; ?></span>
                                                            </td>
                                                            <?php
                                                        } else {
                                                            echo get_status_column_cell($task['status'] ?? 'pending');
                                                        }
                                                        ?>
                                                        <td class="text-center">
                                                            <?php
                                                            $delay_duration = $task['delay_duration'] ?? '';
                                                            if (!empty($delay_duration)) {
                                                                $full_delay = formatDelayForDisplay($delay_duration);
                                                                $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                                                echo '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' 
                                                                     . htmlspecialchars($truncated_delay) 
                                                                     . '</span>';
                                                            } else {
                                                                $task_status = strtolower(trim($task['status'] ?? ''));
                                                                
                                                                // Check for "Not Done" or "Can't be done" statuses - show N/A
                                                                // Normalize status: remove extra spaces, convert to lowercase
                                                                $task_status_normalized = preg_replace('/\s+/', ' ', strtolower(trim($task_status)));
                                                                
                                                                // Check for "not done" variations (case-insensitive)
                                                                $is_not_done = (preg_match('/\bnot\s+done\b/i', $task_status_normalized) || 
                                                                               $task_status_normalized === 'not done' ||
                                                                               $task_status_normalized === 'notdone' ||
                                                                               $task_status_normalized === 'not-done' ||
                                                                               $task_status_normalized === 'not_done' ||
                                                                               stripos($task_status_normalized, 'not done') !== false);
                                                                
                                                                // Check for "can't be done" variations (case-insensitive)
                                                                $is_cant_be_done = (preg_match('/\bcan[^a-z]*t\s+be\s+done\b/i', $task_status_normalized) ||
                                                                                   preg_match('/\bcant\s+be\s+done\b/i', $task_status_normalized) ||
                                                                                   preg_match('/\bcannot\s+be\s+done\b/i', $task_status_normalized) ||
                                                                                   $task_status_normalized === "can't be done" ||
                                                                                   $task_status_normalized === "can not be done" ||
                                                                                   $task_status_normalized === "cant be done" ||
                                                                                   $task_status_normalized === "cannot be done" ||
                                                                                   stripos($task_status_normalized, "can't be done") !== false ||
                                                                                   stripos($task_status_normalized, "can not be done") !== false ||
                                                                                   stripos($task_status_normalized, "cant be done") !== false);
                                                                
                                                                $is_na_status = $is_not_done || $is_cant_be_done;
                                                                
                                                                if ($is_na_status) {
                                                                    // Show N/A for "Not Done" or "Can't be done" tasks
                                                                    echo '<span class="text-muted">N/A</span>';
                                                                } else {
                                                                    // Only show "On Time" for truly completed tasks
                                                                    $completed_statuses = ['completed', 'done'];
                                                                    $is_task_completed = in_array($task_status, $completed_statuses);
                                                                    
                                                                if ($is_task_completed) {
                                                                    echo '<span class="text-success">On Time</span>';
                                                                } else {
                                                                    echo '<span class="text-muted">N/A</span>';
                                                                    }
                                                                }
                                                            }
                                                            ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php 
                                                            $duration_display = "N/A";
                                                            if (!empty($task['duration'])) {
                                                                if (is_numeric($task['duration'])) {
                                                                    $duration_value = floatval($task['duration']);
                                                                    if ($duration_value > 100) {
                                                                        $duration_minutes = (int)$duration_value;
                                                                        $duration_display = formatMinutesToHHMMSS($duration_minutes);
                                                                    } else {
                                                                        $duration_display = formatDecimalDurationToHHMMSS($task['duration']);
                                                                    }
                                                                } else {
                                                                    $duration_display = $task['duration'];
                                                                }
                                                            }
                                                            echo '<small class="font-weight-bold">' . htmlspecialchars($duration_display) . '</small>';
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <small><?php echo htmlspecialchars($task['doer_name'] ?? ''); ?></small>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="btn-group" role="group">
                                                                <?php if ($task['task_type'] === 'fms' && !empty($task['task_link'])): ?>
                                                                    <a href="<?php echo htmlspecialchars($task['task_link']); ?>" 
                                                                       target="_blank" class="btn btn-success btn-xs" 
                                                                       title="Open Task">
                                                                        <i class="fas fa-external-link-alt"></i>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <select class="form-control form-control-sm status-select" 
                                                                            data-task-id="<?php echo $task['id']; ?>" 
                                                                            data-task-type="<?php echo $task['task_type']; ?>">
                                                                        <option value="pending" <?php echo ($task['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                                        <option value="completed" <?php echo ($task['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                                        <option value="not done" <?php echo ($task['status'] === 'not done') ? 'selected' : ''; ?>>Not Done</option>
                                                                        <option value="can not be done" <?php echo ($task['status'] === 'can not be done') ? 'selected' : ''; ?>>Can't Be Done</option>
                                                                    </select>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="12" class="text-center text-muted py-4">
                                                        <i class="fas fa-star fa-2x mb-2 text-warning"></i>
                                                        <br>No priority tasks found
                                                        <br><small>Mark tasks as priority using the star icon in All Tasks tab</small>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Bottom Bar: Show selector (left) + Results Info (center) + Pagination (right) -->
                                <div class="d-flex justify-content-between align-items-center mt-3 px-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="mb-0 small text-muted text-nowrap">Show:</label>
                                        <select class="form-select form-select-sm" style="width: 70px; min-width: 70px;" onchange="changeRecordLimit(this.value)">
                                            <option value="25" <?php echo $tasks_per_page == 25 ? 'selected' : ''; ?>>25</option>
                                            <option value="50" <?php echo $tasks_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                            <option value="100" <?php echo $tasks_per_page == 100 ? 'selected' : ''; ?>>100</option>
                                            <option value="250" <?php echo $tasks_per_page == 250 ? 'selected' : ''; ?>>250</option>
                                        </select>
                                    </div>
                                    <span class="text-muted small">
                                        <?php if ($priority_total_tasks > 0): ?>
                                            Showing <?php echo $priority_display_start; ?>-<?php echo $priority_display_end; ?> of <?php echo $priority_total_tasks; ?> priority tasks
                                        <?php else: ?>
                                            No priority tasks found
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($priority_total_pages_var > 1): ?>
                                        <nav aria-label="Priority Tasks pagination" class="mb-0">
                                            <ul class="pagination pagination-sm mb-0">
                                                <?php if ($priority_current_page_var > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?php echo buildManageTasksQuery(['priority_page' => $priority_current_page_var - 1, 'tab' => 'priority'], ['page']); ?>">
                                                            <i class="fas fa-chevron-left"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php for ($i = max(1, $priority_current_page_var - 2); $i <= min($priority_total_pages_var, $priority_current_page_var + 2); $i++): ?>
                                                    <li class="page-item <?php echo ($i == $priority_current_page_var) ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?<?php echo buildManageTasksQuery(['priority_page' => $i, 'tab' => 'priority'], ['page']); ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                <?php if ($priority_current_page_var < $priority_total_pages_var): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?php echo buildManageTasksQuery(['priority_page' => $priority_current_page_var + 1, 'tab' => 'priority'], ['page']); ?>">
                                                            <i class="fas fa-chevron-right"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    <?php else: ?>
                                        <div></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function refreshTable() {
    location.reload();
}

// Change record limit and reload page
function changeRecordLimit(limit) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('limit', limit);
    urlParams.delete('page');
    urlParams.delete('priority_page');
    window.location.href = '?' + urlParams.toString();
}

// Quick filter (Doer / Status dropdowns in table header)
function applyQuickFilter() {
    var urlParams = new URLSearchParams(window.location.search);
    var doer = document.getElementById('quickDoerFilter').value;
    var status = document.getElementById('quickStatusFilter').value;

    if (doer) urlParams.set('doer', doer); else urlParams.delete('doer');
    if (status) urlParams.set('status', status); else urlParams.delete('status');
    urlParams.delete('page');
    urlParams.delete('priority_page');
    window.location.href = '?' + urlParams.toString();
}

// Date range toggle pills
(function() {
    var pills = document.querySelectorAll('.date-range-pill[data-range]');
    var customFrom = document.getElementById('customDateFrom');
    var customTo = document.getElementById('customDateTo');

    function getDateRange(range) {
        var to = new Date();
        var from = new Date();
        switch(range) {
            case '1w': from.setDate(to.getDate() - 7); break;
            case '2w': from.setDate(to.getDate() - 14); break;
            case '4w': from.setDate(to.getDate() - 28); break;
            case '8w': from.setDate(to.getDate() - 56); break;
            case '12w': from.setDate(to.getDate() - 84); break;
        }
        return {
            from: from.toISOString().split('T')[0],
            to: to.toISOString().split('T')[0]
        };
    }

    function applyDateRange(dateFrom, dateTo) {
        var urlParams = new URLSearchParams(window.location.search);
        if (dateFrom) urlParams.set('date_from', dateFrom); else urlParams.delete('date_from');
        if (dateTo) urlParams.set('date_to', dateTo); else urlParams.delete('date_to');
        urlParams.delete('page');
        urlParams.delete('priority_page');
        window.location.href = '?' + urlParams.toString();
    }

    pills.forEach(function(pill) {
        pill.addEventListener('click', function() {
            var range = this.getAttribute('data-range');
            if (this.classList.contains('active')) {
                applyDateRange('', '');
                return;
            }
            var dates = getDateRange(range);
            applyDateRange(dates.from, dates.to);
        });
    });

    if (customFrom) {
        customFrom.addEventListener('change', function() {
            if (customFrom.value && customTo.value) {
                applyDateRange(customFrom.value, customTo.value);
            }
        });
    }
    if (customTo) {
        customTo.addEventListener('change', function() {
            if (customFrom.value && customTo.value) {
                applyDateRange(customFrom.value, customTo.value);
            }
        });
    }
})();

// Tab switching functionality for All Tasks and Priority Tasks
function switchTasksTab(tabName) {
    // Update radio toggle labels
    const allLabels = document.querySelectorAll('.task-radio-label');
    allLabels.forEach(function(label) {
        const radio = label.querySelector('input[type="radio"]');
        if (radio && radio.value === tabName) {
            label.classList.add('active');
            radio.checked = true;
        } else {
            label.classList.remove('active');
        }
    });

    // Update tab content visibility
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });

    let activeContent;
    if (tabName === 'all') {
        activeContent = document.getElementById('allTasksTab');
    } else if (tabName === 'priority') {
        activeContent = document.getElementById('priorityTasksTab');
    }

    if (activeContent) {
        activeContent.classList.add('active');
    }

    // Update URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName === 'priority' ? 'priority' : 'all');
    window.history.pushState({}, '', url);
}

// Check URL parameter for initial tab
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam === 'priority') {
            switchTasksTab('priority');
        } else {
            switchTasksTab('all');
        }
    });
} else {
    // DOM already loaded
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam === 'priority') {
        switchTasksTab('priority');
    } else {
        switchTasksTab('all');
    }
}

function editTask(taskId) {
    // Implement edit functionality
    alert('Edit task: ' + taskId);
}

function deleteTask(taskId) {
    if (confirm('Are you sure you want to delete this task?')) {
        // Implement delete functionality
        alert('Delete task: ' + taskId);
    }
}

// Optimized manage tasks functionality
if (!window.manageTasksInitialized) {
    window.manageTasksInitialized = true;
    
    // AJAX status update functionality
    document.addEventListener('DOMContentLoaded', function() {
    // Make User Active toggle apply instantly
    const doerStatusRadios = document.querySelectorAll('input[name="doer_status"]');
    doerStatusRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            // Submit the form automatically when toggle changes
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
    
    // Handle status dropdown changes
    const statusSelects = document.querySelectorAll('.status-select');
    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const taskId = this.dataset.taskId;
            const taskType = this.dataset.taskType;
            const newStatus = this.value;
            const originalValue = this.value;
            
            // Show confirmation dialog
            const statusText = newStatus.charAt(0).toUpperCase() + newStatus.slice(1).replace(/_/g, ' ');
            if (!confirm(`Are you sure you want to change the task status to "${statusText}"?`)) {
                // User cancelled, revert the select value
                this.value = originalValue;
                return;
            }
            
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<option>Updating...</option>';
            
            // Make AJAX request
            fetch('action_update_task_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `task_id=${taskId}&task_type=${taskType}&status=${newStatus}&action=update_status`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Server response received:', data);
                if (data.status === 'success') {
                    // Update the row with new data
                    console.log('Calling updateTaskRow with:', taskId, data);
                    updateTaskRow(taskId, data);
                    showAlert(data.message || 'Task status updated successfully!', 'success');
                } else {
                    console.error('Server returned error:', data);
                    showAlert('Error updating task status: ' + (data.message || 'Unknown error'), 'danger');
                    // Revert the select value
                    this.value = originalValue;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error updating task status. Please try again.', 'danger');
                // Revert the select value
                this.value = originalValue;
            })
            .finally(() => {
                // Restore the select
                this.disabled = false;
                this.innerHTML = `
                    <option value="pending" ${newStatus === 'pending' ? 'selected' : ''}>Pending</option>
                    <option value="completed" ${newStatus === 'completed' ? 'selected' : ''}>Completed</option>
                    <option value="not done" ${newStatus === 'not done' ? 'selected' : ''}>Not Done</option>
                    <option value="can not be done" ${newStatus === 'can not be done' ? 'selected' : ''}>Can't Be Done</option>
                `;
            });
        });
    });
    
    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => {
                alert.remove();
            }, 150);
        }, 5000);
    });
    
    // Tooltip hover functionality
    $('.delay-hover').each(function() {
        var fullDelay = $(this).attr('data-full-delay');
        if (fullDelay) {
            var fullFormatDelay = convertDelayToFullFormat(fullDelay);
            $(this).attr('title', fullFormatDelay);
        }
    });
    
    $('.description-hover').each(function() {
        var fullDescription = $(this).attr('data-full-description');
        if (fullDescription) {
            var tooltipDescription = convertDescriptionForTooltip(fullDescription);
            $(this).attr('title', tooltipDescription);
        }
    });
    
    // Convert abbreviated delay format to full format
    function convertDelayToFullFormat(delay) {
        if (!delay || delay === 'N/A' || delay === 'On Time') {
            return delay;
        }
        return delay
            .replace(/(\d+)\s*D\b/g, '$1 days')
            .replace(/(\d+)\s*d\b/g, '$1 days')
            .replace(/(\d+)\s*h\b/g, '$1 hours')
            .replace(/(\d+)\s*m\b/g, '$1 minutes');
    }
    
    // Convert description format for tooltip
    function convertDescriptionForTooltip(description) {
        if (!description || description === 'N/A') {
            return description;
        }
        return description.replace(/\n/g, '<br>');
    }

    // Priority star toggle functionality
    $(document).on('click', '.priority-star', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $star = $(this);
        var taskId = $star.data('task-id');
        var taskType = $star.data('task-type');
        
        // Show loading state
        $star.css('opacity', '0.5');
        
        $.ajax({
            url: 'action_toggle_priority.php',
            method: 'POST',
            data: {
                task_id: taskId,
                task_type: taskType
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Toggle the active class
                    if (response.priority == 1) {
                        $star.addClass('active');
                        $star.attr('title', 'Click to remove priority');
                    } else {
                        $star.removeClass('active');
                        $star.attr('title', 'Click to mark as priority');
                    }
                    showAlert(response.message, 'success');
                    
                    // Refresh page after a short delay to reflect priority sorting and update both tabs
                    // If marking as priority, redirect to Priority Tasks tab
                    setTimeout(function() {
                        if (response.priority == 1) {
                            // If marking as priority, reload and show Priority Tasks tab
                            var urlParams = new URLSearchParams(window.location.search);
                            urlParams.set('tab', 'priority');
                            // Clear pagination params to show first page
                            urlParams.delete('page');
                            urlParams.delete('priority_page');
                            // Add cache buster to force fresh data load
                            urlParams.set('_t', Date.now());
                            window.location.href = window.location.pathname + '?' + urlParams.toString();
                        } else {
                            // If removing priority, reload on current tab with cache buster
                            var urlParams = new URLSearchParams(window.location.search);
                            urlParams.set('_t', Date.now());
                            window.location.href = window.location.pathname + '?' + urlParams.toString();
                        }
                    }, 800);
                } else {
                    showAlert(response.message || 'Error updating priority', 'danger');
                    $star.css('opacity', '1');
                }
            },
            error: function(xhr, status, error) {
                $star.css('opacity', '1');
                showAlert('Error updating priority. Please try again.', 'danger');
                console.error('Priority toggle error:', error);
            }
        });
    });
    });
}

function updateTaskRow(taskId, taskData) {
    console.log('updateTaskRow called with:', taskId, taskData);
    const row = document.querySelector(`tr[data-task-id="${taskId}"]`);
    console.log('Row found:', row);
    if (!row) {
        console.error('Row not found for taskId:', taskId);
        return;
    }
    
    // Update status icon using the new status column system
    const statusCell = row.querySelector('td.status-column');
    console.log('Status cell found:', statusCell);
    console.log('new_status_icon from server:', taskData.new_status_icon);
    
    if (statusCell && taskData.new_status_icon) {
        // Use the status icon HTML returned from the server
        console.log('Using server-provided status icon');
        statusCell.innerHTML = taskData.new_status_icon;
    } else if (statusCell) {
        // Fallback: generate status icon using JavaScript function
        console.log('Using fallback status icon generation');
        const status = taskData.new_status_text || 'Pending';
        const statusIcon = getStatusIcon(status);
        console.log('Generated status icon:', statusIcon);
        statusCell.innerHTML = statusIcon;
    } else {
        console.error('Status cell not found');
    }
    
    // Update actual date/time if completed
    const actualCell = row.querySelector('td:nth-child(5)');
    if (actualCell) {
        if (taskData.updated_actual_display) {
            actualCell.innerHTML = `<small>${taskData.updated_actual_display}</small>`;
        } else if (taskData.new_status_text === 'completed') {
            // Fallback for completed tasks without server response
            const now = new Date();
            const actualDisplay = now.toLocaleDateString('en-GB', { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric' 
            }) + ' ' + now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            actualCell.innerHTML = `<small>${actualDisplay}</small>`;
        } else {
            actualCell.innerHTML = '<small>-</small>';
        }
    }
    
    // Update delay duration
    const delayCell = row.querySelector('td:nth-child(7)');
    if (delayCell) {
        if (taskData.updated_delay_display_html) {
            delayCell.innerHTML = `<small class="font-weight-bold">${taskData.updated_delay_display_html}</small>`;
        } else if (taskData.new_status_text === 'completed') {
            // Fallback for completed tasks - show "On Time" or calculate delay
            delayCell.innerHTML = '<small class="font-weight-bold"><span class="text-success">On Time</span></small>';
        } else {
            delayCell.innerHTML = '<small class="text-muted">N/A</small>';
        }
    }
}

function showAlert(message, type) {
    const alertContainer = document.querySelector('.manage-tasks-page .card-body');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.insertBefore(alertDiv, alertContainer.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.classList.add('fade');
        setTimeout(() => {
            alertDiv.remove();
        }, 150);
    }, 5000);
}

// Filter toggle functionality
$('#toggleFilters').on('click', function() {
    var filterSection = $('#filterSection');
    filterSection.slideToggle(200);

    var icon = $(this).find('i');
    icon.toggleClass('fa-filter fa-times');

    var textNode = this.childNodes[2];
    if (textNode && textNode.nodeValue) {
        textNode.nodeValue = filterSection.is(':visible') ? ' Filters' : ' Close';
    }
});

// Auto-submit filters on change (selects, radios, dates) and on typing (text inputs with debounce)
(function() {
    var filterForm = document.querySelector('.filter-form');
    if (!filterForm) return;

    var debounceTimer = null;

    filterForm.querySelectorAll('select, input[type="date"]').forEach(function(el) {
        el.addEventListener('change', function() { filterForm.submit(); });
    });

    filterForm.querySelectorAll('input[type="radio"]').forEach(function(el) {
        el.addEventListener('change', function() { filterForm.submit(); });
    });

    filterForm.querySelectorAll('input[type="text"]').forEach(function(el) {
        el.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() { filterForm.submit(); }, 600);
        });
    });
})();

// Auto-refresh every 30 seconds
setInterval(function() {
    // Only refresh if no filters are applied and no user interaction
    if (document.querySelector('form input[name="search"]').value === '' && 
        document.querySelector('form select[name="status"]').value === '') {
        refreshTable();
    }
}, 30000);

// Date picker clickable enhancement
document.addEventListener('DOMContentLoaded', function() {
    const dateInputs = document.querySelectorAll('.date-picker-clickable');
    dateInputs.forEach(input => {
        input.addEventListener('click', function() {
            this.showPicker();
        });
        
        // Also make the input focusable and clickable
        input.addEventListener('focus', function() {
            this.showPicker();
        });
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>