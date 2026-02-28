<?php
// Include required files for authentication checks (before any output)
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// All logged-in users can access this page (access control handled below)
require_once "../includes/dashboard_components.php";

// Get username from query parameter
$target_username = isset($_GET['username']) ? trim($_GET['username']) : '';
$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
$current_username = $_SESSION['username'] ?? '';

// If no username provided, don't set default - let dropdown handle it
// Only set default for doers (they can only view themselves)
if(empty($target_username) && isDoer()) {
    $target_username = $current_username;
}

// Get target user information
$target_user = null;
$sql_user = "SELECT id, username, name, user_type, manager_id, department_id, COALESCE(Status, 'Active') as status 
             FROM users WHERE username = ?";
if($stmt_user = mysqli_prepare($conn, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "s", $target_username);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if($row_user = mysqli_fetch_assoc($result_user)) {
        $target_user = $row_user;
    }
    mysqli_stmt_close($stmt_user);
}

// If user not found and username was provided, redirect
// If no username provided (Admin/Manager selecting from dropdown), allow page to load
if(!$target_user && !empty($target_username)) {
    if(isAdmin()) {
        header("location: admin_dashboard.php");
    } else if(isManager()) {
    header("location: manager_dashboard.php");
    } else {
        header("location: doer_dashboard.php");
    }
    exit;
}

// If no target user and no username provided, set target_user to null (will be handled by dropdown)
if(!$target_user && empty($target_username)) {
    $target_user = null;
}

// ACCESS CONTROL (only if target_user exists)
if($target_user) {
    if(isAdmin()) {
        // Admin: Can view any user (active or inactive) - no additional check needed
    } else if(isManager()) {
        // Manager: Can view own performance OR direct doers (active or inactive)
        if($target_user['id'] != $current_user_id) {
            // If not viewing self, must be a doer under their management
    if($target_user['user_type'] !== 'doer' || $target_user['manager_id'] != $current_user_id) {
        header("location: manager_dashboard.php");
        exit;
    }
}
    } else if(isDoer()) {
        // Doer: Can only view own performance AND must be active
        if($target_user['id'] != $current_user_id) {
            header("location: doer_dashboard.php");
            exit;
        }
        // Do not allow viewing inactive users
        if($target_user['status'] !== 'Active') {
            header("location: doer_dashboard.php");
            exit;
        }
    } else {
        // Unknown role, redirect
        header("location: doer_dashboard.php");
        exit;
    }
}

// Now include header and other files (after all redirects are done)
$page_title = "Performance";
require_once "../includes/header.php";
require_once "../includes/status_column_helpers.php";

// Get user's department name (if target_user exists)
$department_name = 'N/A';
if($target_user && !empty($target_user['department_id'])) {
    $sql_dept = "SELECT name FROM departments WHERE id = ?";
    if($stmt_dept = mysqli_prepare($conn, $sql_dept)) {
        mysqli_stmt_bind_param($stmt_dept, "i", $target_user['department_id']);
        mysqli_stmt_execute($stmt_dept);
        $result_dept = mysqli_stmt_get_result($stmt_dept);
        if($row_dept = mysqli_fetch_assoc($result_dept)) {
            $department_name = $row_dept['name'];
        }
        mysqli_stmt_close($stmt_dept);
    }
}

?>

<link rel="stylesheet" href="../assets/css/doer_dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="doer-dashboard performance-page" id="teamPerformancePage">
    <!-- Header Section -->
    <div class="performance-header">
        <div class="performance-header-content">
            <div class="performance-title-section">
                <h1 class="performance-main-title" id="performanceHeader">
                    <span class="performance-username" id="headerUsername"><?php echo $target_user ? htmlspecialchars($target_user['name']) : 'Select User'; ?></span>
                    <span class="performance-time-range" id="headerTimeRange">Last Week</span>
                    <span class="performance-label">Performance is</span>
                    <span class="performance-score" id="headerScore">0%</span>
                    </h1>
                <div class="performance-date-range" id="headerDateRange"></div>
            </div>
            <div class="performance-header-actions">
                <?php
                $back_url = 'doer_dashboard.php';
                if(isAdmin()) $back_url = 'admin_dashboard.php';
                else if(isManager()) $back_url = 'manager_dashboard.php';
                ?>
                <button class="btn btn-primary btn-back" onclick="window.location.href='<?php echo $back_url; ?>'">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
            </div>
        </div>
    </div>

    <!-- Controls Row: User Selector and Time Range Toggle -->
    <div class="controls-row" id="controlsRow">
        <?php if(isAdmin() || isManager()): ?>
        <div class="user-selector-wrapper">
            <label class="user-selector-label">
                <i class="fas fa-user"></i>
            </label>
            <select id="userSelector" class="user-select-dropdown">
                <option value="">Select a user...</option>
            </select>
        </div>
        <?php endif; ?>
        <div class="time-range-toggle">
            <button class="time-range-pill active" data-range="last_week" title="Last Week">
                <span>Last Week</span>
            </button>
            <button class="time-range-pill" data-range="2w" title="2 Weeks">
                <span>2W</span>
            </button>
            <button class="time-range-pill" data-range="4w" title="4 Weeks">
                <span>4W</span>
            </button>
            <button class="time-range-pill" data-range="8w" title="8 Weeks">
                <span>8W</span>
            </button>
            <button class="time-range-pill" data-range="12w" title="12 Weeks">
                <span>12W</span>
            </button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-cards-section">
        <div class="stat-cards-container">
            <div class="stat-cards-grid" id="statCardsGrid">
                <!-- Stat cards will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <p class="text-center text-muted small fst-italic" style="margin: 1rem 1.5rem 0 1.5rem;">Weekly Performance is Frozen Every Sunday at Midnight.<br>Ensure all pending tasks are marked as Done On-time.</p>

    <!-- Graph Section -->
    <div class="graph-section">
        <div class="graph-header">
            <h3 class="graph-title">
                    <i class="fas fa-chart-line"></i>
                    Performance Trend
                </h3>
            <div class="graph-toggle-wrapper">
                <div class="graph-toggle">
                    <button class="graph-toggle-btn active" data-type="wnd">
                        <span>WND</span>
                    </button>
                    <button class="graph-toggle-btn" data-type="wnd_on_time">
                        <span>WND On Time</span>
                    </button>
                    <button class="graph-toggle-btn" data-type="rqc_score">
                        <span>RQC</span>
                    </button>
            </div>
                <!-- Hidden select for backward compatibility -->
                <select id="graphDataType" style="display: none;">
                    <option value="wnd" selected>WND</option>
                    <option value="wnd_on_time">WND On Time</option>
                    <option value="rqc_score">RQC Score</option>
                </select>
            </div>
        </div>
        <div class="graph-container">
            <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>

<style>
/* Performance Page Container */
.performance-page {
    min-height: 100vh;
    padding-bottom: 2rem;
}

/* Header Section - Compact */
.performance-header {
    margin: 0 1.5rem;
    padding: 1.25rem 0 1rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.performance-header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.performance-title-section {
    flex: 1;
    min-width: 280px;
}

.performance-main-title {
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.4;
    margin: 0 0 1rem 0;
    color: var(--dark-text-primary);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.performance-username,
.performance-time-range,
.performance-label {
    color: var(--dark-text-primary);
    font-weight: 600;
    font-size: 1.5rem;
}

.performance-score {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 600;
    font-size: 1.5rem;
}

.performance-date-range {
    font-size: 0.75rem;
    color: var(--dark-text-secondary);
    margin-top: 0.25rem;
    opacity: 0.8;
    font-weight: 400;
}

.performance-header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.btn-back {
    padding: 0.4rem 0.85rem;
    font-size: 0.8125rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 8px;
    background: rgba(59, 130, 246, 0.15) !important;
    border: 1px solid rgba(59, 130, 246, 0.3) !important;
    color: #60a5fa !important;
    box-shadow: none !important;
    filter: none !important;
}

.btn-back:hover {
    transform: translateX(-1px);
    background: rgba(59, 130, 246, 0.2) !important;
    border-color: rgba(59, 130, 246, 0.4) !important;
    box-shadow: none !important;
    filter: none !important;
}

/* Controls Row */
.controls-row {
    margin: 0 1.5rem;
    padding: 1rem 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

/* User Selector - Compact */
.user-selector-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.user-selector-label {
    display: flex;
    align-items: center;
    margin: 0;
    color: var(--brand-primary);
    font-size: 1rem;
    cursor: pointer;
}

.user-selector-label i {
    color: var(--brand-primary);
}

.user-select-dropdown {
    min-width: 200px;
    max-width: 250px;
    padding: 0.5rem 0.875rem;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    color: var(--dark-text-primary);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.user-select-dropdown:hover {
    border-color: rgba(99, 102, 241, 0.3);
    background: rgba(255, 255, 255, 0.04);
}

.user-select-dropdown:focus {
    outline: none;
    border-color: var(--brand-primary);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.time-range-toggle {
    display: flex;
    gap: 0.375rem;
    flex-wrap: wrap;
    align-items: center;
    margin-left: auto;
}

.time-range-pill {
    padding: 0.4375rem 0.9375rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    color: var(--dark-text-secondary);
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.time-range-pill::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%);
    opacity: 0;
    transition: opacity 0.25s ease;
}

.time-range-pill:hover::before {
    opacity: 1;
}

.time-range-pill:hover {
    border-color: rgba(99, 102, 241, 0.25);
    color: var(--dark-text-primary);
    transform: translateY(-0.5px);
}

.time-range-pill.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%);
    border-color: rgba(99, 102, 241, 0.4);
    color: var(--dark-text-primary);
    box-shadow: 0 1px 4px rgba(99, 102, 241, 0.15);
}

.time-range-pill span {
    position: relative;
    z-index: 1;
}

/* Stat Cards Section - Match Dashboard Style */
.stat-cards-section {
    margin: 1.5rem 1.5rem 0 1.5rem;
    padding: 0;
}

.stat-cards-container {
    width: 100%;
}

.stat-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-lg);
    width: 100%;
    overflow: visible;
}

@media (min-width: 1200px) {
    .stat-cards-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (min-width: 1600px) {
    .stat-cards-grid {
        grid-template-columns: repeat(7, 1fr);
    }
}

.stat-card {
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-xl);
    padding: var(--space-lg);
    box-shadow: var(--glass-shadow);
    transition: var(--transition-normal);
    position: relative;
    overflow: visible;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: var(--space-md);
    width: 100%;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: var(--radius-xl);
    opacity: 1;
    transition: var(--transition-normal);
    z-index: -1;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.stat-card:hover::before {
    opacity: 1;
}

/* Tasks Completed - Green */
.stat-card.completed::before {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
}

/* Task Pending - Golden Amber */
.stat-card.pending::before {
    background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
}

/* Delayed Tasks - Red/Orange */
.stat-card.delayed::before {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

/* RQC Score - Purple */
.stat-card.rqc::before {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* WND (Work Not Done) - Dark Gray */
.stat-card[data-stat="wnd"] {
    background: rgba(84, 110, 122, 0.3) !important;
    border-radius: var(--radius-xl);
}

.stat-card[data-stat="wnd"]::before {
    background: linear-gradient(135deg, #546E7A 0%, #607D8B 100%);
    border-radius: var(--radius-xl);
    opacity: 0.6;
}

/* WND On Time - Greyish */
.stat-card[data-stat="wnd_on_time"] {
    background: rgba(120, 144, 156, 0.3) !important;
    border-radius: var(--radius-xl);
}

.stat-card[data-stat="wnd_on_time"]::before {
    background: linear-gradient(135deg, #78909C 0%, #90A4AE 100%);
    border-radius: var(--radius-xl);
    opacity: 0.6;
}

/* Permanent glow effect behind cards */
.stat-card::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    z-index: -2;
    pointer-events: none;
    filter: blur(30px);
    opacity: 0.8;
}

/* Specific glow colors for each card type */
.stat-card.completed::after {
    background: radial-gradient(circle, rgba(22, 163, 74, 0.6) 0%, transparent 70%);
}

.stat-card.pending::after {
    background: radial-gradient(circle, rgba(251, 191, 36, 0.6) 0%, transparent 70%);
}

.stat-card.delayed::after {
    background: radial-gradient(circle, rgba(239, 68, 68, 0.6) 0%, transparent 70%);
}

.stat-card.rqc::after {
    background: radial-gradient(circle, rgba(102, 126, 234, 0.6) 0%, transparent 70%);
}

.stat-card[data-stat="wnd"]::after {
    background: radial-gradient(circle, rgba(55, 71, 79, 0.8) 0%, transparent 70%);
}

.stat-card[data-stat="wnd_on_time"]::after {
    background: radial-gradient(circle, rgba(84, 110, 122, 0.8) 0%, transparent 70%);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--dark-text-primary);
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    box-shadow: var(--neu-shadow-light), var(--neu-shadow-dark);
    flex-shrink: 0;
}

.stat-icon i {
    font-size: 1.5rem !important;
    width: 1.5rem;
    height: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

/* Icon background and colors for all stat cards */
.stat-card.completed .stat-icon {
    background: rgba(22, 163, 74, 0.2);
    border-color: rgba(22, 163, 74, 0.4);
}

.stat-card.completed .stat-icon i {
    color: #86efac;
}

.stat-card.pending .stat-icon {
    background: rgba(251, 191, 36, 0.2);
    border-color: rgba(251, 191, 36, 0.4);
}

.stat-card.pending .stat-icon i {
    color: #fde68a;
}

.stat-card.delayed .stat-icon {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.4);
}

.stat-card.delayed .stat-icon i {
    color: #fca5a5;
}

.stat-card.rqc .stat-icon {
    background: rgba(102, 126, 234, 0.2);
    border-color: rgba(102, 126, 234, 0.4);
}

.stat-card.rqc .stat-icon i {
    color: #a78bfa;
}

.stat-card[data-stat="wnd"] .stat-icon {
    background: rgba(55, 71, 79, 0.2);
    border-color: rgba(55, 71, 79, 0.4);
}

.stat-card[data-stat="wnd"] .stat-icon i {
    color: #90a4ae;
}

.stat-card[data-stat="wnd_on_time"] .stat-icon {
    background: rgba(84, 110, 122, 0.2);
    border-color: rgba(84, 110, 122, 0.4);
}

.stat-card[data-stat="wnd_on_time"] .stat-icon i {
    color: #b0bec5;
}

.stat-content {
    flex: 1;
    min-width: 0;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark-text-primary);
    margin: 0;
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: #f0eaea;
    margin: var(--space-xs) 0 0 0;
    font-weight: 500;
}

/* Graph Section - Compact */
.graph-section {
    margin: 2rem 1.5rem 0 1.5rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.02);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    transition: all 0.25s ease;
}

.graph-section:hover {
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.graph-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.graph-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark-text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.graph-title i {
    color: var(--brand-primary);
    font-size: 0.9375rem;
}

.graph-toggle-wrapper {
    display: flex;
    align-items: center;
}

.graph-toggle {
    display: flex;
    gap: 0.25rem;
    background: rgba(255, 255, 255, 0.03);
    padding: 0.1875rem;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.graph-toggle-btn {
    padding: 0.375rem 0.75rem;
    background: transparent;
    border: none;
    border-radius: 6px;
    color: var(--dark-text-secondary);
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.graph-toggle-btn:hover {
    color: var(--dark-text-primary);
    background: rgba(255, 255, 255, 0.04);
}

.graph-toggle-btn.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%);
    color: var(--dark-text-primary);
    box-shadow: 0 1px 4px rgba(99, 102, 241, 0.12);
}

.graph-container {
    position: relative;
    height: 320px;
    width: 100%;
    margin-top: 0.75rem;
}

/* Smooth Transitions */
.performance-page * {
    transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Loading States */
.stat-cards-grid.loading {
    opacity: 0.5;
    pointer-events: none;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .stat-cards-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .performance-header {
        margin: 0 1rem;
        padding: 1rem 0 0.75rem 0;
    }
    
    .performance-header-content {
        flex-direction: column;
        gap: 1rem;
    }
    
    .performance-main-title {
        font-size: 1.25rem;
    }
    
    .controls-row {
        margin: 0 1rem;
        padding: 0.75rem 0;
        flex-direction: column;
        align-items: stretch;
    }
    
    .user-selector-wrapper {
        width: 100%;
    }
    
    .user-select-dropdown {
        width: 100%;
        max-width: 100%;
    }
    
    .time-range-toggle {
        margin-left: 0;
        width: 100%;
        justify-content: flex-start;
    }
    
    .stat-cards-section,
    .graph-section {
        margin-left: 1rem;
        margin-right: 1rem;
    }
    
    .stat-cards-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .graph-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .graph-toggle {
        width: 100%;
        justify-content: stretch;
    }
    
    .graph-toggle-btn {
        flex: 1;
    }
    
    .graph-container {
        height: 280px;
    }
}

@media (max-width: 480px) {
    .performance-main-title {
        font-size: 1.125rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .time-range-toggle {
        gap: 0.25rem;
    }
    
    .time-range-pill {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .stat-card {
        padding: 1rem 0.875rem;
        min-height: 100px;
    }
    
    .stat-card .stat-value {
        font-size: 1.5rem;
    }
}
</style>

<script>
// Global variables
let currentUsername = '<?php echo !empty($target_username) ? htmlspecialchars($target_username, ENT_QUOTES) : ""; ?>';
let currentWeeks = 1;
let currentRangeType = 'last_week';
let accessibleUsers = [];
let performanceChart = null;
let currentPerformanceData = null;

// Get time range label
function getTimeRangeLabel(rangeType) {
    const labels = {
        'last_week': 'Last Week',
        '2w': '2W',
        '4w': '4W',
        '8w': '8W',
        '12w': '12W'
    };
    return labels[rangeType] || 'Last Week';
}

// Format date range as "DD/MMM to DD/MMM" (e.g., "07/Nov to 13/Dec")
function formatDateRange(dateFrom, dateTo) {
    if (!dateFrom || !dateTo) {
        return '';
    }
    
    // Parse dates (format: YYYY-MM-DD)
    const fromDate = new Date(dateFrom + 'T00:00:00');
    const toDate = new Date(dateTo + 'T00:00:00');
    
    // Month names array
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    // Format as DD/MMM
    const formatDate = (date) => {
        const day = String(date.getDate()).padStart(2, '0');
        const month = monthNames[date.getMonth()];
        return `${day}/${month}`;
    };
    
    const fromFormatted = formatDate(fromDate);
    const toFormatted = formatDate(toDate);
    
    return `${fromFormatted} to ${toFormatted}`;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeTimeRangeSelector();
    initializeUserSelector();
    initializeGraphToggle();
    
    // Only load performance data if username is provided
    if (currentUsername) {
        loadUserPerformance();
    } else {
        // Show empty state with dropdown
        showEmptyState();
    }
});

// Initialize time range selector
function initializeTimeRangeSelector() {
    const timeRangePills = document.querySelectorAll('.time-range-pill[data-range]');
    
    timeRangePills.forEach(pill => {
        pill.addEventListener('click', function() {
            timeRangePills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            
            const range = this.getAttribute('data-range');
            currentRangeType = range;
            switch(range) {
                case 'last_week': currentWeeks = 1;  break;
                case '2w':        currentWeeks = 2;  break;
                case '4w':        currentWeeks = 4;  break;
                case '8w':        currentWeeks = 8;  break;
                case '12w':       currentWeeks = 12; break;
                default:          currentWeeks = 1;
            }

            if (!currentUsername) {
                showEmptyState();
                return;
            }

            loadUserPerformance();
        });
    });
}

// Initialize user selector (for Admin/Manager)
function initializeUserSelector() {
    const userSelector = document.getElementById('userSelector');
    if (!userSelector) return;
    
    // Load accessible users
    fetch('../ajax/get_performance_users.php')
        .then(response => response.json())
        .then(result => {
            if (result.success && result.data) {
                accessibleUsers = result.data;
                populateUserSelector();
                
                // If no current username but users available
                if (!currentUsername && accessibleUsers.length > 0) {
                    <?php if(isManager()): ?>
                    // For managers, auto-select themselves
                    const currentUser = accessibleUsers.find(u => u.id == <?php echo $current_user_id; ?>);
                    if (currentUser) {
                        userSelector.value = currentUser.username;
                        currentUsername = currentUser.username;
                        loadUserPerformance();
                    }
                    <?php else: ?>
                    // For admins, don't auto-select - let them choose from dropdown
                    // Show empty state until user selects
                    <?php endif; ?>
                }
            }
            else {
                accessibleUsers = [];
                populateUserSelector();
            }
        })
        .catch(error => {
            accessibleUsers = [];
            populateUserSelector();
        });
    
    // Handle user selection change - load data instantly
    userSelector.addEventListener('change', function() {
        if (this.value) {
            currentUsername = this.value;
            // Update URL without reload
            const newUrl = `team_performance.php?username=${encodeURIComponent(this.value)}`;
            window.history.pushState({}, '', newUrl);
            // Load performance data instantly
            loadUserPerformance();
        } else {
            currentUsername = '';
            // Remove username from URL without reload
            window.history.pushState({}, '', 'team_performance.php');
            showEmptyState();
        }
    });
}

// Populate user selector dropdown
function populateUserSelector() {
    const selector = document.getElementById('userSelector');
    if (!selector) return;
    
    selector.innerHTML = '<option value="">Select a user...</option>';
    
    if (accessibleUsers.length === 0) {
        selector.innerHTML = '<option value="">No users available</option>';
                return;
            }
            
    // For managers: Show themselves first, then doers
    // For admins: Show all users
    let sortedUsers = [...accessibleUsers];
    <?php if(isManager()): ?>
    // Sort: current user first, then doers
    sortedUsers.sort((a, b) => {
        if (a.id == <?php echo $current_user_id; ?>) return -1;
        if (b.id == <?php echo $current_user_id; ?>) return 1;
        if (a.user_type === 'manager' && b.user_type === 'doer') return -1;
        if (a.user_type === 'doer' && b.user_type === 'manager') return 1;
        return a.name.localeCompare(b.name);
    });
    <?php else: ?>
    // For admins, just sort by name
    sortedUsers.sort((a, b) => a.name.localeCompare(b.name));
    <?php endif; ?>
    
    sortedUsers.forEach(user => {
        const option = document.createElement('option');
        option.value = user.username;
        option.textContent = user.name;
        if (user.username === currentUsername) {
            option.selected = true;
        }
        selector.appendChild(option);
    });
}

// Show empty state when no user selected
function showEmptyState() {
    const grid = document.getElementById('statCardsGrid');
    if (grid) {
        grid.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--dark-text-secondary); grid-column: 1 / -1;"><i class="fas fa-user" style="font-size: 2rem; margin-bottom: 1rem;"></i><p>Please select a user from the dropdown above to view performance data</p></div>';
    }
    
    // Clear header
    const headerUsername = document.getElementById('headerUsername');
    const headerScore = document.getElementById('headerScore');
    const headerDateRange = document.getElementById('headerDateRange');
    if (headerUsername) headerUsername.textContent = 'Select User';
    if (headerScore) headerScore.textContent = '0%';
    if (headerDateRange) headerDateRange.textContent = '';
    
    // Clear graph
    if (performanceChart) {
        performanceChart.destroy();
        performanceChart = null;
    }
    const ctx = document.getElementById('performanceChart');
    if (ctx && ctx.parentElement) {
        ctx.parentElement.innerHTML = '<canvas id="performanceChart"></canvas>';
    }
}

// Initialize graph data type toggle
function initializeGraphToggle() {
    const graphToggleBtns = document.querySelectorAll('.graph-toggle-btn[data-type]');
    
    graphToggleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            graphToggleBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const selectedType = this.getAttribute('data-type');
            // Update hidden select for compatibility
            const graphDataType = document.getElementById('graphDataType');
            if (graphDataType) {
                graphDataType.value = selectedType;
            }
            
            if (currentPerformanceData) {
                updateGraph();
            }
        });
    });
}

// Load user performance data
async function loadUserPerformance() {
    try {
        if (!currentUsername) {
            showEmptyState();
            return;
        }

        showLoadingState();
        
        // Build URL — fetch from frozen snapshots only
        let url = `../ajax/team_performance_data.php?username=${encodeURIComponent(currentUsername)}&weeks=${currentWeeks}`;
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data) {
            currentPerformanceData = result.data;
            displayPerformanceData(result.data);
        } else {
            showErrorState(result.error || 'Failed to load performance data');
        }
    } catch (error) {
        showErrorState('Error loading performance data. Please try again later.');
    }
}

// Show loading state
function showLoadingState() {
    const grid = document.getElementById('statCardsGrid');
    if (grid) {
        grid.classList.add('loading');
        grid.innerHTML = `
            <div style="text-align: center; padding: 2rem; grid-column: 1 / -1;">
                <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; color: var(--brand-primary); margin-bottom: 0.75rem; display: block; opacity: 0.7;"></i>
                <p style="color: var(--dark-text-secondary); font-size: 0.8125rem; opacity: 0.7;">Loading performance data...</p>
        </div>
    `;
}
}

// Show error state
function showErrorState(message) {
    const grid = document.getElementById('statCardsGrid');
    if (grid) {
        grid.innerHTML = `<div style="text-align: center; padding: 2rem; color: var(--brand-danger); grid-column: 1 / -1;"><i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i><p>${message}</p></div>`;
    }
}

// Display performance data
function displayPerformanceData(data) {
    const user = data.user || {};
    const stats = data.stats || {};
    const completionRate = data.completion_rate || 0;
    
    // Calculate Performance Score
    const rqcScore = data.rqc_score || null;
    const wnd = stats.wnd || 0;
    const wndOnTime = stats.wnd_on_time || 0;
    
    // Convert WND and WNDOT to positive scores (always included, even when 0 → 100)
    const convertedWnd = 100 - Math.abs(wnd);
    const convertedWndot = 100 - Math.abs(wndOnTime);
    const rqcValid = (rqcScore !== null && rqcScore > 0);
    
    // Performance Score: with RQC → /3, without RQC → /2
    const performanceScore = rqcValid
        ? (convertedWnd + convertedWndot + rqcScore) / 3
        : (convertedWnd + convertedWndot) / 2;
    
    // Update header (date range comes from the backend snapshot data)
    updateHeader(user.name, currentRangeType, performanceScore, data.date_range);
    
    // Display stat cards
    displayStatCards(stats, wnd, wndOnTime, rqcScore);
    
    // Update graph
    updateGraph();
}

// Update header
function updateHeader(username, timeRange, score, dateRange) {
    const headerUsername = document.getElementById('headerUsername');
    const headerTimeRange = document.getElementById('headerTimeRange');
    const headerScore = document.getElementById('headerScore');
    const headerDateRange = document.getElementById('headerDateRange');
    
    if (headerUsername) headerUsername.textContent = username;
    if (headerTimeRange) headerTimeRange.textContent = getTimeRangeLabel(timeRange);
    if (headerScore) headerScore.textContent = score.toFixed(1) + '%';
    
    // Date range comes from frozen snapshot data (earliest week_start → latest week_end)
    if (headerDateRange && dateRange && dateRange.from && dateRange.to) {
        headerDateRange.textContent = formatDateRange(dateRange.from, dateRange.to);
    } else if (headerDateRange) {
        headerDateRange.textContent = '';
    }
}

// Display stat cards
function displayStatCards(stats, wnd, wndOnTime, rqcScore) {
    const grid = document.getElementById('statCardsGrid');
    if (!grid) return;
    
    grid.classList.remove('loading');
    
    // Format RQC Score - show as percentage or N/A if null/0
    const rqcDisplay = (rqcScore !== null && rqcScore > 0) ? rqcScore.toFixed(1) + '%' : 'N/A';
    
    // Animate cards in with subtle animation - Match dashboard structure
    // Order: Total Task, Completed Tasks, Delayed, Pending Tasks, WND, WND on Time, RQC Score
    grid.innerHTML = `
        <div class="stat-card" style="opacity: 0; transform: translateY(10px); animation: fadeInUp 0.4s ease 0.05s forwards;">
            <div class="stat-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">${stats.total_tasks || 0}</div>
                <div class="stat-label">Total Task</div>
            </div>
        </div>
        <div class="stat-card completed" style="opacity: 0; transform: translateY(10px); animation: fadeInUp 0.4s ease 0.1s forwards;">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">${stats.completed_on_time || 0}</div>
                <div class="stat-label">Completed Tasks</div>
            </div>
        </div>
        <div class="stat-card delayed" style="opacity: 0; transform: translateY(10px); animation: fadeInUp 0.4s ease 0.15s forwards;">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">${stats.all_delayed_tasks || 0}</div>
                <div class="stat-label">Delayed</div>
            </div>
        </div>
        <div class="stat-card pending" style="opacity: 0; transform: translateY(10px); animation: fadeInUp 0.4s ease 0.2s forwards;">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">${stats.current_pending || 0}</div>
                <div class="stat-label">Pending Tasks</div>
            </div>
        </div>
        <div class="stat-card" data-stat="wnd" style="opacity: 0; transform: translateY(10px); animation: fadeInUp 0.4s ease 0.25s forwards;">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">${wnd.toFixed(1)}%</div>
                <div class="stat-label">WND</div>
            </div>
        </div>
        <div class="stat-card" data-stat="wnd_on_time" style="opacity: 0; transform: translateY(10px); animation: fadeInUp 0.4s ease 0.3s forwards;">
            <div class="stat-icon">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">${wndOnTime.toFixed(1)}%</div>
                <div class="stat-label">WND on Time</div>
            </div>
        </div>
        <div class="stat-card rqc" style="opacity: 0; transform: translateY(10px); animation: fadeInUp 0.4s ease 0.35s forwards;">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">${rqcDisplay}</div>
                <div class="stat-label">RQC Score</div>
            </div>
        </div>
    `;
    
    // Apply color-coded glow to WND and WND on Time cards
    applyWndGlow('wnd', wnd);
    applyWndGlow('wnd_on_time', wndOnTime);
}

// Color Coding Logic for WND & WNDOT cards
// Values are negative: <= -20.6% → RED, -20.5% to -10.6% → ORANGE, > -10% → GREY (default)
function applyWndGlow(statType, value) {
    const card = document.querySelector(`.stat-card[data-stat="${statType}"]`);
    if (!card) return;
    
    // Remove existing glow classes
    card.classList.remove('orange-glow', 'red-glow');
    
    // Parse value to number
    const numValue = parseFloat(value);
    if (isNaN(numValue)) return;
    
    // Apply glow based on value thresholds
    // If value <= -20.6%: RED glow
    // If value between -20.5% and -10.6%: ORANGE glow
    // Otherwise (> -10%): no glow (default GREY)
    if (numValue <= -20.6) {
        card.classList.add('red-glow');
    } else if (numValue <= -10.6 && numValue >= -20.5) {
        card.classList.add('orange-glow');
    }
}

// Update graph
function updateGraph() {
    if (!currentPerformanceData) return;
    
    // Get selected type from active button
    const activeBtn = document.querySelector('.graph-toggle-btn.active');
    const selectedType = activeBtn ? activeBtn.getAttribute('data-type') : 'wnd';
    
    const ctx = document.getElementById('performanceChart');
    if (!ctx) return;
    
    if (performanceChart) {
        performanceChart.destroy();
    }
    
    // Get weekly trend data
    const weeklyTrend = currentPerformanceData.weekly_trend || [];
    
    if (weeklyTrend.length === 0) {
        const container = ctx.parentElement;
        container.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: var(--dark-text-secondary);">
                <i class="fas fa-info-circle" style="font-size: 1.25rem; margin-bottom: 0.75rem; opacity: 0.4;"></i>
                <p style="font-size: 0.8125rem; opacity: 0.7;">No trend data available for the selected time range</p>
            </div>
        `;
        return;
    }
    
    const labels = weeklyTrend.map(w => w.week);
    let dataValues = [];
    let label = '';
    let color = '';
    
    // Determine if this is a negative percentage metric (WND, WND On Time, or RQC)
    const isNegativeMetric = selectedType === 'wnd' || selectedType === 'wnd_on_time' || selectedType === 'rqc_score';
    
    switch(selectedType) {
        case 'wnd':
            // Use actual negative values (do NOT convert to positive)
            dataValues = weeklyTrend.map(w => {
                const wnd = w.wnd || 0;
                return wnd; // Keep negative values as-is
            });
            label = 'WND';
            color = 'rgba(99, 102, 241, 1)';
            break;
        case 'wnd_on_time':
            // Use actual negative values (do NOT convert to positive)
            dataValues = weeklyTrend.map(w => {
                const wndOnTime = w.wnd_on_time || 0;
                return wndOnTime; // Keep negative values as-is
            });
            label = 'WND On Time';
            color = 'rgba(139, 92, 246, 1)';
            break;
        case 'rqc_score':
            // Convert RQC to negative scale: 100 (good) → 0 (top), 0 (bad) → -100 (bottom)
            dataValues = weeklyTrend.map(w => {
                const rqc = w.rqc || 0;
                return -(100 - rqc); // Convert: RQC 100 → 0, RQC 0 → -100
            });
            label = 'RQC Score';
            color = 'rgba(102, 126, 234, 1)';
            break;
    }
    
    performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: dataValues,
                borderColor: color,
                backgroundColor: color.replace('1)', '0.15)'),
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: color,
                pointBorderColor: 'rgba(18, 18, 18, 0.8)',
                pointBorderWidth: 1.5,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointHoverBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 800,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: 'rgba(255, 255, 255, 0.7)',
                        font: {
                            size: 11,
                            weight: 500
                        },
                        padding: 12,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(18, 18, 18, 0.98)',
                    titleColor: 'rgba(255, 255, 255, 0.85)',
                    bodyColor: 'rgba(255, 255, 255, 0.7)',
                    borderColor: 'rgba(255, 255, 255, 0.08)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 6,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            const value = context.parsed.y;
                            // Show percentage for all metrics, with negative sign for WND metrics
                            if (isNegativeMetric) {
                                label += value.toFixed(1) + '%';
                            } else {
                                label += value.toFixed(1) + '%';
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    // For WND, WND On Time, and RQC: scale from -100% (bottom) to 0% (top)
                    min: isNegativeMetric ? -100 : 0,
                    max: isNegativeMetric ? 0 : undefined,
                    reverse: false, // Normal scale: -100 at bottom, 0 at top
                    beginAtZero: !isNegativeMetric,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.08)',
                        drawBorder: false
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.5)',
                        font: {
                            size: 10
                        },
                        padding: 8,
                        callback: function(value) {
                            // Always show percentage with proper sign
                            return value + '%';
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.08)',
                        drawBorder: false
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.5)',
                        font: {
                            size: 10
                        },
                        padding: 8
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

// Add fade-in animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);
</script>

<?php require_once "../includes/footer.php";
?>
