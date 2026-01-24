<?php
/**
 * Create Leave Database Structure
 * This script creates all necessary database tables and columns
 * for the leave request system in another project
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Create Leave Database Structure</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
    <style>
        body { background-color: #f8f9fa; }
        .card { margin-bottom: 20px; }
        .alert { margin-bottom: 15px; }
        .code-block { background-color: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 0.9rem; }
        .step-number { background: #007bff; color: white; border-radius: 50%; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; margin-right: 10px; }
        .sql-output { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 0.85rem; }
    </style>
</head>
<body>";

echo "<div class='container-fluid mt-4'>";
echo "<h1 class='text-center mb-4'><i class='fas fa-database'></i> Create Leave Database Structure</h1>";

// Database connection form
echo "<div class='card'>";
echo "<div class='card-header bg-primary text-white'>";
echo "<h5><i class='fas fa-cog'></i> Database Connection</h5>";
echo "</div>";
echo "<div class='card-body'>";

if (!isset($_POST['db_host'])) {
    echo "<form method='POST'>";
    echo "<div class='row'>";
    echo "<div class='col-md-6'>";
    echo "<div class='mb-3'>";
    echo "<label for='db_host' class='form-label'>Database Host:</label>";
    echo "<input type='text' class='form-control' id='db_host' name='db_host' value='localhost' required>";
    echo "</div>";
    echo "</div>";
    echo "<div class='col-md-6'>";
    echo "<div class='mb-3'>";
    echo "<label for='db_name' class='form-label'>Database Name:</label>";
    echo "<input type='text' class='form-control' id='db_name' name='db_name' required>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "<div class='row'>";
    echo "<div class='col-md-6'>";
    echo "<div class='mb-3'>";
    echo "<label for='db_user' class='form-label'>Database User:</label>";
    echo "<input type='text' class='form-control' id='db_user' name='db_user' required>";
    echo "</div>";
    echo "</div>";
    echo "<div class='col-md-6'>";
    echo "<div class='mb-3'>";
    echo "<label for='db_pass' class='form-label'>Database Password:</label>";
    echo "<input type='password' class='form-control' id='db_pass' name='db_pass'>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "<div class='mb-3'>";
    echo "<button type='submit' class='btn btn-primary'>";
    echo "<i class='fas fa-plug'></i> Connect to Database";
    echo "</button>";
    echo "</div>";
    echo "</form>";
} else {
    // Database connection
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    
    if (!$conn) {
        echo "<div class='alert alert-danger'>";
        echo "<i class='fas fa-exclamation-triangle'></i> Database connection failed: " . mysqli_connect_error();
        echo "</div>";
        echo "<a href='' class='btn btn-secondary'>Try Again</a>";
    } else {
        echo "<div class='alert alert-success'>";
        echo "<i class='fas fa-check-circle'></i> <strong>Database connected successfully!</strong><br>";
        echo "Host: $db_host | Database: $db_name | User: $db_user";
        echo "</div>";
        
        // Step 1: Create Leave_request table
        echo "<div class='card'>";
        echo "<div class='card-header bg-success text-white'>";
        echo "<h5><span class='step-number'>1</span> Create Leave_request Table</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        if (isset($_POST['create_leave_table'])) {
            $create_leave_table = "CREATE TABLE IF NOT EXISTS Leave_request (
                id INT AUTO_INCREMENT PRIMARY KEY,
                unique_service_no VARCHAR(64) UNIQUE,
                employee_name VARCHAR(128),
                employee_email VARCHAR(256),
                manager_name VARCHAR(128),
                manager_email VARCHAR(256),
                department VARCHAR(128),
                leave_type VARCHAR(64),
                duration VARCHAR(64),
                start_date DATE,
                end_date DATE NULL,
                reason TEXT,
                leave_count VARCHAR(16),
                file_url TEXT,
                status ENUM('Pending','Approve','Reject','Cancelled') DEFAULT 'Pending',
                sheet_timestamp DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (employee_email),
                INDEX (manager_email),
                INDEX (status),
                INDEX (start_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            if (mysqli_query($conn, $create_leave_table)) {
                echo "<div class='alert alert-success'>";
                echo "<i class='fas fa-check-circle'></i> <strong>Leave_request table created successfully!</strong>";
                echo "</div>";
                
                echo "<h6>Table Structure:</h6>";
                echo "<div class='sql-output'>";
                echo htmlspecialchars($create_leave_table);
                echo "</div>";
            } else {
                echo "<div class='alert alert-danger'>";
                echo "Error creating table: " . mysqli_error($conn);
                echo "</div>";
            }
        } else {
            echo "<div class='alert alert-info'>";
            echo "<i class='fas fa-info-circle'></i> This will create the main Leave_request table with all necessary columns and constraints.";
            echo "</div>";
            
            echo "<form method='POST'>";
            echo "<input type='hidden' name='db_host' value='$db_host'>";
            echo "<input type='hidden' name='db_name' value='$db_name'>";
            echo "<input type='hidden' name='db_user' value='$db_user'>";
            echo "<input type='hidden' name='db_pass' value='$db_pass'>";
            echo "<input type='hidden' name='create_leave_table' value='1'>";
            echo "<button type='submit' class='btn btn-success'>";
            echo "<i class='fas fa-plus'></i> Create Leave_request Table";
            echo "</button>";
            echo "</form>";
        }
        
        echo "</div></div>";
        
        // Step 2: Create leave_status_actions table
        echo "<div class='card'>";
        echo "<div class='card-header bg-info text-white'>";
        echo "<h5><span class='step-number'>2</span> Create leave_status_actions Table</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        if (isset($_POST['create_status_actions_table'])) {
            $create_status_table = "CREATE TABLE IF NOT EXISTS leave_status_actions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                unique_service_no VARCHAR(64),
                employee_name VARCHAR(128),
                manager_email VARCHAR(256),
                actor_email VARCHAR(256),
                action ENUM('Approve','Reject') NOT NULL,
                note TEXT,
                sheet_row_id VARCHAR(32) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (unique_service_no),
                INDEX (manager_email),
                INDEX (actor_email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            if (mysqli_query($conn, $create_status_table)) {
                echo "<div class='alert alert-success'>";
                echo "<i class='fas fa-check-circle'></i> <strong>leave_status_actions table created successfully!</strong>";
                echo "</div>";
                
                echo "<h6>Table Structure:</h6>";
                echo "<div class='sql-output'>";
                echo htmlspecialchars($create_status_table);
                echo "</div>";
            } else {
                echo "<div class='alert alert-danger'>";
                echo "Error creating table: " . mysqli_error($conn);
                echo "</div>";
            }
        } else {
            echo "<div class='alert alert-info'>";
            echo "<i class='fas fa-info-circle'></i> This will create the audit trail table for leave status actions.";
            echo "</div>";
            
            echo "<form method='POST'>";
            echo "<input type='hidden' name='db_host' value='$db_host'>";
            echo "<input type='hidden' name='db_name' value='$db_name'>";
            echo "<input type='hidden' name='db_user' value='$db_user'>";
            echo "<input type='hidden' name='db_pass' value='$db_pass'>";
            echo "<input type='hidden' name='create_status_actions_table' value='1'>";
            echo "<button type='submit' class='btn btn-info'>";
            echo "<i class='fas fa-plus'></i> Create leave_status_actions Table";
            echo "</button>";
            echo "</form>";
        }
        
        echo "</div></div>";
        
        // Step 3: Create user_notes table
        echo "<div class='card'>";
        echo "<div class='card-header bg-warning text-dark'>";
        echo "<h5><span class='step-number'>3</span> Create user_notes Table</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        if (isset($_POST['create_notes_table'])) {
            $create_notes_table = "CREATE TABLE IF NOT EXISTS user_notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_email VARCHAR(256),
                note_text TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (user_email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            if (mysqli_query($conn, $create_notes_table)) {
                echo "<div class='alert alert-success'>";
                echo "<i class='fas fa-check-circle'></i> <strong>user_notes table created successfully!</strong>";
                echo "</div>";
                
                echo "<h6>Table Structure:</h6>";
                echo "<div class='sql-output'>";
                echo htmlspecialchars($create_notes_table);
                echo "</div>";
            } else {
                echo "<div class='alert alert-danger'>";
                echo "Error creating table: " . mysqli_error($conn);
                echo "</div>";
            }
        } else {
            echo "<div class='alert alert-info'>";
            echo "<i class='fas fa-info-circle'></i> This will create the user notes table for personal notes.";
            echo "</div>";
            
            echo "<form method='POST'>";
            echo "<input type='hidden' name='db_host' value='$db_host'>";
            echo "<input type='hidden' name='db_name' value='$db_name'>";
            echo "<input type='hidden' name='db_user' value='$db_user'>";
            echo "<input type='hidden' name='db_pass' value='$db_pass'>";
            echo "<input type='hidden' name='create_notes_table' value='1'>";
            echo "<button type='submit' class='btn btn-warning'>";
            echo "<i class='fas fa-plus'></i> Create user_notes Table";
            echo "</button>";
            echo "</form>";
        }
        
        echo "</div></div>";
        
        // Step 4: Create all tables at once
        echo "<div class='card'>";
        echo "<div class='card-header bg-danger text-white'>";
        echo "<h5><span class='step-number'>4</span> Create All Tables at Once</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        if (isset($_POST['create_all_tables'])) {
            echo "<div class='alert alert-info'>Creating all tables...</div>";
            
            // Create Leave_request table
            $create_leave_table = "CREATE TABLE IF NOT EXISTS Leave_request (
                id INT AUTO_INCREMENT PRIMARY KEY,
                unique_service_no VARCHAR(64) UNIQUE,
                employee_name VARCHAR(128),
                employee_email VARCHAR(256),
                manager_name VARCHAR(128),
                manager_email VARCHAR(256),
                department VARCHAR(128),
                leave_type VARCHAR(64),
                duration VARCHAR(64),
                start_date DATE,
                end_date DATE NULL,
                reason TEXT,
                leave_count VARCHAR(16),
                file_url TEXT,
                status ENUM('Pending','Approve','Reject','Cancelled') DEFAULT 'Pending',
                sheet_timestamp DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (employee_email),
                INDEX (manager_email),
                INDEX (status),
                INDEX (start_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            // Create leave_status_actions table
            $create_status_table = "CREATE TABLE IF NOT EXISTS leave_status_actions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                unique_service_no VARCHAR(64),
                employee_name VARCHAR(128),
                manager_email VARCHAR(256),
                actor_email VARCHAR(256),
                action ENUM('Approve','Reject') NOT NULL,
                note TEXT,
                sheet_row_id VARCHAR(32) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (unique_service_no),
                INDEX (manager_email),
                INDEX (actor_email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            // Create user_notes table
            $create_notes_table = "CREATE TABLE IF NOT EXISTS user_notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_email VARCHAR(256),
                note_text TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (user_email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            $success_count = 0;
            $error_count = 0;
            
            // Execute all table creation queries
            if (mysqli_query($conn, $create_leave_table)) {
                echo "<div class='alert alert-success'>✓ Leave_request table created</div>";
                $success_count++;
            } else {
                echo "<div class='alert alert-danger'>✗ Error creating Leave_request: " . mysqli_error($conn) . "</div>";
                $error_count++;
            }
            
            if (mysqli_query($conn, $create_status_table)) {
                echo "<div class='alert alert-success'>✓ leave_status_actions table created</div>";
                $success_count++;
            } else {
                echo "<div class='alert alert-danger'>✗ Error creating leave_status_actions: " . mysqli_error($conn) . "</div>";
                $error_count++;
            }
            
            if (mysqli_query($conn, $create_notes_table)) {
                echo "<div class='alert alert-success'>✓ user_notes table created</div>";
                $success_count++;
            } else {
                echo "<div class='alert alert-danger'>✗ Error creating user_notes: " . mysqli_error($conn) . "</div>";
                $error_count++;
            }
            
            echo "<div class='alert alert-primary text-center'>";
            echo "<h4>Summary: $success_count tables created successfully, $error_count errors</h4>";
            echo "</div>";
            
        } else {
            echo "<div class='alert alert-info'>";
            echo "<i class='fas fa-info-circle'></i> This will create all three tables at once for faster setup.";
            echo "</div>";
            
            echo "<form method='POST'>";
            echo "<input type='hidden' name='db_host' value='$db_host'>";
            echo "<input type='hidden' name='db_name' value='$db_name'>";
            echo "<input type='hidden' name='db_user' value='$db_user'>";
            echo "<input type='hidden' name='db_pass' value='$db_pass'>";
            echo "<input type='hidden' name='create_all_tables' value='1'>";
            echo "<button type='submit' class='btn btn-danger'>";
            echo "<i class='fas fa-rocket'></i> Create All Tables";
            echo "</button>";
            echo "</form>";
        }
        
        echo "</div></div>";
        
        // Step 5: Generate SQL Script
        echo "<div class='card'>";
        echo "<div class='card-header bg-secondary text-white'>";
        echo "<h5><span class='step-number'>5</span> Generate SQL Script</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        echo "<div class='alert alert-info'>";
        echo "<i class='fas fa-info-circle'></i> Complete SQL script for manual execution:";
        echo "</div>";
        
        $sql_script = "-- Leave Request System Database Structure
-- Generated by create_leave_database_structure.php

-- 1. Create Leave_request table
CREATE TABLE IF NOT EXISTS Leave_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_service_no VARCHAR(64) UNIQUE,
    employee_name VARCHAR(128),
    employee_email VARCHAR(256),
    manager_name VARCHAR(128),
    manager_email VARCHAR(256),
    department VARCHAR(128),
    leave_type VARCHAR(64),
    duration VARCHAR(64),
    start_date DATE,
    end_date DATE NULL,
    reason TEXT,
    leave_count VARCHAR(16),
    file_url TEXT,
    status ENUM('Pending','Approve','Reject','Cancelled') DEFAULT 'Pending',
    sheet_timestamp DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (employee_email),
    INDEX (manager_email),
    INDEX (status),
    INDEX (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Create leave_status_actions table
CREATE TABLE IF NOT EXISTS leave_status_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_service_no VARCHAR(64),
    employee_name VARCHAR(128),
    manager_email VARCHAR(256),
    actor_email VARCHAR(256),
    action ENUM('Approve','Reject') NOT NULL,
    note TEXT,
    sheet_row_id VARCHAR(32) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (unique_service_no),
    INDEX (manager_email),
    INDEX (actor_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Create user_notes table
CREATE TABLE IF NOT EXISTS user_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(256),
    note_text TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Verify tables were created
SHOW TABLES LIKE 'Leave_request';
SHOW TABLES LIKE 'leave_status_actions';
SHOW TABLES LIKE 'user_notes';

-- 5. Check table structures
DESCRIBE Leave_request;
DESCRIBE leave_status_actions;
DESCRIBE user_notes;";
        
        echo "<div class='sql-output'>";
        echo htmlspecialchars($sql_script);
        echo "</div>";
        
        echo "<div class='mt-3'>";
        echo "<button class='btn btn-secondary' onclick='copyToClipboard()'>";
        echo "<i class='fas fa-copy'></i> Copy SQL Script";
        echo "</button>";
        echo "</div>";
        
        echo "</div></div>";
        
        // Step 6: Final Verification
        echo "<div class='card'>";
        echo "<div class='card-header bg-success text-white'>";
        echo "<h5><span class='step-number'>6</span> Final Verification</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        if (isset($_POST['verify_tables'])) {
            echo "<div class='alert alert-info'>Verifying tables...</div>";
            
            $tables_to_check = ['Leave_request', 'leave_status_actions', 'user_notes'];
            $existing_tables = [];
            
            foreach ($tables_to_check as $table) {
                $check_query = "SHOW TABLES LIKE '$table'";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $existing_tables[] = $table;
                    echo "<div class='alert alert-success'>✓ $table table exists</div>";
                } else {
                    echo "<div class='alert alert-danger'>✗ $table table missing</div>";
                }
            }
            
            echo "<div class='alert alert-primary text-center'>";
            echo "<h4>Verification Complete: " . count($existing_tables) . " tables found</h4>";
            echo "</div>";
            
        } else {
            echo "<div class='alert alert-info'>";
            echo "<i class='fas fa-info-circle'></i> Verify that all tables were created successfully.";
            echo "</div>";
            
            echo "<form method='POST'>";
            echo "<input type='hidden' name='db_host' value='$db_host'>";
            echo "<input type='hidden' name='db_name' value='$db_name'>";
            echo "<input type='hidden' name='db_user' value='$db_user'>";
            echo "<input type='hidden' name='db_pass' value='$db_pass'>";
            echo "<input type='hidden' name='verify_tables' value='1'>";
            echo "<button type='submit' class='btn btn-success'>";
            echo "<i class='fas fa-check'></i> Verify Tables";
            echo "</button>";
            echo "</form>";
        }
        
        echo "</div></div>";
    }
}

echo "</div></div>";

// JavaScript for copy functionality
echo "<script>
function copyToClipboard() {
    const sqlScript = `-- Leave Request System Database Structure
-- Generated by create_leave_database_structure.php

-- 1. Create Leave_request table
CREATE TABLE IF NOT EXISTS Leave_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_service_no VARCHAR(64) UNIQUE,
    employee_name VARCHAR(128),
    employee_email VARCHAR(256),
    manager_name VARCHAR(128),
    manager_email VARCHAR(256),
    department VARCHAR(128),
    leave_type VARCHAR(64),
    duration VARCHAR(64),
    start_date DATE,
    end_date DATE NULL,
    reason TEXT,
    leave_count VARCHAR(16),
    file_url TEXT,
    status ENUM('Pending','Approve','Reject','Cancelled') DEFAULT 'Pending',
    sheet_timestamp DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (employee_email),
    INDEX (manager_email),
    INDEX (status),
    INDEX (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Create leave_status_actions table
CREATE TABLE IF NOT EXISTS leave_status_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_service_no VARCHAR(64),
    employee_name VARCHAR(128),
    manager_email VARCHAR(256),
    actor_email VARCHAR(256),
    action ENUM('Approve','Reject') NOT NULL,
    note TEXT,
    sheet_row_id VARCHAR(32) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (unique_service_no),
    INDEX (manager_email),
    INDEX (actor_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Create user_notes table
CREATE TABLE IF NOT EXISTS user_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(256),
    note_text TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;`;
    
    navigator.clipboard.writeText(sqlScript).then(function() {
        alert('SQL script copied to clipboard!');
    }, function(err) {
        console.error('Could not copy text: ', err);
    });
}
</script>";

echo "</div>"; // End container

// Close connection if exists
if (isset($conn)) {
    mysqli_close($conn);
}

echo "</body></html>";
?>
