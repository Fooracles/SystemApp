<?php
/**
 * Centralized session management
 * Call this function at the start of any page that needs session access
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Auto-start session for backward compatibility
startSession();

/**
 * Log activity to the log file
 * 
 * @param string $message The message to log
 */
function log_activity($message) {
    $log_file = __DIR__ . '/../log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Check if the user is logged in
function isLoggedIn() {
    if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
        return true;
    }
    return false;
}

// Check if user is admin
function isAdmin() {
    if(isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "admin") {
        return true;
    }
    return false;
}

// Check if user is manager
function isManager() {
    if(isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "manager") {
        return true;
    }
    return false;
}

// Check if user is doer
function isDoer() {
    if(isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "doer") {
        return true;
    }
    return false;
}

// Check if user is client
function isClient() {
    if(isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "client") {
        return true;
    }
    return false;
}

// Redirect to login page if not logged in
function redirectIfNotLoggedIn() {
    if(!isLoggedIn()) {
        // Save the current URL for redirect after login
        $current_url = $_SERVER['REQUEST_URI'];
        $_SESSION['redirect_after_login'] = $current_url;
        
        header("location: login.php");
        exit;
    }
}

// Redirect to appropriate dashboard based on user type
function redirectToDashboard() {
    // Check if there's a saved redirect URL
    if(isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
        $redirect_url = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']); // Clear the saved URL
        
        // Check if user has access to the requested page
        if(hasAccessToPage($redirect_url)) {
            header("location: " . $redirect_url);
            exit;
        } else {
            // User doesn't have access, redirect to appropriate dashboard
            redirectToDefaultDashboard();
        }
    } else {
        // No saved URL, redirect to default dashboard
        redirectToDefaultDashboard();
    }
}

// Redirect to default dashboard based on user type
function redirectToDefaultDashboard() {
    if (isAdmin()) {
        header("location: pages/admin_dashboard.php");
    } elseif (isManager()) {
        header("location: pages/manager_dashboard.php");
    } elseif (isDoer()) {
        header("location: pages/doer_dashboard.php");
    } elseif (isClient()) {
        header("location: pages/client_dashboard.php");
    } else {
        // Default fallback
    header("location: pages/my_task.php");
    }
    exit;
}

// Check if user has access to a specific page
function hasAccessToPage($url) {
    if(!isLoggedIn()) {
        return false;
    }
    
    // Parse the URL to get the page path
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '';
    
    // Remove the base directory if present
    $path = str_replace('/FMS-4.31/', '', $path);
    $path = ltrim($path, '/');
    
    // Define access rules for different pages
    $access_rules = [
        // Admin-only pages
        'pages/admin_dashboard.php' => ['admin'],
        'pages/fms_task.php' => ['admin'],
        'pages/manage_users.php' => ['admin'],
        'pages/manage_apis.php' => ['admin'],
        'pages/api_analytics.php' => ['admin'],
        'pages/logged_in.php' => ['admin'],
        
        // Manager and Admin pages
        'pages/manager_dashboard.php' => ['admin', 'manager'],
        'pages/manage_tasks.php' => ['admin', 'manager'],
        'pages/reports.php' => ['admin', 'manager'],
        
        // Client-only pages
        'pages/client_dashboard.php' => ['client'],
        'pages/task_ticket.php' => ['client'],
        
        // Reports page (Manager and Client)
        'pages/report.php' => ['manager', 'client'],
        
        // Updates page (Admin, Manager, and Client)
        'pages/updates.php' => ['admin', 'manager', 'client'],
        
        // All user pages (admin, manager, doer)
        'pages/doer_dashboard.php' => ['admin', 'manager', 'doer'],
        'pages/my_task.php' => ['admin', 'manager', 'doer'],
        'pages/checklist_task.php' => ['admin', 'manager', 'doer'],
        'pages/add_task.php' => ['admin', 'manager', 'doer'],
        'pages/leave_request.php' => ['admin', 'manager', 'doer'],
        'pages/holiday_list.php' => ['admin', 'manager', 'doer'],
        'pages/my_notes.php' => ['admin', 'manager', 'doer', 'client'],
        'pages/useful_urls.php' => ['admin', 'manager', 'doer', 'client'],
        'pages/profile.php' => ['admin', 'manager', 'doer', 'client'],
        'pages/api_documentation.php' => ['admin', 'manager', 'doer'],
        
        // Shared pages (managers, doers, clients)
        'pages/admin_my_meetings.php' => ['admin', 'manager', 'doer', 'client'],
    ];
    
    // Check if the page has access rules defined
    if(isset($access_rules[$path])) {
        $allowed_roles = $access_rules[$path];
        $user_role = $_SESSION['user_type'] ?? '';
        
        return in_array($user_role, $allowed_roles);
    }
    
    // If no specific rules, allow access (for pages not in the list)
    return true;
}

// Display error message
function displayError($message) {
    echo '<div class="alert alert-danger">' . $message . '</div>';
}

// Display success message
function displaySuccess($message) {
    echo '<div class="alert alert-success">' . $message . '</div>';
}

// Generate unique ID for tasks
function generateUniqueId() {
    $prefix = "DELG-";
    $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    return $prefix . $random;
}

// Format date time - Updated to handle both old and new signatures
function formatDateTime($planned_date, $planned_time = null, $raw_planned = null, $task_type = 'delegation') {
    // Handle new signature (4 parameters)
    if (func_num_args() >= 4) {
        if ($task_type === 'FMS' && !empty($raw_planned)) {
            // For FMS tasks, use consistent date parsing
            $timestamp = null;
            if (function_exists('parseDateConsistent')) {
                $timestamp = parseDateConsistent($raw_planned);
            } else {
                $timestamp = strtotime($raw_planned);
            }
            
            if ($timestamp !== false && $timestamp !== null) {
                return date('d M Y H:i', $timestamp);
            }
        }
        
        if (!empty($planned_date)) {
            if (!empty($planned_time)) {
                // Use consistent date parsing for planned_date + planned_time
                $timestamp = null;
                if (function_exists('parseDateConsistent')) {
                    $timestamp = parseDateConsistent($planned_date . ' ' . $planned_time);
                } else {
                    $timestamp = strtotime($planned_date . ' ' . $planned_time);
                }
                
                if ($timestamp !== false && $timestamp !== null) {
                    return date('d M Y H:i', $timestamp);
                }
            } else {
                // Use consistent date parsing for planned_date only
                $timestamp = null;
                if (function_exists('parseDateConsistent')) {
                    $timestamp = parseDateConsistent($planned_date);
                } else {
                    $timestamp = strtotime($planned_date);
                }
                
                if ($timestamp !== false && $timestamp !== null) {
                    return date('d M Y', $timestamp);
                }
            }
        }
        
        return 'N/A';
    }
    
    // Handle old signature (2 parameters) - backward compatibility
    if (empty($planned_date) || empty($planned_time)) {
        return "N/A";
    }
    
    return date('d-M-Y', strtotime($planned_date)) . ' ' . date('h:i A', strtotime($planned_time));
}

// Calculate delay between planned and actual (or current) times
function calculateDelay($planned_date, $planned_time) {
    if (empty($planned_date) || empty($planned_time)) {
        return "N/A";
    }
    
    $planned = strtotime($planned_date . ' ' . $planned_time);
    $current = time();
    
    if ($current <= $planned) {
        return "No delay";
    }
    
    $diff = $current - $planned;
    
    $days = floor($diff / (60 * 60 * 24));
    $hours = floor(($diff - ($days * 60 * 60 * 24)) / (60 * 60));
    $minutes = floor(($diff - ($days * 60 * 60 * 24) - ($hours * 60 * 60)) / 60);
    
    $delay = "";
    if ($days > 0) {
        $delay .= $days . " day" . ($days > 1 ? "s" : "") . " ";
    }
    if ($hours > 0) {
        $delay .= $hours . " hour" . ($hours > 1 ? "s" : "") . " ";
    }
    if ($minutes > 0) {
        $delay .= $minutes . " minute" . ($minutes > 1 ? "s" : "");
    }
    
    return trim($delay);
}

// Get department name by ID
function getDepartmentName($conn, $department_id) {
    $sql = "SELECT name FROM departments WHERE id = ?";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $department_id);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if(mysqli_num_rows($result) == 1) {
                $row = mysqli_fetch_assoc($result);
                return $row['name'];
            }
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return "N/A";
}

// Get user name by ID
function getUserName($conn, $user_id) {
    $sql = "SELECT name FROM users WHERE id = ?";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if(mysqli_num_rows($result) == 1) {
                $row = mysqli_fetch_assoc($result);
                return $row['name'];
            }
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return "N/A";
}

// Check if task is delayed and update status
function updateTaskDelayStatus($conn, $task_id) {
    $sql = "SELECT id, planned_date, planned_time, status FROM tasks WHERE id = ? AND status = 'pending'";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $task_id);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if(mysqli_num_rows($result) == 1) {
                $task = mysqli_fetch_assoc($result);
                
                $planned = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
                $current = time();
                
                if ($current > $planned) {
                    $delay = calculateDelay($task['planned_date'], $task['planned_time']);
                    
                    $update_sql = "UPDATE tasks SET is_delayed = 1, delay_duration = ? WHERE id = ?";
                    if($update_stmt = mysqli_prepare($conn, $update_sql)) {
                        mysqli_stmt_bind_param($update_stmt, "si", $delay, $task_id);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }
                } else {
                    // Not delayed or no longer delayed (if date was changed)
                    $update_sql = "UPDATE tasks SET is_delayed = 0, delay_duration = NULL WHERE id = ?";
                    if($update_stmt = mysqli_prepare($conn, $update_sql)) {
                        mysqli_stmt_bind_param($update_stmt, "i", $task_id);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }
                }
            }
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Update delay status for all pending tasks
function updateAllTasksDelayStatus($conn) {
    // Use PHP time for consistency with other delay calculations
    $current_timestamp = time();
    $current_date = date('Y-m-d', $current_timestamp);
    $current_time = date('H:i:s', $current_timestamp);
    
    // This SQL will directly update all delayed tasks in one query
    $sql = "UPDATE tasks 
            SET is_delayed = 1, 
                delay_duration = CASE 
                                    WHEN TIMESTAMP(planned_date, planned_time) < TIMESTAMP(?, ?) THEN 
                                        CONCAT(
                                            FLOOR(TIMESTAMPDIFF(SECOND, TIMESTAMP(planned_date, planned_time), TIMESTAMP(?, ?)) / 86400), 'd ',
                                            FLOOR((TIMESTAMPDIFF(SECOND, TIMESTAMP(planned_date, planned_time), TIMESTAMP(?, ?)) % 86400) / 3600), 'h ',
                                            FLOOR(((TIMESTAMPDIFF(SECOND, TIMESTAMP(planned_date, planned_time), TIMESTAMP(?, ?)) % 86400) % 3600) / 60), 'm'
                                        )
                                    ELSE NULL
                                END
            WHERE status = 'pending' AND TIMESTAMP(planned_date, planned_time) < TIMESTAMP(?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssssss", 
            $current_date, $current_time, 
            $current_date, $current_time, 
            $current_date, $current_time, 
            $current_date, $current_time, 
            $current_date, $current_time
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // This SQL will reset tasks that are no longer delayed (e.g. planned_date was updated)
    $sql_reset = "UPDATE tasks 
                  SET is_delayed = 0, delay_duration = NULL 
                  WHERE status = 'pending' AND is_delayed = 1 AND TIMESTAMP(planned_date, planned_time) >= TIMESTAMP(?, ?)";
    
    $stmt_reset = mysqli_prepare($conn, $sql_reset);
    if ($stmt_reset) {
        mysqli_stmt_bind_param($stmt_reset, "ss", $current_date, $current_time);
        mysqli_stmt_execute($stmt_reset);
        mysqli_stmt_close($stmt_reset);
    }
}

// Check if task was completed late and calculate delay
function calculateCompletionDelay($planned_date, $planned_time, $actual_date, $actual_time) {
    if (empty($planned_date) || empty($planned_time) || empty($actual_date) || empty($actual_time)) {
        return "N/A";
    }
    
    $planned = strtotime($planned_date . ' ' . $planned_time);
    $actual = strtotime($actual_date . ' ' . $actual_time);
    
    if ($actual <= $planned) {
        return "On Time";
    }
    
    $diff = $actual - $planned;
    
    $days = floor($diff / (60 * 60 * 24));
    $hours = floor(($diff - ($days * 60 * 60 * 24)) / (60 * 60));
    $minutes = floor(($diff - ($days * 60 * 60 * 24) - ($hours * 60 * 60)) / 60);
    
    $delay = "";
    if ($days > 0) {
        $delay .= $days . " day" . ($days > 1 ? "s" : "") . " ";
    }
    if ($hours > 0) {
        $delay .= $hours . " hour" . ($hours > 1 ? "s" : "") . " ";
    }
    if ($minutes > 0) {
        $delay .= $minutes . " minute" . ($minutes > 1 ? "s" : "");
    }
    
    return trim($delay);
}

// Get all users with their department names
function getAllUsersWithDepartments($conn) {
    $users = [];
    $sql = "SELECT u.id, u.username, u.name, u.email, u.user_type, d.name AS department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            ORDER BY u.username ASC";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
    }
    return $users;
}

// Get all holiday dates
function getHolidays($conn) {
    $holidays = [];
    $sql = "SELECT holiday_date FROM holidays ORDER BY holiday_date ASC";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $holidays[] = $row['holiday_date'];
        }
    }
    return $holidays;
}

// Function to get the count of checklist tasks for the current week (Mon-Sun)
function getCountCurrentWeekChecklistTasks($conn, $user_role = null, $user_name = null) {
    $today = new DateTime();
    $day_of_week = $today->format('N'); // 1 (for Monday) through 7 (for Sunday)
    
    $start_of_week = clone $today;
    $start_of_week->modify('-' . ($day_of_week - 1) . ' days');
    $start_of_week_str = $start_of_week->format('Y-m-d');

    $end_of_week = clone $today;
    $end_of_week->modify('+' . (7 - $day_of_week) . ' days');
    $end_of_week_str = $end_of_week->format('Y-m-d');

    $sql = "SELECT COUNT(*) as total_checklist_tasks FROM checklist_subtasks WHERE task_date >= ? AND task_date <= ?";
    $params = [$start_of_week_str, $end_of_week_str];
    $types = "ss";

    if ($user_role === 'doer' && !empty($user_name)) {
        $sql .= " AND assignee = ?";
        $params[] = $user_name;
        $types .= "s";
    }

    $count = 0;
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $count = (int)$row['total_checklist_tasks'];
            }
        }
        mysqli_stmt_close($stmt);
    }
    return $count;
}

// Function to get checklist tasks for the current week (Mon-Sun)
function getCurrentWeekChecklistTasks($conn, $user_role = null, $user_name = null) {
    $today = new DateTime();
    $day_of_week = $today->format('N'); 
    
    $start_of_week = clone $today;
    $start_of_week->modify('-' . ($day_of_week - 1) . ' days');
    $start_of_week_str = $start_of_week->format('Y-m-d');

    $end_of_week = clone $today;
    $end_of_week->modify('+' . (7 - $day_of_week) . ' days');
    $end_of_week_str = $end_of_week->format('Y-m-d');

    $tasks = [];
    // Select fields similar to delegation tasks for easier merging, adding 'task_type'
    $sql = "SELECT 
                cs.id as task_id, 
                cs.task_code as unique_id, 
                cs.task_description as description, 
                cs.task_date as planned_date, 
                NULL as planned_time, 
                NULL as actual_date, 
                NULL as actual_time, 
                COALESCE(cs.status, 'pending') as status, 
                0 as is_delayed, /* Checklist tasks don't use is_delayed from tasks table */
                NULL as delay_duration, 
                cs.duration, 
                cs.assignee as doer_name, 
                cs.department as department_name, 
                'checklist' as task_type 
            FROM checklist_subtasks cs 
            WHERE cs.task_date >= ? AND cs.task_date <= ?";
    
    $params = [$start_of_week_str, $end_of_week_str];
    $types = "ss";

    if ($user_role === 'doer' && !empty($user_name)) {
        $sql .= " AND cs.assignee = ? AND COALESCE(cs.status, 'pending') = 'pending'";
        $params[] = $user_name;
        $types .= "s";
    }
    $sql .= " ORDER BY cs.task_date ASC, cs.id ASC"; // Added cs.id for stable sort

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $tasks[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    return $tasks;
}

// Function to get checklist tasks up to a specific end date
function getChecklistTasksUpToDate($conn, $endDateStr, $user_role = null, $user_name = null) {
    $tasks = [];
    $sql = "SELECT 
                cs.id as task_id, 
                cs.task_code as unique_id, 
                cs.task_description as description, 
                cs.task_date as planned_date, 
                NULL as planned_time, 
                NULL as actual_date, 
                NULL as actual_time, 
                COALESCE(cs.status, 'pending') as status, 
                0 as is_delayed, 
                NULL as delay_duration, 
                cs.duration, 
                cs.assignee as doer_name, 
                cs.department as department_name, 
                'checklist' as task_type 
            FROM checklist_subtasks cs 
            WHERE cs.task_date <= ?";
    
    $params = [$endDateStr];
    $types = "s";

    // This function is primarily for manager/admin views, 
    // but a role/user filter can be added if needed for other contexts.
    // For now, it fetches all matching the date criteria.
    // if ($user_role === 'doer' && !empty($user_name)) {
    //     $sql .= " AND cs.assignee = ? AND COALESCE(cs.status, 'pending') = 'pending'";
    //     $params[] = $user_name;
    //     $types .= "s";
    // }

    $sql .= " ORDER BY cs.task_date ASC, cs.id ASC";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $tasks[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    return $tasks;
}

// Function to format total seconds into HH:MM:SS string
function formatSecondsToHHMMSS($total_seconds) {
    if (!is_numeric($total_seconds) || $total_seconds < 0) {
        return "00:00:00"; // Or handle error as appropriate
    }
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    $seconds = $total_seconds % 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}

function parseFMSDateTimeString_doer($raw_planned) {
    if (empty($raw_planned)) {
        return null;
    }
    
    // Use the consistent date parsing function
    if (function_exists('parseDateConsistent')) {
        return parseDateConsistent($raw_planned);
    }
    
    // Fallback to original logic if parseDateConsistent is not available
    $raw_planned = str_replace(" at ", " ", $raw_planned);
    try {
        $dt = null;
        if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\s*(\d{1,2}:\d{2}\s*(am|pm)?)/i', $raw_planned, $matches_datetime)) {
            $date_part = str_replace('-', '/', $matches_datetime[1]);
            $time_part = $matches_datetime[2];
            if (preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-](\d{2})$/', $date_part, $year_match)) {
                $year_short = intval($year_match[1]);
                $year_full = ($year_short < 30) ? (2000 + $year_short) : (1900 + $year_short);
                $date_part = preg_replace('/(\d{2})$/', strval($year_full), $date_part, 1);
            }
            $formats_to_try = ['d/m/Y H:iA', 'd/m/Y g:iA', 'd/m/Y G:i', 'd/m/Y H:i', 'j/n/Y H:iA', 'j/n/Y g:iA', 'j/n/Y G:i', 'j/n/Y H:i'];
            foreach($formats_to_try as $format) { $dt = DateTime::createFromFormat($format, $date_part . ' ' . $time_part); if ($dt) break; }
            if (!$dt) $dt = new DateTime($date_part . ' ' . $time_part);
        } elseif (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/', $raw_planned, $matches_date_only)) {
            $date_part = str_replace('-', '/', $matches_date_only[0]);
            if (preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-](\d{2})$/', $date_part, $year_match)) {
                $year_short = intval($year_match[1]);
                $year_full = ($year_short < 30) ? (2000 + $year_short) : (1900 + $year_short);
                $date_part = preg_replace('/(\d{2})$/', strval($year_full), $date_part, 1);
            }
            $formats_to_try = ['d/m/Y', 'j/n/Y'];
            foreach($formats_to_try as $format) { $dt = DateTime::createFromFormat($format, $date_part); if ($dt) break; }
            if ($dt) $dt->setTime(0,0,0);
            else { $parsed_timestamp = strtotime($date_part); if ($parsed_timestamp !== false) { $dt = new DateTime(); $dt->setTimestamp($parsed_timestamp); $dt->setTime(0,0,0); }}
        } else { $parsed_timestamp = strtotime($raw_planned); if ($parsed_timestamp !== false) { $dt = new DateTime(); $dt->setTimestamp($parsed_timestamp); }}
        return $dt ? $dt->getTimestamp() : null;
    } catch (Exception $e) {
        error_log("Failed to parse FMS date string: {$raw_planned} - Error: " . $e->getMessage());
        return null;
    }
}

// Global function for FMS date parsing (moved from my_task.php)
function parseFMSDateTimeString_my_task($dateTimeStr) {
    if (empty(trim($dateTimeStr)) || strtolower(trim($dateTimeStr)) === 'n/a') {
        return null;
    }
    
    // Use the consistent date parsing function
    if (function_exists('parseDateConsistent')) {
        return parseDateConsistent($dateTimeStr);
    }
    
    // Fallback to original logic if parseDateConsistent is not available
    $dateTimeStr = str_replace(" at ", " ", $dateTimeStr);
    try {
        $dt = null;
        if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\s*(\d{1,2}:\d{2}\s*(am|pm)?)/i', $dateTimeStr, $matches_datetime)) {
            $date_part = str_replace('-', '/', $matches_datetime[1]);
            $time_part = $matches_datetime[2];
            if (preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-](\d{2})$/', $date_part, $year_match)) {
                $year_short = intval($year_match[1]);
                $year_full = ($year_short < 30) ? (2000 + $year_short) : (1900 + $year_short);
                $date_part = preg_replace('/(\d{2})$/', strval($year_full), $date_part, 1);
            }
            $formats_to_try = ['d/m/Y H:iA', 'd/m/Y g:iA', 'd/m/Y G:i', 'd/m/Y H:i', 'j/n/Y H:iA', 'j/n/Y g:iA', 'j/n/Y G:i', 'j/n/Y H:i'];
            foreach($formats_to_try as $format) {
                $dt = DateTime::createFromFormat($format, $date_part . ' ' . $time_part);
                if ($dt) break;
            }
            if (!$dt) $dt = new DateTime($date_part . ' ' . $time_part);
        } elseif (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/', $dateTimeStr, $matches_date_only)) {
            $date_part = str_replace('-', '/', $matches_date_only[0]);
            if (preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-](\d{2})$/', $date_part, $year_match)) {
                $year_short = intval($year_match[1]);
                $year_full = ($year_short < 30) ? (2000 + $year_short) : (1900 + $year_short);
                $date_part = preg_replace('/(\d{2})$/', strval($year_full), $date_part, 1);
            }
            $formats_to_try = ['d/m/Y', 'j/n/Y'];
            foreach($formats_to_try as $format) {
                $dt = DateTime::createFromFormat($format, $date_part);
                if ($dt) break;
            }
            if ($dt) {
                $dt->setTime(0,0,0);
            } else {
                $parsed_timestamp = strtotime($date_part);
                if ($parsed_timestamp !== false) {
                    $dt = new DateTime();
                    $dt->setTimestamp($parsed_timestamp);
                    $dt->setTime(0,0,0);
                }
            }
        } else {
            $parsed_timestamp = strtotime($dateTimeStr);
            if ($parsed_timestamp !== false) {
                $dt = new DateTime();
                $dt->setTimestamp($parsed_timestamp);
            }
        }
        return $dt ? $dt->getTimestamp() : null;
    } catch (Exception $e) {
        error_log("Failed to parse FMS date string: {$dateTimeStr} - Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Robust date parsing function that consistently handles dd/mm/yyyy format
 * regardless of server locale settings
 */
function parseDateConsistent($dateString) {
    if (empty(trim($dateString)) || strtolower(trim($dateString)) === 'n/a') {
        return null;
    }
    
    $dateString = trim($dateString);
    
    try {
        // Handle datetime with time component
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\s+(\d{1,2}):(\d{2})\s*(am|pm)?/i', $dateString, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            $hour = (int)$matches[4];
            $minute = (int)$matches[5];
            $ampm = strtolower($matches[6] ?? '');
            
            // Handle 2-digit year
            if ($year < 100) {
                $year = ($year < 30) ? (2000 + $year) : (1900 + $year);
            }
            
            // Convert 12-hour to 24-hour format
            if ($ampm === 'pm' && $hour != 12) {
                $hour += 12;
            } elseif ($ampm === 'am' && $hour == 12) {
                $hour = 0;
            }
            
            // Create DateTime object with explicit dd/mm/yyyy interpretation
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute));
            return $dt ? $dt->getTimestamp() : null;
        }
        
        // Handle date only
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $dateString, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            
            // Handle 2-digit year
            if ($year < 100) {
                $year = ($year < 30) ? (2000 + $year) : (1900 + $year);
            }
            
            // Create DateTime object with explicit dd/mm/yyyy interpretation
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day));
            return $dt ? $dt->getTimestamp() : null;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Date parsing error for '{$dateString}': " . $e->getMessage());
        return null;
    }
}


function formatMinutesToHHMMSS($minutes) {
    if (!is_numeric($minutes) || $minutes < 0) {
        return "00:00:00";
    }
    
    $hours = floor($minutes / 60);
    $remaining_minutes = $minutes % 60;
    
    return sprintf("%02d:%02d:00", $hours, $remaining_minutes);
}

function formatDecimalDurationToHHMMSS($decimal_hours) {
    if (!is_numeric($decimal_hours) || $decimal_hours < 0) {
        return "00:00:00";
    }
    
    $total_minutes = round($decimal_hours * 60);
    $hours = floor($total_minutes / 60);
    $minutes = $total_minutes % 60;
    
    return sprintf("%02d:%02d:00", $hours, $minutes);
}

// Function to format delay with days when greater than 24 hours
function formatDelayWithDays($total_seconds) {
    if (!is_numeric($total_seconds) || $total_seconds < 0) {
        return "00:00:00";
    }
    
    $days = floor($total_seconds / 86400); // 86400 = 24 * 60 * 60
    $remaining_seconds = $total_seconds % 86400;
    
    if ($days > 0) {
        // Format: "X D HH:MM:SS" (shorter format)
        $hours = floor($remaining_seconds / 3600);
        $minutes = floor(($remaining_seconds % 3600) / 60);
        $seconds = $remaining_seconds % 60;
        return sprintf("%d D %02d:%02d:%02d", $days, $hours, $minutes, $seconds);
    } else {
        // Format: "HH:MM:SS" (same as original function)
        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);
        $seconds = $total_seconds % 60;
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
    }
}

// Function to parse delay strings (e.g., "1d 2h 30m" or "1 day 2 hours 5 minutes") into total seconds
function parseDelayStringToSeconds($delay_string) {
    if (empty($delay_string) || $delay_string === "N/A" || $delay_string === "No delay" || $delay_string === "On Time") {
        return 0;
    }

    $total_seconds = 0;
    // Normalize string: remove commas, ensure spaces around d, h, m, s units
    $normalized_string = strtolower($delay_string);
    $normalized_string = str_replace(',', '', $normalized_string);
    $normalized_string = preg_replace('/([dhms])(?!\s)/', '$1 ', $normalized_string); // ensure space after unit if not present

    // Try parsing "Xd Yh Zm Ws" format (e.g., from updateAllTasksDelayStatus)
    if (preg_match('/(?:(\d+)\s*d)?\s*(?:(\d+)\s*h)?\s*(?:(\d+)\s*m)?\s*(?:(\d+)\s*s)?/', $normalized_string, $matches)) {
        if (!empty($matches[0])) { // Check if any part matched
            $days = !empty($matches[1]) ? (int)$matches[1] : 0;
            $hours = !empty($matches[2]) ? (int)$matches[2] : 0;
            $minutes = !empty($matches[3]) ? (int)$matches[3] : 0;
            $seconds = !empty($matches[4]) ? (int)$matches[4] : 0;
            $total_seconds = ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds;
            if ($total_seconds > 0) return $total_seconds;
        }
    }

    // Fallback for more verbose formats like "X day(s) Y hour(s) Z minute(s) W second(s)"
    $days = 0; $hours = 0; $minutes = 0; $seconds = 0;
    if (preg_match('/(\d+)\s*(day|days)/', $normalized_string, $match)) $days = (int)$match[1];
    if (preg_match('/(\d+)\s*(hour|hours)/', $normalized_string, $match)) $hours = (int)$match[1];
    if (preg_match('/(\d+)\s*(minute|minutes)/', $normalized_string, $match)) $minutes = (int)$match[1];
    if (preg_match('/(\d+)\s*(second|seconds)/', $normalized_string, $match)) $seconds = (int)$match[1];
    
    $total_seconds = ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds;
    return $total_seconds;
}



// Alternative function for better debugging
function formatMinutesToHHMMSSDebug($minutes) {
    if (!is_numeric($minutes) || $minutes < 0) {
        return "00:00:00";
    }
    
    $hours = floor($minutes / 60);
    $remaining_minutes = $minutes % 60;
    
    // Debug output
    error_log("DEBUG: Minutes: $minutes, Hours: $hours, Remaining Minutes: $remaining_minutes");
    
    $result = sprintf("%02d:%02d:00", $hours, $remaining_minutes);
    error_log("DEBUG: Formatted result: $result");
    
    return $result;
}

// Function to convert HH:MM:SS to integer minutes (NEW - for form processing)
function convertHHMMSSToMinutes($time_string) {
    if (empty($time_string) || $time_string === '00:00:00') {
        return 0;
    }
    
    // Handle HH:MM:SS format
    if (preg_match('/(\d{2}):(\d{2}):(\d{2})/', $time_string, $matches)) {
        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];
        $seconds = (int)$matches[3];
        
        return ($hours * 60) + $minutes + ($seconds > 30 ? 1 : 0); // Round seconds
    }
    
    return 0;
}

/**
 * Function to fetch data from Google Sheet via API
 * 
 * @param string $sheetId The Google Sheet ID
 * @param string $sheetName The name of the sheet/tab
 * @param string $range The range to fetch (e.g. 'A1:Z1000')
 * @return array The values from the sheet
 * @throws Exception If there's an error fetching the data
 */
function fetchSheetData($sheetId = null, $sheetName = null, $range = null) {
    // Log function entry for debugging
    if (function_exists('log_activity')) {
        log_activity("Entering fetchSheetData function with sheetId: {$sheetId}, sheetName: {$sheetName}");
    }
    
    // Explicitly include the autoload file
    $autoload_path = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload_path)) {
        if (function_exists('log_activity')) {
            log_activity("ERROR: Autoload file not found at: {$autoload_path}");
        }
        throw new Exception("Vendor autoload file not found. Please run 'composer install'.");
    }
    
    require_once $autoload_path;
    
    if (function_exists('log_activity')) {
        log_activity("Autoload file loaded in fetchSheetData");
    }
    
    try {
        // Check if credentials file exists
        if (!file_exists(GOOGLE_APPLICATION_CREDENTIALS)) {
            if (function_exists('log_activity')) {
                log_activity("ERROR: Credentials file not found at: " . GOOGLE_APPLICATION_CREDENTIALS);
            }
            throw new Exception("Credentials file not found at: " . GOOGLE_APPLICATION_CREDENTIALS);
        }
        
        if (function_exists('log_activity')) {
            log_activity("Creating Google Client instance");
        }
        
        // Create the client
        $client = new \Google\Client();
        $client->setApplicationName('Google Sheets API PHP');
        $client->setScopes([\Google\Service\Sheets::SPREADSHEETS_READONLY]);
        
        if (function_exists('log_activity')) {
            log_activity("Setting auth config with credentials file: " . GOOGLE_APPLICATION_CREDENTIALS);
        }
        
        // Use service account directly
        $client->setAuthConfig(GOOGLE_APPLICATION_CREDENTIALS);
        
        if (function_exists('log_activity')) {
            log_activity("Creating Google Sheets service");
        }
        
        // Create the service using the namespaced class
        $service = new \Google\Service\Sheets($client);
        
        // Formulate the range
        $fullRange = empty($range) ? $sheetName : $sheetName . '!' . $range;
        
        if (function_exists('log_activity')) {
            log_activity("Making API call to get sheet values for range: {$fullRange}");
        }
        
        // Make the API call
        $response = $service->spreadsheets_values->get($sheetId, $fullRange);
        
        if (function_exists('log_activity')) {
            log_activity("API call successful, returning " . count($response->getValues()) . " rows");
        }
        
        // Return the values
        return $response->getValues();
    } catch (Exception $e) {
        if (function_exists('log_activity')) {
            log_activity("ERROR in fetchSheetData: " . $e->getMessage());
            
            // Log additional details about the exception
            log_activity("Exception trace: " . $e->getTraceAsString());
            
            // Check if it's a Google API exception with more details
            if (method_exists($e, 'getErrors')) {
                $errors = $e->getErrors();
                log_activity("Google API errors: " . json_encode($errors));
            }
        }
        throw new Exception("Error fetching sheet data: " . $e->getMessage());
    }
}

// Format delay duration for consistent display across all tables
// This function handles multiple input formats and converts them to "X D Y h Z m" format
function formatDelayForDisplay($delay_string) {
    if (empty($delay_string) || $delay_string == 'N/A' || $delay_string == '0:00:00') {
        return 'N/A';
    }
    
    // Handle different input formats
    
    // Format: "X D HH:MM:SS" (from formatDelayWithDays function)
    if (preg_match('/(\d+)\s+D\s+(\d+):(\d+):(\d+)/', $delay_string, $matches)) {
        $days = (int)$matches[1];
        $hours = (int)$matches[2];
        $minutes = (int)$matches[3];
        $seconds = (int)$matches[4];
        
        // Build the display string with shorter format
        $result = '';
        if ($days > 0) {
            $result .= $days . ' D ';
        }
        if ($hours > 0 || $days > 0) {
            $result .= $hours . ' h ';
        }
        if ($minutes > 0 || $hours > 0 || $days > 0) {
            $result .= $minutes . ' m';
        }
        return trim($result);
    }
    
    // Format: "0d 1h 4m" or similar
    if (preg_match('/(\d+)d\s+(\d+)h\s+(\d+)m/', $delay_string, $matches)) {
        $days = (int)$matches[1];
        $hours = (int)$matches[2];
        $minutes = (int)$matches[3];
        
        // Build the display string with shorter format
        $result = '';
        if ($days > 0) {
            $result .= $days . ' D ';
        }
        if ($hours > 0 || $days > 0) {
            $result .= $hours . ' h ';
        }
        $result .= $minutes . ' m';
        return trim($result);
    }
    
    // Format: "HH:MM:SS" or similar
    if (preg_match('/(\d+):(\d+):(\d+)/', $delay_string, $matches)) {
        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];
        
        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        
        // Build the display string with shorter format
        $result = '';
        if ($days > 0) {
            $result .= $days . ' D ';
        }
        if ($remainingHours > 0 || $days > 0) {
            $result .= $remainingHours . ' h ';
        }
        if ($minutes > 0 || $remainingHours > 0 || $days > 0) {
            $result .= $minutes . ' m';
        }
        return trim($result);
    }
    
    // Format: "X days Y hrs Z mins" (legacy format)
    if (preg_match('/(\d+)\s+days?\s+(\d+)\s+hrs?\s+(\d+)\s+mins?/', $delay_string, $matches)) {
        $days = (int)$matches[1];
        $hours = (int)$matches[2];
        $minutes = (int)$matches[3];
        
        // Build the display string with shorter format
        $result = '';
        if ($days > 0) {
            $result .= $days . ' D ';
        }
        if ($hours > 0 || $days > 0) {
            $result .= $hours . ' h ';
        }
        if ($minutes > 0 || $hours > 0 || $days > 0) {
            $result .= $minutes . ' m';
        }
        return trim($result);
    }
    
    // If we can't parse it, return the original string
    return $delay_string;
}

// Function to get forms accessible to current user
function getAccessibleForms($conn) {
    if (!isLoggedIn()) {
        return [];
    }
    
    // Clear cache if it's from a different day (for daily refresh)
    $cache_key = 'accessible_forms_' . $_SESSION['id'] . '_' . $_SESSION['user_type'];
    if (isset($_SESSION[$cache_key . '_date']) && $_SESSION[$cache_key . '_date'] !== date('Y-m-d')) {
        unset($_SESSION[$cache_key]);
        unset($_SESSION[$cache_key . '_date']);
    }
    
    $user_id = $_SESSION['id'];
    $user_role = $_SESSION['user_type'];
    
    // Build the query to get forms accessible to the user
    // Logic:
    // 1. If visible_for = 'all' → show to everyone
    // 2. If visible_for = specific_type AND has entries in form_user_map → show ONLY to assigned users
    // 3. If visible_for = specific_type AND NO entries in form_user_map → show to ALL users of that type
    $sql = "SELECT DISTINCT f.id, f.form_name, f.form_url 
            FROM forms f 
            LEFT JOIN form_user_map fum ON f.id = fum.form_id 
            WHERE f.is_active = 1 
            AND (
                f.visible_for = 'all' 
                OR (
                    f.visible_for = ? 
                    AND (
                        NOT EXISTS (SELECT 1 FROM form_user_map WHERE form_id = f.id)
                        OR EXISTS (SELECT 1 FROM form_user_map WHERE form_id = f.id AND user_id = ?)
                    )
                )
            ) 
            ORDER BY f.form_name";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $user_role, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            $forms = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $forms[] = $row;
            }
            mysqli_stmt_close($stmt);
            
            // Cache the result with date
            $_SESSION[$cache_key] = $forms;
            $_SESSION[$cache_key . '_date'] = date('Y-m-d');
            
            return $forms;
        } else {
            // Log error for debugging
            error_log("Error executing getAccessibleForms query: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return [];
        }
    } else {
        // Log error for debugging
        error_log("Error preparing getAccessibleForms query: " . mysqli_error($conn));
        return [];
    }
}

// Function to get today's special events (birthdays and work anniversaries)
function getTodaysSpecialEvents($conn) {
    // Note: Removed isLoggedIn() check to allow special events to show for all users
    // This makes the feature visible to everyone, not just logged-in users
    
    // Debug logging
    if (isset($_GET['debug_special_events'])) {
        error_log("getTodaysSpecialEvents called - User: " . ($_SESSION['username'] ?? 'unknown') . ", Role: " . ($_SESSION['user_type'] ?? 'unknown'));
    }
    
    // Check cache first
    $cache_key = 'todays_events_' . date('Y-m-d');
    if (isset($_SESSION[$cache_key])) {
        if (isset($_GET['debug_special_events'])) {
            error_log("Returning cached events: " . count($_SESSION[$cache_key]));
        }
        return $_SESSION[$cache_key];
    }
    
    $today = date('m-d'); // Format: MM-DD for comparison
    $current_year = date('Y');
    $events = [];
    
    // Get all users with their dates
    $sql = "SELECT id, name, date_of_birth, joining_date FROM users";
    $result = mysqli_query($conn, $sql);
    
    if (isset($_GET['debug_special_events'])) {
        error_log("Database query executed. Result: " . ($result ? 'Success' : 'Failed'));
        if (!$result) {
            error_log("Database error: " . mysqli_error($conn));
        }
    }
    
    if ($result) {
        $user_count = 0;
        $birthday_count = 0;
        $anniversary_count = 0;
        
        while ($user = mysqli_fetch_assoc($result)) {
            $user_count++;
            
            // Check for birthday
            if (!empty($user['date_of_birth'])) {
                $birthday = date('m-d', strtotime($user['date_of_birth']));
                if ($birthday === $today) {
                    $birthday_count++;
                    $events[] = [
                        'type' => 'birthday',
                        'name' => $user['name'],
                        'icon' => 'fas fa-birthday-cake',
                        'text' => $user['name'] . ' - Birthday'
                    ];
                    
                    if (isset($_GET['debug_special_events'])) {
                        error_log("Found birthday match: " . $user['name'] . " (" . $user['date_of_birth'] . ")");
                    }
                }
            }
            
            // Check for work anniversary
            if (!empty($user['joining_date'])) {
                $joining_date = date('m-d', strtotime($user['joining_date']));
                if ($joining_date === $today) {
                    $anniversary_count++;
                    $years_worked = $current_year - date('Y', strtotime($user['joining_date']));
                    $events[] = [
                        'type' => 'anniversary',
                        'name' => $user['name'],
                        'icon' => 'fas fa-trophy',
                        'text' => $user['name'] . ' - ' . $years_worked . ' Yrs Work Anniversary'
                    ];
                    
                    if (isset($_GET['debug_special_events'])) {
                        error_log("Found anniversary match: " . $user['name'] . " (" . $user['joining_date'] . ")");
                    }
                }
            }
        }
        
        if (isset($_GET['debug_special_events'])) {
            error_log("Processed $user_count users. Found $birthday_count birthdays and $anniversary_count anniversaries. Total events: " . count($events));
        }
    } else {
        if (isset($_GET['debug_special_events'])) {
            error_log("Database query failed: " . mysqli_error($conn));
        }
    }
    
    // Cache the result for today
    $_SESSION[$cache_key] = $events;
    return $events;
}

/**
 * Simple caching mechanism for frequently accessed data
 * 
 * @param string $key Cache key
 * @param callable $callback Function to execute if cache miss
 * @param int $ttl Time to live in seconds (default: 300 = 5 minutes)
 * @return mixed Cached or fresh data
 */
function getCachedData($key, $callback, $ttl = 300) {
    $cache_key = 'cache_' . $key;
    $timestamp_key = 'cache_timestamp_' . $key;
    
    // Check if cache exists and is still valid
    if (isset($_SESSION[$cache_key]) && isset($_SESSION[$timestamp_key])) {
        if (time() - $_SESSION[$timestamp_key] < $ttl) {
            return $_SESSION[$cache_key];
        }
    }
    
    // Cache miss or expired - execute callback
    try {
        $data = $callback();
        
        // Store in cache
        $_SESSION[$cache_key] = $data;
        $_SESSION[$timestamp_key] = time();
        
        return $data;
    } catch (Exception $e) {
        // Log error and return empty result
        error_log("Cache callback error for key '$key': " . $e->getMessage());
        return null;
    }
}

/**
 * Enhanced error handling for database operations
 * 
 * @param mysqli $conn Database connection
 * @param string $operation Operation description
 * @param callable $callback Database operation callback
 * @return array Result with success status and data/error
 */
function safeDbOperation($conn, $operation, $callback) {
    try {
        $result = $callback($conn);
        return [
            'success' => true,
            'data' => $result,
            'error' => null
        ];
    } catch (Exception $e) {
        $error_msg = "Database error in $operation: " . $e->getMessage();
        error_log($error_msg);
        
        return [
            'success' => false,
            'data' => null,
            'error' => $error_msg
        ];
    }
}

/**
 * Validate and sanitize user input
 * 
 * @param mixed $input Input to validate
 * @param string $type Expected type (string, int, email, etc.)
 * @param int $max_length Maximum length for strings
 * @return mixed Sanitized input or false if invalid
 */
function validateInput($input, $type = 'string', $max_length = 255) {
    if ($input === null || $input === '') {
        return false;
    }
    
    switch ($type) {
        case 'string':
            $sanitized = trim(strip_tags($input));
            return strlen($sanitized) <= $max_length ? $sanitized : false;
            
        case 'int':
            return is_numeric($input) ? (int)$input : false;
            
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
            
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL);
            
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Create a notification for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID to notify
 * @param string $type Notification type (task_delay, meeting_request, meeting_approved, meeting_rescheduled, day_special, notes_reminder, leave_request, leave_approved, leave_rejected)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param int|string|null $related_id ID of related record
 * @param string|null $related_type Type of related record (task, meeting, leave, note, etc.)
 * @param bool $action_required Whether notification requires user action
 * @param array|null $action_data JSON data for action buttons
 * @return int|false Notification ID on success, false on failure
 */
function createNotification($conn, $user_id, $type, $title, $message, $related_id = null, $related_type = null, $action_required = false, $action_data = null) {
    // First, check if table exists and has required columns
    $table_check = "SHOW TABLES LIKE 'notifications'";
    $table_result = mysqli_query($conn, $table_check);
    
    if (mysqli_num_rows($table_result) == 0) {
        error_log("Notifications table does not exist. Please run setup_notifications_table.php first.");
        return false;
    }
    
    // Check if related_id column exists
    $column_check = "SHOW COLUMNS FROM notifications LIKE 'related_id'";
    $column_result = mysqli_query($conn, $column_check);
    
    if (mysqli_num_rows($column_result) == 0) {
        error_log("Notifications table is missing required columns. Please run setup_notifications_table.php first.");
        return false;
    }
    
    $action_data_json = $action_data ? json_encode($action_data) : null;
    
    // Convert related_id to string to handle both INT and VARCHAR (like leave unique_service_no)
    $related_id_value = $related_id !== null ? (string)$related_id : null;
    
    // Convert boolean to integer for MySQL
    $action_required_int = $action_required ? 1 : 0;
    
    // Check for duplicate notification before inserting
    // Duplicate criteria varies by notification type
    $duplicate_check_query = "";
    $duplicate_params = [];
    $duplicate_types = "";
    
    switch ($type) {
        case 'task_delay':
            // For task delays: same user, same task, same day
            $duplicate_check_query = "SELECT id FROM notifications 
                                     WHERE user_id = ? 
                                     AND type = ? 
                                     AND related_id = ? 
                                     AND related_type = ?
                                     AND DATE(created_at) = CURDATE()";
            $duplicate_params = [$user_id, $type, $related_id_value, $related_type];
            $duplicate_types = "isss";
            break;
            
        case 'day_special':
            // For day special: same user, same type, same related_id (user), same day
            $duplicate_check_query = "SELECT id FROM notifications 
                                     WHERE user_id = ? 
                                     AND type = ? 
                                     AND related_id = ? 
                                     AND DATE(created_at) = CURDATE()";
            $duplicate_params = [$user_id, $type, $related_id_value];
            $duplicate_types = "iss";
            break;
            
        case 'leave_request':
            // For leave requests: same user, same leave_id, unread
            $duplicate_check_query = "SELECT id FROM notifications 
                                     WHERE user_id = ? 
                                     AND type = ? 
                                     AND related_id = ? 
                                     AND is_read = 0";
            $duplicate_params = [$user_id, $type, $related_id_value];
            $duplicate_types = "iss";
            break;
            
        case 'notes_reminder':
            // For notes reminders: same user, same note_id, same day
            $duplicate_check_query = "SELECT id FROM notifications 
                                     WHERE user_id = ? 
                                     AND type = ? 
                                     AND related_id = ? 
                                     AND DATE(created_at) = CURDATE()";
            $duplicate_params = [$user_id, $type, $related_id_value];
            $duplicate_types = "iss";
            break;
            
        case 'meeting_request':
        case 'meeting_approved':
        case 'meeting_rescheduled':
            // For meetings: same user, same meeting_id, unread (for requests) or same day (for others)
            if ($type === 'meeting_request') {
                $duplicate_check_query = "SELECT id FROM notifications 
                                         WHERE user_id = ? 
                                         AND type = ? 
                                         AND related_id = ? 
                                         AND is_read = 0";
                $duplicate_params = [$user_id, $type, $related_id_value];
                $duplicate_types = "iss";
            } else {
                $duplicate_check_query = "SELECT id FROM notifications 
                                         WHERE user_id = ? 
                                         AND type = ? 
                                         AND related_id = ? 
                                         AND DATE(created_at) = CURDATE()";
                $duplicate_params = [$user_id, $type, $related_id_value];
                $duplicate_types = "iss";
            }
            break;
            
        case 'leave_approved':
        case 'leave_rejected':
            // For leave status updates: same user, same leave_id, same day
            $duplicate_check_query = "SELECT id FROM notifications 
                                     WHERE user_id = ? 
                                     AND type = ? 
                                     AND related_id = ? 
                                     AND DATE(created_at) = CURDATE()";
            $duplicate_params = [$user_id, $type, $related_id_value];
            $duplicate_types = "iss";
            break;
            
        default:
            // For other types: same user, same type, same related_id, same day
            if ($related_id_value !== null) {
                $duplicate_check_query = "SELECT id FROM notifications 
                                         WHERE user_id = ? 
                                         AND type = ? 
                                         AND related_id = ? 
                                         AND DATE(created_at) = CURDATE()";
                $duplicate_params = [$user_id, $type, $related_id_value];
                $duplicate_types = "iss";
            }
            break;
    }
    
    // Check for duplicate if query is set
    if (!empty($duplicate_check_query) && !empty($duplicate_params)) {
        $duplicate_stmt = mysqli_prepare($conn, $duplicate_check_query);
        if ($duplicate_stmt) {
            mysqli_stmt_bind_param($duplicate_stmt, $duplicate_types, ...$duplicate_params);
            mysqli_stmt_execute($duplicate_stmt);
            $duplicate_result = mysqli_stmt_get_result($duplicate_stmt);
            
            if (mysqli_num_rows($duplicate_result) > 0) {
                // Duplicate found, return existing notification ID
                $existing_notification = mysqli_fetch_assoc($duplicate_result);
                mysqli_stmt_close($duplicate_stmt);
                return (int)$existing_notification['id'];
            }
            mysqli_stmt_close($duplicate_stmt);
        }
    }
    
    $query = "INSERT INTO notifications (user_id, type, title, message, related_id, related_type, action_required, action_data) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        $error_msg = "Failed to prepare notification query: " . mysqli_error($conn);
        error_log($error_msg);
        return false;
    }
    
    // Bind parameters: i=integer, s=string
    // user_id (i), type (s), title (s), message (s), related_id (s), related_type (s), action_required (i), action_data (s)
    mysqli_stmt_bind_param($stmt, 'isssssis', $user_id, $type, $title, $message, $related_id_value, $related_type, $action_required_int, $action_data_json);
    
    if (!mysqli_stmt_execute($stmt)) {
        $error_msg = "Failed to execute notification query: " . mysqli_stmt_error($stmt);
        error_log($error_msg);
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $notification_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    return $notification_id;
}

/**
 * Get unread notification count for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return int Unread count
 */
function getUnreadNotificationCount($conn, $user_id) {
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return 0;
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return (int)($row['count'] ?? 0);
}

/**
 * Get client IP address
 * 
 * @return string IP address
 */
function getClientIpAddress() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get device/browser information
 * 
 * @return string Device and browser info
 */
function getDeviceInfo() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Detect browser (order matters - check more specific first)
    $browser = 'Unknown';
    if (preg_match('/Edg\//i', $user_agent)) {
        $browser = 'Edge';  // New Chromium-based Edge uses "Edg/"
    } elseif (preg_match('/Edge/i', $user_agent)) {
        $browser = 'Edge (Legacy)';  // Old Edge
    } elseif (preg_match('/OPR|Opera/i', $user_agent)) {
        $browser = 'Opera';  // Opera also contains "Chrome", check first
    } elseif (preg_match('/Chrome/i', $user_agent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Safari/i', $user_agent)) {
        $browser = 'Safari';
    } elseif (preg_match('/Firefox/i', $user_agent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/MSIE|Trident/i', $user_agent)) {
        $browser = 'Internet Explorer';
    }
    
    // Detect OS
    $os = 'Unknown';
    if (preg_match('/Windows NT 10.0/i', $user_agent)) {
        $os = 'Windows 10/11';
    } elseif (preg_match('/Windows NT 6.3/i', $user_agent)) {
        $os = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6.2/i', $user_agent)) {
        $os = 'Windows 8';
    } elseif (preg_match('/Windows NT 6.1/i', $user_agent)) {
        $os = 'Windows 7';
    } elseif (preg_match('/Windows/i', $user_agent)) {
        $os = 'Windows';
    } elseif (preg_match('/Mac OS X/i', $user_agent)) {
        $os = 'macOS';
    } elseif (preg_match('/Android/i', $user_agent)) {
        $os = 'Android';
    } elseif (preg_match('/iPhone|iPad|iPod/i', $user_agent)) {
        $os = 'iOS';
    } elseif (preg_match('/Linux/i', $user_agent)) {
        $os = 'Linux';
    }
    
    return $browser . ' / ' . $os;
}

/**
 * Check if this is a new device for the user
 * 
 * @param mysqli $conn Database connection
 * @param string $username Username
 * @param string $ip_address IP address
 * @param string $device_info Device info
 * @return bool True if new device, false otherwise
 */
function isNewDevice($conn, $username, $ip_address, $device_info) {
    // Check if user has any previous sessions with same IP and device
    $sql = "SELECT COUNT(*) as count FROM user_sessions 
            WHERE username = ? AND ip_address = ? AND device_info = ? 
            AND login_time > DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sss", $username, $ip_address, $device_info);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return (int)($row['count'] ?? 0) == 0;
    }
    
    return true; // Assume new device if query fails
}

/**
 * Log user login session
 * 
 * @param mysqli $conn Database connection
 * @param string $username Username
 * @return int|false Session ID on success, false on failure
 */
function logUserLogin($conn, $username) {
    $ip_address = getClientIpAddress();
    $device_info = getDeviceInfo();
    $login_time = date('Y-m-d H:i:s');
    
    // Check if there's an active session for this username with same device/IP
    // If yes, don't create a new session (multiple tabs on same device)
    $check_sql = "SELECT id FROM user_sessions 
                  WHERE username = ? AND ip_address = ? AND device_info = ? AND is_active = 1";
    
    if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($check_stmt, "sss", $username, $ip_address, $device_info);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($result) > 0) {
            // Active session exists for this device, return its ID
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($check_stmt);
            return (int)$row['id'];
        }
        mysqli_stmt_close($check_stmt);
    }
    
    // Create new session
    $sql = "INSERT INTO user_sessions (username, login_time, ip_address, device_info, is_active) 
            VALUES (?, ?, ?, ?, 1)";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssss", $username, $login_time, $ip_address, $device_info);
        
        if (mysqli_stmt_execute($stmt)) {
            $session_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            
            // Store session ID in PHP session for logout tracking
            $_SESSION['session_log_id'] = $session_id;
            $_SESSION['login_time'] = strtotime($login_time);
            
            return $session_id;
        }
        mysqli_stmt_close($stmt);
    }
    
    return false;
}

/**
 * Log user logout session
 * 
 * @param mysqli $conn Database connection
 * @param string $username Username
 * @param string $reason Logout reason (manual, auto, admin_forced)
 * @return bool True on success, false on failure
 */
function logUserLogout($conn, $username, $reason = 'manual') {
    $session_id = $_SESSION['session_log_id'] ?? null;
    $logout_time = date('Y-m-d H:i:s');
    
    if ($session_id) {
        // Update specific session
        $sql = "UPDATE user_sessions 
                SET logout_time = ?, 
                    duration_seconds = TIMESTAMPDIFF(SECOND, login_time, ?),
                    logout_reason = ?,
                    is_active = 0
                WHERE id = ? AND username = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssis", $logout_time, $logout_time, $reason, $session_id, $username);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $result;
        }
    } else {
        // Fallback: update all active sessions for this username
        $sql = "UPDATE user_sessions 
                SET logout_time = ?, 
                    duration_seconds = TIMESTAMPDIFF(SECOND, login_time, ?),
                    logout_reason = ?,
                    is_active = 0
                WHERE username = ? AND is_active = 1";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssss", $logout_time, $logout_time, $reason, $username);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $result;
        }
    }
    
    return false;
}

/**
 * Force logout a user (admin function)
 * 
 * @param mysqli $conn Database connection
 * @param string $username Username to logout
 * @return bool True on success, false on failure
 */
function forceLogoutUser($conn, $username) {
    $logout_time = date('Y-m-d H:i:s');
    
    $sql = "UPDATE user_sessions 
            SET logout_time = ?, 
                duration_seconds = TIMESTAMPDIFF(SECOND, login_time, ?),
                logout_reason = 'admin_forced',
                is_active = 0
            WHERE username = ? AND is_active = 1";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sss", $logout_time, $logout_time, $username);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    
    return false;
}

/**
 * Check if current time is 8:00 PM (20:00) - auto logout time
 * 
 * @return bool True if current time is 8:00 PM, false otherwise
 */
function isSessionExpired() {
    $current_hour = (int)date('H');
    $current_minute = (int)date('i');
    
    // Check if current time is exactly 8:00 PM (20:00)
    // Allow a 1-minute window to catch the logout (20:00 to 20:01)
    if ($current_hour == 20 && $current_minute >= 0 && $current_minute <= 1) {
        return true;
    }
    
    return false;
}

/**
 * Auto-logout all active sessions at 8:00 PM
 * This function should be called on every page load to check if it's 8:00 PM
 * 
 * @param mysqli $conn Database connection
 * @return int Number of sessions logged out
 */
function autoLogoutAllSessionsAt8PM($conn) {
    $current_hour = (int)date('H');
    $current_minute = (int)date('i');
    
    // Only run if current time is exactly 8:00 PM (20:00)
    // Allow a 1-minute window (20:00 to 20:01)
    if ($current_hour != 20 || $current_minute > 1) {
        return 0;
    }
    
    // Check if we already logged out today (prevent multiple logouts in the same minute)
    $today = date('Y-m-d');
    $check_sql = "SELECT COUNT(*) as count FROM user_sessions 
                  WHERE DATE(logout_time) = ? 
                  AND logout_reason = 'auto' 
                  AND HOUR(logout_time) = 20 
                  LIMIT 1";
    
    $check_stmt = mysqli_prepare($conn, $check_sql);
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "s", $today);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $check_row = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);
        
        // If already logged out today at 8 PM, skip
        if ((int)$check_row['count'] > 0) {
            return 0;
        }
    }
    
    // Logout all active sessions
    $logout_time = date('Y-m-d H:i:s');
    $sql = "UPDATE user_sessions 
            SET logout_time = ?,
                duration_seconds = TIMESTAMPDIFF(SECOND, login_time, ?),
                logout_reason = 'auto',
                is_active = 0
            WHERE is_active = 1";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $logout_time, $logout_time);
        mysqli_stmt_execute($stmt);
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        if ($affected_rows > 0) {
            error_log("Auto-logged out $affected_rows session(s) at 8:00 PM");
        }
        
        return $affected_rows;
    }
    
    return 0;
}

/**
 * Format duration in seconds to human-readable format
 * 
 * @param int $seconds Duration in seconds
 * @return string Formatted duration (e.g., "5h 30m")
 */
function formatSessionDuration($seconds) {
    if ($seconds === null || $seconds === 0) {
        return '0m';
    }
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $result = '';
    if ($hours > 0) {
        $result .= $hours . 'h ';
    }
    if ($minutes > 0 || $hours == 0) {
        $result .= $minutes . 'm';
    }
    
    return trim($result);
}

/**
 * Get Monday of the week for a given date (week runs Monday to Sunday)
 * 
 * @param string|DateTime $date Date string (Y-m-d) or DateTime object
 * @return DateTime Monday of that week
 */
if (!function_exists('getMondayOfWeek')) {
function getMondayOfWeek($date) {
    if (is_string($date)) {
            $dt = new DateTime($date);
        } else {
            $dt = clone $date;
    }
        $dt->setTime(0, 0, 0);
        
        // Get day of week: 1 = Monday, 7 = Sunday
        $dayOfWeek = (int)$dt->format('N');
        
        // Calculate days to subtract to get to Monday
        $daysToSubtract = $dayOfWeek - 1;
        $dt->modify("-{$daysToSubtract} days");
        
        return $dt;
    }
}

/**
 * Get Sunday of the week for a given date (week runs Monday to Sunday)
 * 
 * @param string|DateTime $date Date string (Y-m-d) or DateTime object
 * @return DateTime Sunday of that week
 */
if (!function_exists('getSundayOfWeek')) {
function getSundayOfWeek($date) {
    $monday = getMondayOfWeek($date);
    $sunday = clone $monday;
    $sunday->modify('+6 days');
        $sunday->setTime(23, 59, 59);
    return $sunday;
    }
}

/**
 * Check if a date falls within a specific week (Monday to Sunday)
 * 
 * @param string $date Date string (Y-m-d)
 * @param string $weekStart Monday of the week (Y-m-d)
 * @return bool True if date is in the week
 */
if (!function_exists('isDateInWeek')) {
function isDateInWeek($date, $weekStart) {
        if (empty($date) || empty($weekStart)) {
            return false;
        }
        
        $dateDt = new DateTime($date);
        $weekStartDt = new DateTime($weekStart);
        $weekEndDt = clone $weekStartDt;
        $weekEndDt->modify('+6 days');
        $weekEndDt->setTime(23, 59, 59);
        
        return $dateDt >= $weekStartDt && $dateDt <= $weekEndDt;
    }
}

/**
 * Get the week start (Monday) for a given date
 * 
 * @param string $date Date string (Y-m-d)
 * @return string Monday of that week (Y-m-d)
 */
if (!function_exists('getWeekStartForDate')) {
    function getWeekStartForDate($date) {
        if (empty($date)) {
            return null;
        }
        $monday = getMondayOfWeek($date);
        return $monday->format('Y-m-d');
    }
}

/**
 * Check if a task is Pending for a selected week
 * 
 * PENDING TASK LOGIC:
 * A task should be counted as Pending for a selected week if ANY of the following conditions are true:
 * 1. Task status is "Pending" or not marked as "Completed".
 * 2. Task is marked as "Completed", BUT its actual completion date/time falls outside the week in which the task was planned.
 * 
 * @param string $status Task status
 * @param string $planned_date Planned date (Y-m-d)
 * @param string $planned_time Planned time (H:i:s)
 * @param string $actual_date Actual completion date (Y-m-d)
 * @param string $actual_time Actual completion time (H:i:s)
 * @param string $weekStart Monday of the selected week (Y-m-d)
 * @param string $task_type Task type ('delegation', 'fms', 'checklist')
 * @return bool True if task is pending for the week
 */
if (!function_exists('isTaskPendingForWeek')) {
    function isTaskPendingForWeek($status, $planned_date, $planned_time, $actual_date, $actual_time, $weekStart, $task_type = 'delegation') {
        if (empty($planned_date) || empty($weekStart)) {
        return false;
    }
    
    // Normalize status
        $status_lower = strtolower(trim($status ?? ''));
        
        // For FMS tasks, check if they have actual data
        if ($task_type === 'fms') {
            $has_actual_data = !empty($actual_date) || !empty($actual_time);
            if (!$has_actual_data) {
                // No actual data = pending (if planned in this week)
                return isDateInWeek($planned_date, $weekStart);
            }
        }
        
        // Check if task was planned in the selected week
    $planned_in_week = isDateInWeek($planned_date, $weekStart);
    if (!$planned_in_week) {
            return false; // Task not planned in this week
    }
    
        // Condition 1: Task status is "Pending" or not marked as "Completed"
        $completed_statuses = ['completed', 'done'];
        $is_completed = in_array($status_lower, $completed_statuses);
        
    if (!$is_completed) {
            return true; // Not completed = pending
    }
    
        // Condition 2: Task is marked as "Completed", BUT its actual completion date/time falls outside the week
        if ($is_completed && !empty($actual_date)) {
            $actual_in_week = isDateInWeek($actual_date, $weekStart);
            // If completed but actual date is NOT in the planned week, it's still pending for that week
            return !$actual_in_week;
    }
    
        // If completed and no actual date, or actual date is in the same week, it's not pending
    return false;
}

/**
 * Check if a task is Delayed for a selected week
 * 
 * DELAYED TASK LOGIC:
 * Delayed tasks should be counted ONLY under these conditions:
 * 1. Task status is "Completed".
 * 2. Actual completion date/time is later than the planned date/time.
 * 3. Task was completed within the selected week.
 * 4. Tasks already counted as Pending for that week must be EXCLUDED.
 * 
 * @param string $status Task status
 * @param string $planned_date Planned date (Y-m-d)
 * @param string $planned_time Planned time (H:i:s)
 * @param string $actual_date Actual completion date (Y-m-d)
 * @param string $actual_time Actual completion time (H:i:s)
 * @param string $weekStart Monday of the selected week (Y-m-d)
 * @param string $task_type Task type ('delegation', 'fms', 'checklist')
 * @return bool True if task is delayed for the week
 */
if (!function_exists('isTaskDelayedForWeek')) {
    function isTaskDelayedForWeek($status, $planned_date, $planned_time, $actual_date, $actual_time, $weekStart, $task_type = 'delegation') {
        // Condition 1: Task status is "Completed"
        $status_lower = strtolower(trim($status ?? ''));
        $completed_statuses = ['completed', 'done'];
        $is_completed = in_array($status_lower, $completed_statuses);
        
    if (!$is_completed) {
            return false; // Not completed = not delayed
    }
    
        // Condition 2: Actual completion date/time is later than the planned date/time
        if (empty($actual_date) || empty($planned_date)) {
            return false; // No actual date = not delayed
    }
    
    // Build timestamps for comparison
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
        
        $planned_ts = strtotime($planned_datetime);
        $actual_ts = strtotime($actual_datetime);
    
        if ($planned_ts === false || $actual_ts === false || $actual_ts <= $planned_ts) {
            return false; // Not delayed (on time or early)
        }
        
        // Condition 3: Task was completed within the selected week
        $actual_in_week = isDateInWeek($actual_date, $weekStart);
        if (!$actual_in_week) {
            return false; // Completed outside the week
        }
        
        // Condition 4: Tasks already counted as Pending for that week must be EXCLUDED
        // If task was planned in the week but completed outside, it's pending (not delayed)
        $planned_in_week = isDateInWeek($planned_date, $weekStart);
        if ($planned_in_week) {
            // Task was planned in this week
            // If it was completed in the same week, it can be delayed
            // If it was completed outside, it's pending (already excluded above by actual_in_week check)
            return true; // Planned in week, completed in week, and delayed = delayed
        }
        
        // Task was planned outside the week but completed in the week
        // This is still a delayed task for this week (completed within the week and delayed)
        // It's not pending because it wasn't planned in this week
        return true;
    }
}

/**
 * Count pending tasks for a week from a task array
 * 
 * @param array $tasks Array of tasks with status, planned_date, planned_time, actual_date, actual_time, task_type
 * @param string $weekStart Monday of the selected week (Y-m-d)
 * @return int Count of pending tasks
 */
if (!function_exists('countPendingTasksForWeek')) {
    function countPendingTasksForWeek($tasks, $weekStart) {
        $count = 0;
        foreach ($tasks as $task) {
            if (isTaskPendingForWeek(
                $task['status'] ?? '',
                $task['planned_date'] ?? '',
                $task['planned_time'] ?? '',
                $task['actual_date'] ?? '',
                $task['actual_time'] ?? '',
                $weekStart,
                $task['task_type'] ?? 'delegation'
            )) {
                $count++;
            }
        }
        return $count;
    }
}

/**
 * Count delayed tasks for a week from a task array
 * 
 * @param array $tasks Array of tasks with status, planned_date, planned_time, actual_date, actual_time, task_type
 * @param string $weekStart Monday of the selected week (Y-m-d)
 * @return int Count of delayed tasks
 */
if (!function_exists('countDelayedTasksForWeek')) {
    function countDelayedTasksForWeek($tasks, $weekStart) {
        $count = 0;
        foreach ($tasks as $task) {
            if (isTaskDelayedForWeek(
                $task['status'] ?? '',
                $task['planned_date'] ?? '',
                $task['planned_time'] ?? '',
                $task['actual_date'] ?? '',
                $task['actual_time'] ?? '',
                $weekStart,
                $task['task_type'] ?? 'delegation'
            )) {
                $count++;
            }
        }
        return $count;
    }
    }
}
?> 