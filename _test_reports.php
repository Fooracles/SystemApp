<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notification_triggers.php';

// Check for key functions
$funcs = ['isLoggedIn','isAdmin','isManager','isClient','jsonError','handleException','csrfProtect','triggerClientReportNotification','ensureReportRecipientsTable'];
echo "=== FUNCTION CHECK ===" . PHP_EOL;
foreach($funcs as $f) {
    echo $f . ': ' . (function_exists($f) ? 'EXISTS' : 'MISSING') . PHP_EOL;
}

// Check reports table
echo PHP_EOL . "=== REPORTS TABLE ===" . PHP_EOL;
$result = mysqli_query($conn, 'DESCRIBE reports');
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo '  ' . $row['Field'] . ' (' . $row['Type'] . ') ' . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . PHP_EOL;
    }
} else {
    echo 'ERROR: ' . mysqli_error($conn) . PHP_EOL;
}

// Check upload settings
echo PHP_EOL . "=== PHP UPLOAD SETTINGS ===" . PHP_EOL;
echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;
echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;
echo 'max_file_uploads: ' . ini_get('max_file_uploads') . PHP_EOL;
echo 'max_execution_time: ' . ini_get('max_execution_time') . PHP_EOL;

// Check upload directory
echo PHP_EOL . "=== UPLOAD DIRECTORY ===" . PHP_EOL;
$upload_dir = __DIR__ . '/uploads/reports/';
echo 'Path: ' . $upload_dir . PHP_EOL;
echo 'Exists: ' . (is_dir($upload_dir) ? 'YES' : 'NO') . PHP_EOL;
echo 'Writable: ' . (is_writable($upload_dir) ? 'YES' : 'NO') . PHP_EOL;

// Check report_recipients table
echo PHP_EOL . "=== REPORT_RECIPIENTS TABLE ===" . PHP_EOL;
$result2 = mysqli_query($conn, "SHOW TABLES LIKE 'report_recipients'");
echo 'Exists: ' . ($result2 && mysqli_num_rows($result2) > 0 ? 'YES' : 'NO') . PHP_EOL;

echo PHP_EOL . "=== ALL CHECKS COMPLETE ===" . PHP_EOL;
