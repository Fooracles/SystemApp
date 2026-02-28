<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

// Helper function to log activity
function log_activity($msg) {
    file_put_contents(__DIR__ . '/../log.txt', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

if (!isLoggedIn()) {
    log_activity('Unauthorized access attempt to FMS sheet handler.');
    jsonError('Not authenticated.', 401);
}

// CSRF protection for POST requests
csrfProtect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sheet_id'], $_POST['tab_name'], $_POST['sheet_label'])) {
    $sheet_id = trim($_POST['sheet_id']);
    $tab_name = trim($_POST['tab_name']);
    $label = trim($_POST['sheet_label']);
    log_activity("Add sheet request: sheet_id=$sheet_id, tab_name=$tab_name, label=$label");
    if (!$sheet_id || !$tab_name || !$label) {
        log_activity('Missing required fields in add sheet request.');
        jsonError('Missing required fields.', 400);
    }
    // Check if sheet already exists
    $check_stmt = $conn->prepare("SELECT id FROM fms_sheets WHERE sheet_id = ? AND tab_name = ?");
    if ($check_stmt === false) {
        log_activity('Prepare Error (check): ' . $conn->error);
        die(json_encode(['status'=>'error','message'=>'Prepare Error (check): '.$conn->error]));
    }
    $check_stmt->bind_param("ss", $sheet_id, $tab_name);
    if (!$check_stmt->execute()) {
        log_activity('Execute Error (check): ' . $check_stmt->error);
        die(json_encode(['status'=>'error','message'=>'Execute Error (check): '.$check_stmt->error]));
    }
    $check_result = $check_stmt->get_result();
    log_activity("Ran query: SELECT id FROM fms_sheets WHERE sheet_id = $sheet_id AND tab_name = $tab_name");
    if ($check_result->num_rows == 0) {
        // Insert new sheet into fms_sheets
        $stmt = $conn->prepare("INSERT INTO fms_sheets (sheet_id, tab_name, label) VALUES (?, ?, ?)");
        if ($stmt === false) {
            log_activity('Prepare Error (insert): ' . $conn->error);
            die(json_encode(['status'=>'error','message'=>'Prepare Error (insert): '.$conn->error]));
        }
        $stmt->bind_param("sss", $sheet_id, $tab_name, $label);
        if (!$stmt->execute()) {
            log_activity('Execute Error (insert): ' . $stmt->error);
            die(json_encode(['status'=>'error','message'=>'Execute Error (insert): '.$stmt->error]));
        }
        log_activity("Inserted new sheet: $sheet_id, $tab_name, $label");
    }
    // Always fetch and store FMS tasks in DB
    require_once '../pages/fms_task.php'; // for fetchCsvData
    
    // Use Google Sheets API instead of CSV download
    try {
        log_activity("Fetching sheet data via API for: $sheet_id, tab: $tab_name");
        $sheet_data = fetchSheetData($sheet_id, $tab_name, '');
        
        // Process the data and insert into database
        $new_tasks = [];
        
        // Remove old tasks for this sheet
        $del_stmt = $conn->prepare("DELETE FROM fms_tasks WHERE sheet_id = ?");
        $del_stmt->bind_param("s", $sheet_id);
        $del_stmt->execute();
        log_activity("Deleted old tasks for sheet: $sheet_id");
        
        if (count($sheet_data) > 1) {
            array_shift($sheet_data); // Remove header row
            
            foreach ($sheet_data as $row) {
                // Ensure we have at least 11 columns, pad with empty strings if needed
                $row = array_pad(array_slice($row, 0, 11), 11, '');
                
                // Skip empty rows
                if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
                    continue;
                }
                
                $ins_stmt = $conn->prepare("INSERT INTO fms_tasks (sheet_id, unique_key, step_name, planned, actual, status, duration, doer_name, department, task_link, sheet_label, step_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                // Map columns: 0=unique_key, 1=step_name, 2=planned, 3=actual, 4=status, 
                // 5=time_delay(skip), 6=duration, 7=doer_name, 8=task_link, 9=department, 10=step_code
                $unique_key = $row[0] ?? '';
                $step_name = $row[1] ?? '';
                $planned = $row[2] ?? '';
                $actual = $row[3] ?? '';
                $status = $row[4] ?? '';
                $duration = $row[6] ?? ''; // skip index 5
                $doer_name = $row[7] ?? '';
                $department = $row[9] ?? '';
                $task_link = $row[8] ?? '';
                $step_code = $row[10] ?? '';
                
                $ins_stmt->bind_param("ssssssssssss",
                    $sheet_id,
                    $unique_key,
                    $step_name,
                    $planned,
                    $actual,
                    $status,
                    $duration,
                    $doer_name,
                    $department,
                    $task_link,
                    $label,
                    $step_code
                );
                
                if ($ins_stmt->execute()) {
                    log_activity("Inserted FMS task: " . json_encode($row));
                    $new_tasks[] = [
                        'unique_key' => $unique_key,
                        'step_name' => $step_name,
                        'planned' => $planned,
                        'actual' => $actual,
                        'status' => $status,
                        'duration' => $duration,
                        'doer_name' => $doer_name,
                        'department' => $department,
                        'task_link' => $task_link,
                        'sheet_label' => $label,
                        'step_code' => $step_code
                    ];
                }
            }
        }
        
        // Initialize sync metadata
        $sync_stmt = $conn->prepare("INSERT INTO fms_sheet_sync (sheet_id) VALUES (?) ON DUPLICATE KEY UPDATE last_synced = NOW()");
        if ($sync_stmt === false) {
            log_activity('Prepare Error (sync): ' . $conn->error);
            die(json_encode(['status'=>'error','message'=>'Prepare Error (sync): '.$conn->error]));
        }
        $sync_stmt->bind_param("s", $sheet_id);
        if (!$sync_stmt->execute()) {
            log_activity('Execute Error (sync): ' . $sync_stmt->error);
            die(json_encode(['status'=>'error','message'=>'Execute Error (sync): '.$sync_stmt->error]));
        }
        log_activity("Initialized sync data for sheet: $sheet_id");
        
        log_activity("Successfully fetched and processed " . count($new_tasks) . " tasks via API");
        echo json_encode(['status'=>'success', 'tasks' => $new_tasks]);
    } catch (Exception $e) {
        log_activity("Error fetching sheet data via API: " . $e->getMessage());
        handleException($e, 'fms_sheet_handler');
    }
    
    exit;
}
log_activity('Invalid request to FMS sheet handler.');
echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
