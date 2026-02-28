<?php
// Add a Google Sheet to the database
$page_title = "Add Sheet";
require_once '../includes/header.php';

// Initialize variables
$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sheet_id = trim($_POST['sheet_id'] ?? '');
    $tab_name = trim($_POST['tab_name'] ?? '');
    $label = trim($_POST['label'] ?? '');
    
    // Validate input
    if (empty($sheet_id) || empty($tab_name) || empty($label)) {
        $error_message = "All fields are required.";
    } else {
        // Check if sheet already exists
        $check_stmt = $conn->prepare("SELECT id FROM fms_sheets WHERE sheet_id = ? AND tab_name = ?");
        $check_stmt->bind_param("ss", $sheet_id, $tab_name);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "Sheet already exists.";
        } else {
            // Try to fetch data from the sheet to validate it exists and is accessible
            try {
                $sheet_data = fetchSheetData($sheet_id, $tab_name, '');
                
                // Add to database
                $stmt = $conn->prepare("INSERT INTO fms_sheets (sheet_id, tab_name, label) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $sheet_id, $tab_name, $label);
                
                if ($stmt->execute()) {
                    $success_message = "Sheet added successfully. Data will be updated shortly.";
                    
                    // Initialize sync metadata
                    $sync_stmt = $conn->prepare("INSERT INTO fms_sheet_sync (sheet_id) VALUES (?)");
                    $sync_stmt->bind_param("s", $sheet_id);
                    $sync_stmt->execute();
                    
                    // Process and insert tasks
                    if (count($sheet_data) > 1) {
                        // Remove header row
                        array_shift($sheet_data);
                        
                        // Insert tasks
                        $insert_count = 0;
                        $insert_stmt = $conn->prepare("
                            INSERT INTO fms_tasks 
                            (sheet_id, unique_key, step_name, client_name, planned, actual, status, duration, doer_name, department, task_link, sheet_label, step_code) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        foreach ($sheet_data as $row) {
                            // Ensure we have at least 12 columns (added client_name from column L)
                            $row = array_pad(array_slice($row, 0, 12), 12, '');
                            
                            // Skip empty rows
                            if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
                                continue;
                            }
                            
                            $unique_key = $row[0] ?? '';
                            $step_name = $row[1] ?? '';
                            $client_name = $row[11] ?? ''; // Column L (index 11) for client name
                            $planned = $row[2] ?? '';
                            $actual = $row[3] ?? '';
                            $status = $row[4] ?? '';
                            $duration = $row[6] ?? ''; // skip index 5
                            $doer_name = $row[7] ?? '';
                            $department = $row[9] ?? '';
                            $task_link = $row[8] ?? '';
                            $step_code = $row[10] ?? '';
                            
                            $insert_stmt->bind_param("sssssssssssss",
                                $sheet_id,
                                $unique_key,
                                $step_name,
                                $client_name,
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
                            
                            if ($insert_stmt->execute()) {
                                $insert_count++;
                            }
                        }
                        
                        // Update sync metadata
                        $now = date('Y-m-d H:i:s');
                        $sync_stmt = $conn->prepare("
                            INSERT INTO fms_sheet_sync (sheet_id, last_synced) 
                            VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE last_synced = ?
                        ");
                        $sync_stmt->bind_param("sss", $sheet_id, $now, $now);
                        $sync_stmt->execute();
                        
                        $success_message = "Sheet added successfully. Imported {$insert_count} tasks.";
                    } else {
                        $success_message = "Sheet added successfully, but no data was found.";
                    }
                } else {
                    $error_message = "Failed to add sheet to database.";
                }
            } catch (Exception $e) {
                $error_message = "Error accessing Google Sheet: " . $e->getMessage();
            }
        }
    }
}

// Header already included at the top
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-plus"></i> Add New Google Sheet
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="sheet_id">Google Sheet ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sheet_id" name="sheet_id" 
                                   value="<?php echo htmlspecialchars($_POST['sheet_id'] ?? ''); ?>" 
                                   placeholder="Enter the Google Sheet ID from the URL" required>
                            <small class="form-text text-muted">
                                The Sheet ID is the long string in the Google Sheets URL between /d/ and /edit
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="tab_name">Tab Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tab_name" name="tab_name" 
                                   value="<?php echo htmlspecialchars($_POST['tab_name'] ?? ''); ?>" 
                                   placeholder="Enter the tab name (e.g., Sheet1, Data, etc.)" required>
                            <small class="form-text text-muted">
                                The name of the specific tab/sheet within the Google Spreadsheet
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="label">Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="label" name="label" 
                                   value="<?php echo htmlspecialchars($_POST['label'] ?? ''); ?>" 
                                   placeholder="Enter a descriptive label for this sheet" required>
                            <small class="form-text text-muted">
                                A friendly name to identify this sheet in the system
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Instructions:</h6>
                                <ol class="mb-0">
                                    <li>Open your Google Sheet</li>
                                    <li>Copy the Sheet ID from the URL (the long string between /d/ and /edit)</li>
                                    <li>Enter the exact tab name as it appears in the sheet</li>
                                    <li>Give it a descriptive label for easy identification</li>
                                </ol>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Sheet
                            </button>
                            <a href="manage_sheets.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Manage Sheets
                            </a>
                            <a href="fms_task.php" class="btn btn-info">
                                <i class="fas fa-tasks"></i> Back to FMS Tasks
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include the universal footer
require_once "../includes/footer.php";
?>
