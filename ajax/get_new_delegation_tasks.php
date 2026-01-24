<?php
// ajax/get_new_delegation_tasks.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

session_start();

$response = [
    'status' => 'error',
    'message' => 'Invalid request.'
];

try {
    if (!isLoggedIn()) {
        throw new Exception('Not authenticated');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid method');
    }

    $current_doer_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    if ($current_doer_id <= 0) {
        throw new Exception('Invalid user');
    }

    $since = isset($_GET['since']) ? trim($_GET['since']) : '';
    // Basic validation for datetime format Y-m-d H:i:s
    if ($since !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
        throw new Exception('Invalid timestamp format');
    }

    // Add manager filtering for delegation tasks
    $filter_conditions = "";
    $filter_params = [$current_doer_id];
    $filter_param_types = "i";
    
    if (isManager() && !isAdmin()) {
        $current_manager_id = $_SESSION["id"];
        $current_manager_name = $_SESSION["name"] ?? $_SESSION["username"];
        $filter_conditions = " AND (t.manager_id = ? OR t.created_by = ? OR t.doer_manager_id = ? OR COALESCE(t.doer_name, u.username, 'N/A') IN (SELECT username FROM users WHERE manager = ?))";
        $filter_params[] = $current_manager_id;
        $filter_params[] = $current_manager_id;
        $filter_params[] = $current_manager_id;
        $filter_params[] = $current_manager_name;
        $filter_param_types .= "iiis";
    }

    $query = "SELECT 
                t.id as task_id,
                t.unique_id,
                t.description,
                t.planned_date,
                t.planned_time,
                t.actual_date,
                t.actual_time,
                t.status,
                t.is_delayed,
                t.delay_duration,
                t.duration,
                t.shifted_count,
                t.created_at,
                COALESCE(t.doer_name, u.username, 'N/A') as doer_name,
                d.name as department_name,
                'delegation' as task_type
            FROM tasks t
            LEFT JOIN users u ON t.doer_id = u.id
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE t.doer_id = ?
              AND (t.status = 'pending' OR (t.status = 'shifted' AND (t.actual_date IS NULL OR t.actual_time IS NULL)))
              " . $filter_conditions . "
              " . ($since !== '' ? "AND t.created_at > ?" : '') . "
            ORDER BY t.created_at DESC
            LIMIT 10"; // limit to a few newest

    if ($stmt = mysqli_prepare($conn, $query)) {
        if ($since !== '') {
            $filter_params[] = $since;
            $filter_param_types .= "s";
        }
        mysqli_stmt_bind_param($stmt, $filter_param_types, ...$filter_params);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('DB error: ' . mysqli_stmt_error($stmt));
        }
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        $response['status'] = 'success';
        $response['message'] = 'ok';
        $response['tasks'] = $rows;
    } else {
        throw new Exception('DB prepare error: ' . mysqli_error($conn));
    }
} catch (Throwable $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;


