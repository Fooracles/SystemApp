<?php
/**
 * Migration Script: Create URL Sharing Tables
 * 
 * This script creates the personal_url_sharing and admin_url_sharing tables
 * if they don't already exist.
 * 
 * Run this script once to create the required tables for URL sharing functionality.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    die('Access denied. Admin login required.');
}

echo "<h2>Creating URL Sharing Tables</h2>";
echo "<pre>";

// Create personal_url_sharing table
$sql1 = "CREATE TABLE IF NOT EXISTS personal_url_sharing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url_id INT NOT NULL,
    shared_with_user_id INT NOT NULL,
    permission ENUM('view','comment','edit') DEFAULT 'view',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (url_id) REFERENCES personal_urls(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_share (url_id, shared_with_user_id),
    INDEX (url_id),
    INDEX (shared_with_user_id),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql1)) {
    echo "✓ Table 'personal_url_sharing' created successfully (or already exists)\n";
} else {
    echo "✗ Error creating 'personal_url_sharing': " . mysqli_error($conn) . "\n";
}

// Create admin_url_sharing table
$sql2 = "CREATE TABLE IF NOT EXISTS admin_url_sharing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url_id INT NOT NULL,
    shared_with_user_id INT NOT NULL,
    permission ENUM('view','comment','edit') DEFAULT 'view',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (url_id) REFERENCES admin_urls(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_share (url_id, shared_with_user_id),
    INDEX (url_id),
    INDEX (shared_with_user_id),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $sql2)) {
    echo "✓ Table 'admin_url_sharing' created successfully (or already exists)\n";
} else {
    echo "✗ Error creating 'admin_url_sharing': " . mysqli_error($conn) . "\n";
}

echo "\n";
echo "Migration completed!\n";
echo "</pre>";
echo "<p><a href='pages/useful_urls.php'>Go to Useful URLs</a></p>";
?>

