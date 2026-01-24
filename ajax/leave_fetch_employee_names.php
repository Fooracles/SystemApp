<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

// Get user information from request
$user_role = $_GET['user_role'] ?? $_SESSION['user_type'] ?? 'doer';
$user_name = $_GET['user_name'] ?? $_SESSION['username'] ?? '';
$user_id = $_SESSION['id'] ?? null;

try {
    // Build WHERE conditions for getting unique employee names
    $where_conditions = [];
    $params = [];
    $types = '';
    
    // For doer users, only show their own name
    if ($user_role === 'doer' && !empty($user_name)) {
        $where_conditions[] = "employee_name = ?";
        $params[] = $user_name;
        $types .= 's';
    }
    // For admin and manager users, no filtering - they see all names
    // (Managers see all leave requests in Total table like admins, as per leave_request.php line 99-100)
    
    // Build WHERE clause
    $sql_where = "";
    if (!empty($where_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $where_conditions);
    }
    
    // Get all unique employee names from Leave_request table
    $sql = "SELECT DISTINCT employee_name 
            FROM Leave_request" . $sql_where . " 
            ORDER BY employee_name ASC";
    
    $employee_names = [];
    if ($stmt = mysqli_prepare($conn, $sql)) {
        if (!empty($types) && !empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                if (!empty($row['employee_name']) && trim($row['employee_name'])) {
                    $employee_names[] = trim($row['employee_name']);
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Remove duplicates and sort
    $employee_names = array_unique($employee_names);
    sort($employee_names);
    
    echo json_encode([
        'success' => true,
        'data' => $employee_names,
        'count' => count($employee_names)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching employee names: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch employee names: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>

