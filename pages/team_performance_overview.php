<?php
// Include required files for authentication checks (before any output)
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// All logged-in users (Admin, Manager, Doer) can access this page
// Access control is handled in the AJAX endpoints

// Now include header and other files (after all redirects are done)
$page_title = "Team Performance Overview";
require_once "../includes/header.php";
require_once "../includes/status_column_helpers.php";
require_once "../includes/dashboard_components.php";

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
$current_username = $_SESSION['username'] ?? '';

?>

<link rel="stylesheet" href="../assets/css/doer_dashboard.css">
<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="doer-dashboard" id="teamPerformanceOverviewPage">
    <!-- Page Header -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1 class="welcome-title">
                Team Members <span class="username-highlight">Performance</span>
            </h1>
            <p class="welcome-subtitle">View detailed performance metrics and analytics</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <?php if(isAdmin()): ?>
                <button class="btn btn-primary" onclick="window.location.href='admin_dashboard.php'">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
            <?php elseif(isManager()): ?>
                <button class="btn btn-primary" onclick="window.location.href='manager_dashboard.php'">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
            <?php else: ?>
                <button class="btn btn-primary" onclick="window.location.href='doer_dashboard.php'">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Member Selection Section -->
    <div class="chart-section" style="margin-top: 2rem;">
        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <h3 class="section-title">
                <i class="fas fa-user-friends"></i>
                Team Members Performance
            </h3>
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <button class="btn btn-secondary" id="exportOverviewBtn" title="Export Performance Data">
                    <i class="fas fa-download"></i> Export
                </button>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: rgba(255, 255, 255, 0.8);">
                        <input type="checkbox" id="autoRefreshOverviewToggle" style="cursor: pointer;">
                        <span style="font-size: 0.9rem;">Auto-refresh</span>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Date Range Selector -->
        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; margin-bottom: 0.5rem; color: rgba(255, 255, 255, 0.8); font-weight: 600;">
                <i class="fas fa-calendar-alt"></i> Date Range:
            </label>
            <div class="date-range-selector" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button class="date-range-btn active" data-range="7d" title="7 Days">7D</button>
                <button class="date-range-btn" data-range="14d" title="2 Weeks">2W</button>
                <button class="date-range-btn" data-range="28d" title="4 Weeks">4W</button>
                <button class="date-range-btn" data-range="90d" title="3 Months">3M</button>
                <div class="custom-date-dropdown">
                    <button class="date-range-btn custom-date-btn" id="customDateOverviewBtn" title="Custom Date Range">
                        <i class="fas fa-calendar-alt"></i> Custom
                    </button>
                    <div class="custom-date-picker" id="customDateOverviewPicker" style="display: none;">
                        <div class="date-picker-header">
                            <span>Select Date Range</span>
                            <button class="close-date-picker" id="closeDateOverviewPicker">&times;</button>
                        </div>
                        <div class="date-picker-body">
                            <div class="date-input-group">
                                <label>From Date:</label>
                                <input type="date" id="dateFromOverview" class="date-input" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="date-input-group">
                                <label>To Date:</label>
                                <input type="date" id="dateToOverview" class="date-input" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="date-picker-actions">
                                <button class="btn-apply-date" id="applyCustomDateOverview">Apply</button>
                                <button class="btn-cancel-date" id="cancelCustomDateOverview">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Member Selection Dropdown -->
        <div style="margin-bottom: 2rem;">
            <label for="memberSelect" style="display: block; margin-bottom: 0.5rem; color: rgba(255, 255, 255, 0.8); font-weight: 600;">
                Select Team Member:
            </label>
            <select id="memberSelect" class="form-control" style="max-width: 400px; background: rgba(26, 26, 26, 0.95); border: 1px solid rgba(102, 126, 234, 0.2); color: #ffffff; padding: 0.75rem;">
                <option value="">Loading members...</option>
            </select>
        </div>

        <!-- Performance Content -->
        <div id="performanceContent" style="display: none;">
            <!-- Performance Stats -->
            <div class="performance-stats-grid" id="performanceStatsGrid" style="margin-bottom: 2rem;">
                <!-- Stats will be populated by JavaScript -->
            </div>

            <!-- Graph Type Selector -->
            <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <label style="color: rgba(255, 255, 255, 0.8); font-weight: 600; margin: 0;">Graph Type:</label>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button class="btn btn-sm btn-outline-primary graph-type-btn active" data-type="bar">
                        <i class="fas fa-chart-bar"></i> Bar
                    </button>
                    <button class="btn btn-sm btn-outline-primary graph-type-btn" data-type="line">
                        <i class="fas fa-chart-line"></i> Line
                    </button>
                    <button class="btn btn-sm btn-outline-primary graph-type-btn" data-type="pie">
                        <i class="fas fa-chart-pie"></i> Pie
                    </button>
                    <button class="btn btn-sm btn-outline-primary graph-type-btn" data-type="doughnut">
                        <i class="fas fa-chart-pie"></i> Doughnut
                    </button>
                </div>
            </div>

            <!-- Performance Graphs -->
            <div class="performance-graphs-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <!-- Task Status Distribution -->
                <div class="graph-card">
                    <div class="graph-card-header">
                        <h4><i class="fas fa-tasks"></i> Task Status Distribution</h4>
                    </div>
                    <div class="graph-card-body">
                        <canvas id="taskStatusChart"></canvas>
                    </div>
                </div>

                <!-- Completion Rate Over Time -->
                <div class="graph-card">
                    <div class="graph-card-header">
                        <h4><i class="fas fa-chart-line"></i> Completion Rate</h4>
                    </div>
                    <div class="graph-card-body">
                        <canvas id="completionRateChart"></canvas>
                    </div>
                </div>

                <!-- Task Breakdown -->
                <div class="graph-card">
                    <div class="graph-card-header">
                        <h4><i class="fas fa-list"></i> Task Breakdown</h4>
                    </div>
                    <div class="graph-card-body">
                        <canvas id="taskBreakdownChart"></canvas>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="graph-card">
                    <div class="graph-card-header">
                        <h4><i class="fas fa-chart-area"></i> Performance Metrics</h4>
                    </div>
                    <div class="graph-card-body">
                        <canvas id="performanceMetricsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading/Error Messages -->
        <div id="loadingMessage" style="text-align: center; padding: 2rem; color: var(--dark-text-secondary);">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
            <p>Please select a team member to view performance data</p>
        </div>
    </div>
</div>

<style>
/* Consistent side margins and padding with manager dashboard */
.doer-dashboard > .chart-section {
    margin-left: 2rem;
    margin-right: 2rem;
    padding: 1.5rem !important;
}

.doer-dashboard > .dashboard-header {
    margin-left: 2rem;
    margin-right: 2rem;
}

.performance-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
}

.performance-stat-card {
    background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(42, 42, 42, 0.95) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(102, 126, 234, 0.2);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(255, 255, 255, 0.05) inset;
    transition: all 0.3s ease;
    text-align: center;
}

.performance-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 
        0 12px 40px rgba(102, 126, 234, 0.3),
        0 0 0 1px rgba(102, 126, 234, 0.3) inset;
    border-color: rgba(102, 126, 234, 0.4);
}

.performance-stat-value {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
    line-height: 1.2;
}

.performance-stat-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.graph-card {
    background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(42, 42, 42, 0.95) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(102, 126, 234, 0.2);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(255, 255, 255, 0.05) inset;
    transition: all 0.3s ease;
}

.graph-card:hover {
    transform: translateY(-4px);
    box-shadow: 
        0 12px 40px rgba(102, 126, 234, 0.3),
        0 0 0 1px rgba(102, 126, 234, 0.3) inset;
    border-color: rgba(102, 126, 234, 0.4);
}

.graph-card-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.graph-card-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.graph-card-body {
    position: relative;
    height: 300px;
}

.graph-type-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    color: #ffffff;
}

.graph-type-btn {
    transition: all 0.3s ease;
}

.graph-type-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

/* Date Range Selector Styles */
.date-range-selector {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.date-range-btn {
    padding: 0.5rem 1rem;
    background: rgba(26, 26, 26, 0.95);
    border: 1px solid rgba(102, 126, 234, 0.2);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.8);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    font-weight: 500;
}

.date-range-btn:hover {
    background: rgba(102, 126, 234, 0.2);
    border-color: rgba(102, 126, 234, 0.4);
    transform: translateY(-2px);
}

.date-range-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    color: #ffffff;
}

.custom-date-dropdown {
    position: relative;
}

.custom-date-picker {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    background: rgba(26, 26, 26, 0.98);
    border: 1px solid rgba(102, 126, 234, 0.3);
    border-radius: 12px;
    padding: 1rem;
    z-index: 1000;
    min-width: 280px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.date-picker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.date-picker-header span {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

.close-date-picker {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close-date-picker:hover {
    color: #ffffff;
}

.date-picker-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.date-input-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.date-input-group label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    font-weight: 500;
}

.date-input {
    padding: 0.5rem;
    background: rgba(42, 42, 42, 0.95);
    border: 1px solid rgba(102, 126, 234, 0.2);
    border-radius: 6px;
    color: #ffffff;
    font-size: 0.9rem;
}

.date-input:focus {
    outline: none;
    border-color: rgba(102, 126, 234, 0.5);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.date-picker-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.btn-apply-date, .btn-cancel-date {
    flex: 1;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-apply-date {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #ffffff;
}

.btn-apply-date:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-cancel-date {
    background: rgba(42, 42, 42, 0.95);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-cancel-date:hover {
    background: rgba(52, 52, 52, 0.95);
}

/* Responsive Design */
@media (max-width: 768px) {
    .performance-graphs-container {
        grid-template-columns: 1fr !important;
    }
    
    .performance-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .graph-card-body {
        height: 250px;
    }
}
</style>

<script>
let currentCharts = {};
let currentGraphType = 'bar';
let currentPerformanceData = null;
let currentDateRangeOverview = { type: '7d', fromDate: null, toDate: null };
let autoRefreshOverviewInterval = null;
let autoRefreshOverviewEnabled = false;

document.addEventListener('DOMContentLoaded', function() {
    loadMembers();
    setupGraphTypeButtons();
    initializeDateRangeOverview();
    initializeAutoRefreshOverview();
    initializeExportOverview();
    
    // Load initial member if current user is a doer
    <?php if(isDoer()): ?>
    // For doers, automatically select themselves
    setTimeout(() => {
        const select = document.getElementById('memberSelect');
        if (select.options.length > 0) {
            select.value = '<?php echo htmlspecialchars($current_username, ENT_QUOTES); ?>';
            select.dispatchEvent(new Event('change'));
        }
    }, 500);
    <?php endif; ?>
});

async function loadMembers() {
    try {
        const response = await fetch('../ajax/get_team_performance_members.php');
        const result = await response.json();
        
        const select = document.getElementById('memberSelect');
        select.innerHTML = '<option value="">Select a team member...</option>';
        
        if (result.success && result.data && result.data.length > 0) {
            result.data.forEach(member => {
                const option = document.createElement('option');
                option.value = member.username;
                option.textContent = `${member.name} (${member.user_type})`;
                if (member.username === '<?php echo htmlspecialchars($current_username, ENT_QUOTES); ?>') {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            
            // If doer, select is disabled
            <?php if(isDoer()): ?>
            select.disabled = true;
            <?php endif; ?>
        } else {
            select.innerHTML = '<option value="">No members available</option>';
        }
        
        select.addEventListener('change', function() {
            const username = this.value;
            if (username) {
                loadPerformanceData(username);
            } else {
                hidePerformanceContent();
            }
        });
        
    } catch (error) {
        console.error('Error loading members:', error);
        document.getElementById('memberSelect').innerHTML = '<option value="">Error loading members</option>';
    }
}

async function loadPerformanceData(username) {
    const loadingMessage = document.getElementById('loadingMessage');
    const performanceContent = document.getElementById('performanceContent');
    
    loadingMessage.style.display = 'block';
    loadingMessage.innerHTML = `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: rgba(102, 126, 234, 1); margin-bottom: 1rem;"></i>
            <p style="color: rgba(255, 255, 255, 0.7);">Loading performance data...</p>
        </div>
    `;
    performanceContent.style.display = 'none';
    
    // Destroy existing charts
    destroyCharts();
    
    try {
        // Build URL with date range
        let url = `../ajax/team_performance_data.php?username=${encodeURIComponent(username)}`;
        if (currentDateRangeOverview.fromDate && currentDateRangeOverview.toDate) {
            url += `&date_from=${currentDateRangeOverview.fromDate}&date_to=${currentDateRangeOverview.toDate}`;
        } else if (currentDateRangeOverview.type !== '7d') {
            const days = parseInt(currentDateRangeOverview.type.replace('d', '').replace('w', '').replace('m', ''));
            const multiplier = currentDateRangeOverview.type.includes('w') ? 7 : (currentDateRangeOverview.type.includes('m') ? 30 : 1);
            const toDate = new Date();
            const fromDate = new Date();
            fromDate.setDate(fromDate.getDate() - (days * multiplier));
            url += `&date_from=${fromDate.toISOString().split('T')[0]}&date_to=${toDate.toISOString().split('T')[0]}`;
        }
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data) {
            currentPerformanceData = result.data;
            displayPerformanceStats(result.data);
            createCharts(result.data);
            
            loadingMessage.style.display = 'none';
            performanceContent.style.display = 'block';
        } else {
            loadingMessage.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--dark-text-danger);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>${result.error || 'Failed to load performance data'}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading performance data:', error);
        loadingMessage.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: var(--dark-text-danger);">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                <p>Error loading performance data. Please try again later.</p>
            </div>
        `;
    }
}

function displayPerformanceStats(data) {
    const stats = data.stats || {};
    const completionRate = data.completion_rate || 0;
    const grid = document.getElementById('performanceStatsGrid');
    
    grid.innerHTML = `
        <div class="performance-stat-card">
            <div class="performance-stat-value">${stats.total_tasks || 0}</div>
            <div class="performance-stat-label">Total Tasks</div>
        </div>
        <div class="performance-stat-card">
            <div class="performance-stat-value">${completionRate}%</div>
            <div class="performance-stat-label">Completion Rate</div>
        </div>
        <div class="performance-stat-card">
            <div class="performance-stat-value">${stats.completed_on_time || 0}</div>
            <div class="performance-stat-label">Completed On Time</div>
        </div>
        <div class="performance-stat-card">
            <div class="performance-stat-value">${stats.current_pending || 0}</div>
            <div class="performance-stat-label">Pending</div>
        </div>
        <div class="performance-stat-card">
            <div class="performance-stat-value">${stats.current_delayed || 0}</div>
            <div class="performance-stat-label">Delayed</div>
        </div>
        <div class="performance-stat-card">
            <div class="performance-stat-value">${stats.wnd || 0}</div>
            <div class="performance-stat-label">Work Not Done</div>
        </div>
    `;
}

function setupGraphTypeButtons() {
    const buttons = document.querySelectorAll('.graph-type-btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            buttons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentGraphType = this.dataset.type;
            if (currentPerformanceData) {
                destroyCharts();
                createCharts(currentPerformanceData);
            }
        });
    });
}

function createCharts(data) {
    const stats = data.stats || {};
    const completionRate = data.completion_rate || 0;
    
    // Task Status Distribution Chart
    createChart('taskStatusChart', {
        type: currentGraphType === 'pie' || currentGraphType === 'doughnut' ? currentGraphType : 'bar',
        data: {
            labels: ['Completed', 'Pending', 'Delayed', 'Work Not Done'],
            datasets: [{
                label: 'Tasks',
                data: [
                    stats.completed_on_time || 0,
                    stats.current_pending || 0,
                    stats.current_delayed || 0,
                    stats.wnd || 0
                ],
                backgroundColor: [
                    'rgba(76, 175, 80, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(244, 67, 54, 0.8)',
                    'rgba(158, 158, 158, 0.8)'
                ],
                borderColor: [
                    'rgba(76, 175, 80, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(244, 67, 54, 1)',
                    'rgba(158, 158, 158, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#ffffff' }
                }
            },
            scales: currentGraphType === 'bar' ? {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#ffffff' },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' }
                },
                x: {
                    ticks: { color: '#ffffff' },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' }
                }
            } : {}
        }
    });
    
    // Completion Rate Chart
    const totalTasks = stats.total_tasks || 1;
    const completed = stats.completed_on_time || 0;
    const notCompleted = totalTasks - completed;
    
    createChart('completionRateChart', {
        type: currentGraphType === 'pie' || currentGraphType === 'doughnut' ? currentGraphType : 'bar',
        data: {
            labels: ['Completed', 'Not Completed'],
            datasets: [{
                label: 'Tasks',
                data: [completed, notCompleted],
                backgroundColor: [
                    'rgba(102, 126, 234, 0.8)',
                    'rgba(158, 158, 158, 0.8)'
                ],
                borderColor: [
                    'rgba(102, 126, 234, 1)',
                    'rgba(158, 158, 158, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#ffffff' }
                }
            },
            scales: currentGraphType === 'bar' ? {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#ffffff' },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' }
                },
                x: {
                    ticks: { color: '#ffffff' },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' }
                }
            } : {}
        }
    });
    
    // Task Breakdown Chart
    createChart('taskBreakdownChart', {
        type: currentGraphType === 'pie' || currentGraphType === 'doughnut' ? currentGraphType : 'bar',
        data: {
            labels: ['Total Tasks', 'Completed', 'Pending', 'Delayed'],
            datasets: [{
                label: 'Count',
                data: [
                    stats.total_tasks || 0,
                    stats.completed_on_time || 0,
                    stats.current_pending || 0,
                    stats.current_delayed || 0
                ],
                backgroundColor: [
                    'rgba(102, 126, 234, 0.8)',
                    'rgba(76, 175, 80, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(244, 67, 54, 0.8)'
                ],
                borderColor: [
                    'rgba(102, 126, 234, 1)',
                    'rgba(76, 175, 80, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(244, 67, 54, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#ffffff' }
                }
            },
            scales: currentGraphType === 'bar' ? {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#ffffff' },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' }
                },
                x: {
                    ticks: { color: '#ffffff' },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' }
                }
            } : {}
        }
    });
    
    // Performance Metrics Chart
    createChart('performanceMetricsChart', {
        type: currentGraphType === 'line' ? 'line' : (currentGraphType === 'pie' || currentGraphType === 'doughnut' ? currentGraphType : 'bar'),
        data: {
            labels: ['Completion Rate', 'On-Time Rate', 'Pending Rate', 'Delayed Rate'],
            datasets: [{
                label: 'Percentage',
                data: [
                    completionRate,
                    totalTasks > 0 ? ((stats.completed_on_time || 0) / totalTasks * 100).toFixed(2) : 0,
                    totalTasks > 0 ? ((stats.current_pending || 0) / totalTasks * 100).toFixed(2) : 0,
                    totalTasks > 0 ? ((stats.current_delayed || 0) / totalTasks * 100).toFixed(2) : 0
                ],
                backgroundColor: 'rgba(102, 126, 234, 0.6)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 2,
                fill: currentGraphType === 'line'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#ffffff' }
                }
            },
            scales: (currentGraphType === 'bar' || currentGraphType === 'line') ? {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { 
                        color: '#ffffff',
                        callback: function(value) {
                            return value + '%';
                        }
                    },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' }
                },
                x: {
                    ticks: { color: '#ffffff' },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' }
                }
            } : {}
        }
    });
}

function createChart(canvasId, config) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    if (currentCharts[canvasId]) {
        currentCharts[canvasId].destroy();
    }
    
    currentCharts[canvasId] = new Chart(ctx, config);
}

function destroyCharts() {
    Object.values(currentCharts).forEach(chart => {
        if (chart) chart.destroy();
    });
    currentCharts = {};
}

function hidePerformanceContent() {
    document.getElementById('performanceContent').style.display = 'none';
    document.getElementById('loadingMessage').style.display = 'block';
    document.getElementById('loadingMessage').innerHTML = `
        <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
        <p>Please select a team member to view performance data</p>
    `;
    destroyCharts();
}

// Initialize date range selector for overview
function initializeDateRangeOverview() {
    const dateRangeBtns = document.querySelectorAll('.date-range-selector .date-range-btn[data-range]');
    const customDateBtn = document.getElementById('customDateOverviewBtn');
    const customDatePicker = document.getElementById('customDateOverviewPicker');
    const closeDatePicker = document.getElementById('closeDateOverviewPicker');
    const applyCustomDate = document.getElementById('applyCustomDateOverview');
    const cancelCustomDate = document.getElementById('cancelCustomDateOverview');
    const dateFromInput = document.getElementById('dateFromOverview');
    const dateToInput = document.getElementById('dateToOverview');
    const todayStr = new Date().toISOString().split('T')[0];
    
    if (dateFromInput) dateFromInput.setAttribute('max', todayStr);
    if (dateToInput) dateToInput.setAttribute('max', todayStr);

    // Date range button clicks
    dateRangeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            dateRangeBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const range = this.getAttribute('data-range');
            currentDateRangeOverview.type = range;
            currentDateRangeOverview.fromDate = null;
            currentDateRangeOverview.toDate = null;
            
            const memberSelect = document.getElementById('memberSelect');
            if (memberSelect && memberSelect.value) {
                loadPerformanceData(memberSelect.value);
            }
        });
    });

    // Custom date picker toggle
    if (customDateBtn && customDatePicker) {
        customDateBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            customDatePicker.style.display = customDatePicker.style.display === 'none' ? 'block' : 'none';
        });

        if (closeDatePicker) {
            closeDatePicker.addEventListener('click', function() {
                customDatePicker.style.display = 'none';
            });
        }

        if (cancelCustomDate) {
            cancelCustomDate.addEventListener('click', function() {
                customDatePicker.style.display = 'none';
            });
        }

        if (applyCustomDate) {
            applyCustomDate.addEventListener('click', function() {
                const dateFrom = dateFromInput.value;
                const dateTo = dateToInput.value;
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (dateFrom && dateTo) {
                    const fromDateObj = new Date(dateFrom);
                    const toDateObj = new Date(dateTo);
                    fromDateObj.setHours(0, 0, 0, 0);
                    toDateObj.setHours(0, 0, 0, 0);
                    
                    if (fromDateObj > toDateObj) {
                        alert('From date must be before To date');
                        return;
                    }
                    
                    if (fromDateObj > today || toDateObj > today) {
                        alert('Future dates are not allowed. Please select dates up to today.');
                        return;
                    }
                    
                    dateRangeBtns.forEach(b => b.classList.remove('active'));
                    currentDateRangeOverview.type = 'custom';
                    currentDateRangeOverview.fromDate = dateFrom;
                    currentDateRangeOverview.toDate = dateTo;
                    customDatePicker.style.display = 'none';
                    
                    const memberSelect = document.getElementById('memberSelect');
                    if (memberSelect && memberSelect.value) {
                        loadPerformanceData(memberSelect.value);
                    }
                } else {
                    alert('Please select both dates');
                }
            });
        }

        // Close picker when clicking outside
        document.addEventListener('click', function(e) {
            if (customDatePicker && !customDatePicker.contains(e.target) && !customDateBtn.contains(e.target)) {
                customDatePicker.style.display = 'none';
            }
        });
    }
}

// Initialize auto-refresh for overview
function initializeAutoRefreshOverview() {
    const toggle = document.getElementById('autoRefreshOverviewToggle');
    if (toggle) {
        toggle.addEventListener('change', function() {
            autoRefreshOverviewEnabled = this.checked;
            if (autoRefreshOverviewEnabled) {
                startAutoRefreshOverview();
            } else {
                stopAutoRefreshOverview();
            }
        });
    }
}

// Start auto-refresh (every 30 seconds)
function startAutoRefreshOverview() {
    stopAutoRefreshOverview();
    autoRefreshOverviewInterval = setInterval(() => {
        const memberSelect = document.getElementById('memberSelect');
        if (memberSelect && memberSelect.value) {
            loadPerformanceData(memberSelect.value);
        }
    }, 30000);
}

// Stop auto-refresh
function stopAutoRefreshOverview() {
    if (autoRefreshOverviewInterval) {
        clearInterval(autoRefreshOverviewInterval);
        autoRefreshOverviewInterval = null;
    }
}

// Initialize export for overview
function initializeExportOverview() {
    const exportBtn = document.getElementById('exportOverviewBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            if (currentPerformanceData) {
                exportOverviewToCSV();
            } else {
                alert('No data available to export. Please wait for data to load.');
            }
        });
    }
}

// Export overview data to CSV
function exportOverviewToCSV() {
    if (!currentPerformanceData) return;
    
    const data = currentPerformanceData;
    const user = data.user || {};
    const stats = data.stats || {};
    const completionRate = data.completion_rate || 0;
    
    let csv = `Performance Overview Report - ${user.name}\n`;
    csv += `Generated: ${new Date().toLocaleString()}\n`;
    csv += `Date Range: ${currentDateRangeOverview.fromDate || 'N/A'} to ${currentDateRangeOverview.toDate || 'N/A'}\n\n`;
    
    csv += `Metric,Value\n`;
    csv += `Total Tasks,${stats.total_tasks || 0}\n`;
    csv += `Completed On Time,${stats.completed_on_time || 0}\n`;
    csv += `Pending,${stats.current_pending || 0}\n`;
    csv += `Delayed,${stats.current_delayed || 0}\n`;
    csv += `Completion Rate,${completionRate.toFixed(2)}%\n`;
    csv += `Work Not Done (WND),${stats.wnd || 0}%\n\n`;
    
    // Task breakdown
    const breakdown = data.task_breakdown || {};
    csv += `Task Breakdown\n`;
    csv += `Type,Count\n`;
    csv += `Delegation Tasks,${breakdown.delegation || 0}\n`;
    csv += `Checklist Tasks,${breakdown.checklist || 0}\n`;
    csv += `FMS Tasks,${breakdown.fms || 0}\n`;
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `performance_overview_${user.username}_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    stopAutoRefreshOverview();
});
</script>

<?php require_once "../includes/footer.php"; ?>
