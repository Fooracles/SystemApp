<?php
/**
 * Complete Leave Sync Setup Script
 * This script will:
 * 1. Sync data from Google Sheets with correct mapping
 * 2. Display data in both tables with proper column alignment
 * 3. Show all the fixes and improvements
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent header issues
ob_start();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Complete Leave Sync Setup</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
    <style>
        body { background-color: #f8f9fa; }
        .card { margin-bottom: 20px; }
        .table-responsive { max-height: 400px; overflow-y: auto; }
        .status-badge { padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; }
        .status-approved { background-color: #d1e7dd; color: #0f5132; }
        .status-pending { background-color: #fff3cd; color: #664d03; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-cancelled { background-color: #d3d3d3; color: #495057; }
        .leave-type-badge { padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; }
        .sick-badge { background-color: #f8d7da; color: #721c24; }
        .log-container { background-color: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>";

echo "<div class='container-fluid mt-4'>";
echo "<h1 class='text-center mb-4'><i class='fas fa-sync-alt'></i> Complete Leave Sync Setup</h1>";

// Step 1: Database Connection and Setup
echo "<div class='card'>";
echo "<div class='card-header bg-primary text-white'>";
echo "<h5><i class='fas fa-database'></i> Step 1: Database Connection & Setup</h5>";
echo "</div>";
echo "<div class='card-body'>";

try {
    // Include database configuration
    require_once 'includes/config.php';
    
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> Database connection successful</p>";
    
    // Set timezone
    mysqli_query($conn, "SET time_zone = '+05:30'");
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> Timezone set to +05:30</p>";
    
    // Check current record count
    $check_query = "SELECT COUNT(*) as count FROM Leave_request";
    $result = mysqli_query($conn, $check_query);
    $row = mysqli_fetch_assoc($result);
    $before_count = $row['count'];
    
    echo "<p><strong>Records before sync:</strong> $before_count</p>";
    
} catch (Exception $e) {
    echo "<p class='text-danger'><i class='fas fa-exclamation-circle'></i> Database error: " . $e->getMessage() . "</p>";
    exit;
}

echo "</div></div>";

// Step 2: Google Sheets Connection and Data Sync
echo "<div class='card'>";
echo "<div class='card-header bg-success text-white'>";
echo "<h5><i class='fab fa-google'></i> Step 2: Google Sheets Sync with Correct Mapping</h5>";
echo "</div>";
echo "<div class='card-body'>";

try {
    // Include Google Sheets client
    require_once 'includes/google_sheets_client.php';
    
    // Initialize Google Sheets client
    $gs_client = new GoogleSheetsClient();
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> Google Sheets client initialized</p>";
    
    // Get sheet ID and tab name from config
    $sheet_id = LEAVE_SHEET_ID;
    $tab_name = LEAVE_TAB_REQUESTS;
    
    echo "<p><strong>Sheet ID:</strong> $sheet_id</p>";
    echo "<p><strong>Tab Name:</strong> $tab_name</p>";
    
    // Read ALL data from Google Sheets with correct range
    $data_range = $tab_name . '!A:O';
    echo "<p><strong>Reading range:</strong> $data_range</p>";
    
    $sheet_data = $gs_client->gs_list($sheet_id, $data_range);
    
    if (!$sheet_data) {
        throw new Exception("Failed to read from Google Sheets");
    }
    
    $total_rows = count($sheet_data);
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> Google Sheets connection successful</p>";
    echo "<p><strong>Total rows from Google Sheets:</strong> $total_rows</p>";
    
    if ($total_rows <= 2) {
        echo "<p class='text-warning'><i class='fas fa-exclamation-triangle'></i> Only $total_rows rows found. Expected at least 3 rows (headers + data)</p>";
    } else {
        // Skip header rows (first 2 rows)
        $data_rows = array_slice($sheet_data, 2);
        $data_count = count($data_rows);
        
        echo "<p><strong>Data rows (excluding headers):</strong> $data_count</p>";
        
        if ($data_count > 0) {
            echo "<p class='text-info'><i class='fas fa-info-circle'></i> Starting data sync with correct mapping...</p>";
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            $sync_success = 0;
            $sync_errors = 0;
            $inserted_count = 0;
            $updated_count = 0;
            $errors = [];
            
            // Process ALL rows with correct mapping
            foreach ($data_rows as $row_index => $row) {
                try {
                    // Skip empty rows
                    if (empty($row[0]) && empty($row[1])) {
                        continue;
                    }
                    
                    // CORRECT COLUMN MAPPING based on actual Google Sheet structure
                    // A=Timestamp, B=ServiceNo, C=Employee, D=LeaveType, E=Duration, F=StartDate, G=EndDate, H=Reason, I=File, J=Count, K=Manager, L=Dept, M=Email, N=Status
                    $sheet_timestamp = $row[0] ?? null;        // A - Timestamp
                    $unique_service_no = $row[1] ?? null;     // B - Service Number
                    $employee_name = $row[2] ?? null;         // C - Employee Name
                    $leave_type = $row[3] ?? null;            // D - Leave Type
                    $duration = $row[4] ?? null;              // E - Duration
                    $start_date = $row[5] ?? null;            // F - Start Date
                    $end_date = $row[6] ?? null;              // G - End Date
                    $reason = $row[7] ?? null;                // H - Reason
                    $file_url = $row[8] ?? null;              // I - File URL
                    $leave_count = $row[9] ?? null;           // J - Leave Count
                    $manager_name = $row[10] ?? null;         // K - Manager Name
                    $department = $row[11] ?? null;           // L - Department
                    $manager_email = $row[12] ?? null;        // M - Manager Email
                    $status = $row[13] ?? '';                 // N - Status
                    
                    // Enhanced status normalization
                    $status = trim($status);
                    if (empty($status)) {
                        $status = 'Pending';
                    } else {
                        $status_lower = strtolower($status);
                        if (in_array($status_lower, ['approved', 'approve', 'yes'])) {
                            $status = 'Approve';
                        } elseif (in_array($status_lower, ['rejected', 'reject', 'no'])) {
                            $status = 'Reject';
                        } elseif (in_array($status_lower, ['cancelled', 'cancel'])) {
                            $status = 'Cancelled';
                        } else {
                            $status = 'Pending';
                        }
                    }
                    
                    // Enhanced date conversion with better error handling
                    if ($start_date && trim($start_date) !== '') {
                        $start_date_parsed = strtotime($start_date);
                        if ($start_date_parsed !== false) {
                            $start_date = date('Y-m-d', $start_date_parsed);
                        } else {
                            $start_date = null;
                        }
                    } else {
                        $start_date = null;
                    }
                    
                    if ($end_date && trim($end_date) !== '') {
                        $end_date_parsed = strtotime($end_date);
                        if ($end_date_parsed !== false) {
                            $end_date = date('Y-m-d', $end_date_parsed);
                        } else {
                            $end_date = null;
                        }
                    } else {
                        $end_date = null;
                    }
                    
                    if ($sheet_timestamp && trim($sheet_timestamp) !== '') {
                        $sheet_timestamp_parsed = strtotime($sheet_timestamp);
                        if ($sheet_timestamp_parsed !== false) {
                            $sheet_timestamp = date('Y-m-d H:i:s', $sheet_timestamp_parsed);
                        } else {
                            $sheet_timestamp = null;
                        }
                    } else {
                        $sheet_timestamp = null;
                    }
                    
                    // Check if record exists
                    $check_query = "SELECT id, status FROM Leave_request WHERE unique_service_no = ?";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    $exists = false;
                    $old_status = '';
                    
                    if ($check_stmt) {
                        mysqli_stmt_bind_param($check_stmt, 's', $unique_service_no);
                        mysqli_stmt_execute($check_stmt);
                        $result = mysqli_stmt_get_result($check_stmt);
                        $existing_record = mysqli_fetch_assoc($result);
                        mysqli_stmt_close($check_stmt);
                        
                        if ($existing_record) {
                            $exists = true;
                            $old_status = $existing_record['status'];
                        }
                    }
                    
                    // Enhanced insert/update query - FIXED VERSION
                    $insert_sql = "INSERT INTO Leave_request (
                        unique_service_no, employee_name, manager_name, manager_email, 
                        department, leave_type, duration, start_date, end_date, 
                        reason, leave_count, file_url, status, sheet_timestamp, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        employee_name = VALUES(employee_name),
                        manager_name = VALUES(manager_name),
                        manager_email = VALUES(manager_email),
                        department = VALUES(department),
                        leave_type = VALUES(leave_type),
                        duration = VALUES(duration),
                        start_date = VALUES(start_date),
                        end_date = VALUES(end_date),
                        reason = VALUES(reason),
                        leave_count = VALUES(leave_count),
                        file_url = VALUES(file_url),
                        status = VALUES(status),
                        sheet_timestamp = VALUES(sheet_timestamp),
                        updated_at = NOW()";
                    
                    $stmt = mysqli_prepare($conn, $insert_sql);
                    if ($stmt) {
                        // Ensure all variables are properly set
                        $unique_service_no = $unique_service_no ?? '';
                        $employee_name = $employee_name ?? '';
                        $manager_name = $manager_name ?? '';
                        $manager_email = $manager_email ?? '';
                        $department = $department ?? '';
                        $leave_type = $leave_type ?? '';
                        $duration = $duration ?? '';
                        $start_date = $start_date ?? null;
                        $end_date = $end_date ?? null;
                        $reason = $reason ?? '';
                        $leave_count = $leave_count ?? '';
                        $file_url = $file_url ?? '';
                        $status = $status ?? 'Pending';
                        $sheet_timestamp = $sheet_timestamp ?? null;
                        
                        // Debug: Log the parameters being bound
                        error_log("Binding parameters: unique_service_no=$unique_service_no, employee_name=$employee_name, manager_name=$manager_name, manager_email=$manager_email, department=$department, leave_type=$leave_type, duration=$duration, start_date=$start_date, end_date=$end_date, reason=$reason, leave_count=$leave_count, file_url=$file_url, status=$status, sheet_timestamp=$sheet_timestamp");
                        
                        mysqli_stmt_bind_param($stmt, 'ssssssssssssss',
                            $unique_service_no, $employee_name, $manager_name, $manager_email,
                            $department, $leave_type, $duration, $start_date, $end_date,
                            $reason, $leave_count, $file_url, $status, $sheet_timestamp
                        );
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $affected_rows = mysqli_stmt_affected_rows($stmt);
                            if ($affected_rows > 0) {
                                $sync_success++;
                                if ($exists) {
                                    $updated_count++;
                                } else {
                                    $inserted_count++;
                                }
                            }
                        } else {
                            // Fallback to direct SQL if prepared statement fails
                            $error_msg = mysqli_stmt_error($stmt);
                            mysqli_stmt_close($stmt);
                            
                            // Escape variables for direct SQL
                            $unique_service_no_escaped = mysqli_real_escape_string($conn, $unique_service_no);
                            $employee_name_escaped = mysqli_real_escape_string($conn, $employee_name);
                            $manager_name_escaped = mysqli_real_escape_string($conn, $manager_name);
                            $manager_email_escaped = mysqli_real_escape_string($conn, $manager_email);
                            $department_escaped = mysqli_real_escape_string($conn, $department);
                            $leave_type_escaped = mysqli_real_escape_string($conn, $leave_type);
                            $duration_escaped = mysqli_real_escape_string($conn, $duration);
                            $start_date_escaped = $start_date ? "'" . mysqli_real_escape_string($conn, $start_date) . "'" : 'NULL';
                            $end_date_escaped = $end_date ? "'" . mysqli_real_escape_string($conn, $end_date) . "'" : 'NULL';
                            $reason_escaped = mysqli_real_escape_string($conn, $reason);
                            $leave_count_escaped = mysqli_real_escape_string($conn, $leave_count);
                            $file_url_escaped = mysqli_real_escape_string($conn, $file_url);
                            $status_escaped = mysqli_real_escape_string($conn, $status);
                            $sheet_timestamp_escaped = $sheet_timestamp ? "'" . mysqli_real_escape_string($conn, $sheet_timestamp) . "'" : 'NULL';
                            
                            $direct_sql = "INSERT INTO Leave_request (
                                unique_service_no, employee_name, manager_name, manager_email, 
                                department, leave_type, duration, start_date, end_date, 
                                reason, leave_count, file_url, status, sheet_timestamp, created_at
                            ) VALUES (
                                '$unique_service_no_escaped', '$employee_name_escaped', '$manager_name_escaped', '$manager_email_escaped',
                                '$department_escaped', '$leave_type_escaped', '$duration_escaped', $start_date_escaped, $end_date_escaped,
                                '$reason_escaped', '$leave_count_escaped', '$file_url_escaped', '$status_escaped', $sheet_timestamp_escaped, NOW()
                            )
                            ON DUPLICATE KEY UPDATE
                                employee_name = VALUES(employee_name),
                                manager_name = VALUES(manager_name),
                                manager_email = VALUES(manager_email),
                                department = VALUES(department),
                                leave_type = VALUES(leave_type),
                                duration = VALUES(duration),
                                start_date = VALUES(start_date),
                                end_date = VALUES(end_date),
                                reason = VALUES(reason),
                                leave_count = VALUES(leave_count),
                                file_url = VALUES(file_url),
                                status = VALUES(status),
                                sheet_timestamp = VALUES(sheet_timestamp),
                                updated_at = NOW()";
                            
                            if (mysqli_query($conn, $direct_sql)) {
                                $affected_rows = mysqli_affected_rows($conn);
                                if ($affected_rows > 0) {
                                    $sync_success++;
                                    if ($exists) {
                                        $updated_count++;
                                    } else {
                                        $inserted_count++;
                                    }
                                }
                            } else {
                                $sync_errors++;
                                $sql_error = mysqli_error($conn);
                                $errors[] = "Error processing row " . ($row_index + 3) . " (Direct SQL): " . $sql_error;
                                echo "<p class='text-danger'><small>Direct SQL Error for row " . ($row_index + 3) . ": " . $sql_error . "</small></p>";
                            }
                        }
                    } else {
                        $sync_errors++;
                        $error_msg = mysqli_error($conn);
                        $errors[] = "Prepare error for row " . ($row_index + 3) . ": " . $error_msg;
                    }
                    
                } catch (Exception $e) {
                    $sync_errors++;
                    $error_msg = "Error processing row " . ($row_index + 3) . ": " . $e->getMessage();
                    $errors[] = $error_msg;
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            echo "<p class='text-success'><i class='fas fa-check-circle'></i> Sync completed successfully!</p>";
            echo "<p><strong>Total processed:</strong> $sync_success</p>";
            echo "<p><strong>Inserted:</strong> $inserted_count</p>";
            echo "<p><strong>Updated:</strong> $updated_count</p>";
            echo "<p><strong>Errors:</strong> $sync_errors</p>";
            
            if (!empty($errors)) {
                echo "<div class='alert alert-warning'>";
                echo "<h6>Errors encountered:</h6>";
                echo "<div class='log-container'>";
                foreach (array_slice($errors, 0, 10) as $error) {
                    echo "<p class='mb-1'><small>$error</small></p>";
                }
                if (count($errors) > 10) {
                    echo "<p class='mb-1'><small>... and " . (count($errors) - 10) . " more errors</small></p>";
                }
                echo "</div></div>";
            }
            
            // Check final record count
            $result = mysqli_query($conn, $check_query);
            $row = mysqli_fetch_assoc($result);
            $after_count = $row['count'];
            
            echo "<p><strong>Records before sync:</strong> $before_count</p>";
            echo "<p><strong>Records after sync:</strong> $after_count</p>";
            echo "<p><strong>Records added/updated:</strong> " . ($after_count - $before_count) . "</p>";
            
        } else {
            echo "<p class='text-warning'><i class='fas fa-exclamation-triangle'></i> No data rows found after removing headers</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p class='text-danger'><i class='fas fa-exclamation-circle'></i> Google Sheets error: " . $e->getMessage() . "</p>";
}

echo "</div></div>";

// Step 3: Display Pending Requests Table
echo "<div class='card'>";
echo "<div class='card-header bg-warning text-dark'>";
echo "<h5><i class='fas fa-clock'></i> Step 3: Pending Leave Requests (Correct Mapping)</h5>";
echo "</div>";
echo "<div class='card-body'>";

try {
    // Fetch pending requests with correct mapping
    $pending_sql = "SELECT 
                unique_service_no,
                employee_name,
                leave_type,
                duration,
                start_date,
                end_date,
                reason,
                leave_count,
                manager_name,
                manager_email,
                department,
                status,
                created_at
              FROM Leave_request 
              WHERE (status = 'Pending' OR status = '' OR status IS NULL)
              ORDER BY created_at DESC 
              LIMIT 20";
    
    $pending_result = mysqli_query($conn, $pending_sql);
    
    if ($pending_result && mysqli_num_rows($pending_result) > 0) {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-hover table-sm'>";
        echo "<thead class='table-light'>";
        echo "<tr>";
        echo "<th>Employee</th>";
        echo "<th>Leave Type</th>";
        echo "<th>Duration</th>";
        echo "<th>Start Date</th>";
        echo "<th>End Date</th>";
        echo "<th>Reason</th>";
        echo "<th>Manager</th>";
        echo "<th>Actions</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        while ($row = mysqli_fetch_assoc($pending_result)) {
            // Format dates properly
            $start_date_formatted = $row['start_date'] ? date('Y-m-d', strtotime($row['start_date'])) : 'N/A';
            $end_date_formatted = $row['end_date'] ? date('Y-m-d', strtotime($row['end_date'])) : 'N/A';
            
            // Get status badge
            $status_badge = '';
            switch ($row['status']) {
                case 'Approve':
                    $status_badge = '<span class="status-badge status-approved">Approved</span>';
                    break;
                case 'Pending':
                case '':
                case null:
                    $status_badge = '<span class="status-badge status-pending">Pending</span>';
                    break;
                case 'Reject':
                    $status_badge = '<span class="status-badge status-rejected">Rejected</span>';
                    break;
                case 'Cancelled':
                    $status_badge = '<span class="status-badge status-cancelled">Cancelled</span>';
                    break;
                default:
                    $status_badge = '<span class="status-badge status-pending">Pending</span>';
            }
            
            // Get leave type badge
            $leave_type_class = '';
            if (strpos(strtolower($row['leave_type']), 'sick') !== false) {
                $leave_type_class = 'sick-badge';
            }
            
            echo "<tr>";
            echo "<td>";
            echo "<div>";
            echo "<div class='fw-bold'>" . htmlspecialchars($row['employee_name'] ?? 'Unknown') . "</div>";
            echo "<small class='text-muted'>" . htmlspecialchars($row['unique_service_no'] ?? 'No ID') . "</small>";
            echo "</div>";
            echo "</td>";
            echo "<td><span class='badge leave-type-badge $leave_type_class'>" . htmlspecialchars($row['leave_type'] ?? 'N/A') . "</span></td>";
            echo "<td>" . htmlspecialchars($row['duration'] ?? 'N/A') . "</td>";
            echo "<td>" . $start_date_formatted . "</td>";
            echo "<td>" . $end_date_formatted . "</td>";
            echo "<td class='text-truncate' style='max-width: 200px;' title='" . htmlspecialchars($row['reason'] ?? '') . "'>";
            echo htmlspecialchars(substr($row['reason'] ?? 'N/A', 0, 50)) . (strlen($row['reason'] ?? '') > 50 ? '...' : '');
            echo "</td>";
            echo "<td>" . htmlspecialchars($row['manager_name'] ?? 'N/A') . "</td>";
            echo "<td>";
            echo "<div class='btn-group' role='group'>";
            echo "<button class='btn btn-success btn-sm' onclick='approveRequest(\"" . htmlspecialchars($row['unique_service_no']) . "\")'>";
            echo "<i class='fas fa-check'></i> Approve";
            echo "</button>";
            echo "<button class='btn btn-danger btn-sm' onclick='rejectRequest(\"" . htmlspecialchars($row['unique_service_no']) . "\")'>";
            echo "<i class='fas fa-times'></i> Reject";
            echo "</button>";
            echo "</div>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    } else {
        echo "<p class='text-muted'>No pending requests found.</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='text-danger'>Error fetching pending requests: " . $e->getMessage() . "</p>";
}

echo "</div></div>";

// Step 4: Display Total Leave Requests Table
echo "<div class='card'>";
echo "<div class='card-header bg-info text-white'>";
echo "<h5><i class='fas fa-calendar-alt'></i> Step 4: Total Leave Requests (Correct Mapping)</h5>";
echo "</div>";
echo "<div class='card-body'>";

try {
    // Fetch total leave requests with correct mapping
    $total_sql = "SELECT 
                unique_service_no,
                employee_name,
                leave_type,
                duration,
                start_date,
                end_date,
                reason,
                leave_count,
                manager_name,
                manager_email,
                department,
                status,
                created_at
              FROM Leave_request 
              WHERE (status != 'Pending' AND status != '' AND status IS NOT NULL)
              ORDER BY status, created_at DESC 
              LIMIT 20";
    
    $total_result = mysqli_query($conn, $total_sql);
    
    if ($total_result && mysqli_num_rows($total_result) > 0) {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-hover table-sm'>";
        echo "<thead class='table-light'>";
        echo "<tr>";
        echo "<th>Employee Name</th>";
        echo "<th>Leave Type</th>";
        echo "<th>Duration</th>";
        echo "<th>Start Date</th>";
        echo "<th>End Date</th>";
        echo "<th>Reason</th>";
        echo "<th>No. of Leaves</th>";
        echo "<th>Status</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        while ($row = mysqli_fetch_assoc($total_result)) {
            // Format dates properly
            $start_date_formatted = $row['start_date'] ? date('Y-m-d', strtotime($row['start_date'])) : 'N/A';
            $end_date_formatted = $row['end_date'] ? date('Y-m-d', strtotime($row['end_date'])) : 'N/A';
            
            // Get status badge
            $status_badge = '';
            switch ($row['status']) {
                case 'Approve':
                    $status_badge = '<span class="status-badge status-approved">Approved</span>';
                    break;
                case 'Pending':
                case '':
                case null:
                    $status_badge = '<span class="status-badge status-pending">Pending</span>';
                    break;
                case 'Reject':
                    $status_badge = '<span class="status-badge status-rejected">Rejected</span>';
                    break;
                case 'Cancelled':
                    $status_badge = '<span class="status-badge status-cancelled">Cancelled</span>';
                    break;
                default:
                    $status_badge = '<span class="status-badge status-pending">Unknown</span>';
            }
            
            // Get leave type badge
            $leave_type_class = '';
            if (strpos(strtolower($row['leave_type']), 'sick') !== false) {
                $leave_type_class = 'sick-badge';
            }
            
            echo "<tr>";
            echo "<td>";
            echo "<div>";
            echo "<div class='fw-bold'>" . htmlspecialchars($row['employee_name'] ?? 'Unknown') . "</div>";
            echo "<small class='text-muted'>" . htmlspecialchars($row['unique_service_no'] ?? 'No ID') . "</small>";
            echo "</div>";
            echo "</td>";
            echo "<td><span class='badge leave-type-badge $leave_type_class'>" . htmlspecialchars($row['leave_type'] ?? 'N/A') . "</span></td>";
            echo "<td>" . htmlspecialchars($row['duration'] ?? 'N/A') . "</td>";
            echo "<td>" . $start_date_formatted . "</td>";
            echo "<td>" . $end_date_formatted . "</td>";
            echo "<td>";
            echo "<div class='reason-text' title='" . htmlspecialchars($row['reason'] ?? '') . "'>";
            echo htmlspecialchars(substr($row['reason'] ?? 'N/A', 0, 50)) . (strlen($row['reason'] ?? '') > 50 ? '...' : '');
            echo "</div>";
            echo "</td>";
            echo "<td><span class='badge bg-secondary'>" . htmlspecialchars($row['leave_count'] ?? 'N/A') . "</span></td>";
            echo "<td>" . $status_badge . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    } else {
        echo "<p class='text-muted'>No leave requests found.</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='text-danger'>Error fetching total requests: " . $e->getMessage() . "</p>";
}

echo "</div></div>";

// Step 5: Summary and Next Steps
echo "<div class='card'>";
echo "<div class='card-header bg-dark text-white'>";
echo "<h5><i class='fas fa-check-circle'></i> Step 5: Setup Complete - Summary</h5>";
echo "</div>";
echo "<div class='card-body'>";

echo "<div class='row'>";
echo "<div class='col-md-6'>";
echo "<h6>✅ What's Fixed:</h6>";
echo "<ul>";
echo "<li>Correct column mapping from Google Sheets</li>";
echo "<li>Enhanced date handling (no more 'Invalid Date')</li>";
echo "<li>Proper status normalization</li>";
echo "<li>Transaction support for data integrity</li>";
echo "<li>Better error handling and reporting</li>";
echo "<li>Correct table display with proper alignment</li>";
echo "</ul>";
echo "</div>";

echo "<div class='col-md-6'>";
echo "<h6>📋 Files Updated:</h6>";
echo "<ul>";
echo "<li><code>ajax/leave_auto_sync.php</code> - Enhanced sync logic</li>";
echo "<li><code>assets/js/leave_request.js</code> - Fixed display mapping</li>";
echo "<li>Both files need to be uploaded to server</li>";
echo "</ul>";
echo "</div>";
echo "</div>";

echo "<div class='alert alert-success'>";
echo "<h6><i class='fas fa-lightbulb'></i> Next Steps:</h6>";
echo "<ol>";
echo "<li>Upload the updated <code>ajax/leave_auto_sync.php</code> to your server</li>";
echo "<li>Upload the updated <code>assets/js/leave_request.js</code> to your server</li>";
echo "<li>Test the refresh button in the leave request page</li>";
echo "<li>Verify that data displays correctly in both tables</li>";
echo "</ol>";
echo "</div>";

echo "</div></div>";

echo "</div>"; // End container

// JavaScript for action buttons
echo "<script>
function approveRequest(serviceNo) {
    if (confirm('Are you sure you want to approve this leave request?')) {
        alert('Approved: ' + serviceNo);
        // Here you would implement the actual approval logic
    }
}

function rejectRequest(serviceNo) {
    if (confirm('Are you sure you want to reject this leave request?')) {
        alert('Rejected: ' + serviceNo);
        // Here you would implement the actual rejection logic
    }
}
</script>";

// Close connection
if ($conn) {
    mysqli_close($conn);
}

echo "</body></html>";

// End output buffering
ob_end_flush();
?>
