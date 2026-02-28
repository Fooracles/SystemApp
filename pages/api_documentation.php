<?php
session_start();
$page_title = "API Documentation";
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

// Define API endpoints with detailed documentation
$api_endpoints = [
    [
        'id' => 'tasks_api',
        'name' => 'Tasks API',
        'endpoint' => '/api/tasks.php',
        'description' => 'Dynamic task fetching with support for delegation, FMS, and checklist tasks',
        'method' => 'GET',
        'status' => 'active',
        'authentication' => 'Session-based (requires login)',
        'rate_limit' => '100 requests per hour per user',
        'response_format' => 'JSON with success/error structure',
        'parameters' => [
            'doer_id' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Filter by specific user ID',
                'example' => '123'
            ],
            'team_id' => [
                'type' => 'integer', 
                'required' => false,
                'description' => 'Filter by department/team ID',
                'example' => '456'
            ],
            'status' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by task status',
                'options' => ['pending', 'completed', 'not done', 'can not be done', 'shifted'],
                'example' => 'pending'
            ],
            'type' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by task type',
                'options' => ['delegation', 'fms', 'checklist'],
                'example' => 'delegation'
            ],
            'date_from' => [
                'type' => 'date',
                'required' => false,
                'description' => 'Filter tasks from specific date',
                'format' => 'YYYY-MM-DD',
                'example' => '2024-01-01'
            ],
            'date_to' => [
                'type' => 'date',
                'required' => false,
                'description' => 'Filter tasks to specific date',
                'format' => 'YYYY-MM-DD',
                'example' => '2024-01-31'
            ],
            'page' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Page number for pagination',
                'default' => 1,
                'example' => '1'
            ],
            'per_page' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Items per page',
                'default' => 15,
                'max' => 100,
                'example' => '15'
            ],
            'sort' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Sort column',
                'options' => ['unique_id', 'description', 'planned_date', 'actual_date', 'status', 'delay_duration', 'duration', 'doer_name'],
                'default' => 'planned_date',
                'example' => 'planned_date'
            ],
            'dir' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Sort direction',
                'options' => ['asc', 'desc'],
                'default' => 'desc',
                'example' => 'desc'
            ]
        ],
        'examples' => [
            [
                'title' => 'Get all tasks',
                'url' => '/api/tasks.php',
                'description' => 'Fetch all tasks for the authenticated user'
            ],
            [
                'title' => 'Get tasks for specific user',
                'url' => '/api/tasks.php?doer_id=123',
                'description' => 'Fetch tasks assigned to user ID 123'
            ],
            [
                'title' => 'Get pending delegation tasks',
                'url' => '/api/tasks.php?type=delegation&status=pending',
                'description' => 'Fetch only pending delegation tasks'
            ],
            [
                'title' => 'Get tasks with pagination',
                'url' => '/api/tasks.php?page=2&per_page=10',
                'description' => 'Fetch second page with 10 items per page'
            ],
            [
                'title' => 'Get tasks in date range',
                'url' => '/api/tasks.php?date_from=2024-01-01&date_to=2024-01-31',
                'description' => 'Fetch tasks planned between January 1-31, 2024'
            ]
        ],
        'response_examples' => [
            'success' => [
                'success' => true,
                'data' => [
                    'tasks' => [
                        [
                            'id' => 123,
                            'unique_id' => 'DELG-ABC123',
                            'task_type' => 'delegation',
                            'description' => 'Complete project report',
                            'planned_date' => '2024-01-20',
                            'planned_time' => '14:30:00',
                            'actual_date' => '',
                            'actual_time' => '',
                            'status' => 'pending',
                            'is_delayed' => 0,
                            'delay_duration' => '',
                            'duration' => '02:00:00',
                            'doer_name' => 'John Doe',
                            'department_name' => 'IT',
                            'assigned_by' => 'Jane Smith'
                        ]
                    ],
                    'meta' => [
                        'total_count' => 150,
                        'filtered_count' => 25,
                        'page' => 1,
                        'per_page' => 15,
                        'total_pages' => 10,
                        'has_next' => true,
                        'has_prev' => false
                    ],
                    'auth_info' => [
                        'user_id' => 123,
                        'user_type' => 'manager',
                        'auth_method' => 'session'
                    ]
                ],
                'timestamp' => '2024-01-20 15:30:00'
            ],
            'error' => [
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 401,
                    'message' => 'Authentication required',
                    'details' => 'You must be logged in to access this API'
                ],
                'timestamp' => '2024-01-20 15:30:00'
            ]
        ]
    ]
];

?>

<style>
    .api-docs-page .card {
        border: 1px solid #dee2e6;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        background: #ffffff;
        margin-bottom: 2rem;
    }

    .api-docs-page .card-header {
        border-radius: 12px 12px 0 0;
        border-bottom: 1px solid #dee2e6;
        background: #f8f9fa;
    }

    .api-docs-page .endpoint-header {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }

    .api-docs-page .method-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        border-radius: 0.375rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .method-get {
        background-color: #28a745;
        color: white;
    }

    .method-post {
        background-color: #007bff;
        color: white;
    }

    .method-put {
        background-color: #ffc107;
        color: #212529;
    }

    .method-delete {
        background-color: #dc3545;
        color: white;
    }

    .parameter-table {
        background: #f8f9fa;
        border-radius: 8px;
        overflow: hidden;
    }

    .parameter-table th {
        background: #e9ecef;
        font-weight: 600;
        border: none;
    }

    .parameter-table td {
        border: none;
        border-bottom: 1px solid #dee2e6;
    }

    .parameter-table tr:last-child td {
        border-bottom: none;
    }

    .code-block {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 1rem;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        overflow-x: auto;
    }

    .example-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .example-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: #007bff;
    }

    .example-header {
        background: #f8f9fa;
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
        border-radius: 8px 8px 0 0;
    }

    .example-body {
        padding: 1rem;
    }

    .copy-btn {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: rgba(0, 0, 0, 0.1);
        border: none;
        border-radius: 4px;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .copy-btn:hover {
        background: rgba(0, 0, 0, 0.2);
    }

    .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 0.5rem;
    }

    .status-active {
        background-color: #28a745;
    }

    .status-development {
        background-color: #ffc107;
    }

    .status-planned {
        background-color: #17a2b8;
    }

    .toc {
        position: sticky;
        top: 2rem;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 1rem;
    }

    .toc ul {
        list-style: none;
        padding-left: 0;
        margin-bottom: 0;
    }

    .toc li {
        margin-bottom: 0.5rem;
    }

    .toc a {
        color: #495057;
        text-decoration: none;
        font-size: 0.875rem;
    }

    .toc a:hover {
        color: #007bff;
        text-decoration: underline;
    }
</style>

<div class="api-docs-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="endpoint-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="mb-2">
                                <i class="fas fa-book"></i> FMS API Documentation
                            </h1>
                            <p class="mb-0">Complete reference for FMS system API endpoints</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-dark">Version 1.0</span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Table of Contents -->
                    <div class="col-lg-3">
                        <div class="toc">
                            <h6 class="mb-3">Table of Contents</h6>
                            <ul>
                                <li><a href="#overview">Overview</a></li>
                                <li><a href="#authentication">Authentication</a></li>
                                <li><a href="#rate-limiting">Rate Limiting</a></li>
                                <li><a href="#response-format">Response Format</a></li>
                                <li><a href="#endpoints">Endpoints</a></li>
                                <li><a href="#examples">Examples</a></li>
                                <li><a href="#error-codes">Error Codes</a></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="col-lg-9">
                        <!-- Overview -->
                        <div class="card" id="overview">
                            <div class="card-header">
                                <h5 class="mb-0">Overview</h5>
                            </div>
                            <div class="card-body">
                                <p>The FMS API provides programmatic access to task management functionality. All endpoints require authentication and return JSON responses.</p>
                                
                                <h6>Base URL</h6>
                                <div class="code-block">
                                    https://your-domain.com
                                </div>

                                <h6 class="mt-3">Supported HTTP Methods</h6>
                                <ul>
                                    <li><span class="method-badge method-get">GET</span> - Retrieve data</li>
                                    <li><span class="method-badge method-post">POST</span> - Create data</li>
                                    <li><span class="method-badge method-put">PUT</span> - Update data</li>
                                    <li><span class="method-badge method-delete">DELETE</span> - Delete data</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Authentication -->
                        <div class="card" id="authentication">
                            <div class="card-header">
                                <h5 class="mb-0">Authentication</h5>
                            </div>
                            <div class="card-body">
                                <p>All API endpoints require session-based authentication. Users must be logged in to the FMS system to access API endpoints.</p>
                                
                                <h6>Session Authentication</h6>
                                <p>Include your session cookie in the request. The API will automatically detect your authentication status and user role.</p>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Note:</strong> API access is role-based. Managers see their team's tasks, doers see their own tasks, and admins see all tasks.
                                </div>
                            </div>
                        </div>

                        <!-- Rate Limiting -->
                        <div class="card" id="rate-limiting">
                            <div class="card-header">
                                <h5 class="mb-0">Rate Limiting</h5>
                            </div>
                            <div class="card-body">
                                <p>API endpoints are rate-limited to prevent abuse and ensure system stability.</p>
                                
                                <ul>
                                    <li><strong>Tasks API:</strong> 100 requests per hour per user</li>
                                    <li><strong>Users API:</strong> 50 requests per hour per user</li>
                                    <li><strong>Reports API:</strong> 20 requests per hour per user</li>
                                </ul>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Rate Limit Exceeded:</strong> If you exceed the rate limit, you'll receive a 429 status code with a retry-after header.
                                </div>
                            </div>
                        </div>

                        <!-- Response Format -->
                        <div class="card" id="response-format">
                            <div class="card-header">
                                <h5 class="mb-0">Response Format</h5>
                            </div>
                            <div class="card-body">
                                <p>All API responses follow a consistent JSON structure:</p>
                                
                                <h6>Success Response</h6>
                                <div class="code-block">
{
    "success": true,
    "data": {
        // Response data here
    },
    "timestamp": "2024-01-20 15:30:00"
}
                                </div>

                                <h6 class="mt-3">Error Response</h6>
                                <div class="code-block">
{
    "success": false,
    "data": null,
    "error": {
        "code": 400,
        "message": "Invalid parameter",
        "details": "doer_id must be a positive integer"
    },
    "timestamp": "2024-01-20 15:30:00"
}
                                </div>
                            </div>
                        </div>

                        <!-- Endpoints -->
                        <div class="card" id="endpoints">
                            <div class="card-header">
                                <h5 class="mb-0">API Endpoints</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($api_endpoints as $endpoint): ?>
                                    <div class="endpoint-section mb-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="mb-1">
                                                    <span class="status-indicator status-<?php echo $endpoint['status']; ?>"></span>
                                                    <?php echo htmlspecialchars($endpoint['name']); ?>
                                                </h6>
                                                <code class="text-muted"><?php echo htmlspecialchars($endpoint['endpoint']); ?></code>
                                            </div>
                                            <div>
                                                <span class="method-badge method-<?php echo strtolower($endpoint['method']); ?>">
                                                    <?php echo $endpoint['method']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <p class="text-muted mb-3"><?php echo htmlspecialchars($endpoint['description']); ?></p>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Authentication:</strong> <?php echo htmlspecialchars($endpoint['authentication']); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Rate Limit:</strong> <?php echo htmlspecialchars($endpoint['rate_limit']); ?>
                                            </div>
                                        </div>

                                        <h6>Parameters</h6>
                                        <div class="parameter-table">
                                            <table class="table table-sm mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Parameter</th>
                                                        <th>Type</th>
                                                        <th>Required</th>
                                                        <th>Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($endpoint['parameters'] as $param => $details): ?>
                                                        <tr>
                                                            <td><code><?php echo htmlspecialchars($param); ?></code></td>
                                                            <td><?php echo htmlspecialchars($details['type']); ?></td>
                                                            <td>
                                                                <?php if (isset($details['required']) && $details['required']): ?>
                                                                    <span class="badge bg-danger">Required</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Optional</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($details['description']); ?>
                                                                <?php if (isset($details['options'])): ?>
                                                                    <br><small class="text-muted">Options: <?php echo implode(', ', $details['options']); ?></small>
                                                                <?php endif; ?>
                                                                <?php if (isset($details['default'])): ?>
                                                                    <br><small class="text-muted">Default: <?php echo htmlspecialchars($details['default']); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <h6 class="mt-4">Examples</h6>
                                        <?php foreach ($endpoint['examples'] as $example): ?>
                                            <div class="example-card">
                                                <div class="example-header">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($example['title']); ?></h6>
                                                    <p class="mb-0 text-muted small"><?php echo htmlspecialchars($example['description']); ?></p>
                                                </div>
                                                <div class="example-body">
                                                    <div class="position-relative">
                                                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($example['url']); ?>')">
                                                            <i class="fas fa-copy"></i> Copy
                                                        </button>
                                                        <div class="code-block">
                                                            GET <?php echo htmlspecialchars($example['url']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <h6 class="mt-4">Response Examples</h6>
                                        
                                        <h6 class="mt-3">Success Response</h6>
                                        <div class="position-relative">
                                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars(json_encode($endpoint['response_examples']['success'], JSON_PRETTY_PRINT)); ?>')">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                            <div class="code-block">
                                                <pre><?php echo htmlspecialchars(json_encode($endpoint['response_examples']['success'], JSON_PRETTY_PRINT)); ?></pre>
                                            </div>
                                        </div>

                                        <h6 class="mt-3">Error Response</h6>
                                        <div class="position-relative">
                                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars(json_encode($endpoint['response_examples']['error'], JSON_PRETTY_PRINT)); ?>')">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                            <div class="code-block">
                                                <pre><?php echo htmlspecialchars(json_encode($endpoint['response_examples']['error'], JSON_PRETTY_PRINT)); ?></pre>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Error Codes -->
                        <div class="card" id="error-codes">
                            <div class="card-header">
                                <h5 class="mb-0">HTTP Status Codes</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Status</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><span class="badge bg-success">200</span></td>
                                                <td>OK</td>
                                                <td>Request successful</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-warning">400</span></td>
                                                <td>Bad Request</td>
                                                <td>Invalid parameters or request format</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-danger">401</span></td>
                                                <td>Unauthorized</td>
                                                <td>Authentication required</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-danger">403</span></td>
                                                <td>Forbidden</td>
                                                <td>Insufficient permissions</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-warning">405</span></td>
                                                <td>Method Not Allowed</td>
                                                <td>HTTP method not supported</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-warning">429</span></td>
                                                <td>Too Many Requests</td>
                                                <td>Rate limit exceeded</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-danger">500</span></td>
                                                <td>Internal Server Error</td>
                                                <td>Server error occurred</td>
                                            </tr>
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

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show temporary success message
        const btn = event.target.closest('.copy-btn');
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.background = 'rgba(40, 167, 69, 0.2)';
        
        setTimeout(() => {
            btn.innerHTML = originalContent;
            btn.style.background = 'rgba(0, 0, 0, 0.1)';
        }, 2000);
    }).catch(err => {
        alert('Failed to copy to clipboard');
    });
}

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
