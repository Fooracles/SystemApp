<?php
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Set header for JSON response
header('Content-Type: application/json');

// Check if this is an AJAX request for updating delay status
if(isset($_POST['update_delays'])) {
    // Update all task delays
    updateAllTasksDelayStatus($conn);
    
    // Get all delayed pending tasks
    $delayed_tasks = array();
    $sql = "SELECT id, unique_id, status, is_delayed, delay_duration 
            FROM tasks 
            WHERE (is_delayed = 1 OR status = 'pending')";
    
    $result = mysqli_query($conn, $sql);
    if($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $delayed_tasks[] = $row;
        }
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'message' => 'Delays updated successfully',
        'delayed_tasks' => $delayed_tasks
    ]);
    exit;
}

// If no valid action is provided
echo json_encode([
    'success' => false,
    'message' => 'Invalid request'
]);
?> 