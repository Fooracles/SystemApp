<?php
$page_title = "Admin Dashboard";
require_once "../includes/header.php";
require_once "../includes/status_column_helpers.php";
require_once "../includes/dashboard_components.php";

// Performance optimization: Increase execution time limit for large datasets
set_time_limit(120); // 2 minutes instead of default 30 seconds
ini_set('memory_limit', '256M'); // Increase memory limit if needed

// Helper function for FMS date formatting in this page
function formatFMSDateTime($planned, $actual = null) {
    if (!empty($actual)) {
        return date("d M Y h:i A", strtotime($actual));
    } elseif (!empty($planned)) {
        return date("d M Y h:i A", strtotime($planned));
    }
    return "N/A";
}

// Helper function to parse FMS datetime strings (same as Manage Tasks)
function parseFMSDateTimeString_manage($dateTimeStr) {
    if (empty(trim($dateTimeStr)) || strtolower(trim($dateTimeStr)) === 'n/a') {
        return null;
    }
    // Try to parse formats like "dd/mm/yy at hh:mmaa" or "dd/mm/yy"
    $dateTimeStr = str_replace(" at ", " ", $dateTimeStr);
    try {
        $dt = null;
        // Handle "d/m/y H:iA", "d/m/Y H:iA", "d/m/y G:i", "d/m/Y G:i"
        if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\s*(\d{1,2}:\d{2}\s*(am|pm)?)/i', $dateTimeStr, $matches_datetime)) {
            $date_part = str_replace('-', '/', $matches_datetime[1]);
            $time_part = $matches_datetime[2];
            
            // Check for 2-digit year and try to infer century
            if (preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-](\d{2})$/', $date_part, $year_match)) {
                $year_short = intval($year_match[1]);
                $year_full = ($year_short < 70) ? (2000 + $year_short) : (1900 + $year_short);
                $date_part = preg_replace('/(\d{2})$/', strval($year_full), $date_part, 1);
            }

            $formats_to_try = [
                'd/m/Y H:iA', 'd/m/Y g:iA', 'd/m/Y G:i', 'd/m/Y H:i', 
                'j/n/Y H:iA', 'j/n/Y g:iA', 'j/n/Y G:i', 'j/n/Y H:i'
            ];
            foreach($formats_to_try as $format) {
                $dt = DateTime::createFromFormat($format, $date_part . ' ' . $time_part);
                if ($dt) break;
            }
            if (!$dt) $dt = new DateTime($date_part . ' ' . $time_part); // Fallback to general parsing

        } elseif (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/', $dateTimeStr, $matches_date_only)) {
            $date_part = str_replace('-', '/', $matches_date_only[0]);

            if (preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-](\d{2})$/', $date_part, $year_match)) {
                $year_short = intval($year_match[1]);
                $year_full = ($year_short < 70) ? (2000 + $year_short) : (1900 + $year_short);
                $date_part = preg_replace('/(\d{2})$/', strval($year_full), $date_part, 1);
            }
            $formats_to_try = ['d/m/Y', 'j/n/Y'];
            foreach($formats_to_try as $format) {
                $dt = DateTime::createFromFormat($format, $date_part);
                if ($dt) break;
            }
            if ($dt) $dt->setTime(0,0,0);
            else { // Fallback to general parsing for date only
                 $parsed_timestamp = strtotime($date_part);
                 if ($parsed_timestamp !== false) {
                    $dt = new DateTime();
                    $dt->setTimestamp($parsed_timestamp);
                    $dt->setTime(0,0,0);
                 }
            }
        } else {
             $parsed_timestamp = strtotime($dateTimeStr);
             if ($parsed_timestamp !== false) {
                $dt = new DateTime();
                $dt->setTimestamp($parsed_timestamp);
             }
        }
        return $dt ? $dt->getTimestamp() : null;
    } catch (Exception $e) {
        error_log("Admin Dashboard - Failed to parse FMS date string: {$dateTimeStr} - Error: " . $e->getMessage());
        return null;
    }
}

// Check if the user is logged in and is an admin
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

if(!isAdmin()) {
    header("location: " . (isManager() ? "manager_dashboard.php" : "doer_dashboard.php"));
    exit;
}

$system_task_stats = calculateGlobalTaskStats($conn);
$total_tasks = $system_task_stats['total_tasks'];
$completed_tasks = $system_task_stats['completed_tasks'];
$pending_tasks = $system_task_stats['pending_tasks'];
$delayed_tasks = $system_task_stats['delayed_tasks'];

echo "<!-- DEBUG: Admin Dashboard - Total Tasks: " . $total_tasks . " -->";
echo "<!-- DEBUG: Admin Dashboard - Completed Tasks: " . $completed_tasks . " -->";
echo "<!-- DEBUG: Admin Dashboard - Pending Tasks: " . $pending_tasks . " -->";
echo "<!-- DEBUG: Admin Dashboard - Delayed Tasks: " . $delayed_tasks . " -->";

// Count users by type
$admin_count = $manager_count = $doer_count = 0;
$sql = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        switch($row['user_type']) {
            case 'admin':
                $admin_count = $row['count'];
                break;
            case 'manager':
                $manager_count = $row['count'];
                break;
            case 'doer':
                $doer_count = $row['count'];
                break;
        }
    }
}

// Count departments
$department_count = 0;
$sql = "SELECT COUNT(*) as count FROM departments";
$result = mysqli_query($conn, $sql);
if($result) {
    $row = mysqli_fetch_assoc($result);
    $department_count = $row['count'];
}

// Get all tasks with department and user info (for display table)
$tasks = array();
$sql = "SELECT t.*, COALESCE(t.doer_name, u.username, 'N/A') as doer_name, d.name as department_name, 'delegation' as task_type 
        FROM tasks t 
        LEFT JOIN users u ON t.doer_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        ORDER BY t.planned_date ASC, t.planned_time ASC
        LIMIT 5";
$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $row['task_id'] = $row['id'];
        $tasks[] = $row;
    }
}

// Get current week's checklist tasks (for display table only)
$current_week_checklist_tasks = getCurrentWeekChecklistTasks($conn, 'admin');

// Merge and sort tasks for display
$all_display_tasks = array_merge($tasks, $current_week_checklist_tasks);

// Sort all tasks by planned_date and then by planned_time (if delegation)
usort($all_display_tasks, function($a, $b) {
    if ($a['planned_date'] == $b['planned_date']) {
        if ($a['task_type'] === 'delegation' && $b['task_type'] === 'delegation') {
            return strcmp($a['planned_time'], $b['planned_time']);
        }
        return ($a['task_id'] ?? 0) <=> ($b['task_id'] ?? 0); 
    }
    return strcmp($a['planned_date'], $b['planned_date']);
});

// Get recent users
$recent_users = array();
$sql = "SELECT u.*, d.name as department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        ORDER BY u.created_at DESC 
        LIMIT 5";
$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $recent_users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Enhanced Stats Cards Styling with Hover Shining Effect */
        .card.bg-primary, .card.bg-success, .card.bg-danger, .card.bg-info, .card.bg-warning {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .card.bg-primary:hover, .card.bg-success:hover, .card.bg-danger:hover, .card.bg-info:hover, .card.bg-warning:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        /* Hover Shining Effect */
        .card.bg-primary::before, .card.bg-success::before, .card.bg-danger::before, .card.bg-info::before, .card.bg-warning::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .card.bg-primary:hover::before, .card.bg-success:hover::before, .card.bg-danger:hover::before, .card.bg-info:hover::before, .card.bg-warning:hover::before {
            left: 100%;
        }

        .text-white-75 {
            color: rgba(255, 255, 255, 0.75) !important;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .text-lg {
            font-size: 2rem;
            font-weight: 700;
        }

        .card-footer {
            background: rgba(0, 0, 0, 0.1);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1rem;
        }

        .card-footer a {
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .card-footer a:hover {
            text-decoration: underline;
        }

        .card-footer i {
            transition: transform 0.2s ease;
        }

        .card:hover .card-footer i {
            transform: translateX(3px);
        }

        /* Icon animations */
        .card i {
            transition: all 0.3s ease;
        }

        .card:hover i {
            transform: scale(1.1);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .text-lg {
                font-size: 1.5rem;
            }
            
            .card i {
                font-size: 1.5rem !important;
            }
        }

        /* Pulse animation for delayed tasks */
        .card.bg-danger {
            animation: pulse-danger 2s infinite;
        }

        @keyframes pulse-danger {
            0% { box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
            50% { box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3); }
            100% { box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        }

        /* Success pulse for completed tasks */
        .card.bg-success {
            animation: pulse-success 3s infinite;
        }

        @keyframes pulse-success {
            0% { box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
            50% { box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); }
            100% { box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        }

        /* Description hover styling */
        .description-hover {
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
            display: inline-block;
            position: relative;
            width: 100%;
        }

        /* Ensure description column has fixed width */
        .table {
            table-layout: fixed;
        }

        .table td:nth-child(2) {
            max-width: 150px;
            width: 150px;
            word-wrap: break-word;
            overflow: hidden;
        }

        .description-hover:hover {
            text-decoration: none;
        }

        /* Custom tooltip for description */
        .description-hover::after {
            content: attr(data-full-description);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #000;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            white-space: nowrap;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            border: 1px solid #333;
        }

        .description-hover::before {
            content: '';
            position: absolute;
            bottom: 115%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .description-hover:hover::after,
        .description-hover:hover::before {
            opacity: 1;
            visibility: visible;
        }
    </style>
                
                <!-- Modern Admin Dashboard -->
                <link rel="stylesheet" href="../assets/css/doer_dashboard.css">
                <div class="doer-dashboard" id="adminDashboard">
                    <!-- Dashboard Header -->
                    <div class="dashboard-header">
                        <div class="welcome-section">
                            <h1 class="welcome-title">
                                Welcome back, <span class="username-highlight"><?php echo htmlspecialchars($_SESSION["username"]); ?></span>
                            </h1>
                            <p class="welcome-subtitle">System-wide overview and management!</p>
                        </div>
                        <div class="Daily-Quotes">
                            <div class="quote-container">
                                <div class="quote-icon">
                                    <i class="fas fa-quote-left"></i>
                    </div>
                                <div class="quote-content">
                                    <p class="daily-quote" id="dailyQuote">
                                        "The best way to predict the future is to create it."
                                    </p>
                                    <div class="quote-author" id="quoteAuthor">
                                        — Peter Drucker
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
<br><br>
                    <!-- Personal Stats Section (Admin's Own Tasks) -->
                    <div class="stats-section">
                        <div class="stats-header">
                            <div>
                                <h6 class="stats-title">
                                    <i class="fas fa-user"></i>
                                    <span id="personalOverviewTitle">Personal Overview</span>
                                </h6>
                                <div id="personalOverviewCaption" class="stats-caption">This Week Overview</div>
                            </div>
                            <div class="date-range-selector" id="personalDateRangeSelector">
                                <button class="date-range-btn active" data-range="this_week" title="This Week">This Week</button>
                                <button class="date-range-btn" data-range="last_week" title="Last Week">Last Week</button>
                                <div class="date-range-dropdown">
                                    <button class="date-range-btn dropdown-toggle" id="personalDateRangeDropdownBtn" title="More Options">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <div class="date-range-dropdown-menu" id="personalDateRangeDropdownMenu" style="display: none;">
                                        <button class="date-range-dropdown-item" data-range="last_2_weeks">Last 2 Weeks</button>
                                        <button class="date-range-dropdown-item" data-range="last_4_weeks">Last 4 Weeks</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="stats-container-wrapper">
                            <div class="stats-grid" id="personalStatsGrid">
                                <div class="stat-card completed" onclick="window.location.href='my_task.php'">
                                    <div class="stat-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value" data-target="0">0</div>
                                        <div class="stat-label">Completed Tasks</div>
                                    </div>
                                </div>
                                <div class="stat-card pending" onclick="window.location.href='my_task.php'">
                                    <div class="stat-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value" data-target="0">0</div>
                                        <div class="stat-label">Pending Tasks</div>
                                    </div>
                                </div>
                                <div class="stat-card" data-stat="wnd" onclick="window.location.href='my_task.php'">
                                    <div class="stat-icon">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value" data-target="0">0%</div>
                                        <div class="stat-label">WND</div>
                                    </div>
                                </div>
                                <div class="stat-card" data-stat="wnd_on_time" onclick="window.location.href='my_task.php'">
                                    <div class="stat-icon">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value" data-target="0">0%</div>
                                        <div class="stat-label">WND on Time</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <br><br><br>

                    <!-- System Overview Stats -->
                    <div class="stats-section">
                        <div class="stats-header">
                            <h6 class="stats-title">
                                <i class="fas fa-chart-line"></i>
                                <span id="systemOverviewTitle">System Overview</span>
                            </h6>
                            <div class="date-range-selector">
                                <button class="date-range-btn active" data-range="this_week" title="This Week">This Week</button>
                                <button class="date-range-btn" data-range="last_week" title="Last Week">Last Week</button>
                                <div class="date-range-dropdown">
                                    <button class="date-range-btn dropdown-toggle" id="systemDateRangeDropdownBtn" title="More Options">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <div class="date-range-dropdown-menu" id="systemDateRangeDropdownMenu" style="display: none;">
                                        <button class="date-range-dropdown-item" data-range="last_2_weeks">Last 2 Weeks</button>
                                        <button class="date-range-dropdown-item" data-range="last_4_weeks">Last 4 Weeks</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="stats-container-wrapper">
                            <div class="stats-grid" id="systemStatsGrid">
                                <div class="system-stat-card system-stat-total" onclick="window.location.href='manage_tasks.php'">
                                    <div class="system-stat-icon">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <div class="system-stat-content">
                                        <div class="system-stat-value" id="systemTotalTasks"><?php echo $total_tasks; ?></div>
                                        <div class="system-stat-label">Total Tasks</div>
                                    </div>
                                </div>
                                <div class="system-stat-card system-stat-completed" onclick="window.location.href='manage_tasks.php'">
                                    <div class="system-stat-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="system-stat-content">
                                        <div class="system-stat-value" id="systemCompletedTasks"><?php echo $completed_tasks; ?></div>
                                        <div class="system-stat-label">Completed</div>
                                    </div>
                                </div>
                                <div class="system-stat-card system-stat-pending" onclick="window.location.href='manage_tasks.php'">
                                    <div class="system-stat-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="system-stat-content">
                                        <div class="system-stat-value" id="systemPendingTasks"><?php echo $pending_tasks; ?></div>
                                        <div class="system-stat-label">Pending</div>
                                    </div>
                                </div>
                                <div class="system-stat-card system-stat-delayed" onclick="window.location.href='manage_tasks.php'">
                                    <div class="system-stat-icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="system-stat-content">
                                        <div class="system-stat-value" id="systemDelayedTasks"><?php echo $delayed_tasks; ?></div>
                                        <div class="system-stat-label">Delayed</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Grid -->
                    <div class="dashboard-grid">
                        <!-- Top Performers Leaderboard -->
                        <div class="leaderboard-section">
                            <div class="section-header">
                                <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                <h3 class="section-title">
                                    <i class="fas fa-trophy"></i>
                                    Top Performers
                                </h3>
                                    <button class="btn btn-sm btn-info" id="rqcSyncBtn" onclick="syncRqcData()" title="Sync RQC scores from Google Sheets">
                                        <i class="fas fa-sync-alt"></i> Sync RQC
                                    </button>
                                </div>
                                <div class="leaderboard-controls">
                                    <div class="time-period-selector">
                                        <button class="period-btn active" data-period="last_week" title="Last Week">
                                            <i class="fas fa-calendar-week"></i> Last Week
                                        </button>
                                        <button class="period-btn" data-period="last_2_weeks" title="Last 2 Week">
                                            <i class="fas fa-calendar"></i> Last 2 Week
                                        </button>
                                        <button class="period-btn" data-period="last_4_weeks" title="Last 4 Week">
                                            <i class="fas fa-calendar-alt"></i> Last 4 Week
                                        </button>
                                    </div>
                                    
                                </div>
                            </div>
                            <div class="leaderboard-content">
                                <div class="leaderboard-list" id="topPerformersList">
                                    <!-- Leaderboard items will be populated by JavaScript -->
                                </div>
                                <div class="leaderboard-pagination" id="leaderboardPagination">
                                    <!-- Pagination controls will be populated by JavaScript -->
                                </div>
                                <div style="text-align: center; margin-top: 1.5rem;">
                                    <button class="btn btn-primary" id="viewPerformanceBtn" onclick="viewPerformance()">
                                        <i class="fas fa-chart-line"></i> View Performance
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Team Availability Section -->
                        <div class="chart-section team-availability-section" id="teamAvailabilitySection">
                            <div class="section-header">
                                <h4 class="section-title">
                                    <i class="fas fa-users"></i>
                                    Team Availability
                                </h4>
                                <div class="availability-stats">
                                    <div class="stat-item available">
                                        <span class="stat-dot"></span>
                                        <span id="availableCount">0</span> Available
                                    </div>
                                    <div class="stat-item on-wfh">
                                        <span class="stat-dot"></span>
                                        <span id="onWfhCount">0</span> On WFH
                                    </div>
                                    <div class="stat-item on-leave">
                                        <span class="stat-dot"></span>
                                        <span id="onLeaveCount">0</span> On Leave
                                    </div>
                                </div>
                            </div>
                            <div class="team-grid" id="teamGrid">
                                <!-- Team members (available and on leave) will be populated by JavaScript -->
                            </div>

                            <!-- Leave Details Modal - Inside Team Availability Section -->
                            <div class="leave-details-modal" id="leaveDetailsModal">
                                <div class="leave-details-overlay" id="leaveDetailsOverlay"></div>
                                <div class="leave-details-card">
                                    <div class="leave-details-header">
                                        <h5 class="leave-details-title">
                                            <i class="fas fa-calendar-alt"></i> <span></span> <span></span>
                                            <span id="leaveDetailsMemberName">Leave Details</span>
                                        </h5>
                                        <button class="leave-details-close" id="leaveDetailsClose">
                                            <i class="fas fa-times"></i>
                                        </button>
                            </div>
                                    <div class="leave-details-body">
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-tag"></i>
                                                Leave Type:
                            </div>
                                            <div class="leave-detail-value" id="leaveDetailsType">-</div>
                                        </div>
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-clock"></i>
                                                Duration:
                                            </div>
                                            <div class="leave-detail-value" id="leaveDetailsDuration">-</div>
                                        </div>
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-calendar-check"></i>
                                                Start Date:
                                            </div>
                                            <div class="leave-detail-value" id="leaveDetailsStartDate">-</div>
                                        </div>
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-calendar-times"></i>
                                                End Date:
                                            </div>
                                            <div class="leave-detail-value" id="leaveDetailsEndDate">-</div>
                                        </div>
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-calendar-day"></i>
                                                No. of Days:
                                            </div>
                                            <div class="leave-detail-value" id="leaveDetailsDays">-</div>
                                        </div>
                                        <div class="leave-detail-item">
                                            <div class="leave-detail-label">
                                                <i class="fas fa-info-circle"></i>
                                                Status:
                                            </div>
                                            <div class="leave-detail-value">
                                                <span class="leave-status-badge" id="leaveDetailsStatus">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Motivation & Insights Panel -->
                    <div class="motivation-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-lightbulb"></i>
                                Performance Insights
                            </h3>
                            <div class="user-selector-wrapper">
                                <label for="userMotivationSelector" class="user-selector-label">
                                    <i class="fas fa-user"></i> Select User:
                                </label>
                                <select id="userMotivationSelector" class="form-control user-selector-dropdown">
                                    <option value="">-- Select a User --</option>
                                    <?php
                                    // Fetch all managers and doers
                                    $users_query = "SELECT id, name, username, user_type FROM users WHERE user_type IN ('manager', 'doer') ORDER BY user_type, name";
                                    $users_result = mysqli_query($conn, $users_query);
                                    if ($users_result && mysqli_num_rows($users_result) > 0) {
                                        while ($user = mysqli_fetch_assoc($users_result)) {
                                            $display_name = htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['username']) . ') - ' . ucfirst($user['user_type']);
                                            echo '<option value="' . $user['id'] . '">' . $display_name . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="motivation-content" id="userMotivationContent" style="display: none;">
                            <!-- Current Insights -->
                            <div class="insights-subsection">
                                <div class="subsection-title">
                                    <i class="fas fa-chart-line"></i>
                                    Current Insights
                                </div>
                                <div class="insight-editable-container">
                                    <div class="insight-display" id="currentInsightsDisplay">
                                        <p class="insight-placeholder">No insights available. Select a user to view or edit.</p>
                                    </div>
                                    <div class="insight-edit" id="currentInsightsEdit" style="display: none;">
                                        <textarea class="form-control insight-textarea" id="currentInsightsTextarea" rows="6" placeholder="Enter current insights for this user..."></textarea>
                                        <div class="preset-suggestions">
                                            <label class="preset-label">Quick Presets:</label>
                                            <div class="preset-buttons">
                                                <button type="button" class="btn-preset" data-preset="current_insights" data-value="🔥 Excellent performance this week! Keep up the great work!">🔥 Excellent Performance</button>
                                                <button type="button" class="btn-preset" data-preset="current_insights" data-value="⚡ Strong task completion rate. Meeting deadlines consistently.">⚡ Strong Completion Rate</button>
                                                <button type="button" class="btn-preset" data-preset="current_insights" data-value="📈 Showing steady improvement in task management and efficiency.">📈 Steady Improvement</button>
                                                <button type="button" class="btn-preset" data-preset="current_insights" data-value="🎯 Consistently delivering high-quality work on time.">🎯 High Quality Work</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Areas of Improvement -->
                            <div class="insights-subsection">
                                <div class="subsection-title">
                                    <i class="fas fa-target"></i>
                                    Areas of Improvement / Focus
                                </div>
                                <div class="insight-editable-container">
                                    <div class="insight-display" id="areasOfImprovementDisplay">
                                        <p class="insight-placeholder">No improvement areas defined. Select a user to view or edit.</p>
                                    </div>
                                    <div class="insight-edit" id="areasOfImprovementEdit" style="display: none;">
                                        <textarea class="form-control insight-textarea" id="areasOfImprovementTextarea" rows="6" placeholder="Enter areas of improvement for this user..."></textarea>
                                        <div class="preset-suggestions">
                                            <label class="preset-label">Quick Presets:</label>
                                            <div class="preset-buttons">
                                                <button type="button" class="btn-preset" data-preset="areas_of_improvement" data-value="⏰ Time Management - Focus on completing tasks 2 hours earlier to boost efficiency.">⏰ Time Management</button>
                                                <button type="button" class="btn-preset" data-preset="areas_of_improvement" data-value="🤝 Team Collaboration - Increase peer feedback participation by 15% this month.">🤝 Team Collaboration</button>
                                                <button type="button" class="btn-preset" data-preset="areas_of_improvement" data-value="📊 Communication - Improve clarity in task updates and status reports.">📊 Communication</button>
                                                <button type="button" class="btn-preset" data-preset="areas_of_improvement" data-value="🎯 Goal Setting - Set more specific and measurable targets for better tracking.">🎯 Goal Setting</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Combined Save/Cancel Buttons -->
                        <div class="motivation-actions-container" id="motivationActionsContainer" style="display: none;">
                            <div class="motivation-actions">
                                <button class="btn btn-success btn-save-motivation" id="saveAllMotivation">
                                    <i class="fas fa-save"></i> Save All Changes
                                </button>
                                <button class="btn btn-secondary btn-cancel-motivation" id="cancelAllMotivation">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Tasks Section -->
                    <div class="chart-section recent-tasks">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-list"></i>
                                Recent System Tasks
                            </h3>
                            <button class="btn btn-sm btn-primary" onclick="window.location.href='manage_tasks.php'">
                                <i class="fas fa-external-link-alt"></i> View All
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>Task ID</th>
                                        <th>Description</th>
                                        <th>Doer</th>
                                        <th>Department</th>
                                        <th>Planned Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="recentTasksTable">
                                    <tr>
                                        <td colspan="6" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <style>
                /* Leave Details Modal Styles - Same as manager dashboard */
                .team-availability-section .leave-details-modal {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 1000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    visibility: hidden;
                    transition: opacity 0.3s ease, visibility 0.3s ease;
                    pointer-events: none;
                }

                .team-availability-section .leave-details-modal.show {
                    opacity: 1;
                    visibility: visible;
                    pointer-events: auto;
                }

                .team-availability-section .leave-details-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    backdrop-filter: blur(10px);
                    -webkit-backdrop-filter: blur(10px);
                    z-index: 1001;
                    border-radius: 16px;
                }

                .team-availability-section .leave-details-card {
                    position: relative;
                    z-index: 1002;
                    background: linear-gradient(135deg, rgba(26, 26, 26, 0.98) 0%, rgba(42, 42, 42, 0.98) 100%);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 16px;
                    box-shadow: 
                        0 20px 60px rgba(0, 0, 0, 0.6),
                        0 0 0 1px rgba(255, 255, 255, 0.1) inset;
                    backdrop-filter: blur(20px);
                    -webkit-backdrop-filter: blur(20px);
                    width: 90%;
                    max-width: 270px;
                    max-height: 55vh;
                    overflow: hidden;
                    transform: scale(0.9) translateY(20px);
                    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                }

                .team-availability-section .leave-details-modal.show .leave-details-card {
                    transform: scale(1) translateY(0);
                }

                .team-availability-section .leave-details-header {
                    padding: 1.25rem 1rem;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1rem;
                }

                .team-availability-section .leave-details-title {
                    margin: 0;
                    color: #ffffff;
                    font-size: 1rem;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                .team-availability-section .leave-details-title i {
                    color: #667eea;
                    font-size: 1.2rem;
                }

                .team-availability-section .leave-details-close {
                    background: transparent;
                    border: none;
                    color: rgba(255, 255, 255, 0.7);
                    font-size: 1.1rem;
                    cursor: pointer;
                    padding: 0.25rem;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 6px;
                    transition: all 0.2s ease;
                }

                .team-availability-section .leave-details-close:hover {
                    background: rgba(255, 255, 255, 0.1);
                    color: #ffffff;
                    transform: rotate(90deg);
                }

                .team-availability-section .leave-details-body {
                    padding: 1rem;
                    max-height: calc(85vh - 100px);
                    overflow-y: auto;
                    scroll-behavior: smooth;
                }

                .team-availability-section .leave-details-body::-webkit-scrollbar {
                    width: 4px;
                }

                .team-availability-section .leave-details-body::-webkit-scrollbar-track {
                    background: rgba(255, 255, 255, 0.05);
                    border-radius: 6px;
                }

                .team-availability-section .leave-details-body::-webkit-scrollbar-thumb {
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 6px;
                }

                .team-availability-section .leave-details-body::-webkit-scrollbar-thumb:hover {
                    background: #667eea;
                }

                .team-availability-section .leave-detail-item {
                    display: flex;
                    flex-direction: row;
                    gap: 0.25rem;
                    padding: 0.5rem 0;
                    border-bottom: 2px solid rgba(255, 255, 255, 0.05);
                }

                .team-availability-section .leave-detail-item:last-child {
                    border-bottom: none;
                }

                .team-availability-section .leave-detail-label {
                    color: rgba(255, 255, 255, 0.6);
                    font-size: 0.75rem;
                    font-weight: 500;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                .team-availability-section .leave-detail-label i {
                    color: #667eea;
                    font-size: 0.9rem;
                    width: 18px;
                    text-align: center;
                }

                .team-availability-section .leave-detail-value {
                    color: #ffffff;
                    font-size: 0.75rem;
                    font-weight: 500;
                    text-transform: uppercase;
                    margin-top: 0.25rem;
                    padding-left: calc(18px + 0.5rem);
                }

                .leave-status-badge {
                    display: inline-block;
                    padding: 0.25rem 0.75rem;
                    border-radius: 6px;
                    font-size: 0.7rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .leave-status-badge.status-approved {
                    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
                    color: #10b981;
                    border: 1px solid rgba(16, 185, 129, 0.3);
                    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
                }

                .leave-status-badge.status-pending {
                    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(217, 119, 6, 0.2) 100%);
                    color: #f59e0b;
                    border: 1px solid rgba(245, 158, 11, 0.3);
                    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
                }

                /* Loading Overlay Styles */
                #dashboardLoadingOverlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    backdrop-filter: blur(10px);
                    -webkit-backdrop-filter: blur(10px);
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: opacity 0.3s ease;
                    transform: none !important;
                }

                #dashboardLoadingOverlay .loading-spinner {
                    text-align: center;
                    color: white;
                    transform: none !important;
                    animation: none !important;
                    width: auto;
                    height: auto;
                    border: none !important;
                }

                #dashboardLoadingOverlay .spinner {
                    width: 50px;
                    height: 50px;
                    border: 4px solid rgba(255, 255, 255, 0.3) !important;
                    border-top-color: #667eea !important;
                    border-right-color: rgba(255, 255, 255, 0.3) !important;
                    border-bottom-color: rgba(255, 255, 255, 0.3) !important;
                    border-left-color: rgba(255, 255, 255, 0.3) !important;
                    border-radius: 50%;
                    animation: dashboard-spin 1s linear infinite;
                    margin: 0 auto 1rem;
                    transform-origin: center center;
                    display: block;
                    background: none !important;
                }

                @keyframes dashboard-spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }

                #dashboardLoadingOverlay .loading-spinner p {
                    margin: 0;
                    font-size: 1.1rem;
                    font-weight: 500;
                    transform: none !important;
                    animation: none !important;
                    display: block;
                    position: relative;
                }

                /* Hide sections initially until data loads */
                .stats-section,
                .chart-section,
                .leaderboard-section,
                .motivation-section {
                    opacity: 0;
                    transform: translateY(20px);
                    transition: opacity 0.3s ease, transform 0.3s ease;
                }

                .stats-section.loaded,
                .chart-section.loaded,
                .leaderboard-section.loaded,
                .motivation-section.loaded {
                    opacity: 1;
                    transform: translateY(0);
                }

                /* Leaderboard Section Header */
                .leaderboard-section .section-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 1.5rem;
                    gap: 1rem;
                    flex-wrap: wrap;
                }
                
                /* On smaller containers, allow controls to wrap below title */
                @media (max-width: 1200px) {
                    .leaderboard-section .section-header {
                        justify-content: flex-start;
                    }
                    
                    .leaderboard-controls {
                        margin-left: auto;
                    }
                }
                
                /* Leaderboard Controls */
                .leaderboard-controls {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    flex-wrap: wrap;
                    flex-shrink: 1;
                    min-width: 0;
                }
                
                .time-period-selector {
                    display: flex;
                    gap: 0.25rem;
                    background: rgba(255, 255, 255, 0.05);
                    padding: 0.25rem;
                    border-radius: 0.5rem;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    flex-shrink: 0;
                    min-width: 0;
                }
                
                .period-btn {
                    padding: 0.375rem 0.625rem;
                    background: transparent;
                    border: none;
                    color: var(--dark-text-secondary);
                    border-radius: 0.375rem;
                    font-size: 0.75rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    display: flex;
                    align-items: center;
                    gap: 0.25rem;
                    line-height: 1.3;
                    white-space: nowrap;
                    flex-shrink: 0;
                }
                
                .period-btn:hover {
                    background: rgba(255, 255, 255, 0.1);
                    color: var(--dark-text-primary);
                }
                
                .period-btn.active {
                    background: var(--gradient-primary);
                    color: var(--dark-text-primary);
                    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.3);
                }
                
                .period-btn i {
                    font-size: 0.6875rem;
                    flex-shrink: 0;
                }
                
                /* Responsive adjustments for smaller screens */
                @media (max-width: 768px) {
                    .leaderboard-section .section-header {
                        flex-direction: column;
                        align-items: flex-start;
                    }
                    
                    .leaderboard-controls {
                        width: 100%;
                        justify-content: space-between;
                    }
                    
                    .time-period-selector {
                        flex-wrap: wrap;
                        width: 100%;
                    }
                    
                    .period-btn {
                        flex: 1;
                        min-width: 0;
                        justify-content: center;
                    }
                }
                
                /* Enhanced Leaderboard Item Styles */
                .leaderboard-item {
                    position: relative;
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    padding: 0.75rem 1.25rem;
                    margin-bottom: 0.625rem;
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 0.75rem;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    min-height: 75px;
                    z-index: 1;
                }
                
                .leaderboard-item:hover {
                    z-index: 10;
                }
                
                .leaderboard-item:last-child {
                    margin-bottom: 0;
                }
                
                .leaderboard-item[style*="cursor: pointer"] {
                    transition: all 0.2s ease;
                }
                
                .leaderboard-item[style*="cursor: pointer"]:hover {
                    transform: translateY(-3px) scale(1.01);
                    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
                    background: rgba(102, 126, 234, 0.08);
                    z-index: 10;
                }
                
                .leaderboard-item.clicked {
                    transform: scale(0.98);
                    transition: transform 0.1s ease;
                }
                
                /* Top 3 Rank Gradients */
                .leaderboard-item.rank-gold {
                    background: linear-gradient(135deg, rgba(255, 215, 0, 0.15) 0%, rgba(255, 165, 0, 0.15) 100%);
                    border: 2px solid rgba(255, 215, 0, 0.4);
                    box-shadow: 0 4px 16px rgba(255, 215, 0, 0.2);
                    padding: 0.875rem 1.375rem;
                }
                
                .leaderboard-item.rank-gold:hover {
                    box-shadow: 0 8px 32px rgba(255, 215, 0, 0.4);
                    border-color: rgba(255, 215, 0, 0.6);
                    z-index: 10;
                }
                
                .leaderboard-item.rank-silver {
                    background: linear-gradient(135deg, rgba(192, 192, 192, 0.15) 0%, rgba(128, 128, 128, 0.15) 100%);
                    border: 2px solid rgba(192, 192, 192, 0.4);
                    box-shadow: 0 4px 16px rgba(192, 192, 192, 0.2);
                    padding: 0.875rem 1.375rem;
                }
                
                .leaderboard-item.rank-silver:hover {
                    box-shadow: 0 8px 32px rgba(192, 192, 192, 0.4);
                    border-color: rgba(192, 192, 192, 0.6);
                    z-index: 10;
                }
                
                .leaderboard-item.rank-bronze {
                    background: linear-gradient(135deg, rgba(205, 127, 50, 0.15) 0%, rgba(139, 69, 19, 0.15) 100%);
                    border: 2px solid rgba(205, 127, 50, 0.4);
                    box-shadow: 0 4px 16px rgba(205, 127, 50, 0.2);
                    padding: 0.875rem 1.375rem;
                }
                
                .leaderboard-item.rank-bronze:hover {
                    box-shadow: 0 8px 32px rgba(205, 127, 50, 0.4);
                    border-color: rgba(205, 127, 50, 0.6);
                    z-index: 10;
                }
                
                /* Rank Badge Enhancements */
                .rank-badge {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-width: 50px;
                    width: 50px;
                    height: 50px;
                    gap: 0.2rem;
                    flex-shrink: 0;
                }
                
                .rank-number {
                    font-size: 1.25rem;
                    font-weight: 700;
                    line-height: 1.2;
                    color: var(--dark-text-primary);
                }
                
                .rank-emoji {
                    font-size: 1rem;
                    line-height: 1;
                }
                
                .rank-badge.rank-gold .rank-number {
                    color: #FFD700;
                    text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
                }
                
                .rank-badge.rank-silver .rank-number {
                    color: #C0C0C0;
                    text-shadow: 0 0 10px rgba(192, 192, 192, 0.5);
                }
                
                .rank-badge.rank-bronze .rank-number {
                    color: #CD7F32;
                    text-shadow: 0 0 10px rgba(205, 127, 50, 0.5);
                }
                
                /* Enhanced Avatar Styles */
                .user-avatar-wrapper {
                    position: relative;
                    flex-shrink: 0;
                }
                
                .user-avatar {
                    width: 48px;
                    height: 48px;
                    border-radius: 50%;
                    overflow: hidden;
                    border: 2px solid rgba(255, 255, 255, 0.2);
                    position: relative;
                    background: var(--gradient-primary);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.3s ease;
                    flex-shrink: 0;
                }
                
                .leaderboard-item:hover .user-avatar {
                    border-color: rgba(99, 102, 241, 0.6);
                    transform: scale(1.05);
                    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
                }
                
                .user-avatar img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                
                .avatar-initials {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 100%;
                    height: 100%;
                    font-size: 1.1rem;
                    font-weight: 700;
                    color: white;
                    background: var(--gradient-primary);
                }
                
                /* User Info Container */
                .user-info {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    flex: 1;
                    min-width: 0;
                }
                
                /* User Details */
                .user-details {
                    flex: 1;
                    min-width: 0;
                    display: flex;
                    flex-direction: column;
                    gap: 0.375rem;
                }
                
                .user-name {
                    font-size: 1rem;
                    font-weight: 600;
                    color: var(--dark-text-primary);
                    margin: 0;
                    line-height: 1.4;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                    max-width: 100%;
                }
                
                /* User Scores Display */
                .user-scores {
                    display: flex;
                    flex-direction: column;
                    gap: 0.375rem;
                    margin: 0;
                }
                
                .user-score {
                    font-size: 0.875rem;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    line-height: 1.4;
                    margin: 0;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                    flex-wrap: wrap;
                }
                
                .user-score.completion-rate {
                    color: #10b981;
                    font-weight: 500;
                }
                
                .user-score.rqc-score {
                    color: #f59e0b;
                    font-weight: 500;
                }
                
                .user-score i {
                    font-size: 0.75rem;
                    width: 14px;
                    text-align: center;
                }
                
                .user-tasks {
                    font-size: 0.8125rem;
                    color: var(--dark-text-muted);
                    font-weight: 500;
                    margin: 0;
                    line-height: 1.4;
                }
                
                /* Progress Ring Styles */
                .performance-ring-wrapper {
                    width: 75px;
                    height: 75px;
                    position: relative;
                    flex-shrink: 0;
                    margin-left: auto;
                }
                
                .performance-ring {
                    width: 100%;
                    height: 100%;
                }
                
                .ring-background {
                    transition: stroke 0.3s ease;
                }
                
                .ring-progress {
                    transition: stroke-dashoffset 1.5s cubic-bezier(0.4, 0, 0.2, 1);
                }
                
                .ring-text {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    pointer-events: none;
                    font-size: 11px;
                    font-weight: 600;
                }
                
                /* Tooltip Styles */
                .leaderboard-item[data-tooltip] {
                    position: relative;
                }
                
                .leaderboard-tooltip {
                    position: absolute;
                    bottom: calc(100% + 12px);
                    left: 50%;
                    transform: translateX(-50%);
                    background: rgba(26, 26, 26, 0.95);
                    backdrop-filter: blur(10px);
                    color: var(--dark-text-primary);
                    padding: 1rem 1.25rem;
                    border-radius: 0.625rem;
                    font-size: 0.875rem;
                    z-index: 10000;
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
                    pointer-events: none;
                    opacity: 0;
                    visibility: hidden;
                    transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease;
                    min-width: 220px;
                    max-width: 280px;
                    white-space: normal;
                }
                
                .leaderboard-item[data-tooltip]:hover .leaderboard-tooltip {
                    opacity: 1;
                    visibility: visible;
                    transform: translateX(-50%) translateY(-5px);
                }
                
                .leaderboard-tooltip::after {
                    content: '';
                    position: absolute;
                    top: 100%;
                    left: 50%;
                    transform: translateX(-50%);
                    border: 6px solid transparent;
                    border-top-color: rgba(26, 26, 26, 0.95);
                }
                
                .leaderboard-tooltip-content {
                    line-height: 1.6;
                }
                
                .leaderboard-tooltip-content strong {
                    color: var(--brand-primary);
                    display: block;
                    margin-bottom: 0.625rem;
                    font-size: 1rem;
                    font-weight: 600;
                }
                
                .leaderboard-tooltip-content span {
                    display: block;
                    color: var(--dark-text-secondary);
                    margin: 0.375rem 0;
                    font-size: 0.8125rem;
                    line-height: 1.5;
                }
                
                /* Leaderboard List Container */
                .leaderboard-list {
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                    margin-bottom: 1.25rem;
                    overflow: visible;
                    position: relative;
                }
                
                /* Empty State */
                .leaderboard-empty {
                    text-align: center;
                    padding: 3rem 1.5rem;
                    color: var(--dark-text-secondary);
                    font-size: 1rem;
                    font-weight: 500;
                    line-height: 1.5;
                }
                
                /* Leaderboard Pagination Styles */
                .leaderboard-pagination {
                    margin-top: 1rem;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    gap: 0.75rem;
                }

                .pagination-controls {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                }

                .pagination-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 32px;
                    height: 32px;
                    padding: 0.375rem 0.625rem;
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 0.5rem;
                    color: var(--dark-text-primary);
                    font-size: 0.8125rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .pagination-btn:hover:not(:disabled) {
                    background: rgba(255, 255, 255, 0.1);
                    border-color: rgba(255, 255, 255, 0.2);
                    transform: translateY(-1px);
                }

                .pagination-btn:disabled {
                    opacity: 0.4;
                    cursor: not-allowed;
                }

                .pagination-btn.page-number {
                    min-width: 36px;
                }

                .pagination-btn.active {
                    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                    border-color: rgba(99, 102, 241, 0.5);
                    color: #fff;
                    font-weight: 600;
                }

                .pagination-btn.active:hover {
                    background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
                }

                .pagination-ellipsis {
                    color: var(--dark-text-secondary);
                    padding: 0 0.25rem;
                    font-size: 0.875rem;
                }

                .view-rank-btn {
                    width: auto;
                    min-width: 160px;
                    background: rgba(99, 102, 241, 0.1);
                    border: 1px solid rgba(99, 102, 241, 0.3);
                    color: #818cf8;
                    padding: 0.5rem 1rem;
                    border-radius: 0.5rem;
                    font-size: 0.8125rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                }

                .view-rank-btn:hover {
                    background: rgba(99, 102, 241, 0.2);
                    border-color: rgba(99, 102, 241, 0.5);
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
                }

                .view-rank-btn i {
                    font-size: 0.8125rem;
                }

                /* View All Button */
                .view-all-btn {
                    width: 100%;
                    background: var(--gradient-primary);
                    color: var(--dark-text-primary);
                    border: none;
                    padding: 0.875rem 1.5rem;
                    border-radius: 0.75rem;
                    font-size: 0.9375rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.625rem;
                    margin-top: 0.5rem;
                }
                
                .view-all-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
                }
                
                .view-all-btn i {
                    font-size: 0.875rem;
                    transition: transform 0.3s ease;
                }
                
                .view-all-btn:hover i {
                    transform: scale(1.1);
                }
                
                .view-all-btn .btn-text {
                    font-weight: 600;
                }

                /* Consistent Section Spacing - Same as manager dashboard */
                .stats-section {
                    margin-bottom: 2rem;
                }

                .stats-section:last-of-type {
                    margin-bottom: 0;
                }

                .dashboard-grid {
                    margin-top: 2rem;
                }

                .chart-section.full-width {
                    grid-column: 1 / -1;
                }

                .chart-section.recent-tasks {
                    margin-top: 2rem;
                    margin-left: auto;
                    margin-right: auto;
                    max-width: 100%;
                }

                /* Ensure Recent Tasks section has same container padding as dashboard-grid */
                .doer-dashboard > .chart-section.recent-tasks {
                    margin-left: 2rem;
                    margin-right: 2rem;
                }

                /* Ensure dashboard header has consistent side margins */
                .doer-dashboard > .dashboard-header {
                    margin-left: 2rem;
                    margin-right: 2rem;
                }

                /* Ensure stats section has consistent side margins */
                .doer-dashboard > .stats-section {
                    margin-left: 2rem;
                    margin-right: 2rem;
                }

                /* Ensure dashboard grid has consistent side margins */
                .doer-dashboard > .dashboard-grid {
                    margin-left: 2rem;
                    margin-right: 2rem;
                }

                /* Leaderboard Section Container */
                .leaderboard-section {
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 1rem;
                    padding: 1.5rem;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                    transition: all 0.3s ease;
                    position: relative;
                    overflow: visible;
                }
                
                .leaderboard-content {
                    margin-top: 0;
                    overflow: visible;
                }
                
                /* Consistent Section Headers */
                .stats-section .stats-title,
                .chart-section .section-title,
                .leaderboard-section .section-title {
                    font-size: 1.25rem;
                    font-weight: 600;
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 0.625rem;
                    line-height: 1.4;
                    flex-shrink: 0;
                }

                .chart-section .section-title,
                .leaderboard-section .section-title {
                    font-size: 1.375rem;
                }

                .stats-section .stats-title i,
                .chart-section .section-title i,
                .leaderboard-section .section-title i {
                    color: #667eea;
                    font-size: 1.125rem;
                }

                .stats-caption {
                    font-size: 0.875rem;
                    color: rgba(255, 255, 255, 0.6);
                    margin-top: 0.25rem;
                    font-weight: 400;
                    line-height: 1.4;
                }
                
                /* Ensure section title doesn't push controls off screen */
                .leaderboard-section .section-title {
                    min-width: 0;
                    flex: 0 0 auto;
                }

                /* Consistent Section Padding - Override doer_dashboard.css for consistency */
                .chart-section {
                    padding: 1.5rem !important;
                }

                .leaderboard-section {
                    padding: 1.5rem !important;
                }

                .motivation-section {
                    padding: 1.5rem !important;
                    margin-top: 2rem;
                    margin-left: auto;
                    margin-right: auto;
                    max-width: 100%;
                }

                /* Ensure Motivation section has same container margins as other sections */
                .doer-dashboard > .motivation-section {
                    margin-left: 2rem;
                    margin-right: 2rem;
                }

                /* User Selector Styles */
                .user-selector-wrapper {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    margin-left: auto;
                }

                .user-selector-label {
                    font-size: 0.9rem;
                    font-weight: 500;
                    color: var(--dark-text-primary);
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                .user-selector-dropdown {
                    min-width: 250px;
                    background: var(--dark-bg-card);
                    border: 1px solid var(--glass-border);
                    color: var(--dark-text-primary);
                    padding: 0.5rem 1rem;
                    border-radius: var(--radius-md);
                    font-size: 0.9rem;
                }

                .user-selector-dropdown:focus {
                    outline: none;
                    border-color: var(--brand-primary);
                    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
                }

                /* Ensure motivation-content uses flex layout consistently */
                .motivation-content {
                    display: flex;
                    flex-direction: row;
                    gap: 1.5rem;
                    align-items: flex-start;
                }

                /* Insight Editable Styles */
                .insight-editable-container {
                    margin-top: 1rem;
                }

                .insight-display {
                    padding: 1rem;
                    background: var(--glass-bg);
                    border: 1px solid var(--glass-border);
                    border-radius: var(--radius-lg);
                    min-height: 60px;
                }

                .insight-display p {
                    margin: 0;
                    color: var(--dark-text-primary);
                    line-height: 1.6;
                    white-space: pre-wrap;
                }

                .insight-placeholder {
                    color: var(--dark-text-muted);
                    font-style: italic;
                }

                .insight-edit {
                    padding: 1rem;
                }

                .insight-textarea {
                    width: 100%;
                    background: var(--dark-bg-card);
                    border: 1px solid var(--glass-border);
                    color: var(--dark-text-primary);
                    padding: 0.75rem;
                    border-radius: var(--radius-md);
                    font-size: 0.9rem;
                    resize: vertical;
                    min-height: 100px;
                    font-family: inherit;
                }

                .insight-textarea:focus {
                    outline: none;
                    border-color: var(--brand-primary);
                    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
                }

                /* Loading state for motivation */
                .loading-motivation {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 2rem;
                    color: var(--dark-text-muted);
                    gap: 0.5rem;
                }

                /* Toast Animation */
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }

                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }

                /* Combined Save/Cancel Actions */
                .motivation-actions-container {
                    margin-top: 2rem;
                    padding-top: 1.5rem;
                    border-top: 1px solid var(--glass-border);
                    display: flex;
                    justify-content: center;
                    width: 100%;
                }

                .motivation-actions {
                    display: flex;
                    gap: 1rem;
                    align-items: center;
                }

                .btn-save-motivation,
                .btn-cancel-motivation {
                    padding: 0.75rem 2rem;
                    font-size: 1rem;
                    font-weight: 600;
                    border-radius: var(--radius-md);
                    transition: var(--transition-normal);
                    min-width: 150px;
                }

                .btn-save-motivation {
                    background: var(--brand-primary);
                    border: 1px solid var(--brand-primary);
                    color: white;
                }

                .btn-save-motivation:hover:not(:disabled) {
                    background: var(--brand-primary-dark);
                    border-color: var(--brand-primary-dark);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
                }

                .btn-save-motivation:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }

                .btn-cancel-motivation {
                    background: var(--dark-bg-glass);
                    border: 1px solid var(--glass-border);
                    color: var(--dark-text-primary);
                }

                .btn-cancel-motivation:hover {
                    background: var(--dark-bg-glass-hover);
                    border-color: var(--glass-border);
                    transform: translateY(-2px);
                }

                /* Preset Suggestions Styles */
                .preset-suggestions {
                    margin-top: 0.75rem;
                    padding: 0.75rem;
                    background: var(--glass-bg);
                    border: 1px solid var(--glass-border);
                    border-radius: var(--radius-md);
                }

                .preset-label {
                    font-size: 0.85rem;
                    font-weight: 600;
                    color: var(--dark-text-secondary);
                    margin-bottom: 0.5rem;
                    display: block;
                }

                .preset-buttons {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.5rem;
                }

                .btn-preset {
                    background: var(--dark-bg-glass);
                    border: 1px solid var(--glass-border);
                    color: var(--dark-text-primary);
                    padding: 0.4rem 0.75rem;
                    border-radius: var(--radius-sm);
                    font-size: 0.8rem;
                    cursor: pointer;
                    transition: var(--transition-normal);
                    white-space: nowrap;
                }

                .btn-preset:hover {
                    background: var(--brand-primary);
                    border-color: var(--brand-primary);
                    color: white;
                    transform: translateY(-1px);
                    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
                }

                .btn-preset:active {
                    transform: translateY(0);
                }

                /* Responsive: Stack motivation content on mobile */
                @media (max-width: 768px) {
                    .motivation-content {
                        flex-direction: column !important;
                        gap: 1rem !important;
                    }
                    
                    .insights-subsection {
                        width: 100%;
                    }
                    
                    .user-selector-wrapper {
                        flex-direction: column;
                        align-items: flex-start;
                        margin-left: 0;
                        margin-top: 1rem;
                    }
                    
                    .user-selector-dropdown {
                        min-width: 100%;
                    }
                }

                .team-availability-section {
                    padding: 1.5rem !important;
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 1rem;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                    overflow: visible;
                }

                /* Ensure consistent alignment */
                .section-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1.5rem;
                    gap: 1rem;
                    flex-wrap: wrap;
                }
                
                /* Team Availability Section Header */
                .team-availability-section .section-header {
                    margin-bottom: 1.5rem;
                    padding-bottom: 1rem;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                }
                
                .team-availability-section .section-title {
                    font-size: 1.375rem;
                    font-weight: 600;
                    color: var(--dark-text-primary);
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 0.625rem;
                    line-height: 1.4;
                }
                
                .team-availability-section .section-title i {
                    color: #667eea;
                    font-size: 1.125rem;
                }
                
                /* Availability Stats Container */
                .availability-stats {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    flex-wrap: nowrap;
                }
                
                /* Stat Item Badges */
                .stat-item {
                    display: flex;
                    align-items: center;
                    gap: 0.375rem;
                    padding: 0.375rem 0.625rem;
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 0.375rem;
                    font-size: 0.75rem;
                    font-weight: 500;
                    color: var(--dark-text-primary);
                    line-height: 1.3;
                    transition: all 0.3s ease;
                    position: relative;
                    white-space: nowrap;
                    flex-shrink: 0;
                    overflow: hidden;
                }
                
                .stat-item::before {
                    content: '';
                    position: absolute;
                    left: 0;
                    top: 0;
                    bottom: 0;
                    width: 3px;
                    border-radius: 0.625rem 0 0 0.625rem;
                    transition: width 0.3s ease;
                }
                
                .stat-item:hover {
                    background: rgba(255, 255, 255, 0.08);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                }
                
                /* Available Stat Item */
                .stat-item.available {
                    border-left: 3px solid #10b981;
                }
                
                .stat-item.available::before {
                    background: #10b981;
                }
                
                .stat-item.available:hover {
                    border-left-color: #059669;
                    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
                }
                
                /* On WFH Stat Item */
                .stat-item.on-wfh {
                    border-left: 3px solid #3b82f6;
                }
                
                .stat-item.on-wfh::before {
                    background: #3b82f6;
                }
                
                .stat-item.on-wfh:hover {
                    border-left-color: #2563eb;
                    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
                }
                
                /* On Leave Stat Item */
                .stat-item.on-leave {
                    border-left: 3px solid #ef4444;
                }
                
                .stat-item.on-leave::before {
                    background: #ef4444;
                }
                
                .stat-item.on-leave:hover {
                    border-left-color: #dc2626;
                    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
                }
                
                /* Stat Dot */
                .stat-dot {
                    width: 6px;
                    height: 6px;
                    border-radius: 50%;
                    display: inline-block;
                    flex-shrink: 0;
                    box-shadow: 0 0 6px currentColor;
                    animation: pulse 2s ease-in-out infinite;
                }
                
                @keyframes pulse {
                    0%, 100% {
                        opacity: 1;
                        transform: scale(1);
                    }
                    50% {
                        opacity: 0.8;
                        transform: scale(1.1);
                    }
                }
                
                .stat-item.available .stat-dot {
                    background: #10b981;
                    color: #10b981;
                    box-shadow: 0 0 6px rgba(16, 185, 129, 0.5);
                }
                
                .stat-item.on-wfh .stat-dot {
                    background: #3b82f6;
                    color: #3b82f6;
                    box-shadow: 0 0 6px rgba(59, 130, 246, 0.5);
                }
                
                .stat-item.on-leave .stat-dot {
                    background: #ef4444;
                    color: #ef4444;
                    box-shadow: 0 0 6px rgba(239, 68, 68, 0.5);
                }
                
                /* Stat Item Text */
                .stat-item span:not(.stat-dot) {
                    font-weight: 500;
                    color: var(--dark-text-primary);
                }
                
                .stat-item span[id] {
                    font-weight: 600;
                    color: var(--dark-text-primary);
                }
                
                /* Team Grid */
                .team-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
                    gap: 1rem;
                    margin-top: 1rem;
                    padding: 0;
                }
                
                /* Team Member Card */
                .team-member {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.875rem 0.75rem;
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 0.75rem;
                    transition: all 0.3s ease;
                    cursor: pointer;
                    position: relative;
                    overflow: visible;
                    z-index: 1;
                }
                
                .team-member:hover {
                    transform: translateY(-4px) scale(1.02);
                    background: rgba(255, 255, 255, 0.08);
                    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
                    border-color: rgba(99, 102, 241, 0.4);
                    z-index: 10;
                }
                
                .team-member.available {
                    border-left: 3px solid #10b981;
                }
                
                .team-member.remote {
                    border-left: 3px solid #3b82f6;
                }
                
                .team-member.on-leave {
                    border-left: 3px solid #ef4444;
                    opacity: 0.85;
                }
                
                .team-member.on-leave:hover {
                    opacity: 1;
                }
                
                /* Member Avatar */
                .member-avatar {
                    width: 48px;
                    height: 48px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1rem;
                    font-weight: 700;
                    color: white;
                    margin-bottom: 0;
                    background: var(--gradient-primary);
                    border: 2px solid rgba(255, 255, 255, 0.2);
                    transition: all 0.3s ease;
                    flex-shrink: 0;
                }
                
                .team-member:hover .member-avatar {
                    transform: scale(1.1);
                    border-color: rgba(99, 102, 241, 0.6);
                    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
                }
                
                .member-avatar img {
                    width: 100%;
                    height: 100%;
                    border-radius: 50%;
                    object-fit: cover;
                }
                
                /* Member Name */
                .member-name {
                    font-size: 0.8125rem;
                    font-weight: 500;
                    color: var(--dark-text-primary);
                    text-align: center;
                    margin: 0;
                    line-height: 1.3;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    max-width: 100%;
                }
                
                /* Member Status Indicator */
                .member-status {
                    width: 10px;
                    height: 10px;
                    border-radius: 50%;
                    background: #10b981;
                    box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
                    flex-shrink: 0;
                    margin-top: 0.25rem;
                }
                
                .member-status.remote {
                    background: #3b82f6;
                    box-shadow: 0 0 8px rgba(59, 130, 246, 0.5);
                }
                
                .member-status.on-leave {
                    background: #ef4444;
                    box-shadow: 0 0 8px rgba(239, 68, 68, 0.5);
                }
                
                /* Responsive adjustments */
                @media (max-width: 768px) {
                    .team-grid {
                        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                        gap: 0.75rem;
                    }
                    
                    .member-avatar {
                        width: 42px;
                        height: 42px;
                        font-size: 0.9rem;
                    }
                    
                    .member-name {
                        font-size: 0.75rem;
                    }
                    
                    .availability-stats {
                        gap: 0.375rem;
                        flex-wrap: nowrap;
                    }
                    
                    .stat-item {
                        padding: 0.375rem 0.625rem;
                        font-size: 0.75rem;
                        white-space: nowrap;
                    }
                }

                .stats-header {
                    margin-bottom: 1.5rem;
                }

                /* Premium Enhanced System Overview Cards - Inspired by Team Performance Cards */
                .system-stat-card {
                    background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(42, 42, 42, 0.95) 100%);
                    padding: 1.75rem 2rem;
                    border-radius: 20px;
                    backdrop-filter: blur(20px);
                    -webkit-backdrop-filter: blur(20px);
                    border: 1px solid rgba(102, 126, 234, 0.2);
                    color: var(--dark-text-primary);
                    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                    box-shadow: 
                        0 8px 32px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 2px 8px rgba(102, 126, 234, 0.1);
                    position: relative;
                    overflow: hidden;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 1.5rem;
                    width: 100%;
                }

                /* Shimmer Animation Effect - Top Border */
                .system-stat-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #667eea 100%);
                    background-size: 200% 100%;
                    animation: shimmer 3s ease-in-out infinite;
                    opacity: 0.6;
                    z-index: 1;
                }

                /* Color-Coded Shimmer for Each Card Type */
                .system-stat-total::before {
                    background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 50%, #6366f1 100%);
                }

                .system-stat-completed::before {
                    background: linear-gradient(90deg, #06b6d4 0%, #10b981 50%, #06b6d4 100%);
                }

                .system-stat-pending::before {
                    background: linear-gradient(90deg, #f59e0b 0%, #d97706 50%, #f59e0b 100%);
                }

                .system-stat-delayed::before {
                    background: linear-gradient(90deg, #f59e0b 0%, #ef4444 50%, #f59e0b 100%);
                }

                @keyframes shimmer {
                    0%, 100% { background-position: 0% 50%; }
                    50% { background-position: 100% 50%; }
                }

                /* Background Gradient Layer */
                .system-stat-card::after {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    border-radius: 20px;
                    opacity: 0.15;
                    transition: opacity 0.4s ease;
                    z-index: 0;
                }

                /* Color-Coded Background Gradients */
                .system-stat-total::after {
                    background: linear-gradient(135deg, rgba(99, 102, 241, 0.3) 0%, rgba(139, 92, 246, 0.3) 100%);
                }

                .system-stat-completed::after {
                    background: linear-gradient(135deg, rgba(6, 182, 212, 0.3) 0%, rgba(16, 185, 129, 0.3) 100%);
                }

                .system-stat-pending::after {
                    background: linear-gradient(135deg, rgba(245, 158, 11, 0.3) 0%, rgba(217, 119, 6, 0.3) 100%);
                }

                .system-stat-delayed::after {
                    background: linear-gradient(135deg, rgba(245, 158, 11, 0.3) 0%, rgba(239, 68, 68, 0.3) 100%);
                }

                /* Color-Coded Glow Effect Behind Cards - Override default box-shadow */
                .system-stat-total {
                    box-shadow: 
                        0 8px 32px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 2px 8px rgba(102, 126, 234, 0.1),
                        0 0 30px rgba(99, 102, 241, 0.2) !important;
                }

                .system-stat-completed {
                    box-shadow: 
                        0 8px 32px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 2px 8px rgba(102, 126, 234, 0.1),
                        0 0 30px rgba(6, 182, 212, 0.2) !important;
                }

                .system-stat-pending {
                    box-shadow: 
                        0 8px 32px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 2px 8px rgba(102, 126, 234, 0.1),
                        0 0 30px rgba(245, 158, 11, 0.2) !important;
                }

                .system-stat-delayed {
                    box-shadow: 
                        0 8px 32px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 2px 8px rgba(102, 126, 234, 0.1),
                        0 0 30px rgba(239, 68, 68, 0.2) !important;
                }

                /* Enhanced Hover Effects with Color-Coded Glows */
                .system-stat-total:hover {
                    transform: translateY(-8px) scale(1.02);
                    box-shadow: 
                        0 20px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(139, 92, 246, 0.3) inset,
                        0 4px 16px rgba(0, 0, 0, 0.3),
                        0 0 50px rgba(99, 102, 241, 0.4);
                    border-color: rgba(139, 92, 246, 0.4);
                }

                .system-stat-completed:hover {
                    transform: translateY(-8px) scale(1.02);
                    box-shadow: 
                        0 20px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(16, 185, 129, 0.3) inset,
                        0 4px 16px rgba(0, 0, 0, 0.3),
                        0 0 50px rgba(6, 182, 212, 0.4);
                    border-color: rgba(16, 185, 129, 0.4);
                }

                .system-stat-pending:hover {
                    transform: translateY(-8px) scale(1.02);
                    box-shadow: 
                        0 20px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(217, 119, 6, 0.3) inset,
                        0 4px 16px rgba(0, 0, 0, 0.3),
                        0 0 50px rgba(245, 158, 11, 0.4);
                    border-color: rgba(217, 119, 6, 0.4);
                }

                .system-stat-delayed:hover {
                    transform: translateY(-8px) scale(1.02);
                    box-shadow: 
                        0 20px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(239, 68, 68, 0.3) inset,
                        0 4px 16px rgba(0, 0, 0, 0.3),
                        0 0 50px rgba(239, 68, 68, 0.4);
                    border-color: rgba(239, 68, 68, 0.4);
                }

                .system-stat-card:hover::after {
                    opacity: 0.25;
                }

                .system-stat-card:hover::before {
                    opacity: 1;
                }

                /* Enhanced Icon Styling with Color-Coded Backgrounds */
                .system-stat-icon {
                    width: 60px;
                    height: 60px;
                    border-radius: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.75rem;
                    background: rgba(102, 126, 234, 0.1);
                    backdrop-filter: blur(10px);
                    -webkit-backdrop-filter: blur(10px);
                    border: 1px solid rgba(102, 126, 234, 0.2);
                    box-shadow: 
                        0 4px 16px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 0 20px rgba(102, 126, 234, 0.2);
                    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                    position: relative;
                    z-index: 2;
                    flex-shrink: 0;
                }

                /* Color-Coded Icon Backgrounds */
                .system-stat-total .system-stat-icon {
                    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
                    border-color: rgba(139, 92, 246, 0.3);
                    box-shadow: 
                        0 4px 16px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 0 20px rgba(99, 102, 241, 0.3);
                }

                .system-stat-completed .system-stat-icon {
                    background: linear-gradient(135deg, rgba(6, 182, 212, 0.2) 0%, rgba(16, 185, 129, 0.2) 100%);
                    border-color: rgba(16, 185, 129, 0.3);
                    box-shadow: 
                        0 4px 16px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 0 20px rgba(6, 182, 212, 0.3);
                }

                .system-stat-pending .system-stat-icon {
                    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(217, 119, 6, 0.2) 100%);
                    border-color: rgba(217, 119, 6, 0.3);
                    box-shadow: 
                        0 4px 16px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 0 20px rgba(245, 158, 11, 0.3);
                }

                .system-stat-delayed .system-stat-icon {
                    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(239, 68, 68, 0.2) 100%);
                    border-color: rgba(239, 68, 68, 0.3);
                    box-shadow: 
                        0 4px 16px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                        0 0 20px rgba(245, 158, 11, 0.3);
                }

                /* Color-Coded Icon Colors */
                .system-stat-total .system-stat-icon i {
                    color: #a78bfa;
                    filter: drop-shadow(0 2px 8px rgba(139, 92, 246, 0.5));
                }

                .system-stat-completed .system-stat-icon i {
                    color: #34d399;
                    filter: drop-shadow(0 2px 8px rgba(16, 185, 129, 0.5));
                }

                .system-stat-pending .system-stat-icon i {
                    color: #fbbf24;
                    filter: drop-shadow(0 2px 8px rgba(217, 119, 6, 0.5));
                }

                .system-stat-delayed .system-stat-icon i {
                    color: #f87171;
                    filter: drop-shadow(0 2px 8px rgba(239, 68, 68, 0.5));
                }

                .system-stat-card:hover .system-stat-icon {
                    transform: scale(1.15) rotate(5deg);
                    box-shadow: 
                        0 8px 30px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.15) inset,
                        0 0 30px rgba(102, 126, 234, 0.4);
                }

                /* Enhanced Content Styling */
                .system-stat-content {
                    flex: 1;
                    min-width: 0;
                    position: relative;
                    z-index: 2;
                }

                .system-stat-value {
                    font-size: 2rem;
                    font-weight: 800;
                    margin: 0;
                    line-height: 1.2;
                    background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.8) 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
                    transition: all 0.4s ease;
                    letter-spacing: -0.02em;
                }

                /* Color-Coded Value Gradients */
                .system-stat-total .system-stat-value {
                    background: linear-gradient(135deg, #a78bfa 0%, #c4b5fd 50%, #ddd6fe 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                .system-stat-completed .system-stat-value {
                    background: linear-gradient(135deg, #34d399 0%, #6ee7b7 50%, #a7f3d0 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                .system-stat-pending .system-stat-value {
                    background: linear-gradient(135deg, #fbbf24 0%, #fcd34d 50%, #fde68a 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                .system-stat-delayed .system-stat-value {
                    background: linear-gradient(135deg, #f87171 0%, #fca5a5 50%, #fecaca 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                .system-stat-card:hover .system-stat-value {
                    transform: scale(1.08) translateY(-2px);
                    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
                }

                .system-stat-label {
                    font-size: 0.8rem;
                    color: rgba(255, 255, 255, 0.7);
                    margin: 0.5rem 0 0 0;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    transition: all 0.4s ease;
                }

                .system-stat-card:hover .system-stat-label {
                    color: rgba(255, 255, 255, 0.95);
                    transform: translateY(-2px);
                }

                /* Responsive adjustments */
                @media (max-width: 768px) {
                    .system-stat-card {
                        padding: 1.5rem 1.75rem;
                        gap: 1.25rem;
                    }

                    .system-stat-icon {
                        width: 50px;
                        height: 50px;
                        font-size: 1.5rem;
                    }

                    .system-stat-value {
                        font-size: 1.75rem;
                    }

                    .system-stat-label {
                        font-size: 0.75rem;
                        margin-top: 0.5rem;
                    }
                }

                /* Admin Dashboard - All 4 stat cards in one row */
                #adminDashboard .stats-grid {
                    grid-template-columns: repeat(4, 1fr) !important;
                }

                /* Personal Overview stats grid - 8 cards (4x2) */
                #adminDashboard #personalStatsGrid {
                    grid-template-columns: repeat(4, 1fr) !important;
                }

                /* Responsive adjustments for admin dashboard stats */
                @media (max-width: 1200px) {
                    #adminDashboard .stats-grid {
                        grid-template-columns: repeat(2, 1fr) !important;
                    }
                    #adminDashboard #personalStatsGrid {
                        grid-template-columns: repeat(2, 1fr) !important;
                    }
                }

                @media (max-width: 768px) {
                    #adminDashboard .stats-grid {
                        grid-template-columns: 1fr !important;
                    }
                    #adminDashboard #personalStatsGrid {
                        grid-template-columns: 1fr !important;
                    }
                }
                </style>

                <script>
                // Global variables
                let currentDateRange = {
                    type: 'this_week',
                    fromDate: null,
                    toDate: null
                };

                let currentPersonalDateRange = {
                    type: 'this_week',
                    fromDate: null,
                    toDate: null
                };

                // Helper function to get Monday of a given week (week runs Monday to Sunday)
                function getMondayOfWeek(date) {
                    const d = new Date(date);
                    d.setHours(0, 0, 0, 0);
                    const day = d.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
                    const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
                    const monday = new Date(d);
                    monday.setDate(diff);
                    return monday;
                }

                // Calculate date range based on week-based options
                function calculateWeekDateRange(rangeType) {
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    let fromDate, toDate;
                    
                    switch(rangeType) {
                        case 'this_week':
                            // Monday of current week to today (inclusive)
                            fromDate = getMondayOfWeek(today);
                            toDate = new Date(today);
                            break;
                            
                        case 'last_week':
                            // Monday to Sunday of last week
                            const thisWeekMonday = getMondayOfWeek(today);
                            const lastWeekMonday = new Date(thisWeekMonday);
                            lastWeekMonday.setDate(thisWeekMonday.getDate() - 7);
                            fromDate = lastWeekMonday;
                            toDate = new Date(lastWeekMonday);
                            toDate.setDate(lastWeekMonday.getDate() + 6);
                            break;
                            
                        case 'last_2_weeks':
                            // Monday of 2 weeks ago to Sunday of last week
                            const thisWeekMonday2 = getMondayOfWeek(today);
                            const twoWeeksAgoMonday = new Date(thisWeekMonday2);
                            twoWeeksAgoMonday.setDate(thisWeekMonday2.getDate() - 14);
                            fromDate = twoWeeksAgoMonday;
                            const lastWeekSunday = new Date(thisWeekMonday2);
                            lastWeekSunday.setDate(thisWeekMonday2.getDate() - 1);
                            toDate = lastWeekSunday;
                            break;
                            
                        case 'last_4_weeks':
                            // Monday of 4 weeks ago to Sunday of last week
                            const thisWeekMonday4 = getMondayOfWeek(today);
                            const fourWeeksAgoMonday = new Date(thisWeekMonday4);
                            fourWeeksAgoMonday.setDate(thisWeekMonday4.getDate() - 28);
                            fromDate = fourWeeksAgoMonday;
                            const lastWeekSunday4 = new Date(thisWeekMonday4);
                            lastWeekSunday4.setDate(thisWeekMonday4.getDate() - 1);
                            toDate = lastWeekSunday4;
                            break;
                            
                            
                        default:
                            // Default to this week
                            fromDate = getMondayOfWeek(today);
                            toDate = new Date(today);
                    }
                    
                    // Format dates as YYYY-MM-DD
                    const formatDate = (date) => {
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    };
                    
                    return {
                        fromDate: formatDate(fromDate),
                        toDate: formatDate(toDate)
                    };
                }

                // Leaderboard variables
                let leaderboardData = [];
                let currentLeaderboardPage = 1;
                const leaderboardItemsPerPage = 3; // Show 3 ranks per page
                let currentLeaderboardPeriod = 'last_week'; // 'last_week', 'last_2_weeks', 'last_4_weeks'

                // Track if this is the first load
                let isFirstLoad = true;

                // User Motivation Insights Management
                let currentUserId = null;
                let originalInsights = { current_insights: '', areas_of_improvement: '' };
                
                // Initialize motivation section handlers
                function initMotivationSection() {
                    const userSelector = document.getElementById('userMotivationSelector');
                    const motivationContent = document.getElementById('userMotivationContent');
                    
                    if (!userSelector) return;
                    
                    // Handle user selection
                    userSelector.addEventListener('change', function() {
                        const userId = this.value;
                        if (userId) {
                            currentUserId = userId;
                            loadUserMotivation(userId);
                            motivationContent.style.display = 'flex';
                            motivationContent.style.flexDirection = 'row';
                            motivationContent.style.gap = '1.5rem';
                            motivationContent.style.alignItems = 'flex-start';
                            // Show edit mode by default for easier editing
                            setTimeout(() => {
                                showEditMode('currentInsights');
                                showEditMode('areasOfImprovement');
                                document.getElementById('motivationActionsContainer').style.display = 'flex';
                            }, 100);
                        } else {
                            currentUserId = null;
                            motivationContent.style.display = 'none';
                            document.getElementById('motivationActionsContainer').style.display = 'none';
                        }
                    });
                    
                    // Save All button - saves both fields at once
                    document.getElementById('saveAllMotivation')?.addEventListener('click', function() {
                        saveAllMotivationData();
                    });
                    
                    // Cancel All button - cancels changes and reloads data
                    document.getElementById('cancelAllMotivation')?.addEventListener('click', function() {
                        cancelAllMotivationData();
                    });
                    
                    // Preset buttons - handle clicks on preset buttons
                    document.querySelectorAll('.btn-preset').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const presetType = this.getAttribute('data-preset');
                            const presetValue = this.getAttribute('data-value');
                            
                            if (presetType === 'current_insights') {
                                const textarea = document.getElementById('currentInsightsTextarea');
                                const currentValue = textarea.value.trim();
                                if (currentValue) {
                                    textarea.value = currentValue + '\n\n' + presetValue;
                                } else {
                                    textarea.value = presetValue;
                                }
                                textarea.focus();
                                textarea.scrollTop = textarea.scrollHeight;
                            } else if (presetType === 'areas_of_improvement') {
                                const textarea = document.getElementById('areasOfImprovementTextarea');
                                const currentValue = textarea.value.trim();
                                if (currentValue) {
                                    textarea.value = currentValue + '\n\n' + presetValue;
                                } else {
                                    textarea.value = presetValue;
                                }
                                textarea.focus();
                                textarea.scrollTop = textarea.scrollHeight;
                            }
                        });
                    });
                }
                
                // Load user motivation data
                function loadUserMotivation(userId) {
                    const motivationContent = document.getElementById('userMotivationContent');
                    motivationContent.innerHTML = '<div class="loading-motivation"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
                    
                    fetch(`../ajax/get_user_motivation.php?user_id=${userId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const currentInsightsValue = data.data.current_insights || '';
                                const areasOfImprovementValue = data.data.areas_of_improvement || '';
                                
                                // Store original values
                                originalInsights = {
                                    current_insights: currentInsightsValue,
                                    areas_of_improvement: areasOfImprovementValue
                                };
                                
                                // Restore the content structure
                                restoreMotivationContent();
                                
                                document.getElementById('currentInsightsTextarea').value = currentInsightsValue;
                                document.getElementById('areasOfImprovementTextarea').value = areasOfImprovementValue;
                                
                                updateDisplay('currentInsights', currentInsightsValue);
                                updateDisplay('areasOfImprovement', areasOfImprovementValue);
                                
                                showEditMode('currentInsights');
                                showEditMode('areasOfImprovement');
                            } else {
                                showToast('Error loading motivation data: ' + (data.error || 'Unknown error'), 'error');
                                restoreMotivationContent();
                            }
                        })
                        .catch(error => {
                            showToast('Error loading motivation data', 'error');
                            restoreMotivationContent();
                        });
                }
                
                // Restore motivation content structure
                function restoreMotivationContent() {
                    const motivationContent = document.getElementById('userMotivationContent');
                    motivationContent.innerHTML = `
                        <!-- Current Insights -->
                        <div class="insights-subsection">
                            <div class="subsection-title">
                                <i class="fas fa-chart-line"></i>
                                Current Insights
                            </div>
                            <div class="insight-editable-container">
                                <div class="insight-display" id="currentInsightsDisplay">
                                    <p class="insight-placeholder">No insights available. Select a user to view or edit.</p>
                                </div>
                                <div class="insight-edit" id="currentInsightsEdit" style="display: none;">
                                    <textarea class="form-control insight-textarea" id="currentInsightsTextarea" rows="6" placeholder="Enter current insights for this user..."></textarea>
                                    <div class="preset-suggestions">
                                        <label class="preset-label">Quick Presets:</label>
                                        <div class="preset-buttons">
                                            <button type="button" class="btn-preset" data-preset="current_insights" data-value="🔥 Excellent performance this week! Keep up the great work!">🔥 Excellent Performance</button>
                                            <button type="button" class="btn-preset" data-preset="current_insights" data-value="⚡ Strong task completion rate. Meeting deadlines consistently.">⚡ Strong Completion Rate</button>
                                            <button type="button" class="btn-preset" data-preset="current_insights" data-value="📈 Showing steady improvement in task management and efficiency.">📈 Steady Improvement</button>
                                            <button type="button" class="btn-preset" data-preset="current_insights" data-value="🎯 Consistently delivering high-quality work on time.">🎯 High Quality Work</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Areas of Improvement -->
                        <div class="insights-subsection">
                            <div class="subsection-title">
                                <i class="fas fa-target"></i>
                                Areas of Improvement / Focus
                            </div>
                            <div class="insight-editable-container">
                                <div class="insight-display" id="areasOfImprovementDisplay">
                                    <p class="insight-placeholder">No improvement areas defined. Select a user to view or edit.</p>
                                </div>
                                <div class="insight-edit" id="areasOfImprovementEdit" style="display: none;">
                                    <textarea class="form-control insight-textarea" id="areasOfImprovementTextarea" rows="6" placeholder="Enter areas of improvement for this user..."></textarea>
                                    <div class="preset-suggestions">
                                        <label class="preset-label">Quick Presets:</label>
                                        <div class="preset-buttons">
                                            <button type="button" class="btn-preset" data-preset="areas_of_improvement" data-value="⏰ Time Management - Focus on completing tasks 2 hours earlier to boost efficiency.">⏰ Time Management</button>
                                            <button type="button" class="btn-preset" data-preset="areas_of_improvement" data-value="🤝 Team Collaboration - Increase peer feedback participation by 15% this month.">🤝 Team Collaboration</button>
                                            <button type="button" class="btn-preset" data-preset="areas_of_improvement" data-value="📊 Communication - Improve clarity in task updates and status reports.">📊 Communication</button>
                                            <button type="button" class="btn-preset" data-preset="areas_of_improvement" data-value="🎯 Goal Setting - Set more specific and measurable targets for better tracking.">🎯 Goal Setting</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Re-bind preset buttons
                    document.querySelectorAll('.btn-preset').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const presetType = this.getAttribute('data-preset');
                            const presetValue = this.getAttribute('data-value');
                            
                            if (presetType === 'current_insights') {
                                const textarea = document.getElementById('currentInsightsTextarea');
                                const currentValue = textarea.value.trim();
                                if (currentValue) {
                                    textarea.value = currentValue + '\n\n' + presetValue;
                                } else {
                                    textarea.value = presetValue;
                                }
                                textarea.focus();
                                textarea.scrollTop = textarea.scrollHeight;
                            } else if (presetType === 'areas_of_improvement') {
                                const textarea = document.getElementById('areasOfImprovementTextarea');
                                const currentValue = textarea.value.trim();
                                if (currentValue) {
                                    textarea.value = currentValue + '\n\n' + presetValue;
                                } else {
                                    textarea.value = presetValue;
                                }
                                textarea.focus();
                                textarea.scrollTop = textarea.scrollHeight;
                            }
                        });
                    });
                }
                
                // Show edit mode
                function showEditMode(type) {
                    const display = document.getElementById(type + 'Display');
                    const edit = document.getElementById(type + 'Edit');
                    
                    if (display && edit) {
                        display.style.display = 'none';
                        edit.style.display = 'block';
                    }
                }
                
                // Update display
                function updateDisplay(type, value) {
                    const display = document.getElementById(type + 'Display');
                    if (display) {
                        if (value && value.trim()) {
                            display.innerHTML = '<p>' + escapeHtml(value).replace(/\n/g, '<br>') + '</p>';
                        } else {
                            display.innerHTML = '<p class="insight-placeholder">No ' + (type === 'currentInsights' ? 'insights' : 'improvement areas') + ' defined. Click edit to add.</p>';
                        }
                    }
                }
                
                // Save all motivation data
                function saveAllMotivationData() {
                    if (!currentUserId) {
                        showToast('Please select a user first', 'error');
                        return;
                    }
                    
                    const saveButton = document.getElementById('saveAllMotivation');
                    const originalText = saveButton.innerHTML;
                    saveButton.disabled = true;
                    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    
                    const formData = new FormData();
                    formData.append('user_id', currentUserId);
                    formData.append('current_insights', document.getElementById('currentInsightsTextarea').value);
                    formData.append('areas_of_improvement', document.getElementById('areasOfImprovementTextarea').value);
                    
                    fetch('../ajax/update_user_motivation.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast('Motivation insights updated successfully!', 'success');
                                // Update original values
                                originalInsights = {
                                    current_insights: document.getElementById('currentInsightsTextarea').value,
                                    areas_of_improvement: document.getElementById('areasOfImprovementTextarea').value
                                };
                                // Notify doer dashboard to refresh
                                notifyDoerDashboardRefresh(currentUserId);
                                
                                // Collapse section and reset user selector
                                const userSelector = document.getElementById('userMotivationSelector');
                                const motivationContent = document.getElementById('userMotivationContent');
                                const actionsContainer = document.getElementById('motivationActionsContainer');
                                
                                if (userSelector) {
                                    userSelector.value = '';
                                }
                                if (motivationContent) {
                                    motivationContent.style.display = 'none';
                                }
                                if (actionsContainer) {
                                    actionsContainer.style.display = 'none';
                                }
                                
                                // Reset current user ID
                                currentUserId = null;
                            } else {
                                showToast('Error updating: ' + (data.error || 'Unknown error'), 'error');
                            }
                        })
                        .catch(error => {
                            showToast('Error updating motivation data', 'error');
                        })
                        .finally(() => {
                            saveButton.disabled = false;
                            saveButton.innerHTML = originalText;
                        });
                }
                
                // Cancel all changes and reset form
                function cancelAllMotivationData() {
                    // Reset textarea values to original
                    if (currentUserId) {
                        document.getElementById('currentInsightsTextarea').value = originalInsights.current_insights || '';
                        document.getElementById('areasOfImprovementTextarea').value = originalInsights.areas_of_improvement || '';
                        
                        // Hide edit modes and show display modes
                        const currentInsightsDisplay = document.getElementById('currentInsightsDisplay');
                        const currentInsightsEdit = document.getElementById('currentInsightsEdit');
                        const areasOfImprovementDisplay = document.getElementById('areasOfImprovementDisplay');
                        const areasOfImprovementEdit = document.getElementById('areasOfImprovementEdit');
                        
                        if (currentInsightsDisplay && currentInsightsEdit) {
                            currentInsightsEdit.style.display = 'none';
                            currentInsightsDisplay.style.display = 'block';
                            // Update display with original value
                            updateDisplay('currentInsights', originalInsights.current_insights || '');
                        }
                        
                        if (areasOfImprovementDisplay && areasOfImprovementEdit) {
                            areasOfImprovementEdit.style.display = 'none';
                            areasOfImprovementDisplay.style.display = 'block';
                            // Update display with original value
                            updateDisplay('areasOfImprovement', originalInsights.areas_of_improvement || '');
                    }
                    }
                    
                    // Reset user selector
                    const userSelector = document.getElementById('userMotivationSelector');
                    if (userSelector) {
                        userSelector.value = '';
                    }
                    
                    // Hide motivation content
                    const motivationContent = document.getElementById('userMotivationContent');
                    if (motivationContent) {
                        motivationContent.style.display = 'none';
                    }
                    
                    // Hide action buttons
                    const actionsContainer = document.getElementById('motivationActionsContainer');
                    if (actionsContainer) {
                        actionsContainer.style.display = 'none';
                    }
                    
                    // Reset current user ID
                    currentUserId = null;
                    
                    // Reset original insights
                    originalInsights = { current_insights: '', areas_of_improvement: '' };
                    
                    showToast('Form reset successfully', 'info');
                }
                
                // Notify doer dashboard to refresh
                function notifyDoerDashboardRefresh(userId) {
                    const refreshKey = `motivation_refresh_${userId}_${Date.now()}`;
                    localStorage.setItem('motivation_insights_updated', refreshKey);
                    window.dispatchEvent(new CustomEvent('motivationInsightsUpdated', {
                        detail: { userId: userId, timestamp: Date.now() }
                    }));
                }
                
                // Escape HTML helper
                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                // Toast notification function
                function showToast(message, type = 'info') {
                    const toast = document.createElement('div');
                    toast.className = `toast-notification toast-${type}`;
                    toast.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                        color: white;
                        padding: 1rem 1.5rem;
                        border-radius: 0.5rem;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                        z-index: 10000;
                        animation: slideIn 0.3s ease;
                    `;
                    toast.textContent = message;
                    document.body.appendChild(toast);
                    
                    setTimeout(() => {
                        toast.style.animation = 'slideOut 0.3s ease';
                        setTimeout(() => toast.remove(), 300);
                    }, 3000);
                }

                // Initialize admin dashboard
                document.addEventListener('DOMContentLoaded', function() {
                    // Set initial system overview title
                    updateSystemOverviewTitle('this_week');
                    
                    // Set initial personal overview caption
                    updatePersonalOverviewCaption('this_week');
                    
                    initializeDateRangeSelector();
                    initializePersonalDateRangeSelector();
                    loadDashboardData();
                    initializeDailyQuotes();
                    initializeLeaveDetailsModal();
                    initMotivationSection();
                    
                    // Pagination is handled by goToLeaderboardPage function
                    
                    // Set up time period selector
                    const periodButtons = document.querySelectorAll('.period-btn');
                    periodButtons.forEach(btn => {
                        btn.addEventListener('click', function() {
                            periodButtons.forEach(b => b.classList.remove('active'));
                            this.classList.add('active');
                            currentLeaderboardPeriod = this.getAttribute('data-period');
                            loadLeaderboardData(); // Only reload when period button is clicked
                        });
                    });
                    
                    // Load leaderboard data with default "Last Week" period on initial load
                    loadLeaderboardData();
                    
                    // Auto-refresh every 10 minutes
                    setInterval(() => loadDashboardData(), 600000);
                });

                // Loading overlay functions
                function showLoadingOverlay() {
                    const existingOverlay = document.getElementById('dashboardLoadingOverlay');
                    if (existingOverlay) {
                        existingOverlay.remove();
                    }
                    
                    const overlay = document.createElement('div');
                    overlay.id = 'dashboardLoadingOverlay';
                    overlay.innerHTML = `
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Loading dashboard...</p>
                        </div>
                    `;
                    document.body.appendChild(overlay);
                }

                function hideLoadingOverlay() {
                    const overlay = document.getElementById('dashboardLoadingOverlay');
                    if (overlay) {
                        overlay.style.opacity = '0';
                        setTimeout(() => overlay.remove(), 300);
                    }
                }

                // Load dashboard data
                async function loadDashboardData() {
                    try {
                        // Show loading overlay only on first load
                        if (isFirstLoad) {
                            showLoadingOverlay();
                        }
                        
                        let url = '../ajax/admin_dashboard_data.php';
                        const params = new URLSearchParams();
                        
                        // System Overview date range
                        if (currentDateRange.fromDate && currentDateRange.toDate) {
                            // Send calculated date range
                            params.append('date_from', currentDateRange.fromDate);
                            params.append('date_to', currentDateRange.toDate);
                            // Also send the range type for reference
                            params.append('date_range', currentDateRange.type);
                        } else {
                            // Fallback: calculate dates if not set
                            const dateRange = calculateWeekDateRange(currentDateRange.type);
                            if (dateRange.fromDate && dateRange.toDate) {
                                params.append('date_from', dateRange.fromDate);
                                params.append('date_to', dateRange.toDate);
                            }
                            params.append('date_range', currentDateRange.type);
                        }
                        
                        // Personal Overview date range
                        if (currentPersonalDateRange.fromDate && currentPersonalDateRange.toDate) {
                            // Send calculated date range
                            params.append('personal_date_from', currentPersonalDateRange.fromDate);
                            params.append('personal_date_to', currentPersonalDateRange.toDate);
                            // Also send the range type for reference
                            params.append('personal_date_range', currentPersonalDateRange.type);
                        } else {
                            // Fallback: calculate dates if not set
                            const dateRange = calculateWeekDateRange(currentPersonalDateRange.type);
                            if (dateRange.fromDate && dateRange.toDate) {
                                params.append('personal_date_from', dateRange.fromDate);
                                params.append('personal_date_to', dateRange.toDate);
                            }
                            params.append('personal_date_range', currentPersonalDateRange.type);
                        }
                        
                        if (params.toString()) {
                            url += '?' + params.toString();
                        }
                        
                        const response = await fetch(url);
                        const result = await response.json();
                        
                        if (result.success) {
                            updateDashboard(result.data);
                        } else {
                            hideLoadingOverlay();
                        }
                    } catch (error) {
                        hideLoadingOverlay();
                    }
                }

                // Update dashboard with data
                function updateDashboard(data) {
                    if (!data) {
                        hideLoadingOverlay();
                        return;
                    }
                    
                    // Batch all updates together using requestAnimationFrame
                    requestAnimationFrame(() => {
                        // Update system stats (instant on first load, animated on refresh)
                    if (data.system_stats) {
                            updateSystemStats(data.system_stats, isFirstLoad);
                    }
                    
                    // Update personal stats
                    if (data.personal_stats) {
                        // Check if RQC score is valid (not 0 or null)
                        const rqcScore = (data.personal_rqc_score !== undefined ? data.personal_rqc_score : data.personal_completion_rate);
                        const numScore = parseFloat(rqcScore);
                        const validRqcScore = (!isNaN(numScore) && numScore > 0 && isFinite(numScore)) ? numScore : null;
                        updatePersonalStats(data.personal_stats, validRqcScore, isFirstLoad);
                    }
                    
                    // Leaderboard is updated separately via period buttons (This Week, This Month, Last Year)
                    // Do not update leaderboard here to keep it independent from stats date range
                    
                    // Update team availability
                    if (data.team_availability && Array.isArray(data.team_availability)) {
                            updateTeamAvailability({ team_availability: data.team_availability });
                    }
                    
                    // Update recent tasks
                    if (data.recent_tasks && Array.isArray(data.recent_tasks)) {
                        updateRecentTasks(data.recent_tasks);
                    }
                    
                        // Show all sections with fade-in animation
                        requestAnimationFrame(() => {
                            const sections = document.querySelectorAll('.stats-section, .chart-section, .leaderboard-section, .motivation-section');
                            sections.forEach((section, index) => {
                                setTimeout(() => {
                                    section.classList.add('loaded');
                                }, index * 30);
                            });
                            
                            // Hide loading overlay after all sections are visible
                            setTimeout(() => {
                                hideLoadingOverlay();
                                isFirstLoad = false;
                            }, Math.max(300, sections.length * 30 + 100));
                        });
                    });
                }

                // Update system stats
                function updateSystemStats(stats, instant = false) {
                    if (instant) {
                        // Instant update on first load (no animation)
                        const totalEl = document.getElementById('systemTotalTasks');
                        const completedEl = document.getElementById('systemCompletedTasks');
                        const pendingEl = document.getElementById('systemPendingTasks');
                        const delayedEl = document.getElementById('systemDelayedTasks');
                        
                        if (totalEl) {
                            totalEl.textContent = stats.total_tasks_all || stats.total_tasks || 0;
                            totalEl.setAttribute('data-target', stats.total_tasks_all || stats.total_tasks || 0);
                        }
                        if (completedEl) {
                            completedEl.textContent = stats.completed_tasks || 0;
                            completedEl.setAttribute('data-target', stats.completed_tasks || 0);
                        }
                        if (pendingEl) {
                            pendingEl.textContent = stats.pending_tasks || 0;
                            pendingEl.setAttribute('data-target', stats.pending_tasks || 0);
                        }
                        if (delayedEl) {
                            delayedEl.textContent = stats.delayed_tasks || 0;
                            delayedEl.setAttribute('data-target', stats.delayed_tasks || 0);
                        }
                    } else {
                        // Animated update on refresh
                        animateCounter('#systemTotalTasks', stats.total_tasks_all || stats.total_tasks || 0);
                        animateCounter('#systemCompletedTasks', stats.completed_tasks || 0);
                        animateCounter('#systemPendingTasks', stats.pending_tasks || 0);
                        animateCounter('#systemDelayedTasks', stats.delayed_tasks || 0);
                    }
                }

                // Special function to update personal RQC score (handles N/A)
                function updatePersonalRqcScore(element, rqcScore) {
                    if (!element) return;
                    
                    // Debug logging
                    
                    // Handle null, undefined, empty string
                    if (rqcScore === null || rqcScore === undefined || rqcScore === '') {
                        element.setAttribute('data-is-na', 'true');
                        element.textContent = 'N/A';
                        return;
                    }
                    
                    const numScore = parseFloat(rqcScore);
                    if (!isNaN(numScore) && numScore > 0 && isFinite(numScore)) {
                        // Valid RQC score - update value (rounded, no decimals, with %)
                        element.setAttribute('data-is-na', 'false');
                        element.textContent = Math.round(numScore) + '%';
                    } else {
                        // No RQC score - show N/A
                        element.setAttribute('data-is-na', 'true');
                        element.textContent = 'N/A';
                    }
                }

                // Function to apply glow class based on WND value
                function applyWndGlow(statType, value) {
                    const card = document.querySelector(`.stat-card[data-stat="${statType}"]`);
                    if (!card) return;
                    
                    // Remove existing glow classes
                    card.classList.remove('orange-glow', 'red-glow');
                    
                    // Parse value to number
                    const numValue = parseFloat(value);
                    if (isNaN(numValue)) return;
                    
                    // Apply glow based on value
                    // If value > -10%: No glow (default GREY) - good/acceptable values
                    // If value is between -20.5% and -10.6% (inclusive): ORANGE glow - moderately bad
                    // If value ≤ -20.6%: RED glow - very bad (takes priority over ORANGE)
                    if (numValue <= -20.6) {
                        card.classList.add('red-glow');
                    } else if (numValue <= -10.6 && numValue >= -20.5) {
                        card.classList.add('orange-glow');
                    }
                    // Otherwise (value > -10%), no glow class (default GREY)
                }

                // Update personal stats
                function updatePersonalStats(stats, completionRate, instant = false) {
                    const personalStatsGrid = document.getElementById('personalStatsGrid');
                    if (!personalStatsGrid) return;
                    
                    const statCards = personalStatsGrid.querySelectorAll('.stat-card');
                    if (statCards.length < 4) return;
                    
                    // Completed Tasks
                    const completedValue = statCards[0].querySelector('.stat-value');
                    // Pending Tasks
                    const pendingValue = statCards[1].querySelector('.stat-value');
                    // WND
                    const wndValue = statCards[2].querySelector('.stat-value');
                    // WND on Time
                    const wndOnTimeValue = statCards[3].querySelector('.stat-value');
                    
                    if (instant) {
                        // Instant update on first load
                        if (completedValue) completedValue.textContent = stats.completed_on_time || 0;
                        if (pendingValue) pendingValue.textContent = stats.current_pending || 0;
                        if (wndValue) {
                            wndValue.textContent = Math.round(stats.wnd || 0) + '%';
                            applyWndGlow('wnd', stats.wnd || 0);
                        }
                        if (wndOnTimeValue) {
                            wndOnTimeValue.textContent = Math.round(stats.wnd_on_time || 0) + '%';
                            applyWndGlow('wnd_on_time', stats.wnd_on_time || 0);
                        }
                    } else {
                        // Animated update on refresh - use direct element animation
                        if (completedValue) animateValueElement(completedValue, stats.completed_on_time || 0);
                        if (pendingValue) animateValueElement(pendingValue, stats.current_pending || 0);
                        if (wndValue) {
                            animateValueElement(wndValue, stats.wnd || 0, true);
                            applyWndGlow('wnd', stats.wnd || 0);
                        }
                        if (wndOnTimeValue) {
                            animateValueElement(wndOnTimeValue, stats.wnd_on_time || 0, true);
                            applyWndGlow('wnd_on_time', stats.wnd_on_time || 0);
                        }
                    }
                }

                // Helper function to animate value element directly
                // Store active element timers separately
                const activeElementTimers = new Map();
                
                function animateValueElement(element, targetValue, isPercentage = false) {
                    if (!element) return;
                    
                    // Create a unique key for this element
                    const elementKey = element.id || element.className || Math.random().toString();
                    
                    // Clear any existing animation for this element
                    if (activeElementTimers.has(elementKey)) {
                        clearInterval(activeElementTimers.get(elementKey));
                        activeElementTimers.delete(elementKey);
                    }
                    
                    // Skip animation if element is marked as N/A
                    if (element.getAttribute('data-is-na') === 'true') {
                        return;
                    }
                    
                    // Parse current value (remove % sign if present, handle N/A)
                    const currentText = element.textContent.trim();
                    if (currentText === 'N/A') {
                        const targetNum = parseFloat(targetValue);
                        if (!isNaN(targetNum) && targetNum > 0 && isFinite(targetNum)) {
                            element.setAttribute('data-is-na', 'false');
                        } else {
                            return;
                        }
                    }
                    
                    // Read from data-target attribute if available, otherwise parse from text
                    let currentValue = 0;
                    const dataTarget = element.getAttribute('data-target');
                    if (dataTarget && dataTarget !== 'N/A' && !isNaN(parseFloat(dataTarget))) {
                        currentValue = parseFloat(dataTarget);
                    } else {
                        currentValue = parseFloat(currentText.replace('%', '').replace('N/A', '0')) || 0;
                    }
                    
                    if (isNaN(currentValue)) {
                        currentValue = 0;
                    }
                    
                    // Ensure targetValue is a valid number
                    if (targetValue === null || targetValue === undefined) {
                        if (isPercentage) {
                            element.setAttribute('data-is-na', 'true');
                            element.textContent = 'N/A';
                        }
                        return;
                    }
                    
                    const targetNum = parseFloat(targetValue);
                    if (isNaN(targetNum) || !isFinite(targetNum)) {
                        if (isPercentage) {
                            element.setAttribute('data-is-na', 'true');
                            element.textContent = 'N/A';
                        }
                        return;
                    }
                    
                    targetValue = targetNum;
                    
                    // Update data-target attribute
                    element.setAttribute('data-target', targetValue);
                    
                    // For percentages, allow negative values (WND and WND on-time can be negative)
                    if (!isPercentage && targetValue < 0) {
                        return;
                    }
                    
                    // If current value equals target, just set it directly
                    if (Math.abs(currentValue - targetValue) < 0.01) {
                        if (isPercentage) {
                            element.textContent = Math.round(targetValue) + '%';
                        } else {
                            element.textContent = Math.round(targetValue);
                        }
                        // Apply glow for WND and WND_On_Time even when no animation is needed
                        const card = element.closest('.stat-card');
                        if (card) {
                            const statType = card.getAttribute('data-stat');
                            if (statType === 'wnd' || statType === 'wnd_on_time') {
                                applyWndGlow(statType, targetValue);
                            }
                        }
                        return;
                    }
                    
                    const increment = (targetValue - currentValue) / 30;
                    let current = currentValue;
                    
                    const timer = setInterval(() => {
                        current += increment;
                        if ((increment > 0 && current >= targetValue) || (increment < 0 && current <= targetValue)) {
                            current = targetValue;
                            clearInterval(timer);
                            activeElementTimers.delete(elementKey);
                            
                            // Apply glow for WND and WND_On_Time when animation completes
                            const card = element.closest('.stat-card');
                            if (card) {
                                const statType = card.getAttribute('data-stat');
                                if (statType === 'wnd' || statType === 'wnd_on_time') {
                                    applyWndGlow(statType, targetValue);
                                }
                            }
                        }
                        
                        if (isPercentage) {
                            // Format as percentage with rounded value (no decimals)
                            element.textContent = Math.round(current) + '%';
                        } else {
                            // Format as integer count
                            element.textContent = Math.round(current);
                        }
                    }, 50);
                    
                    // Store the timer
                    activeElementTimers.set(elementKey, timer);
                }
                
                // Apply initial glow on page load
                document.addEventListener('DOMContentLoaded', function() {
                    // Get initial WND values from data-target attributes
                    const wndElement = document.querySelector('.stat-card[data-stat="wnd"] .stat-value');
                    const wndOnTimeElement = document.querySelector('.stat-card[data-stat="wnd_on_time"] .stat-value');
                    
                    if (wndElement) {
                        const wndValue = parseFloat(wndElement.getAttribute('data-target') || wndElement.textContent.replace('%', ''));
                        if (!isNaN(wndValue)) {
                            applyWndGlow('wnd', wndValue);
                        }
                    }
                    
                    if (wndOnTimeElement) {
                        const wndOnTimeValue = parseFloat(wndOnTimeElement.getAttribute('data-target') || wndOnTimeElement.textContent.replace('%', ''));
                        if (!isNaN(wndOnTimeValue)) {
                            applyWndGlow('wnd_on_time', wndOnTimeValue);
                        }
                    }
                });

                // Load leaderboard data based on selected time period
                async function loadLeaderboardData() {
                    try {
                        let dateFrom = null;
                        let dateTo = null;
                        
                        // Use calculateWeekDateRange to get the correct date range for the selected period
                        if (currentLeaderboardPeriod === 'last_week' || 
                            currentLeaderboardPeriod === 'last_2_weeks' || 
                            currentLeaderboardPeriod === 'last_4_weeks') {
                            const dateRange = calculateWeekDateRange(currentLeaderboardPeriod);
                            dateFrom = dateRange.fromDate;
                            dateTo = dateRange.toDate;
                        } else {
                            // Fallback: if period is not recognized, default to last week
                            const dateRange = calculateWeekDateRange('last_week');
                            dateFrom = dateRange.fromDate;
                            dateTo = dateRange.toDate;
                        }
                        
                        let url = '../ajax/admin_dashboard_data.php';
                        const params = new URLSearchParams();
                        if (dateFrom) {
                            params.append('date_from', dateFrom);
                        }
                        if (dateTo) {
                            params.append('date_to', dateTo);
                        }
                        params.append('leaderboard_limit', '0'); // 0 means no limit - show all users
                        
                        if (params.toString()) {
                            url += '?' + params.toString();
                        }
                        
                        const response = await fetch(url);
                        const result = await response.json();
                        
                        if (result.success && result.data.leaderboard) {
                            leaderboardData = result.data.leaderboard || [];
                            if (leaderboardData.length > 0) {
                            }
                            currentLeaderboardPage = 1; // Reset to first page when data changes
                            initializeLeaderboard();
                        } else {
                            // Show empty state
                            const list = document.getElementById('topPerformersList');
                            if (list) {
                                list.innerHTML = '<div class="leaderboard-empty">No leaderboard data available</div>';
                            }
                        }
                    } catch (error) {
                        // Show error state
                        const list = document.getElementById('topPerformersList');
                        if (list) {
                            list.innerHTML = '<div class="leaderboard-empty">Error loading leaderboard data</div>';
                        }
                    }
                }
                
                // Update leaderboard (same as manager dashboard)
                function updateLeaderboard(leaderboard) {
                    // Store leaderboard data globally
                    leaderboardData = leaderboard || [];
                    
                    // Reset to first page when data changes
                    currentLeaderboardPage = 1;
                    
                    // Re-initialize leaderboard with current state (includes pagination)
                    initializeLeaderboard();
                }

                // Helper function to get user initials
                function getUserInitials(name) {
                    if (!name) return '?';
                    const parts = name.trim().split(' ');
                    if (parts.length >= 2) {
                        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
                    }
                    return name.substring(0, 2).toUpperCase();
                }
                
                // Helper function to get gradient class for rank
                function getRankGradientClass(rank) {
                    if (rank === 1) return 'rank-gold';
                    if (rank === 2) return 'rank-silver';
                    if (rank === 3) return 'rank-bronze';
                    return '';
                }
                
                // Initialize leaderboard
                function initializeLeaderboard() {
                    const list = document.getElementById('topPerformersList');
                    if (!list) return;
                    
                    list.innerHTML = '';
                    
                    if (leaderboardData.length === 0) {
                        list.innerHTML = '<div class="leaderboard-empty">No leaderboard data available</div>';
                        renderLeaderboardPagination(); // Still render pagination (will show empty)
                        return;
                    }
                    
                    // Calculate pagination
                    const totalPages = Math.ceil(leaderboardData.length / leaderboardItemsPerPage);
                    const startIndex = (currentLeaderboardPage - 1) * leaderboardItemsPerPage;
                    const endIndex = startIndex + leaderboardItemsPerPage;
                    
                    // Get data for current page (3 items per page)
                    const displayData = leaderboardData.slice(startIndex, endIndex);
                    
                    if (displayData.length === 0) {
                        list.innerHTML = '<div class="leaderboard-empty">No leaderboard data available</div>';
                        renderLeaderboardPagination();
                        return;
                    }
                    
                    displayData.forEach((user, index) => {
                        const item = document.createElement('div');
                        const rankClass = getRankGradientClass(user.rank);
                        item.className = `leaderboard-item ${user.is_current_user ? 'current-user' : ''} ${rankClass}`;
                        item.style.animationDelay = `${index * 0.1}s`;
                        
                        // Get avatar emoji based on rank
                        let avatar = '-';
                        if (user.rank === 1) avatar = '🥇';
                        else if (user.rank === 2) avatar = '🥈';
                        else if (user.rank === 3) avatar = '🥉';
                        
                        // Get user initials for avatar fallback
                        const initials = getUserInitials(user.name);
                        
                        // Get Performance Rate (primary metric) or fallback to completion_rate for backward compatibility
                        const performanceRate = parseFloat(user.performance_rate) ?? parseFloat(user.completion_rate) ?? 0;
                        // Handle rqc_score: check if it exists and is not null/undefined, then parse
                        const rqcScoreRaw = parseFloat(user.rqc_score);
                        const rqcScore = (rqcScoreRaw && rqcScoreRaw > 0) ? rqcScoreRaw : null; // Show N/A if 0 or null
                        const wnd = parseFloat(user.wnd) || 0;
                        const wndOnTime = parseFloat(user.wnd_on_time) || 0;
                        
                        // Create tooltip content
                        const tooltipContent = `
                            <div class="leaderboard-tooltip-content">
                                <strong>${user.name}</strong><br>
                                <span>Rank: #${user.rank}</span><br>
                                <span>Performance Rate: ${performanceRate.toFixed(1)}%</span><br>
                                <span>RQC Score: ${rqcScore !== null ? rqcScore.toFixed(1) : 'N/A'}</span><br>
                                <span>WND: ${wnd.toFixed(1)}%</span><br>
                                <span>WND On-Time: ${wndOnTime.toFixed(1)}%</span><br>
                                <span>Tasks: ${user.completed_tasks || 0}/${user.total_tasks || 0}</span><br>
                                <span>User Type: ${user.user_type || 'N/A'}</span>
                            </div>
                        `;
                        
                        // Make entire item clickable for admins
                        if (user.username) {
                            item.style.cursor = 'pointer';
                            item.setAttribute('data-tooltip', 'true');
                            item.setAttribute('data-user-name', user.name);
                            item.setAttribute('data-user-rank', user.rank);
                            item.setAttribute('data-performance-rate', performanceRate);
                            item.setAttribute('data-rqc-score', rqcScore);
                            item.setAttribute('data-completed-tasks', user.completed_tasks || 0);
                            item.setAttribute('data-total-tasks', user.total_tasks || 0);
                            item.setAttribute('data-user-type', user.user_type || 'N/A');
                            
                            // Create tooltip element
                            const tooltip = document.createElement('div');
                            tooltip.className = 'leaderboard-tooltip';
                            tooltip.innerHTML = tooltipContent;
                            item.appendChild(tooltip);
                            
                            item.addEventListener('click', function(e) {
                                // Add click animation
                                item.classList.add('clicked');
                                setTimeout(() => {
                                    item.classList.remove('clicked');
                                    viewPerformanceForUser(user.username);
                                }, 200);
                            });
                        }
                        
                        // Progress ring calculation (radius = 33 for 75px wrapper)
                        // Use Performance Rate for the ring visualization
                        const ringRadius = 33;
                        const circumference = 2 * Math.PI * ringRadius;
                        const offset = circumference - (performanceRate / 100) * circumference;
                        
                        item.innerHTML = `
                            <div class="rank-badge ${rankClass}">
                                <span class="rank-number">${user.rank}</span>
                                <span class="rank-emoji">${avatar}</span>
                            </div>
                            <div class="user-info">
                                <div class="user-avatar-wrapper">
                                    <div class="user-avatar">
                                        <div class="avatar-initials" style="display: flex;">${initials}</div>
                                    </div>
                                </div>
                                <div class="user-details">
                                    <div class="user-name">${user.name}</div>
                                    <div class="user-scores">
                                        <span class="user-score performance-rate">
                                            <i class="fas fa-chart-line"></i> ${performanceRate.toFixed(1)}% Performance
                                        </span>

                                    </div>
                                    <div class="user-tasks">RQC Score: ${rqcScore !== null ? rqcScore.toFixed(1) : 'N/A'}</div>
                                </div>
                            </div>
                            <div class="performance-ring-wrapper">
                                <svg class="performance-ring" width="75" height="75">
                                    <circle class="ring-background" cx="37.5" cy="37.5" r="33" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="6"/>
                                    <circle class="ring-progress" cx="37.5" cy="37.5" r="33" fill="none" 
                                            stroke="url(#ringGradient${user.rank})" 
                                            stroke-width="6" 
                                            stroke-linecap="round"
                                            stroke-dasharray="${circumference}"
                                            stroke-dashoffset="${offset}"
                                            transform="rotate(-90 37.5 37.5)"/>
                                    <defs>
                                        <linearGradient id="ringGradient${user.rank}" x1="0%" y1="0%" x2="100%" y2="100%">
                                            ${user.rank === 1 ? `
                                            <stop offset="0%" style="stop-color:#FFD700;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#FFA500;stop-opacity:1" />
                                            ` : user.rank === 2 ? `
                                            <stop offset="0%" style="stop-color:#C0C0C0;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#808080;stop-opacity:1" />
                                            ` : user.rank === 3 ? `
                                            <stop offset="0%" style="stop-color:#CD7F32;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#8B4513;stop-opacity:1" />
                                            ` : `
                                            <stop offset="0%" style="stop-color:#6366f1;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#8b5cf6;stop-opacity:1" />
                                            `}
                                        </linearGradient>
                                    </defs>
                                    <text class="ring-text" x="37.5" y="42" text-anchor="middle" font-size="11" font-weight="600" fill="#fff">${performanceRate.toFixed(1)}%</text>
                                </svg>
                            </div>
                        `;
                        
                        list.appendChild(item);
                        
                        // Animate progress ring after a short delay
                        setTimeout(() => {
                            const ring = item.querySelector('.ring-progress');
                            if (ring) {
                                ring.style.transition = 'stroke-dashoffset 1.5s ease-out';
                            }
                        }, index * 100);
                    });
                    
                    // Render pagination controls
                    renderLeaderboardPagination();
                }
                
                // View Performance functions
                function viewPerformance() {
                    // Directly open performance page - dropdown will be shown there
                    window.location.href = 'team_performance.php';
                }
                
                function viewPerformanceForUser(username) {
                    if (username) {
                        window.location.href = `team_performance.php?username=${encodeURIComponent(username)}`;
                    }
                }
                
                // Render pagination controls for leaderboard
                function renderLeaderboardPagination() {
                    const paginationContainer = document.getElementById('leaderboardPagination');
                    if (!paginationContainer) return;
                    
                    if (leaderboardData.length === 0) {
                        paginationContainer.innerHTML = '';
                        return;
                    }
                    
                    const totalPages = Math.ceil(leaderboardData.length / leaderboardItemsPerPage);
                    
                    if (totalPages <= 1) {
                        paginationContainer.innerHTML = '';
                        return;
                    }
                    
                    let paginationHTML = '<div class="pagination-controls">';
                    
                    // Previous button
                    paginationHTML += `<button class="pagination-btn" ${currentLeaderboardPage === 1 ? 'disabled' : ''} onclick="goToLeaderboardPage(${currentLeaderboardPage - 1})">
                        <i class="fas fa-chevron-left"></i>
                    </button>`;
                    
                    // Page numbers
                    const maxVisiblePages = 5;
                    let startPage = Math.max(1, currentLeaderboardPage - Math.floor(maxVisiblePages / 2));
                    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
                    
                    // Adjust start page if we're near the end
                    if (endPage - startPage < maxVisiblePages - 1) {
                        startPage = Math.max(1, endPage - maxVisiblePages + 1);
                    }
                    
                    // First page and ellipsis
                    if (startPage > 1) {
                        paginationHTML += `<button class="pagination-btn page-number ${currentLeaderboardPage === 1 ? 'active' : ''}" onclick="goToLeaderboardPage(1)">1</button>`;
                        if (startPage > 2) {
                            paginationHTML += '<span class="pagination-ellipsis">...</span>';
                        }
                    }
                    
                    // Page numbers
                    for (let i = startPage; i <= endPage; i++) {
                        paginationHTML += `<button class="pagination-btn page-number ${currentLeaderboardPage === i ? 'active' : ''}" onclick="goToLeaderboardPage(${i})">${i}</button>`;
                    }
                    
                    // Last page and ellipsis
                    if (endPage < totalPages) {
                        if (endPage < totalPages - 1) {
                            paginationHTML += '<span class="pagination-ellipsis">...</span>';
                        }
                        paginationHTML += `<button class="pagination-btn page-number ${currentLeaderboardPage === totalPages ? 'active' : ''}" onclick="goToLeaderboardPage(${totalPages})">${totalPages}</button>`;
                    }
                    
                    // Next button
                    paginationHTML += `<button class="pagination-btn" ${currentLeaderboardPage === totalPages ? 'disabled' : ''} onclick="goToLeaderboardPage(${currentLeaderboardPage + 1})">
                        <i class="fas fa-chevron-right"></i>
                    </button>`;
                    
                    paginationHTML += '</div>';
                    
                    // Page info
                    const startRank = (currentLeaderboardPage - 1) * leaderboardItemsPerPage + 1;
                    const endRank = Math.min(currentLeaderboardPage * leaderboardItemsPerPage, leaderboardData.length);
                    paginationHTML += `<div style="color: var(--dark-text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">
                        Showing ranks ${startRank}-${endRank} of ${leaderboardData.length}
                    </div>`;
                    
                    paginationContainer.innerHTML = paginationHTML;
                }
                
                // Navigate to specific leaderboard page
                function goToLeaderboardPage(page) {
                    const totalPages = Math.ceil(leaderboardData.length / leaderboardItemsPerPage);
                    if (page < 1 || page > totalPages) return;
                    
                    currentLeaderboardPage = page;
                    initializeLeaderboard();
                    
                    // Scroll to top of leaderboard section
                    const leaderboardSection = document.getElementById('topPerformersList');
                    if (leaderboardSection) {
                        leaderboardSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
                
                // Make goToLeaderboardPage available globally
                window.goToLeaderboardPage = goToLeaderboardPage;

                // Helper function to get display status based on leave_status and duration
                function getDisplayStatus(member) {
                    const leaveStatus = (member.leave_status || '').toLowerCase();
                    const duration = (member.duration || '').toLowerCase();
                    const leaveType = (member.leave_type || '').toLowerCase();
                    const hasLeaveRequest = member.leave_status || member.duration || member.leave_type;
                    
                    if (!hasLeaveRequest) {
                        return 'available'; // No leave request = available
                    }
                    
                    if (leaveStatus === 'pending') {
                        return 'available'; // Pending leave = show as available (green)
                    }
                    
                    if (leaveStatus === 'approve' || leaveStatus === 'approved') {
                        // Check if it's WFH (Full Day WFH or Half Day WFH)
                        if (duration.includes('wfh') || leaveType.includes('wfh') || 
                            duration.includes('work from home') || leaveType.includes('work from home')) {
                            return 'remote'; // Blue for WFH
                        }
                        // Check if it's Leave (Full Day Leave, Half Day Leave, or Short Leave)
                        else if (duration.includes('leave') || leaveType.includes('leave') || 
                                 duration.includes('short leave') || leaveType.includes('short leave')) {
                            return 'on-leave'; // Red for Leave
                        }
                    }
                    
                    return 'available'; // Default fallback
                }

                // Function to update team availability with new data
                function updateTeamAvailability(data) {
                    if (data && Array.isArray(data.team_availability)) {
                        const teamMembers = data.team_availability;
                        
                        // Sort team members: on-leave (red) first, then remote (blue/WFH), then available (green), then by name
                        const sortedTeam = teamMembers.sort((a, b) => {
                            const statusA = getDisplayStatus(a);
                            const statusB = getDisplayStatus(b);
                            
                            // Priority order: on-leave (0) > remote (1) > available (2)
                            const priority = { 'on-leave': 0, 'remote': 1, 'available': 2 };
                            const priorityA = priority[statusA] ?? 3;
                            const priorityB = priority[statusB] ?? 3;
                            
                            if (priorityA !== priorityB) {
                                return priorityA - priorityB;
                            }
                            
                            // If same priority, sort by name
                            return a.name.localeCompare(b.name);
                        });
                        
                        // Populate team grid
                        populateTeamGrid(sortedTeam);
                        
                        // Update availability stats
                        updateAvailabilityStats(sortedTeam);
                    }
                }

                function populateTeamGrid(teamMembers) {
                    const teamGrid = document.getElementById('teamGrid');
                    if (!teamGrid) return;
                    
                    teamGrid.innerHTML = '';
                    
                    teamMembers.forEach((member, index) => {
                        const memberElement = document.createElement('div');
                        
                        // Use helper function to determine status class
                        const statusClass = getDisplayStatus(member);
                        
                        memberElement.className = `team-member ${statusClass}`;
                        memberElement.style.animationDelay = `${index * 0.1}s`;
                        
                        // Make clickable if on leave or WFH (to show leave details)
                        if (statusClass === 'on-leave' || statusClass === 'remote') {
                            memberElement.style.cursor = 'pointer';
                            // Store member data for modal
                            memberElement.dataset.memberId = member.id;
                            memberElement.dataset.memberName = member.name;
                            memberElement.dataset.leaveType = member.leave_type || '';
                            memberElement.dataset.duration = member.duration || '';
                            memberElement.dataset.startDate = member.start_date || '';
                            memberElement.dataset.endDate = member.end_date || '';
                            memberElement.dataset.leaveStatus = member.leave_status || '';
                            
                            // Add click event to show leave details
                            memberElement.addEventListener('click', function() {
                                showLeaveDetails(member);
                            });
                        } else {
                            memberElement.style.cursor = 'default';
                        }
                        
                        // Get first letter of name for avatar
                        const firstLetter = member.name.split(' ')[0].charAt(0);
                        
                        memberElement.innerHTML = `
                            <div class="member-avatar">
                                <span style="display: flex;">${firstLetter}</span>
                            </div>
                            <div class="member-name">${member.name}</div>
                            <div class="member-status ${statusClass}"></div>
                        `;
                        
                        teamGrid.appendChild(memberElement);
                    });
                }

                function updateAvailabilityStats(teamMembers) {
                    // Count based on display status (using helper function)
                    let availableCount = 0;
                    let onWfhCount = 0;
                    let onLeaveCount = 0;
                    
                    teamMembers.forEach(member => {
                        const displayStatus = getDisplayStatus(member);
                        if (displayStatus === 'available') {
                            availableCount++;
                        } else if (displayStatus === 'remote') {
                            onWfhCount++;
                        } else if (displayStatus === 'on-leave') {
                            onLeaveCount++;
                        }
                    });
                    
                    const availableCountElement = document.getElementById('availableCount');
                    const onWfhCountElement = document.getElementById('onWfhCount');
                    const onLeaveCountElement = document.getElementById('onLeaveCount');
                    
                    if (availableCountElement) {
                        availableCountElement.textContent = availableCount;
                    }
                    
                    if (onWfhCountElement) {
                        onWfhCountElement.textContent = onWfhCount;
                    }
                    
                    if (onLeaveCountElement) {
                        onLeaveCountElement.textContent = onLeaveCount;
                    }
                }

                // Update recent tasks
                function updateRecentTasks(tasks) {
                    const tbody = document.getElementById('recentTasksTable');
                    tbody.innerHTML = '';
                    
                    if (tasks.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No recent tasks</td></tr>';
                        return;
                    }
                    
                    tasks.forEach(task => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${task.unique_id || task.id}</td>
                            <td>${task.description ? (task.description.length > 50 ? task.description.substring(0, 50) + '...' : task.description) : 'N/A'}</td>
                            <td>${task.doer_name || 'N/A'}</td>
                            <td>${task.department_name || 'N/A'}</td>
                            <td>${task.planned_date || 'N/A'}</td>
                            <td>${getStatusBadge(task.status || 'pending')}</td>
                        `;
                        tbody.appendChild(row);
                    });
                }

                // Helper functions
                // Store active timers to clear them when needed
                const activeTimers = new Map();

                function animateCounter(selector, targetValue, isPercentage = false) {
                    const element = document.querySelector(selector);
                    if (!element) return;
                    
                    // Clear any existing animation for this element
                    if (activeTimers.has(selector)) {
                        clearInterval(activeTimers.get(selector));
                        activeTimers.delete(selector);
                    }
                    
                    // Skip animation if element is marked as N/A
                    if (element.getAttribute('data-is-na') === 'true') {
                        return;
                    }
                    
                    // Skip if current value is "N/A"
                    const currentText = element.textContent.trim();
                    if (currentText === 'N/A') {
                        // If we have a valid target value, update it
                        const targetNum = parseFloat(targetValue);
                        if (!isNaN(targetNum) && targetNum > 0 && isFinite(targetNum)) {
                            element.setAttribute('data-is-na', 'false');
                        } else {
                            return; // Keep N/A if no valid target
                        }
                    }
                    
                    // Parse current value (remove % sign if present, handle N/A)
                    // Read from data-target attribute if available, otherwise parse from text
                    let currentValue = 0;
                    const dataTarget = element.getAttribute('data-target');
                    if (dataTarget && dataTarget !== 'N/A' && !isNaN(parseFloat(dataTarget))) {
                        currentValue = parseFloat(dataTarget);
                    } else {
                        currentValue = parseFloat(currentText.replace('%', '').replace('N/A', '0')) || 0;
                    }
                    
                    if (isNaN(currentValue)) {
                        currentValue = 0;
                    }
                    
                    // Ensure targetValue is a valid number
                    if (targetValue === null || targetValue === undefined) {
                        if (isPercentage) {
                            element.setAttribute('data-is-na', 'true');
                            element.textContent = 'N/A';
                        }
                        return;
                    }
                    
                    // Convert to number if it's a string
                    const targetNum = parseFloat(targetValue);
                    if (isNaN(targetNum) || !isFinite(targetNum)) {
                        if (isPercentage) {
                            element.setAttribute('data-is-na', 'true');
                            element.textContent = 'N/A';
                        }
                        return;
                    }
                    
                    // Use the parsed number
                    targetValue = targetNum;
                    
                    // Update data-target attribute with new target value
                    element.setAttribute('data-target', targetValue);
                    
                    // For percentages, allow negative values (WND and WND on-time can be negative)
                    if (!isPercentage) {
                        // For non-percentages (counts), values must be >= 0
                        if (targetValue < 0) {
                            return;
                        }
                    }
                    
                    // If current value equals target, just set it directly (no animation needed)
                    if (Math.abs(currentValue - targetValue) < 0.01) {
                        if (isPercentage) {
                            element.textContent = Math.round(targetValue) + '%';
                        } else {
                            element.textContent = Math.round(targetValue);
                        }
                        return;
                    }
                    
                    const increment = (targetValue - currentValue) / 30;
                    let current = currentValue;
                    
                    const timer = setInterval(() => {
                        current += increment;
                        if ((increment > 0 && current >= targetValue) || (increment < 0 && current <= targetValue)) {
                            current = targetValue;
                            clearInterval(timer);
                            activeTimers.delete(selector);
                        }
                        
                        if (isPercentage) {
                            // Format as percentage with rounded value (no decimals)
                            element.textContent = Math.round(current) + '%';
                        } else {
                            // Format as integer count
                            element.textContent = Math.round(current);
                        }
                    }, 50);
                    
                    // Store the timer so we can clear it later
                    activeTimers.set(selector, timer);
                }

                function getStatusBadge(status) {
                    const statusLower = status.toLowerCase();
                    let badgeClass = 'badge-secondary';
                    let displayText = status;
                    let customStyle = '';
                    
                    if (statusLower === 'completed' || statusLower === 'done') {
                        // Green gradient matching personal stats
                        customStyle = 'background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border: none;';
                        displayText = 'Completed';
                    } else if (statusLower === 'shifted') {
                        badgeClass = 'badge-info';
                        displayText = 'Shifted';
                    } else if (statusLower === 'pending') {
                        // Yellow/Golden Amber gradient matching personal stats
                        customStyle = 'background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); color: white; border: none;';
                        displayText = 'Pending';
                    } else if (statusLower === 'delayed') {
                        badgeClass = 'badge-danger';
                        displayText = 'Delayed';
                    } else if (statusLower === 'not done' || statusLower === 'not_done') {
                        // Maroon Red gradient
                        customStyle = 'background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%); color: white; border: none;';
                        displayText = 'Not Done';
                    } else if (statusLower === 'can not be done' || statusLower === 'cant_be_done' || statusLower === 'cannot be done') {
                        // Charcoal grey gradient
                        customStyle = 'background: linear-gradient(135deg, #37474F 0%, #455A64 100%); color: white; border: none;';
                        displayText = 'Can\'t be done';
                    }
                    
                    if (customStyle) {
                        return `<span class="badge" style="${customStyle}">${displayText}</span>`;
                    }
                    return `<span class="badge ${badgeClass}">${displayText}</span>`;
                }

                // Update system overview title based on date range
                function updateSystemOverviewTitle(range, fromDate = null, toDate = null) {
                    const titleElement = document.getElementById('systemOverviewTitle');
                    if (!titleElement) return;
                    
                    let title = '';
                    switch(range) {
                        case 'this_week':
                            title = 'This Week Overview';
                            break;
                        case 'last_week':
                            title = 'Last Week Overview';
                            break;
                        case 'last_2_weeks':
                            title = 'Last 2 Weeks Overview';
                            break;
                        case 'last_4_weeks':
                            title = 'Last 4 Weeks Overview';
                            break;
                        default:
                            title = 'This Week Overview';
                    }
                    
                    titleElement.textContent = title;
                }

                // Update personal overview caption based on date range
                function updatePersonalOverviewCaption(range, fromDate = null, toDate = null) {
                    const captionElement = document.getElementById('personalOverviewCaption');
                    if (!captionElement) return;
                    
                    let caption = '';
                    switch(range) {
                        case 'this_week':
                            caption = 'This Week Overview';
                            break;
                        case 'last_week':
                            caption = 'Last Week Overview';
                            break;
                        case 'last_2_weeks':
                            caption = 'Last 2 Weeks Overview';
                            break;
                        case 'last_4_weeks':
                            caption = 'Last 4 Weeks Overview';
                            break;
                        default:
                            caption = 'This Week Overview';
                    }
                    
                    captionElement.textContent = caption;
                }

                // Date range selector for System Overview
                function initializeDateRangeSelector() {
                    // System Overview date range selector
                    const systemStatsGrid = document.getElementById('systemStatsGrid');
                    if (systemStatsGrid) {
                        const systemStatsSection = systemStatsGrid.closest('.stats-section');
                        if (systemStatsSection) {
                            // Handle main date range buttons (This Week, Last Week)
                            systemStatsSection.querySelectorAll('.date-range-btn[data-range]').forEach(btn => {
                                if (!btn.classList.contains('dropdown-toggle')) {
                                    btn.addEventListener('click', function() {
                                        const range = this.getAttribute('data-range');
                                        
                                        // Remove active class from all buttons
                                        systemStatsSection.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                                            if (!b.classList.contains('dropdown-toggle')) {
                                                b.classList.remove('active');
                                            }
                                        });
                                        
                                        // Add active class to clicked button
                                        this.classList.add('active');
                                        
                                        // Close dropdown if open
                                        const dropdownMenu = document.getElementById('systemDateRangeDropdownMenu');
                                        if (dropdownMenu) {
                                            dropdownMenu.style.display = 'none';
                                        }
                                        
                                        // Update current date range
                                        currentDateRange.type = range;
                                        const dateRange = calculateWeekDateRange(range);
                                        currentDateRange.fromDate = dateRange.fromDate;
                                        currentDateRange.toDate = dateRange.toDate;
                                        
                                        // Update title
                                        updateSystemOverviewTitle(range);
                                        
                                        loadDashboardData();
                                    });
                                }
                            });
                            
                            // Handle dropdown toggle button
                            const dropdownBtn = document.getElementById('systemDateRangeDropdownBtn');
                            const dropdownMenu = document.getElementById('systemDateRangeDropdownMenu');
                            
                            if (dropdownBtn && dropdownMenu) {
                                dropdownBtn.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    const isVisible = dropdownMenu.style.display !== 'none';
                                    dropdownMenu.style.display = isVisible ? 'none' : 'block';
                                });
                                
                                // Close dropdown when clicking outside
                                document.addEventListener('click', function(e) {
                                    if (!dropdownMenu.contains(e.target) && !dropdownBtn.contains(e.target)) {
                                        dropdownMenu.style.display = 'none';
                                    }
                                });
                            }
                            
                            // Handle dropdown menu items
                            if (dropdownMenu) {
                                dropdownMenu.querySelectorAll('.date-range-dropdown-item').forEach(item => {
                                    item.addEventListener('click', function() {
                                        const range = this.getAttribute('data-range');
                                        
                                        // Remove active class from all main buttons
                                        systemStatsSection.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                                            if (!b.classList.contains('dropdown-toggle')) {
                                                b.classList.remove('active');
                                            }
                                        });
                                        
                                        // Update current date range
                                        currentDateRange.type = range;
                                        const dateRange = calculateWeekDateRange(range);
                                        currentDateRange.fromDate = dateRange.fromDate;
                                        currentDateRange.toDate = dateRange.toDate;
                                        
                                        // Update title
                                        updateSystemOverviewTitle(range);
                                        
                                        // Close dropdown
                                        dropdownMenu.style.display = 'none';
                                        
                                        loadDashboardData();
                                    });
                                });
                            }
                        }
                    }
                }

                // Personal Overview date range selector
                function initializePersonalDateRangeSelector() {
                    const personalDateRangeSelector = document.getElementById('personalDateRangeSelector');
                    if (!personalDateRangeSelector) return;
                    
                    // Handle main date range buttons (This Week, Last Week)
                    personalDateRangeSelector.querySelectorAll('.date-range-btn[data-range]').forEach(btn => {
                        if (!btn.classList.contains('dropdown-toggle')) {
                            btn.addEventListener('click', function() {
                                const range = this.getAttribute('data-range');
                                
                                // Remove active class from all buttons
                                personalDateRangeSelector.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                                    if (!b.classList.contains('dropdown-toggle')) {
                                        b.classList.remove('active');
                                    }
                                });
                                
                                // Add active class to clicked button
                                this.classList.add('active');
                                
                                // Close dropdown if open
                                const dropdownMenu = document.getElementById('personalDateRangeDropdownMenu');
                                if (dropdownMenu) {
                                    dropdownMenu.style.display = 'none';
                                }
                                
                                // Update current date range
                                currentPersonalDateRange.type = range;
                                const dateRange = calculateWeekDateRange(range);
                                currentPersonalDateRange.fromDate = dateRange.fromDate;
                                currentPersonalDateRange.toDate = dateRange.toDate;
                                
                                // Update caption
                                updatePersonalOverviewCaption(range);
                                
                                loadDashboardData();
                            });
                        }
                    });
                    
                    // Handle dropdown toggle button
                    const dropdownBtn = document.getElementById('personalDateRangeDropdownBtn');
                    const dropdownMenu = document.getElementById('personalDateRangeDropdownMenu');
                    
                    if (dropdownBtn && dropdownMenu) {
                        dropdownBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            const isVisible = dropdownMenu.style.display !== 'none';
                            dropdownMenu.style.display = isVisible ? 'none' : 'block';
                        });
                        
                        // Close dropdown when clicking outside
                        document.addEventListener('click', function(e) {
                            if (!dropdownMenu.contains(e.target) && !dropdownBtn.contains(e.target)) {
                                dropdownMenu.style.display = 'none';
                            }
                        });
                    }
                    
                    // Handle dropdown menu items
                    if (dropdownMenu) {
                        dropdownMenu.querySelectorAll('.date-range-dropdown-item').forEach(item => {
                            item.addEventListener('click', function() {
                                const range = this.getAttribute('data-range');
                                
                                // Remove active class from all main buttons
                                personalDateRangeSelector.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                                    if (!b.classList.contains('dropdown-toggle')) {
                                        b.classList.remove('active');
                                    }
                                });
                                
                                // Update current date range
                                currentPersonalDateRange.type = range;
                                const dateRange = calculateWeekDateRange(range);
                                currentPersonalDateRange.fromDate = dateRange.fromDate;
                                currentPersonalDateRange.toDate = dateRange.toDate;
                                
                                // Update caption
                                updatePersonalOverviewCaption(range);
                                
                                // Close dropdown
                                dropdownMenu.style.display = 'none';
                                
                                loadDashboardData();
                            });
                        });
                    }
                }

                // Daily quotes (same as manager dashboard)
                const dailyQuotes = [
                    { quote: "The best way to predict the future is to create it.", author: "Peter Drucker" },
                    { quote: "Management is doing things right; leadership is doing the right things.", author: "Peter Drucker" },
                    { quote: "The greatest leader is not necessarily the one who does the greatest things. He is the one that gets the people to do the greatest things.", author: "Ronald Reagan" }
                ];

                function initializeDailyQuotes() {
                    const today = new Date();
                    const dayOfYear = Math.floor((today - new Date(today.getFullYear(), 0, 0)) / (1000 * 60 * 60 * 24));
                    const quoteIndex = dayOfYear % dailyQuotes.length;
                    const selectedQuote = dailyQuotes[quoteIndex];
                    
                    const quoteElement = document.getElementById('dailyQuote');
                    const authorElement = document.getElementById('quoteAuthor');
                    
                    if (quoteElement && authorElement) {
                        quoteElement.textContent = selectedQuote.quote;
                        authorElement.textContent = `— ${selectedQuote.author}`;
                    }
                }

                // Leave details modal (same as manager dashboard)
                function initializeLeaveDetailsModal() {
                    const modal = document.getElementById('leaveDetailsModal');
                    const overlay = document.getElementById('leaveDetailsOverlay');
                    const closeBtn = document.getElementById('leaveDetailsClose');
                    
                    if (overlay) {
                        overlay.addEventListener('click', hideLeaveDetails);
                    }
                    
                    if (closeBtn) {
                        closeBtn.addEventListener('click', hideLeaveDetails);
                    }
                    
                    // Close on Escape key
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && modal && modal.classList.contains('show')) {
                            hideLeaveDetails();
                        }
                    });
                }

                // Function to show leave details modal
                function showLeaveDetails(member) {
                    const modal = document.getElementById('leaveDetailsModal');
                    const memberName = document.getElementById('leaveDetailsMemberName');
                    const leaveType = document.getElementById('leaveDetailsType');
                    const duration = document.getElementById('leaveDetailsDuration');
                    const startDate = document.getElementById('leaveDetailsStartDate');
                    const endDate = document.getElementById('leaveDetailsEndDate');
                    const days = document.getElementById('leaveDetailsDays');
                    const status = document.getElementById('leaveDetailsStatus');
                    
                    if (!modal) return;
                    
                    // Populate modal with member data
                    memberName.textContent = member.name + "'s Leave Details";
                    leaveType.textContent = member.leave_type || '-';
                    duration.textContent = member.duration || '-';
                    startDate.textContent = formatDate(member.start_date);
                    endDate.textContent = member.end_date ? formatDate(member.end_date) : formatDate(member.start_date);
                    
                    // Calculate number of days
                    const numDays = calculateDays(member.start_date, member.end_date);
                    days.textContent = numDays + (numDays === 1 ? ' day' : ' days');
                    
                    // Set status badge
                    const statusText = member.leave_status || 'PENDING';
                    status.textContent = statusText;
                    status.className = 'leave-status-badge ' + (statusText.toLowerCase() === 'approve' ? 'status-approved' : 'status-pending');
                    
                    // Show modal
                    modal.classList.add('show');
                }

                // Function to hide leave details modal
                function hideLeaveDetails() {
                    const modal = document.getElementById('leaveDetailsModal');
                    if (modal) {
                        modal.classList.remove('show');
                    }
                }

                // Function to format date for display
                function formatDate(dateString) {
                    if (!dateString) return '-';
                    const date = new Date(dateString);
                    return date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                }

                // Function to calculate number of days between two dates
                function calculateDays(startDate, endDate) {
                    if (!startDate) return 0;
                    
                    const start = new Date(startDate);
                    const end = endDate ? new Date(endDate) : new Date(startDate);
                    
                    // Calculate difference in milliseconds
                    const diffTime = Math.abs(end - start);
                    // Convert to days (add 1 to include both start and end date)
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    
                    return diffDays;
                }
                </script>
                
                                                </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- Font Awesome for the bell icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Notification Sound -->
    <audio id="notification-sound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT" type="audio/wav">
    </audio>
    
    <!-- Notification System JavaScript -->
    <script>
    // Optimized admin dashboard functionality
    if (!window.adminDashboardInitialized) {
        window.adminDashboardInitialized = true;
        
        $(document).ready(function() {
            // Initialize notification system
            updatePendingCount();
        });
    }
    
    function updatePendingCount() {
        const pendingRows = $('tbody tr').filter(function() {
            return $(this).find('.badge-warning').length > 0;
        }).length;
        
        if (pendingRows > 0) {
            $('#pending-count').text(pendingRows + ' Pending');
        } else {
            $('#pending-count').text('0 Pending');
        }
    }
    
    function refreshNotifications() {
        // Reload the page to refresh notifications
        location.reload();
    }
    
    function approveRequest(requestId) {
        if (confirm('Are you sure you want to approve this password reset request?')) {
            const button = event.target.closest('.btn');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;
            
            $.ajax({
                url: '../ajax/approve_password_reset.php',
                type: 'POST',
                data: { request_id: requestId },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        showToast('Request approved successfully!', 'success');
                        
                        // Find and update the table row
                        const row = button.closest('tr');
                        if (row) {
                            // Update status column
                            const statusCell = row.querySelector('td:nth-child(3)');
                            statusCell.innerHTML = '<span class="badge badge-success">Approved</span>';
                            
                            // Update reset code column
                            const resetCodeCell = row.querySelector('td:nth-child(5)');
                            resetCodeCell.innerHTML = '<span class="badge badge-success">' + data.reset_code + '</span>';
                            
                            // Update actions column
                            const actionsCell = row.querySelector('td:nth-child(6)');
                            actionsCell.innerHTML = '<span class="text-muted">No actions</span>';
                        }
                        
                        // Update the pending count
                        updatePendingCount();
                        
                    } else {
                        showToast('Error: ' + data.message, 'error');
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                },
                error: function(xhr, status, error) {
                    showToast('Error: ' + error, 'error');
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            });
        }
    }
    
    function rejectRequest(requestId) {
        if (confirm('Are you sure you want to reject this password reset request?')) {
            const button = event.target.closest('.btn');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;
            
            $.ajax({
                url: '../ajax/reject_password_reset.php',
                type: 'POST',
                data: { request_id: requestId },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        showToast('Request rejected successfully!', 'success');
                        
                        // Find and update the table row
                        const row = button.closest('tr');
                        if (row) {
                            // Update status column
                            const statusCell = row.querySelector('td:nth-child(3)');
                            statusCell.innerHTML = '<span class="badge badge-danger">Rejected</span>';
                            
                            // Update actions column
                            const actionsCell = row.querySelector('td:nth-child(6)');
                            actionsCell.innerHTML = '<span class="text-muted">No actions</span>';
                        }
                        
                        // Update the pending count
                        updatePendingCount();
                        
                    } else {
                        showToast('Error: ' + data.message, 'error');
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                },
                error: function(xhr, status, error) {
                    showToast('Error: ' + error, 'error');
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            });
        }
    }
    
    function clearAllNotifications() {
        if (confirm('Are you sure you want to clear ALL password reset notifications? This action cannot be undone and will remove all records from the database.')) {
            $.ajax({
                url: '../ajax/clear_all_notifications.php',
                type: 'POST',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        showToast('All notifications cleared successfully!', 'success');
                        
                        // Clear the table content
                        $('#notification-content').html('<tr><td colspan="6" class="text-center text-muted py-4">' +
                            '<i class="fa fa-bell fa-2x mb-2"></i><br>No password reset requests found</td></tr>');
                        
                        // Update the pending count
                        $('#pending-count').text('0 Pending');
                        
                    } else {
                        showToast('Error: ' + data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showToast('Error: ' + error, 'error');
                }
            });
        }
    }
    
    function formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleString();
    }
    
    function showToast(message, type) {
        const toastClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
        const toast = $(`
            <div class="alert ${toastClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `);
        
        $('body').append(toast);
        
        // Auto-remove after 3 seconds
        setTimeout(function() {
            toast.alert('close');
        }, 3000);
    }
    
    // RQC Sync Function
    function syncRqcData() {
        const btn = document.getElementById('rqcSyncBtn');
        if (!btn) return;
        
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
        
        showToast('Starting RQC sync from Google Sheets...', 'info');
        
        $.ajax({
            url: '../ajax/rqc_auto_sync.php',
            type: 'POST',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    let message = `RQC sync completed! `;
                    if (data.data) {
                        message += `${data.data.synced} records synced `;
                        if (data.data.inserted > 0) {
                            message += `(${data.data.inserted} new, ${data.data.updated} updated)`;
                        }
                    }
                    showToast(message, 'success');
                    
                    // Optionally refresh the dashboard data
                    if (typeof refreshDashboardData === 'function') {
                        refreshDashboardData();
                    }
                } else {
                    showToast('Sync failed: ' + (data.error || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Sync error: ' + error, 'error');
            },
            complete: function() {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }

    // Tooltip hover functionality
    $(document).ready(function() {
        $('.description-hover').each(function() {
            var fullDescription = $(this).attr('data-full-description');
            if (fullDescription) {
                var tooltipDescription = convertDescriptionForTooltip(fullDescription);
                $(this).attr('title', tooltipDescription);
            }
        });
        
        // Convert description format for tooltip
        function convertDescriptionForTooltip(description) {
            if (!description || description === 'N/A') {
                return description;
            }
            return description.replace(/\n/g, '<br>');
        }
    });
    </script>
