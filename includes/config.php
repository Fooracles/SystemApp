<?php

// Load centralized error handler FIRST (before any potential errors)
require_once __DIR__ . '/error_handler.php';

// Load centralized secure file upload handler
require_once __DIR__ . '/file_uploader.php';
// Set global timezone for consistent time calculations across the application
date_default_timezone_set('Asia/Kolkata');

// Configure session save path to avoid permission issues
// Use a directory within the project that should have write permissions
$session_dir = __DIR__ . '/../sessions';
if (!file_exists($session_dir)) {
    @mkdir($session_dir, 0755, true);
}
// Only set session save path if directory exists and is writable
if (file_exists($session_dir) && is_writable($session_dir)) {
    ini_set('session.save_path', $session_dir);
} elseif (file_exists($session_dir)) {
    // Try to make it writable
    @chmod($session_dir, 0755);
    if (is_writable($session_dir)) {
        ini_set('session.save_path', $session_dir);
    }
}

// Database configuration - Environment detection
$is_localhost = (
    (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1' || strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0)) ||
    (php_sapi_name() === 'cli' && !getenv('IS_PRODUCTION'))
);

if ($is_localhost) {
    // Local development settings
    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', 'root');           // Default XAMPP username
    define('DB_PASSWORD', '');               // Default XAMPP password (empty)
    define('DB_NAME', 'task_management');    // Your new database name
} else {
    // Production server settings
    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', 'u155861787_shubham');
    define('DB_PASSWORD', 'Fooracles@7z21');
    define('DB_NAME', 'u155861787_system_database');    // Your new database name
}

// OpenAI API key
//define('OPENAI_API_KEY', 'sk-proj-qKXn9xZ6-j6C613B7wUyj33z12-_7gN_gA8');

// Google Sheets API credentials path
define('GOOGLE_APPLICATION_CREDENTIALS', __DIR__ . '/../credentials.json');

// Leave Request Module Configuration
define('ENABLE_LEAVE_REQUESTS_PAGE', true);
define('GOOGLE_SA_EMAIL', 'fooracles-system-application@fooracles-system-upgrade.iam.gserviceaccount.com');
define('GOOGLE_SA_JSON_PATH', __DIR__ . '/../credentials.json');
define('LEAVE_SHEET_ID', '1uLjlLs1Nd1eumtP3XjCWa0BEa4yPqev1ato5kGr5UY0');
define('LEAVE_TAB_REQUESTS', 'Leave_Requests');
define('LEAVE_TAB_STATUS_APP', 'Leave_Status(Approve/Reject) via App');

// Attempt to connect to MySQL database with retry logic for "Too many connections"
$conn = null;
$max_retries = 3;
$retry_delay_ms = 500; // milliseconds

for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
    try {
        $conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($conn) {
            break;
        }
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Too many connections') !== false && $attempt < $max_retries) {
            usleep($retry_delay_ms * 1000);
            $retry_delay_ms *= 2;
            continue;
        }
        throw $e;
    }

    if (!$conn && $attempt < $max_retries) {
        $error = mysqli_connect_error();
        if (strpos($error, 'Too many connections') !== false) {
            usleep($retry_delay_ms * 1000);
            $retry_delay_ms *= 2;
            continue;
        }
    }
}

if (!$conn) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Set charset to UTF-8 for proper character handling (CRITICAL for live servers)
mysqli_set_charset($conn, "utf8mb4");

// Set MySQL timezone to match PHP timezone for consistent time calculations
mysqli_query($conn, "SET time_zone = '+05:30'");

// ============================================================================
// AUTO-CLOSE DATABASE CONNECTION ON SCRIPT END
// Ensures the MySQL connection is released back to the pool when the script
// finishes, regardless of how it exits (normal end, exit(), die(), fatal error).
// This prevents "Too many connections" errors on shared hosting.
// ============================================================================
register_shutdown_function(function () {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            mysqli_close($conn);
        } catch (\Error $e) {
            // Connection already closed — nothing to do
        }
    }
});


// Include database utility functions
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/db_schema.php';

// Check and create tables if needed
// Set to true to display table creation status on screen (useful for debugging)
$verbose_db_check = isset($_GET['show_db_status']) && $_GET['show_db_status'] === '1';

// Use the new createDatabaseTables function from db_schema.php
if (function_exists('createDatabaseTables')) {
    createDatabaseTables($conn, $verbose_db_check);
    // Run non-destructive migration guards for existing installations.
    if (function_exists('ensureFmsFlowNodeTypes')) {
        ensureFmsFlowNodeTypes($conn);
    }
    if (function_exists('ensureFmsFlowExecutionSchema')) {
        ensureFmsFlowExecutionSchema($conn);
    }
} else {
    // Fallback to old function if new one doesn't exist
    ensureDatabaseTables($conn, $verbose_db_check);
}