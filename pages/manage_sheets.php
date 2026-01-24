<?php
// Increase execution time limit for Google Sheets operations
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', 300);

// Buffer output to allow header() redirects later in the script
ob_start();
$page_title = "Manage Google Sheets";
require_once "../includes/header.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Access Control: Admin Only
if(!isAdmin()) {
    if (isManager()) {
        header("location: manager_dashboard.php");
    } elseif (isDoer()) {
        header("location: doer_dashboard.php");
    } else {
        header("location: ../login.php");
    }
    exit;
}

// Handle refresh sheet data
if (isset($_GET['refresh_sheet'])) {
    $sheet_id = $_GET['refresh_sheet'];
    
    // Get the sheet details
    $sheet_query = "SELECT * FROM fms_sheets WHERE id = ?";
    $stmt = $conn->prepare($sheet_query);
    $stmt->bind_param("i", $sheet_id);
    $stmt->execute();
    $sheet_result = $stmt->get_result();
    
    if ($sheet_result && $sheet_result->num_rows > 0) {
        $sheet = $sheet_result->fetch_assoc();
        $google_sheet_id = $sheet['sheet_id'];
        $tab_name = $sheet['tab_name'];
        $label = $sheet['label'];
        
        try {
            // Log start of operation
            error_log("Starting refresh for sheet: {$google_sheet_id}, tab: {$tab_name}");
            
            // Fetch data from Google Sheets API
            error_log("Fetching data from Google Sheets API...");
            $sheet_data = fetchSheetData($google_sheet_id, $tab_name, '');
            error_log("Fetched " . count($sheet_data) . " rows from Google Sheets");
            
            if (count($sheet_data) > 1) {
                // Remove old tasks for this sheet
                error_log("Deleting old tasks for sheet...");
                $del_stmt = $conn->prepare("DELETE FROM fms_tasks WHERE sheet_id = ?");
                $del_stmt->bind_param("s", $google_sheet_id);
                $del_stmt->execute();
                error_log("Deleted old tasks");
                
                // Insert new tasks
                $insert_count = 0;
                $total_rows = count($sheet_data) - 1; // Exclude header
                error_log("Processing {$total_rows} rows for insertion...");
                
                $insert_stmt = $conn->prepare("
                    INSERT INTO fms_tasks 
                    (sheet_id, unique_key, step_name, client_name, planned, actual, status, duration, doer_name, department, task_link, sheet_label, step_code) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Remove header row
                array_shift($sheet_data);
                
                foreach ($sheet_data as $index => $row) {
                    // Log progress every 100 rows
                    if ($index % 100 == 0) {
                        error_log("Processing row {$index} of {$total_rows}");
                    }
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
                        $google_sheet_id,
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
                $sync_stmt->bind_param("sss", $google_sheet_id, $now, $now);
                $sync_stmt->execute();
                
                $_SESSION['success_message'] = "Successfully refreshed sheet '{$label}'. Imported {$insert_count} tasks.";
            } else {
                $_SESSION['error_message'] = "No data found in sheet '{$label}'.";
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error refreshing sheet data: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Sheet not found.";
    }
    
    header("Location: manage_sheets.php");
    exit;
}

// Handle delete sheet
if (isset($_GET['delete_sheet'])) {
    $sheet_id = $_GET['delete_sheet'];
    
    // Get the sheet ID before deleting
    $get_sheet_id_query = "SELECT sheet_id FROM fms_sheets WHERE id = ?";
    $stmt = $conn->prepare($get_sheet_id_query);
    $stmt->bind_param("i", $sheet_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $google_sheet_id = $row['sheet_id'];
        
        // Delete from fms_sheets
        $delete_stmt = $conn->prepare("DELETE FROM fms_sheets WHERE id = ?");
        $delete_stmt->bind_param("i", $sheet_id);
        $delete_stmt->execute();
        
        // Delete associated tasks
        $delete_tasks_stmt = $conn->prepare("DELETE FROM fms_tasks WHERE sheet_id = ?");
        $delete_tasks_stmt->bind_param("s", $google_sheet_id);
        $delete_tasks_stmt->execute();
        
        // Delete from sync table
        $delete_sync_stmt = $conn->prepare("DELETE FROM fms_sheet_sync WHERE sheet_id = ?");
        $delete_sync_stmt->bind_param("s", $google_sheet_id);
        $delete_sync_stmt->execute();
        
        $_SESSION['success_message'] = "Sheet and associated tasks deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Sheet not found.";
    }
    
    header("Location: manage_sheets.php");
    exit;
}

// Handle delete all FMS tasks
if (isset($_GET['delete_all_tasks'])) {
    // Delete all records from fms_tasks table
    $delete_all_stmt = $conn->prepare("DELETE FROM fms_tasks");
    $delete_all_stmt->execute();
    
    // Clear sync metadata
    $clear_sync_stmt = $conn->prepare("DELETE FROM fms_sheet_sync");
    $clear_sync_stmt->execute();
    
    $_SESSION['success_message'] = "All FMS tasks have been deleted successfully.";
    
    header("Location: manage_sheets.php");
    exit;
}

// Handle refresh all sheets
if (isset($_GET['refresh_all_sheets'])) {
    $total_imported = 0;
    $success_count = 0;
    $error_count = 0;
    
    // Get all sheets
    $all_sheets_query = "SELECT * FROM fms_sheets ORDER BY created_at ASC";
    $all_sheets_result = $conn->query($all_sheets_query);
    
    if ($all_sheets_result && $all_sheets_result->num_rows > 0) {
        while ($sheet = $all_sheets_result->fetch_assoc()) {
            $google_sheet_id = $sheet['sheet_id'];
            $tab_name = $sheet['tab_name'];
            $label = $sheet['label'];
            
            try {
                // Fetch data from Google Sheets API
                $sheet_data = fetchSheetData($google_sheet_id, $tab_name, '');
                
                if (count($sheet_data) > 1) {
                    // Remove old tasks for this sheet
                    $del_stmt = $conn->prepare("DELETE FROM fms_tasks WHERE sheet_id = ?");
                    $del_stmt->bind_param("s", $google_sheet_id);
                    $del_stmt->execute();
                    
                    // Insert new tasks
                    $insert_count = 0;
                    $insert_stmt = $conn->prepare("
                        INSERT INTO fms_tasks 
                        (sheet_id, unique_key, step_name, client_name, planned, actual, status, duration, doer_name, department, task_link, sheet_label, step_code) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    // Remove header row
                    array_shift($sheet_data);
                    
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
                            $google_sheet_id,
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
                    $sync_stmt->bind_param("sss", $google_sheet_id, $now, $now);
                    $sync_stmt->execute();
                    
                    $total_imported += $insert_count;
                    $success_count++;
                } else {
                    $error_count++;
                }
            } catch (Exception $e) {
                $error_count++;
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['success_message'] = "Successfully refreshed {$success_count} sheets. Total {$total_imported} tasks imported.";
        }
        if ($error_count > 0) {
            $_SESSION['error_message'] = "Failed to refresh {$error_count} sheets.";
        }
    } else {
        $_SESSION['error_message'] = "No sheets found to refresh.";
    }
    
    header("Location: manage_sheets.php");
    exit;
}

// Get all sheets with their sync status
$sheets_query = "
    SELECT 
        s.*, 
        COALESCE(sync.last_synced, 'Never') as last_updated,
        (SELECT COUNT(*) FROM fms_tasks WHERE sheet_id = s.sheet_id) as row_count
    FROM 
        fms_sheets s
    LEFT JOIN 
        fms_sheet_sync sync ON s.sheet_id = sync.sheet_id
    ORDER BY 
        s.created_at DESC
";

$sheets_result = $conn->query($sheets_query);
$sheets = [];

if ($sheets_result) {
    while ($row = $sheets_result->fetch_assoc()) {
        $sheets[] = $row;
    }
}

// Header already included at the top
?>

<div class="container-fluid mt-4">
    <div class="row mb-3 align-items-center">
        <div class="col-md-8">
            <h2 class="mb-0">
                <i class="fas fa-table"></i> Manage Google Sheets
            </h2>
        </div>
        <div class="col-md-4">
            <div class="action-buttons">
                <a href="add_sheet.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Sheet
                </a>
                <a href="?refresh_all_sheets=1" class="btn btn-info" 
                   onclick="return confirm('Are you sure you want to refresh all sheets? This will update data from all connected Google Sheets.');">
                    <i class="fas fa-sync-alt"></i> Refresh All Sheets
                </a>
                <a href="?delete_all_tasks=1" class="btn btn-danger" 
                   onclick="return confirm('WARNING: This will delete ALL FMS tasks from the database. This action cannot be undone. Are you sure you want to continue?');">
                    <i class="fas fa-trash"></i> Delete All FMS Tasks
                </a>
                <a href="fms_task.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to FMS Tasks
                </a>
            </div>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">Connected Google Sheets</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($sheets)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Label</th>
                                <th>Sheet ID</th>
                                <th>Tab Name</th>
                                <th>Rows</th>
                                <th>Last Updated</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sheets as $sheet): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sheet['label']); ?></td>
                                    <td class="sheet-id">
                                        <small class="text-monospace">
                                            <?php echo htmlspecialchars($sheet['sheet_id']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($sheet['tab_name']); ?></td>
                                    <td><?php echo number_format($sheet['row_count']); ?></td>
                                    <td>
                                        <?php 
                                            if ($sheet['last_updated'] !== 'Never') {
                                                $date = new DateTime($sheet['last_updated']);
                                                echo $date->format('d M Y H:i:s');
                                            } else {
                                                echo 'Never';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <a href="view_sheet_data.php?sheet_id=<?php echo $sheet['id']; ?>" 
                                               class="btn btn-sm btn-primary btn-icon" title="View Data">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?refresh_sheet=<?php echo $sheet['id']; ?>" 
                                               class="btn btn-sm btn-info btn-icon" title="Refresh Data">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                            <a href="https://docs.google.com/spreadsheets/d/<?php echo htmlspecialchars($sheet['sheet_id']); ?>" 
                                               target="_blank" class="btn btn-sm btn-secondary btn-icon" title="Open in Google Sheets">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <a href="?delete_sheet=<?php echo $sheet['id']; ?>" 
                                               class="btn btn-sm btn-danger btn-icon" 
                                               onclick="return confirm('Are you sure you want to delete this sheet and all associated tasks?');" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info m-3">
                    <i class="fas fa-info-circle"></i> No Google Sheets connected yet. Click "Add New Sheet" to connect a sheet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.btn-icon {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
.sheet-id {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
}
.action-buttons .btn {
    white-space: nowrap;
}
@media (max-width: 768px) {
    .action-buttons {
        justify-content: flex-start;
        margin-top: 1rem;
    }
}

/* Tooltip hover styles */
.description-hover {
    cursor: help;
    border-bottom: 1px dotted #666;
}

.delay-hover {
    cursor: help;
    border-bottom: 1px dotted #dc3545;
}

.tooltip-inner {
    max-width: 300px;
    text-align: left;
    white-space: pre-wrap;
    word-wrap: break-word;
}</style>

<?php
// Include the universal footer
require_once "../includes/footer.php";
?>



                        $department = $row[9] ?? '';

                        $task_link = $row[8] ?? '';

                        $step_code = $row[10] ?? '';

                        

                        $insert_stmt->bind_param("sssssssssssss",

                            $google_sheet_id,

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

                    $sync_stmt->bind_param("sss", $google_sheet_id, $now, $now);

                    $sync_stmt->execute();

                    

                    $total_imported += $insert_count;

                    $success_count++;

                } else {

                    $error_count++;

                }

            } catch (Exception $e) {

                $error_count++;

            }

        }

        

        if ($success_count > 0) {

            $_SESSION['success_message'] = "Successfully refreshed {$success_count} sheets. Total {$total_imported} tasks imported.";

        }

        if ($error_count > 0) {

            $_SESSION['error_message'] = "Failed to refresh {$error_count} sheets.";

        }

    } else {

        $_SESSION['error_message'] = "No sheets found to refresh.";

    }

    

    header("Location: manage_sheets.php");

    exit;

}



// Get all sheets with their sync status

$sheets_query = "

    SELECT 

        s.*, 

        COALESCE(sync.last_synced, 'Never') as last_updated,

        (SELECT COUNT(*) FROM fms_tasks WHERE sheet_id = s.sheet_id) as row_count

    FROM 

        fms_sheets s

    LEFT JOIN 

        fms_sheet_sync sync ON s.sheet_id = sync.sheet_id

    ORDER BY 

        s.created_at DESC

";



$sheets_result = $conn->query($sheets_query);

$sheets = [];



if ($sheets_result) {

    while ($row = $sheets_result->fetch_assoc()) {

        $sheets[] = $row;

    }

}



// Header already included at the top
?>



<div class="container-fluid mt-4">

    <div class="row mb-3 align-items-center">

        <div class="col-md-8">

            <h2 class="mb-0">

                <i class="fas fa-table"></i> Manage Google Sheets

            </h2>

        </div>

        <div class="col-md-4">

            <div class="action-buttons">

                <a href="add_sheet.php" class="btn btn-success">

                    <i class="fas fa-plus"></i> Add New Sheet

                </a>

                <a href="?refresh_all_sheets=1" class="btn btn-info" 

                   onclick="return confirm('Are you sure you want to refresh all sheets? This will update data from all connected Google Sheets.');">

                    <i class="fas fa-sync-alt"></i> Refresh All Sheets

                </a>

                <a href="?delete_all_tasks=1" class="btn btn-danger" 

                   onclick="return confirm('WARNING: This will delete ALL FMS tasks from the database. This action cannot be undone. Are you sure you want to continue?');">

                    <i class="fas fa-trash"></i> Delete All FMS Tasks

                </a>

                <a href="fms_task.php" class="btn btn-secondary">

                    <i class="fas fa-arrow-left"></i> Back to FMS Tasks

                </a>

            </div>

        </div>

    </div>

    

    <?php if (isset($_SESSION['success_message'])): ?>

        <div class="alert alert-success alert-dismissible fade show" role="alert">

            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>

            <button type="button" class="close" data-dismiss="alert" aria-label="Close">

                <span aria-hidden="true">&times;</span>

            </button>

        </div>

    <?php endif; ?>

    

    <?php if (isset($_SESSION['error_message'])): ?>

        <div class="alert alert-danger alert-dismissible fade show" role="alert">

            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>

            <button type="button" class="close" data-dismiss="alert" aria-label="Close">

                <span aria-hidden="true">&times;</span>

            </button>

        </div>

    <?php endif; ?>

    

    <div class="card shadow-sm">

        <div class="card-header bg-light">

            <h5 class="mb-0">Connected Google Sheets</h5>

        </div>

        <div class="card-body p-0">

            <?php if (!empty($sheets)): ?>

                <div class="table-responsive">

                    <table class="table table-striped table-bordered mb-0">

                        <thead class="thead-light">

                            <tr>

                                <th>Label</th>

                                <th>Sheet ID</th>

                                <th>Tab Name</th>

                                <th>Rows</th>

                                <th>Last Updated</th>

                                <th class="text-center">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($sheets as $sheet): ?>

                                <tr>

                                    <td><?php echo htmlspecialchars($sheet['label']); ?></td>

                                    <td class="sheet-id">

                                        <small class="text-monospace">

                                            <?php echo htmlspecialchars($sheet['sheet_id']); ?>

                                        </small>

                                    </td>

                                    <td><?php echo htmlspecialchars($sheet['tab_name']); ?></td>

                                    <td><?php echo number_format($sheet['row_count']); ?></td>

                                    <td>

                                        <?php 

                                            if ($sheet['last_updated'] !== 'Never') {

                                                $date = new DateTime($sheet['last_updated']);

                                                echo $date->format('d M Y H:i:s');

                                            } else {

                                                echo 'Never';

                                            }

                                        ?>

                                    </td>

                                    <td class="text-center">

                                        <div class="btn-group">

                                            <a href="view_sheet_data.php?sheet_id=<?php echo $sheet['id']; ?>" 

                                               class="btn btn-sm btn-primary btn-icon" title="View Data">

                                                <i class="fas fa-eye"></i>

                                            </a>

                                            <a href="?refresh_sheet=<?php echo $sheet['id']; ?>" 

                                               class="btn btn-sm btn-info btn-icon" title="Refresh Data">

                                                <i class="fas fa-sync-alt"></i>

                                            </a>

                                            <a href="https://docs.google.com/spreadsheets/d/<?php echo htmlspecialchars($sheet['sheet_id']); ?>" 

                                               target="_blank" class="btn btn-sm btn-secondary btn-icon" title="Open in Google Sheets">

                                                <i class="fas fa-external-link-alt"></i>

                                            </a>

                                            <a href="?delete_sheet=<?php echo $sheet['id']; ?>" 

                                               class="btn btn-sm btn-danger btn-icon" 

                                               onclick="return confirm('Are you sure you want to delete this sheet and all associated tasks?');" title="Delete">

                                                <i class="fas fa-trash"></i>

                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="alert alert-info m-3">

                    <i class="fas fa-info-circle"></i> No Google Sheets connected yet. Click "Add New Sheet" to connect a sheet.

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>



<style>

.btn-icon {

    padding: 0.25rem 0.5rem;

    font-size: 0.875rem;

}

.sheet-id {

    max-width: 200px;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.action-buttons {

    display: flex;

    flex-wrap: wrap;

    gap: 0.5rem;

    justify-content: flex-end;

}

.action-buttons .btn {

    white-space: nowrap;

}

@media (max-width: 768px) {

    .action-buttons {

        justify-content: flex-start;

        margin-top: 1rem;

    }

}



/* Tooltip hover styles */

.description-hover {

    cursor: help;

    border-bottom: 1px dotted #666;

}



.delay-hover {

    cursor: help;

    border-bottom: 1px dotted #dc3545;

}



.tooltip-inner {

    max-width: 300px;

    text-align: left;

    white-space: pre-wrap;

    word-wrap: break-word;

}</style>



<?php

// Include the universal footer

require_once "../includes/footer.php";

?>




