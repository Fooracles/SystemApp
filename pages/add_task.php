<?php
$page_title = "Add Delegation Tasks";
require_once "../includes/config.php";
require_once "../includes/functions.php";
require_once "../includes/status_column_helpers.php";
require_once "../includes/sorting_helpers.php";

// Update all pending tasks delay status
updateAllTasksDelayStatus($conn);

// Check if the user is logged in and is a manager
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

if(!isManager() && !isAdmin()) {
    header("location: doer_dashboard.php");
    exit;
}

// Define variables and initialize with empty values
$description = $planned_date = $planned_time = $duration = $doer_id = $department_id = $assigned_by = $manager_id = "";
$description_err = $planned_date_err = $planned_time_err = $duration_err = $doer_id_err = $department_err = $assigned_by_err = $manager_id_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate description
    if(empty(trim($_POST["description"]))) {
        $description_err = "Please enter task description.";
    } else {
        $description = trim($_POST["description"]);
    }
    
    // Validate planned date
    if(empty($_POST["planned_date"])) {
        $planned_date_err = "Please select date.";
    } else {
        $planned_date = $_POST["planned_date"];
    }
    
    // Validate planned time
    if(empty($_POST["planned_time"])) {
        $planned_time_err = "Please select time.";
    } else {
        $planned_time = $_POST["planned_time"];
    }
    
    // Validate duration
    if(!isset($_POST["duration"]) || $_POST["duration"] === "") {
        $duration_err = "Please select duration.";
    } else {
        $duration = intval($_POST["duration"]); // Store as integer minutes
        // Validate that duration is not negative (0 is allowed)
        if($duration < 0) {
            $duration_err = "Duration must not be negative.";
        }
        
        // Debug logging for duration values
        error_log("DEBUG: Duration form value: " . $_POST["duration"] . " -> Processed: " . $duration . " minutes");
    }
    
    // Validate department
    if(empty($_POST["department_id"])) {
        $department_err = "Please select department.";
    } else {
        $department_id = $_POST["department_id"];
    }
    
    // Validate doer
    if(empty($_POST["doer_id"])) {
        $doer_id_err = "Please select a doer.";
    } else {
        $doer_id = $_POST["doer_id"];
    }
    
    // Manager restrictions removed for delegation tasks - managers can assign to any user
    
    // Validate Assigner
    if(empty($_POST["assigned_by"])) {
        $assigned_by_err = "Please select who is assigning the task.";
    } else {
        $assigned_by = $_POST["assigned_by"];
        
        // If Assigner manager, validate manager selection
        if($assigned_by == "manager" && empty($_POST["manager_id"])) {
            $manager_id_err = "Please select a manager.";
        } elseif($assigned_by == "manager") {
            $manager_id = $_POST["manager_id"];
            // Set assigned_by to the manager's ID for database insertion
            $assigned_by = $manager_id;
            $assigned_by_type = "manager";
        } else {
            // If Assigner self, set to current user's ID
            $assigned_by = $_SESSION["id"];
            $manager_id = null;
            $assigned_by_type = "self";
        }
    }
    
    // Check input errors before inserting in database
    if(empty($description_err) && empty($planned_date_err) && empty($planned_time_err) && 
       empty($duration_err) && empty($doer_id_err) && empty($department_err) && 
       empty($assigned_by_err) && empty($manager_id_err)) {
        
        
        // Generate unique ID
        $unique_id = generateUniqueId();
        
        // Get doer username and manager for storage
        $doer_name = "";
        $doer_manager_id = null;
        $sql_get_doer = "SELECT username, manager_id FROM users WHERE id = ?";
        if($stmt_get_doer = mysqli_prepare($conn, $sql_get_doer)) {
            mysqli_stmt_bind_param($stmt_get_doer, "i", $doer_id);
            if(mysqli_stmt_execute($stmt_get_doer)) {
                $result_get_doer = mysqli_stmt_get_result($stmt_get_doer);
                if($row_get_doer = mysqli_fetch_assoc($result_get_doer)) {
                    $doer_name = $row_get_doer['username'];
                    $doer_manager_id = $row_get_doer['manager_id'];
                }
            }
            mysqli_stmt_close($stmt_get_doer);
        }
        
        // Get the current user's ID (the creator)
        $created_by = $_SESSION["id"];
        
        // Prepare an insert statement
        $sql = "INSERT INTO tasks (unique_id, description, planned_date, planned_time, duration, duration_minutes, doer_id, doer_name, manager_id, assigned_by, assigned_by_type, department_id, created_by, doer_manager_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssssdiissssiii", $unique_id, $param_description, $param_planned_date, $param_planned_time, 
                                $param_duration_for_db, $param_duration_minutes, $param_doer_id, $doer_name, $param_manager_id, $param_assigned_by, $param_assigned_by_type, $param_department_id, $created_by, $doer_manager_id);
            
            // Set parameters
            $param_description = $description;
            $param_planned_date = $planned_date;
            $param_planned_time = $planned_time;
            $param_duration_for_db = floatval($duration / 60); // Convert minutes to decimal hours for backward compatibility
            $param_duration_minutes = intval($duration); // Store as integer minutes
            $param_doer_id = $doer_id;
            $param_manager_id = $manager_id;
            $param_assigned_by = $assigned_by;
            $param_assigned_by_type = $assigned_by_type;
            $param_department_id = $department_id;
            
            // Debug logging for database insertion
            error_log("DEBUG: Database insertion - Duration Minutes: " . $param_duration_minutes . " (Type: " . gettype($param_duration_minutes) . ")");
            
            // Execute the statement
            if(mysqli_stmt_execute($stmt)) {
                // Close statement before redirect
                mysqli_stmt_close($stmt);
                
                // Redirect to prevent form resubmission (Post-Redirect-Get pattern)
                header("Location: " . $_SERVER["PHP_SELF"] . "?success=1");
                exit();
            } else {
                $error_msg = "Something went wrong. Please try again later. Error: " . mysqli_stmt_error($stmt);
                // Log the error
                file_put_contents("../logs/task_creation_error.log", date('Y-m-d H:i:s') . " - Error: " . mysqli_stmt_error($stmt) . " - SQL: " . $sql . "\n", FILE_APPEND);
                
                // Close statement
                mysqli_stmt_close($stmt);
            }
        }
    } else {
    }
}

// --- START: Sorting Parameters ---
// Support both old format (sort_column/sort_order) and new format (sort/dir) for backward compatibility
// Default: DESC by last created task (created_at DESC)
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : (isset($_GET['sort_column']) ? $_GET['sort_column'] : 'created_at');
$sort_direction = isset($_GET['dir']) ? $_GET['dir'] : (isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc');

// Validate sort parameters to prevent SQL injection
$allowed_columns = ['unique_id', 'description', 'planned_date', 'actual_date', 'status', 'delay_duration', 'duration', 'doer_name', 'assigned_by', 'created_at'];
$sort_column = validateSortColumn($sort_column, $allowed_columns, 'created_at');
$sort_direction = validateSortDirection($sort_direction);

// For backward compatibility with existing code that uses $sort_order
$sort_order = $sort_direction;
// --- END: Sorting Parameters ---

// --- START: Handle Success Message from URL ---
$success_msg = null;
$error_msg = null;

// Check if we have a success parameter from redirect
if (isset($_GET["success"]) && $_GET["success"] == "1") {
    $success_msg = "Task added successfully!";
}
// --- END: Handle Success Message from URL ---

// Include header after all processing is complete
require_once "../includes/header.php";

// Get users based on user role - Manager and Admin can delegate to users
if (isManager() || isAdmin()) {
    $doers = array();
    
    if (isAdmin()) {
        // Admin can see all users except client
        // Admin can assign tasks to other admin users
        $sql = "SELECT u.id, u.username, u.name, u.user_type, u.department_id, u.manager_id, d.name as department_name 
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.Status = 'Active' AND u.user_type != 'client'
                ORDER BY u.username ASC";
        $result = mysqli_query($conn, $sql);
    } else {
        // Manager can see all users except client and admin
        $sql = "SELECT u.id, u.username, u.name, u.user_type, u.department_id, u.manager_id, d.name as department_name 
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.Status = 'Active' AND u.user_type != 'client' AND u.user_type != 'admin'
                ORDER BY u.username ASC";
        $result = mysqli_query($conn, $sql);
    }
    
    if ($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $doers[] = $row;
        }
    }
} else {
    // For other users, show empty array (should not reach here due to access control)
    $doers = array();
}

// Get all departments for dropdown
$departments = array();
$sql = "SELECT id, name FROM departments ORDER BY name";
$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }
}

// Get all managers and admins for dropdown
$managers = array();
$sql = "SELECT id, username, name FROM users WHERE user_type IN ('manager', 'admin') ORDER BY name";
$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $managers[] = $row;
    }
}

// ---- START: Fetch Delegation Tasks for Table ----
$delegation_tasks_list = array();

// Helper function to build query string for pagination links (same as checklist_task.php)
function buildDelegationQuery($params = [], $exclude = []) {
    $query = $_GET;
    foreach ($exclude as $key) {
        unset($query[$key]);
    }
    $query = array_merge($query, $params);
    return http_build_query($query);
}

$items_per_page_delegation = 20; // Default items per page (matching checklist_task.php)
$current_page_delegation = isset($_GET['page_delegation']) ? (int)$_GET['page_delegation'] : 1;
if ($current_page_delegation < 1) $current_page_delegation = 1;
$offset_delegation = ($current_page_delegation - 1) * $items_per_page_delegation;
// ---- END: Initial Task List Setup ----

// ---- START: Filter Variables ----
$filter_id = isset($_GET['filter_id']) ? trim($_GET['filter_id']) : '';
$filter_description = isset($_GET['filter_description']) ? trim($_GET['filter_description']) : '';
$filter_doer = isset($_GET['filter_doer']) ? trim($_GET['filter_doer']) : '';
$filter_department = isset($_GET['filter_department']) ? trim($_GET['filter_department']) : '';
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';
$filter_date_from = isset($_GET['filter_date_from']) ? trim($_GET['filter_date_from']) : '';
$filter_date_to = isset($_GET['filter_date_to']) ? trim($_GET['filter_date_to']) : '';

// Get doer_status filter (default to 'Active', only for Admin/Manager)
$doer_status = 'Active'; // Default value
if (isAdmin() || isManager()) {
    $doer_status = isset($_GET['doer_status']) ? $_GET['doer_status'] : 'Active';
    // Validate doer_status value
    if ($doer_status !== 'Active' && $doer_status !== 'Inactive') {
        $doer_status = 'Active';
    }
}

$filter_conditions_sql = "";
$filter_params = [];
$filter_param_types = "";

// Add manager filtering for delegation tasks
if (isManager() && !isAdmin()) {
    $current_manager_id = $_SESSION["id"]; // Get current manager's ID
    $current_manager_name = $_SESSION["name"] ?? $_SESSION["username"]; // Get current manager's name, fallback to username
    $filter_conditions_sql .= " AND (t.manager_id = ? OR t.created_by = ? OR t.created_by = 0 OR t.doer_manager_id = ? OR COALESCE(t.doer_name, u.username, 'N/A') IN (SELECT username FROM users WHERE manager = ?))"; // updated on V2 server
    $filter_params[] = $current_manager_id;
    $filter_params[] = $current_manager_id;
    $filter_params[] = $current_manager_id;
    $filter_params[] = $current_manager_name;
    $filter_param_types .= "iiis";
}

if (!empty($filter_id)) {
    $filter_conditions_sql .= " AND t.unique_id LIKE ?";
    $filter_params[] = "%" . $filter_id . "%";
    $filter_param_types .= "s";
}
if (!empty($filter_description)) {
    $filter_conditions_sql .= " AND t.description LIKE ?";
    $filter_params[] = "%" . $filter_description . "%";
    $filter_param_types .= "s";
}
if (!empty($filter_doer)) {
    // Assuming join with users table as 'u'
    $filter_conditions_sql .= " AND u.username LIKE ?";
    $filter_params[] = "%" . $filter_doer . "%";
    $filter_param_types .= "s";
}
if (!empty($filter_department)) {
    // Assuming join with departments table as 'd'
    $filter_conditions_sql .= " AND d.name LIKE ?";
    $filter_params[] = "%" . $filter_department . "%";
    $filter_param_types .= "s";
}
if (!empty($filter_status)) {
    // Map filter form values (with underscores) to database values (with spaces)
    $db_filter_status = $filter_status;
    if ($filter_status === 'not_done') {
        $db_filter_status = 'not done';
    } elseif ($filter_status === 'cant_be_done') {
        $db_filter_status = 'can not be done';
    }
    
    // Normalize the status value for comparison (handle variations in database)
    // The database stores: 'not done', 'can not be done', etc.
    // Normalize by removing apostrophes, replacing underscores with spaces, and normalizing multiple spaces
    $normalized_status = strtolower(trim(preg_replace('/\s+/', ' ', str_replace(['\'', '_'], ['', ' '], $db_filter_status))));
    
    // Use normalized comparison to match database values with any variations
    $filter_conditions_sql .= " AND LOWER(TRIM(REPLACE(REPLACE(REPLACE(t.status, '\'', ''), '_', ' '), '  ', ' '))) = ?";
    $filter_params[] = $normalized_status;
    $filter_param_types .= "s";
}
if (!empty($filter_date_from)) {
    $filter_conditions_sql .= " AND t.planned_date >= ?";
    $filter_params[] = $filter_date_from;
    $filter_param_types .= "s";
}
if (!empty($filter_date_to)) {
    $filter_conditions_sql .= " AND t.planned_date <= ?";
    $filter_params[] = $filter_date_to;
    $filter_param_types .= "s";
}
// ---- END: Filter Variables ----

// ---- START: Populate Doer and Department Filter Dropdowns (mimicking manage_tasks.php approach) ----
$distinct_doers_for_filter_dropdown = [];
$distinct_departments_for_filter_dropdown = [];

// Get doers from users table (filtered by doer_status for Admin/Manager)
if (isAdmin() || isManager()) {
    // Query users table directly, filtered by doer_status
    // Only fetch username (not name) to show in dropdown
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
                        $distinct_doers_for_filter_dropdown[] = trim($doer_row['username']);
                    }
                }
                sort($distinct_doers_for_filter_dropdown);
            }
        }
        mysqli_stmt_close($stmt_doers);
    }
    
    // Get departments from tasks for the filtered doers
    if (!empty($distinct_doers_for_filter_dropdown)) {
        $placeholders = str_repeat('?,', count($distinct_doers_for_filter_dropdown) - 1) . '?';
        $dept_sql = "SELECT DISTINCT d.name as department_name 
                     FROM tasks t 
                     LEFT JOIN users u ON t.doer_id = u.id 
                     LEFT JOIN departments d ON t.department_id = d.id 
                     WHERE u.username IN ($placeholders) 
                       AND d.name IS NOT NULL 
                       AND d.name != ''
                     ORDER BY d.name ASC";
        $dept_stmt = mysqli_prepare($conn, $dept_sql);
        if ($dept_stmt) {
            mysqli_stmt_bind_param($dept_stmt, str_repeat('s', count($distinct_doers_for_filter_dropdown)), ...$distinct_doers_for_filter_dropdown);
            if (mysqli_stmt_execute($dept_stmt)) {
                $dept_result = mysqli_stmt_get_result($dept_stmt);
                while ($r = mysqli_fetch_assoc($dept_result)) {
                    if (!empty($r['department_name']) && !in_array($r['department_name'], $distinct_departments_for_filter_dropdown, true)) {
                        $distinct_departments_for_filter_dropdown[] = $r['department_name'];
                    }
                }
                sort($distinct_departments_for_filter_dropdown);
            }
            mysqli_stmt_close($dept_stmt);
        }
    }
} else {
    // For Doer users, use existing logic (from tasks)
    $sql_tasks_for_dropdown_source = "SELECT COALESCE(t.doer_name, u.username, 'N/A') as doer_name, d.name as department_name 
                                      FROM tasks t 
                                      LEFT JOIN users u ON t.doer_id = u.id 
                                      LEFT JOIN departments d ON t.department_id = d.id 
                                      WHERE 1=1";

$dropdown_source_filter_conditions_sql = "";
$dropdown_source_params = [];
$dropdown_source_param_types = "";

// Add manager filtering for dropdown source
if (isManager() && !isAdmin()) {
    $current_manager_id = $_SESSION["id"]; // Get current manager's ID
    $current_manager_name = $_SESSION["name"] ?? $_SESSION["username"]; // Get current manager's name, fallback to username
    $dropdown_source_filter_conditions_sql .= " AND (t.manager_id = ? OR COALESCE(t.doer_name, u.username, 'N/A') IN (SELECT username FROM users WHERE manager = ?))";
    $dropdown_source_params[] = $current_manager_id;
    $dropdown_source_params[] = $current_manager_name;
    $dropdown_source_param_types .= "is";
}

if (!empty($filter_id)) {
    $dropdown_source_filter_conditions_sql .= " AND t.unique_id LIKE ?";
    $dropdown_source_params[] = "%" . $filter_id . "%";
    $dropdown_source_param_types .= "s";
}
if (!empty($filter_description)) {
    $dropdown_source_filter_conditions_sql .= " AND t.description LIKE ?";
    $dropdown_source_params[] = "%" . $filter_description . "%";
    $dropdown_source_param_types .= "s";
}

$sql_tasks_for_dropdown_source .= $dropdown_source_filter_conditions_sql;

$tasks_source_for_dropdowns = [];
if (!empty($dropdown_source_params)) {
    if ($stmt_source = mysqli_prepare($conn, $sql_tasks_for_dropdown_source)) {
        mysqli_stmt_bind_param($stmt_source, $dropdown_source_param_types, ...$dropdown_source_params);
        if (mysqli_stmt_execute($stmt_source)) {
            $result_source = mysqli_stmt_get_result($stmt_source);
            while ($row_source = mysqli_fetch_assoc($result_source)) {
                $tasks_source_for_dropdowns[] = $row_source;
            }
        }
        mysqli_stmt_close($stmt_source);
    }
} else {
    $result_source = mysqli_query($conn, $sql_tasks_for_dropdown_source);
    if ($result_source) {
        while ($row_source = mysqli_fetch_assoc($result_source)) {
            $tasks_source_for_dropdowns[] = $row_source;
        }
    }
}

if (!empty($tasks_source_for_dropdowns)) {
    foreach ($tasks_source_for_dropdowns as $task_item) {
        if (!empty($task_item['doer_name']) && !in_array($task_item['doer_name'], $distinct_doers_for_filter_dropdown, true)) {
            // Check if this doer is a client user - exclude if so
            $check_client_sql = "SELECT user_type FROM users WHERE (username = ? OR name = ?) AND user_type = 'client' LIMIT 1";
            if ($check_client_stmt = mysqli_prepare($conn, $check_client_sql)) {
                $trimmed_doer = trim($task_item['doer_name']);
                mysqli_stmt_bind_param($check_client_stmt, "ss", $trimmed_doer, $trimmed_doer);
                if (mysqli_stmt_execute($check_client_stmt)) {
                    $check_client_result = mysqli_stmt_get_result($check_client_stmt);
                    if (mysqli_num_rows($check_client_result) == 0) {
                        // Not a client user, add to dropdown
                        $distinct_doers_for_filter_dropdown[] = $trimmed_doer;
                    }
                }
                mysqli_stmt_close($check_client_stmt);
            } else {
                // If query fails, add anyway (fallback)
                $distinct_doers_for_filter_dropdown[] = trim($task_item['doer_name']);
            }
        }
        if (!empty($task_item['department_name']) && !in_array($task_item['department_name'], $distinct_departments_for_filter_dropdown, true)) {
            $distinct_departments_for_filter_dropdown[] = $task_item['department_name'];
        }
    }
    sort($distinct_doers_for_filter_dropdown);
    sort($distinct_departments_for_filter_dropdown);
}
}
// ---- END: Populate Doer and Department Filter Dropdowns ----


// Get total count for pagination
$sql_count_delegation = "SELECT COUNT(t.id) as total_count 
                         FROM tasks t
                         LEFT JOIN users u ON t.doer_id = u.id
                         LEFT JOIN departments d ON t.department_id = d.id
                         WHERE 1=1" . $filter_conditions_sql;

$result_count_delegation = null;
if (!empty($filter_params)) {
    if ($stmt_count = mysqli_prepare($conn, $sql_count_delegation)) {
        mysqli_stmt_bind_param($stmt_count, $filter_param_types, ...$filter_params);
        if (mysqli_stmt_execute($stmt_count)) {
            $result_count_delegation = mysqli_stmt_get_result($stmt_count);
        }
        mysqli_stmt_close($stmt_count);
    }
} else {
    $result_count_delegation_query = mysqli_query($conn, $sql_count_delegation);
    if($result_count_delegation_query) {
        $result_count_delegation = $result_count_delegation_query;
    }
}

if($result_count_delegation) {
    $row_count_delegation = mysqli_fetch_assoc($result_count_delegation);
    $total_items_delegation = $row_count_delegation['total_count'] ?? 0;
}
$total_pages_delegation = ceil($total_items_delegation / $items_per_page_delegation);
if ($current_page_delegation > $total_pages_delegation && $total_pages_delegation > 0) $current_page_delegation = $total_pages_delegation;
$offset_delegation = ($current_page_delegation - 1) * $items_per_page_delegation;


// Fetch ALL delegation tasks first (without pagination)
$all_delegation_tasks = [];
$sql_all_delegation_tasks = "SELECT t.id, t.unique_id, t.description, t.planned_date, t.planned_time, t.duration, t.duration_minutes, t.status, 
                               t.actual_date, t.actual_time, t.is_delayed, t.delay_duration, t.shifted_count,
                               t.assigned_by, t.created_at,
                               t.manager_id, t.created_by, t.doer_manager_id,
                               COALESCE(t.doer_name, u.username, 'N/A') as doer_name, d.name as department_name,
                               COALESCE(m.username, m.name, 'N/A') as manager_name,
                               COALESCE(c.username, c.name, 'N/A') as created_by_name,
                               COALESCE(dm.username, dm.name, 'N/A') as doer_manager_name,
                               CASE 
                                   WHEN t.assigned_by_type = 'self' THEN 'Self'
                                   ELSE COALESCE(a.name, a.username)
                               END as assigned_by_name,
                               COALESCE(u.Status, 'Active') as doer_user_status,
                               (u.id IS NOT NULL) as doer_user_exists
                        FROM tasks t
                        LEFT JOIN users u ON t.doer_id = u.id
                        LEFT JOIN departments d ON t.department_id = d.id
                        LEFT JOIN users m ON t.manager_id = m.id
                        LEFT JOIN users c ON t.created_by = c.id
                        LEFT JOIN users dm ON t.doer_manager_id = dm.id
                        LEFT JOIN users a ON t.assigned_by = a.id
                        WHERE 1=1" . $filter_conditions_sql . "
                        " . buildSmartOrderBy($sort_column, $sort_direction, 't.', ['created_at' => 'DESC']) . "";

if($stmt_all_delegation_tasks = mysqli_prepare($conn, $sql_all_delegation_tasks)) {
    if (!empty($filter_param_types) && !empty($filter_params)){
        mysqli_stmt_bind_param($stmt_all_delegation_tasks, $filter_param_types, ...$filter_params);
    }
    
    if(mysqli_stmt_execute($stmt_all_delegation_tasks)) {
        $result_all_delegation_tasks = mysqli_stmt_get_result($stmt_all_delegation_tasks);
        while($row = mysqli_fetch_assoc($result_all_delegation_tasks)) {
            $all_delegation_tasks[] = $row;
        }
    }
    mysqli_stmt_close($stmt_all_delegation_tasks);
}

// Filter tasks by doer_status (only for Admin/Manager)
if (isAdmin() || isManager()) {
    $all_delegation_tasks = array_filter($all_delegation_tasks, function($task) use ($doer_status) {
        $task_doer_status = $task['doer_user_status'] ?? null;
        $doer_user_exists = isset($task['doer_user_exists']) ? (int)$task['doer_user_exists'] : 0;
        
        // If user doesn't exist in database, default to Active
        if ($doer_user_exists === 0) {
            return ($doer_status === 'Active');
        }
        
        // User exists - normalize and check status
        if (!empty($task_doer_status)) {
            $task_doer_status = ucfirst(strtolower(trim($task_doer_status)));
            if ($task_doer_status !== 'Active' && $task_doer_status !== 'Inactive') {
                return ($doer_status === 'Active');
            }
            return ($task_doer_status === $doer_status);
        } else {
            return ($doer_status === 'Active');
        }
    });
    // Re-index array after filtering
    $all_delegation_tasks = array_values($all_delegation_tasks);
}
// ---- END: Fetch ALL Delegation Tasks ----

// --- START: Sorting Logic ---
// Custom sorting function for delegation tasks
function sortDelegationTasks($a, $b, $column, $order) {
    // Handle special cases for different column types
    switch($column) {
        case 'planned_date':
            $dateA = strtotime($a['planned_date'] . ' ' . ($a['planned_time'] ?? '00:00:00'));
            $dateB = strtotime($b['planned_date'] . ' ' . ($b['planned_time'] ?? '00:00:00'));
            return ($order === 'asc') ? $dateA - $dateB : $dateB - $dateA;
            
        case 'actual_date':
            // Handle null values for actual dates
            if (empty($a['actual_date']) && empty($b['actual_date'])) return 0;
            if (empty($a['actual_date'])) return ($order === 'asc') ? 1 : -1;
            if (empty($b['actual_date'])) return ($order === 'asc') ? -1 : 1;
            
            $dateA = strtotime($a['actual_date'] . ' ' . ($a['actual_time'] ?? '00:00:00'));
            $dateB = strtotime($b['actual_date'] . ' ' . ($b['actual_time'] ?? '00:00:00'));
            return ($order === 'asc') ? $dateA - $dateB : $dateB - $dateA;
            
        case 'status':
            $statusA = strtolower($a['status'] ?? '');
            $statusB = strtolower($b['status'] ?? '');
            return ($order === 'asc') ? strcmp($statusA, $statusB) : strcmp($statusB, $statusA);
            
        case 'delay_duration':
            // Convert delay duration to seconds for comparison
            $delayA = isset($a['delay_duration']) ? parseDelayToSeconds($a['delay_duration']) : 0;
            $delayB = isset($b['delay_duration']) ? parseDelayToSeconds($b['delay_duration']) : 0;
            return ($order === 'asc') ? $delayA - $delayB : $delayB - $delayA;
            
        case 'duration':
            // Convert duration to seconds for comparison
            $durationA = isset($a['duration']) ? convertTimeToSeconds($a['duration']) : 0;
            $durationB = isset($b['duration']) ? convertTimeToSeconds($b['duration']) : 0;
            return ($order === 'asc') ? $durationA - $durationB : $durationB - $durationA;
            
        case 'created_at':
            // Handle created_at timestamp comparison
            $timeA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
            $timeB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
            return ($order === 'asc') ? $timeA - $timeB : $timeB - $timeA;
            
        default:
            // Default string comparison
            $valA = isset($a[$column]) ? strtolower($a[$column]) : '';
            $valB = isset($b[$column]) ? strtolower($b[$column]) : '';
            return ($order === 'asc') ? strcmp($valA, $valB) : strcmp($valB, $valA);
    }
}

// Helper function to convert delay string to seconds
function parseDelayToSeconds($delay) {
    if (empty($delay) || $delay === 'N/A' || $delay === 'On Time') {
        return 0;
    }
    
    // Parse delay format like "X D Y h Z m" (new format) or "X days Y hrs Z mins" (legacy format)
    $seconds = 0;
    
    // New format: "X D Y h Z m"
    if (preg_match('/(\d+)\s+D\b/i', $delay, $matches)) {
        $seconds += $matches[1] * 86400; // days to seconds
    }
    
    if (preg_match('/(\d+)\s+h\b/i', $delay, $matches)) {
        $seconds += $matches[1] * 3600; // hours to seconds
    }
    
    if (preg_match('/(\d+)\s+m\b/i', $delay, $matches)) {
        $seconds += $matches[1] * 60; // minutes to seconds
    }
    
    // Legacy format: "X days Y hrs Z mins" (for backward compatibility)
    if ($seconds === 0) {
        if (preg_match('/(\d+)\s+days?/i', $delay, $matches)) {
            $seconds += $matches[1] * 86400; // days to seconds
        }
        
        if (preg_match('/(\d+)\s+hrs?/i', $delay, $matches)) {
            $seconds += $matches[1] * 3600; // hours to seconds
        }
        
        if (preg_match('/(\d+)\s+mins?/i', $delay, $matches)) {
            $seconds += $matches[1] * 60; // minutes to seconds
        }
    }
    
    return $seconds;
}

// Helper function to convert time format to seconds
function convertTimeToSeconds($time) {
    if (empty($time)) {
        return 0;
    }
    
    // Handle HH:MM:SS format
    if (preg_match('/(\d{2}):(\d{2}):(\d{2})/', $time, $matches)) {
        return ($matches[1] * 3600) + ($matches[2] * 60) + $matches[3];
    }
    
    return 0;
}

// Sort the entire dataset
usort($all_delegation_tasks, function($a, $b) use ($sort_column, $sort_order) {
    return sortDelegationTasks($a, $b, $sort_column, $sort_order);
});

// Recalculate pagination after sorting (matching checklist_task.php)
$total_filtered_tasks_delegation = count($all_delegation_tasks);
$total_items_delegation = $total_filtered_tasks_delegation; // Update total_items to match filtered count
$total_pages_delegation = max(1, ceil($total_filtered_tasks_delegation / $items_per_page_delegation));
// Ensure current page is within valid range
if ($current_page_delegation > $total_pages_delegation && $total_pages_delegation > 0) {
    $current_page_delegation = 1;
    $offset_delegation = 0;
} else {
    // Recalculate offset based on current page
    $offset_delegation = ($current_page_delegation - 1) * $items_per_page_delegation;
}
$delegation_tasks_list = array_slice($all_delegation_tasks, $offset_delegation, $items_per_page_delegation);
// --- END: Sorting Logic ---

?>

<!-- HTML structure is handled by header.php -->
    
    <!-- Native HTML5 date picker styling -->
    <style>
        /* Enhanced styling for native date inputs - Dark Theme */
        input[type="date"] {
            position: relative;
            padding: 0.375rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.4;
            color: #e0e0e0 !important;
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a) !important;
            background-clip: padding-box;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 6px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        input[type="date"]:focus {
            color: white !important;
            background: linear-gradient(135deg, #2a2a2a, #333333) !important;
            border-color: var(--brand-primary) !important;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25) !important;
        }
        
        input[type="date"]:invalid {
            border-color: rgba(255, 255, 255, 0.12) !important;
        }
        
        /* Custom styling for date input icons */
        input[type="date"]::-webkit-calendar-picker-indicator {
            background: transparent;
            bottom: 0;
            color: transparent;
            cursor: pointer;
            height: auto;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            width: auto;
        }
        
        /* Ensure consistent appearance across browsers */
        input[type="date"]::-webkit-datetime-edit {
            padding: 0;
        }
        
        input[type="date"]::-webkit-datetime-edit-fields-wrapper {
            padding: 0;
        }
        
        input[type="date"]::-webkit-datetime-edit-text {
            color: #888888;
            padding: 0 0.25rem;
        }
        
        /* Placeholder styling for better visibility - Dark Theme */
        .form-control::placeholder {
            color: #888888;
            opacity: 0.8;
            font-style: normal;
        }
        
        .form-control::-webkit-input-placeholder {
            color: #888888;
            opacity: 0.8;
        }
        
        .form-control::-moz-placeholder {
            color: #888888;
            opacity: 0.8;
        }
        
        .form-control:-ms-input-placeholder {
            color: #888888;
            opacity: 0.8;
        }
        
        .form-control:-moz-placeholder {
            color: #888888;
            opacity: 0.8;
        }
        
        /* Ensure placeholders are visible in textarea - Dark Theme */
        textarea.form-control::placeholder {
            color: #888888;
            opacity: 0.8;
            font-style: normal;
        }
    </style>
    <style>
        /* Dark Theme Styling for Add Task Page */
        .add-task-page {
            background: transparent;
            color: var(--dark-text-primary);
            min-height: 100vh;
        }

        .add-task-page .content-area {
            background: transparent;
            padding: 5px;
        }

        .add-task-page .container-fluid {
            background: transparent;
        }

        .add-task-page h2 {
            color: var(--dark-text-primary);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        /* Card Styling */
        .add-task-page .card {
            background: var(--dark-bg-glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(10px);
            transition: var(--transition-normal);
        }

        .add-task-page .card:hover {
            box-shadow: var(--glass-shadow-hover);
            transform: translateY(-2px);
        }

        .add-task-page .card-header {
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d, #1a1a1a);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #e0e0e0;
            font-weight: 600;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .add-task-page .card-body {
            background: var(--dark-bg-glass);
            color: var(--dark-text-primary);
        }

        /* Form Styling */
        .add-task-page .form-group label {
            color: #b0b0b0;
            font-weight: 500;
            margin-bottom: 0.25rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            font-size: 0.875rem;
        }

        .add-task-page .form-control {
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: var(--radius-md);
            color: #e0e0e0;
            transition: var(--transition-normal);
            padding: 0.375rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .add-task-page .form-control:focus {
            background: linear-gradient(135deg, #2a2a2a, #333333);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            color: white;
        }

        .add-task-page .form-control::placeholder {
            color: #888888;
            opacity: 0.8;
        }

        /* Form Wrap Styling */
        .form-wrap { 
            max-width: 1000px; 
            margin: 10px auto; 
            background: var(--dark-bg-glass); 
            border: 1px solid var(--glass-border); 
            border-radius: var(--radius-lg); 
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(10px);
        }
        .form-wrap .header { 
            padding: 0.75rem 1rem; 
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d, #1a1a1a);
            color: #e0e0e0; 
            border-radius: var(--radius-lg) var(--radius-lg) 0 0; 
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        
        .form-wrap .header h4 {
            position: relative;
            z-index: 1;
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .form-wrap .content { 
            padding: 0.75rem 0.75rem; 
            background: var(--dark-bg-glass);
        }
        .form-wrap .content .form-control {
            width: 100% !important;
            max-width: 100% !important;
        }
        
        /* Override form-control-lg to use smaller size */
        .form-wrap .form-control-lg {
            padding: 0.375rem 0.5rem !important;
            font-size: 0.875rem !important;
            line-height: 1.4 !important;
            min-height: auto !important;
        }

        /* Button Styling */
        .add-task-page .btn-primary {
            background: linear-gradient(135deg, #00d4aa, #00a085);
            border: 1px solid rgba(0, 212, 170, 0.3);
            color: white;
            border-radius: var(--radius-md);
            transition: var(--transition-normal);
            box-shadow: 0 0 15px rgba(0, 212, 170, 0.4), 0 2px 4px rgba(0, 0, 0, 0.3);
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .add-task-page .btn-primary:hover {
            background: linear-gradient(135deg, #00a085, #00d4aa);
            border-color: rgba(0, 212, 170, 0.5);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 0 25px rgba(0, 212, 170, 0.6), 0 4px 8px rgba(0, 0, 0, 0.4);
        }

        .add-task-page .btn-secondary {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #b0b0b0;
            border-radius: var(--radius-md);
            transition: var(--transition-normal);
        }

        .add-task-page .btn-secondary:hover {
            background: linear-gradient(135deg, #2a2a2a, #333333);
            border-color: rgba(255, 255, 255, 0.2);
            color: #e0e0e0;
            transform: translateY(-1px);
        }

        /* Alert Styling */
        .add-task-page .alert {
            border-radius: var(--radius-md);
            border: none;
            backdrop-filter: blur(10px);
        }

        .add-task-page .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-left: 4px solid #28a745;
        }

        .add-task-page .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .add-task-page .alert-info {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
            border-left: 4px solid #17a2b8;
        }

        /* Table Styling */
        .add-task-page .table {
            background: var(--dark-bg-glass);
            color: var(--dark-text-primary);
            border-radius: var(--radius-md);
            overflow: hidden;
            table-layout: fixed;
        }

        .add-task-page .table thead th {
            background: linear-gradient(135deg, var(--dark-bg-secondary), var(--dark-bg-primary)) !important;
            color: var(--dark-text-primary) !important;
            border: none !important;
            font-weight: 600 !important;
            font-size: 0.70rem !important; /* Reduced from 0.8rem for better readability and spacing */
            padding: 0.75rem 0.6rem !important;
            border-bottom: 2px solid var(--glass-border) !important;
            cursor: default !important;
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: clip !important;
        }

        /* Override any global hover effects on table headers */
        .add-task-page .table thead th:hover,
        .add-task-page .table thead th:active,
        .add-task-page .table thead th:focus,
        .add-task-page .table thead th.sortable-header:hover,
        .add-task-page .table thead th.sortable-header:active,
        .add-task-page .table thead th.sortable-header:focus {
            background: linear-gradient(135deg, var(--dark-bg-secondary), var(--dark-bg-primary)) !important;
            color: var(--dark-text-primary) !important;
            transform: none !important;
            box-shadow: none !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        /* Remove any potential Bootstrap table-hover effects on headers */
        .add-task-page .table-hover thead th:hover {
            background: linear-gradient(135deg, var(--dark-bg-secondary), var(--dark-bg-primary)) !important;
            color: var(--dark-text-primary) !important;
        }

        .add-task-page .table tbody td {
            background: var(--dark-bg-glass);
            color: var(--dark-text-primary);
            border-color: var(--glass-border);
            padding: 0.6rem 0.5rem;
            vertical-align: middle;
            font-size: 0.8rem;
        }

        .add-task-page .table tbody tr:hover {
            background: var(--dark-bg-hover);
        }

        .add-task-page .table tbody tr:hover td {
            background: var(--dark-bg-hover);
        }

        /* Column Widths - matching checklist_task.php */
        .add-task-page .table td:nth-child(1) { width: 5%; } /* ID */
        .add-task-page .table td:nth-child(2) { width: 38%; } /* Description */
        .add-task-page .table td:nth-child(3) { width: 10%; } /* Planned */
        .add-task-page .table td:nth-child(4) { width: 10%; } /* Actual */
        .add-task-page .table td:nth-child(5) { width: 4%; } /* Status */
        .add-task-page .table td:nth-child(6) { width: 10%; } /* Delayed */
        .add-task-page .table td:nth-child(7) { width: 10%; } /* Duration */
        .add-task-page .table td:nth-child(8) { width: 10%; } /* Doer */
        .add-task-page .table td:nth-child(9) { width: 8%; } /* Assigner */
        .add-task-page .table td:nth-child(10) { width: 14%; } /* Actions */

        /* Text truncation - ensure text is visible */
        .add-task-page .table td:nth-child(2),
        .add-task-page .table td:nth-child(6) {
            white-space: normal;
            overflow: visible;
            word-wrap: break-word;
            word-break: break-word;
        }
        
        /* Ensure all table cells don't cut text */
        .add-task-page .table td {
            overflow: visible !important;
            word-wrap: break-word;
        }

        /* Increase table width by reducing side margins */
        .add-task-page .container {
            max-width: 98% !important;
            margin-left: auto !important;
            margin-right: auto !important;
            padding-left: 10px !important;
            padding-right: 10px !important;
        }

        /* Reduce card body padding for delegation tasks table */
        .add-task-page .row.mt-4 .card-body {
            padding-left: 15px !important;
            padding-right: 15px !important;
        }

        /* Reduce table-responsive padding if needed */
        .add-task-page .row.mt-4 .table-responsive {
            margin-left: -5px;
            margin-right: -5px;
        }

        /* Collapsible Form Styles */
        .add-task-form-content {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .add-task-form-content.collapsed {
            max-height: 0;
            padding: 0;
            margin: 0;
        }

        .add-task-toggle-btn {
            background: linear-gradient(135deg, #333333, #444444);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #e0e0e0;
            border-radius: var(--radius-md);
            transition: var(--transition-normal);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .add-task-toggle-btn:hover {
            background: linear-gradient(135deg, #444444, #555555);
            border-color: rgba(255, 255, 255, 0.25);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        /* Delegation Filters Collapsible Styles - Fixed Lag */
        .delegation-filters-content {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .delegation-filters-content.collapsed {
            max-height: 0;
            padding: 0;
            margin: 0;
        }

        /* Filter Form Styling - Matching Checklist Task */
        .add-task-page .filter-form {
            background: linear-gradient(135deg, #0f0f0f, #1a1a1a, #0f0f0f);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-top: none;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
            padding: 1.5rem;
        }

        .add-task-page .filter-form .form-control {
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #e0e0e0;
            border-radius: var(--radius-sm);
            transition: var(--transition-normal);
        }

        .add-task-page .filter-form .form-control:focus {
            background: linear-gradient(135deg, #2a2a2a, #333333);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            color: white;
        }

        .add-task-page .filter-form .form-control::placeholder {
            color: #888888;
            opacity: 0.8;
        }

        .add-task-page .filter-form label {
            color: #b0b0b0;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .add-task-page .filter-form .btn-primary,
        .add-task-page .filter-form .btn-secondary {
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

        .add-task-page .filter-form .btn-primary:hover,
        .add-task-page .filter-form .btn-secondary:hover {
            background: linear-gradient(135deg, #00a085, #00d4aa);
            border-color: rgba(0, 212, 170, 0.5);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 0 25px rgba(0, 212, 170, 0.6), 0 4px 8px rgba(0, 0, 0, 0.4);
        }

        /* Ensure proper spacing between buttons */
        .add-task-page .filter-form .d-flex.gap-2 {
            gap: 0.5rem !important;
        }

        /* User Status Toggle Styling - matching manage_tasks.php */
        .add-task-page .doer-status-toggle {
            width: 100%;
        }

        .add-task-page .doer-status-toggle .btn-group {
            width: 100%;
            display: flex;
        }

        .add-task-page .doer-status-toggle .btn-group .btn {
            flex: 1;
            background: var(--dark-bg-glass);
            border: 1px solid var(--glass-border);
            color: var(--dark-text-secondary);
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
            transition: var(--transition-normal);
        }

        .add-task-page .doer-status-toggle .btn-group .btn:hover {
            background: var(--dark-bg-hover);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .add-task-page .doer-status-toggle .btn-group .btn.active,
        .add-task-page .doer-status-toggle .btn-group .btn-primary {
            background: var(--gradient-primary);
            border-color: var(--primary-color);
            color: white;
        }

        .add-task-page .doer-status-toggle .btn-group .btn input[type="radio"] {
            display: none;
        }
        
        /* Horizontal Form Layout Enhancements */
        .form-wrap .row {
            margin-bottom: 0.4rem;
        }
        
        .form-wrap .row:last-child {
            margin-bottom: 0;
        }
        
        .form-wrap .form-group {
            margin-bottom: 0.4rem;
        }
        
        .form-wrap .form-group label {
            font-weight: 600;
            color: var(--dark-text-secondary);
            margin-bottom: 0.2rem;
            font-size: 0.875rem;
        }
        
        .form-wrap .form-control {
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 6px;
            padding: 0.375rem 0.5rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            line-height: 1.4;
            color: #e0e0e0 !important;
        }
        
        .form-wrap .form-control:focus {
            background: linear-gradient(135deg, #2a2a2a, #333333) !important;
            border-color: var(--brand-primary) !important;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25) !important;
            color: white !important;
        }
        
        .form-wrap select.form-control {
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a) !important;
            color: #e0e0e0 !important;
        }
        
        .form-wrap select.form-control option {
            background: #1e1e1e !important;
            color: #e0e0e0 !important;
        }
        
        .form-wrap input[type="date"].form-control {
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a) !important;
            color: #e0e0e0 !important;
        }
        
        .form-wrap textarea.form-control {
            background: linear-gradient(135deg, #1e1e1e, #2a2a2a) !important;
            color: #e0e0e0 !important;
        }
        
        /* Fix for select elements to prevent placeholder text from being cut off */
        .form-wrap select.form-control {
            height: auto;
            min-height: calc(1.5em + 1rem + 4px);
            padding: 0.5rem 0.75rem;
            line-height: 1.5;
        }
        
        /* Auto-adjust width for filter dropdowns to show full text */
        #filter_doer_select,
        #filter_status {
            width: 100% !important;
            min-width: 150px !important;
            padding: 0.25rem 2rem 0.25rem 0.5rem !important; /* Extra right padding for arrow */
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: ellipsis !important;
            box-sizing: border-box !important;
        }
        
        /* Expand dropdown on focus to show full selected text */
        #filter_doer_select:focus,
        #filter_status:focus {
            min-width: 180px !important;
            width: auto !important;
            max-width: 100% !important;
        }
        
        /* Ensure dropdown options show full text */
        #filter_doer_select option,
        #filter_status option {
            white-space: normal !important;
            padding: 5px 10px !important;
        }
        
        .form-wrap select.form-control-lg {
            min-height: calc(1.5em + 0.75rem + 4px);
            padding: 0.375rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-wrap .form-control.is-invalid {
            border-color: #ef4444 !important;
        }
        
        .form-wrap .form-control.is-invalid:focus {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 0.2rem rgba(239, 68, 68, 0.25) !important;
        }
        
        .form-wrap textarea.form-control {
            resize: vertical;
            min-height: 60px;
            padding: 0.375rem 0.5rem;
        }
        
        .form-wrap .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .form-wrap .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }
        
        /* Responsive adjustments for mobile */
        @media (max-width: 768px) {
            .form-wrap .row .col-md-6,
            .form-wrap .row .col-md-4 {
                margin-bottom: 1rem;
            }
            
            .form-wrap .row .col-md-6:last-child,
            .form-wrap .row .col-md-4:last-child {
                margin-bottom: 0;
            }
        }
        .btn-full {
            display:block; 
            width:100%; 
            text-align:center; 
            font-weight:600;
            padding:.85rem 1rem; 
            border-radius:.6rem;
        }
        .help { 
            color:#667085; 
            font-size:.85rem; 
        }
        
        /* Custom styles for Select2 to match form-control-sm */
        .select2-container .select2-selection--single {
            height: calc(1.5em + .5rem + 2px); /* Match Bootstrap's form-control-sm height */
            padding: .25rem .5rem;            /* Match Bootstrap's form-control-sm padding */
            font-size: .875rem;               /* Match Bootstrap's form-control-sm font-size */
            line-height: 1.5;
        }
        .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 1.5; 
            padding-left: 0; 
        }
        .select2-container .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + .5rem); 
        }
        
        /* Custom styles for task status dropdown - now moved to global CSS */
        /* Removed inline styles as they are now in assets/css/style.css */
        
        .bg-warning-light {
            background-color: #FFDE21 !important; /* Yellow for shifted count = 1 */
            color: #000000 !important; /* Black text */
        }
        .bg-danger-light {
            background-color: #FF2C2C !important; /* Red for shifted count = 2 */
            color: #FFFFFF !important; /* White text */
        }
        
        /* Edit Mode Styling - Dark Theme */
        .edit-mode {
            background: rgba(99, 102, 241, 0.15) !important; /* Dark theme highlight with brand primary tint */
            border: 2px solid var(--brand-primary) !important; /* Brand primary border */
        }
        
        .edit-mode td {
            padding: 6px 8px !important;
            background: rgba(99, 102, 241, 0.15) !important; /* Dark theme highlight */
            color: var(--dark-text-primary) !important;
            vertical-align: middle !important;
        }
        
        .edit-mode:hover td {
            background: rgba(99, 102, 241, 0.2) !important; /* Slightly brighter on hover */
        }
        
        .edit-mode .form-control,
        .edit-mode .form-control-sm,
        tr.edit-mode .form-control,
        tr.edit-mode select,
        tr.edit-mode textarea {
            background-color: #2d2d3d !important;
            background: #2d2d3d !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            border-radius: 4px !important;
            color: #ffffff !important;
            font-size: 13px !important;
            -webkit-text-fill-color: #ffffff !important;
        }
        
        .edit-mode .form-control:focus,
        tr.edit-mode select:focus,
        tr.edit-mode textarea:focus {
            background-color: #353548 !important;
            background: #353548 !important;
            border-color: var(--brand-primary) !important;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25) !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
        }
        
        .edit-mode select.form-control,
        tr.edit-mode select,
        .edit-mode .edit-select {
            background-color: #2d2d3d !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            overflow: visible !important;
            text-overflow: clip !important;
            white-space: nowrap !important;
            font-size: 14px !important;
            padding: 4px 6px !important;
            height: 30px !important;
            line-height: 1.5 !important;
            width: auto !important;
            min-width: 0 !important;
            max-width: none !important;
        }
        
        .edit-mode select.form-control option,
        tr.edit-mode select option {
            background-color: #2d2d3d !important;
            color: #ffffff !important;
        }
        
        .edit-mode select.form-control option:hover,
        .edit-mode select.form-control option:checked {
            background-color: var(--brand-primary) !important;
            color: white !important;
        }
        
        .edit-mode textarea.form-control,
        tr.edit-mode textarea,
        .edit-mode .edit-textarea {
            background-color: #2d2d3d !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            resize: vertical;
            min-width: 180px;
            font-size: 14px !important;
            padding: 4px 8px !important;
            line-height: 1.5 !important;
        }
        
        /* Edit mode Save/Cancel buttons - Dark Theme */
        .edit-mode .save-edit-btn {
            background: linear-gradient(135deg, #28a745, #1e7e34) !important;
            border: 1px solid rgba(40, 167, 69, 0.3) !important;
            color: white !important;
        }
        
        .edit-mode .save-edit-btn:hover {
            background: linear-gradient(135deg, #1e7e34, #28a745) !important;
            border-color: rgba(40, 167, 69, 0.5) !important;
            color: white !important;
        }
        
        .edit-mode .cancel-edit-btn {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #b0b0b0 !important;
        }
        
        .edit-mode .cancel-edit-btn:hover {
            background: linear-gradient(135deg, #2a2a2a, #333333) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            color: #e0e0e0 !important;
        }

        /* Edit mode action buttons container */
        .edit-mode .edit-action-btns {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
            min-width: 90px;
        }

        .edit-mode .edit-action-btns .btn {
            width: 100%;
            white-space: nowrap;
            font-size: 12px;
            padding: 4px 8px;
        }

        /* Delay hover styling */
        .delay-hover {
            cursor: pointer;
            border-bottom: 1px dotted #dc3545;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 80px;
            display: inline-block;
            position: relative;
        }
        
        .delay-hover:hover {
            text-decoration: none;
        }
        
        .delay-hover::after {
            content: attr(data-full-delay);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #000;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            white-space: nowrap;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            border: 1px solid #333;
        }
        
        .delay-hover::before {
            content: '';
            position: absolute;
            bottom: 115%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .delay-hover:hover::after,
        .delay-hover:hover::before {
            opacity: 1;
            visibility: visible;
        }
        
        /* Description hover styling */
        .description-hover {
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
            display: inline-block;
            position: relative;
            width: 100%;
        }
        
        /* Ensure description column has fixed width */
        .table {
            table-layout: fixed;
        }
        
        .table td:nth-child(2) {
            max-width: 150px;
            width: 150px;
            word-wrap: break-word;
            overflow: hidden;
        }
        
        .description-hover:hover {
            text-decoration: none;
        }
        
        /* Custom tooltip for description */
        .description-hover::after {
            content: attr(data-full-description);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #000;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            white-space: normal;
            max-width: 300px;
            word-wrap: break-word;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            border: 1px solid #333;
        }
        
        .description-hover::before {
            content: '';
            position: absolute;
            bottom: 115%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .description-hover:hover::after,
        .description-hover:hover::before {
            opacity: 1;
            visibility: visible;
        }
    </style>
    
    <!-- Content will be wrapped by header.php -->
    <div class="add-task-page">
    <div class="content-area">
        <div class="container mt-2">
        <div class="d-flex justify-content-end align-items-center mb-2">
            <?php if(isLoggedIn()): ?>
                <p class="mb-0">Welcome, <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong>!</p>
            <?php endif; ?>
        </div>

        <div class="form-wrap">
                <div class="header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Add Delegation Task</h4>
                    <button class="btn btn-sm add-task-toggle-btn" type="button" id="addTaskToggle">
                        <i class="fas fa-chevron-up" id="addTaskToggleIcon"></i> Hide Form
                    </button>
            </div>
                <div class="add-task-form-content" id="addTaskContent">
            <div class="content">
                        <?php if(isset($error_msg)): ?>
                            <div class="alert alert-danger mb-2" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;"><?php echo $error_msg; ?></div>
                        <?php endif; ?>
                        <?php if(isset($success_msg)): ?>
                            <div class="alert alert-success mb-2" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;"><?php echo $success_msg; ?></div>
                        <?php endif; ?>
                        
                        <form id="taskForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="margin: 0; padding: 0;">
                            <!-- Row 1: Doer, Department, Duration -->
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="doer_id" class="form-label">
                                            <i class="fas fa-user"></i> Select Doer
                                        </label>
                                        <select name="doer_id" id="doer_id" class="form-control <?php echo (!empty($doer_id_err)) ? 'is-invalid' : ''; ?>" required>
                                            <option value="">Choose a doer...</option>
                                            <?php foreach($doers as $doer): ?>
                                                <option value="<?php echo $doer['id']; ?>" 
                                                        data-department="<?php echo $doer['department_id']; ?>"
                                                        <?php echo ($doer_id == $doer['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($doer['username']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $doer_id_err; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="department_id" class="form-label">
                                            <i class="fas fa-building"></i> Department
                                        </label>
                                        <select name="department_id" id="department_id" class="form-control <?php echo (!empty($department_err)) ? 'is-invalid' : ''; ?>" disabled>
                                            <option value="">Select department...</option>
                                            <?php foreach($departments as $dept): ?>
                                                <option value="<?php echo $dept['id']; ?>" <?php echo ($department_id == $dept['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $department_err; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="duration" class="form-label">
                                            <i class="fas fa-clock"></i> Duration
                                        </label>
                                        <select name="duration" id="duration" class="form-control <?php echo (!empty($duration_err)) ? 'is-invalid' : ''; ?>" required>
                                            <option value="">Select duration...</option>
                                            <option value="0" <?php echo ($duration == '0') ? 'selected' : ''; ?>>00:00:00</option>
                                            <option value="15" <?php echo ($duration == '15') ? 'selected' : ''; ?>>00:15:00</option>
                                            <option value="20" <?php echo ($duration == '20') ? 'selected' : ''; ?>>00:20:00</option>
                                            <option value="25" <?php echo ($duration == '25') ? 'selected' : ''; ?>>00:25:00</option>
                                            <option value="30" <?php echo ($duration == '30') ? 'selected' : ''; ?>>00:30:00</option>
                                            <option value="45" <?php echo ($duration == '45') ? 'selected' : ''; ?>>00:45:00</option>
                                            <option value="60" <?php echo ($duration == '60') ? 'selected' : ''; ?>>01:00:00</option>
                                            <option value="90" <?php echo ($duration == '90') ? 'selected' : ''; ?>>01:30:00</option>
                                            <option value="120" <?php echo ($duration == '120') ? 'selected' : ''; ?>>02:00:00</option>
                                            <option value="150" <?php echo ($duration == '150') ? 'selected' : ''; ?>>02:30:00</option>
                                            <option value="180" <?php echo ($duration == '180') ? 'selected' : ''; ?>>03:00:00</option>
                                            <option value="210" <?php echo ($duration == '210') ? 'selected' : ''; ?>>03:30:00</option>
                                            <option value="240" <?php echo ($duration == '240') ? 'selected' : ''; ?>>04:00:00</option>
                                            <option value="270" <?php echo ($duration == '270') ? 'selected' : ''; ?>>04:30:00</option>
                                            <option value="300" <?php echo ($duration == '300') ? 'selected' : ''; ?>>05:00:00</option>
                                            <option value="330" <?php echo ($duration == '330') ? 'selected' : ''; ?>>05:30:00</option>
                                            <option value="360" <?php echo ($duration == '360') ? 'selected' : ''; ?>>06:00:00</option>
                                            <option value="390" <?php echo ($duration == '390') ? 'selected' : ''; ?>>06:30:00</option>
                                            <option value="420" <?php echo ($duration == '420') ? 'selected' : ''; ?>>07:00:00</option>
                                            <option value="450" <?php echo ($duration == '450') ? 'selected' : ''; ?>>07:30:00</option>
                                            <option value="480" <?php echo ($duration == '480') ? 'selected' : ''; ?>>08:00:00</option>
                                            <option value="720" <?php echo ($duration == '720') ? 'selected' : ''; ?>>12:00:00</option>
                                            <option value="1440" <?php echo ($duration == '1440') ? 'selected' : ''; ?>>24:00:00</option>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $duration_err; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Row 2: Task Description (Full Width) -->
                            <div class="row mb-2">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description" class="form-label">
                                            <i class="fas fa-tasks"></i> Task Description
                                        </label>
                                        <textarea name="description" id="description" class="form-control <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>" rows="2" placeholder="Enter detailed task description..." required><?php echo $description; ?></textarea>
                                        <span class="invalid-feedback"><?php echo $description_err; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Row 3: Date, Time, Assigner -->
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="planned_date" class="form-label">
                                            <i class="fas fa-calendar"></i> Planned Date
                                        </label>
                                        <input type="date" name="planned_date" id="planned_date" class="form-control <?php echo (!empty($planned_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $planned_date; ?>" required>
                                        <span class="invalid-feedback"><?php echo $planned_date_err; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="planned_time" class="form-label">
                                            <i class="fas fa-clock"></i> Planned Time
                                        </label>
                                        <select name="planned_time" id="planned_time" class="form-control <?php echo (!empty($planned_time_err)) ? 'is-invalid' : ''; ?>" required>
                                            <option value="">Select time...</option>
                                            <?php 
                                            // Generate time options from 10:00 AM to 11:30 PM with 30 minute intervals
                                            $start = strtotime('10:00');
                                            $end = strtotime('23:30');
                                            $interval = 30 * 60; // 30 minutes in seconds
                                            
                                            for($time = $start; $time <= $end; $time += $interval) {
                                                $value = date('H:i', $time); // Store in 24-hour format for DB
                                                $display = date('h:i A', $time); // Display in 12-hour format
                                                $selected = ($planned_time == $value) ? 'selected' : '';
                                                echo "<option value='{$value}' {$selected}>{$display}</option>";
                                            }
                                            
                                            // Add 11:59 PM as a special option
                                            $value_1159 = '23:59';
                                            $display_1159 = '11:59 PM';
                                            $selected_1159 = ($planned_time == $value_1159) ? 'selected' : '';
                                            echo "<option value='{$value_1159}' {$selected_1159}>{$display_1159}</option>";
                                            ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $planned_time_err; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="assigned_by" class="form-label">
                                            <i class="fas fa-user-check"></i> Assigner
                                        </label>
                                        <select name="assigned_by" id="assigned_by" class="form-control <?php echo (!empty($assigned_by_err)) ? 'is-invalid' : ''; ?>" required>
                                            <option value="">Select assigner...</option>
                                            <option value="self" <?php echo ($assigned_by == 'self') ? 'selected' : ''; ?>>Self</option>
                                            <option value="manager" <?php echo ($assigned_by == 'manager') ? 'selected' : ''; ?>>Manager</option>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $assigned_by_err; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Fourth Row: Manager Selection -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group" id="manager_selection" style="display: <?php echo ($assigned_by == 'manager') ? 'block' : 'none'; ?>">
                                        <label for="manager_id" class="mb-1">Select Assigner</label>
                                        <select name="manager_id" id="manager_id" class="form-control <?php echo (!empty($manager_id_err)) ? 'is-invalid' : ''; ?>">
                                            <option value="">Select Assigner</option>
                                            <?php foreach($managers as $manager): ?>
                                                <option value="<?php echo $manager['id']; ?>" <?php echo ($manager_id == $manager['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($manager['name'] . ' (' . $manager['username'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $manager_id_err; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button Row -->
                            <div class="row mt-1">
                                <div class="col-md-12">
                                    <div class="pt-0 pb-0">
                                        <button type="submit" class="btn btn-primary" style="width: 100%;">Add Task</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
            </div>
        </div>

        <!-- START: Delegation Tasks Table -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Existing Delegation Tasks</h4>
                        <button class="btn btn-sm add-task-toggle-btn" type="button" id="delegationFiltersToggle">
                            <i class="fas fa-chevron-down" id="delegationFiltersToggleIcon"></i> Show Filters
                        </button>
                    </div>
                    <div class="delegation-filters-content collapsed" id="delegationFiltersContent">
                        <div class="card-body border-bottom">
                            <form action="add_task.php" method="GET" class="filter-form">
                                <div class="row g-3">
                                    <!-- Row 1 -->
                                    <div class="col-md-3">
                                        <label for="filter_id" class="form-label small text-muted">
                                            <i class="fas fa-hashtag"></i> Task ID
                                        </label>
                                        <input type="text" class="form-control form-control-sm" id="filter_id" name="filter_id" value="<?php echo htmlspecialchars($filter_id); ?>" placeholder="Search Task ID..." autocomplete="off">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filter_description" class="form-label small text-muted">
                                            <i class="fas fa-tasks"></i> Description
                                        </label>
                                        <input type="text" class="form-control form-control-sm" id="filter_description" name="filter_description" value="<?php echo htmlspecialchars($filter_description); ?>" placeholder="Search Description..." autocomplete="off">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filter_doer_select" class="form-label small text-muted">
                                            <i class="fas fa-user"></i> Doer
                                        </label>
                                        <select class="form-control form-control-sm" id="filter_doer_select" name="filter_doer">
                                            <option value="">All Doers</option>
                                            <?php foreach($distinct_doers_for_filter_dropdown as $doer_name_opt): ?>
                                                <option value="<?php echo htmlspecialchars($doer_name_opt); ?>" <?php echo ($filter_doer == $doer_name_opt) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($doer_name_opt); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
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
                                            <option value="not_done" <?php echo ($filter_status == 'not_done') ? 'selected' : ''; ?>>Not done</option>
                                            <option value="cant_be_done" <?php echo ($filter_status == 'cant_be_done') ? 'selected' : ''; ?>>Can't be done</option>
                                            <option value="shifted" <?php echo ($filter_status == 'shifted') ? 'selected' : ''; ?>>Shifted</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filter_date_from" class="form-label small text-muted">
                                            <i class="fas fa-calendar-alt"></i> Date From
                                        </label>
                                        <input type="date" class="form-control form-control-sm" id="filter_date_from" name="filter_date_from" value="<?php echo htmlspecialchars($filter_date_from ?? ''); ?>" autocomplete="off">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filter_date_to" class="form-label small text-muted">
                                            <i class="fas fa-calendar-check"></i> Date To
                                        </label>
                                        <input type="date" class="form-control form-control-sm" id="filter_date_to" name="filter_date_to" value="<?php echo htmlspecialchars($filter_date_to ?? ''); ?>" autocomplete="off">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">&nbsp;</label>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-filter"></i> Filter
                                            </button>
                                            <a href="add_task.php" class="btn btn-secondary">
                                                <i class="fas fa-undo"></i> Reset
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if(empty($delegation_tasks_list)): ?>
                            <div class="alert alert-info">No delegation tasks found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover sortable-table">
                                    <thead>
                                        <tr>
                                            <th>
                                                <a href="<?php echo buildSortUrl('unique_id', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                                   class="text-white text-decoration-none sortable-header" data-column="unique_id">
                                                    ID <?php echo getSortIcon('unique_id', $sort_column, $sort_direction); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo buildSortUrl('description', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                                   class="text-white text-decoration-none sortable-header" data-column="description">
                                                    Description <?php echo getSortIcon('description', $sort_column, $sort_direction); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo buildSortUrl('planned_date', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                                   class="text-white text-decoration-none sortable-header" data-column="planned_date">
                                                    Planned <?php echo getSortIcon('planned_date', $sort_column, $sort_direction); ?>
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
                                                <a href="<?php echo buildSortUrl('doer_name', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                                   class="text-white text-decoration-none sortable-header" data-column="doer_name">
                                                    Doer <?php echo getSortIcon('doer_name', $sort_column, $sort_direction); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo buildSortUrl('assigned_by', $sort_column, $sort_direction, array_filter($_GET, function($k) { return $k !== 'sort' && $k !== 'dir' && $k !== 'sort_column' && $k !== 'sort_order'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                                                   class="text-white text-decoration-none sortable-header" data-column="assigned_by">
                                                    Assigner <?php echo getSortIcon('assigned_by', $sort_column, $sort_direction); ?>
                                                </a>
                                            </th>
                                            <th class="no-sort">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($delegation_tasks_list as $task): ?>
                                            <?php 
                                                $cell_class = '';
                                                // Determine cell highlighting class based on shifted_count
                                                if (isset($task['shifted_count'])) {
                                                    if ($task['shifted_count'] == 1) {
                                                        $cell_class = 'bg-warning-light';
                                                    } elseif ($task['shifted_count'] >= 2) {
                                                        $cell_class = 'bg-danger-light';
                                                    }
                                                }
                                            ?>
                                            <?php 
                                                // Simplified logic: check if the Delayed column has a value or if planned time is in the past
                                                $delay_output = 'N/A'; // Default
                                                $is_delayed = false;
                                                $current_time = time();
                                                
                                                // For shifted delegation tasks, apply different logic based on shifted_count
                                                if ($task['status'] === 'shifted') {
                                                    // Check shifted_count value
                                                    $shifted_count = isset($task['shifted_count']) ? (int)$task['shifted_count'] : 0;
                                                    
                                                    if ($shifted_count == 1) {
                                                        // Same week shift: Clear delayed time from database and show N/A
                                                        $delay_output = 'N/A';
                                                        $is_delayed = false;
                                                        
                                                        // Clear delay from database if it exists
                                                        if (isset($task['id']) && !empty($task['id'])) {
                                                            $clear_delay_sql = "UPDATE tasks SET is_delayed = 0, delay_duration = NULL WHERE id = ?";
                                                            if ($clear_stmt = mysqli_prepare($conn, $clear_delay_sql)) {
                                                                mysqli_stmt_bind_param($clear_stmt, "i", $task['id']);
                                                                mysqli_stmt_execute($clear_stmt);
                                                                mysqli_stmt_close($clear_stmt);
                                                            }
                                                        }
                                                        
                                                    } elseif ($shifted_count >= 2) {
                                                        // Different week shift: Calculate delayed time using normal logic
                                                        if (isset($task['actual_date']) && isset($task['actual_time']) && 
                                                            isset($task['planned_date']) && isset($task['planned_time'])) {
                                                            
                                                            $planned_timestamp = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
                                                            $actual_timestamp = strtotime($task['actual_date'] . ' ' . $task['actual_time']);
                                                            
                                                            if ($planned_timestamp && $actual_timestamp && $actual_timestamp > $planned_timestamp) {
                                                                // Task was completed late - show delay
                                                                if (isset($task['delay_duration']) && !empty($task['delay_duration'])) {
                                                                    $formatted_delay = formatDelayForDisplay($task['delay_duration']);
                                                                    $full_delay = $formatted_delay;
                                                                    $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                                                    $delay_output = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                                                } else {
                                                                    // Calculate delay if not stored
                                                                    $delay_seconds = $actual_timestamp - $planned_timestamp;
                                                                    $delay_duration_str = formatDelayWithDays($delay_seconds);
                                                                    $formatted_delay = formatDelayForDisplay($delay_duration_str);
                                                                    $full_delay = $formatted_delay;
                                                                    $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                                                    $delay_output = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                                                }
                                                                $is_delayed = true;
                                                            } else {
                                                                // Task was completed on time or early
                                                                $delay_output = '<span class="text-success">On Time</span>';
                                                                $is_delayed = false;
                                                            }
                                                        } else {
                                                            // No actual values - show N/A
                                                            $delay_output = 'N/A';
                                                            $is_delayed = false;
                                                        }
                                                    } else {
                                                        // Default case for shifted_count = 0 or unknown
                                                        $delay_output = 'N/A';
                                                        $is_delayed = false;
                                                    }
                                                }
                                                // DIRECT APPROACH: If the task has a delay value in the Delayed column, mark it as delayed
                                                elseif (!empty($task['delay_duration']) && $task['delay_duration'] != 'N/A' && $task['delay_duration'] != 'On Time') {
                                                    $formatted_delay = formatDelayForDisplay($task['delay_duration']);
                                                    $full_delay = $formatted_delay;
                                                    $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                                    $delay_output = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                                    $is_delayed = true;
                                                } 
                                                // For completed tasks with delay info
                                                elseif ($task['status'] === 'completed') {
                                                    if ($task['is_delayed'] == 1 && !empty($task['delay_duration'])) {
                                                        $formatted_delay = formatDelayForDisplay($task['delay_duration']);
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
                                                    if (!empty($task['planned_date']) && !empty($task['planned_time'])) {
                                                        $planned_ts = strtotime($task['planned_date'] . ' ' . $task['planned_time']);
                                                    }
                                                    
                                                    if ($planned_ts && $current_time > $planned_ts) {
                                                        // Calculate current delay
                                                        $delay_secs = $current_time - $planned_ts;
                                                        $delay_duration_str = formatSecondsToHHMMSS($delay_secs);
                                                        $formatted_delay = formatDelayForDisplay($delay_duration_str);
                                                        $full_delay = $formatted_delay;
                                                        $truncated_delay = strlen($full_delay) > 12 ? substr($full_delay, 0, 12) . '..' : $full_delay;
                                                        $delay_output = '<span class="text-danger delay-hover" data-full-delay="' . htmlspecialchars($full_delay) . '">' . htmlspecialchars($truncated_delay) . '</span>';
                                                        $is_delayed = true;
                                                    }
                                                }
                                                
                                                // ADDITIONAL CHECK: If the Delayed column in the table shows a value, always mark as delayed
                                                if (strpos($delay_output, 'text-danger') !== false) {
                                                    $is_delayed = true;
                                                }
                                                
                                                // Set row class based on delay status
                                                $row_class = $is_delayed ? 'delayed-task-row' : '';
                                            ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td><?php echo htmlspecialchars($task['unique_id']); ?></td>
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
                                                <td class="<?php echo $cell_class; ?>"><?php echo htmlspecialchars(date("d-M-Y", strtotime($task['planned_date']))) . " " . htmlspecialchars(date("h:i A", strtotime($task['planned_time']))); ?></td>
                                                <td><?php echo (!empty($task['actual_date']) && !empty($task['actual_time'])) ? htmlspecialchars(formatDateTime($task['actual_date'], $task['actual_time'])) : 'N/A'; ?></td>
                                                <?php echo get_status_column_cell($task['status'] ?? 'pending'); ?>
                                                <td>
                                                    <?php
                                                    // Use the pre-calculated delay output
                                                    echo $delay_output;
                                                    ?>
                                                </td>
                                                <td><?php
                                                    // Use duration_minutes column if available, otherwise fall back to duration column
                                                    if (!empty($task['duration_minutes'])) {
                                                        // New system: duration stored as minutes
                                                        $duration_minutes = (int)$task['duration_minutes'];
                                                        $duration_display = formatMinutesToHHMMSS($duration_minutes);
                                                    } elseif (!empty($task['duration'])) {
                                                        // Fallback: old system with decimal hours
                                                        $duration_raw = $task['duration'];
                                                        if (is_numeric($duration_raw)) {
                                                            $duration_value = floatval($duration_raw);
                                                            // Check if it's already in minutes (value > 100) or decimal hours
                                                            if ($duration_value > 100) {
                                                                $duration_display = formatMinutesToHHMMSS((int)$duration_value);
                                                            } else {
                                                                $duration_display = formatDecimalDurationToHHMMSS($duration_value);
                                                            }
                                                        } else {
                                                            $duration_display = $duration_raw;
                                                        }
                                                    } else {
                                                        $duration_display = '00:00:00';
                                                    }
                                                    
                                                    echo htmlspecialchars($duration_display);
                                                ?></td>
                                                <td><?php echo htmlspecialchars($task['doer_name']); ?></td>
                                                <td><?php 
                                                    // Use assigned_by_name from the SQL query which handles all cases
                                                    $assigned_by_name = $task['assigned_by_name'] ?? 'Manager';
                                                    echo htmlspecialchars($assigned_by_name);
                                                ?></td>
                                                <td style="min-width: 120px !important; width: 120px !important; max-width: 120px !important; padding-right: 5px !important; padding-left: 5px !important;">
                                                    <select class="form-control form-control-sm task-status-dropdown action-select" data-task-id="<?php echo htmlspecialchars($task['id']); ?>" style="width: auto !important; min-width: 110px !important; max-width: 120px !important; text-overflow: clip !important; overflow: visible !important; white-space: normal !important; height: 30px !important; padding: 2px 5px !important;">
                                                        <?php if(isAdmin()): ?>
                                                        <option value="edit" data-action="edit">Edit</option>
                                                        <?php endif; ?>
                                                        <option value="pending" data-action="status" <?php echo ($task['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="completed" data-action="status" <?php echo ($task['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                        <option value="not_done" data-action="status" <?php echo ($task['status'] === 'not_done') ? 'selected' : ''; ?>>Not done</option>
                                                        <option value="cant_be_done" data-action="status" <?php echo ($task['status'] === 'cant_be_done') ? 'selected' : ''; ?>>Can't be done</option>
                                                        <option value="shifted" data-action="status" <?php echo ($task['status'] === 'shifted') ? 'selected' : ''; ?>>Shifted</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination for Delegation Tasks -->
                            <?php if ($total_pages_delegation > 1): ?>
                            <nav aria-label="Page navigation" class="mt-3">
                                <ul class="pagination pagination-sm justify-content-center">
                                    <?php if ($current_page_delegation > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo buildDelegationQuery(['page_delegation' => $current_page_delegation - 1]); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $current_page_delegation - 2); $i <= min($total_pages_delegation, $current_page_delegation + 2); $i++): ?>
                                        <li class="page-item <?php echo ($i == $current_page_delegation) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo buildDelegationQuery(['page_delegation' => $i]); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($current_page_delegation < $total_pages_delegation): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo buildDelegationQuery(['page_delegation' => $current_page_delegation + 1]); ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>

                            <!-- Results Info -->
                            <div class="text-center text-muted small mt-2">
                                Showing <?php echo count($delegation_tasks_list); ?> of <?php echo $total_items_delegation; ?> tasks
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- END: Delegation Tasks Table -->

    </div>
    
    <!-- Shift Confirmation Modal -->
    <div class="modal fade" id="shiftConfirmationModal" tabindex="-1" role="dialog" aria-labelledby="shiftConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shiftConfirmationModalLabel">Confirm Shift</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to shift this task to a new date and time?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmShiftBtn">Yes, Shift Task</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Time Picker Modal -->
    <div class="modal fade" id="dateTimePickerModal" tabindex="-1" role="dialog" aria-labelledby="dateTimePickerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dateTimePickerModalLabel">Select New Date and Time</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="shiftTaskForm">
                        <input type="hidden" id="shift_task_id" name="task_id" value="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="new_planned_date" class="form-label">
                                        <i class="fas fa-calendar mr-1"></i>New Date
                                    </label>
                                    <input type="date" class="form-control" id="new_planned_date" name="new_planned_date" required min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+2 years')); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="new_planned_time" class="form-label">
                                        <i class="fas fa-clock mr-1"></i>New Time
                                    </label>
                                    <select class="form-control" id="new_planned_time" name="new_planned_time" required>
                                        <option value="">Select Time</option>
    <?php 
                                        // Generate time options from 10:00 AM to 11:30 PM with 30 minute intervals
                                        $start = strtotime('10:00');
                                        $end = strtotime('23:30');
                                        $interval = 30 * 60; // 30 minutes in seconds
                                        
                                        for($time = $start; $time <= $end; $time += $interval) {
                                            $value = date('H:i', $time); // Store in 24-hour format for DB
                                            $display = date('h:i A', $time); // Display in 12-hour format
                                            echo "<option value='{$value}'>{$display}</option>";
                                        }
                                        
                                        // Add 11:59 PM as a special option
                                        $value_1159 = '23:59';
                                        $display_1159 = '11:59 PM';
                                        echo "<option value='{$value_1159}'>{$display_1159}</option>";
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Note:</strong> Shifting to a different week will create a new task and mark the current one as completed.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="submitShiftBtn">
                        <i class="fas fa-calendar-check mr-1"></i>Shift Task
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="bg-dark text-white text-center py-3 mt-5">
        <div class="container">
            <p class="mb-0">Task Management System &copy; <?php echo date('Y'); ?></p>
        </div>
    </footer>
    

    
    <!-- Native HTML5 date input is used for shift task modal - no additional dependencies needed -->
    <script>
    $(document).ready(function() {
        // Form reset is handled by redirect (Post-Redirect-Get pattern)
        // Enable disabled fields before form submission to ensure all values are submitted
        $('#taskForm').on('submit', function() {
            $('#department_id').prop('disabled', false);
            return true;
        });
        
        // Function to refresh doer list
        function refreshDoerList(departmentId = null) {
            // Get current selected doer if any
            const currentDoerId = $('#doer_id').val();
            
            // Prepare AJAX request data
            let ajaxData = {};
            if (departmentId) {
                ajaxData.department_id = departmentId;
            }
            
            // Fetch doers via AJAX
            $.ajax({
                url: '../ajax/get_doers.php',
                type: 'GET',
                data: ajaxData,
                dataType: 'json',
                success: function(data) {
                    // Clear current options except the first one
                    $('#doer_id option:not(:first)').remove();
                    
                    // Add new options based on response
                    if (data.length > 0) {
                        $.each(data, function(index, doer) {
                            const option = $('<option></option>')
                                .val(doer.id)
                                // Prefer username in UI; fallback to name if missing
                                .text(doer.username ? doer.username : doer.name)
                                .attr('data-department', doer.department_id);
                            $('#doer_id').append(option);
                        });
                        
                        // Re-select previously selected doer if it still exists
                        if (currentDoerId) {
                            $('#doer_id').val(currentDoerId);
                            // Trigger change to update department if needed
                            $('#doer_id').trigger('change');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching doers:', error);
                    showDelegationToast('Error fetching doers: ' + error, 'danger');
                }
            });
        }
        
        // Refresh doer list when page loads
        refreshDoerList();
        
        // Override department change handler
        $('#department_id').off('change').on('change', function() {
            const departmentId = $(this).val();
            if(departmentId !== '') {
                refreshDoerList(departmentId);
            } else {
                refreshDoerList(); // Get all doers if no department selected
            }
        });
        
        // Function to show toast messages (copied and adapted from checklist_task.php)
        function showDelegationToast(message, type = 'success') {
            const toastContainer = document.getElementById('toast-container-delegation');
            if (!toastContainer) { // Fallback if container is missing for some reason
                alert(message);
                return;
            }
            const toast = document.createElement('div');
            // Using bootstrap alert classes for styling the toast
            toast.className = `toast-message-delegation alert alert-${type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger')}`;
            toast.role = 'alert';
            toast.textContent = message;
            
            toastContainer.appendChild(toast);
            
            // Auto-dismiss after 7 seconds (like checklist, can be adjusted)
            setTimeout(() => { 
                // Add a fade-out effect if possible, or just remove
                $(toast).fadeOut(500, function() { $(this).remove(); });
            }, 7000);
        }

        // Native HTML5 date inputs - set constraints
        var today = new Date().toISOString().split('T')[0];
        var maxDate = new Date();
        maxDate.setFullYear(maxDate.getFullYear() + 2);
        var maxDateString = maxDate.toISOString().split('T')[0];
        
        // Set minimum date to today for planned_date field (no past dates)
        $('#planned_date').attr('min', today);
        $('#planned_date').attr('max', maxDateString);
        
        // Filter date fields can have past dates (no min restriction)
        $('#filter_date_from, #filter_date_to').attr('max', maxDateString);

        // Show/hide assigner selection based on assigned_by value
        $('#assigned_by').change(function() {
            if($(this).val() == 'manager') {
                $('#manager_selection').show();
            } else {
                $('#manager_selection').hide();
            }
        });
        
        // Auto-select department based on selected doer
        $('#doer_id').change(function() {
            const selectedOption = $(this).find('option:selected');
            const departmentId = selectedOption.data('department');
            
            if(departmentId) {
                $('#department_id').val(departmentId);
            } else {
                $('#department_id').val(''); // Clear if no department associated
            }
            // Always keep the department field disabled
            $('#department_id').prop('disabled', true);
        });
        
        // Trigger change on page load if a doer is already selected to populate department
        if($('#doer_id').val() && $('#doer_id').find('option:selected').data('department')) {
             $('#doer_id').trigger('change');
        }

        // // Initialize Select2 for Filter Dropdowns
        // if ($.fn.select2) {
        //     $('#filter_doer_select').select2({
        //         placeholder: "Filter by Doer",
        //         allowClear: true,
        //         width: '100%'
        //     });
        // }

        // Remove existing delegation task status dropdown handler
        // $(document).off('change', '.task-status-dropdown'); // Good practice to .off() before .on() if re-applying, but here we replace the whole block

        // Store the previous value of the dropdown when it's clicked
        $(document).on('click', '.task-status-dropdown', function() {
            $(this).data('previous-value', $(this).val());
        });

        // OLD HANDLER REMOVED - Now using the new handler below that properly handles both edit and status actions
        
        // Modal JavaScript will be included after this document ready block

        // Auto-dismiss alerts if any are loaded with the page (e.g., from form submission)
        window.setTimeout(function() {
            $(".alert-danger, .alert-success").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 7000); // 7 seconds

        // Ensure delayed task highlighting is applied after page loads
        setTimeout(function() {
            if (typeof highlightDelayedTasks === 'function') {
                highlightDelayedTasks();
            }
        }, 1000);
        
        // Delay hover tooltip fallback
        $('.delay-hover').each(function() {
            // Initialize delay tooltips
        });
        $('.delay-hover').each(function() {
            var fullDelay = $(this).attr('data-full-delay');
            if (fullDelay) {
                // Convert abbreviated format to full format for tooltip
                var fullFormatDelay = convertDelayToFullFormat(fullDelay);
                $(this).attr('data-full-delay', fullFormatDelay);
                $(this).attr('title', fullFormatDelay);
            }
        });
        
        // Convert description data attribute for tooltip
        $('.description-hover').each(function() {
            var fullDescription = $(this).attr('data-full-description');
            if (fullDescription) {
                // Convert description format for tooltip
                var tooltipDescription = convertDescriptionForTooltip(fullDescription);
                $(this).attr('data-full-description', tooltipDescription);
                $(this).attr('title', tooltipDescription);
            }
        });
        
        // Function to convert abbreviated delay format to full format
        function convertDelayToFullFormat(delay) {
            if (!delay || delay === 'N/A' || delay === 'On Time') {
                return delay;
            }
            
            // Convert "4 D 13 h 12 m" to "4 days 13 hours 12 minutes"
            return delay
                .replace(/(\d+)\s*D\b/g, '$1 days')
                .replace(/(\d+)\s*h\b/g, '$1 hours')
                .replace(/(\d+)\s*m\b/g, '$1 minutes');
        }
        
        // Function to convert description format for tooltip
        function convertDescriptionForTooltip(description) {
            if (!description || description === 'N/A') {
                return description;
            }
            
            // Convert line breaks to HTML for proper display in tooltip
            return description.replace(/\n/g, '<br>');
        }

        // AJAX for Delegation Task Status Dropdown change
        $(document).on('change', '.task-status-dropdown', function() {
            var dropdown = $(this);
            var taskId = dropdown.data('task-id');
            var selectedValue = dropdown.val();
            var selectedOption = dropdown.find("option:selected");
            var action = selectedOption.data('action');
            var selectedText = selectedOption.text();

            // Handle Edit action - Admin only
            if (action === 'edit') {
                // Check if user is admin (server-side check)
                <?php if(!isAdmin()): ?>
                alert('Only administrators can edit tasks.');
                // Reset dropdown to current status
                var row = dropdown.closest('tr');
                var currentStatusText = row.find('td').eq(4).find('.status-icon').attr('title') || row.find('td').eq(4).text().trim();
                var currentStatusValue = '';
                switch(currentStatusText.toLowerCase()) {
                    case 'pending': currentStatusValue = 'pending'; break;
                    case 'completed': currentStatusValue = 'completed'; break;
                    case 'not done': currentStatusValue = 'not_done'; break;
                    case 'can\'t be done': 
                    case 'cant be done': currentStatusValue = 'cant_be_done'; break;
                    case 'shifted': currentStatusValue = 'shifted'; break;
                    default: currentStatusValue = 'pending'; break;
                }
                dropdown.val(currentStatusValue);
                return;
                <?php endif; ?>
                
                var row = dropdown.closest('tr');
                var taskId = dropdown.data('task-id');
                
                // Enable inline editing directly
                enableInlineEditing(row, taskId);
                
                // Reset dropdown to current status
                var currentStatusText = row.find('td').eq(4).find('.status-icon').attr('title') || row.find('td').eq(4).text().trim();
                var currentStatusValue = '';
                
                // Map status text to option values
                switch(currentStatusText.toLowerCase()) {
                    case 'pending': currentStatusValue = 'pending'; break;
                    case 'completed': currentStatusValue = 'completed'; break;
                    case 'not done': currentStatusValue = 'not_done'; break;
                    case 'can\'t be done': 
                    case 'cant be done': currentStatusValue = 'cant_be_done'; break;
                    case 'shifted': currentStatusValue = 'shifted'; break;
                    default: currentStatusValue = 'pending'; break;
                }
                
                dropdown.val(currentStatusValue);
                return;
            }

            // Handle Status change (existing logic)
            if (action === 'status') {
                var newStatus = selectedValue;
                
                // If "Shifted" is selected, trigger new auto-scroll modal
                if (newStatus === 'shifted') {
                    // Reset dropdown to previous value
                    dropdown.val(dropdown.data('previous-value') || 'pending');
                    
                    // Store the task ID for later use
                    $('#shift_task_id').val(taskId);
                    
                    // Add the js-shift-task class to trigger auto-scroll functionality
                    dropdown.addClass('js-shift-task');
                    
                    // Trigger the click event to activate auto-scroll and show modal
                    dropdown.trigger('click');
                    return;
                }
                
                if (!confirm('Are you sure you want to change the status of this task to "' + selectedText + '"?')) {
                    // Reset dropdown to current status
                    var row = dropdown.closest('tr');
                    var currentStatusText = row.find('td').eq(4).find('.status-icon').attr('title') || row.find('td').eq(4).text().trim();
                    var currentStatusValue = '';
                    
                    // Map status text to option values
                    switch(currentStatusText.toLowerCase()) {
                        case 'pending': currentStatusValue = 'pending'; break;
                        case 'completed': currentStatusValue = 'completed'; break;
                        case 'not done': currentStatusValue = 'not_done'; break;
                        case 'can\'t be done': 
                        case 'cant be done': currentStatusValue = 'cant_be_done'; break;
                        case 'shifted': currentStatusValue = 'shifted'; break;
                        default: currentStatusValue = 'pending'; break;
                    }
                    
                    dropdown.val(currentStatusValue);
                    return;
                }

                // Send AJAX request to update status
                $.ajax({
                    url: 'action_update_task_status.php',
                    type: 'POST',
                    data: {
                        task_id: taskId,
                        status: newStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            var row = dropdown.closest('tr');
                            if (!row.length) { 
                                alert(response.message + " (UI update for row failed)");
                                location.reload(); 
                                return; 
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

                            // Update Actual Column using server response (Column 3: Actual)
                            var actualCell = row.find('td').eq(3);
                            if (response.updated_actual_display) {
                                actualCell.html('<small>' + response.updated_actual_display + '</small>');
                            } else if (newStatus === 'completed') {
                                actualCell.text('N/A (Updated)'); 
                            } else {
                                actualCell.text('N/A');
                            }

                            // Update Delayed Column using server response (Column 5: Delayed)
                            var delayedCell = row.find('td').eq(5);
                            if (response.updated_delay_display_html) {
                                delayedCell.html('<small class="font-weight-bold">' + response.updated_delay_display_html + '</small>');
                            } else if (newStatus === 'shifted') {
                                delayedCell.html('N/A'); // Clear delays for shifted status
                            } else if (newStatus === 'pending' || newStatus === 'completed') {
                                delayedCell.html('N/A (Updated)'); 
                            } else { 
                                delayedCell.html('N/A');
                            }

                            alert('Task status updated successfully!');
                            // No page reload needed - the UI is updated dynamically 
                        } else {
                            alert('Error: ' + response.message);
                            // Reset dropdown to current status
                            var row = dropdown.closest('tr');
                            var currentStatusText = row.find('td').eq(4).find('.status-icon').attr('title') || row.find('td').eq(4).text().trim();
                            var currentStatusValue = '';
                            
                            // Map status text to option values
                            switch(currentStatusText.toLowerCase()) {
                                case 'pending': currentStatusValue = 'pending'; break;
                                case 'completed': currentStatusValue = 'completed'; break;
                                case 'not done': currentStatusValue = 'not_done'; break;
                                case 'can\'t be done': 
                                case 'cant be done': currentStatusValue = 'cant_be_done'; break;
                                case 'shifted': currentStatusValue = 'shifted'; break;
                                default: currentStatusValue = 'pending'; break;
                            }
                            
                            dropdown.val(currentStatusValue);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('AJAX Error: Could not update task status. ' + error);
                        // Reset dropdown to current status
                        var row = dropdown.closest('tr');
                        var currentStatusText = row.find('td').eq(4).find('.status-icon').attr('title') || row.find('td').eq(4).text().trim();
                        var currentStatusValue = '';
                        
                        // Map status text to option values
                        switch(currentStatusText.toLowerCase()) {
                            case 'pending': currentStatusValue = 'pending'; break;
                            case 'completed': currentStatusValue = 'completed'; break;
                            case 'not done': currentStatusValue = 'not_done'; break;
                            case 'can\'t be done': 
                            case 'cant be done': currentStatusValue = 'cant_be_done'; break;
                            case 'shifted': currentStatusValue = 'shifted'; break;
                            default: currentStatusValue = 'pending'; break;
                        }
                        
                        dropdown.val(currentStatusValue);
                    }
                });
            }
        });

        // Edit permission confirmation removed - now editing directly

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

        // Add Task Form Toggle Functionality
        const addTaskToggleButton = document.getElementById('addTaskToggle');
        const addTaskContent = document.getElementById('addTaskContent');
        const addTaskToggleIcon = document.getElementById('addTaskToggleIcon');
        
        if (addTaskToggleButton && addTaskContent && addTaskToggleIcon) {
            addTaskToggleButton.addEventListener('click', function() {
                if (addTaskContent.classList.contains('collapsed')) {
                    // Show form
                    addTaskContent.classList.remove('collapsed');
                    addTaskToggleIcon.className = 'fas fa-chevron-up';
                    addTaskToggleButton.innerHTML = '<i class="fas fa-chevron-up" id="addTaskToggleIcon"></i> Hide Form';
                } else {
                    // Hide form
                    addTaskContent.classList.add('collapsed');
                    addTaskToggleIcon.className = 'fas fa-chevron-down';
                    addTaskToggleButton.innerHTML = '<i class="fas fa-chevron-down" id="addTaskToggleIcon"></i> Show Form';
                }
            });
        }

        // Delegation Filters Toggle Functionality - Fixed Lag
        const delegationFiltersToggleButton = document.getElementById('delegationFiltersToggle');
        const delegationFiltersContent = document.getElementById('delegationFiltersContent');
        const delegationFiltersToggleIcon = document.getElementById('delegationFiltersToggleIcon');
        
        if (delegationFiltersToggleButton && delegationFiltersContent && delegationFiltersToggleIcon) {
            delegationFiltersToggleButton.addEventListener('click', function() {
                if (delegationFiltersContent.classList.contains('collapsed')) {
                    // Show filters
                    delegationFiltersContent.classList.remove('collapsed');
                    delegationFiltersToggleIcon.className = 'fas fa-chevron-up';
                    delegationFiltersToggleButton.innerHTML = '<i class="fas fa-chevron-up" id="delegationFiltersToggleIcon"></i> Hide Filters';
                } else {
                    // Hide filters
                    delegationFiltersContent.classList.add('collapsed');
                    delegationFiltersToggleIcon.className = 'fas fa-chevron-down';
                    delegationFiltersToggleButton.innerHTML = '<i class="fas fa-chevron-down" id="delegationFiltersToggleIcon"></i> Show Filters';
                }
            });
        }

    });
    </script>

    <script>
    // Inline editing functions
    function enableInlineEditing(row, taskId) {
        // Store original values
        row.data('original-values', {
            taskCode: row.find('td').eq(0).text().trim(),
            taskDescription: row.find('td').eq(1).find('.description-hover').data('full-description') || row.find('td').eq(1).text().trim(),
            doer: row.find('td').eq(7).text().trim(), // Doer (col index 7)
            duration: row.find('td').eq(6).text().trim() // Duration (col index 6)
        });
        
        // Store task ID
        row.data('task-id', taskId);
        
        // Add edit mode class
        row.addClass('edit-mode');
        
        // Make Task Description editable
        var descCell = row.find('td').eq(1);
        // Use data-full-description attribute (clean text) or fallback to trimmed text
        var originalDesc = descCell.find('.description-hover').data('full-description') || descCell.text().trim();
        descCell.html('<textarea class="form-control form-control-sm edit-textarea" rows="1">' + $('<div/>').text(originalDesc).html() + '</textarea>');
        
        // Keep Assigner as read-only (no changes needed)
        // The Assigner field will remain as static text during edit mode
        
        // Keep Planned Date/Time as read-only (no changes needed)
        // The planned date/time field will remain as static text during edit mode
        
        // Make Doer editable (dropdown)
        var doerCell = row.find('td').eq(7); // Doer is col index 7
        var originalDoer = doerCell.text().trim();
        var doerOptions = '<select class="form-control form-control-sm edit-select">';
        doerOptions += '<option value="">Select Doer</option>';
        <?php 
        // Get all active non-client users for the dropdown
        $all_users_for_edit = [];
        $users_query = "SELECT username FROM users 
                        WHERE user_type != 'client' 
                        AND COALESCE(Status, 'Active') = 'Active' 
                        ORDER BY username ASC";
        $users_result = mysqli_query($conn, $users_query);
        if ($users_result) {
            while ($user_row = mysqli_fetch_assoc($users_result)) {
                $all_users_for_edit[] = $user_row['username'];
            }
        }
        ?>
        <?php foreach($all_users_for_edit as $username): ?>
        doerOptions += '<option value="<?php echo htmlspecialchars($username); ?>"' + 
            (originalDoer === '<?php echo htmlspecialchars($username); ?>' ? ' selected' : '') + 
            '><?php echo htmlspecialchars($username); ?></option>';
        <?php endforeach; ?>
        doerOptions += '</select>';
        doerCell.html(doerOptions);
        
        // Make Duration editable (dropdown)
        var durationCell = row.find('td').eq(6); // Duration is col index 6
        var originalDuration = durationCell.text().trim();
        var durationDropdown = '<select class="form-control form-control-sm edit-select">';
        durationDropdown += '<option value="">Select Duration</option>';
        durationDropdown += '<option value="0"' + (originalDuration === '00:00:00' ? ' selected' : '') + '>00:00:00</option>';
        durationDropdown += '<option value="15"' + (originalDuration === '00:15:00' ? ' selected' : '') + '>00:15:00</option>';
        durationDropdown += '<option value="20"' + (originalDuration === '00:20:00' ? ' selected' : '') + '>00:20:00</option>';
        durationDropdown += '<option value="25"' + (originalDuration === '00:25:00' ? ' selected' : '') + '>00:25:00</option>';
        durationDropdown += '<option value="30"' + (originalDuration === '00:30:00' ? ' selected' : '') + '>00:30:00</option>';
        durationDropdown += '<option value="45"' + (originalDuration === '00:45:00' ? ' selected' : '') + '>00:45:00</option>';
        durationDropdown += '<option value="60"' + (originalDuration === '01:00:00' ? ' selected' : '') + '>01:00:00</option>';
        durationDropdown += '<option value="90"' + (originalDuration === '01:30:00' ? ' selected' : '') + '>01:30:00</option>';
        durationDropdown += '<option value="120"' + (originalDuration === '02:00:00' ? ' selected' : '') + '>02:00:00</option>';
        durationDropdown += '<option value="150"' + (originalDuration === '02:30:00' ? ' selected' : '') + '>02:30:00</option>';
        durationDropdown += '<option value="180"' + (originalDuration === '03:00:00' ? ' selected' : '') + '>03:00:00</option>';
        durationDropdown += '<option value="210"' + (originalDuration === '03:30:00' ? ' selected' : '') + '>03:30:00</option>';
        durationDropdown += '<option value="240"' + (originalDuration === '04:00:00' ? ' selected' : '') + '>04:00:00</option>';
        durationDropdown += '<option value="270"' + (originalDuration === '04:30:00' ? ' selected' : '') + '>04:30:00</option>';
        durationDropdown += '<option value="300"' + (originalDuration === '05:00:00' ? ' selected' : '') + '>05:00:00</option>';
        durationDropdown += '<option value="330"' + (originalDuration === '05:30:00' ? ' selected' : '') + '>05:30:00</option>';
        durationDropdown += '<option value="360"' + (originalDuration === '06:00:00' ? ' selected' : '') + '>06:00:00</option>';
        durationDropdown += '<option value="390"' + (originalDuration === '06:30:00' ? ' selected' : '') + '>06:30:00</option>';
        durationDropdown += '<option value="420"' + (originalDuration === '07:00:00' ? ' selected' : '') + '>07:00:00</option>';
        durationDropdown += '<option value="450"' + (originalDuration === '07:30:00' ? ' selected' : '') + '>07:30:00</option>';
        durationDropdown += '<option value="480"' + (originalDuration === '08:00:00' ? ' selected' : '') + '>08:00:00</option>';
        durationDropdown += '<option value="720"' + (originalDuration === '12:00:00' ? ' selected' : '') + '>12:00:00</option>';
        durationDropdown += '<option value="1440"' + (originalDuration === '24:00:00' ? ' selected' : '') + '>24:00:00</option>';
        durationDropdown += '</select>';
        durationCell.html(durationDropdown);
        
        // Replace action dropdown with Save/Cancel buttons
        var actionCell = row.find('td').eq(9); // Actions is col index 9
        actionCell.html('<div class="edit-action-btns">' +
                       '<button class="btn btn-success btn-sm save-edit-btn"><i class="fas fa-save"></i> Save</button>' +
                       '<button class="btn btn-secondary btn-sm cancel-edit-btn"><i class="fas fa-times"></i> Cancel</button>' +
                       '</div>');
    }

    function cancelInlineEditing(row) {
        var originalValues = row.data('original-values');
        
        // Restore original values
        row.find('td').eq(0).text(originalValues.taskCode);
        row.find('td').eq(1).text(originalValues.taskDescription);
        // Assigner field remains unchanged (read-only)
        // Planned date/time field remains unchanged (read-only)
        row.find('td').eq(6).text(originalValues.duration); // Duration (col index 6)
        row.find('td').eq(7).text(originalValues.doer); // Doer (col index 7)
        
        // Restore action dropdown
        var taskId = row.data('task-id');
        var currentStatusText = row.find('td').eq(4).find('.status-icon').attr('title') || row.find('td').eq(4).text().trim();
        var currentStatusValue = '';
        
        // Map status text to option values
        switch(currentStatusText.toLowerCase()) {
            case 'pending': currentStatusValue = 'pending'; break;
            case 'completed': currentStatusValue = 'completed'; break;
            case 'not done': currentStatusValue = 'not_done'; break;
            case 'can\'t be done': 
            case 'cant be done': currentStatusValue = 'cant_be_done'; break;
            case 'shifted': currentStatusValue = 'shifted'; break;
            default: currentStatusValue = 'pending'; break;
        }
        
        var actionDropdown = '<select class="form-control form-control-sm task-status-dropdown action-select" data-task-id="' + taskId + '" style="width: auto !important; min-width: 110px !important; max-width: 120px !important; text-overflow: clip !important; overflow: visible !important; white-space: normal !important; height: 30px !important; padding: 2px 5px !important;">' +
            <?php if(isAdmin()): ?>
            '<option value="edit" data-action="edit">Edit</option>' +
            <?php endif; ?>
            '<option value="pending" data-action="status"' + (currentStatusValue === 'pending' ? ' selected' : '') + '>Pending</option>' +
            '<option value="completed" data-action="status"' + (currentStatusValue === 'completed' ? ' selected' : '') + '>Completed</option>' +
            '<option value="not_done" data-action="status"' + (currentStatusValue === 'not_done' ? ' selected' : '') + '>Not done</option>' +
            '<option value="cant_be_done" data-action="status"' + (currentStatusValue === 'cant_be_done' ? ' selected' : '') + '>Can\'t be done</option>' +
            '<option value="shifted" data-action="status"' + (currentStatusValue === 'shifted' ? ' selected' : '') + '>Shifted</option>' +
            '</select>';
        row.find('td').eq(9).html(actionDropdown); // Updated index for actions column
        
        // Remove edit mode class
        row.removeClass('edit-mode');
    }

    function saveInlineEdit(row, taskId) {
        // Collect edited values
        var editedData = {
            task_id: taskId,
            task_type: 'delegation',
            description: row.find('td').eq(1).find('textarea').val(),
            doer: row.find('td').eq(7).find('select').val(), // Doer (col index 7)
            duration: row.find('td').eq(6).find('select').val() // Duration (col index 6)
        };
        
        // Validate required fields
        if (!editedData.description.trim()) {
            alert('Task description is required!');
            return;
        }
        
        if (!editedData.duration) {
            alert('Duration is required!');
            return;
        }
        
        if (!editedData.doer) {
            alert('Doer is required!');
            return;
        }
        
        // Show loading state
        var saveBtn = row.find('.save-edit-btn');
        var originalText = saveBtn.html();
        saveBtn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
        
        // Duration is already in minutes, format it directly
        var durationFormatted = convertMinutesToHHMMSS(editedData.duration);
        
        // Send AJAX request
        $.ajax({
            url: 'action_update_task_edit.php',
            type: 'POST',
            data: {
                task_id: editedData.task_id,
                task_type: editedData.task_type,
                description: editedData.description,
                doer: editedData.doer,
                duration: durationFormatted
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('Task updated successfully!');
                    
                    // Update the row with new values
                    row.find('td').eq(1).text(editedData.description);
                    // Assigner field remains unchanged (read-only)
                    // Planned date/time field remains unchanged (read-only)
                    row.find('td').eq(6).text(durationFormatted); // Duration (col index 6)
                    row.find('td').eq(7).text(editedData.doer); // Doer (col index 7)
                    
                    // Restore action dropdown
                    var currentStatusText = row.find('td').eq(4).find('.status-icon').attr('title') || row.find('td').eq(4).text().trim();
                    var currentStatusValue = '';
                    
                    // Map status text to option values
                    switch(currentStatusText.toLowerCase()) {
                        case 'pending': currentStatusValue = 'pending'; break;
                        case 'completed': currentStatusValue = 'completed'; break;
                        case 'not done': currentStatusValue = 'not_done'; break;
                        case 'can\'t be done': 
                        case 'cant be done': currentStatusValue = 'cant_be_done'; break;
                        case 'shifted': currentStatusValue = 'shifted'; break;
                        default: currentStatusValue = 'pending'; break;
                    }
                    
                    var actionDropdown = '<select class="form-control form-control-sm task-status-dropdown action-select" data-task-id="' + taskId + '" style="width: auto !important; min-width: 110px !important; max-width: 120px !important; text-overflow: clip !important; overflow: visible !important; white-space: normal !important; height: 30px !important; padding: 2px 5px !important;">' +
                        <?php if(isAdmin()): ?>
                        '<option value="edit" data-action="edit">Edit</option>' +
                        <?php endif; ?>
                        '<option value="pending" data-action="status"' + (currentStatusValue === 'pending' ? ' selected' : '') + '>Pending</option>' +
                        '<option value="completed" data-action="status"' + (currentStatusValue === 'completed' ? ' selected' : '') + '>Completed</option>' +
                        '<option value="not_done" data-action="status"' + (currentStatusValue === 'not_done' ? ' selected' : '') + '>Not done</option>' +
                        '<option value="cant_be_done" data-action="status"' + (currentStatusValue === 'cant_be_done' ? ' selected' : '') + '>Can\'t be done</option>' +
                        '<option value="shifted" data-action="status"' + (currentStatusValue === 'shifted' ? ' selected' : '') + '>Shifted</option>' +
                        '</select>';
                    row.find('td').eq(9).html(actionDropdown); // Updated index for actions column
                    
                    // Remove edit mode class
                    row.removeClass('edit-mode');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX Error: Could not update task. ' + error);
            },
            complete: function() {
                // Restore button state
                saveBtn.html(originalText).prop('disabled', false);
            }
        });
    }

    // Helper functions for date and duration conversion
    function convertDisplayDateToInput(displayDate) {
        // Convert "05-Sep-2025" to "2025-09-05"
        var parts = displayDate.split('-');
        if (parts.length === 3) {
            var day = parts[0];
            var month = parts[1];
            var year = parts[2];
            
            // Convert month name to number
            var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var monthNum = monthNames.indexOf(month) + 1;
            if (monthNum < 10) monthNum = '0' + monthNum;
            if (day.length === 1) day = '0' + day;
            
            return year + '-' + monthNum + '-' + day;
        }
        return displayDate;
    }

    function convertDisplayTimeToInput(displayTime) {
        // Convert "11:30 PM" to "23:30"
        var time = displayTime.trim();
        var isPM = time.indexOf('PM') !== -1;
        var timeOnly = time.replace(/\s*(AM|PM)/i, '');
        var parts = timeOnly.split(':');
        
        if (parts.length === 2) {
            var hours = parseInt(parts[0]);
            var minutes = parts[1];
            
            if (isPM && hours !== 12) {
                hours += 12;
            } else if (!isPM && hours === 12) {
                hours = 0;
            }
            
            return (hours < 10 ? '0' : '') + hours + ':' + minutes;
        }
        return time;
    }

    function convertDisplayDurationToInput(displayDuration) {
        // Convert "01:30:00" to "01:30"
        if (displayDuration && displayDuration.length >= 5) {
            return displayDuration.substring(0, 5);
        }
        return displayDuration;
    }

    function convertInputDateTimeToDisplay(inputDate, inputTime) {
        // Convert "2025-09-05" and "23:30" to "05-Sep-2025 11:30 PM"
        var dateParts = inputDate.split('-');
        var timeParts = inputTime.split(':');
        
        if (dateParts.length === 3 && timeParts.length === 2) {
            var year = dateParts[0];
            var month = parseInt(dateParts[1]) - 1; // JavaScript months are 0-based
            var day = parseInt(dateParts[2]);
            var hours = parseInt(timeParts[0]);
            var minutes = timeParts[1];
            
            // Format date
            var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var formattedDate = (day < 10 ? '0' : '') + day + '-' + monthNames[month] + '-' + year;
            
            // Format time
            var isPM = hours >= 12;
            var displayHours = hours;
            if (hours === 0) {
                displayHours = 12;
            } else if (hours > 12) {
                displayHours = hours - 12;
            }
            var formattedTime = displayHours + ':' + minutes + ' ' + (isPM ? 'PM' : 'AM');
            
            return formattedDate + ' ' + formattedTime;
        }
        return inputDate + ' ' + inputTime;
    }

    function convertInputDurationToDisplay(inputDuration) {
        // Convert "01:30" to "01:30:00"
        if (inputDuration && inputDuration.length === 5) {
            return inputDuration + ':00';
        }
        return inputDuration;
    }

    function convertMinutesToHHMMSS(minutes) {
        // Convert minutes (e.g., 90) to HH:MM:SS format
        var hours = Math.floor(minutes / 60);
        var remainingMinutes = minutes % 60;
        
        // Format with leading zeros
        var hoursStr = hours.toString().padStart(2, '0');
        var minutesStr = remainingMinutes.toString().padStart(2, '0');
        
        return hoursStr + ':' + minutesStr + ':00';
    }
    
    function convertDecimalHoursToHHMMSS(decimalHours) {
        // Convert decimal hours (e.g., "1.5") to HH:MM:SS format
        var hours = Math.floor(decimalHours);
        var minutes = Math.round((decimalHours - hours) * 60);
        
        // Handle case where minutes round to 60
        if (minutes === 60) {
            hours += 1;
            minutes = 0;
        }
        
        // Format with leading zeros
        var hoursStr = hours.toString().padStart(2, '0');
        var minutesStr = minutes.toString().padStart(2, '0');
        
        return hoursStr + ':' + minutesStr + ':00';
    }
    </script>

    <!-- New shift modal JavaScript with auto-scroll functionality -->
    <script>
    $(document).ready(function() {
        // ===== AUTO-SCROLL MODAL FUNCTIONALITY =====
        
        // Utility function to find the nearest scrollable parent
        function getScrollParent(el) {
            let node = el.parentElement;
            while (node && node !== document.body) {
                const s = getComputedStyle(node);
                if ((/auto|scroll/i.test(s.overflowY)) && node.scrollHeight > node.clientHeight) {
                    return node;
                }
                node = node.parentElement;
            }
            return window;
        }
        
        // Utility function for smooth scrolling with Promise support
        function smoothScrollTo(container, top, duration = 400) {
            return new Promise((resolve) => {
                if ('scrollTo' in (container === window ? window : container)) {
                    (container === window ? window : container).scrollTo({ 
                        top: top, 
                        behavior: 'smooth' 
                    });
                    setTimeout(resolve, duration);
                } else {
                    // Fallback for older browsers
                    (container === window ? window : container).scrollTop = top;
                    resolve();
                }
            });
        }
        
        // Delegate click handler for shift task buttons
        $(document).on('click', '.js-shift-task', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const row = btn.closest('tr');
            const container = getScrollParent(row[0]);
            const rect = row[0].getBoundingClientRect();
            
            // Calculate container properties
            const containerTop = container === window ? window.pageYOffset : container.scrollTop;
            const viewportH = container === window ? window.innerHeight : container.clientHeight;
            
            // Calculate target scroll position to center the row
            const targetTop = containerTop + rect.top + (container === window ? 0 : container.scrollTop) - (viewportH / 2 - rect.height / 2);
            const finalTargetTop = Math.max(0, targetTop);
            
            // Smooth scroll to center the row, then show modal
            smoothScrollTo(container, finalTargetTop, 400).then(() => {
                // Show the shift confirmation modal
                $('#shiftConfirmationModal').modal('show');
                
                // Focus first input/select when modal is shown
                $('#shiftConfirmationModal').on('shown.bs.modal', function() {
                    const firstFocusable = $(this).find('input, select, textarea, button, [tabindex]:not([tabindex="-1"])').first();
                    if (firstFocusable.length) {
                        firstFocusable.focus();
                    }
                });
            });
            
            // Remove the class after use to prevent duplicate triggers
            btn.removeClass('js-shift-task');
        });
        
        // ===== END AUTO-SCROLL MODAL FUNCTIONALITY =====
        
        // ===== MODAL EVENT HANDLERS =====
        
        // Fix modal positioning and clickability
        function fixModalClickability() {
            // Ensure modals are moved to body if not already there
            $('.modal').each(function() {
                if ($(this).parent()[0] !== document.body) {
                    $(this).appendTo('body');
                }
            });
            
            // Ensure modal backdrop is properly positioned
            $('.modal-backdrop').css({
                'position': 'fixed',
                'top': '0',
                'left': '0',
                'width': '100vw',
                'height': '100vh',
                'z-index': '1050'
            });
            
            // Force pointer events on modal content
            $('.modal-content').css('pointer-events', 'auto');
            $('.modal-content *').css('pointer-events', 'auto');
        }
        
        // Apply fixes when modals are shown
        $(document).on('show.bs.modal', '.modal', function() {
            fixModalClickability();
        });
        
        // Apply fixes when modals are shown (alternative event)
        $(document).on('shown.bs.modal', '.modal', function() {
            fixModalClickability();
        });
        
        // Handle shift confirmation button click
        $('#confirmShiftBtn').on('click', function() {
            // Hide the confirmation modal
            $('#shiftConfirmationModal').modal('hide');
            
            // Reset the date input to today's date
            const today = new Date();
            const todayString = today.getFullYear() + '-' + 
                               String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                               String(today.getDate()).padStart(2, '0');
            $('#new_planned_date').val(todayString);
            
            // Show the date time picker modal
            $('#dateTimePickerModal').modal('show');
        });
        
        // Handle shift task form submission
        $('#submitShiftBtn').on('click', function() {
            const form = $('#shiftTaskForm');
            const taskId = $('#shift_task_id').val();
            const newDate = $('#new_planned_date').val();
            const newTime = $('#new_planned_time').val();
            
            // Basic validation
            if (!newDate || !newTime) {
                alert('Please select both date and time.');
                return;
            }
            
            // Disable submit button to prevent double submission
            $(this).prop('disabled', true);
            
            // Prepare form data
            const formData = {
                    task_id: taskId,
                new_planned_date: newDate,
                new_planned_time: newTime,
                action: 'shift_task'
            };
            
            // Submit via AJAX
            $.ajax({
                url: 'action_shift_task.php', // Backend handler for task shifting
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        // Close modal immediately after successful AJAX response
                        $('#dateTimePickerModal').modal('hide');
                        
                        // Show success message
                        alert('Alert: ' + (response.message || 'Task shifted successfully!'));
                        
                        // Reload page to reflect changes
                        location.reload();
                    } else {
                        // Close modal on error as well
                        $('#dateTimePickerModal').modal('hide');
                        alert('Error: ' + (response.message || 'Failed to shift task.'));
                    }
                },
                error: function() {
                    // Close modal on AJAX error
                    $('#dateTimePickerModal').modal('hide');
                    alert('An error occurred while shifting the task.');
                },
                complete: function() {
                    // Re-enable submit button
                    $('#submitShiftBtn').prop('disabled', false);
                }
            });
        });
        
        // ===== END MODAL EVENT HANDLERS =====
        
        // Initialize modal fixes on page load
        fixModalClickability();
        
        // Re-apply fixes periodically to ensure they stick
        setInterval(fixModalClickability, 1000);
    });
    </script>

    <!-- Enhanced styling for shift task modal -->
    <style>
    /* Enhanced styling for shift task modal */
    #dateTimePickerModal .modal-dialog {
        max-width: 600px;
    }

    #dateTimePickerModal .modal-body {
        padding: 1.5rem;
    }

    #dateTimePickerModal .form-group {
        margin-bottom: 1.5rem;
    }

    #dateTimePickerModal .form-label {
        font-weight: 600;
        color: var(--dark-text-secondary);
        margin-bottom: 0.5rem;
        display: block;
    }

    #dateTimePickerModal select.form-control {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: initial !important;
        padding: 0.5rem 0.75rem !important;
        font-size: 0.875rem !important;
        line-height: 1.4 !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        border-radius: 0.375rem !important;
        background: linear-gradient(135deg, #1e1e1e, #2a2a2a) !important;
        color: #e0e0e0 !important;
        height: auto !important;
        min-height: auto !important;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
    }

    #dateTimePickerModal select.form-control:focus {
        border-color: var(--brand-primary) !important;
        outline: 0 !important;
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25) !important;
        background: linear-gradient(135deg, #2a2a2a, #333333) !important;
        color: white !important;
    }

    #dateTimePickerModal select.form-control:hover {
        border-color: rgba(255, 255, 255, 0.2) !important;
    }

    #dateTimePickerModal select.form-control option {
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: initial !important;
        padding: 8px 12px !important;
        min-height: 35px !important;
        line-height: 1.4 !important;
        font-size: 0.875rem !important;
        color: #e0e0e0 !important;
        background: #1e1e1e !important;
    }

    #dateTimePickerModal select.form-control option:hover {
        background: #2a2a2a !important;
    }

    #dateTimePickerModal select.form-control option:checked {
        background: var(--brand-primary) !important;
        color: white !important;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        #dateTimePickerModal .modal-dialog {
            max-width: 95%;
            margin: 1rem auto;
        }
        #dateTimePickerModal .row {
            margin: 0;
        }
        #dateTimePickerModal .col-md-6 {
            padding: 0;
            margin-bottom: 1rem;
        }
        #dateTimePickerModal .modal-body {
            padding: 1rem;
        }
    }

    #dateTimePickerModal .row {
        margin-left: 0;
        margin-right: 0;
    }

    #dateTimePickerModal .col-md-6 {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }

    #dateTimePickerModal .alert {
        border-radius: 0.5rem;
        border: 1px solid #bee5eb;
        background-color: #d1ecf1;
        color: #0c5460;
    }

    #dateTimePickerModal .alert i {
        color: #0c5460;
    }

    /* Modal Fixes - Ensure proper z-index and clickability */
    .modal {
        z-index: 1055 !important;
    }

    .modal.show {
        z-index: 1055 !important;
    }

    .modal-dialog {
        z-index: 1056 !important;
    }

    .modal-content {
        z-index: 1057 !important;
        pointer-events: auto !important;
        position: relative !important;
    }

    .modal-backdrop {
        z-index: 1050 !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
    }

    .modal-backdrop.show {
        z-index: 1050 !important;
    }

    /* Ensure modal is clickable and not blocked */
    .modal.fade .modal-dialog {
        pointer-events: auto !important;
    }

    .modal.show .modal-dialog {
        pointer-events: auto !important;
    }

    /* Fix for any potential overlay issues */
    .modal-content * {
        pointer-events: auto !important;
    }

    /* Ensure buttons and inputs are clickable */
    .modal-content button,
    .modal-content input,
    .modal-content select,
    .modal-content textarea {
        pointer-events: auto !important;
        z-index: 1058 !important;
        position: relative !important;
    }
    </style>
    
    <!-- Debug code removed for production -->

<!-- Edit Permission Modal removed - now using simple browser confirm popup -->

        </div> <!-- End content-area -->
    </div> <!-- End add-task-page -->
