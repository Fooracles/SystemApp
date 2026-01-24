<?php
$page_title = "Doer Dashboard";
require_once "../includes/header.php";
require_once "../includes/status_column_helpers.php";
require_once "../includes/dashboard_components.php";

// Check if the user is logged in and is a doer
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Check if this is a manager viewing their tasks via my_task.php
$manager_viewing_tasks = isset($_SESSION['manager_viewing_tasks']) && $_SESSION['manager_viewing_tasks'] === true;

// Redirect if not a doer and not a manager viewing tasks
if(!isDoer() && !$manager_viewing_tasks) {
    // Redirect Admin/Manager to their respective dashboards if they somehow land here directly
    if (isAdmin()) {
        header("location: admin_dashboard.php");
        exit;
    } elseif (isManager()) {
        header("location: manager_dashboard.php");
        exit;
    }
    // Default redirect for any other case (though should be covered by isLoggedIn)
    header("location: ../login.php"); 
    exit;
}

// Clear the session flag after use
if ($manager_viewing_tasks) {
    unset($_SESSION['manager_viewing_tasks']);
}

// Get user data for dashboard
$username = htmlspecialchars($_SESSION["username"]);
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1; // Default to 1 if not set

// Default date range for initial load (matches 7D preset on frontend)
$default_range_days = 7;
$default_date_to = date('Y-m-d');
$default_date_from = date('Y-m-d', strtotime('-' . ($default_range_days - 1) . ' days'));

// Get initial personal stats for the default range so UI shows accurate values immediately
$initial_personal_stats = calculatePersonalStats($conn, $user_id, $username, $default_date_from, $default_date_to);
$initial_tasks_completed = $initial_personal_stats['completed_on_time'] ?? 0;
$initial_tasks_pending = $initial_personal_stats['current_pending'] ?? 0;
$initial_tasks_delayed = $initial_personal_stats['current_delayed'] ?? 0;
$initial_wnd = $initial_personal_stats['wnd'] ?? 0;
$initial_wnd_on_time = $initial_personal_stats['wnd_on_time'] ?? 0;

// Get user's name for RQC score lookup (for initial display)
$user_name = '';
$user_name_query = "SELECT name FROM users WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $user_name_query)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $user_name = $row['name'] ?? '';
        }
    }
    mysqli_stmt_close($stmt);
}

// Get initial RQC Score from rqc_scores table (most recent, no date filter for initial load)
$rqc_score = 0;
if (!empty($user_name)) {
    $rqc_score = getRqcScore($conn, $user_name);
}

// Get leaderboard data (using function from dashboard_components.php)
// The function is already included above, so we can call it directly
// Non-admin users will only see top 3 + current user
// Default to "This Week" - last 7 days
$date_to = date('Y-m-d');
$date_from = date('Y-m-d', strtotime('-7 days'));
$is_admin = isAdmin();
$leaderboard_data = getLeaderboardData($conn, 0, null, $date_from, $date_to, $is_admin);
    
// Get team availability data (using function from dashboard_components.php)
// The function is already included above, so we can call it directly
$team_availability_data = getTeamAvailabilityData($conn);
?>

<!-- Modern Doer Dashboard with Glassmorphism + Neumorphism + Dark Theme -->
<div class="doer-dashboard">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1 class="welcome-title">
                Welcome back, <span class="username-highlight"><?php echo $username; ?></span>
            </h1>
            <p class="welcome-subtitle">Track your performance and climb the leaderboard!</p>
        </div>
        <div class="Daily-Quotes">
            <div class="quote-container">
                <div class="quote-icon">
                    <i class="fas fa-quote-left"></i>
                    </div>
                <div class="quote-content">
                    <p class="daily-quote" id="dailyQuote">
                        "Success is not final, failure is not fatal: it is the courage to continue that counts."
                    </p>
                    <div class="quote-author" id="quoteAuthor">
                        — Winston Churchill
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Widgets -->
    <div class="stats-section">
        <!-- Stats Header -->
        <div class="stats-header">
            <h6 class="stats-title">
                <i class="fas fa-chart-bar"></i>
                <span id="overviewTitle">Overview</span>
            </h6>
            <div class="date-range-selector">
                <button class="date-range-btn active" data-range="this_week" title="This Week">This Week</button>
                <button class="date-range-btn" data-range="last_week" title="Last Week">Last Week</button>
                <div class="date-range-dropdown">
                    <button class="date-range-btn dropdown-toggle" id="dateRangeDropdownBtn" title="More Options">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="date-range-dropdown-menu" id="dateRangeDropdownMenu" style="display: none;">
                        <button class="date-range-dropdown-item" data-range="last_2_weeks">Last 2 Weeks</button>
                        <button class="date-range-dropdown-item" data-range="last_4_weeks">Last 4 Weeks</button>
                    </div>
                </div>
            </div>
        </div>
    <div class="stats-container-wrapper">
        <div class="stats-grid" id="statsGrid">
        <div class="stat-card completed" onclick="window.location.href='my_task.php'">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-target="<?php echo (int) $initial_tasks_completed; ?>">
                    <?php echo (int) $initial_tasks_completed; ?>
                </div>
                <div class="stat-label">Completed Tasks</div>
            </div>
        </div>

        <div class="stat-card pending" onclick="window.location.href='my_task.php'">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-target="<?php echo (int) $initial_tasks_pending; ?>">
                    <?php echo (int) $initial_tasks_pending; ?>
                </div>
                <div class="stat-label">Pending Tasks</div>
            </div>
        </div>
            
        <div class="stat-card" data-stat="wnd" onclick="window.location.href='my_task.php'">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-target="<?php echo round((float) $initial_wnd); ?>">
                    <?php echo round((float) $initial_wnd); ?>%
                </div>
                <div class="stat-label">WND</div>
            </div>
        </div>

        <div class="stat-card" data-stat="wnd_on_time" onclick="window.location.href='my_task.php'">
            <div class="stat-icon">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-target="<?php echo round((float) $initial_wnd_on_time); ?>">
                    <?php echo round((float) $initial_wnd_on_time); ?>%
                </div>
                <div class="stat-label">WND on Time</div>
            </div>
        </div>

        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <!-- Leaderboard Section -->
        <div class="leaderboard-section">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-trophy"></i>
                    Top Performers
                </h3>
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
                <div class="leaderboard-list" id="leaderboardList">
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
            <style>
                .team-availability-section .section-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1rem;
                    flex-wrap: wrap;
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

                .team-availability-section .availability-stats {
                    display: flex;
                    align-items: center;
                    gap: 0.875rem;
                    flex-wrap: nowrap !important;
                }

                .team-availability-section .stat-item {
                    display: flex;
                    align-items: center;
                    gap: 0.625rem;
                    padding: 0.625rem 1rem;
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 0.625rem;
                    font-size: 0.875rem;
                    font-weight: 500;
                    color: var(--dark-text-primary);
                    line-height: 1.4;
                    transition: all 0.3s ease;
                    position: relative;
                    white-space: nowrap !important;
                    flex-shrink: 0 !important;
                    overflow: hidden;
                }

                .team-availability-section .stat-item::before {
                    content: '';
                    position: absolute;
                    left: 0;
                    top: 0;
                    bottom: 0;
                    width: 3px;
                    border-radius: 0.625rem 0 0 0.625rem;
                    transition: width 0.3s ease;
                }

                .team-availability-section .stat-item:hover {
                    background: rgba(255, 255, 255, 0.08);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                }

                .team-availability-section .stat-item.available {
                    border-left: 3px solid #10b981;
                }

                .team-availability-section .stat-item.available::before {
                    background: #10b981;
                }

                .team-availability-section .stat-item.on-wfh {
                    border-left: 3px solid #3b82f6;
                }

                .team-availability-section .stat-item.on-wfh::before {
                    background: #3b82f6;
                }

                .team-availability-section .stat-item.on-leave {
                    border-left: 3px solid #ef4444;
                }

                .team-availability-section .stat-item.on-leave::before {
                    background: #ef4444;
                }

                @media (max-width: 768px) {
                    .team-availability-section .section-header {
                        flex-direction: column;
                        align-items: flex-start;
                    }

                    .team-availability-section .availability-stats {
                        flex-wrap: nowrap !important;
                        gap: 0.625rem;
                    }

                    .team-availability-section .stat-item {
                        font-size: 0.8125rem;
                        white-space: nowrap !important;
                        padding: 0.5rem 0.875rem;
                    }
                }
            </style>
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
<hr>
        <!-- Motivation & Insights Panel -->
        <div class="motivation-section">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-lightbulb"></i>
                    Performance Insights
                </h3>
            </div>
            <div class="motivation-content" id="doerMotivationContent">
                <!-- Content will be loaded dynamically -->
                <div class="loading-motivation">
                    <i class="fas fa-spinner fa-spin"></i> Loading insights...
                </div>
            </div>
        </div>
    </div>

    <!-- Footer 
    <div class="dashboard-footer">
        <div class="footer-content">
            <span class="last-updated">Last Updated: <span id="lastUpdated"></span></span>
            <span class="powered-by">Powered by Fooracles </span>
        </div>
    </div> -->
</div>

<!-- Debug Information (Remove in production) -->
<?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h5>Debug Information</h5>
        </div>
        <div class="card-body">
            <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
            <p><strong>Username:</strong> <?php echo $username; ?></p>
            <p><strong>User Name:</strong> <?php echo $user_name; ?></p>
            <p><strong>RQC Score:</strong> <?php echo (is_numeric($rqc_score) && $rqc_score > 0) ? round(floatval($rqc_score), 1) . '%' : 'N/A'; ?></p>
            <p><strong>Note:</strong> All stats are now loaded via AJAX from ajax/doer_dashboard_data.php</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js removed - no longer needed -->

<!-- Dashboard JavaScript -->
<script>
// Global variable to store current date range
let currentDateRange = {
    type: 'this_week', // 'this_week', 'last_week', 'last_2_weeks', 'last_4_weeks', 'last_month', 'last_3_months', 'lifetime'
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

// Helper function to get Sunday of a given week
function getSundayOfWeek(date) {
    const monday = getMondayOfWeek(date);
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    return sunday;
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
            lastWeekSunday.setDate(thisWeekMonday2.getDate() - 1); // Sunday before this week's Monday
            toDate = lastWeekSunday;
            break;
            
        case 'last_4_weeks':
            // Monday of 4 weeks ago to Sunday of last week
            const thisWeekMonday4 = getMondayOfWeek(today);
            const fourWeeksAgoMonday = new Date(thisWeekMonday4);
            fourWeeksAgoMonday.setDate(thisWeekMonday4.getDate() - 28);
            fromDate = fourWeeksAgoMonday;
            const lastWeekSunday4 = new Date(thisWeekMonday4);
            lastWeekSunday4.setDate(thisWeekMonday4.getDate() - 1); // Sunday before this week's Monday
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

document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard
    initializeDashboard();
    
    // Set initial overview title
    updateOverviewTitle('this_week');
    
    // Load dashboard data
    loadDashboardData();
    
    // Initialize daily quotes
    initializeDailyQuotes();
    
    // Initialize team availability
    initializeTeamAvailability();
    
    // Load motivation insights
    loadDoerMotivation();
    
    // Listen for motivation updates from admin dashboard
    window.addEventListener('motivationInsightsUpdated', function(event) {
        const userId = <?php echo $user_id; ?>;
        if (event.detail.userId === userId) {
            loadDoerMotivation();
        }
    });
    
    // Also check localStorage for updates (cross-tab communication)
    setInterval(() => {
        const updateKey = localStorage.getItem('motivation_insights_updated');
        if (updateKey) {
            const userId = updateKey.match(/motivation_refresh_(\d+)_/);
            if (userId && parseInt(userId[1]) === <?php echo $user_id; ?>) {
                loadDoerMotivation();
                localStorage.removeItem('motivation_insights_updated');
            }
        }
    }, 1000);
    
    // Pagination is handled by goToPage function
    
    // Initialize date range selector
    initializeDateRangeSelector();
    
    // Initialize leave details modal
    initializeLeaveDetailsModal();
    
    // Initialize leaderboard with initial PHP data
    initializeLeaderboard();
    
    // Set up auto-refresh
    setInterval(() => loadDashboardData(), 600000); // Update every 10 Minutes
});

// Load doer motivation insights
function loadDoerMotivation() {
    const motivationContent = document.getElementById('doerMotivationContent');
    if (!motivationContent) return;
    
    fetch(`../ajax/get_user_motivation.php?user_id=<?php echo $user_id; ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const currentInsights = data.data.current_insights || '';
                const areasOfImprovement = data.data.areas_of_improvement || '';
                
                // Build HTML for motivation content
                let html = '';
                
                // Current Insights Section
                html += '<div class="insights-subsection">';
                html += '<div class="subsection-title"><i class="fas fa-chart-line"></i> Current Insights</div>';
                
                if (currentInsights && currentInsights.trim()) {
                    const insightsLines = currentInsights.split('\n').filter(line => line.trim());
                    insightsLines.forEach(insight => {
                        html += '<div class="insight-card">';
                        html += '<div class="insight-icon"><i class="fas fa-check-circle"></i></div>';
                        html += '<div class="insight-text">';
                        html += '<p>' + escapeHtml(insight.trim()) + '</p>';
                        html += '</div></div>';
                    });
                } else {
                    html += '<div class="insight-card">';
                    html += '<div class="insight-icon"><i class="fas fa-info-circle"></i></div>';
                    html += '<div class="insight-text">';
                    html += '<p style="color: var(--dark-text-muted); font-style: italic;">No insights available yet. Your manager will add insights soon!</p>';
                    html += '</div></div>';
                }
                html += '</div>';
                
                // Areas of Improvement Section
                html += '<div class="insights-subsection">';
                html += '<div class="subsection-title"><i class="fas fa-target"></i> Areas of Improvement / Focus</div>';
                
                if (areasOfImprovement && areasOfImprovement.trim()) {
                    const improvementLines = areasOfImprovement.split('\n').filter(line => line.trim());
                    improvementLines.forEach(improvement => {
                        html += '<div class="improvement-card">';
                        html += '<div class="improvement-icon"><i class="fas fa-lightbulb"></i></div>';
                        html += '<div class="improvement-text">';
                        html += '<p>' + escapeHtml(improvement.trim()) + '</p>';
                        html += '</div></div>';
                    });
                } else {
                    html += '<div class="improvement-card">';
                    html += '<div class="improvement-icon"><i class="fas fa-info-circle"></i></div>';
                    html += '<div class="improvement-text">';
                    html += '<p style="color: var(--dark-text-muted); font-style: italic;">No improvement areas defined yet.</p>';
                    html += '</div></div>';
                }
                html += '</div>';
                
                motivationContent.innerHTML = html;
            } else {
                motivationContent.innerHTML = '<div class="insight-card"><div class="insight-text"><p style="color: var(--dark-text-muted);">Unable to load insights. Please refresh the page.</p></div></div>';
            }
        })
        .catch(error => {
            console.error('Error loading motivation:', error);
            motivationContent.innerHTML = '<div class="insight-card"><div class="insight-text"><p style="color: var(--dark-text-muted);">Error loading insights. Please refresh the page.</p></div></div>';
        });
}

// Escape HTML helper
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize date range selector
function initializeDateRangeSelector() {
    // Handle main date range buttons (This Week, Last Week)
    document.querySelectorAll('.date-range-btn[data-range]').forEach(btn => {
        if (!btn.classList.contains('dropdown-toggle')) {
            btn.addEventListener('click', function() {
                const range = this.getAttribute('data-range');
                
                // Remove active class from all buttons
                document.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                    if (!b.classList.contains('dropdown-toggle')) {
                        b.classList.remove('active');
                    }
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Close dropdown if open
                const dropdownMenu = document.getElementById('dateRangeDropdownMenu');
                if (dropdownMenu) {
                    dropdownMenu.style.display = 'none';
                }
                
                // Update current date range
                currentDateRange.type = range;
                const dateRange = calculateWeekDateRange(range);
                currentDateRange.fromDate = dateRange.fromDate;
                currentDateRange.toDate = dateRange.toDate;
                
                // Update overview title
                updateOverviewTitle(range);
                
                // Reload dashboard data
                loadDashboardData();
            });
        }
    });
    
    // Handle dropdown toggle button
    const dropdownBtn = document.getElementById('dateRangeDropdownBtn');
    const dropdownMenu = document.getElementById('dateRangeDropdownMenu');
    
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
                document.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                    if (!b.classList.contains('dropdown-toggle')) {
                        b.classList.remove('active');
                    }
                });
                
                // Update current date range
                currentDateRange.type = range;
                const dateRange = calculateWeekDateRange(range);
                currentDateRange.fromDate = dateRange.fromDate;
                currentDateRange.toDate = dateRange.toDate;
                
                // Update overview title
                updateOverviewTitle(range);
                
                // Close dropdown
                dropdownMenu.style.display = 'none';
                
                // Reload dashboard data
                loadDashboardData();
            });
        });
    }
}

// Update overview title based on date range
function updateOverviewTitle(range, fromDate = null, toDate = null) {
    const titleElement = document.getElementById('overviewTitle');
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

async function loadDashboardData() {
    try {
        // Build query string for date range
        let url = '../ajax/doer_dashboard_data.php';
        const params = new URLSearchParams();
        
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
        
        if (params.toString()) {
            url += '?' + params.toString();
        }
        
        const response = await fetch(url);
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to check if it's valid JSON
        const responseText = await response.text();
        console.log('AJAX Response:', responseText);
        
        // Check if response starts with HTML (error page)
        if (responseText.trim().startsWith('<')) {
            throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
        }
        
        // Parse JSON
        const result = JSON.parse(responseText);
        
        if (result.success) {
            updateDashboard(result.data);
        } else {
            console.error('Failed to load dashboard data:', result.error);
            // Fallback to static data
            initializeStaticDashboard();
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        // Fallback to static data
        initializeStaticDashboard();
    }
}

function updateDashboard(data) {
    // Update stats (pass rqc_score separately since it's at data level, not stats level)
    // Check if RQC score is valid (not 0 or null or NaN)
    const rqcScoreRaw = (data.rqc_score !== undefined ? data.rqc_score : data.completion_rate);
    console.log('RQC score from API:', rqcScoreRaw, 'rqc_score:', data.rqc_score, 'completion_rate:', data.completion_rate);
    const rqcScoreNum = parseFloat(rqcScoreRaw);
    const validRqcScore = (!isNaN(rqcScoreNum) && rqcScoreNum > 0 && isFinite(rqcScoreNum)) ? rqcScoreNum : null;
    console.log('Valid RQC score:', validRqcScore);
    updateStats(data.stats, data.trends, validRqcScore);
    
    // Charts removed - no longer needed
    
    // Leaderboard is updated separately via period buttons (This Week, This Month, Last Year)
    // Do not update leaderboard here to keep it independent from stats date range
    
    // Update team availability
    updateTeamAvailability(data);
    
    // Update insights
    updateInsights(data.insights);
    
    // Update last updated time
    const lastUpdatedEl = document.getElementById('lastUpdated');
    if (lastUpdatedEl) {
        lastUpdatedEl.textContent = data.last_updated;
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

function updateStats(stats, trends, rqcScore) {
    // Update stat values with animation
    // Card 1: Completed Tasks (count)
    animateCounter('.stat-card.completed .stat-value', stats.tasks_completed || 0);
    
    // Card 2: Pending Tasks (count)
    animateCounter('.stat-card.pending .stat-value', stats.task_pending || 0);
    
    // Card 3: Work Not Done (percentage)
    const wndValue = (stats.wnd_percent !== undefined && stats.wnd_percent !== null) ? stats.wnd_percent : 0;
    animateCounter('.stat-card[data-stat="wnd"] .stat-value', wndValue, true);
    applyWndGlow('wnd', wndValue);
    
    // Card 4: Work Not Done On Time (percentage)
    const wndOnTimeValue = (stats.wnd_on_time_percent !== undefined && stats.wnd_on_time_percent !== null) ? stats.wnd_on_time_percent : 0;
    animateCounter('.stat-card[data-stat="wnd_on_time"] .stat-value', wndOnTimeValue, true);
    applyWndGlow('wnd_on_time', wndOnTimeValue);
}

// Special function to update RQC score (handles N/A)
function updateRqcScore(rqcScore) {
    const element = document.querySelector('.stat-card.em-score .stat-value');
    if (!element) return;
    
    // Debug logging
    console.log('updateRqcScore called with:', rqcScore, 'type:', typeof rqcScore);
    
    // Handle null, undefined, empty string
    if (rqcScore === null || rqcScore === undefined || rqcScore === '') {
        element.setAttribute('data-is-na', 'true');
        element.textContent = 'N/A';
        return;
    }
    
    // Convert to number and validate
    const numScore = parseFloat(rqcScore);
    
    // Check if it's a valid number and greater than 0
    if (!isNaN(numScore) && numScore > 0 && isFinite(numScore)) {
        // Valid RQC score - animate to new value with % and rounded
        element.setAttribute('data-is-na', 'false');
        animateCounter('.stat-card.em-score .stat-value', numScore, true);
    } else {
        // No RQC score - show N/A
        console.log('RQC score is invalid:', numScore, 'original:', rqcScore);
        element.setAttribute('data-is-na', 'true');
        element.textContent = 'N/A';
    }
}

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
    
    // Store the timer so we can clear it later
    activeTimers.set(selector, timer);
}

function updateTrend(selector, trend) {
    const element = document.querySelector(selector);
    if (!element) return;
    
    const trendClass = trend > 0 ? 'positive' : trend < 0 ? 'negative' : 'neutral';
    const icon = trend > 0 ? 'fas fa-arrow-up' : trend < 0 ? 'fas fa-arrow-down' : 'fas fa-minus';
    const sign = trend > 0 ? '+' : '';
    
    element.className = `stat-trend ${trendClass}`;
    element.innerHTML = `<i class="${icon}"></i><span>${sign}${trend}%</span>`;
}


function updatePerformanceChart(data) {
    const ctx = document.getElementById('performanceChart');
    if (!ctx) return;
    
    // Destroy existing chart if it exists
    if (window.performanceChart) {
        window.performanceChart.destroy();
    }
    
    window.performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'EM Score',
                data: data.data,
                borderColor: 'rgba(47, 60, 126, 1)',
                backgroundColor: 'rgba(47, 60, 126, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'rgba(47, 60, 126, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 80,
                    max: 100,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.7)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.7)'
                    }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            }
        }
    });
}

function updateLeaderboard(data) {
    // Update global leaderboard data
    // Ensure data is an array
    if (data && Array.isArray(data)) {
        leaderboardData = data;
    } else if (data) {
        // If data is not an array, try to convert it
        leaderboardData = Array.isArray(data) ? data : [];
    } else {
        // If data is null/undefined, keep existing data or set to empty array
        if (!leaderboardData || !Array.isArray(leaderboardData)) {
            leaderboardData = [];
        }
    }
    
    // Re-initialize leaderboard with current state
    initializeLeaderboard();
}

function updateInsights(insights) {
    // Skip if this is the doer motivation content (managed by loadDoerMotivation)
    const motivationContent = document.querySelector('.motivation-content');
    if (!motivationContent || motivationContent.id === 'doerMotivationContent') {
        return; // Skip - motivation content is managed by loadDoerMotivation()
    }
    
    motivationContent.innerHTML = '';
    
    if (insights && Array.isArray(insights)) {
        insights.forEach((insight, index) => {
            const insightCard = document.createElement('div');
            insightCard.className = 'insight-card';
            insightCard.style.animationDelay = `${index * 0.2}s`;
            
            insightCard.innerHTML = `
                <div class="insight-icon">
                    <i class="${insight.icon}"></i>
                </div>
                <div class="insight-text">
                    <strong>${insight.title}</strong>
                    <p>${insight.message}</p>
                </div>
            `;
            
            motivationContent.appendChild(insightCard);
        });
    }
}

function initializeStaticDashboard() {
    // Fallback to static data if API fails
    console.log('Using static dashboard data');
    
    // Initialize with static data
    initializeDashboard();
    animateCounters();
    initializeLeaderboard();
    updateLastUpdated();
}

function initializeDashboard() {
    // Add staggered animation to dashboard elements
    const elements = document.querySelectorAll('.stat-card, .chart-section, .leaderboard-section, .motivation-section');
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

function animateCounters() {
    const counters = document.querySelectorAll('.stat-value');
    
    counters.forEach(counter => {
        // Skip RQC card - it's handled by updateRqcScore
        if (counter.closest('.em-score')) {
            return;
        }
        
        // Skip if marked as N/A
        if (counter.getAttribute('data-is-na') === 'true') {
            return;
        }
        
        const targetAttr = counter.getAttribute('data-target');
        // Skip if target is "N/A" or invalid
        if (targetAttr === 'N/A' || targetAttr === null || targetAttr === undefined) {
            return;
        }
        
        const target = parseFloat(targetAttr);
        if (isNaN(target) || !isFinite(target)) {
            return;
        }
        
        const duration = 2000;
        const increment = target / (duration / 16);
        let current = 0;
        
        // Check if it's a percentage (has % in text or is in a percentage card)
        const isPercentage = counter.textContent.includes('%') || 
                            counter.closest('[data-stat="wnd"]') || 
                            counter.closest('[data-stat="wnd_on_time"]');
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            if (isPercentage) {
                counter.textContent = Math.round(current) + '%';
            } else {
                counter.textContent = Math.floor(current);
            }
        }, 16);
    });
}


function initializePerformanceChart() {
    const canvas = document.getElementById('performanceChart');
    if (!canvas) {
        console.warn('Performance chart canvas not found');
        return;
    }
    const ctx = canvas.getContext('2d');
    
    // Generate sample data for the last 7 days
    const labels = [];
    const data = [];
    const today = new Date();
    
    for (let i = 6; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(date.getDate() - i);
        labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        data.push(85 + Math.random() * 15); // Random EM scores between 85-100
    }
    
    new Chart(ctx, {
            type: 'line',
            data: {
            labels: labels,
                datasets: [{
                label: 'EM Score',
                data: data,
                borderColor: 'rgba(47, 60, 126, 1)',
                backgroundColor: 'rgba(47, 60, 126, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                pointBackgroundColor: 'rgba(47, 60, 126, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                    display: false
                    }
                },
                scales: {
                y: {
                    beginAtZero: false,
                    min: 80,
                    max: 100,
                        grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                        color: 'rgba(255, 255, 255, 0.7)'
                    }
                },
                x: {
                        grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                        color: 'rgba(255, 255, 255, 0.7)'
                            }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });
}

// Global variable to store leaderboard data
let leaderboardData = <?php echo json_encode($leaderboard_data); ?>;
let currentPage = 1;
const itemsPerPage = 4;
let currentLeaderboardPeriod = 'last_week'; // 'last_week', 'last_2_weeks', 'last_4_weeks'

function initializeLeaderboard() {
    const leaderboardList = document.getElementById('leaderboardList');
    if (!leaderboardList) return;
    
    leaderboardList.innerHTML = '';
    
    if (leaderboardData.length === 0) {
        leaderboardList.innerHTML = '<div class="leaderboard-empty">No leaderboard data available</div>';
        return;
    }
    
    // Get Top 3 performers
    const top3 = leaderboardData.slice(0, 3);
    
    // Find current user
    const currentUser = leaderboardData.find(user => user.is_current_user);
    
    // Build display data: Top 3 + current user (if not in Top 3)
    let displayData = [...top3];
    if (currentUser && currentUser.rank > 3) {
        displayData.push(currentUser);
    }
    
    if (displayData.length === 0) {
        leaderboardList.innerHTML = '<div class="leaderboard-empty">No leaderboard data available</div>';
        return;
    }
    
    // Helper function to get rank gradient class
    function getRankGradientClass(rank) {
        if (rank === 1) return 'rank-gold';
        if (rank === 2) return 'rank-silver';
        if (rank === 3) return 'rank-bronze';
        return '';
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
        
        // Make entire item clickable if username exists
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
                        <img src="../assets/uploads/profile_photos/user_${user.id}.png" 
                             alt="${user.name}" 
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="avatar-initials" style="display: none;">${initials}</div>
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
        
        leaderboardList.appendChild(item);
        
        // Animate progress ring after a short delay
        setTimeout(() => {
            const ring = item.querySelector('.ring-progress');
            if (ring) {
                ring.style.transition = 'stroke-dashoffset 1.5s ease-out';
            }
        }, index * 100);
    });
    
    // Hide pagination controls (no longer needed)
    const paginationContainer = document.getElementById('leaderboardPagination');
    if (paginationContainer) {
        paginationContainer.innerHTML = '';
    }
}

function updateLastUpdated() {
    const lastUpdatedEl = document.getElementById('lastUpdated');
    if (lastUpdatedEl) {
        const now = new Date();
        const timeString = now.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        lastUpdatedEl.textContent = timeString;
    }
}

// Daily Quotes functionality
const dailyQuotes = [
    {
        quote: "Success is not final, failure is not fatal: it is the courage to continue that counts.",
        author: "Winston Churchill"
    },
    {
        quote: "The future belongs to those who believe in the beauty of their dreams.",
        author: "Eleanor Roosevelt"
    },
    {
        quote: "Don't watch the clock; do what it does. Keep going.",
        author: "Sam Levenson"
    },
    {
        quote: "The only way to do great work is to love what you do.",
        author: "Steve Jobs"
    },
    {
        quote: "Success is walking from failure to failure with no loss of enthusiasm.",
        author: "Winston Churchill"
    },
    {
        quote: "Hard work beats talent when talent doesn't work hard.",
        author: "Tim Notke"
    },
    {
        quote: "The way to get started is to quit talking and begin doing.",
        author: "Walt Disney"
    },
    {
        quote: "Innovation distinguishes between a leader and a follower.",
        author: "Steve Jobs"
    },
    {
        quote: "Your limitation—it's only your imagination.",
        author: "Push yourself, because no one else is going to do it for you."
    },
    {
        quote: "Great things never come from comfort zones.",
        author: "Roy T. Bennett"
    },
    {
        quote: "Dream it. Wish it. Do it.",
        author: "Narendera Modi Ji"
    },
    {
        quote: "Don't be afraid to give up the good to go for the great.",
        author: "John D. Rockefeller"
    },
    {
        quote: "The harder you work for something, the greater you'll feel when you achieve it.",
        author: "Anonymous"
    },
    {
        quote: "Dream bigger. Do bigger.",
        author: "Anonymous"
    },
    {
        quote: "Don't stop when you're tired. Stop when you're done.",
        author: "Anonymous"
    }
];

function initializeDailyQuotes() {
    // Get today's date to determine which quote to show
    const today = new Date();
    const dayOfYear = Math.floor((today - new Date(today.getFullYear(), 0, 0)) / (1000 * 60 * 60 * 24));
    
    // Use day of year to select quote (ensures same quote all day)
    const quoteIndex = dayOfYear % dailyQuotes.length;
    const selectedQuote = dailyQuotes[quoteIndex];
    
    // Update the quote elements
    const quoteElement = document.getElementById('dailyQuote');
    const authorElement = document.getElementById('quoteAuthor');
    
    if (quoteElement && authorElement) {
        quoteElement.textContent = selectedQuote.quote;
        authorElement.textContent = `— ${selectedQuote.author}`;
    }
}

// Load leaderboard data based on selected time period
async function loadLeaderboardData() {
    try {
        let dateFrom = null;
        let dateTo = null;
        
        // Calculate week-based date ranges
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const thisWeekMonday = getMondayOfWeek(today);
        
        if (currentLeaderboardPeriod === 'last_week') {
            // Monday to Sunday of last week
            const lastWeekMonday = new Date(thisWeekMonday);
            lastWeekMonday.setDate(thisWeekMonday.getDate() - 7);
            const lastWeekSunday = new Date(lastWeekMonday);
            lastWeekSunday.setDate(lastWeekMonday.getDate() + 6);
            dateFrom = lastWeekMonday.toISOString().split('T')[0];
            dateTo = lastWeekSunday.toISOString().split('T')[0];
        } else if (currentLeaderboardPeriod === 'last_2_weeks') {
            // Monday of 2 weeks ago to Sunday of last week
            const twoWeeksAgoMonday = new Date(thisWeekMonday);
            twoWeeksAgoMonday.setDate(thisWeekMonday.getDate() - 14);
            const lastWeekSunday = new Date(thisWeekMonday);
            lastWeekSunday.setDate(thisWeekMonday.getDate() - 1);
            dateFrom = twoWeeksAgoMonday.toISOString().split('T')[0];
            dateTo = lastWeekSunday.toISOString().split('T')[0];
        } else if (currentLeaderboardPeriod === 'last_4_weeks') {
            // Monday of 4 weeks ago to Sunday of last week
            const fourWeeksAgoMonday = new Date(thisWeekMonday);
            fourWeeksAgoMonday.setDate(thisWeekMonday.getDate() - 28);
            const lastWeekSunday = new Date(thisWeekMonday);
            lastWeekSunday.setDate(thisWeekMonday.getDate() - 1);
            dateFrom = fourWeeksAgoMonday.toISOString().split('T')[0];
            dateTo = lastWeekSunday.toISOString().split('T')[0];
        }
        
        let url = '../ajax/doer_dashboard_data.php';
        const params = new URLSearchParams();
        if (dateFrom) {
            params.append('date_from', dateFrom);
        }
        if (dateTo) {
            params.append('date_to', dateTo);
        }
        
        if (params.toString()) {
            url += '?' + params.toString();
        }
        
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        if (responseText.trim().startsWith('<')) {
            throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
        }
        
        const result = JSON.parse(responseText);
        
        if (result.success && result.data && result.data.leaderboard) {
            leaderboardData = result.data.leaderboard || [];
            currentPage = 1; // Reset to first page
            initializeLeaderboard();
        } else {
            console.error('Failed to load leaderboard data:', result);
            // Show empty state
            const leaderboardList = document.getElementById('leaderboardList');
            if (leaderboardList) {
                leaderboardList.innerHTML = '<div class="leaderboard-empty">No leaderboard data available</div>';
            }
        }
    } catch (error) {
        console.error('Error loading leaderboard data:', error);
        // Show error state
        const leaderboardList = document.getElementById('leaderboardList');
        if (leaderboardList) {
            leaderboardList.innerHTML = '<div class="leaderboard-empty">Error loading leaderboard data</div>';
        }
    }
}

// Period button click handler for leaderboard
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('period-btn') || e.target.closest('.period-btn')) {
        const periodBtn = e.target.classList.contains('period-btn') ? e.target : e.target.closest('.period-btn');
        // Remove active class from all period buttons
        document.querySelectorAll('.period-btn').forEach(btn => btn.classList.remove('active'));
        // Add active class to clicked button
        periodBtn.classList.add('active');
        
        // Update current period and reload leaderboard data
        const period = periodBtn.getAttribute('data-period');
        currentLeaderboardPeriod = period;
        loadLeaderboardData();
    }
});

// View Performance functions
function viewPerformance() {
    // For doers, directly open their own performance page
    const currentUsername = '<?php echo htmlspecialchars($username, ENT_QUOTES); ?>';
    window.location.href = `team_performance.php?username=${encodeURIComponent(currentUsername)}`;
}

function viewPerformanceForUser(username) {
    if (username) {
        window.location.href = `team_performance.php?username=${encodeURIComponent(username)}`;
    }
}


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

// Team Availability functionality - Shows both available and on-leave members
function initializeTeamAvailability() {
    // Use real PHP data - includes both available and on-leave members
    const teamMembers = <?php echo json_encode($team_availability_data); ?>;
    
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
                <img src="../assets/uploads/profile_photos/user_${member.id}.png" 
                     alt="${member.name}" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <span style="display: none;">${firstLetter}</span>
            </div>
            <div class="member-name">${member.name}</div>
            <div class="member-status ${statusClass}"></div>
        `;
        
        teamGrid.appendChild(memberElement);
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
    // Don't prevent body scrolling since modal is contained within section
}

// Function to hide leave details modal
function hideLeaveDetails() {
    const modal = document.getElementById('leaveDetailsModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

// Initialize modal close handlers (called from main DOMContentLoaded)
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

// Function to update team availability with new data
function updateTeamAvailability(data) {
    if (data && data.team) {
        // Sort team members: on-leave (red) first, then remote (blue/WFH), then available (green), then by name
        const sortedTeam = data.team.sort((a, b) => {
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

// Stats Scroll Functionality - Removed (using 3x3 grid layout instead)
    </script>

<?php require_once "../includes/footer.php"; ?>
