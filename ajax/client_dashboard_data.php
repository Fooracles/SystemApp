<?php
// Suppress all output except JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_clean();

require_once "../includes/config.php";
require_once "../includes/functions.php";

session_start();

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in and is a client
if (!isLoggedIn() || !isClient()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

// Get date range parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

try {
    // Validate user ID
    if (empty($current_user_id) || !is_numeric($current_user_id)) {
        throw new Exception('Invalid user ID');
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
        
        // Query tasks from client_taskflow table
        foreach ($task_statuses as $status => $key) {
            $query = "SELECT COUNT(*) as count FROM client_taskflow 
                      WHERE type = 'Task' AND status = ? AND created_by = ?" . $date_filter;
            
            $stmt = mysqli_prepare($conn, $query);
            if ($stmt) {
                if ($date_from && $date_to) {
                    mysqli_stmt_bind_param($stmt, "siss", $status, $current_user_id, $date_from, $date_to);
                } else {
                    mysqli_stmt_bind_param($stmt, "si", $status, $current_user_id);
                }
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($row = mysqli_fetch_assoc($result)) {
                    $response['tasks'][$key] = (int)$row['count'];
                }
                mysqli_stmt_close($stmt);
            }
        }
        
        // Query tickets - Ticket statuses: Raised, In Progress, Resolved
        $ticket_statuses = [
            'Raised' => 'raised',
            'In Progress' => 'in_progress',
            'Resolved' => 'resolved'
        ];
        
        foreach ($ticket_statuses as $status => $key) {
            $query = "SELECT COUNT(*) as count FROM client_taskflow 
                      WHERE type = 'Ticket' AND status = ? AND created_by = ?" . $date_filter;
            
            $stmt = mysqli_prepare($conn, $query);
            if ($stmt) {
                if ($date_from && $date_to) {
                    mysqli_stmt_bind_param($stmt, "siss", $status, $current_user_id, $date_from, $date_to);
                } else {
                    mysqli_stmt_bind_param($stmt, "si", $status, $current_user_id);
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
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

