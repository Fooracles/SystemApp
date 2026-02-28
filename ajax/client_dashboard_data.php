<?php
// Start output buffering first to catch any stray output
if (ob_get_level() == 0) {
    ob_start();
} else {
    ob_clean();
}

// Include core files (functions.php auto-starts session)
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Re-suppress error display AFTER config loads (error_handler.php overrides it in dev)
ini_set('display_errors', 0);

// Set proper headers for JSON response
if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
}

// Check if user is logged in and is a client
if (!isLoggedIn() || !isClient()) {
    jsonError('Unauthorized', 401);
}

$current_user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

// Get date range parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

try {
    // Validate user ID
    if (empty($current_user_id)) {
        throw new Exception('Invalid user ID');
    }

    // Resolve account head: same-account = all client users under the same account (do not mix other client accounts).
    // Client sub-users have manager_id = account head user id; account head has manager_id = manager (or self).
    $account_head_id = $current_user_id;
    $manager_id_stmt = mysqli_prepare($conn, "SELECT manager_id FROM users WHERE id = ? AND user_type = 'client'");
    if ($manager_id_stmt) {
        mysqli_stmt_bind_param($manager_id_stmt, 'i', $current_user_id);
        mysqli_stmt_execute($manager_id_stmt);
        $manager_id_result = mysqli_stmt_get_result($manager_id_stmt);
        if ($row = mysqli_fetch_assoc($manager_id_result)) {
            $my_manager_id = $row['manager_id'];
            if (!empty($my_manager_id) && is_numeric($my_manager_id)) {
                // Check if manager_id points to another client (account head)
                $is_account_head_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? AND user_type = 'client'");
                if ($is_account_head_stmt) {
                    mysqli_stmt_bind_param($is_account_head_stmt, 'i', $my_manager_id);
                    mysqli_stmt_execute($is_account_head_stmt);
                    $is_account_head_result = mysqli_stmt_get_result($is_account_head_stmt);
                    if (mysqli_num_rows($is_account_head_result) > 0) {
                        $account_head_id = (int)$my_manager_id;
                    }
                    mysqli_stmt_close($is_account_head_stmt);
                }
            }
        }
        mysqli_stmt_close($manager_id_stmt);
    }
    
    // Build date filter for WHERE clause
    $date_filter = "";
    $params = [];
    $types = "";
    
    if ($date_from && $date_to) {
        $date_filter = " AND DATE(created_at) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
    }
    
    // Initialize response
    $response = [
        'success' => true,
        'tasks' => [
            'assigned' => 0,
            'working' => 0,
            'review' => 0,
            'revise' => 0,
            'approved' => 0,
            'complete' => 0
        ],
        'tickets' => [
            'raised' => 0,
            'in_progress' => 0,
            'resolved' => 0
        ]
    ];
    
    // Check if client_taskflow table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'client_taskflow'");
    $table_exists = ($table_check && mysqli_num_rows($table_check) > 0);
    
    if ($table_exists) {
        // Task statuses: Assigned, Working, Review, Revise, Approved, Completed
        // Note: Database uses "Completed" but dashboard expects "complete" key
        $task_statuses = [
            'Assigned' => 'assigned',
            'Working' => 'working',
            'Review' => 'review',
            'Revise' => 'revise',
            'Approved' => 'approved',
            'Completed' => 'complete'  // Map "Completed" status to "complete" key
        ];
        
        // Subquery: user IDs in the same client account (do not mix other accounts)
        $account_user_subquery = "SELECT id FROM users WHERE user_type = 'client' AND (id = ? OR manager_id = ?)";

        // Query tasks from client_taskflow table (all users under same account)
        foreach ($task_statuses as $status => $key) {
            $query = "SELECT COUNT(*) as count FROM client_taskflow 
                      WHERE type = 'Task' AND status = ? AND created_by IN (" . $account_user_subquery . ")" . $date_filter;

            $stmt = mysqli_prepare($conn, $query);
            if ($stmt) {
                if ($date_from && $date_to) {
                    mysqli_stmt_bind_param($stmt, "siiss", $status, $account_head_id, $account_head_id, $date_from, $date_to);
                } else {
                    mysqli_stmt_bind_param($stmt, "sii", $status, $account_head_id, $account_head_id);
                }
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($row = mysqli_fetch_assoc($result)) {
                    $response['tasks'][$key] = (int)$row['count'];
                }
                mysqli_stmt_close($stmt);
            }
        }

        // Query tickets - Ticket statuses: Raised, In Progress, Resolved (all users under same account)
        $ticket_statuses = [
            'Raised' => 'raised',
            'In Progress' => 'in_progress',
            'Resolved' => 'resolved'
        ];

        foreach ($ticket_statuses as $status => $key) {
            $query = "SELECT COUNT(*) as count FROM client_taskflow 
                      WHERE type = 'Ticket' AND status = ? AND created_by IN (" . $account_user_subquery . ")" . $date_filter;

            $stmt = mysqli_prepare($conn, $query);
            if ($stmt) {
                if ($date_from && $date_to) {
                    mysqli_stmt_bind_param($stmt, "siiss", $status, $account_head_id, $account_head_id, $date_from, $date_to);
                } else {
                    mysqli_stmt_bind_param($stmt, "sii", $status, $account_head_id, $account_head_id);
                }
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($row = mysqli_fetch_assoc($result)) {
                    $response['tickets'][$key] = (int)$row['count'];
                }
                mysqli_stmt_close($stmt);
            }
        }
    } else {
        // Table doesn't exist - return zeros
        error_log("client_taskflow table does not exist");
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    handleException($e, 'client_dashboard_data');
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
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
