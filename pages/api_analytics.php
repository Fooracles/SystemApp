<?php
session_start();
$page_title = "API Usage Analytics";
require_once "../includes/header.php";

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user has admin role
if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

// Generate sample analytics data
$analytics_data = [
    'overview' => [
        'total_requests' => 2847,
        'unique_users' => 23,
        'active_endpoints' => 3,
        'avg_response_time' => '245ms',
        'error_rate' => '2.3%'
    ],
    'daily_usage' => [
        ['date' => '2024-01-15', 'requests' => 45, 'users' => 8],
        ['date' => '2024-01-16', 'requests' => 67, 'users' => 12],
        ['date' => '2024-01-17', 'requests' => 89, 'users' => 15],
        ['date' => '2024-01-18', 'requests' => 123, 'users' => 18],
        ['date' => '2024-01-19', 'requests' => 156, 'users' => 22],
        ['date' => '2024-01-20', 'requests' => 189, 'users' => 23],
        ['date' => '2024-01-21', 'requests' => 203, 'users' => 20]
    ],
    'endpoint_stats' => [
        [
            'endpoint' => '/api/tasks.php',
            'requests' => 1847,
            'avg_response_time' => '189ms',
            'error_count' => 23,
            'success_rate' => '98.8%',
            'top_users' => ['john.doe', 'jane.smith', 'mike.wilson']
        ],
        [
            'endpoint' => '/api/users.php',
            'requests' => 567,
            'avg_response_time' => '156ms',
            'error_count' => 8,
            'success_rate' => '98.6%',
            'top_users' => ['admin', 'manager1', 'manager2']
        ],
        [
            'endpoint' => '/api/reports.php',
            'requests' => 433,
            'avg_response_time' => '389ms',
            'error_count' => 12,
            'success_rate' => '97.2%',
            'top_users' => ['admin', 'manager1', 'analyst1']
        ]
    ],
    'user_activity' => [
        ['user' => 'admin', 'requests' => 456, 'last_activity' => '2024-01-21 14:30:00'],
        ['user' => 'john.doe', 'requests' => 234, 'last_activity' => '2024-01-21 13:45:00'],
        ['user' => 'jane.smith', 'requests' => 189, 'last_activity' => '2024-01-21 12:20:00'],
        ['user' => 'mike.wilson', 'requests' => 156, 'last_activity' => '2024-01-21 11:15:00'],
        ['user' => 'manager1', 'requests' => 134, 'last_activity' => '2024-01-21 10:30:00']
    ],
    'error_analysis' => [
        ['error_code' => 400, 'count' => 15, 'percentage' => '35.7%', 'description' => 'Bad Request - Invalid parameters'],
        ['error_code' => 401, 'count' => 12, 'percentage' => '28.6%', 'description' => 'Unauthorized - Authentication required'],
        ['error_code' => 403, 'count' => 8, 'percentage' => '19.0%', 'description' => 'Forbidden - Insufficient permissions'],
        ['error_code' => 500, 'count' => 7, 'percentage' => '16.7%', 'description' => 'Internal Server Error']
    ],
    'hourly_distribution' => [
        ['hour' => '00:00', 'requests' => 12],
        ['hour' => '01:00', 'requests' => 8],
        ['hour' => '02:00', 'requests' => 5],
        ['hour' => '03:00', 'requests' => 3],
        ['hour' => '04:00', 'requests' => 2],
        ['hour' => '05:00', 'requests' => 4],
        ['hour' => '06:00', 'requests' => 15],
        ['hour' => '07:00', 'requests' => 28],
        ['hour' => '08:00', 'requests' => 45],
        ['hour' => '09:00', 'requests' => 67],
        ['hour' => '10:00', 'requests' => 89],
        ['hour' => '11:00', 'requests' => 95],
        ['hour' => '12:00', 'requests' => 78],
        ['hour' => '13:00', 'requests' => 82],
        ['hour' => '14:00', 'requests' => 91],
        ['hour' => '15:00', 'requests' => 88],
        ['hour' => '16:00', 'requests' => 76],
        ['hour' => '17:00', 'requests' => 54],
        ['hour' => '18:00', 'requests' => 32],
        ['hour' => '19:00', 'requests' => 18],
        ['hour' => '20:00', 'requests' => 12],
        ['hour' => '21:00', 'requests' => 8],
        ['hour' => '22:00', 'requests' => 6],
        ['hour' => '23:00', 'requests' => 4]
    ]
];

?>

<style>
    .analytics-page .card {
        border: 1px solid #dee2e6;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        background: #ffffff;
        margin-bottom: 2rem;
    }

    .analytics-page .card-header {
        border-radius: 12px 12px 0 0;
        border-bottom: 1px solid #dee2e6;
        background: #f8f9fa;
    }

    .analytics-page .metric-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border: 2px solid #e9ecef;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .analytics-page .metric-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        border-color: #007bff;
    }

    .analytics-page .metric-card.bg-primary {
        background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%) !important;
        border-color: #4a90e2;
        color: white;
    }

    .analytics-page .metric-card.bg-success {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%) !important;
        border-color: #28a745;
        color: white;
    }

    .analytics-page .metric-card.bg-warning {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
        border-color: #ffc107;
        color: #212529;
    }

    .analytics-page .metric-card.bg-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
        border-color: #dc3545;
        color: white;
    }

    .analytics-page .metric-card.bg-info {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
        border-color: #17a2b8;
        color: white;
    }

    .chart-container {
        position: relative;
        height: 300px;
        margin: 1rem 0;
    }

    .chart-container canvas {
        max-height: 300px;
    }

    .table-analytics {
        font-size: 0.875rem;
    }

    .table-analytics th {
        background: #f8f9fa;
        font-weight: 600;
        border: none;
    }

    .table-analytics td {
        border: none;
        border-bottom: 1px solid #f1f3f4;
        vertical-align: middle;
    }

    .table-analytics tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }

    .progress-thin {
        height: 8px;
        border-radius: 4px;
    }

    .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 0.5rem;
    }

    .status-success {
        background-color: #28a745;
    }

    .status-warning {
        background-color: #ffc107;
    }

    .status-danger {
        background-color: #dc3545;
    }

    .filter-section {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 2rem;
    }

    .export-btn {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
        color: white;
        transition: all 0.3s ease;
    }

    .export-btn:hover {
        background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }
</style>

<div class="analytics-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line"></i> API Usage Analytics
                            </h5>
                            <div class="d-flex gap-2">
                                <button class="btn btn-light btn-sm" onclick="refreshAnalytics()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                                <button class="btn btn-light btn-sm export-btn" onclick="exportAnalytics()">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Overview Metrics -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card total-tasks">
                            <div class="metric-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?php echo number_format($analytics_data['overview']['total_requests']); ?></div>
                                <div class="metric-label">Total Requests</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card completed-tasks">
                            <div class="metric-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?php echo $analytics_data['overview']['unique_users']; ?></div>
                                <div class="metric-label">Unique Users</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card total-users">
                            <div class="metric-icon">
                                <i class="fas fa-code"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?php echo $analytics_data['overview']['active_endpoints']; ?></div>
                                <div class="metric-label">Active Endpoints</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card pending-tasks">
                            <div class="metric-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?php echo $analytics_data['overview']['avg_response_time']; ?></div>
                                <div class="metric-label">Avg Response Time</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card delayed-tasks">
                            <div class="metric-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?php echo $analytics_data['overview']['error_rate']; ?></div>
                                <div class="metric-label">Error Rate</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card completion-rate">
                            <div class="metric-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value">7 Days</div>
                                <div class="metric-label">Analysis Period</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <label class="form-label">Date Range</label>
                            <select class="form-control" id="dateRange">
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                                <option value="custom">Custom range</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Endpoint</label>
                            <select class="form-control" id="endpointFilter">
                                <option value="all">All Endpoints</option>
                                <option value="/api/tasks.php">Tasks API</option>
                                <option value="/api/users.php">Users API</option>
                                <option value="/api/reports.php">Reports API</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">User</label>
                            <select class="form-control" id="userFilter">
                                <option value="all">All Users</option>
                                <option value="admin">Admin</option>
                                <option value="john.doe">John Doe</option>
                                <option value="jane.smith">Jane Smith</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" onclick="applyFilters()">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Daily Usage Chart -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Daily API Usage</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="dailyUsageChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hourly Distribution -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Hourly Distribution</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="hourlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Endpoint Statistics -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Endpoint Statistics</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-analytics">
                                        <thead>
                                            <tr>
                                                <th>Endpoint</th>
                                                <th>Requests</th>
                                                <th>Success Rate</th>
                                                <th>Avg Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics_data['endpoint_stats'] as $stat): ?>
                                                <tr>
                                                    <td>
                                                        <code><?php echo htmlspecialchars($stat['endpoint']); ?></code>
                                                    </td>
                                                    <td><?php echo number_format($stat['requests']); ?></td>
                                                    <td>
                                                        <span class="status-indicator status-<?php echo $stat['success_rate'] >= '98%' ? 'success' : ($stat['success_rate'] >= '95%' ? 'warning' : 'danger'); ?>"></span>
                                                        <?php echo $stat['success_rate']; ?>
                                                    </td>
                                                    <td><?php echo $stat['avg_response_time']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Users -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Top Active Users</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-analytics">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Requests</th>
                                                <th>Last Activity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics_data['user_activity'] as $user): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($user['user']); ?></strong>
                                                    </td>
                                                    <td><?php echo number_format($user['requests']); ?></td>
                                                    <td>
                                                        <small class="text-muted"><?php echo date('M d, H:i', strtotime($user['last_activity'])); ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Analysis -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Error Analysis</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-analytics">
                                        <thead>
                                            <tr>
                                                <th>Error Code</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics_data['error_analysis'] as $error): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-<?php echo $error['error_code'] >= 500 ? 'danger' : ($error['error_code'] >= 400 ? 'warning' : 'secondary'); ?>">
                                                            <?php echo $error['error_code']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $error['count']; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress progress-thin flex-grow-1 me-2">
                                                                <div class="progress-bar bg-<?php echo $error['error_code'] >= 500 ? 'danger' : ($error['error_code'] >= 400 ? 'warning' : 'secondary'); ?>" 
                                                                     style="width: <?php echo $error['percentage']; ?>"></div>
                                                            </div>
                                                            <span class="text-muted small"><?php echo $error['percentage']; ?></span>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($error['description']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Daily Usage Chart
const dailyCtx = document.getElementById('dailyUsageChart').getContext('2d');
const dailyChart = new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($analytics_data['daily_usage'], 'date')); ?>,
        datasets: [{
            label: 'Requests',
            data: <?php echo json_encode(array_column($analytics_data['daily_usage'], 'requests')); ?>,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Users',
            data: <?php echo json_encode(array_column($analytics_data['daily_usage'], 'users')); ?>,
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Hourly Distribution Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
const hourlyChart = new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($analytics_data['hourly_distribution'], 'hour')); ?>,
        datasets: [{
            label: 'Requests',
            data: <?php echo json_encode(array_column($analytics_data['hourly_distribution'], 'requests')); ?>,
            backgroundColor: 'rgba(0, 123, 255, 0.8)',
            borderColor: '#007bff',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

function refreshAnalytics() {
    location.reload();
}

function exportAnalytics() {
    // Create CSV content
    let csvContent = "Metric,Value\n";
    csvContent += `Total Requests,${<?php echo $analytics_data['overview']['total_requests']; ?>}\n`;
    csvContent += `Unique Users,${<?php echo $analytics_data['overview']['unique_users']; ?>}\n`;
    csvContent += `Active Endpoints,${<?php echo $analytics_data['overview']['active_endpoints']; ?>}\n`;
    csvContent += `Average Response Time,${<?php echo $analytics_data['overview']['avg_response_time']; ?>}\n`;
    csvContent += `Error Rate,${<?php echo $analytics_data['overview']['error_rate']; ?>}\n`;
    
    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `api_analytics_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    alert('Analytics data exported successfully!');
}

function applyFilters() {
    const dateRange = document.getElementById('dateRange').value;
    const endpoint = document.getElementById('endpointFilter').value;
    const user = document.getElementById('userFilter').value;
    
    // In a real implementation, this would make an AJAX call to filter the data
    alert(`Filters applied:\n- Date Range: ${dateRange}\n- Endpoint: ${endpoint}\n- User: ${user}\n\nNote: This is a demo. In a real implementation, this would filter the displayed data.`);
}
</script>

<?php require_once "../includes/footer.php"; ?>
