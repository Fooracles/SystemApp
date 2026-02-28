<?php
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Set appropriate content type header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    // Return empty array with error message
    echo json_encode(array('error' => 'Not authenticated'));
    exit;
}

// Prepare SQL to get users based on role and filters
$sql = "SELECT u.id, u.username, u.name, u.department_id, d.name as department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id";

$params = [];
$types = "";

// Add filtering based on user role - Manager and Admin can see all users
if (isManager() || isAdmin()) {
    // For Manager and Admin users, show all active users (admin, manager, doer)
    // Always exclude client users
    // Exclude admin users only if current user is NOT Admin (i.e., if Manager)
    $sql .= " WHERE u.Status = 'Active' AND u.user_type != 'client'";
    
    // If current user is Manager (not Admin), exclude admin users
    if (isManager() && !isAdmin()) {
        $sql .= " AND u.user_type != 'admin'";
    }
} else {
    // For other users, show empty result (should not reach here due to access control)
    $sql .= " WHERE 1=0";
}

// Check if department_id was provided
if (isset($_GET['department_id']) && !empty($_GET['department_id']) && is_numeric($_GET['department_id'])) {
    $sql .= " AND u.department_id = ?";
    $params[] = $_GET['department_id'];
    $types .= "i";
}

// Add ordering (default to username if present)
$sql .= " ORDER BY u.username";

$doers = array();

if ($stmt = mysqli_prepare($conn, $sql)) {
    // Bind parameters if any
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    // Execute the statement
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        // Fetch doers
        while ($row = mysqli_fetch_assoc($result)) {
            $doers[] = array(
                'id' => $row['id'],
                'username' => isset($row['username']) ? $row['username'] : null,
                'name' => $row['name'],
                'department_id' => $row['department_id'],
                'department_name' => $row['department_name']
            );
        }
    } else {
        echo json_encode(array('error' => 'Database error: ' . mysqli_stmt_error($stmt)));
        exit;
    }
    
    // Close statement
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(array('error' => 'Database error: ' . mysqli_error($conn)));
    exit;
}

// Output the JSON data
echo json_encode($doers);

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
