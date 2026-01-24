<?php
$page_title = "Holiday List";
require_once "../includes/header.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// --- Access Control: All Users Can View, Only Admin Can Add/Delete ---
// All logged-in users can view holidays
// Only admins can add/edit/delete holidays

$holiday_date = "";
$holiday_name = "";
$error_msg = "";
$success_msg = "";

// Handle Add Holiday form submission (Admin only)
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_holiday']) && isAdmin()) {
    $holiday_date = trim($_POST['holiday_date']);
    $holiday_name = trim($_POST['holiday_name']);

    if(empty($holiday_date)) {
        $error_msg = "Please select a holiday date.";
    } elseif(empty($holiday_name)) {
        $error_msg = "Please enter the holiday name.";
    } else {
        // Check if holiday already exists for that date
        $sql = "SELECT id FROM holidays WHERE holiday_date = ?";
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $holiday_date);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if(mysqli_stmt_num_rows($stmt) > 0) {
                $error_msg = "A holiday already exists for this date.";
            } else {
                // Insert new holiday
                $sql_insert = "INSERT INTO holidays (holiday_date, holiday_name) VALUES (?, ?)";
                if($stmt_insert = mysqli_prepare($conn, $sql_insert)) {
                    mysqli_stmt_bind_param($stmt_insert, "ss", $holiday_date, $holiday_name);
                    if(mysqli_stmt_execute($stmt_insert)) {
                        $success_msg = "Holiday added successfully!";
                        $holiday_date = ""; // Clear form
                        $holiday_name = ""; // Clear form
                    } else {
                        $error_msg = "Error adding holiday. Please try again.";
                    }
                    mysqli_stmt_close($stmt_insert);
                }
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_msg = "Database error. Please try again later.";
        }
    }
}

// Fetch all holidays for display
$holidays = array();
$sql_select_holidays = "SELECT id, holiday_date, holiday_name FROM holidays ORDER BY holiday_date ASC";
$result_holidays = mysqli_query($conn, $sql_select_holidays);
if($result_holidays && mysqli_num_rows($result_holidays) > 0) {
    while($row = mysqli_fetch_assoc($result_holidays)) {
        $holidays[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Task Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Include datepicker CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <style>
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1050;
        }
        .toast-message {
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
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
}

/* Enhanced Holiday List Styles - Dark Theme */
.holiday-page-container {
    background: var(--dark-bg-primary);
    background-image: 
        radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(6, 182, 212, 0.05) 0%, transparent 50%);
    min-height: 100vh;
    padding: 2rem 0;
    color: var(--dark-text-primary);
}

.holiday-header {
    background: var(--gradient-primary);
    color: var(--dark-text-primary);
    padding: 2rem;
    border-radius: var(--radius-lg);
    margin-bottom: 2rem;
    box-shadow: var(--glass-shadow);
    position: relative;
    overflow: hidden;
    border: 1px solid var(--glass-border);
}

.holiday-header h1 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.holiday-header h1 i {
    margin-right: 0;
}

.holiday-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

.holiday-stats {
    display: flex;
    gap: 2rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.stat-item {
    background: var(--dark-bg-glass);
    padding: 1rem;
    border-radius: var(--radius-md);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    color: var(--dark-text-primary);
    flex: 1;
    min-width: 150px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    display: block;
    color: var(--dark-text-primary);
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    color: var(--dark-text-secondary);
}

.search-filter-section {
    background: var(--dark-bg-card);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 2rem;
    box-shadow: var(--glass-shadow);
    border: 1px solid var(--glass-border);
    backdrop-filter: var(--glass-blur);
}

.search-input-group {
    position: relative;
    margin-bottom: 1rem;
}

.search-input {
    padding: 0.75rem 1rem 0.75rem 3rem;
    border: 2px solid var(--glass-border);
    border-radius: var(--radius-xl);
    font-size: 1rem;
    transition: var(--transition-normal);
    width: 100%;
    background: var(--dark-bg-secondary);
    color: var(--dark-text-primary);
}

.search-input:focus {
    border-color: var(--brand-primary);
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
    outline: none;
    background: var(--dark-bg-tertiary);
}

.search-input::placeholder {
    color: var(--dark-text-muted);
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--dark-text-muted);
    font-size: 1.1rem;
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 0.5rem 1rem;
    border: 2px solid var(--glass-border);
    background: var(--dark-bg-secondary);
    border-radius: var(--radius-xl);
    cursor: pointer;
    transition: var(--transition-normal);
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--dark-text-secondary);
}

.filter-btn:hover, .filter-btn.active {
    background: var(--gradient-primary);
    color: var(--dark-text-primary);
    border-color: var(--brand-primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.holiday-form-card {
    background: var(--dark-bg-card);
    border-radius: var(--radius-lg);
    box-shadow: var(--glass-shadow);
    border: 1px solid var(--glass-border);
    margin-bottom: 2rem;
    overflow: hidden;
    backdrop-filter: var(--glass-blur);
}

.holiday-form-header {
    background: var(--gradient-secondary);
    color: var(--dark-text-primary);
    padding: 1.5rem;
    font-weight: 600;
    font-size: 1.1rem;
}

.holiday-form-body {
    padding: 2rem;
    background: var(--dark-bg-secondary);
}

.form-floating {
    position: relative;
    margin-bottom: 1.5rem;
}

.form-floating input {
    padding: 1.25rem 0.75rem 0.5rem 2.5rem;
    border: 2px solid var(--glass-border);
    border-radius: var(--radius-md);
    font-size: 1rem;
    transition: var(--transition-normal);
    width: 100%;
    background: var(--dark-bg-tertiary);
    color: var(--dark-text-primary);
}

.form-floating input:focus {
    border-color: var(--brand-primary);
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
    outline: none;
    background: var(--dark-bg-card);
}

.form-floating .input-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--dark-text-muted);
    font-size: 1rem;
    pointer-events: none;
    z-index: 1;
}

.form-floating input:focus ~ .input-icon {
    color: var(--brand-primary);
}

.form-floating label {
    position: absolute;
    top: 0.75rem;
    left: 2.5rem;
    color: var(--dark-text-muted);
    transition: var(--transition-normal);
    pointer-events: none;
    font-size: 0.9rem;
    white-space: nowrap;
}

.form-floating input:focus ~ label,
.form-floating input:not(:placeholder-shown) ~ label {
    top: -0.5rem;
    left: 0.5rem;
    font-size: 0.75rem;
    color: var(--brand-primary);
    background: var(--dark-bg-secondary);
    padding: 0 0.5rem;
    z-index: 2;
}

.holiday-table-container {
    background: var(--dark-bg-card);
    border-radius: var(--radius-lg);
    box-shadow: var(--glass-shadow);
    border: 1px solid var(--glass-border);
    overflow: hidden;
    backdrop-filter: var(--glass-blur);
}

.holiday-table-header {
    background: var(--gradient-dark);
    color: var(--dark-text-primary);
    padding: 1.5rem;
    font-weight: 600;
    font-size: 1.1rem;
}

.holiday-table-header .filter-buttons {
    margin: 0;
    gap: 0.5rem;
}

.holiday-table-header .filter-btn {
    padding: 0.4rem 0.9rem;
    font-size: 0.85rem;
}

/* Add Holiday Button - Unique Color */
.btn-add-holiday {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: white;
    padding: 0.6rem 1.2rem;
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition-normal);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    white-space: nowrap;
}

.btn-add-holiday i {
    margin-right: 0;
    flex-shrink: 0;
}

.btn-add-holiday:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    color: white;
}

.btn-add-holiday:active {
    transform: translateY(0);
}

/* Modal Backdrop Blur */
.modal-backdrop-blur {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    background-color: rgba(0, 0, 0, 0.6) !important;
}

/* React Modal Styles */
.holiday-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.holiday-modal-content {
    background: var(--dark-bg-card);
    border-radius: var(--radius-lg);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    border: 1px solid var(--glass-border);
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    z-index: 10001;
}

.holiday-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--glass-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--gradient-secondary);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}

.holiday-modal-header h3 {
    margin: 0;
    color: var(--dark-text-primary);
    font-weight: 600;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.holiday-modal-header h3 i {
    margin-right: 0;
    flex-shrink: 0;
}

.holiday-modal-close {
    background: none;
    border: none;
    color: var(--dark-text-primary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    transition: var(--transition-normal);
}

.holiday-modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--dark-text-primary);
}

.holiday-modal-body {
    padding: 1.5rem;
}

.holiday-modal-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid var(--glass-border);
}

.holiday-modal-tab {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    color: var(--dark-text-secondary);
    cursor: pointer;
    font-weight: 500;
    font-size: 0.95rem;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: var(--transition-normal);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.holiday-modal-tab i {
    margin-right: 0;
    flex-shrink: 0;
}

.holiday-modal-tab.active {
    color: var(--brand-primary);
    border-bottom-color: var(--brand-primary);
}

.holiday-modal-tab:hover {
    color: var(--dark-text-primary);
}

.holiday-modal-form-group {
    margin-bottom: 1.5rem;
}

.holiday-modal-form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    color: var(--dark-text-primary);
    font-weight: 500;
    font-size: 0.9rem;
}

.holiday-modal-form-group label i {
    margin-right: 0;
    flex-shrink: 0;
}

.holiday-modal-form-group input,
.holiday-modal-form-group input[type="file"] {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid var(--glass-border);
    border-radius: var(--radius-md);
    background: var(--dark-bg-tertiary);
    color: var(--dark-text-primary);
    font-size: 0.95rem;
    transition: var(--transition-normal);
}

.holiday-modal-form-group input:focus {
    outline: none;
    border-color: var(--brand-primary);
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
    background: var(--dark-bg-card);
}

.holiday-modal-form-group input::placeholder {
    color: var(--dark-text-muted);
    opacity: 0.7;
}

/* Holiday Suggestions Dropdown */
.holiday-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--dark-bg-card);
    border: 2px solid var(--glass-border);
    border-radius: var(--radius-md);
    margin-top: 0.25rem;
    max-height: 200px;
    overflow-y: auto;
    z-index: 10002;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.holiday-suggestion-item {
    padding: 0.75rem 1rem;
    color: var(--dark-text-primary);
    cursor: pointer;
    transition: var(--transition-normal);
    border-bottom: 1px solid var(--glass-border);
}

.holiday-suggestion-item:last-child {
    border-bottom: none;
}

.holiday-suggestion-item:hover,
.holiday-suggestion-item.selected {
    background: var(--dark-bg-glass-hover);
    color: var(--brand-primary);
}

.holiday-suggestions::-webkit-scrollbar {
    width: 6px;
}

.holiday-suggestions::-webkit-scrollbar-track {
    background: var(--dark-bg-tertiary);
    border-radius: var(--radius-sm);
}

.holiday-suggestions::-webkit-scrollbar-thumb {
    background: var(--brand-primary);
    border-radius: var(--radius-sm);
}

.holiday-suggestions::-webkit-scrollbar-thumb:hover {
    background: var(--brand-primary-hover);
}

.holiday-modal-form-group input[type="file"] {
    padding: 0.5rem;
    cursor: pointer;
}

.holiday-modal-form-group input[type="file"]::file-selector-button {
    padding: 0.5rem 1rem;
    margin-right: 1rem;
    background: var(--gradient-primary);
    color: var(--dark-text-primary);
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-weight: 500;
    transition: var(--transition-normal);
}

.holiday-modal-form-group input[type="file"]::file-selector-button:hover {
    opacity: 0.9;
}

.holiday-modal-footer {
    padding: 1rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background: transparent;
    width: 60%;
    justify-content: center;
    align-items: center;
    position: relative;
    left: 50%;
    transform: translateX(-50%);
}

.holiday-modal-btn {
    padding: 0.6rem 1rem;
    border: none;
    border-radius: var(--radius-md);
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition-normal);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    z-index: -2;
}

.holiday-modal-btn-primary {
    background: var(--gradient-primary);
    color: var(--dark-text-primary);
}

.holiday-modal-btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.holiday-modal-btn-secondary {
    background: var(--dark-bg-tertiary);
    color: var(--dark-text-secondary);
    border: 1px solid var(--glass-border);
}

.holiday-modal-btn-secondary:hover {
    background: var(--dark-bg-glass-hover);
    color: var(--dark-text-primary);
}

.holiday-modal-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.holiday-modal-btn i {
    margin-right: 0;
    flex-shrink: 0;
}

.holiday-modal-btn .loading-spinner {
    margin-right: 0;
    flex-shrink: 0;
}

.holiday-modal-info {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-bottom: 1.5rem;
    color: var(--dark-text-primary);
    font-size: 0.9rem;
}

.holiday-modal-info i {
    color: var(--brand-primary);
    margin-right: 0.5rem;
}

.holiday-modal-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-bottom: 1.5rem;
    color: var(--dark-text-primary);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    animation: slideInDown 0.3s ease;
}

.holiday-modal-error i {
    color: #ef4444;
    flex-shrink: 0;
    font-size: 1.1rem;
}

@keyframes slideInDown {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Ensure calendar icon is visible */
.datepicker-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.datepicker-input-wrapper i {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--dark-text-muted);
    pointer-events: none;
    z-index: 3;
    filter: brightness(0) invert(1);
}

.datepicker-input-wrapper input[type="date"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
    padding: 0;
    border: none;
    left: 0;
    top: 0;
}

.datepicker-input-wrapper input[type="text"] {
    padding-left: 2.5rem !important;
    position: relative;
    z-index: 1;
    width: 100%;
    background: transparent;
}

.datepicker-input-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 0;
    width: 100%;
    height: 100%;
    position: absolute;
    left: 0;
    top: 0;
}

/* Fade out past holidays */
.holiday-table tbody tr.past-holiday {
    opacity: 0.5;
    filter: grayscale(0.3);
}

.holiday-table tbody tr.past-holiday:hover {
    opacity: 0.7;
    filter: grayscale(0.2);
}

.holiday-card.past-holiday {
    opacity: 0.5;
    filter: grayscale(0.3);
}

.holiday-table {
    width: 100%;
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.holiday-table thead th {
    background: var(--dark-bg-tertiary);
    color: var(--dark-text-primary);
    font-weight: 600;
    padding: 1rem;
    border-bottom: 2px solid var(--glass-border);
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 10;
}

.holiday-table tbody tr {
    transition: var(--transition-normal);
    border-bottom: 1px solid var(--glass-border);
    background: var(--dark-bg-card);
}

.holiday-table tbody tr:hover {
    background: var(--dark-bg-glass-hover);
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.holiday-table tbody td {
    padding: 1rem;
    vertical-align: middle;
    color: var(--dark-text-secondary);
}

.holiday-date {
    font-weight: 600;
    color: var(--brand-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.holiday-name {
    font-weight: 500;
    color: var(--dark-text-primary);
}

.holiday-status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-upcoming {
    background: rgba(6, 182, 212, 0.2);
    color: var(--brand-accent);
    border: 1px solid rgba(6, 182, 212, 0.4);
}

.status-current {
    background: rgba(16, 185, 129, 0.2);
    color: var(--brand-success);
    border: 1px solid rgba(16, 185, 129, 0.4);
}

.status-past {
    background: rgba(107, 114, 128, 0.2);
    color: var(--dark-text-muted);
    border: 1px solid rgba(107, 114, 128, 0.4);
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-delete {
    background: var(--gradient-accent);
    border: none;
    color: var(--dark-text-primary);
    padding: 0.5rem 1rem;
    border-radius: var(--radius-sm);
    font-size: 0.9rem;
    font-weight: 500;
    transition: var(--transition-normal);
    display: flex;
    align-items: center;
    gap: 0.25rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    color: var(--dark-text-primary);
}

.no-holidays {
    text-align: center;
    padding: 3rem;
    color: var(--dark-text-muted);
}

.no-holidays i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--dark-text-muted);
}

/* Mobile Responsive Design */
@media (max-width: 768px) {
    .holiday-page-container {
        padding: 1rem 0;
    }
    
    .holiday-header {
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .holiday-stats {
        gap: 1rem;
    }
    
    .stat-item {
        padding: 0.75rem;
        flex: 1;
        min-width: 120px;
    }
    
    .search-filter-section {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .filter-buttons {
        justify-content: center;
    }
    
    .holiday-table-container {
        overflow-x: auto;
    }
    
    .holiday-table {
        min-width: 600px;
    }
    
    .holiday-table thead th,
    .holiday-table tbody td {
        padding: 0.75rem 0.5rem;
        font-size: 0.9rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-delete {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
    }
}

/* Card Layout for Mobile */
@media (max-width: 576px) {
    .holiday-table-container {
        overflow: visible;
    }
    
    .holiday-table {
        display: none;
    }
    
    .holiday-cards {
        display: block;
        padding: 1rem;
    }
    
    .holiday-card {
        background: var(--dark-bg-card);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-bottom: 1rem;
        box-shadow: var(--glass-shadow);
        transition: var(--transition-normal);
        backdrop-filter: var(--glass-blur);
    }
    
    .holiday-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    }
    
    .holiday-card-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    
    .holiday-card-date {
        font-weight: 600;
        color: var(--brand-primary);
        font-size: 1.1rem;
    }
    
    .holiday-card-name {
        font-weight: 500;
        color: var(--dark-text-primary);
        margin-bottom: 0.5rem;
    }
    
    .holiday-card-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }
}

/* Loading Animation */
.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid var(--dark-bg-tertiary);
    border-top: 3px solid var(--brand-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Enhanced Toast Notifications */
.toast-message {
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    border: none;
    border-radius: var(--radius-md);
    box-shadow: var(--glass-shadow);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
    animation: slideInRight 0.3s ease;
    backdrop-filter: var(--glass-blur);
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast-message.success {
    background: var(--gradient-secondary);
    color: var(--dark-text-primary);
    border: 1px solid var(--glass-border);
}

.toast-message.danger {
    background: var(--gradient-accent);
    color: var(--dark-text-primary);
    border: 1px solid var(--glass-border);
}

.toast-message i {
    font-size: 1.2rem;
}

/* Additional Dark Theme Enhancements */
.holiday-page-container h1,
.holiday-page-container h2,
.holiday-page-container h3,
.holiday-page-container h4,
.holiday-page-container h5,
.holiday-page-container h6 {
    color: var(--dark-text-primary);
}

.holiday-page-container p {
    color: var(--dark-text-secondary);
}

.holiday-page-container .text-muted {
    color: var(--dark-text-muted) !important;
}

/* Enhanced button hover effects */
.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

/* Form input placeholder styling */
.form-floating input::placeholder {
    color: var(--dark-text-muted);
    opacity: 0.7;
}

/* Enhanced glassmorphism effects */
.holiday-header,
.holiday-form-card,
.holiday-table-container,
.search-filter-section {
    backdrop-filter: var(--glass-blur);
    -webkit-backdrop-filter: var(--glass-blur);
}

/* Improved focus states */
.form-floating input:focus,
.search-input:focus,
.filter-btn:focus {
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
}

/* Enhanced table row striping */
.holiday-table tbody tr:nth-child(even) {
    background: rgba(255, 255, 255, 0.02);
}

.holiday-table tbody tr:nth-child(odd) {
    background: var(--dark-bg-card);
}
</style>
</head>
<body class="dark-theme">
        <!-- Content will be wrapped by header.php -->
    
    <div id="toast-container" class="toast-container"></div>
    <div id="holiday-react-root"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Include datepicker JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <!-- React and ReactDOM -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <!-- Babel Standalone for JSX -->
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <!-- SheetJS for Excel file parsing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script type="text/babel">
        // Global toast function
        window.showToast = function(message, type = 'success') {
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast-message ${type === 'success' ? 'success' : 'danger'}`;
            toast.role = 'alert';
            toast.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            toastContainer.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 5000);
        };

        // Initial data from PHP
        const initialHolidays = <?php echo json_encode($holidays); ?>;
        const isAdminUser = <?php echo isAdmin() ? 'true' : 'false'; ?>;
        const username = <?php echo json_encode($_SESSION["username"] ?? ''); ?>;
        const pageTitle = <?php echo json_encode($page_title); ?>;

        // Helper function
        function getHolidayStatus(holidayDate) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const holiday = new Date(holidayDate);
            holiday.setHours(0, 0, 0, 0);
            const diffTime = holiday - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            if (diffDays < 0) return 'past';
            if (diffDays === 0) return 'current';
            return 'upcoming';
        }

        // React Components
        const { useState, useEffect, useRef, useCallback } = React;

        // Statistics Component
        function Statistics({ holidays }) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const total = holidays.length;
            const upcoming = holidays.filter(h => {
                const d = new Date(h.holiday_date);
                d.setHours(0, 0, 0, 0);
                return d > today;
            }).length;
            const past = holidays.filter(h => {
                const d = new Date(h.holiday_date);
                d.setHours(0, 0, 0, 0);
                return d < today;
            }).length;

            return React.createElement('div', { className: 'holiday-stats' },
                React.createElement('div', { className: 'stat-item' },
                    React.createElement('span', { className: 'stat-number' }, total),
                    React.createElement('span', { className: 'stat-label' }, 'Total Holidays')
                ),
                React.createElement('div', { className: 'stat-item' },
                    React.createElement('span', { className: 'stat-number' }, upcoming),
                    React.createElement('span', { className: 'stat-label' }, 'Upcoming')
                ),
                React.createElement('div', { className: 'stat-item' },
                    React.createElement('span', { className: 'stat-number' }, past),
                    React.createElement('span', { className: 'stat-label' }, 'Past')
                )
            );
        }

        // Filter Buttons Component
        function FilterButtons({ currentFilter, onFilterChange }) {
            return React.createElement('div', { className: 'filter-buttons' },
                React.createElement('button', {
                    className: `filter-btn ${currentFilter === 'all' ? 'active' : ''}`,
                    onClick: () => onFilterChange('all')
                }, 'All Holidays'),
                React.createElement('button', {
                    className: `filter-btn ${currentFilter === 'upcoming' ? 'active' : ''}`,
                    onClick: () => onFilterChange('upcoming')
                }, 'Upcoming'),
                React.createElement('button', {
                    className: `filter-btn ${currentFilter === 'past' ? 'active' : ''}`,
                    onClick: () => onFilterChange('past')
                }, 'Past')
            );
        }

        // Holiday Table Row Component
        function HolidayRow({ holiday, canDelete, onDelete }) {
            const date = new Date(holiday.holiday_date + 'T00:00:00');
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const formattedDate = `${day}/${month}/${year}`;
            const status = getHolidayStatus(holiday.holiday_date);
            const statusClass = `status-${status}`;
            const statusText = status === 'upcoming' ? 'Upcoming' : status === 'current' ? 'Today' : 'Past';
            const statusIcon = status === 'upcoming' ? 'fa-clock' : status === 'current' ? 'fa-star' : 'fa-history';
            const pastClass = status === 'past' ? 'past-holiday' : '';

            return React.createElement('tr', { className: pastClass },
                React.createElement('td', null,
                    React.createElement('div', { className: 'holiday-date' },
                        React.createElement('i', { className: 'fas fa-calendar-alt' }),
                        ' ' + formattedDate
                    )
                ),
                React.createElement('td', null,
                    React.createElement('div', { className: 'holiday-name' }, holiday.holiday_name)
                ),
                React.createElement('td', null,
                    React.createElement('span', { className: `holiday-status ${statusClass}` },
                        React.createElement('i', { className: `fas ${statusIcon}` }),
                        ' ' + statusText
                    )
                ),
                canDelete && React.createElement('td', null,
                    React.createElement('div', { className: 'action-buttons' },
                        React.createElement('button', {
                            className: 'btn-delete',
                            onClick: () => onDelete(holiday.id, holiday.holiday_name)
                        },
                            React.createElement('i', { className: 'fas fa-trash' }),
                            ' Delete'
                        )
                    )
                )
            );
        }

        // Holiday Table Component
        function HolidayTable({ holidays, canDelete, onDelete }) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const sortedHolidays = [...holidays].sort((a, b) => {
                const dateA = new Date(a.holiday_date);
                dateA.setHours(0, 0, 0, 0);
                const dateB = new Date(b.holiday_date);
                dateB.setHours(0, 0, 0, 0);
                const isUpcomingA = dateA >= today;
                const isUpcomingB = dateB >= today;
                if (isUpcomingA && !isUpcomingB) return -1;
                if (!isUpcomingA && isUpcomingB) return 1;
                return dateA - dateB;
            });

            if (sortedHolidays.length === 0) {
                return React.createElement('div', { className: 'no-holidays' },
                    React.createElement('i', { className: 'fas fa-calendar-times' }),
                    React.createElement('h5', null, 'No holidays found'),
                    React.createElement('p', null, 'Try adjusting your filter criteria')
                );
            }

            return React.createElement('table', { className: 'holiday-table' },
                React.createElement('thead', null,
                    React.createElement('tr', null,
                        React.createElement('th', null, 'Date'),
                        React.createElement('th', null, 'Holiday Name'),
                        React.createElement('th', null, 'Status'),
                        canDelete && React.createElement('th', null,
                            React.createElement('i', { className: 'fas fa-cog me-1' }),
                            'Actions'
                        )
                    )
                ),
                React.createElement('tbody', null,
                    ...sortedHolidays.map(holiday =>
                        React.createElement(HolidayRow, {
                            key: holiday.id,
                            holiday: holiday,
                            canDelete: canDelete,
                            onDelete: onDelete
                        })
                    )
                )
            );
        }

        // Add Holiday Modal Component

            function AddHolidayModal({ isOpen, onClose, onSuccess }) {
                const [activeTab, setActiveTab] = useState('single');
                const [holidayDate, setHolidayDate] = useState('');
                const [holidayDateDisplay, setHolidayDateDisplay] = useState('');
                const [holidayName, setHolidayName] = useState('');
                const [file, setFile] = useState(null);
                const [loading, setLoading] = useState(false);
                const [suggestions, setSuggestions] = useState([]);
                const [showSuggestions, setShowSuggestions] = useState(false);
                const [selectedSuggestionIndex, setSelectedSuggestionIndex] = useState(-1);
                const [errorMessage, setErrorMessage] = useState('');
                const dateInputRef = useRef(null);
                const dateDisplayRef = useRef(null);
                const nameInputRef = useRef(null);
                const suggestionsRef = useRef(null);

                // Common holidays with abbreviations
                const holidaySuggestions = [
                    'Diwali', 'Holi', 'Christmas', 'New Year\'s Day', 'Eid', 'Eid al-Fitr', 'Eid al-Adha',
                    'Independence Day', 'Republic Day', 'Gandhi Jayanti', 'Dussehra', 'Raksha Bandhan',
                    'Janmashtami', 'Ganesh Chaturthi', 'Navratri', 'Durga Puja', 'Krishna Janmashtami',
                    'Makar Sankranti', 'Pongal', 'Onam', 'Baisakhi', 'Guru Nanak Jayanti',
                    'Good Friday', 'Easter', 'Easter Sunday', 'Thanksgiving', 'Halloween',
                    'Valentine\'s Day', 'Mother\'s Day', 'Father\'s Day', 'Labour Day',
                    'Republic Day', 'Independence Day', 'Gandhi Jayanti', 'Children\'s Day',
                    'Teacher\'s Day', 'Makar Sankranti', 'Pongal', 'Onam', 'Baisakhi'
                ];

                const formatDateToDDMMYYYY = (dateString) => {
                    if (!dateString) return '';
                    const date = new Date(dateString + 'T00:00:00');
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear();
                    return `${day}/${month}/${year}`;
                };

                const parseDDMMYYYYToYYYYMMDD = (dateString) => {
                    if (!dateString) return '';
                    const parts = dateString.split('/');
                    if (parts.length !== 3) return '';
                    const day = parts[0].padStart(2, '0');
                    const month = parts[1].padStart(2, '0');
                    const year = parts[2];
                    return `${year}-${month}-${day}`;
                };

                const openNativeDatePicker = () => {
                    if (dateInputRef.current) {
                        dateInputRef.current.showPicker();
                    }
                };

                const handleDateChange = (e) => {
                    const value = e.target.value;
                    setHolidayDate(value);
                    setHolidayDateDisplay(formatDateToDDMMYYYY(value));
                };

                const handleDateDisplayChange = (e) => {
                    const value = e.target.value;
                    setHolidayDateDisplay(value);
                    const yyyymmdd = parseDDMMYYYYToYYYYMMDD(value);
                    if (yyyymmdd) {
                        setHolidayDate(yyyymmdd);
                        if (dateInputRef.current) {
                            dateInputRef.current.value = yyyymmdd;
                        }
                    }
                };

                const handleDateDisplayFocus = () => {
                    if (dateInputRef.current) {
                        dateInputRef.current.showPicker();
                    }
                };

                const handleHolidayNameChange = (e) => {
                    const value = e.target.value;
                    setHolidayName(value);
                    setSelectedSuggestionIndex(-1);
                    
                    if (value.length > 0) {
                        const filtered = holidaySuggestions.filter(holiday => 
                            holiday.toLowerCase().includes(value.toLowerCase())
                        );
                        setSuggestions(filtered);
                        setShowSuggestions(filtered.length > 0);
                    } else {
                        setSuggestions([]);
                        setShowSuggestions(false);
                    }
                };

                const handleSuggestionSelect = (suggestion) => {
                    setHolidayName(suggestion);
                    setSuggestions([]);
                    setShowSuggestions(false);
                    setSelectedSuggestionIndex(-1);
                    if (nameInputRef.current) {
                        nameInputRef.current.focus();
                    }
                };

                const handleKeyDown = (e) => {
                    if (!showSuggestions || suggestions.length === 0) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setSelectedSuggestionIndex(prev => 
                            prev < suggestions.length - 1 ? prev + 1 : prev
                        );
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        setSelectedSuggestionIndex(prev => prev > 0 ? prev - 1 : -1);
                    } else if (e.key === 'Enter' && selectedSuggestionIndex >= 0) {
                        e.preventDefault();
                        handleSuggestionSelect(suggestions[selectedSuggestionIndex]);
                    } else if (e.key === 'Escape') {
                        setShowSuggestions(false);
                        setSelectedSuggestionIndex(-1);
                    }
                };

                const handleNameInputFocus = () => {
                    if (holidayName.length > 0 && suggestions.length > 0) {
                        setShowSuggestions(true);
                    }
                };

                const handleNameInputBlur = (e) => {
                    // Delay to allow click on suggestion
                    setTimeout(() => {
                        if (suggestionsRef.current && !suggestionsRef.current.contains(document.activeElement)) {
                            setShowSuggestions(false);
                        }
                    }, 200);
                };

                const handleSingleSubmit = async (e) => {
                    e.preventDefault();
                    if (!holidayDate || !holidayName) {
                        setErrorMessage('Please fill in both date and name.');
                        return;
                    }

                    setErrorMessage(''); // Clear any previous errors
                    setLoading(true);
                    try {
                        const formData = new FormData();
                        formData.append('action', 'add_holiday');
                        formData.append('holiday_date', holidayDate);
                        formData.append('holiday_name', holidayName);

                        const response = await fetch('../ajax/holiday_handler.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();
                        
                        if (result.status === 'success') {
                            showToast(result.message, result.status);
                            setHolidayDate('');
                            setHolidayDateDisplay('');
                            setHolidayName('');
                            setSuggestions([]);
                            setShowSuggestions(false);
                            setErrorMessage(''); // Clear error on success
                            if (dateInputRef.current) {
                                dateInputRef.current.value = '';
                            }
                            onSuccess();
                            onClose();
                        } else {
                            // Display error inside modal
                            setErrorMessage(result.message || 'An error occurred. Please try again.');
                        }
                    } catch (error) {
                        setErrorMessage('Error communicating with server. Please try again.');
                    } finally {
                        setLoading(false);
                    }
                };

                const handleBulkSubmit = async (e) => {
                    e.preventDefault();
                    if (!file) {
                        setErrorMessage('Please select a file to upload.');
                        return;
                    }

                    setErrorMessage(''); // Clear any previous errors
                    setLoading(true);
                    try {
                        const formData = new FormData();
                        formData.append('action', 'bulk_upload_holidays');
                        formData.append('file', file);

                        const response = await fetch('../ajax/holiday_handler.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();
                        
                        if (result.status === 'success') {
                            showToast(result.message, result.status);
                            setFile(null);
                            setErrorMessage(''); // Clear error on success
                            onSuccess();
                            onClose();
                        } else {
                            // Display error inside modal
                            setErrorMessage(result.message || 'An error occurred. Please try again.');
                        }
                    } catch (error) {
                        setErrorMessage('Error communicating with server. Please try again.');
                    } finally {
                        setLoading(false);
                    }
                };

                const handleFileChange = (e) => {
                    const selectedFile = e.target.files[0];
                    if (selectedFile) {
                        const fileExtension = selectedFile.name.split('.').pop().toLowerCase();
                        if (fileExtension === 'csv' || fileExtension === 'xls' || fileExtension === 'xlsx') {
                            setFile(selectedFile);
                        } else {
                            showToast('Please select a CSV or Excel file.', 'danger');
                            e.target.value = '';
                        }
                    }
                };

                const downloadTemplate = () => {
                    const csvContent = 'holiday_date,holiday_name\n2024-01-01,New Year\'s Day\n2024-12-25,Christmas';
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', 'holiday_template.csv');
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                };

                // Clear error message when modal closes or tab changes
                useEffect(() => {
                    if (!isOpen) {
                        setErrorMessage('');
                    }
                }, [isOpen]);

                useEffect(() => {
                    setErrorMessage(''); // Clear error when switching tabs
                }, [activeTab]);

                if (!isOpen) return null;

                return ReactDOM.createPortal(
                    React.createElement('div', { className: 'holiday-modal-overlay', onClick: onClose },
                        React.createElement('div', { className: 'holiday-modal-content', onClick: (e) => e.stopPropagation() },
                            React.createElement('div', { className: 'holiday-modal-header' },
                                React.createElement('h3', null,
                                    React.createElement('i', { className: 'fas fa-calendar-plus me-2' }),
                                    'Add Holiday'
                                ),
                                React.createElement('button', { className: 'holiday-modal-close', onClick: onClose },
                                    React.createElement('i', { className: 'fas fa-times' })
                                )
                            ),
                            React.createElement('div', { className: 'holiday-modal-body' },
                                React.createElement('div', { className: 'holiday-modal-tabs' },
                                    React.createElement('button', {
                                        className: `holiday-modal-tab ${activeTab === 'single' ? 'active' : ''}`,
                                        onClick: () => setActiveTab('single')
                                    },
                                        React.createElement('i', { className: 'fas fa-plus-circle me-2' }),
                                        'Single Holiday'
                                    ),
                                    React.createElement('button', {
                                        className: `holiday-modal-tab ${activeTab === 'bulk' ? 'active' : ''}`,
                                        onClick: () => setActiveTab('bulk')
                                    },
                                        React.createElement('i', { className: 'fas fa-upload me-2' }),
                                        'Bulk Upload'
                                    )
                                ),
                                errorMessage && React.createElement('div', {
                                    className: 'holiday-modal-error'
                                },
                                    React.createElement('i', { className: 'fas fa-exclamation-circle' }),
                                    React.createElement('span', null, errorMessage)
                                ),
                                activeTab === 'single' ? (
                                    React.createElement('form', { onSubmit: handleSingleSubmit },
                                        React.createElement('div', { className: 'holiday-modal-form-group' },
                                            React.createElement('label', { htmlFor: 'modal-holiday-date' },
                                                React.createElement('i', { className: 'fas fa-calendar me-2' }),
                                                'Holiday Date'
                                            ),
                                            React.createElement('div', { className: 'datepicker-input-wrapper' },
                                                React.createElement('i', { 
                                                    className: 'fas fa-calendar',
                                                    onClick: openNativeDatePicker,
                                                    style: { cursor: 'pointer', pointerEvents: 'auto' }
                                                }),
                                                React.createElement('input', {
                                                    ref: dateInputRef,
                                                    type: 'date',
                                                    id: 'modal-holiday-date',
                                                    className: 'form-control',
                                                    style: { position: 'absolute', opacity: 0, width: '100%', height: '100%', cursor: 'pointer', zIndex: 2 },
                                                    value: holidayDate,
                                                    onChange: handleDateChange,
                                                    required: true
                                                }),
                                                React.createElement('input', {
                                                    ref: dateDisplayRef,
                                                    type: 'text',
                                                    className: 'form-control',
                                                    placeholder: 'DD/MM/YYYY',
                                                    value: holidayDateDisplay,
                                                    onChange: handleDateDisplayChange,
                                                    onFocus: handleDateDisplayFocus,
                                                    pattern: '\\d{2}/\\d{2}/\\d{4}',
                                                    style: { position: 'relative', zIndex: 1 }
                                                })
                                            )
                                        ),
                                        React.createElement('div', { className: 'holiday-modal-form-group', style: { position: 'relative' } },
                                            React.createElement('label', { htmlFor: 'modal-holiday-name' },
                                                React.createElement('i', { className: 'fas fa-tag me-2' }),
                                                'Holiday Name'
                                            ),
                                            React.createElement('div', { style: { position: 'relative' } },
                                                React.createElement('input', {
                                                    ref: nameInputRef,
                                                    type: 'text',
                                                    id: 'modal-holiday-name',
                                                    className: 'form-control',
                                                    value: holidayName,
                                                    onChange: handleHolidayNameChange,
                                                    onKeyDown: handleKeyDown,
                                                    onFocus: handleNameInputFocus,
                                                    onBlur: handleNameInputBlur,
                                                    placeholder: 'Enter holiday name',
                                                    required: true,
                                                    autoComplete: 'off'
                                                }),
                                                showSuggestions && suggestions.length > 0 && React.createElement('div', {
                                                    ref: suggestionsRef,
                                                    className: 'holiday-suggestions',
                                                    onMouseDown: (e) => e.preventDefault()
                                                },
                                                    suggestions.map((suggestion, index) =>
                                                        React.createElement('div', {
                                                            key: index,
                                                            className: `holiday-suggestion-item ${selectedSuggestionIndex === index ? 'selected' : ''}`,
                                                            onClick: () => handleSuggestionSelect(suggestion),
                                                            onMouseEnter: () => setSelectedSuggestionIndex(index)
                                                        }, suggestion)
                                                    )
                                                )
                                            )
                                        ),
                                        React.createElement('div', { className: 'holiday-modal-footer' },
                                            React.createElement('button', {
                                                type: 'submit',
                                                className: 'holiday-modal-btn holiday-modal-btn-primary',
                                                disabled: loading
                                            },
                                                loading ? (
                                                    React.createElement(React.Fragment, null,
                                                        React.createElement('div', { className: 'loading-spinner me-2' }),
                                                        'Adding...'
                                                    )
                                                ) : (
                                                    React.createElement(React.Fragment, null,
                                                        React.createElement('i', { className: 'fas fa-plus me-2' }),
                                                        'Add Holiday'
                                                    )
                                                )
                                            )
                                        )
                                    )
                                ) : (
                                    React.createElement('form', { onSubmit: handleBulkSubmit },
                                        React.createElement('div', { className: 'holiday-modal-info' },
                                            React.createElement('i', { className: 'fas fa-info-circle' }),
                                            ' Upload a CSV or Excel file with columns: ',
                                            React.createElement('strong', null, 'holiday_date'),
                                            ' and ',
                                            React.createElement('strong', null, 'holiday_name')
                                        ),
                                        React.createElement('div', { className: 'holiday-modal-form-group' },
                                            React.createElement('label', { htmlFor: 'bulk-file-upload' },
                                                React.createElement('i', { className: 'fas fa-file-upload me-2' }),
                                                'Select File (CSV or Excel)'
                                            ),
                                            React.createElement('input', {
                                                type: 'file',
                                                id: 'bulk-file-upload',
                                                accept: '.csv,.xls,.xlsx',
                                                onChange: handleFileChange,
                                                required: true
                                            }),
                                            file && React.createElement('p', {
                                                style: { marginTop: '0.5rem', color: 'var(--dark-text-secondary)', fontSize: '0.85rem' }
                                            },
                                                React.createElement('i', { className: 'fas fa-check-circle me-2', style: { color: '#10b981' } }),
                                                'Selected: ',
                                                file.name
                                            )
                                        ),
                                        React.createElement('div', { className: 'holiday-modal-form-group' },
                                            React.createElement('button', {
                                                type: 'button',
                                                className: 'holiday-modal-btn holiday-modal-btn-secondary',
                                                onClick: downloadTemplate
                                            },
                                                React.createElement('i', { className: 'fas fa-download me-2' }),
                                                'Download Template'
                                            )
                                        ),
                                        React.createElement('div', { className: 'holiday-modal-footer' },
                                            React.createElement('button', {
                                                type: 'submit',
                                                className: 'holiday-modal-btn holiday-modal-btn-primary',
                                                disabled: loading || !file
                                            },
                                                loading ? (
                                                    React.createElement(React.Fragment, null,
                                                        React.createElement('div', { className: 'loading-spinner me-2' }),
                                                        'Uploading...'
                                                    )
                                                ) : (
                                                    React.createElement(React.Fragment, null,
                                                        React.createElement('i', { className: 'fas fa-upload me-2' }),
                                                        'Upload Holidays'
                                                    )
                                                )
                                            )
                                        )
                                    )
                                )
                            )
                        )
                    ),
                    document.body
                );
            }

        // Main Holiday List App Component
        function HolidayListApp() {
            // Initialize with upcoming filter applied
            const getInitialFilteredHolidays = () => {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                return initialHolidays.filter(holiday => {
                    const holidayDate = new Date(holiday.holiday_date);
                    holidayDate.setHours(0, 0, 0, 0);
                    const status = getHolidayStatus(holiday.holiday_date);
                    return status === 'upcoming';
                });
            };

            const [holidays, setHolidays] = useState(initialHolidays);
            const [filteredHolidays, setFilteredHolidays] = useState(getInitialFilteredHolidays());
            const [currentFilter, setCurrentFilter] = useState('upcoming');
            const [loading, setLoading] = useState(false);
            const [isModalOpen, setIsModalOpen] = useState(false);

            useEffect(() => {
                let filtered = [...holidays];
                if (currentFilter !== 'all') {
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    filtered = filtered.filter(holiday => {
                        const holidayDate = new Date(holiday.holiday_date);
                        holidayDate.setHours(0, 0, 0, 0);
                        const status = getHolidayStatus(holiday.holiday_date);
                        switch (currentFilter) {
                            case 'upcoming': return status === 'upcoming';
                            case 'past': return status === 'past';
                            default: return true;
                        }
                    });
                }
                setFilteredHolidays(filtered);
            }, [holidays, currentFilter]);

            const fetchHolidays = useCallback(async () => {
                setLoading(true);
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_holidays');
                    const response = await fetch('../ajax/holiday_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        setHolidays(result.holidays);
                    } else {
                        window.showToast(result.message || 'Error fetching holidays.', 'danger');
                    }
                } catch (error) {
                    window.showToast('Failed to communicate with server.', 'danger');
                } finally {
                    setLoading(false);
                }
            }, []);

            const handleDelete = useCallback(async (holidayId, holidayName) => {
                if (!confirm(`Are you sure you want to delete "${holidayName}"?\n\nThis action cannot be undone.`)) {
                    return;
                }
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_holiday');
                    formData.append('holiday_id', holidayId);
                    const response = await fetch('../ajax/holiday_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    window.showToast(result.message, result.status);
                    if (result.status === 'success') {
                        fetchHolidays();
                    }
                } catch (error) {
                    window.showToast('Error communicating with server.', 'danger');
                }
            }, [fetchHolidays]);

            const handleModalSuccess = useCallback(() => {
                fetchHolidays();
            }, [fetchHolidays]);

            return React.createElement('div', { className: 'holiday-page-container' },
                React.createElement('div', { className: 'container' },
                    React.createElement('div', { className: 'holiday-header' },
                        React.createElement('div', { className: 'd-flex justify-content-between align-items-center' },
                            React.createElement('div', null,
                                React.createElement('h1', { className: 'mb-2' },
                                    React.createElement('i', { className: 'fas fa-calendar-alt me-2' }),
                                    pageTitle
                                ),
                                React.createElement('p', { className: 'mb-0 opacity-75' }, 'Manage and view all company holidays')
                            )
                        ),
                        React.createElement(Statistics, { holidays: filteredHolidays })
                    ),
                    React.createElement('div', { className: 'holiday-table-container' },
                        React.createElement('div', { className: 'holiday-table-header' },
                            React.createElement('div', { className: 'd-flex justify-content-between align-items-center' },
                                React.createElement('span', null, 'Holiday Calendar'),
                                React.createElement('div', { className: 'd-flex align-items-center', style: { gap: '1rem' } },
                                    React.createElement(FilterButtons, {
                                        currentFilter: currentFilter,
                                        onFilterChange: setCurrentFilter
                                    }),
                                    isAdminUser && React.createElement('button', {
                                        className: 'btn-add-holiday',
                                        onClick: () => setIsModalOpen(true)
                                    },
                                        React.createElement('i', { className: 'fas fa-plus me-2' }),
                                        'Add Holiday'
                                    )
                                )
                            )
                        ),
                        React.createElement('div', { className: 'table-responsive' },
                            loading ? (
                                React.createElement('div', { className: 'text-center p-4' },
                                    React.createElement('div', { className: 'loading-spinner' }),
                                    React.createElement('p', { className: 'mt-2' }, 'Loading holidays...')
                                )
                            ) : (
                                React.createElement(HolidayTable, {
                                    holidays: filteredHolidays,
                                    canDelete: isAdminUser,
                                    onDelete: handleDelete
                                })
                            )
                        )
                    )
                ),
                React.createElement(AddHolidayModal, {
                    isOpen: isModalOpen,
                    onClose: () => setIsModalOpen(false),
                    onSuccess: handleModalSuccess
                })
            );
        }

        // Initialize React App - Wait for everything to load
        function initializeReactApp() {
            const rootElement = document.getElementById('holiday-react-root');
            if (!rootElement) {
                console.error('React root element #holiday-react-root not found');
                setTimeout(initializeReactApp, 100);
                return;
            }
            
            // Check if React is loaded
            if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
                console.error('React or ReactDOM not loaded');
                setTimeout(initializeReactApp, 100);
                return;
            }
            
            try {
                // Try React 18 createRoot first
                if (ReactDOM.createRoot) {
                    const root = ReactDOM.createRoot(rootElement);
                    root.render(React.createElement(HolidayListApp));
                } else if (ReactDOM.render) {
                    // Fallback to React 17 render
                    ReactDOM.render(React.createElement(HolidayListApp), rootElement);
                } else {
                    console.error('ReactDOM.createRoot and ReactDOM.render not available');
                }
            } catch (error) {
                console.error('Error initializing React app:', error);
            }
        }

        // Wait for DOM and scripts to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initializeReactApp, 100);
            });
        } else {
            setTimeout(initializeReactApp, 100);
        }
    </script>
</body>
</html>
