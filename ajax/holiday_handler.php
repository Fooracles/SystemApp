<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../includes/config.php";
require_once "../includes/functions.php"; // For isLoggedIn or other helpers if needed

header('Content-Type: application/json');

// Check if the user is logged in - basic security
if (!isLoggedIn()) {
    jsonError('Authentication required.', 400);
}

// CSRF protection for POST requests
csrfProtect();

$response = ['status' => 'error', 'message' => 'Invalid request'];

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'add_holiday') {
        if (isset($_POST['holiday_date'], $_POST['holiday_name'])) {
            $holiday_date = trim($_POST['holiday_date']);
            $holiday_name = trim($_POST['holiday_name']);

            if (empty($holiday_date) || empty($holiday_name)) {
                $response['message'] = 'Date and name cannot be empty.';
            } else {
                // Check if holiday already exists for that date
                $sql_check = "SELECT id FROM holidays WHERE holiday_date = ?";
                if ($stmt_check = mysqli_prepare($conn, $sql_check)) {
                    mysqli_stmt_bind_param($stmt_check, "s", $holiday_date);
                    mysqli_stmt_execute($stmt_check);
                    mysqli_stmt_store_result($stmt_check);
                    if (mysqli_stmt_num_rows($stmt_check) > 0) {
                        $response['message'] = 'A holiday already exists for this date.';
                    } else {
                        $current_user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : null;

                        $sql_insert = "INSERT INTO holidays (holiday_date, holiday_name, created_by) VALUES (?, ?, ?)";
                        if ($stmt_insert = mysqli_prepare($conn, $sql_insert)) {
                            mysqli_stmt_bind_param($stmt_insert, "ssi", $holiday_date, $holiday_name, $current_user_id);
                            if (mysqli_stmt_execute($stmt_insert)) {
                                $response['status'] = 'success';
                                $response['message'] = 'Holiday added successfully!';
                                $response['new_holiday_id'] = mysqli_insert_id($conn);
                            } else {
                                $response['message'] = 'Error adding holiday: ' . mysqli_error($conn);
                            }
                            mysqli_stmt_close($stmt_insert);
                        } else {
                             $response['message'] = 'Database error (prepare insert). '.mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt_check);
                } else {
                    $response['message'] = 'Database error (check). '.mysqli_error($conn);
                }
            }
        } else {
            $response['message'] = 'Missing holiday data.';
        }
    } elseif ($action == 'delete_holiday') {
        if (isset($_POST['holiday_id'])) {
            $holiday_id = intval($_POST['holiday_id']);
            $sql_delete = "DELETE FROM holidays WHERE id = ?";
            if ($stmt_delete = mysqli_prepare($conn, $sql_delete)) {
                mysqli_stmt_bind_param($stmt_delete, "i", $holiday_id);
                if (mysqli_stmt_execute($stmt_delete)) {
                    if(mysqli_stmt_affected_rows($stmt_delete) > 0){
                        $response['status'] = 'success';
                        $response['message'] = 'Holiday deleted successfully!';
                    } else {
                        $response['message'] = 'Holiday not found or already deleted.';
                    }
                } else {
                    $response['message'] = 'Error deleting holiday: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt_delete);
            } else {
                 $response['message'] = 'Database error (delete). '.mysqli_error($conn);
            }
        } else {
            $response['message'] = 'Missing holiday ID.';
        }
    } elseif ($action == 'bulk_upload_holidays') {
        // Check if user is admin
        if (!isAdmin()) {
            $response['message'] = 'Only administrators can upload holidays in bulk.';
        } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'No file uploaded or upload error occurred.';
        } else {
            $file = $_FILES['file'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, ['csv', 'xls', 'xlsx'])) {
                $response['message'] = 'Invalid file type. Please upload a CSV or Excel file.';
            } else {
                $current_user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : null;
                $successCount = 0;
                $errorCount = 0;
                $errors = [];
                
                // Read file based on extension
                $holidays = [];
                
                if ($fileExtension === 'csv') {
                    // Read CSV file
                    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                        $header = fgetcsv($handle); // Skip header row
                        while (($data = fgetcsv($handle)) !== FALSE) {
                            if (count($data) >= 2) {
                                $holidays[] = [
                                    'date' => trim($data[0]),
                                    'name' => trim($data[1])
                                ];
                            }
                        }
                        fclose($handle);
                    }
                } else {
                    // Read Excel file (XLS/XLSX)
                    require_once '../vendor/autoload.php'; // If using PhpSpreadsheet
                    // For simplicity, we'll use a basic approach
                    // Note: You may need to install PhpSpreadsheet via Composer
                    // For now, we'll parse CSV-like data from Excel
                    $response['message'] = 'Excel file parsing requires PhpSpreadsheet library. Please use CSV format for now.';
                    echo json_encode($response);
                    exit;
                }
                
                // Process each holiday
                foreach ($holidays as $holiday) {
                    $holiday_date = $holiday['date'];
                    $holiday_name = $holiday['name'];
                    
                    if (empty($holiday_date) || empty($holiday_name)) {
                        $errorCount++;
                        $errors[] = "Skipped row with empty date or name";
                        continue;
                    }
                    
                    // Validate date format (YYYY-MM-DD)
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $holiday_date)) {
                        $errorCount++;
                        $errors[] = "Invalid date format for: $holiday_name (expected YYYY-MM-DD)";
                        continue;
                    }
                    
                    // Check if holiday already exists
                    $sql_check = "SELECT id FROM holidays WHERE holiday_date = ?";
                    if ($stmt_check = mysqli_prepare($conn, $sql_check)) {
                        mysqli_stmt_bind_param($stmt_check, "s", $holiday_date);
                        mysqli_stmt_execute($stmt_check);
                        mysqli_stmt_store_result($stmt_check);
                        if (mysqli_stmt_num_rows($stmt_check) > 0) {
                            $errorCount++;
                            $errors[] = "Holiday already exists for date: $holiday_date";
                            mysqli_stmt_close($stmt_check);
                            continue;
                        }
                        mysqli_stmt_close($stmt_check);
                    }
                    
                    // Insert holiday
                    $sql_insert = "INSERT INTO holidays (holiday_date, holiday_name, created_by) VALUES (?, ?, ?)";
                    if ($stmt_insert = mysqli_prepare($conn, $sql_insert)) {
                        mysqli_stmt_bind_param($stmt_insert, "ssi", $holiday_date, $holiday_name, $current_user_id);
                        if (mysqli_stmt_execute($stmt_insert)) {
                            $successCount++;
                        } else {
                            $errorCount++;
                            $errors[] = "Error inserting: $holiday_name - " . mysqli_error($conn);
                        }
                        mysqli_stmt_close($stmt_insert);
                    }
                }
                
                if ($successCount > 0) {
                    $response['status'] = 'success';
                    $response['message'] = "Successfully added $successCount holiday(s).";
                    if ($errorCount > 0) {
                        $response['message'] .= " $errorCount error(s) occurred.";
                        $response['errors'] = array_slice($errors, 0, 10); // Limit errors shown
                    }
                } else {
                    $response['message'] = "No holidays were added. " . ($errorCount > 0 ? implode(' ', array_slice($errors, 0, 5)) : '');
                }
            }
        }
    } elseif ($action == 'get_holidays') {
        $holidays = [];
        $sql_select = "SELECT id, holiday_date, holiday_name FROM holidays ORDER BY holiday_date ASC";
        $result = mysqli_query($conn, $sql_select);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                // Format date for display consistency if needed, e.g., date("d M Y", strtotime($row['holiday_date']))
                // For now, returning raw data.
                $holidays[] = $row;
            }
            $response['status'] = 'success';
            $response['holidays'] = $holidays;
            $response['message'] = 'Holidays fetched.';
        } else {
            $response['message'] = 'Error fetching holidays: ' . mysqli_error($conn);
        }
    }
}

echo json_encode($response);

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
