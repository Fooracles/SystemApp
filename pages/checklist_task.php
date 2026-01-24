<?php
// Load core dependencies BEFORE any output so we can safely redirect
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Handle form submission BEFORE any output
$error_msg_create = "";
$success_msg_create = "";
$skipped_dates_msg_create = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_checklist_task'])) {
    $f_assignee_id = trim($_POST['assignee_id']);
    $f_assigned_by = trim($_POST['assigned_by']);
    $f_start_date_create = trim($_POST['start_date_create']);
    $f_end_date_create = trim($_POST['end_date_create']);
    $f_frequency = trim($_POST['frequency']);
    $f_duration = trim($_POST['duration']);
    $f_task_description = trim($_POST['task_description']);

    if (empty($f_assignee_id) || empty($f_assigned_by) || empty($f_start_date_create) || empty($f_end_date_create) || empty($f_frequency) || empty($f_duration) || empty($f_task_description)) {
        $error_msg_create = "All task creation form fields are required.";
    } else {
        try {
            $start_dt_obj_create = new DateTime($f_start_date_create);
            $end_dt_obj_create = new DateTime($f_end_date_create);
            if ($start_dt_obj_create > $end_dt_obj_create) {
                $error_msg_create = "End date for creation must be on or after start date.";
            }
        } catch (Exception $e) {
            $error_msg_create = "Invalid date format for creation.";
        }

        if (empty($error_msg_create)) {
            // Get users based on user role for validation
            $all_users = [];
            
            if (isAdmin()) {
                // Admin can assign to all users
                $users_query = "SELECT id, username, name, manager_id FROM users WHERE Status = 'Active' ORDER BY username";
                $users_result = mysqli_query($conn, $users_query);
                if ($users_result) {
                    while ($row = mysqli_fetch_assoc($users_result)) {
                        $all_users[] = $row;
                    }
                }
            } else {
                // Manager can only assign to their direct reports
                $current_user_id = $_SESSION['id'];
                
                // Get manager's own record
                $manager_sql = "SELECT id, username, name, manager_id FROM users WHERE id = ? AND Status = 'Active'";
                $manager_stmt = mysqli_prepare($conn, $manager_sql);
                if ($manager_stmt) {
                    mysqli_stmt_bind_param($manager_stmt, "i", $current_user_id);
                    if (mysqli_stmt_execute($manager_stmt)) {
                        $manager_result = mysqli_stmt_get_result($manager_stmt);
                        if ($manager_row = mysqli_fetch_assoc($manager_result)) {
                            $all_users[] = $manager_row;
                        }
                    }
                    mysqli_stmt_close($manager_stmt);
                }
                
                // Get manager's name for filtering direct reports
                $manager_name_sql = "SELECT name FROM users WHERE id = ?";
                $manager_name_stmt = mysqli_prepare($conn, $manager_name_sql);
                $manager_name = '';
                if ($manager_name_stmt) {
                    mysqli_stmt_bind_param($manager_name_stmt, "i", $current_user_id);
                    if (mysqli_stmt_execute($manager_name_stmt)) {
                        $manager_name_result = mysqli_stmt_get_result($manager_name_stmt);
                        if ($manager_name_row = mysqli_fetch_assoc($manager_name_result)) {
                            $manager_name = $manager_name_row['name'];
                        }
                    }
                    mysqli_stmt_close($manager_name_stmt);
                }
                
                // Get direct reports using manager name
                if (!empty($manager_name)) {
                    $users_query = "SELECT id, username, name, manager_id FROM users WHERE manager = ? AND user_type = 'doer' AND Status = 'Active' ORDER BY username";
                    $stmt = mysqli_prepare($conn, $users_query);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "s", $manager_name);
                        if (mysqli_stmt_execute($stmt)) {
                            $users_result = mysqli_stmt_get_result($stmt);
                            while ($row = mysqli_fetch_assoc($users_result)) {
                                $all_users[] = $row;
                            }
                        }
                        mysqli_stmt_close($stmt);
                    }
                }
            }

            $selected_assignee_data = null;
            foreach ($all_users as $user) {
                if ($user['id'] == $f_assignee_id) {
                    $selected_assignee_data = $user;
                    break;
                }
            }

            if (!$selected_assignee_data) {
                if (isManager()) {
                    $error_msg_create = "You can only assign tasks to users under your management.";
                } else {
                    $error_msg_create = "Invalid assignee selected for creation.";
                }
            } else {
                // Additional validation for managers - check if assignee is in allowed list
                if (isManager()) {
                    // The assignee is already validated by being in the $all_users array
                    // which only contains the manager and their direct reports
                    // No additional validation needed since we already filtered the list
                }
                // Get holidays for date adjustment
                $holidays_list_dates = getHolidays($conn);
                
                // Generate subtasks with proper frequency-based intervals
                $subtasks_generated = 0;
                $skipped = [];
                $adjusted = [];
                
                // Determine the correct interval based on frequency
                $interval = 'P1D'; // Default to daily
                switch(strtolower($f_frequency)) {
                    case 'daily':
                        $interval = 'P1D';        // Every 1 day
                        break;
                    case 'weekly':
                        $interval = 'P7D';        // Every 7 days
                        break;
                    case 'fortnightly':
                        $interval = 'P14D';       // Every 14 days
                        break;
                    case 'monthly':
                        $interval = 'P1M';        // Every 1 month
                        break;
                    case 'quarterly':
                        $interval = 'P3M';        // Every 3 months
                        break;
                    case 'yearly':
                        $interval = 'P1Y';        // Every 1 year
                        break;
                    default:
                        $interval = 'P1D';        // Default to daily
                        break;
                }
                
                mysqli_begin_transaction($conn);
                
                try {
                    $current_date = clone $start_dt_obj_create;
                    while ($current_date <= $end_dt_obj_create) {
                        $date_str = $current_date->format('Y-m-d');
                        
                        // Skip holidays
                        if (in_array($date_str, $holidays_list_dates)) {
                            $skipped[] = $date_str;
                            $current_date->add(new DateInterval($interval));
                            continue;
                        }
                        
                        // Skip Sundays (day of week: 0 = Sunday)
                        $day_of_week = (int)$current_date->format('w');
                        if ($day_of_week == 0) { // 0 = Sunday
                            $skipped[] = $date_str . ' (Sunday)';
                            $current_date->add(new DateInterval($interval));
                            continue;
                        }
                        
                        // Generate task code
                        $task_code = 'CHK-' . strtoupper(substr(md5($f_task_description . $date_str . $f_assignee_id), 0, 8));
                        
                        // Insert subtask
                        $insert_sql = "INSERT INTO checklist_subtasks (task_code, assignee, department, task_description, frequency, task_date, duration, status, assigned_by, doer_id, doer_name) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $insert_sql);
                        
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "ssssssssis", 
                                $task_code,
                                $selected_assignee_data['username'],
                                $selected_assignee_data['name'],
                                $f_task_description,
                                $f_frequency,
                                $date_str,
                                $f_duration,
                                $f_assigned_by,
                                $f_assignee_id,
                                $selected_assignee_data['username']
                            );
                            
                            if (mysqli_stmt_execute($stmt)) {
                                $subtasks_generated++;
                            }
                            mysqli_stmt_close($stmt);
                        }
                        
                        // Use the correct interval based on frequency
                        $current_date->add(new DateInterval($interval));
                    }
                    
                    if ($subtasks_generated > 0) {
                        mysqli_commit($conn);
                        $success_msg_create = "Successfully created $subtasks_generated checklist subtasks.";
                        
                        // Store success message in session for display after redirect
                        $_SESSION['success_msg_create'] = $success_msg_create;
                        
                        // Set session message for skipped dates
                        if (!empty($skipped) || !empty($adjusted)) {
                            $message_parts = [];
                            if (!empty($skipped)) {
                                $message_parts[] = "Skipped: " . implode(", ", $skipped);
                            }
                            if (!empty($adjusted)) {
                                $message_parts[] = "Adjusted: " . implode(", ", $adjusted);
                            }
                            
                            $_SESSION['skipped_dates_msg_create'] = implode(". ", $message_parts) . ".";
                        }
                        
                        // Redirect to prevent form resubmission on refresh
                        header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
                        exit;
                    } else {
                        $error_msg_create = "No subtasks generated. Check dates and holidays.";
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_msg_create = "Error creating subtasks: " . $e->getMessage();
                }
            }
        }
    }
}

// Check if the user is logged in - MUST BE BEFORE ANY OUTPUT
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// --- Access Control: Admin and Manager Only ---
// MUST BE BEFORE ANY OUTPUT (before header.php include)
if(!isAdmin() && !isManager()) {
    // Redirect non-admins/non-managers to their respective dashboards or a default page
    if (isDoer()) {
        header("location: doer_dashboard.php");
    } else {
        header("location: ../index.php"); // Fallback for other roles or if role not set
    }
    exit;
}
// --- End Access Control ---

// Now that any redirects are done, include the header and helpers (outputs HTML)
$page_title = "Checklist Tasks";
require_once "../includes/header.php";
require_once "../includes/sorting_helpers.php";
require_once "../includes/status_column_helpers.php";

// Helper function to get current manager's name
function getCurrentManagerName($conn) {
    $current_manager_name = $_SESSION["name"] ?? $_SESSION["username"];
    
    // If we only have username, try to get the full name from database
    if ($current_manager_name === $_SESSION["username"]) {
        $name_sql = "SELECT name FROM users WHERE id = ?";
        $name_stmt = mysqli_prepare($conn, $name_sql);
        if ($name_stmt) {
            mysqli_stmt_bind_param($name_stmt, "i", $_SESSION["id"]);
            if (mysqli_stmt_execute($name_stmt)) {
                $name_result = mysqli_stmt_get_result($name_stmt);
                if ($name_row = mysqli_fetch_assoc($name_result)) {
                    $current_manager_name = $name_row['name'];
                }
            }
            mysqli_stmt_close($name_stmt);
        }
    }
    
    return $current_manager_name;
}

// Helper function to get allowed assignees for current user
function getAllowedAssignees($conn) {
    $allowed_assignees = [];
    
    if (isManager() && !isAdmin()) {
        $current_manager_name = getCurrentManagerName($conn);
        
        // Add current manager's username
        $manager_username_sql = "SELECT username FROM users WHERE id = ?";
        $manager_username_stmt = mysqli_prepare($conn, $manager_username_sql);
        if ($manager_username_stmt) {
            mysqli_stmt_bind_param($manager_username_stmt, "i", $_SESSION["id"]);
            if (mysqli_stmt_execute($manager_username_stmt)) {
                $manager_username_result = mysqli_stmt_get_result($manager_username_stmt);
                if ($manager_username_row = mysqli_fetch_assoc($manager_username_result)) {
                    $allowed_assignees[] = $manager_username_row['username'];
                }
            }
            mysqli_stmt_close($manager_username_stmt);
        }
        
        // Add subordinates' usernames
        $subordinates_sql = "SELECT username FROM users WHERE manager = ? AND user_type = 'doer'";
        $subordinates_stmt = mysqli_prepare($conn, $subordinates_sql);
        if ($subordinates_stmt) {
            mysqli_stmt_bind_param($subordinates_stmt, "s", $current_manager_name);
            if (mysqli_stmt_execute($subordinates_stmt)) {
                $subordinates_result = mysqli_stmt_get_result($subordinates_stmt);
                while ($subordinate_row = mysqli_fetch_assoc($subordinates_result)) {
                    $allowed_assignees[] = $subordinate_row['username'];
                }
            }
            mysqli_stmt_close($subordinates_stmt);
        }
    } elseif (isDoer() && !isAdmin() && !isManager()) {
        // For Doer users, only themselves
        $doer_username = $_SESSION["username"] ?? '';
        if (!empty($doer_username)) {
            $allowed_assignees[] = $doer_username;
        }
    }
    
    return $allowed_assignees;
}


// ---- START: Programmatic table creation for checklist_subtasks ----
if (isset($conn)) {
    $checklistSubtasksTableName = 'checklist_subtasks';
    // Ensure table name is properly escaped if used directly in SHOW TABLES, though LIKE pattern is generally safe.
    $checkTableSql_cs = "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $checklistSubtasksTableName) . "'";
    $tableResult_cs = mysqli_query($conn, $checkTableSql_cs);

    if ($tableResult_cs && mysqli_num_rows($tableResult_cs) == 0) {
        $createTableSql_cs = "
        CREATE TABLE `checklist_subtasks` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `task_code` VARCHAR(20) DEFAULT NULL,
          `assignee` VARCHAR(100) DEFAULT NULL,
          `department` VARCHAR(100) DEFAULT NULL,
          `task_description` TEXT,
          `task_date` DATE DEFAULT NULL,
          `duration` TIME DEFAULT NULL,
          `status` VARCHAR(50) DEFAULT 'pending',
          `actual_date` DATE DEFAULT NULL,
          `actual_time` TIME DEFAULT NULL,
          `is_delayed` TINYINT(1) DEFAULT 0,
          `delay_duration` VARCHAR(50) DEFAULT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        if (!mysqli_query($conn, $createTableSql_cs)) {
            // This error will be displayed by the toast mechanism if $error_msg is utilized by it later.
            $error_msg = "CRITICAL: Failed to create table `$checklistSubtasksTableName`: " . htmlspecialchars(mysqli_error($conn));
        }
    } elseif (!$tableResult_cs) {
        $error_msg = "CRITICAL: Failed to check for table `$checklistSubtasksTableName`: " . htmlspecialchars(mysqli_error($conn));
    }
} else {
    $error_msg = "CRITICAL: Database connection not available for table check.";
}

// ---- START: Ensure new columns exist in checklist_subtasks ----
if (isset($conn) && $tableResult_cs && mysqli_num_rows($tableResult_cs) == 1) { // Check if table exists
    $columns_to_add = [
        ["name" => "actual_date", "type" => "DATE DEFAULT NULL"],
        ["name" => "actual_time", "type" => "TIME DEFAULT NULL"],
        ["name" => "is_delayed", "type" => "TINYINT(1) DEFAULT 0"],
        ["name" => "delay_duration", "type" => "VARCHAR(50) DEFAULT NULL"],
        ["name" => "frequency", "type" => "VARCHAR(20) DEFAULT NULL"],
        ["name" => "assigned_by", "type" => "VARCHAR(100) DEFAULT NULL"]
    ];

    foreach ($columns_to_add as $column) {
        $check_column_sql = "SHOW COLUMNS FROM `checklist_subtasks` LIKE '" . $column["name"] . "'";
        $result_check_column = mysqli_query($conn, $check_column_sql);
        if ($result_check_column && mysqli_num_rows($result_check_column) == 0) {
            $alter_sql = "ALTER TABLE `checklist_subtasks` ADD COLUMN `" . $column["name"] . "` " . $column["type"];
            if (!mysqli_query($conn, $alter_sql)) {
                // Accumulate errors, or handle as critical
                $error_msg = ($error_msg ?? "") . " Error adding column " . $column["name"] . ": " . htmlspecialchars(mysqli_error($conn));
            }
        }
    }
}
// ---- END: Ensure new columns exist in checklist_subtasks ----

// ---- END: Programmatic table creation for checklist_subtasks ----

$can_create_tasks = true; // This page is now Admin-only, so creation is allowed.

// --- START: Sorting Parameters ---
// Support both old format (sort_column/sort_order) and new format (sort/dir) for backward compatibility
// Default: DESC by last created task (created_at DESC)
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : (isset($_GET['sort_column']) ? $_GET['sort_column'] : 'created_at');
$sort_direction = isset($_GET['dir']) ? $_GET['dir'] : (isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc');

// Validate sort parameters to prevent SQL injection
$allowed_columns = ['task_code', 'task_description', 'task_date', 'actual_date', 'status', 'delay_duration', 'duration', 'assignee', 'assigned_by', 'created_at'];
$sort_column = validateSortColumn($sort_column, $allowed_columns, 'created_at');
$sort_direction = validateSortDirection($sort_direction);

// For backward compatibility with existing code that uses $sort_order
$sort_order = $sort_direction;
// --- END: Sorting Parameters ---

// Get users based on user role - Consolidated logic
$all_users = [];

if (isAdmin()) {
    // Admin can see all users
    $sql = "SELECT u.id, u.username, u.name, u.email, u.user_type, u.manager, u.manager_id, d.name AS department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.Status = 'Active'
            ORDER BY u.username ASC";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $all_users[] = $row;
        }
    }
} elseif (isManager() && !isAdmin()) {
    // Manager can see themselves and their direct reports
    $current_user_id = $_SESSION['id'];
    
    if (!isset($current_user_id)) {
        $error_msg_create = "User session not found. Please login again.";
        $all_users = [];
    } else {
        // Get manager's own record
        $manager_sql = "SELECT u.id, u.username, u.name, u.email, u.user_type, u.manager, u.manager_id, d.name AS department_name 
                        FROM users u 
                        LEFT JOIN departments d ON u.department_id = d.id 
                        WHERE u.id = ? AND u.Status = 'Active'";
        $manager_stmt = mysqli_prepare($conn, $manager_sql);
        if ($manager_stmt) {
            mysqli_stmt_bind_param($manager_stmt, "i", $current_user_id);
            if (mysqli_stmt_execute($manager_stmt)) {
                $manager_result = mysqli_stmt_get_result($manager_stmt);
                if ($manager_row = mysqli_fetch_assoc($manager_result)) {
                    $all_users[] = $manager_row;
                }
            }
            mysqli_stmt_close($manager_stmt);
        }
        
        // Get manager's name for filtering direct reports
        $manager_name_sql = "SELECT name FROM users WHERE id = ?";
        $manager_name_stmt = mysqli_prepare($conn, $manager_name_sql);
        $manager_name = '';
        if ($manager_name_stmt) {
            mysqli_stmt_bind_param($manager_name_stmt, "i", $current_user_id);
            if (mysqli_stmt_execute($manager_name_stmt)) {
                $manager_name_result = mysqli_stmt_get_result($manager_name_stmt);
                if ($manager_name_row = mysqli_fetch_assoc($manager_name_result)) {
                    $manager_name = $manager_name_row['name'];
                }
            }
            mysqli_stmt_close($manager_name_stmt);
        }
        
        // Get direct reports using manager name (since manager_id is not populated)
        if (!empty($manager_name)) {
            $sql = "SELECT u.id, u.username, u.name, u.email, u.user_type, u.manager, u.manager_id, d.name AS department_name 
                    FROM users u 
                    LEFT JOIN departments d ON u.department_id = d.id 
                    WHERE u.manager = ? AND u.user_type = 'doer' AND u.Status = 'Active'
                    ORDER BY u.username ASC";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $manager_name);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    while ($row = mysqli_fetch_assoc($result)) {
                        $all_users[] = $row;
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
} else {
    // For other users, show empty array (should not reach here due to access control)
    $all_users = [];
}

// Debug: Check if user query is working properly
if (empty($all_users)) {
    error_log("DEBUG: all_users array is empty in checklist_task.php");
    // Fallback: Get all users if no role-based filtering works
    $fallback_sql = "SELECT u.id, u.username, u.name, u.email, u.user_type, u.manager, d.name AS department_name 
                     FROM users u 
                     LEFT JOIN departments d ON u.department_id = d.id 
                     WHERE u.Status = 'Active'
                     ORDER BY u.username ASC";
    $fallback_result = mysqli_query($conn, $fallback_sql);
    if ($fallback_result) {
        while ($row = mysqli_fetch_assoc($fallback_result)) {
            $all_users[] = $row;
        }
    }
}

$holidays_list_dates = getHolidays($conn); // Fetches holiday dates as ['YYYY-MM-DD', ...]

$f_assignee_id = "";
$f_assigned_by = "";
$f_start_date_create = "";
$f_end_date_create = "";
$f_frequency = "";
$f_duration = "";
$f_task_description = "";

// Initialize manager display value
$f_assigned_by_display = "";

// Set assigned_by to current manager's name
if (isManager() && !isAdmin()) {
    $current_user_id = $_SESSION['id'];
    $manager_name_sql = "SELECT name FROM users WHERE id = ?";
    $manager_name_stmt = mysqli_prepare($conn, $manager_name_sql);
    if ($manager_name_stmt) {
        mysqli_stmt_bind_param($manager_name_stmt, "i", $current_user_id);
        if (mysqli_stmt_execute($manager_name_stmt)) {
            $manager_name_result = mysqli_stmt_get_result($manager_name_stmt);
            if ($manager_name_row = mysqli_fetch_assoc($manager_name_result)) {
                $f_assigned_by = $manager_name_row['name'];
                $f_assigned_by_display = $manager_name_row['name'];
            }
        }
        mysqli_stmt_close($manager_name_stmt);
    }
} elseif (isAdmin()) {
    $f_assigned_by = 'Admin';
    $f_assigned_by_display = 'Admin';
}

$error_msg_create = $error_msg ?? "";
$success_msg_create = "";
$skipped_dates_msg_create = "";

// Old form processing removed - now handled at top of file

// Check for session messages from redirect
$success_msg_create = "";
$skipped_dates_msg_create = "";
if (isset($_SESSION['success_msg_create'])) {
    $success_msg_create = $_SESSION['success_msg_create'];
    unset($_SESSION['success_msg_create']);
}
if (isset($_SESSION['skipped_dates_msg_create'])) {
    $skipped_dates_msg_create = $_SESSION['skipped_dates_msg_create'];
    unset($_SESSION['skipped_dates_msg_create']);
}

// --- START: Filtering & Pagination Logic ---

// Helper function to build query string for pagination links (same as manage_tasks.php)
function buildChecklistQuery($params = [], $exclude = []) {
    $query = $_GET;
    foreach ($exclude as $key) {
        unset($query[$key]);
    }
    $query = array_merge($query, $params);
    return http_build_query($query);
}

$filter_assignee = isset($_GET['filter_assignee']) ? trim($_GET['filter_assignee']) : '';
$filter_department = isset($_GET['filter_department']) ? trim($_GET['filter_department']) : '';
$filter_date_from = isset($_GET['filter_date_from']) ? trim($_GET['filter_date_from']) : '';
$filter_date_to = isset($_GET['filter_date_to']) ? trim($_GET['filter_date_to']) : '';
$filter_task_name_checklist = isset($_GET['filter_task_name']) ? trim($_GET['filter_task_name']) : '';
$filter_task_id = isset($_GET['filter_task_id']) ? trim($_GET['filter_task_id']) : '';
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';

// Get doer_status filter (default to 'Active', only for Admin/Manager)
$doer_status = 'Active'; // Default value
if (isAdmin() || isManager()) {
    $doer_status = isset($_GET['doer_status']) ? $_GET['doer_status'] : 'Active';
    // Validate doer_status value
    if ($doer_status !== 'Active' && $doer_status !== 'Inactive') {
        $doer_status = 'Active';
    }
}

$items_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

$where_clauses = [];
$param_types = "";
$param_values = [];

// Role-based data filtering
if (isManager() && !isAdmin()) {
    // Manager can only see tasks for their direct reports
    $current_user_id = $_SESSION['id'];
    $allowed_assignees = [];
    
    // Get manager's own username
    $manager_username_sql = "SELECT username FROM users WHERE id = ?";
    $manager_username_stmt = mysqli_prepare($conn, $manager_username_sql);
    if ($manager_username_stmt) {
        mysqli_stmt_bind_param($manager_username_stmt, "i", $current_user_id);
        if (mysqli_stmt_execute($manager_username_stmt)) {
            $manager_username_result = mysqli_stmt_get_result($manager_username_stmt);
            if ($manager_username_row = mysqli_fetch_assoc($manager_username_result)) {
                $allowed_assignees[] = $manager_username_row['username'];
            }
        }
        mysqli_stmt_close($manager_username_stmt);
    }
    
    // Get manager's name for filtering direct reports
    $manager_name_sql = "SELECT name FROM users WHERE id = ?";
    $manager_name_stmt = mysqli_prepare($conn, $manager_name_sql);
    $manager_name = '';
    if ($manager_name_stmt) {
        mysqli_stmt_bind_param($manager_name_stmt, "i", $current_user_id);
        if (mysqli_stmt_execute($manager_name_stmt)) {
            $manager_name_result = mysqli_stmt_get_result($manager_name_stmt);
            if ($manager_name_row = mysqli_fetch_assoc($manager_name_result)) {
                $manager_name = $manager_name_row['name'];
            }
        }
        mysqli_stmt_close($manager_name_stmt);
    }
    
    // Get direct reports' usernames using manager name
    if (!empty($manager_name)) {
        $subordinates_sql = "SELECT username FROM users WHERE manager = ? AND user_type = 'doer'";
        $subordinates_stmt = mysqli_prepare($conn, $subordinates_sql);
        if ($subordinates_stmt) {
            mysqli_stmt_bind_param($subordinates_stmt, "s", $manager_name);
            if (mysqli_stmt_execute($subordinates_stmt)) {
                $subordinates_result = mysqli_stmt_get_result($subordinates_stmt);
                while ($subordinate_row = mysqli_fetch_assoc($subordinates_result)) {
                    $allowed_assignees[] = $subordinate_row['username'];
                }
            }
            mysqli_stmt_close($subordinates_stmt);
        }
    }
    
    // Add role-based filtering
    if (!empty($allowed_assignees)) {
        $placeholders = str_repeat('?,', count($allowed_assignees) - 1) . '?';
        $where_clauses[] = "cs.assignee IN ($placeholders)";
        $param_types .= str_repeat('s', count($allowed_assignees));
        $param_values = array_merge($param_values, $allowed_assignees);
    } else {
        // If no allowed assignees, show no results
        $where_clauses[] = "1 = 0";
    }
}

if (!empty($filter_assignee)) {
    $where_clauses[] = "cs.assignee LIKE ?";
    $param_types .= "s";
    $param_values[] = "%" . $filter_assignee . "%";
}
if (!empty($filter_department)) {
    $where_clauses[] = "cs.department LIKE ?";
    $param_types .= "s";
    $param_values[] = "%" . $filter_department . "%";
}
if (!empty($filter_date_from)) {
    $where_clauses[] = "cs.task_date >= ?";
    $param_types .= "s";
    $param_values[] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    $where_clauses[] = "cs.task_date <= ?";
    $param_types .= "s";
    $param_values[] = $filter_date_to;
}
if (!empty($filter_task_name_checklist)) {
    $where_clauses[] = "cs.task_description LIKE ?";
    $param_types .= "s";
    $param_values[] = "%" . $filter_task_name_checklist . "%";
}
if (!empty($filter_task_id)) {
    $where_clauses[] = "cs.task_code LIKE ?";
    $param_types .= "s";
    $param_values[] = "%" . $filter_task_id . "%";
}
if (!empty($filter_status)) {
    $where_clauses[] = "cs.status = ?";
    $param_types .= "s";
    $param_values[] = $filter_status;
}
// Frequency filter removed from frontend but kept in backend

// Build base SQL - define both with and without join
$sql_base = "FROM checklist_subtasks cs";
$sql_base_with_join = "FROM checklist_subtasks cs "
                     . "LEFT JOIN users u ON LOWER(TRIM(cs.assignee)) = LOWER(TRIM(u.username)) OR LOWER(TRIM(cs.assignee)) = LOWER(TRIM(u.name))";

$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// Add doer_status filter to count query (only for Admin/Manager)
$count_doer_status_where = "";
$count_doer_status_param_types = "";
$count_doer_status_param_values = [];
if (isAdmin() || isManager()) {
    // For count query, use the join base and filter by doer_status
    $count_doer_status_where = (empty($sql_where) ? " WHERE " : " AND ") . "(u.id IS NOT NULL AND COALESCE(u.Status, 'Active') = ?)";
    $count_doer_status_param_types = "s";
    $count_doer_status_param_values = [$doer_status];
    // Use joined base for count query when Admin/Manager
    $sql_base = $sql_base_with_join;
}

// Get total count for pagination
$sql_count = "SELECT COUNT(*) as total_count " . $sql_base . $sql_where . $count_doer_status_where;
$total_items = 0;
if ($stmt_count = mysqli_prepare($conn, $sql_count)) {
    $count_param_types = $param_types . $count_doer_status_param_types;
    $count_param_values = array_merge($param_values, $count_doer_status_param_values);
    // Bind parameters if we have any
    if (!empty($count_param_types)) {
        mysqli_stmt_bind_param($stmt_count, $count_param_types, ...$count_param_values);
    }
    if (mysqli_stmt_execute($stmt_count)) {
        $result_count = mysqli_stmt_get_result($stmt_count);
        $row_count = mysqli_fetch_assoc($result_count);
        $total_items = $row_count['total_count'] ?? 0;
    }
    mysqli_stmt_close($stmt_count);
}
$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages; // Adjust if current page is out of bounds
$offset = ($current_page - 1) * $items_per_page; // Recalculate offset


// Fetch data for current page
$checklist_subtasks_list = [];
// Use the same joined base that was used for count query (already defined above)

// Add doer_status filter to SQL WHERE clause (only for Admin/Manager)
$doer_status_where = "";
$doer_status_param_types = "";
$doer_status_param_values = [];
if (isAdmin() || isManager()) {
    // Filter by doer_status at SQL level: only include tasks where user exists and status matches
    // This ensures tasks are filtered correctly from the start
    $doer_status_where = (empty($sql_where) ? " WHERE " : " AND ") . "(u.id IS NOT NULL AND COALESCE(u.Status, 'Active') = ?)";
    $doer_status_param_types = "s";
    $doer_status_param_values = [$doer_status];
}

// Use the same base as count query for consistency
$data_sql_base = (isAdmin() || isManager()) ? $sql_base_with_join : $sql_base;

// Fetch ALL data first (no ORDER BY, LIMIT, OFFSET) - sorting will be done in PHP
$sql_select_subtasks = "SELECT cs.id, cs.task_code, cs.task_date, cs.assignee, cs.department, cs.duration, cs.task_description, cs.frequency, cs.status, "
                     . "cs.actual_date, cs.actual_time, cs.is_delayed, cs.delay_duration, cs.assigned_by, cs.created_at, "
                     . "COALESCE(u.Status, 'Active') as doer_user_status, (u.id IS NOT NULL) as doer_user_exists "
                     . $data_sql_base . $sql_where . $doer_status_where;

if ($stmt_select = mysqli_prepare($conn, $sql_select_subtasks)) {
    $current_param_types = $param_types . $doer_status_param_types;
    $current_param_values = array_merge($param_values, $doer_status_param_values);
    
    if (!empty($current_param_types)){
        mysqli_stmt_bind_param($stmt_select, $current_param_types, ...$current_param_values);
    }

    if (mysqli_stmt_execute($stmt_select)) {
        $result_subtasks = mysqli_stmt_get_result($stmt_select);
        while ($row = mysqli_fetch_assoc($result_subtasks)) {
            $checklist_subtasks_list[] = $row;
        }
    }
    mysqli_stmt_close($stmt_select);
} else {
    if (empty($error_msg_create) && empty($error_msg)) { // Avoid overwriting other errors
         $error_msg = "Error fetching checklist subtasks: " . mysqli_error($conn);
    }
}

// Filter tasks by doer_status (only for Admin/Manager)
// This filters the tasks list to only show tasks assigned to users with the selected status
// Only shows tasks from users that exist in the database and match the selected status
if (isAdmin() || isManager()) {
    $checklist_subtasks_list = array_filter($checklist_subtasks_list, function($task) use ($doer_status) {
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
    $checklist_subtasks_list = array_values($checklist_subtasks_list);
}

// ---- START: Update delay status for fetched pending checklist tasks ----
if (!empty($checklist_subtasks_list)) {
    $update_delay_stmt = null; // Prepare statement outside loop
    foreach ($checklist_subtasks_list as $key => $task_item) {
        if ($task_item['status'] === 'pending') {
            $planned_datetime_str = $task_item['task_date'] . ' 23:59:59';
            $planned_timestamp = strtotime($planned_datetime_str);
            $current_timestamp = time();

            if ($current_timestamp > $planned_timestamp) {
                $delay_seconds = $current_timestamp - $planned_timestamp;
                $calculated_delay_duration_str = formatSecondsToHHMMSS($delay_seconds);

                // Update in database and also the array for immediate display consistency
                if ($task_item['is_delayed'] != 1 || $task_item['delay_duration'] !== $calculated_delay_duration_str) {
                    if ($update_delay_stmt === null) {
                        $sql_update_delay = "UPDATE checklist_subtasks SET is_delayed = 1, delay_duration = ? WHERE id = ?";
                        $update_delay_stmt = mysqli_prepare($conn, $sql_update_delay);
                    }
                    if ($update_delay_stmt) {
                        mysqli_stmt_bind_param($update_delay_stmt, "si", $calculated_delay_duration_str, $task_item['id']);
                        mysqli_stmt_execute($update_delay_stmt);
                        // Update array for current view
                        $checklist_subtasks_list[$key]['is_delayed'] = 1;
                        $checklist_subtasks_list[$key]['delay_duration'] = $calculated_delay_duration_str;
                    }
                }
            } else {
                // If it was marked delayed but is now not (e.g., date changed), reset it.
                if ($task_item['is_delayed'] == 1) {
                     // This case is less likely if planned_date is not editable directly in this table view
                     // but good for robustness if other processes might change task_date.
                    if ($update_delay_stmt === null) { // Ensure statement is prepared if not already
                        $sql_update_delay = "UPDATE checklist_subtasks SET is_delayed = ?, delay_duration = ? WHERE id = ?";
                        $update_delay_stmt = mysqli_prepare($conn, $sql_update_delay); 
                    }
                    if ($update_delay_stmt) {
                        $reset_is_delayed = 0;
                        $reset_delay_duration = NULL;
                        mysqli_stmt_bind_param($update_delay_stmt, "isi", $reset_is_delayed, $reset_delay_duration, $task_item['id']);
                        mysqli_stmt_execute($update_delay_stmt);
                        $checklist_subtasks_list[$key]['is_delayed'] = 0;
                        $checklist_subtasks_list[$key]['delay_duration'] = NULL;
                    }
                }
            }
        }
    }
    if ($update_delay_stmt !== null) {
        mysqli_stmt_close($update_delay_stmt);
    }
}
// ---- END: Update delay status for fetched pending checklist tasks ----

// --- START: Sorting Logic ---
// Custom sorting function (similar to manage_tasks.php)
function customSort($a, $b, $column, $direction) {
    $val_a = $a[$column] ?? '';
    $val_b = $b[$column] ?? '';
    
    // Handle null values
    if (empty($val_a) && empty($val_b)) return 0;
    if (empty($val_a)) return $direction === 'asc' ? 1 : -1;
    if (empty($val_b)) return $direction === 'asc' ? -1 : 1;
    
    // Special handling for different data types
    if ($column === 'task_date') {
        // Convert date to timestamp for accurate sorting
        $val_a = strtotime($val_a);
        $val_b = strtotime($val_b);
        
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
        // Handle multiple formats: HH:MM:SS, "X D HH:MM:SS", "X days Y hrs Z mins", etc.
        $val_a = convertDelayToSeconds($val_a);
        $val_b = convertDelayToSeconds($val_b);
    } elseif ($column === 'status') {
        // Normalize status to lowercase for consistent sorting
        $val_a = strtolower(trim($val_a));
        $val_b = strtolower(trim($val_b));
    } elseif ($column === 'duration') {
        // Convert duration to seconds for proper sorting
        $val_a = convertTimeToSeconds($val_a);
        $val_b = convertTimeToSeconds($val_b);
    } elseif ($column === 'created_at') {
        // Handle created_at timestamp comparison
        $val_a = strtotime($val_a);
        $val_b = strtotime($val_b);
        
        // Handle invalid timestamps (fallback to 0)
        if ($val_a === false) $val_a = 0;
        if ($val_b === false) $val_b = 0;
    }
    
    if ($val_a == $val_b) return 0;
    return ($val_a < $val_b) ? ($direction === 'asc' ? -1 : 1) : ($direction === 'asc' ? 1 : -1);
}

function convertDelayToSeconds($delay_str) {
    if (empty($delay_str) || $delay_str === 'N/A' || $delay_str === 'On Time') {
        return 0;
    }
    
    // First, try to parse HH:MM:SS format (most common in database)
    if (preg_match('/(\d+):(\d+):(\d+)/', $delay_str, $matches)) {
        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];
        $seconds = (int)$matches[3];
        $total_seconds = ($hours * 3600) + ($minutes * 60) + $seconds;
        
        // Check if there's a "X D" prefix (days format: "X D HH:MM:SS")
        if (preg_match('/(\d+)\s+D\s+/', $delay_str, $day_matches)) {
            $days = (int)$day_matches[1];
            $total_seconds += ($days * 24 * 3600);
        }
        
        return $total_seconds;
    }
    
    // Try format: "X D Y h Z m" (display format)
    $total_seconds = 0;
    if (preg_match('/(\d+)\s+D\b/i', $delay_str, $matches)) {
        $total_seconds += (int)$matches[1] * 24 * 3600;
    }
    if (preg_match('/(\d+)\s+h\b/i', $delay_str, $matches)) {
        $total_seconds += (int)$matches[1] * 3600;
    }
    if (preg_match('/(\d+)\s+m\b/i', $delay_str, $matches)) {
        $total_seconds += (int)$matches[1] * 60;
    }
    if ($total_seconds > 0) {
        return $total_seconds;
    }
    
    // Fallback: Parse delay string like "2 days, 3 hours, 15 minutes" or "X days Y hrs Z mins"
    if (preg_match('/(\d+)\s*days?/', $delay_str, $matches)) {
        $total_seconds += (int)$matches[1] * 24 * 3600;
    }
    if (preg_match('/(\d+)\s*hrs?/', $delay_str, $matches)) {
        $total_seconds += (int)$matches[1] * 3600;
    }
    if (preg_match('/(\d+)\s*mins?/', $delay_str, $matches)) {
        $total_seconds += (int)$matches[1] * 60;
    }
    
    return $total_seconds;
}

function convertTimeToSeconds($time) {
    if (empty($time)) {
        return 0;
    }
    
    // Handle HH:MM:SS format (most common)
    if (preg_match('/(\d{1,2}):(\d{2}):(\d{2})/', $time, $matches)) {
        return ((int)$matches[1] * 3600) + ((int)$matches[2] * 60) + (int)$matches[3];
    }
    
    // Handle HH:MM format (without seconds)
    if (preg_match('/(\d{1,2}):(\d{2})/', $time, $matches)) {
        return ((int)$matches[1] * 3600) + ((int)$matches[2] * 60);
    }
    
    // Handle numeric value (already in seconds or minutes - assume seconds if > 3600, else minutes)
    if (is_numeric($time)) {
        $num = (int)$time;
        if ($num > 3600) {
            return $num; // Already in seconds
        } else {
            return $num * 60; // Assume minutes, convert to seconds
        }
    }
    
    return 0;
}

// Sort all tasks using customSort function (similar to manage_tasks.php)
// Debug logging for sorting issues (can be enabled via GET parameter)
$debug_sorting = isset($_GET['debug_sort']) && $_GET['debug_sort'] === '1';
$sort_debug_log = [];

usort($checklist_subtasks_list, function($a, $b) use ($sort_column, $sort_order, $debug_sorting, &$sort_debug_log) {
    $result = customSort($a, $b, $sort_column, $sort_order);
    
    // Debug logging for problematic columns
    if ($debug_sorting && in_array($sort_column, ['delay_duration', 'status', 'duration'])) {
        $val_a = $a[$sort_column] ?? '';
        $val_b = $b[$sort_column] ?? '';
        $sort_debug_log[] = [
            'task_a_id' => $a['id'] ?? 'N/A',
            'task_b_id' => $b['id'] ?? 'N/A',
            'val_a' => $val_a,
            'val_b' => $val_b,
            'result' => $result,
            'column' => $sort_column,
            'direction' => $sort_order
        ];
    }
    
    return $result;
});

// Write debug log to file if debugging is enabled
if ($debug_sorting && !empty($sort_debug_log)) {
    $debug_file = '../logs/checklist_sorting_debug_' . date('Y-m-d_H-i-s') . '.txt';
    $debug_content = "=== CHECKLIST SORTING DEBUG ===\n";
    $debug_content .= "Sort Column: $sort_column\n";
    $debug_content .= "Sort Direction: $sort_order\n";
    $debug_content .= "Total items sorted: " . count($checklist_subtasks_list) . "\n\n";
    $debug_content .= "=== SORTING COMPARISONS (First 20) ===\n";
    foreach (array_slice($sort_debug_log, 0, 20) as $log_entry) {
        $debug_content .= "Task A (ID: {$log_entry['task_a_id']}): '{$log_entry['val_a']}' | ";
        $debug_content .= "Task B (ID: {$log_entry['task_b_id']}): '{$log_entry['val_b']}' | ";
        $debug_content .= "Result: {$log_entry['result']}\n";
    }
    $debug_content .= "\n=== SAMPLE SORTED VALUES (First 10) ===\n";
    foreach (array_slice($checklist_subtasks_list, 0, 10) as $task) {
        $val = $task[$sort_column] ?? '';
        $debug_content .= "ID: {$task['id']} | $sort_column: '$val'\n";
    }
    @file_put_contents($debug_file, $debug_content);
}

// Apply pagination after sorting (similar to manage_tasks.php)
$total_filtered_tasks = count($checklist_subtasks_list);
$total_items = $total_filtered_tasks; // Update total_items to match filtered count
$total_pages = max(1, ceil($total_filtered_tasks / $items_per_page));
// Ensure current page is within valid range
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = 1;
    $offset = 0;
} else {
    // Recalculate offset based on current page
    $offset = ($current_page - 1) * $items_per_page;
}
$checklist_subtasks_list = array_slice($checklist_subtasks_list, $offset, $items_per_page);
// --- END: Sorting Logic ---

// Fetch distinct assignees and departments for filter dropdowns with role-based filtering
$distinct_assignees = [];
$distinct_departments = [];

// Get doers from users table (filtered by doer_status for Admin/Manager)
if (isAdmin() || isManager()) {
    // Query users table directly, filtered by doer_status
    // Only fetch username as per user request
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
                    if (!empty($doer_row['username']) && trim($doer_row['username']) !== '') {
                        $distinct_assignees[] = trim($doer_row['username']);
                    }
                }
                sort($distinct_assignees);
            }
        }
        mysqli_stmt_close($stmt_doers);
    }
    
    // Get departments from checklist_subtasks for the filtered assignees
    if (!empty($distinct_assignees)) {
        $placeholders = str_repeat('?,', count($distinct_assignees) - 1) . '?';
        $dept_sql = "SELECT DISTINCT department FROM checklist_subtasks WHERE assignee IN ($placeholders) AND department IS NOT NULL AND department != '' ORDER BY department ASC";
        $dept_stmt = mysqli_prepare($conn, $dept_sql);
        if ($dept_stmt) {
            mysqli_stmt_bind_param($dept_stmt, str_repeat('s', count($distinct_assignees)), ...$distinct_assignees);
            if (mysqli_stmt_execute($dept_stmt)) {
                $dept_result = mysqli_stmt_get_result($dept_stmt);
                while ($r = mysqli_fetch_assoc($dept_result)) {
                    $distinct_departments[] = $r['department'];
                }
            }
            mysqli_stmt_close($dept_stmt);
        }
    }
} elseif (isAdmin()) {
    // Legacy code for Admin (fallback)
    $result_da = mysqli_query($conn, "SELECT DISTINCT assignee FROM checklist_subtasks WHERE assignee IS NOT NULL AND assignee != '' ORDER BY assignee ASC");
    if($result_da) while($r = mysqli_fetch_assoc($result_da)) $distinct_assignees[] = $r['assignee'];
    
    $result_dd = mysqli_query($conn, "SELECT DISTINCT department FROM checklist_subtasks WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
    if($result_dd) while($r = mysqli_fetch_assoc($result_dd)) $distinct_departments[] = $r['department'];
} elseif (isManager() && !isAdmin()) {
    // Manager can only see assignees and departments for their direct reports
    $current_user_id = $_SESSION['id'];
    $allowed_assignees = [];
    
    // Get manager's own username
    $manager_username_sql = "SELECT username FROM users WHERE id = ?";
    $manager_username_stmt = mysqli_prepare($conn, $manager_username_sql);
    if ($manager_username_stmt) {
        mysqli_stmt_bind_param($manager_username_stmt, "i", $current_user_id);
        if (mysqli_stmt_execute($manager_username_stmt)) {
            $manager_username_result = mysqli_stmt_get_result($manager_username_stmt);
            if ($manager_username_row = mysqli_fetch_assoc($manager_username_result)) {
                $allowed_assignees[] = $manager_username_row['username'];
            }
        }
        mysqli_stmt_close($manager_username_stmt);
    }
    
    // Get manager's name for filtering direct reports
    $manager_name_sql = "SELECT name FROM users WHERE id = ?";
    $manager_name_stmt = mysqli_prepare($conn, $manager_name_sql);
    $manager_name = '';
    if ($manager_name_stmt) {
        mysqli_stmt_bind_param($manager_name_stmt, "i", $current_user_id);
        if (mysqli_stmt_execute($manager_name_stmt)) {
            $manager_name_result = mysqli_stmt_get_result($manager_name_stmt);
            if ($manager_name_row = mysqli_fetch_assoc($manager_name_result)) {
                $manager_name = $manager_name_row['name'];
            }
        }
        mysqli_stmt_close($manager_name_stmt);
    }
    
    // Get direct reports' usernames using manager name
    if (!empty($manager_name)) {
        $subordinates_sql = "SELECT username FROM users WHERE manager = ? AND user_type = 'doer'";
        $subordinates_stmt = mysqli_prepare($conn, $subordinates_sql);
        if ($subordinates_stmt) {
            mysqli_stmt_bind_param($subordinates_stmt, "s", $manager_name);
            if (mysqli_stmt_execute($subordinates_stmt)) {
                $subordinates_result = mysqli_stmt_get_result($subordinates_stmt);
                while ($subordinate_row = mysqli_fetch_assoc($subordinates_result)) {
                    $allowed_assignees[] = $subordinate_row['username'];
                }
            }
            mysqli_stmt_close($subordinates_stmt);
        }
    }
    
    // Get distinct assignees and departments for allowed users only
    if (!empty($allowed_assignees)) {
        $placeholders = str_repeat('?,', count($allowed_assignees) - 1) . '?';
        $assignee_sql = "SELECT DISTINCT assignee FROM checklist_subtasks WHERE assignee IN ($placeholders) AND assignee IS NOT NULL AND assignee != '' ORDER BY assignee ASC";
        $assignee_stmt = mysqli_prepare($conn, $assignee_sql);
        if ($assignee_stmt) {
            mysqli_stmt_bind_param($assignee_stmt, str_repeat('s', count($allowed_assignees)), ...$allowed_assignees);
            if (mysqli_stmt_execute($assignee_stmt)) {
                $assignee_result = mysqli_stmt_get_result($assignee_stmt);
                while ($r = mysqli_fetch_assoc($assignee_result)) {
                    $distinct_assignees[] = $r['assignee'];
                }
            }
            mysqli_stmt_close($assignee_stmt);
        }
        
        $dept_sql = "SELECT DISTINCT department FROM checklist_subtasks WHERE assignee IN ($placeholders) AND department IS NOT NULL AND department != '' ORDER BY department ASC";
        $dept_stmt = mysqli_prepare($conn, $dept_sql);
        if ($dept_stmt) {
            mysqli_stmt_bind_param($dept_stmt, str_repeat('s', count($allowed_assignees)), ...$allowed_assignees);
            if (mysqli_stmt_execute($dept_stmt)) {
                $dept_result = mysqli_stmt_get_result($dept_stmt);
                while ($r = mysqli_fetch_assoc($dept_result)) {
                    $distinct_departments[] = $r['department'];
                }
            }
            mysqli_stmt_close($dept_stmt);
        }
    }
}

// --- END: Filtering & Pagination Logic ---

$duration_options = [
    '00:00:00', '00:15:00', '00:20:00', '00:25:00', '00:30:00', '00:45:00', '01:00:00', '01:30:00',
    '02:00:00', '02:30:00', '03:00:00', '03:30:00', '04:00:00', '04:30:00', '05:00:00',
    '05:30:00', '06:00:00', '06:30:00', '07:00:00', '07:30:00', '08:00:00', '12:00:00', '24:00:00'
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checklist Tasks - Task Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="../assets/css/table-sorter.css">
    <style>
        /* Dark Theme Styling for Checklist Tasks Page */
        .checklist-tasks-page {
            background: transparent;
            color: var(--dark-text-primary);
            min-height: 100vh;
        }

        .checklist-tasks-page .content-area {
            background: transparent;
            padding: 5px;
        }

        .checklist-tasks-page .container-fluid {
            background: transparent;
        }

        .checklist-tasks-page h2 {
            color: var(--dark-text-primary);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        /* Card Styling */
        .checklist-tasks-page .card {
            background: var(--dark-bg-glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(10px);
            transition: var(--transition-normal);
        }

        .checklist-tasks-page .card:hover {
            box-shadow: var(--glass-shadow-hover);
            transform: translateY(-2px);
        }

        .checklist-tasks-page .card-header {
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d, #1a1a1a);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #e0e0e0;
            font-weight: 600;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            padding: 0.75rem 1rem;
        }
        
        .checklist-tasks-page .card-header h5 {
            font-size: 1.25rem;
            margin: 0;
        }

        .checklist-tasks-page .card-body {
            background: var(--dark-bg-glass);
            color: var(--dark-text-primary);
            padding: 0.75rem 0.75rem;
        }
        
        .checklist-tasks-page .form-row {
            margin-bottom: 0.4rem;
        }
        
        .checklist-tasks-page .form-group {
            margin-bottom: 0.4rem;
        }

        /* Form Styling */
        .checklist-tasks-page .form-group label {
            color: var(--dark-text-secondary);
            font-weight: 500;
            margin-bottom: 0.25rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            font-size: 0.875rem;
        }

        .checklist-tasks-page .form-control {
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: var(--radius-md);
            color: #e0e0e0 !important;
            transition: var(--transition-normal);
            padding: 0.375rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .checklist-tasks-page .form-control:focus {
            background: linear-gradient(135deg, #2a2a2a, #333333) !important;
            border-color: var(--brand-primary) !important;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25) !important;
            color: white !important;
        }

        .checklist-tasks-page .form-control::placeholder {
            color: #888888;
            opacity: 0.8;
        }

        .checklist-tasks-page select.form-control {
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e") !important;
            background-position: right 0.5rem center !important;
            background-repeat: no-repeat !important;
            background-size: 1rem !important;
            appearance: none !important;
            color: #e0e0e0 !important;
        }
        
        .checklist-tasks-page select.form-control option {
            background: #1e1e1e !important;
            color: #e0e0e0 !important;
        }

        .checklist-tasks-page textarea.form-control {
            resize: vertical;
            min-height: 60px;
            padding: 0.375rem 0.5rem;
        }
        
        .checklist-tasks-page input[type="date"].form-control {
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a) !important;
            color: #e0e0e0 !important;
        }
        
        .checklist-tasks-page input[type="date"].form-control:focus {
            background: linear-gradient(135deg, #2a2a2a, #333333) !important;
            color: white !important;
        }

        /* Button Styling */
        .checklist-tasks-page .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-color-dark));
            border: none;
            border-radius: var(--radius-md);
            color: white;
            font-weight: 500;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            transition: var(--transition-normal);
        }

        .checklist-tasks-page .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-color-dark), var(--primary-color));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }

        .checklist-tasks-page .btn-secondary {
            background: var(--dark-bg-secondary);
            border: 1px solid var(--glass-border);
            color: var(--dark-text-primary);
            border-radius: var(--radius-md);
            transition: var(--transition-normal);
        }

        .checklist-tasks-page .btn-secondary:hover {
            background: var(--dark-bg-hover);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Filter Section Styling */
        .checklist-tasks-page .checklist-filter-header {
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d, #1a1a1a);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .checklist-tasks-page .filter-toggle-btn {
            background: linear-gradient(135deg, #333333, #444444);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #e0e0e0;
            border-radius: var(--radius-md);
            transition: var(--transition-normal);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .checklist-tasks-page .filter-toggle-btn:hover {
            background: linear-gradient(135deg, #444444, #555555);
            border-color: rgba(255, 255, 255, 0.25);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .checklist-tasks-page .filter-form {
            background: linear-gradient(135deg, #0f0f0f, #1a1a1a, #0f0f0f);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-top: none;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
            padding: 1.5rem;
        }

        .checklist-tasks-page .filter-form .form-control {
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #e0e0e0;
            border-radius: var(--radius-sm);
            transition: var(--transition-normal);
        }

        .checklist-tasks-page .filter-form .form-control:focus {
            background: linear-gradient(135deg, #2a2a2a, #333333);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            color: white;
        }

        /* Fix text alignment and padding in filter dropdowns */
        .checklist-tasks-page .filter-form select,
        .checklist-tasks-page .filter-section select {
            background-color: #1e1e1e !important;
            color: #fff !important;
            border: 1px solid #444 !important;
            height: 40px !important;
            line-height: 40px !important;
            padding: 0 35px 0 12px !important;
            border-radius: 6px !important;
            min-height: 40px !important;
            vertical-align: middle !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e") !important;
            background-position: right 12px center !important;
            background-repeat: no-repeat !important;
            background-size: 1rem !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
        }

        .checklist-tasks-page .filter-form select.form-control-sm,
        .checklist-tasks-page .filter-section select.form-control-sm {
            height: 40px !important;
            line-height: 40px !important;
            padding: 0 35px 0 12px !important;
            min-height: 40px !important;
        }

        /* Adjust text rendering for select placeholder and options */
        .checklist-tasks-page .filter-form select option,
        .checklist-tasks-page .filter-section select option {
            color: #fff !important;
            background-color: #2a2a2a !important;
            padding: 8px 12px !important;
            line-height: 1.5 !important;
        }

        .checklist-tasks-page .filter-form select:focus,
        .checklist-tasks-page .filter-section select:focus {
            color: #fff !important;
            background-color: #2a2a2a !important;
        }

        /* Fix placeholder cut-off for Select2 (if used) */
        .checklist-tasks-page .filter-form .select2-selection__rendered,
        .checklist-tasks-page .filter-section .select2-selection__rendered {
            line-height: 38px !important;
            color: #fff !important;
        }

        /* Consistent dropdown list appearance */
        .checklist-tasks-page .select2-dropdown {
            background-color: #2a2a2a !important;
            color: #fff !important;
        }

        .checklist-tasks-page .filter-form .form-control::placeholder {
            color: #888888;
            opacity: 0.8;
        }

        .checklist-tasks-page .filter-form label {
            color: #b0b0b0;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .checklist-tasks-page .filter-form .btn-primary,
        .checklist-tasks-page .filter-form .btn-secondary {
            background: linear-gradient(135deg, #00d4aa, #00a085);
            border: 1px solid rgba(0, 212, 170, 0.3);
            color: white;
            border-radius: var(--radius-sm);
            transition: var(--transition-normal);
            box-shadow: 0 0 15px rgba(0, 212, 170, 0.4), 0 2px 4px rgba(0, 0, 0, 0.3);
            padding: 0.5rem 1rem;
            font-weight: 500;
            min-width: 80px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Ensure proper spacing between buttons */
        .checklist-tasks-page .filter-form .d-flex.gap-2 {
            gap: 0.5rem !important;
        }

        .checklist-tasks-page .filter-form .btn-primary:hover,
        .checklist-tasks-page .filter-form .btn-secondary:hover {
            background: linear-gradient(135deg, #00a085, #00d4aa);
            border-color: rgba(0, 212, 170, 0.5);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 0 25px rgba(0, 212, 170, 0.6), 0 4px 8px rgba(0, 0, 0, 0.4);
        }

        .checklist-tasks-page .filter-form .btn-primary:focus,
        .checklist-tasks-page .filter-form .btn-secondary:focus {
            background: linear-gradient(135deg, #00a085, #00d4aa);
            border-color: rgba(0, 212, 170, 0.5);
            color: white;
            box-shadow: 0 0 25px rgba(0, 212, 170, 0.6), 0 0 0 0.2rem rgba(0, 212, 170, 0.25);
        }

        /* Table Styling */
        .checklist-tasks-page .table {
            background: var(--dark-bg-glass);
            color: var(--dark-text-primary);
            border-radius: var(--radius-md);
            overflow: hidden;
            table-layout: fixed;
        }

        .checklist-tasks-page .table thead th {
            background: linear-gradient(135deg, var(--dark-bg-secondary), var(--dark-bg-primary)) !important;
            color: var(--dark-text-primary) !important;
            border: none !important;
            font-weight: 600 !important;
            font-size: 0.75rem !important; /* Reduced from 0.8rem for better readability and spacing */
            padding: 0.75rem 0.6rem !important;
            border-bottom: 2px solid var(--glass-border) !important;
            cursor: default !important;
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: clip !important;
        }

        /* Override any global hover effects on table headers */
        .checklist-tasks-page .table thead th:hover,
        .checklist-tasks-page .table thead th:active,
        .checklist-tasks-page .table thead th:focus,
        .checklist-tasks-page .table thead th.sortable-header:hover,
        .checklist-tasks-page .table thead th.sortable-header:active,
        .checklist-tasks-page .table thead th.sortable-header:focus {
            background: linear-gradient(135deg, var(--dark-bg-secondary), var(--dark-bg-primary)) !important;
            color: var(--dark-text-primary) !important;
            transform: none !important;
            box-shadow: none !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        /* Remove any potential Bootstrap table-hover effects on headers */
        .checklist-tasks-page .table-hover thead th:hover {
            background: linear-gradient(135deg, var(--dark-bg-secondary), var(--dark-bg-primary)) !important;
            color: var(--dark-text-primary) !important;
        }

        .checklist-tasks-page .table tbody td {
            background: var(--dark-bg-glass);
            color: var(--dark-text-primary);
            border-color: var(--glass-border);
            padding: 0.6rem 0.5rem;
            vertical-align: middle;
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .checklist-tasks-page .table tbody tr:hover {
            background: var(--dark-bg-hover);
        }

        .checklist-tasks-page .table tbody tr:hover td {
            background: var(--dark-bg-hover);
        }
        
        /* Delayed task row highlighting */
        .checklist-tasks-page .delayed-task-row {
            background: rgba(220, 53, 69, 0.1) !important;
            border-left: 4px solid #dc3545 !important;
        }
        
        .checklist-tasks-page .delayed-task-row:hover {
            background: rgba(220, 53, 69, 0.15) !important;
        }

        .checklist-tasks-page .delayed-task-row td {
            background: rgba(220, 53, 69, 0.1) !important;
        }

        .checklist-tasks-page .delayed-task-row:hover td {
            background: rgba(220, 53, 69, 0.15) !important;
        }

        /* Badge Styling */
        .checklist-tasks-page .badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            white-space: nowrap;
        }

        .checklist-tasks-page .badge.badge-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #000;
        }

        .checklist-tasks-page .badge.badge-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }

        .checklist-tasks-page .badge.badge-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .checklist-tasks-page .badge.badge-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }

        /* Action Dropdown Styling - Fix text clipping */
        .checklist-tasks-page .task-action-dropdown {
            background-color: #1e1e1e !important;
            border: 1px solid #444 !important;
            color: #fff !important;
            border-radius: 8px !important;
            height: 28px !important;
            line-height: 28px !important;
            padding: 0 28px 0 8px !important;
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            text-align: left !important;
            display: inline-block !important;
            vertical-align: middle !important;
            min-height: 28px !important;
            min-width: 180px !important;
            width: 100% !important;
            max-width: 200px !important;
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: clip !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e") !important;
            background-position: right 8px center !important;
            background-repeat: no-repeat !important;
            background-size: 0.9rem !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
        }

        .checklist-tasks-page .task-action-dropdown:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
            background-color: #2a2a2a !important;
            color: #fff !important;
            outline: none !important;
        }

        .checklist-tasks-page .task-action-dropdown option {
            background-color: #2a2a2a !important;
            color: #fff !important;
            padding: 5px 10px !important;
            line-height: 1.4 !important;
            font-size: 0.75rem !important;
        }

        /* Status button / badge styling in Actions column */
        .checklist-tasks-page .task-status-btn,
        .checklist-tasks-page .actions-column button,
        .checklist-tasks-page .status-badge {
            height: 28px !important;
            line-height: 28px !important;
            padding: 2px 8px !important;
            border-radius: 8px !important;
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            color: #fff !important;
            text-align: center !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            white-space: nowrap !important;
        }

        /* For dropdown-based buttons in the Action column */
        .checklist-tasks-page .actions-column .dropdown-toggle {
            background-color: #3a1f1f !important;
            color: #fff !important;
            border: 1px solid #6a3c3c !important;
            height: 28px !important;
            line-height: 28px !important;
            padding: 0 10px !important;
            border-radius: 8px !important;
            font-size: 0.75rem !important;
            min-width: 180px !important;
            width: 100% !important;
            max-width: 200px !important;
            white-space: nowrap !important;
            overflow: visible !important;
        }

        /* Dropdown menu styling */
        .checklist-tasks-page .dropdown-menu {
            background-color: #1e1e1e !important;
            color: #fff !important;
            border: 1px solid #333 !important;
            border-radius: 6px !important;
        }

        .checklist-tasks-page .dropdown-menu .dropdown-item {
            color: #fff !important;
            padding: 6px 12px !important;
            line-height: 20px !important;
        }

        .checklist-tasks-page .dropdown-menu .dropdown-item:hover {
            background-color: #2e2e2e !important;
            color: #fff !important;
        }

        /* Pagination Styling */
        .checklist-tasks-page .pagination .page-link {
            background: var(--dark-bg-glass);
            border: 1px solid var(--glass-border);
            color: var(--dark-text-primary);
            border-radius: var(--radius-sm);
            margin: 0 0.125rem;
            transition: var(--transition-normal);
        }

        .checklist-tasks-page .pagination .page-link:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .checklist-tasks-page .pagination .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .checklist-tasks-page .pagination .page-item.disabled .page-link {
            background: var(--dark-bg-secondary);
            border-color: var(--glass-border);
            color: var(--dark-text-muted);
        }
        
        /* Delay hover - Browser default tooltip only */
        .checklist-tasks-page .delay-hover {
            cursor: help;
        }
        
        /* Description hover styling */
        .checklist-tasks-page .description-hover {
            cursor: help;
        }

        /* Column Width Management */
        .checklist-tasks-page .table td:nth-child(1) { width: 5%; } /* ID */
        .checklist-tasks-page .table td:nth-child(2) { width: 38%; } /* Description */
        .checklist-tasks-page .table td:nth-child(3) { width: 10%; } /* Planned */
        .checklist-tasks-page .table td:nth-child(4) { width: 10%; } /* Actual */
        .checklist-tasks-page .table td:nth-child(5) { width: 4%; } /* Status */
        .checklist-tasks-page .table td:nth-child(6) { width: 10%; } /* Delayed */
        .checklist-tasks-page .table td:nth-child(7) { width: 10%; } /* Duration */
        .checklist-tasks-page .table td:nth-child(8) { width: 10%; } /* Doer */
        .checklist-tasks-page .table td:nth-child(9) { width: 8%; } /* Assigner */
        .checklist-tasks-page .table td:nth-child(10) { width: 14%; } /* Actions */

        /* Text truncation - ensure text is visible */
        .checklist-tasks-page .table td:nth-child(2),
        .checklist-tasks-page .table td:nth-child(6) {
            white-space: normal;
            overflow: visible;
            word-wrap: break-word;
            word-break: break-word;
        }
        
        /* Ensure all table cells don't cut text */
        .checklist-tasks-page .table td {
            overflow: visible !important;
            word-wrap: break-word;
        }
        
        /* Select2 Dark Theme */
        .checklist-tasks-page .select2-container .select2-selection--single {
            height: calc(1.5em + .5rem + 2px);
            padding: .25rem .5rem;
            font-size: 0.8rem;
            line-height: 1.4;
            background: var(--dark-bg-glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            color: var(--dark-text-primary);
        }

        .checklist-tasks-page .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
            color: var(--dark-text-primary);
        }

        .checklist-tasks-page .select2-container .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + .5rem);
        }

        /* Doer Filter Styling - Dark Theme (matching manage_tasks.php) */
        .checklist-tasks-page .doer-filter-container {
            position: relative;
        }

        /* User Status Toggle Styling - matching manage_tasks.php */
        .checklist-tasks-page .doer-status-toggle {
            width: 100%;
        }

        .checklist-tasks-page .doer-status-toggle .btn-group {
            width: 100%;
            display: flex;
        }

        .checklist-tasks-page .doer-status-toggle .btn-group .btn {
            flex: 1;
            background: var(--dark-bg-glass);
            border: 1px solid var(--glass-border);
            color: var(--dark-text-secondary);
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
            transition: var(--transition-normal);
        }

        .checklist-tasks-page .doer-status-toggle .btn-group .btn:hover {
            background: var(--dark-bg-hover);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .checklist-tasks-page .doer-status-toggle .btn-group .btn.active,
        .checklist-tasks-page .doer-status-toggle .btn-group .btn-primary {
            background: var(--gradient-primary);
            border-color: var(--primary-color);
            color: white;
        }

        .checklist-tasks-page .doer-status-toggle .btn-group .btn input[type="radio"] {
            display: none;
        }

        .checklist-tasks-page .doer-dropdown {
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

        .checklist-tasks-page .doer-dropdown-item {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid var(--glass-border);
            transition: var(--transition-normal);
            color: var(--dark-text-primary);
        }

        .checklist-tasks-page .doer-dropdown-item:hover {
            background: var(--dark-bg-glass-hover);
            color: var(--dark-text-primary);
        }

        .checklist-tasks-page .doer-dropdown-item:last-child {
            border-bottom: none;
        }

        .checklist-tasks-page .doer-dropdown-item.selected {
            background: rgba(99, 102, 241, 0.2);
            color: var(--brand-primary);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .checklist-tasks-page .table-responsive {
                border-radius: var(--radius-md);
            }
            
            .checklist-tasks-page .form-row .form-group {
                margin-bottom: 1rem;
            }
            
            .checklist-tasks-page .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        /* Loading States */
        .checklist-tasks-page .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .checklist-tasks-page .btn .fa-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Success/Error Messages */
        .checklist-tasks-page .alert {
            border-radius: var(--radius-md);
            border: none;
            backdrop-filter: blur(10px);
        }

        .checklist-tasks-page .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-left: 4px solid #28a745;
        }

        .checklist-tasks-page .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .checklist-tasks-page .alert-info {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
            border-left: 4px solid #17a2b8;
        }

        /* Toast Notifications */
        .toast-message-checklist {
            border-radius: var(--radius-md);
            border: none;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        /* Form Validation States */
        .checklist-tasks-page .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .checklist-tasks-page .form-control.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        /* Loading States */
        .checklist-tasks-page .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .checklist-tasks-page .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Enhanced Hover Effects */
        .checklist-tasks-page .table tbody tr {
            transition: all 0.2s ease;
        }

        .checklist-tasks-page .table tbody tr:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Improved Focus States */
        .checklist-tasks-page .form-control:focus,
        .checklist-tasks-page .btn:focus {
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* Better Spacing */
        .checklist-tasks-page .card-body {
            padding: 1.5rem;
        }

        .checklist-tasks-page .form-group {
            margin-bottom: 1rem;
        }

        /* Icon Styling */
        .checklist-tasks-page .fas,
        .checklist-tasks-page .far {
            margin-right: 0.5rem;
        }
    </style>
    
    <!-- Custom CSS for improved datepicker styling - Fixed spacing -->
    <style>
        /* Calendar Picker Styling - Fixed Spacing */
        .datepicker {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding: 0;
            width: 240px;
        }
        
        /* Header with month/year and navigation */
        .datepicker .datepicker-switch {
            font-size: 16px;
            font-weight: bold;
            color: #111;
            text-align: center;
            padding: 12px 0;
            background: white;
            border-bottom: 1px solid #f0f0f0;
        }
        
        /* Navigation arrows */
        .datepicker .datepicker-days th.prev,
        .datepicker .datepicker-days th.next {
            background: white;
            border: none;
            cursor: pointer;
            padding: 12px 8px;
            font-size: 14px;
            color: #666;
            transition: color 0.2s ease;
        }
        
        .datepicker .datepicker-days th.prev:hover,
        .datepicker .datepicker-days th.next:hover {
            color: #111;
        }
        
        /* Weekday headers - Equal spacing - FIXED */
        .datepicker .dow {
            background: white;
            color: #111;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            padding: 8px 0;
            border: none;
            width: 14.2857% !important; /* Equal width for 7 days */
            vertical-align: middle;
        }
        
        /* Calendar grid */
        .datepicker table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .datepicker table tr td,
        .datepicker table tr th {
            border: none;
            padding: 0;
            margin: 0;
        }
        
        /* Date cells */
        .datepicker .day {
            width: 32px;
            height: 32px;
            line-height: 32px;
            text-align: center;
            cursor: pointer;
            font-size: 13px;
            color: #111;
            background: white;
            border-radius: 4px;
            margin: 1px;
            transition: all 0.2s ease;
            font-weight: normal;
        }
        
        .datepicker .day:hover {
            background-color: #f0f0f0;
            color: #111;
        }
        
        .datepicker .day.active {
            background-color: #ff9500 !important;
            color: white !important;
            font-weight: bold;
        }
        
        .datepicker .day.today {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        
        /* Previous/Next month dates */
        .datepicker .day.old,
        .datepicker .day.new {
            color: #ccc;
            background: white;
        }
        
        .datepicker .day.old:hover,
        .datepicker .day.new:hover {
            background-color: #f8f8f8;
            color: #999;
        }
        
        /* Today button */
        .datepicker .datepicker-days th.today {
            background: white;
            border: none;
            padding: 10px 0;
            text-align: center;
        }
        
        .datepicker .datepicker-days th.today button {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 6px 12px;
            font-weight: bold;
            color: #111;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 12px;
        }
        
        .datepicker .datepicker-days th.today button:hover {
            background: #f0f0f0;
            border-color: #999;
        }
        
        /* Input field styling */
        .form-control.datepicker {
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control.datepicker:focus {
            border-color: #007bff;
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
            background: white;
        }
        
        /* Calendar positioning */
        .datepicker-dropdown {
            padding: 0;
            border: none;
            background: white;
        }
        
        /* CRITICAL: Fix spacing issues - Ensure equal column widths */
        .datepicker-days table {
            table-layout: fixed !important;
            width: 100% !important;
        }
        
        .datepicker-days th.dow,
        .datepicker-days td.day {
            width: 14.2857% !important; /* Exactly 1/7 of width */
            text-align: center !important;
            vertical-align: middle !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        /* Remove any extra spacing */
        .datepicker-days td.day {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Ensure proper alignment */
        .datepicker-days th.dow {
            padding: 8px 0 !important;
            margin: 0 !important;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .datepicker {
                width: 220px;
            }
            
            .datepicker .day {
                width: 28px;
                height: 28px;
                line-height: 28px;
                font-size: 12px;
            }
        }
    
        /* Ensure description column has fixed width and single line */
        .table {
            table-layout: fixed;
        }
        
        .table td:nth-child(2) {
            max-width: 150px;
            width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Delay column single line styling */
        .table td:nth-child(6) {
            max-width: 120px;
            width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Delay hover styling */
        .delay-hover {
            cursor: help;
        }
        
        /* Description hover styling */
        .description-hover {
            cursor: help;
        }
        /* Stretch content area to reduce blank space */
        .content-area .container-fluid {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 10px 15px !important;
        }
        
        .content-area .card {
            margin-top: 0.5rem !important;
            margin-bottom: 1rem !important;
        }
        
        .content-area h2 {
            margin-bottom: 0.5rem !important;
            margin-top: 0.5rem !important;
        }
        
        /* Ensure form takes full width */
        .content-area .card-body {
            padding: 1rem !important;
        }
        
        /* Custom filter toggle styles for smooth transitions - matching manage_tasks.php */
        .checklist-filter-content {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .checklist-filter-content.collapsed {
            max-height: 0;
            padding: 0;
            margin: 0;
        }

        /* Create Task Form Toggle Styles */
        .create-task-content {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .create-task-content.collapsed {
            max-height: 0;
            padding: 0;
            margin: 0;
        }
        
        /* Button hover effects */
        #checklistToggleFilters:hover,
        #createTaskToggle:hover {
            background-color: #0d479e !important;
            color: white !important;
            border-color: #0d479e !important;
        }</style>
</head>
<body>
    <!-- Content will be wrapped by header.php -->
    <div class="checklist-tasks-page">
        <div class="content-area">
        <div class="container-fluid">
        <h2>Checklist Tasks</h2>

<?php if ($can_create_tasks): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle"></i> Create Recurring Checklist Task
                </h5>
                <button class="btn btn-sm filter-toggle-btn" type="button" id="createTaskToggle">
                    <i class="fas fa-chevron-down" id="createTaskToggleIcon"></i> Show Form
                </button>
            </div>
            <div class="create-task-content collapsed" id="createTaskContent">
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="checklistTaskForm">
                    <!-- Row 1: Doer, Department, Assigner -->
                    <div class="form-row mb-2">
                    <div class="form-group col-md-4">
                            <label for="assignee_id">
                                <i class="fas fa-user"></i> Doer
                            </label>
                        <select class="form-control" id="assignee_id" name="assignee_id" required>
                            <option value="">Select Doer</option>
                            <?php 
                            // Get current manager's name for assigned_by field
                            $current_manager_name = '';
                            if (isManager() && !isAdmin()) {
                                $current_user_id = $_SESSION['id'];
                                $manager_name_sql = "SELECT name FROM users WHERE id = ?";
                                $manager_name_stmt = mysqli_prepare($conn, $manager_name_sql);
                                if ($manager_name_stmt) {
                                    mysqli_stmt_bind_param($manager_name_stmt, "i", $current_user_id);
                                    if (mysqli_stmt_execute($manager_name_stmt)) {
                                        $manager_name_result = mysqli_stmt_get_result($manager_name_stmt);
                                        if ($manager_name_row = mysqli_fetch_assoc($manager_name_result)) {
                                            $current_manager_name = $manager_name_row['name'];
                                        }
                                    }
                                    mysqli_stmt_close($manager_name_stmt);
                                }
                            } elseif (isAdmin()) {
                                $current_manager_name = 'Admin';
                            }
                            ?>
                            <?php foreach($all_users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>" data-department="<?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?>" data-manager="<?php echo htmlspecialchars($current_manager_name); ?>" <?php echo ($f_assignee_id == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                            <label for="department_display">
                                <i class="fas fa-building"></i> Department
                            </label>
                            <input type="text" class="form-control" id="department_display" name="department_display" readonly placeholder="Auto-filled when doer is selected">
                    </div>
                    <div class="form-group col-md-4">
                            <label for="assigned_by_display">
                                <i class="fas fa-user-tie"></i> Assigner (Manager)
                            </label>
                            <input type="text" class="form-control" id="assigned_by_display" name="assigned_by_display" value="<?php echo htmlspecialchars($f_assigned_by_display); ?>" readonly placeholder="Auto-filled">
                        <input type="hidden" id="assigned_by" name="assigned_by" value="<?php echo htmlspecialchars($f_assigned_by); ?>">
                    </div>
                </div>

                    <!-- Row 2: Start Date, End Date -->
                    <div class="form-row mb-2">
                        <div class="form-group col-md-6">
                            <label for="start_date_create">
                                <i class="fas fa-calendar-alt"></i> Start Date
                            </label>
                            <input type="date" class="form-control" id="start_date_create" name="start_date_create" value="<?php echo htmlspecialchars($f_start_date_create); ?>" min="<?php echo date('Y-m-d'); ?>" required autocomplete="off">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="end_date_create">
                                <i class="fas fa-calendar-check"></i> End Date
                            </label>
                            <input type="date" class="form-control" id="end_date_create" name="end_date_create" value="<?php echo htmlspecialchars($f_end_date_create); ?>" min="<?php echo date('Y-m-d'); ?>" required autocomplete="off">
                        </div>
                    </div>

                    <!-- Row 3: Frequency, Duration -->
                    <div class="form-row mb-2">
                        <div class="form-group col-md-6">
                            <label for="frequency">
                                <i class="fas fa-repeat"></i> Frequency
                            </label>
                            <select class="form-control" id="frequency" name="frequency" required>
                                <option value="">Select Frequency</option>
                                <option value="daily" <?php echo ($f_frequency == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo ($f_frequency == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                <option value="fortnightly" <?php echo ($f_frequency == 'fortnightly') ? 'selected' : ''; ?>>Fortnightly (every 14 days)</option>
                                <option value="monthly" <?php echo ($f_frequency == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                <option value="quarterly" <?php echo ($f_frequency == 'quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                                <option value="yearly" <?php echo ($f_frequency == 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="duration">
                                <i class="fas fa-clock"></i> Duration (HH:MM:SS)
                            </label>
                            <select class="form-control" id="duration" name="duration" required>
                                <option value="">Select Duration</option>
                                <?php foreach($duration_options as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($f_duration == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Row 4: Task Description -->
                    <div class="form-group mb-2">
                        <label for="task_description">
                            <i class="fas fa-tasks"></i> Task Description
                        </label>
                        <textarea class="form-control" id="task_description" name="task_description" rows="2" required placeholder="Enter detailed task description..."><?php echo htmlspecialchars($f_task_description); ?></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-group text-center mt-1">
                        <button type="submit" name="create_checklist_task" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Create Checklist Tasks
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mt-4">
            <div class="card-header checklist-filter-header text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list-alt"></i> Generated Checklist Subtasks
                </h5>
                <button class="btn btn-sm filter-toggle-btn" type="button" id="checklistToggleFilters">
                    <i class="fas fa-chevron-down" id="checklistFilterToggleIcon"></i> Show Filters
                </button>
            </div>
            <div class="checklist-filter-content collapsed" id="checklistFilterContent">
                <div class="card-body">
                    <!-- Filter Form -->
                    <form action="" method="get" class="filter-form">
                        <div class="row g-3">
                            <!-- Row 1 -->
                            <div class="col-md-3">
                                <label for="filter_task_id" class="form-label small text-muted">
                                    <i class="fas fa-hashtag"></i> Task ID
                                </label>
                                <input type="text" class="form-control form-control-sm" id="filter_task_id" name="filter_task_id" value="<?php echo htmlspecialchars($filter_task_id); ?>" placeholder="Search Task ID..." autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <label for="filter_task_name_chk" class="form-label small text-muted">
                                    <i class="fas fa-tasks"></i> Task Name
                                </label>
                                <input type="text" class="form-control form-control-sm" id="filter_task_name_chk" name="filter_task_name" value="<?php echo htmlspecialchars($filter_task_name_checklist); ?>" placeholder="Search Task Name..." autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <label for="filter_assignee" class="form-label small text-muted">
                                    <i class="fas fa-user"></i> Doer
                                </label>
                                <div class="doer-filter-container">
                                    <select class="form-control form-control-sm" id="filter_assignee" name="filter_assignee">
                                        <option value="">Filter by Doer</option>
                                        <?php foreach($distinct_assignees as $assignee_name): ?>
                                        <option value="<?php echo htmlspecialchars($assignee_name); ?>" <?php echo ($filter_assignee == $assignee_name) ? 'selected' : ''; ?>><?php echo htmlspecialchars($assignee_name); ?></option>
                                        <?php endforeach; ?>
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
                            </div>
                        <div class="row g-3 mt-2">
                            <!-- Row 2 -->
                            <div class="col-md-3">
                                <label for="filter_status" class="form-label small text-muted">
                                    <i class="fas fa-info-circle"></i> Status
                                </label>
                                <select class="form-control form-control-sm" id="filter_status" name="filter_status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="not done" <?php echo ($filter_status == 'not done') ? 'selected' : ''; ?>>Not Done</option>
                                    <option value="can not be done" <?php echo ($filter_status == 'can not be done') ? 'selected' : ''; ?>>Can't be done</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_date_from" class="form-label small text-muted">
                                    <i class="fas fa-calendar-alt"></i> Date From
                                </label>
                                <input type="date" class="form-control form-control-sm" id="filter_date_from" name="filter_date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <label for="filter_date_to" class="form-label small text-muted">
                                    <i class="fas fa-calendar-check"></i> Date To
                                </label>
                                <input type="date" class="form-control form-control-sm" id="filter_date_to" name="filter_date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    <a href="checklist_task.php" class="btn btn-secondary">
                                        <i class="fas fa-undo"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

                <?php if(!empty($checklist_subtasks_list)): ?>
                <div class="table-responsive">
                    <table class="table table-hover sortable-table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="<?php echo buildSortUrl('task_code', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                       class="text-white text-decoration-none sortable-header" data-column="task_code">
                                        ID <?php echo getSortIcon('task_code', $sort_column, $sort_direction); ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo buildSortUrl('task_description', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                       class="text-white text-decoration-none sortable-header" data-column="task_description">
                                        Description <?php echo getSortIcon('task_description', $sort_column, $sort_direction); ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo buildSortUrl('task_date', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                       class="text-white text-decoration-none sortable-header" data-column="task_date">
                                        Planned <?php echo getSortIcon('task_date', $sort_column, $sort_direction); ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo buildSortUrl('actual_date', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                       class="text-white text-decoration-none sortable-header" data-column="actual_date">
                                        Actual <?php echo getSortIcon('actual_date', $sort_column, $sort_direction); ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo buildSortUrl('status', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                       class="text-white text-decoration-none sortable-header" data-column="status">
                                        Status <?php echo getSortIcon('status', $sort_column, $sort_direction); ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo buildSortUrl('delay_duration', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                       class="text-white text-decoration-none sortable-header" data-column="delay_duration">
                                        Delayed <?php echo getSortIcon('delay_duration', $sort_column, $sort_direction); ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo buildSortUrl('duration', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                       class="text-white text-decoration-none sortable-header" data-column="duration">
                                        Duration <?php echo getSortIcon('duration', $sort_column, $sort_direction); ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo buildSortUrl('assignee', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                       class="text-white text-decoration-none sortable-header" data-column="assignee">
                                        Doer <?php echo getSortIcon('assignee', $sort_column, $sort_direction); ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo buildSortUrl('assigned_by', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                       class="text-white text-decoration-none sortable-header" data-column="assigned_by">
                                        Assigner <?php echo getSortIcon('assigned_by', $sort_column, $sort_direction); ?>
                                    </a>
                                </th>
                                <th class="no-sort">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checklist_subtasks_list as $subtask): ?>
                                <?php 
                                    // Simplified logic: check if the Delayed column has a value or if planned time is in the past
                                    $delay_output = 'N/A'; // Default
                                    $is_delayed = false;
                                    $current_time = time();
                                    
                                     // DIRECT APPROACH: If the task has a delay value in the Delayed column, mark it as delayed
                                    // But exclude tasks that can't be done (they should always show N/A)
                                    if (!empty($subtask['delay_duration']) && $subtask['delay_duration'] != 'N/A' && $subtask['delay_duration'] != 'On Time' && strtolower($subtask['status']) !== 'cant_be_done') {
                                        $formatted_delay = formatDelayForDisplay($subtask['delay_duration']);
                                        $full_delay = $formatted_delay;
                                        $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                        $delay_output = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                        $is_delayed = true;
                                    } 
                                    // For completed tasks with delay info
                                    elseif ($subtask['status'] === 'completed') {
                                        if ($subtask['is_delayed'] && !empty($subtask['delay_duration'])) {
                                            $formatted_delay = formatDelayForDisplay($subtask['delay_duration']);
                                            $full_delay = $formatted_delay;
                                            $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                            $delay_output = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                            $is_delayed = true;
                                        } else {
                                            $delay_output = '<span class="text-success">On Time</span>';
                                        }
                                    }
                                    // For pending tasks, check if planned time has passed
                                    else {
                                        $planned_ts = null;
                                        if (!empty($subtask['task_date'])) {
                                            $planned_ts = strtotime($subtask['task_date'] . ' 23:59:59');
                                        }
                                        
                                        if ($planned_ts && $current_time > $planned_ts) {
                                            // Calculate current delay
                                            $delay_secs = $current_time - $planned_ts;
                                            $delay_formatted = formatDelayForDisplay(formatSecondsToHHMMSS($delay_secs));
                                            $full_delay = $delay_formatted;
                                            $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                            $delay_output = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                            $is_delayed = true;
                                        }
                                    }
                                    
                                    // ADDITIONAL CHECK: If the Delayed column in the table shows a value, always mark as delayed
                                    if (strpos($delay_output, 'text-danger') !== false) {
                                        $is_delayed = true;
                                    }
                                    
                                    $row_class = $is_delayed ? 'delayed-task-row' : '';
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo htmlspecialchars($subtask['task_code'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        $description = $subtask['task_description'] ?? 'N/A';
                                        $full_description = htmlspecialchars($description);
                                        $truncated_description = strlen($description) > 20 ? substr($description, 0, 20) . '...' : $description;
                                        ?>
                                        <span title="<?php echo htmlspecialchars($full_description); ?>">
                                            <?php echo htmlspecialchars($truncated_description); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(date("d M Y", strtotime($subtask['task_date']))); ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($subtask['actual_date'])) {
                                            $actual_display = date("d M Y", strtotime($subtask['actual_date']));
                                            if (!empty($subtask['actual_time'])) {
                                                $actual_display .= " " . date("h:i A", strtotime($subtask['actual_time']));
                                            }
                                            echo htmlspecialchars($actual_display);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <?php echo get_status_column_cell($subtask['status'] ?? 'pending'); ?>
                                    <td>
                                        <?php
                                        // Use the pre-calculated delay output
                                        echo $delay_output;
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($subtask['duration']); ?></td>
                                    <td><?php echo htmlspecialchars($subtask['assignee']); ?></td>
                                    <td><?php echo htmlspecialchars($subtask['assigned_by'] ?? 'N/A'); ?></td>
                                    <td class="text-center">
                                        <select class="form-control form-control-sm task-action-dropdown" data-task-id="<?php echo htmlspecialchars($subtask['id']); ?>">
                                            <?php if(isAdmin()): ?>
                                            <option value="edit" data-action="edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </option>
                                            <?php endif; ?>
                                            <option value="pending" data-action="status" <?php echo (strtolower($subtask['status'] ?? 'pending') === 'pending') ? 'selected' : ''; ?>>
                                                <i class="fas fa-clock"></i> Pending
                                            </option>
                                            <option value="completed" data-action="status" <?php echo (strtolower($subtask['status'] ?? 'pending') === 'completed') ? 'selected' : ''; ?>>
                                                <i class="fas fa-check"></i> Completed
                                            </option>
                                            <option value="not_done" data-action="status" <?php echo (strtolower($subtask['status'] ?? 'pending') === 'not_done') ? 'selected' : ''; ?>>
                                                <i class="fas fa-times"></i> Not done
                                            </option>
                                            <option value="cant_be_done" data-action="status" <?php echo (strtolower($subtask['status'] ?? 'pending') === 'cant_be_done') ? 'selected' : ''; ?>>
                                                <i class="fas fa-ban"></i> Can't be done
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination pagination-sm justify-content-center">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo buildChecklistQuery(['page' => $current_page - 1]); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo buildChecklistQuery(['page' => $i]); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo buildChecklistQuery(['page' => $current_page + 1]); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

                <!-- Results Info -->
                <div class="text-center text-muted small mt-2">
                    Showing <?php echo count($checklist_subtasks_list); ?> of <?php echo $total_items; ?> tasks
            </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted"></i>
                    </div>
                    <h5 class="text-muted mb-3">No Checklist Subtasks Found</h5>
                    <p class="text-muted">
                        <?php if (!empty($filter_assignee) || !empty($filter_department) || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                            No tasks match your current filter criteria.
                            <br><a href="checklist_task.php" class="btn btn-outline-primary btn-sm mt-2">
                                <i class="fas fa-undo"></i> Clear Filters
                            </a>
                        <?php elseif ($can_create_tasks): ?>
                            No checklist tasks have been created yet.
                            <br><span class="text-muted">Create some using the form above.</span>
                        <?php else: ?>
                            No checklist tasks are available.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Checklist Task Modal -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../assets/js/table-sorter.js"></script>

    <style>
        
        body .modal#editChecklistTaskModal .modal-dialog {
            z-index: 100000 !important;
        }
        
        /* Ensure modal backdrops also have high z-index */
        body .modal-backdrop.show {
            z-index: 99998 !important;
        }
        
        /* Override any Bootstrap modal z-index issues with maximum specificity */
        body .modal.show {
            z-index: 99999 !important;
        }
        
        body .modal.show .modal-dialog {
            z-index: 100000 !important;
        }
        
        /* Force modal visibility and positioning with maximum specificity */
        body .modal#editChecklistTaskModal.modal.fade.show {
            display: block !important;
            z-index: 99999 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* Ensure modal is always visible when shown */
        #editChecklistTaskModal.modal.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* Ensure modal content is visible with maximum specificity */
        body .modal#editChecklistTaskModal .modal-content {
            position: relative !important;
            z-index: 100000 !important;
        }
        
        /* Override Bootstrap's modal z-index completely */
        .modal.fade.show {
            z-index: 99999 !important;
        }
        
        .modal.fade.show .modal-dialog {
            z-index: 100000 !important;
        }
        
        /* Remove any potential overlay issues */
        .modal-backdrop + .modal-backdrop {
            display: none !important;
        }
        
        /* Ensure only one backdrop exists */
        .modal-backdrop:not(:first-child) {
            display: none !important;
        }
        
        /* Force modal to be on top of everything */
        .modal.show {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            z-index: 99999 !important;
            pointer-events: auto !important;
        }
        
        /* Ensure modal dialog is properly centered and clickable */
        .modal.show .modal-dialog {
            position: relative !important;
            margin: 1.75rem auto !important;
            z-index: 100000 !important;
            pointer-events: auto !important;
        }
        
        /* Fix modal backdrop positioning and pointer events */
        .modal-backdrop {
            z-index: 99998 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
        }
        
        /* Ensure modal content is clickable */
        .modal-content {
            position: relative !important;
            z-index: 100000 !important;
            pointer-events: auto !important;
        }
        
        /* Ensure modal dialog is clickable */
        .modal-dialog {
            position: relative !important;
            z-index: 100000 !important;
            pointer-events: auto !important;
        }
        
        /* Ensure modal itself is clickable */
        .modal {
            pointer-events: auto !important;
        }
    
        /* Ensure description column has fixed width and single line */
        .table {
            table-layout: fixed;
        }
        
        .table td:nth-child(2) {
            max-width: 150px;
            width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Delay column single line styling */
        .table td:nth-child(6) {
            max-width: 120px;
            width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Delay hover styling */
        .delay-hover {
            cursor: help;
        }
        
        /* Description hover styling */
        .description-hover {
            cursor: help;
        }
        /* Stretch content area to reduce blank space */
        .content-area .container-fluid {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 10px 15px !important;
        }
        
        .content-area .card {
            margin-top: 0.5rem !important;
            margin-bottom: 1rem !important;
        }
        
        .content-area h2 {
            margin-bottom: 0.5rem !important;
            margin-top: 0.5rem !important;
        }
        
        /* Ensure form takes full width */
        .content-area .card-body {
            padding: 1rem !important;
        }</style>

    <script>
        function showChecklistToast(message, type = 'success') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container-checklist');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container-checklist';
                toastContainer.style.position = 'fixed';
                toastContainer.style.top = '20px';
                toastContainer.style.right = '20px';
                toastContainer.style.zIndex = '9999';
                document.body.appendChild(toastContainer);
            }
            
            const toast = document.createElement('div');
            toast.className = `toast-message-checklist alert alert-${type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger')}`;
            toast.role = 'alert';
            toast.textContent = message;
            toast.style.marginBottom = '10px';
            toastContainer.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 7000);
        }

        // Optimized checklist task functionality
        if (!window.checklistTaskInitialized) {
            window.checklistTaskInitialized = true;
            
            $(document).ready(function(){
                <?php if(!empty($error_msg_create)): ?> showChecklistToast("<?php echo addslashes($error_msg_create); ?>", 'danger'); <?php endif; ?>
            <?php if(!empty($success_msg_create)): ?> showChecklistToast("<?php echo addslashes($success_msg_create); ?>", 'success'); <?php endif; ?>
            <?php if(!empty($skipped_dates_msg_create)): ?> showChecklistToast("<?php echo addslashes($skipped_dates_msg_create); ?>", 'info'); <?php endif; ?>
            <?php if(!empty($error_msg) && empty($error_msg_create)): ?> showChecklistToast("<?php echo addslashes($error_msg); ?>", 'danger'); <?php endif; ?> // For general page errors like DB connection for table check

            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true,
                orientation: "bottom auto",
                todayBtn: "linked",
                clearBtn: true,
                language: 'en',
                calendarWeeks: false,
                multidate: false,
                forceParse: true,
                weekStart: 0, // Start week on Sunday (matching screenshot)
                startDate: new Date(),
                endDate: '+2y', // Allow dates up to 2 years in future
                templates: {
                    leftArrow: '&laquo;',
                    rightArrow: '&raquo;'
                },
                beforeShowDay: function(date) {
                    // Add custom classes for better styling
                    var today = new Date();
                    var isToday = date.getDate() === today.getDate() && 
                                 date.getMonth() === today.getMonth() && 
                                 date.getFullYear() === today.getFullYear();
                    
                    if (isToday) {
                        return {classes: 'today'};
                    }
                    return {};
                }
            });

            // Department and Manager auto-fill for task creation form
            $('#assignee_id').change(function(){
                var department = $(this).find('option:selected').data('department');
                var manager = $(this).find('option:selected').data('manager');
                
                $('#department_display').val(department ? department : 'N/A');
                $('#assigned_by_display').val(manager ? manager : 'Shubham Tyagi');
                $('#assigned_by').val(manager ? manager : 'Shubham Tyagi');
            });
            if($('#assignee_id').val()){
                 $('#assignee_id').trigger('change');
            }

            // Initialize Select2 for filter dropdowns (excluding doer filter to match manage_tasks.php)
            if ($.fn.select2) {
                // Doer filter uses native select to match manage_tasks.php UI
                $('#filter_department').select2({ placeholder: "Filter by Department", allowClear: true });
            }

            // Test if dropdown exists
            console.log('Number of task-action-dropdown elements found:', $('.task-action-dropdown').length);
            
            // Test click handler
            $(document).on('click', '.task-action-dropdown', function() {
                console.log('=== DROPDOWN CLICKED ===');
            });
            
            // Handle task action dropdown (both edit and status changes)
            $(document).on('change', '.task-action-dropdown', function() {
                console.log('=== DROPDOWN CHANGE EVENT TRIGGERED ===');
                var dropdown = $(this);
                var taskId = dropdown.data('task-id');
                var selectedOption = dropdown.find('option:selected');
                var action = selectedOption.data('action');
                var value = selectedOption.val();
                
                console.log('Checklist dropdown changed:', {
                    taskId: taskId,
                    action: action,
                    value: value,
                    selectedText: selectedOption.text(),
                    dropdownElement: dropdown[0],
                    selectedOptionElement: selectedOption[0]
                });
                
                console.log('Action check - action === "edit":', action === 'edit');
                console.log('Action value:', action);
                console.log('Value:', value);
                
                if (action === 'edit') {
                    // Handle edit action - Admin only
                    <?php if(!isAdmin()): ?>
                    alert('Only administrators can edit checklist tasks.');
                    // Reset dropdown to current status
                    var row = dropdown.closest('tr');
                    var currentStatusText = row.find('td').eq(4).find('.badge').text().trim().toLowerCase().replace(/\s+/g, '_');
                    var currentStatusValue = '';
                    switch(currentStatusText) {
                        case 'pending': currentStatusValue = 'pending'; break;
                        case 'completed': currentStatusValue = 'completed'; break;
                        case 'not_done': 
                        case 'notdone': currentStatusValue = 'not_done'; break;
                        case 'cant_be_done':
                        case 'cantbedone': currentStatusValue = 'cant_be_done'; break;
                        default: currentStatusValue = 'pending'; break;
                    }
                    dropdown.val(currentStatusValue);
                    return;
                    <?php endif; ?>
                    
                    // Handle edit action - Enable inline editing
                    console.log('Edit checklist task clicked for ID:', taskId);
                    var row = dropdown.closest('tr');
                    enableInlineEditing(row, taskId);
                    
                    // Reset dropdown to previous value
                    dropdown.val(dropdown.data('previous-value') || 'pending');
                    return;
                } else if (action === 'status') {
                    // Handle status change (existing logic)
                    handleChecklistStatusChange(dropdown, taskId, value);
                }
            });
            
            // Store the previous value of the dropdown when it's clicked
            $(document).on('click', '.task-action-dropdown', function() {
                console.log('Checklist dropdown clicked, storing previous value:', $(this).val());
                $(this).data('previous-value', $(this).val());
            });
            
            // Test if the dropdown change event is working at all
            $(document).on('change', 'select', function() {
                console.log('Any select changed:', $(this).attr('class'), $(this).val());
            });
            
            // Function to handle status changes
            function handleChecklistStatusChange(dropdown, taskId, newStatus) {
                var selectedText = dropdown.find("option:selected").text();
                
                if (!confirm('Are you sure you want to change the status of this task to "' + selectedText + '"?')) {
                    return;
                }

                $.ajax({
                    url: 'action_update_checklist_status.php', 
                    type: 'POST',
                    data: {
                        task_id: taskId,
                        status: newStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            console.log("Checklist task status updated successfully. Response:", response);
                            var row = dropdown.closest('tr');
                            if (!row.length) { 
                                console.error("Could not find table row for checklist task.");
                                alert(response.message + " (UI update for row failed)");
                                location.reload(); return; 
                            }

                            // Update Status Column using new status icon system
                            var statusCell = row.find('td.status-column');
                            if (statusCell.length) {
                                if (response.new_status_icon) {
                                    // Use the status icon HTML returned from the server
                                    statusCell.html(response.new_status_icon);
                                } else {
                                    // Fallback: generate status icon using JavaScript function
                                    var statusIcon = getStatusIcon(newStatus);
                                    statusCell.html(statusIcon);
                                }
                            }

                            // Update Actual Column using server response
                            var actualCell = row.find('td').eq(3);
                            if (response.updated_actual_display) {
                                actualCell.html('<small>' + response.updated_actual_display + '</small>');
                            } else if (newStatus === 'completed') {
                                actualCell.text('N/A (Updated)');
                            } else {
                                actualCell.text('N/A');
                            }

                            // Update Delayed Column using server response
                            var delayedCell = row.find('td').eq(5);
                            if (response.updated_delay_display_html) {
                                delayedCell.html('<small class="font-weight-bold">' + response.updated_delay_display_html + '</small>');
                            } else if (newStatus === 'pending' || newStatus === 'completed') {
                                delayedCell.html('N/A (Updated)'); 
                            } else { 
                                delayedCell.text('N/A');
                            }

                            showChecklistToast(response.message, 'success');
                            // Real-time updates handled above - no page reload needed
                        } else {
                            showChecklistToast('Error: ' + response.message, 'danger');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error - Checklist Status Update:", xhr, status, error);
                        showChecklistToast('AJAX Error: Could not update checklist task status. ' + error, 'danger');
                    }
                });
            }
            
            // Handle edit checklist task submission
            $('#submitEditChecklistBtn').on('click', function() {
                var taskId = $('#edit_checklist_task_id').val();
                var description = $('#edit_checklist_description').val();
                var taskDate = $('#edit_checklist_date').val();
                var assignee = $('#edit_checklist_assignee').val();
                var duration = $('#edit_checklist_duration').val();
                
                // Validate inputs
                if (!description || !taskDate || !assignee || !duration) {
                    alert('Please fill in all required fields.');
                    return;
                }
                
                // Validate duration format
                if (!/^\d{2}:\d{2}:\d{2}$/.test(duration)) {
                    alert('Please enter duration in HH:MM:SS format.');
                    return;
                }
                
                // Submit the edit request
                $.ajax({
                    url: 'action_update_checklist_edit.php',
                    type: 'POST',
                    data: {
                        task_id: taskId,
                        task_description: description,
                        task_date: taskDate,
                        assignee: assignee,
                        duration: duration
                    },
                    dataType: 'json',
                    success: function(response) {
                        // Hide the edit modal
                        
                        if (response.status === 'success') {
                            showChecklistToast(response.message, 'success');
                            setTimeout(function() { 
                                location.reload(); 
                            }, 1500);
                        } else {
                            showChecklistToast('Error: ' + response.message, 'danger');
                        }
                    },
                    error: function(xhr, status, error) {
                        // Hide the edit modal
                        
                        console.error("AJAX Error - Edit Checklist Task:", xhr, status, error);
                        showChecklistToast('AJAX Error: Could not update task. ' + error, 'danger');
                    }
                });
            });
            
            // Initialize datepicker for edit modal
            $('#edit_checklist_date').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true,
                startDate: new Date(),
                endDate: '+2y',
                clearBtn: true,
                weekStart: 0,
                templates: {
                    leftArrow: '&laquo;',
                    rightArrow: '&raquo;'
                }
            });
            
            // Set min attribute for edit_checklist_date input to disable past dates
            $('#edit_checklist_date').attr('min', new Date().toISOString().split('T')[0]);
            
            // Also set min attribute when modal is shown (in case input is dynamically created)
            $(document).on('shown.bs.modal', '#editChecklistTaskModal', function() {
                $('#edit_checklist_date').attr('min', new Date().toISOString().split('T')[0]);
            });
            
            // Global modal z-index fix - ensure all modals appear above header and sidebar
            $(document).on('shown.bs.modal', '.modal', function() {
                console.log('Modal shown:', $(this).attr('id'));
                $(this).css('z-index', '99999');
                $(this).find('.modal-dialog').css('z-index', '100000');
                $('.modal-backdrop').css({
                    'z-index': '99998',
                    'position': 'fixed',
                    'top': '0',
                    'left': '0',
                    'width': '100%',
                    'height': '100%'
                });
                
                // Ensure modal content is clickable
                $(this).find('.modal-content').css({
                    'position': 'relative',
                    'z-index': '100000',
                    'pointer-events': 'auto'
                });
                
                $(this).find('.modal-dialog').css({
                    'position': 'relative',
                    'z-index': '100000',
                    'pointer-events': 'auto'
                });
            });
            
            // Debug modal events
            $(document).on('show.bs.modal', '.modal', function() {
                console.log('Modal about to show:', $(this).attr('id'));
            });
            
            $(document).on('hide.bs.modal', '.modal', function() {
                console.log('Modal about to hide:', $(this).attr('id'));
            });
            
            $(document).on('hidden.bs.modal', '.modal', function() {
                console.log('Modal hidden:', $(this).attr('id'));
            });

            // AJAX for Checklist Task Status Dropdown change (legacy - keeping for backward compatibility)
            $(document).on('change', '.task-status-dropdown', function() {
                var dropdown = $(this);
                var taskId = dropdown.data('task-id');
                var newStatus = dropdown.val();
                var selectedText = dropdown.find("option:selected").text();

                if (!confirm('Are you sure you want to change the status of this task to "' + selectedText + '"?')) {
                    // If user cancels, revert the dropdown to its original value if possible
                    // This requires knowing the original value. For simplicity, we might just return.
                    // Or, we can reload the page to reflect the true state if the dropdown was already changed optimistically.
                    // For now, let's find the original status from the badge if needed for a revert, or just return.
                    // To prevent a half-changed state if user cancels, it is better to fetch original state or not change optimistically.
                    // For now, we simply return and the dropdown remains at the new (but unconfirmed) selection.
                    // A page reload could be forced here if it becomes an issue: // location.reload(); 
                    return;
                }

                $.ajax({
                    url: 'action_update_checklist_status.php', 
                    type: 'POST',
                    data: {
                        task_id: taskId,
                        status: newStatus // Send the new status value
                        // 'action' parameter might still be needed if your backend uses it to differentiate operations.
                        // Based on previous `action_update_checklist_status.php`, it expects 'action': 'complete'.
                        // We need to adapt it to handle various statuses, or add a new action type e.g. 'update_status'
                        // For now, let's assume the backend will be updated to infer action from 'status' field or handle a generic update.
                        // Let's assume the backend will look at the 'status' field to drive its logic.
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            console.log("Checklist task status updated successfully. Response:", response);
                            var row = dropdown.closest('tr');
                            if (!row.length) { 
                                console.error("Could not find table row for checklist task.");
                                alert(response.message + " (UI update for row failed)");
                                location.reload(); return; 
                            }

                            // Update Status Column using new status icon system
                            var statusCell = row.find('td.status-column');
                            if (statusCell.length) {
                                if (response.new_status_icon) {
                                    // Use the status icon HTML returned from the server
                                    statusCell.html(response.new_status_icon);
                                } else {
                                    // Fallback: generate status icon using JavaScript function
                                    var statusIcon = getStatusIcon(newStatus);
                                    statusCell.html(statusIcon);
                                }
                            }

                            // Update Actual Column
                            var actualCell = row.find('td').eq(3);
                            if (newStatus === 'completed') {
                                actualCell.text('N/A (Updated)'); // Server will set this, reload will show true value
                            } else {
                                actualCell.text('N/A');
                            }

                            // Update Delayed Column
                            var delayedCell = row.find('td').eq(5);
                            if (newStatus === 'pending' || newStatus === 'completed') {
                                delayedCell.html('N/A (Updated)'); 
                            } else { 
                                delayedCell.text('N/A');
                            }

                            showChecklistToast(response.message, 'success');
                            // Real-time updates handled above - no page reload needed // Give toast time to show
                        } else {
                            showChecklistToast('Error: ' + response.message, 'danger');
                            // Optionally reload or allow user to retry
                            // location.reload(); 
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error - Checklist Status Update:", xhr, status, error);
                        showChecklistToast('AJAX Error: Could not update checklist task status. ' + error, 'danger');
                        // location.reload(); 
                    }
                });
            });
            
            // Ensure delayed task highlighting is applied after page loads
            setTimeout(function() {
                if (typeof highlightDelayedTasks === 'function') {
                    highlightDelayedTasks();
                }
            }, 1000);
            // Native HTML title tooltip functionality
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
                    .replace(/(\d+)\s*D\b/g, '$1 Day')
                    .replace(/(\d+)\s*d\b/g, '$1 Day')
                    .replace(/(\d+)\s*h\b/g, '$1 Hrs')
                    .replace(/(\d+)\s*m\b/g, '$1 Min');
            }
            
            // Convert description format for tooltip
            function convertDescriptionForTooltip(description) {
                if (!description || description === 'N/A') {
                    return description;
                }
                return description.replace(/\n/g, '<br>');
            }

            // Simple Inline Editing Functions - Only Description, Duration, Doer
            function enableInlineEditing(row, taskId) {
                // Store original values
                row.data('original-values', {
                    taskDescription: row.find('td').eq(1).text(),
                    assignee: row.find('td').eq(7).text(),
                    duration: row.find('td').eq(6).text()
                });
                
                // Store task ID
                row.data('task-id', taskId);
                
                // Add edit mode class
                row.addClass('edit-mode');
                
                // 1. Make Description editable (textarea)
                var descCell = row.find('td').eq(1);
                var originalDesc = descCell.text();
                descCell.html('<textarea class="form-control form-control-sm" rows="2" style="min-width: 200px;">' + originalDesc + '</textarea>');
                
                // 2. Make Duration editable (dropdown)
                var durationCell = row.find('td').eq(6);
                var originalDuration = durationCell.text().trim();
                console.log('Original Duration:', originalDuration);
                var durationOptions = '<select class="form-control form-control-sm" style="min-width: 120px;">';
                durationOptions += '<option value="">Select Duration</option>';
                var durationValues = ['00:00:00', '00:15:00', '00:30:00', '00:45:00', '01:00:00', '01:30:00', '02:00:00', '02:30:00', '03:00:00', '03:30:00', '04:00:00', '04:30:00', '05:00:00', '05:30:00', '06:00:00', '06:30:00', '07:00:00', '07:30:00', '08:00:00', '12:00:00', '24:00:00'];
                for (var i = 0; i < durationValues.length; i++) {
                    var isSelected = originalDuration === durationValues[i];
                    if (isSelected) console.log('Duration match found:', durationValues[i]);
                    durationOptions += '<option value="' + durationValues[i] + '"' + (isSelected ? ' selected' : '') + '>' + durationValues[i] + '</option>';
                }
                durationOptions += '</select>';
                durationCell.html(durationOptions);
                
                // Force the dropdown to show the selected value
                setTimeout(function() {
                    var select = durationCell.find('select');
                    select.trigger('change');
                }, 100);
                
                // 3. Make Doer editable (dropdown)
                var doerCell = row.find('td').eq(7);
                var originalDoer = doerCell.text().trim();
                console.log('Original Doer:', originalDoer);
                var doerOptions = '<select class="form-control form-control-sm" style="min-width: 150px;">';
                doerOptions += '<option value="">Select Doer</option>';
                <?php 
                // Get all users for doer selection (only active users)
                $users_sql = "SELECT id, username, name, department_id FROM users WHERE user_type = 'doer' AND COALESCE(Status, 'Active') = 'Active' ORDER BY name";
                $users_result = mysqli_query($conn, $users_sql);
                if ($users_result && mysqli_num_rows($users_result) > 0) {
                    while ($user = mysqli_fetch_assoc($users_result)) {
                        echo "var isSelected = originalDoer.includes(" . json_encode($user['username']) . ");";
                        echo "if (isSelected) console.log('Doer match found:', " . json_encode($user['username']) . ");";
                        echo "doerOptions += '<option value=' + " . json_encode($user['username']) . " + ' ' + (isSelected ? 'selected' : '') + '>' + " . json_encode($user['name'] . ' (' . $user['username'] . ')') . " + '</option>';";
                    }
                }
                ?>
                doerOptions += '</select>';
                doerCell.html(doerOptions);
                
                // Force the dropdown to show the selected value
                setTimeout(function() {
                    var select = doerCell.find('select');
                    select.trigger('change');
                }, 100);
                
                // Replace action dropdown with Save/Cancel buttons
                var actionCell = row.find('td').eq(9);
                actionCell.html('<button class="btn btn-success btn-sm save-edit-btn mr-1"><i class="fas fa-save"></i> Save</button>' +
                               '<button class="btn btn-secondary btn-sm cancel-edit-btn"><i class="fas fa-times"></i> Cancel</button>');
            }

            function cancelInlineEditing(row) {
                var originalValues = row.data('original-values');
                
                // Restore original values for editable cells only
                row.find('td').eq(1).text(originalValues.taskDescription);
                row.find('td').eq(6).text(originalValues.duration);
                row.find('td').eq(7).text(originalValues.assignee);
                
                // Restore action dropdown
                var taskId = row.data('task-id');
                var currentStatus = row.find('td').eq(4).find('.badge').text().toLowerCase().replace(/\s+/g, '_');
                var actionDropdown = '<select class="form-control form-control-sm task-action-dropdown action-select" data-task-id="' + taskId + '" style="width: 100% !important; min-width: 180px !important; max-width: 200px !important; text-overflow: clip !important; overflow: visible !important; white-space: nowrap !important; height: 28px !important; line-height: 28px !important; padding: 0 28px 0 8px !important; font-size: 0.75rem !important;">' +
                    <?php if(isAdmin()): ?>
                    '<option value="edit" data-action="edit">Edit</option>' +
                    <?php endif; ?>
                    '<option value="pending" data-action="status"' + (currentStatus === 'pending' ? ' selected' : '') + '>Pending</option>' +
                    '<option value="completed" data-action="status"' + (currentStatus === 'completed' ? ' selected' : '') + '>Completed</option>' +
                    '<option value="not_done" data-action="status"' + (currentStatus === 'not_done' ? ' selected' : '') + '>Not done</option>' +
                    '<option value="cant_be_done" data-action="status"' + (currentStatus === 'cant_be_done' ? ' selected' : '') + '>Can\'t be done</option>' +
                    '</select>';
                row.find('td').eq(9).html(actionDropdown);
                
                // Remove edit mode class
                row.removeClass('edit-mode');
            }

            function saveInlineEdit(row, taskId) {
                // Collect edited values - only the 3 editable fields
                var editedData = {
                    task_id: taskId,
                    task_description: row.find('td').eq(1).find('textarea').val(),
                    assignee: row.find('td').eq(7).find('select').val(),
                    duration: row.find('td').eq(6).find('select').val()
                };
                
                // Debug logging
                console.log('Save data:', editedData);
                console.log('Task ID:', taskId);
                
                // Validate required fields
                if (!editedData.task_description.trim()) {
                    showChecklistToast('Task description is required!', 'danger');
                    return;
                }
                
                if (!editedData.assignee) {
                    showChecklistToast('Please select a doer!', 'danger');
                    return;
                }
                
                if (!editedData.duration) {
                    showChecklistToast('Please select a duration!', 'danger');
                    return;
                }
                
                // Show loading state
                var saveBtn = row.find('.save-edit-btn');
                var originalText = saveBtn.html();
                saveBtn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
                
                // Send AJAX request
                $.ajax({
                    url: 'action_update_checklist_edit.php',
                    type: 'POST',
                    data: editedData,
                    dataType: 'json',
                    success: function(response) {
                        console.log('AJAX Success Response:', response);
                        if (response.status === 'success') {
                            showChecklistToast(response.message, 'success');
                            
                            // Update only the edited cells
                            row.find('td').eq(1).text(editedData.task_description);
                            row.find('td').eq(6).text(editedData.duration);
                            row.find('td').eq(7).text(editedData.assignee);
                            
                            // Restore action dropdown
                            var currentStatus = row.find('td').eq(4).find('.badge').text().toLowerCase().replace(/\s+/g, '_');
                            var actionDropdown = '<select class="form-control form-control-sm task-action-dropdown action-select" data-task-id="' + taskId + '" style="width: 100% !important; min-width: 180px !important; max-width: 200px !important; text-overflow: clip !important; overflow: visible !important; white-space: nowrap !important; height: 28px !important; line-height: 28px !important; padding: 0 28px 0 8px !important; font-size: 0.75rem !important;">' +
                                <?php if(isAdmin()): ?>
                                '<option value="edit" data-action="edit">Edit</option>' +
                                <?php endif; ?>
                                '<option value="pending" data-action="status"' + (currentStatus === 'pending' ? ' selected' : '') + '>Pending</option>' +
                                '<option value="completed" data-action="status"' + (currentStatus === 'completed' ? ' selected' : '') + '>Completed</option>' +
                                '<option value="not_done" data-action="status"' + (currentStatus === 'not_done' ? ' selected' : '') + '>Not done</option>' +
                                '<option value="cant_be_done" data-action="status"' + (currentStatus === 'cant_be_done' ? ' selected' : '') + '>Can\'t be done</option>' +
                                '</select>';
                            row.find('td').eq(9).html(actionDropdown);
                            
                            // Remove edit mode class
                            row.removeClass('edit-mode');
                        } else {
                            console.log('Error response:', response);
                            showChecklistToast('Error: ' + response.message, 'danger');
                            saveBtn.html(originalText).prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error - Checklist Edit Update:", xhr, status, error);
                        console.error("Response Text:", xhr.responseText);
                        showChecklistToast('AJAX Error: Could not update checklist task. ' + error, 'danger');
                        saveBtn.html(originalText).prop('disabled', false);
                    }
                });
            }

            // Helper functions for date conversion
            function convertDisplayDateToInput(displayDate) {
                if (!displayDate || displayDate === 'N/A') return '';
                try {
                    var date = new Date(displayDate);
                    return date.toISOString().split('T')[0];
                } catch (e) {
                    return '';
                }
            }

            function convertInputDateToDisplay(inputDate) {
                if (!inputDate) return 'N/A';
                try {
                    var date = new Date(inputDate);
                    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                } catch (e) {
                    return inputDate;
                }
            }
            

            // Handle Cancel Edit
            $(document).on('click', '.cancel-edit-btn', function() {
                var row = $(this).closest('tr');
                cancelInlineEditing(row);
            });

            // Handle Save Edit
            $(document).on('click', '.save-edit-btn', function() {
                var row = $(this).closest('tr');
                var taskId = row.data('task-id');
                saveInlineEdit(row, taskId);
            });

            // Handle Save Edit
            $(document).on('click', '.save-edit-btn', function() {
                var row = $(this).closest('tr');
                var taskId = row.data('task-id');
                saveInlineEdit(row, taskId);
            });

            // ADD THE NEW CODE RIGHT HERE
            $('input[type="date"]').on('click', function() {
                try {
                    this.showPicker();
                } catch (e) {
                    // Fallback for browsers that do not support this.
                }
            });

            // Custom Filter Toggle Functionality for Checklist Tasks - matching manage_tasks.php exactly
            const checklistToggleButton = document.getElementById('checklistToggleFilters');
            const checklistFilterContent = document.getElementById('checklistFilterContent');
            const checklistFilterIcon = document.getElementById('checklistFilterToggleIcon');
            
            if (checklistToggleButton && checklistFilterContent && checklistFilterIcon) {
                checklistToggleButton.addEventListener('click', function() {
                    if (checklistFilterContent.classList.contains('collapsed')) {
                        // Show filters
                        checklistFilterContent.classList.remove('collapsed');
                        checklistFilterIcon.className = 'fas fa-chevron-up';
                        checklistToggleButton.innerHTML = '<i class="fas fa-chevron-up" id="checklistFilterToggleIcon"></i> Hide Filters';
                    } else {
                        // Hide filters
                        checklistFilterContent.classList.add('collapsed');
                        checklistFilterIcon.className = 'fas fa-chevron-down';
                        checklistToggleButton.innerHTML = '<i class="fas fa-chevron-down" id="checklistFilterToggleIcon"></i> Show Filters';
                    }
                });
            }

            // Create Task Form Toggle Functionality
            const createTaskToggleButton = document.getElementById('createTaskToggle');
            const createTaskContent = document.getElementById('createTaskContent');
            const createTaskToggleIcon = document.getElementById('createTaskToggleIcon');
            
            if (createTaskToggleButton && createTaskContent && createTaskToggleIcon) {
                createTaskToggleButton.addEventListener('click', function() {
                    if (createTaskContent.classList.contains('collapsed')) {
                        // Show form
                        createTaskContent.classList.remove('collapsed');
                        createTaskToggleIcon.className = 'fas fa-chevron-up';
                        createTaskToggleButton.innerHTML = '<i class="fas fa-chevron-up" id="createTaskToggleIcon"></i> Hide Form';
                    } else {
                        // Hide form
                        createTaskContent.classList.add('collapsed');
                        createTaskToggleIcon.className = 'fas fa-chevron-down';
                        createTaskToggleButton.innerHTML = '<i class="fas fa-chevron-down" id="createTaskToggleIcon"></i> Show Form';
                    }
                });
            }

        });
        
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
        }
    </script>
        </div> <!-- End content-area -->
    </div> <!-- End checklist-tasks-page -->
    </div>
</body>
</html>   