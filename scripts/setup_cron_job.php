<?php
/**
 * Cron Job Setup Script
 * This script helps set up the automatic sync cron job
 */

echo "=== LEAVE SYNC CRON JOB SETUP ===\n\n";

// Get current directory
$current_dir = __DIR__;
$project_root = dirname($current_dir);
$cron_script = $current_dir . '/cron_leave_sync.php';
$log_file = $project_root . '/logs/cron_sync.log';

echo "Project Root: {$project_root}\n";
echo "Cron Script: {$cron_script}\n";
echo "Log File: {$log_file}\n\n";

// Check if cron script exists
if (!file_exists($cron_script)) {
    echo "ERROR: Cron script not found at {$cron_script}\n";
    exit(1);
}

echo "✅ Cron script found\n";

// Check if log directory exists
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
    echo "✅ Created log directory: {$log_dir}\n";
} else {
    echo "✅ Log directory exists: {$log_dir}\n";
}

// Test the cron script
echo "\n=== TESTING CRON SCRIPT ===\n";
$test_command = "php " . escapeshellarg($cron_script);
echo "Running: {$test_command}\n\n";

$output = [];
$return_code = 0;
exec($test_command . " 2>&1", $output, $return_code);

if ($return_code === 0) {
    echo "✅ Cron script test successful!\n";
    echo "Output:\n";
    foreach ($output as $line) {
        echo "  {$line}\n";
    }
} else {
    echo "❌ Cron script test failed with return code: {$return_code}\n";
    echo "Output:\n";
    foreach ($output as $line) {
        echo "  {$line}\n";
    }
    exit(1);
}

echo "\n=== CRON JOB CONFIGURATION ===\n";
echo "To set up automatic sync, add this line to your crontab:\n\n";

// Different cron schedules
$cron_schedules = [
    "Every 10 minutes" => "*/10 * * * *",
    "Every 15 minutes" => "*/15 * * * *",
    "Every 5 minutes" => "*/5 * * * *",
    "Every 30 minutes" => "*/30 * * * *"
];

foreach ($cron_schedules as $description => $schedule) {
    echo "# {$description}\n";
    echo "{$schedule} cd " . escapeshellarg($project_root) . " && php " . escapeshellarg($cron_script) . " >> " . escapeshellarg($log_file) . " 2>&1\n\n";
}

echo "=== INSTRUCTIONS ===\n";
echo "1. Open terminal/command prompt\n";
echo "2. Run: crontab -e\n";
echo "3. Add one of the cron job lines above\n";
echo "4. Save and exit\n";
echo "5. Check if cron is running: crontab -l\n\n";

echo "=== VERIFICATION ===\n";
echo "After setting up the cron job:\n";
echo "1. Wait for the scheduled time\n";
echo "2. Check log file: {$log_file}\n";
echo "3. Look for 'CRON:' entries in the log\n\n";

echo "=== MANUAL TEST ===\n";
echo "To test manually, run:\n";
echo "php " . escapeshellarg($cron_script) . "\n\n";

echo "=== SETUP COMPLETE ===\n";
?>
