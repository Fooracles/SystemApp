<?php
session_start();

// Include necessary files for authentication functions
require_once '../includes/functions.php';
require_once '../includes/dashboard_components.php';
require_once '../includes/sorting_helpers.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Set page title and include header after authentication
$page_title = 'Leave Request Sorting Test';
require_once '../includes/header.php';

// Get user information from session
$user_role = $_SESSION["user_type"] ?? 'doer';
$user_name = $_SESSION["username"] ?? 'User';
$user_id = $_SESSION["id"] ?? null;
$user_display_name = $_SESSION["name"] ?? $user_name;

// Get sorting parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : '';
$sort_direction = isset($_GET['dir']) ? $_GET['dir'] : 'desc';
?>

<style>
/* Dark theme for test page */
body {
    background-color: #1a1a1a !important;
    color: #e0e0e0 !important;
}

.test-container {
    max-width: 1400px;
    margin: 20px auto;
    padding: 20px;
    background-color: #1a1a1a;
    color: #e0e0e0;
}

.test-container h1 {
    color: #ffffff;
}

.test-container p {
    color: #c0c0c0;
}

.test-panel {
    background: #2d2d2d;
    border: 2px solid #4a9eff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    color: #e0e0e0;
}

.test-panel h3 {
    color: #4a9eff;
    margin-top: 0;
    border-bottom: 2px solid #4a9eff;
    padding-bottom: 10px;
}

.test-panel p {
    color: #e0e0e0;
}

.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.status-pass {
    background-color: #28a745;
}

.status-fail {
    background-color: #dc3545;
}

.status-pending {
    background-color: #ffc107;
}

.debug-panel {
    background: #1e1e1e;
    border: 1px solid #444;
    border-radius: 4px;
    padding: 15px;
    margin-top: 15px;
    max-height: 300px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: #e0e0e0;
}

.debug-panel .log-entry {
    padding: 5px;
    border-bottom: 1px solid #333;
    color: #e0e0e0;
}

.debug-panel .log-entry.success {
    color: #4ade80;
}

.debug-panel .log-entry.error {
    color: #f87171;
}

.debug-panel .log-entry.info {
    color: #60a5fa;
}

.debug-panel .log-entry.warning {
    color: #fbbf24;
}

.test-table {
    margin-top: 20px;
}

.test-table th {
    background-color: #007bff;
    color: white;
    cursor: pointer;
    user-select: none;
}

.test-table th:hover {
    background-color: #0056b3;
}

.test-table td {
    background-color: #2d2d2d;
    color: #e0e0e0;
    border-color: #444;
}

.test-table tbody tr:hover {
    background-color: #3a3a3a;
}

.current-sort {
    background-color: #4a5568 !important;
    color: #fbbf24 !important;
}

.url-display {
    background: #2d2d2d;
    padding: 10px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    word-break: break-all;
    margin: 10px 0;
    color: #e0e0e0;
    border: 1px solid #444;
}

.url-display strong {
    color: #ffffff;
}

.ajax-monitor {
    background: #2d2d2d;
    border-left: 4px solid #4a9eff;
    padding: 10px;
    margin: 10px 0;
    color: #e0e0e0;
}

.ajax-monitor strong {
    color: #ffffff;
}

.test-button {
    margin: 5px;
    padding: 8px 16px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.test-button:hover {
    background: #0056b3;
}

.test-button:disabled {
    background: #4a5568;
    cursor: not-allowed;
    color: #9ca3af;
}

.sort-verification {
    margin-top: 15px;
    padding: 10px;
    background: #1e3a5f;
    border-radius: 4px;
    color: #e0e0e0;
}

.sort-verification.pass {
    background: #1e3a2e;
    border-left: 4px solid #28a745;
    color: #d4edda;
}

.sort-verification.fail {
    background: #3a1e1e;
    border-left: 4px solid #dc3545;
    color: #f8d7da;
}

.sort-verification strong {
    color: #ffffff;
}

/* Table styling for dark theme */
.table {
    color: #e0e0e0;
}

.table thead th {
    color: #ffffff;
}

.table tbody td {
    color: #e0e0e0;
}

.table-hover tbody tr:hover {
    background-color: #3a3a3a;
    color: #ffffff;
}

/* Spinner colors */
.spinner-border.text-warning {
    color: #fbbf24 !important;
}

.spinner-border.text-success {
    color: #28a745 !important;
}

/* Text colors */
.text-center {
    color: #e0e0e0;
}

.text-muted {
    color: #9ca3af !important;
}

/* Sortable header links */
.sortable-header {
    color: #ffffff !important;
}

.sortable-header:hover {
    color: #4a9eff !important;
}

.sortable-header.text-dark {
    color: #ffffff !important;
}

.sortable-header.text-dark:hover {
    color: #4a9eff !important;
}

/* Ensure all text in panels is visible */
.test-panel * {
    color: inherit;
}

.test-panel a {
    color: #4a9eff;
}

.test-panel a:hover {
    color: #60a5fa;
}
</style>

<div class="test-container">
    <h1><i class="fas fa-vial"></i> Leave Request Sorting Test</h1>
    <p>This page tests the sorting functionality in real-time. Click on any column header to test sorting.</p>

    <!-- Test Status Panel -->
    <div class="test-panel">
        <h3><i class="fas fa-clipboard-check"></i> Test Status</h3>
        <div id="testStatus">
            <p><span class="status-indicator status-pending"></span>Waiting for test to start...</p>
        </div>
    </div>

    <!-- URL Monitor Panel -->
    <div class="test-panel">
        <h3><i class="fas fa-link"></i> URL Parameters Monitor</h3>
        <div class="url-display" id="urlDisplay">
            Current URL: <span id="currentUrl"><?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?></span>
        </div>
        <div style="margin-top: 10px;">
            <strong>Sort Column:</strong> <span id="sortColumnDisplay"><?php echo htmlspecialchars($sort_column ?: 'None'); ?></span><br>
            <strong>Sort Direction:</strong> <span id="sortDirectionDisplay"><?php echo htmlspecialchars($sort_direction); ?></span>
        </div>
    </div>

    <!-- AJAX Monitor Panel -->
    <div class="test-panel">
        <h3><i class="fas fa-exchange-alt"></i> AJAX Request Monitor</h3>
        <div id="ajaxMonitor">
            <p>No AJAX requests yet. Click a column header to trigger a request.</p>
        </div>
    </div>

    <!-- Debug Log Panel -->
    <div class="test-panel">
        <h3><i class="fas fa-bug"></i> Debug Log</h3>
        <div class="debug-panel" id="debugLog">
            <div class="log-entry info">Test page loaded. Ready to test sorting functionality.</div>
        </div>
        <button class="test-button" onclick="clearDebugLog()">Clear Log</button>
    </div>

    <!-- Test Controls -->
    <div class="test-panel">
        <h3><i class="fas fa-sliders-h"></i> Test Controls</h3>
        <button class="test-button" onclick="runAllTests()">Run All Tests</button>
        <button class="test-button" onclick="testPendingTable()">Test Pending Table</button>
        <button class="test-button" onclick="testTotalTable()">Test Total Table</button>
        <button class="test-button" onclick="resetSorting()">Reset Sorting</button>
        <button class="test-button" onclick="verifyCurrentSort()">Verify Current Sort</button>
    </div>

    <!-- Pending Requests Table Test -->
    <div class="test-panel">
        <h3><i class="fas fa-clock"></i> Pending Requests Table Test</h3>
        <div class="table-responsive test-table">
            <table class="table table-hover table-sm" id="pendingRequestsTable">
                <thead class="table-light">
                    <tr>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('pending', 'employee_name')" class="text-dark text-decoration-none sortable-header" data-column="employee_name">
                                Employee <?php echo getSortIcon('employee_name', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('pending', 'leave_type')" class="text-dark text-decoration-none sortable-header" data-column="leave_type">
                                Leave Type <?php echo getSortIcon('leave_type', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('pending', 'duration')" class="text-dark text-decoration-none sortable-header" data-column="duration">
                                Duration <?php echo getSortIcon('duration', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('pending', 'start_date')" class="text-dark text-decoration-none sortable-header" data-column="start_date">
                                Start Date <?php echo getSortIcon('start_date', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('pending', 'end_date')" class="text-dark text-decoration-none sortable-header" data-column="end_date">
                                End Date <?php echo getSortIcon('end_date', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('pending', 'reason')" class="text-dark text-decoration-none sortable-header" data-column="reason">
                                Reason <?php echo getSortIcon('reason', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('pending', 'manager_name')" class="text-dark text-decoration-none sortable-header" data-column="manager_name">
                                Manager <?php echo getSortIcon('manager_name', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center">
                            <div class="spinner-border text-warning" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Loading pending requests...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id="pendingSortVerification" class="sort-verification" style="display: none;"></div>
    </div>

    <!-- Total Leaves Table Test -->
    <div class="test-panel">
        <h3><i class="fas fa-list"></i> Total Leaves Table Test</h3>
        <div class="table-responsive test-table">
            <table class="table table-hover table-sm" id="totalLeavesTable">
                <thead class="table-light">
                    <tr>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('total', 'employee_name')" class="text-dark text-decoration-none sortable-header" data-column="employee_name">
                                Employee Name <?php echo getSortIcon('employee_name', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('total', 'leave_type')" class="text-dark text-decoration-none sortable-header" data-column="leave_type">
                                Leave Type <?php echo getSortIcon('leave_type', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('total', 'duration')" class="text-dark text-decoration-none sortable-header" data-column="duration">
                                Duration <?php echo getSortIcon('duration', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('total', 'start_date')" class="text-dark text-decoration-none sortable-header" data-column="start_date">
                                Start Date <?php echo getSortIcon('start_date', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('total', 'end_date')" class="text-dark text-decoration-none sortable-header" data-column="end_date">
                                End Date <?php echo getSortIcon('end_date', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('total', 'reason')" class="text-dark text-decoration-none sortable-header" data-column="reason">
                                Reason <?php echo getSortIcon('reason', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                        <th>No. of Leaves</th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortTable('total', 'status')" class="text-dark text-decoration-none sortable-header" data-column="status">
                                Status <?php echo getSortIcon('status', $sort_column, $sort_direction); ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="spinner-border text-success" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Loading leave data...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id="totalSortVerification" class="sort-verification" style="display: none;"></div>
    </div>
</div>

<script>
// Initialize window.LEAVE FIRST - before any other scripts
window.LEAVE = {
    role: '<?php echo $user_role; ?>',
    name: '<?php echo addslashes($user_name); ?>',
    displayName: '<?php echo addslashes($user_display_name); ?>',
    id: <?php echo $user_id ? (int)$user_id : 'null'; ?>,
    email: '<?php echo addslashes($user_name); ?>@company.com'
};

// Test state
let testLog = [];
let ajaxRequests = [];
let lastSortState = {
    column: '',
    direction: '',
    tableType: ''
};

// Initialize test environment
document.addEventListener('DOMContentLoaded', function() {
    logDebug('info', 'Test page initialized');
    logDebug('info', 'window.LEAVE: role=' + (window.LEAVE ? window.LEAVE.role : 'undefined') + ', name=' + (window.LEAVE ? window.LEAVE.name : 'undefined'));
    updateURLDisplay();
    
    // Monitor URL changes
    let lastUrl = window.location.href;
    setInterval(function() {
        if (window.location.href !== lastUrl) {
            lastUrl = window.location.href;
            updateURLDisplay();
            logDebug('info', 'URL changed: ' + window.location.href);
        }
    }, 100);
    
    // Intercept fetch calls to monitor AJAX requests
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        const url = args[0];
        if (typeof url === 'string' && (url.includes('leave_fetch_pending') || url.includes('leave_fetch_totals'))) {
            logDebug('info', 'AJAX Request: ' + url);
            ajaxRequests.push({
                url: url,
                timestamp: new Date().toISOString()
            });
            updateAjaxMonitor();
            
            // Call original fetch and monitor response
            return originalFetch.apply(this, args).then(response => {
                response.clone().json().then(data => {
                    logDebug('success', 'AJAX Response received: ' + JSON.stringify(data).substring(0, 200));
                    verifySortInResponse(data, url);
                    // Update icons after data loads (headers might be re-rendered)
                    setTimeout(() => {
                        updateSortIconsFromURL();
                    }, 100);
                }).catch(e => {
                    logDebug('error', 'Error parsing AJAX response: ' + e.message);
                });
                return response;
            });
        }
        return originalFetch.apply(this, args);
    };
    
    // Initialize LeaveRequestManager and load data
    initializeDataLoading();
});

// Logging functions
function logDebug(type, message) {
    const timestamp = new Date().toLocaleTimeString();
    const logEntry = {
        type: type,
        message: message,
        timestamp: timestamp
    };
    testLog.push(logEntry);
    
    const logPanel = document.getElementById('debugLog');
    const entry = document.createElement('div');
    entry.className = 'log-entry ' + type;
    entry.textContent = '[' + timestamp + '] ' + message;
    logPanel.appendChild(entry);
    logPanel.scrollTop = logPanel.scrollHeight;
    
    // Keep only last 100 entries
    if (testLog.length > 100) {
        testLog.shift();
        logPanel.removeChild(logPanel.firstChild);
    }
}

function clearDebugLog() {
    testLog = [];
    document.getElementById('debugLog').innerHTML = '';
    logDebug('info', 'Debug log cleared');
}

// Update URL display
function updateURLDisplay() {
    const urlParams = new URLSearchParams(window.location.search);
    const sortParam = urlParams.get('sort') || 'None';
    const dirParam = urlParams.get('dir') || 'None';
    
    document.getElementById('currentUrl').textContent = window.location.href;
    document.getElementById('sortColumnDisplay').textContent = sortParam;
    document.getElementById('sortDirectionDisplay').textContent = dirParam;
}

// Update AJAX monitor
function updateAjaxMonitor() {
    const monitor = document.getElementById('ajaxMonitor');
    if (ajaxRequests.length === 0) {
        monitor.innerHTML = '<p>No AJAX requests yet.</p>';
        return;
    }
    
    let html = '<div class="ajax-monitor">';
    html += '<strong>Total Requests:</strong> ' + ajaxRequests.length + '<br>';
    if (ajaxRequests.length > 0) {
        const lastRequest = ajaxRequests[ajaxRequests.length - 1];
        html += '<strong>Last Request:</strong> ' + lastRequest.url + '<br>';
        html += '<strong>Time:</strong> ' + new Date(lastRequest.timestamp).toLocaleTimeString();
    }
    html += '</div>';
    monitor.innerHTML = html;
}

// Verify sort in AJAX response
function verifySortInResponse(data, url) {
    if (!data || !data.success || !data.data || !Array.isArray(data.data) || data.data.length < 2) {
        return; // Not enough data to verify
    }
    
    const urlParams = new URLSearchParams(url.split('?')[1] || '');
    const sortColumn = urlParams.get('sort');
    const sortDirection = urlParams.get('dir');
    
    if (!sortColumn || !sortDirection) {
        return; // No sort parameters
    }
    
    const rows = data.data;
    let isSorted = true;
    let verificationMessage = '';
    const isAsc = sortDirection.toLowerCase() === 'asc';
    
    for (let i = 0; i < rows.length - 1; i++) {
        const current = rows[i][sortColumn];
        const next = rows[i + 1][sortColumn];
        
        // Skip null/undefined values - they should be handled by SQL COALESCE
        if (current === null || current === undefined || current === '') {
            continue;
        }
        if (next === null || next === undefined || next === '') {
            continue;
        }
        
        // Compare values
        let comparison = 0;
        const currentStr = String(current).trim();
        const nextStr = String(next).trim();
        
        // Handle different data types
        if (sortColumn === 'duration' || sortColumn === 'leave_count') {
            // Numeric comparison
            const currentNum = parseFloat(currentStr) || 0;
            const nextNum = parseFloat(nextStr) || 0;
            comparison = currentNum - nextNum;
        } else if (sortColumn.includes('date') || sortColumn === 'created_at') {
            // Date comparison
            const currentDate = new Date(currentStr);
            const nextDate = new Date(nextStr);
            comparison = currentDate.getTime() - nextDate.getTime();
        } else {
            // String comparison (case-insensitive like SQL LOWER())
            comparison = currentStr.toLowerCase().localeCompare(nextStr.toLowerCase());
        }
        
        // Check if order is correct
        // For ASC: current should be <= next (comparison <= 0)
        // For DESC: current should be >= next (comparison >= 0)
        if (isAsc) {
            if (comparison > 0) {
                isSorted = false;
                verificationMessage = `Sort verification FAILED (ASC): Row ${i+1} "${currentStr}" should come after Row ${i+2} "${nextStr}" (alphabetically/numerically smaller values first)`;
                logDebug('error', `Row ${i+1}: "${currentStr}" vs Row ${i+2}: "${nextStr}" - comparison: ${comparison}`);
                break;
            }
        } else {
            if (comparison < 0) {
                isSorted = false;
                verificationMessage = `Sort verification FAILED (DESC): Row ${i+1} "${currentStr}" should come before Row ${i+2} "${nextStr}" (alphabetically/numerically larger values first)`;
                logDebug('error', `Row ${i+1}: "${currentStr}" vs Row ${i+2}: "${nextStr}" - comparison: ${comparison}`);
                break;
            }
        }
    }
    
    if (isSorted) {
        verificationMessage = `✓ Sort verification PASSED: Data is correctly sorted by ${sortColumn} (${sortDirection.toUpperCase()})`;
        logDebug('success', verificationMessage);
        
        const tableType = url.includes('pending') ? 'pending' : 'total';
        const verificationDiv = document.getElementById(tableType + 'SortVerification');
        verificationDiv.className = 'sort-verification pass';
        verificationDiv.innerHTML = '<strong>✓ Sort Verified:</strong> ' + verificationMessage;
        verificationDiv.style.display = 'block';
    } else {
        logDebug('error', verificationMessage);
        
        const tableType = url.includes('pending') ? 'pending' : 'total';
        const verificationDiv = document.getElementById(tableType + 'SortVerification');
        verificationDiv.className = 'sort-verification fail';
        verificationDiv.innerHTML = '<strong>✗ Sort Failed:</strong> ' + verificationMessage;
        verificationDiv.style.display = 'block';
    }
}

// Test functions
function runAllTests() {
    logDebug('info', 'Running all tests...');
    testPendingTable();
    setTimeout(() => {
        testTotalTable();
    }, 1000);
}

function testPendingTable() {
    logDebug('info', 'Testing Pending Table sorting...');
    const columns = ['employee_name', 'leave_type', 'duration', 'start_date', 'end_date'];
    let index = 0;
    
    function testNextColumn() {
        if (index >= columns.length) {
            logDebug('success', 'Pending table test completed');
            return;
        }
        
        const column = columns[index];
        logDebug('info', 'Testing column: ' + column);
        sortTable('pending', column);
        
        setTimeout(() => {
            verifyCurrentSort();
            index++;
            setTimeout(testNextColumn, 2000);
        }, 1500);
    }
    
    testNextColumn();
}

function testTotalTable() {
    logDebug('info', 'Testing Total Table sorting...');
    const columns = ['employee_name', 'leave_type', 'duration', 'start_date', 'end_date', 'status'];
    let index = 0;
    
    function testNextColumn() {
        if (index >= columns.length) {
            logDebug('success', 'Total table test completed');
            return;
        }
        
        const column = columns[index];
        logDebug('info', 'Testing column: ' + column);
        sortTable('total', column);
        
        setTimeout(() => {
            verifyCurrentSort();
            index++;
            setTimeout(testNextColumn, 2000);
        }, 1500);
    }
    
    testNextColumn();
}

function resetSorting() {
    logDebug('info', 'Resetting sorting...');
    const url = new URL(window.location);
    url.searchParams.delete('sort');
    url.searchParams.delete('dir');
    window.location.href = url.toString();
}

function verifyCurrentSort() {
    const urlParams = new URLSearchParams(window.location.search);
    const sortColumn = urlParams.get('sort');
    const sortDirection = urlParams.get('dir');
    
    logDebug('info', 'Verifying current sort: column=' + sortColumn + ', direction=' + sortDirection);
    
    // Check if sort icons are correct
    const headers = document.querySelectorAll('.sortable-header');
    let iconCorrect = true;
    
    headers.forEach(header => {
        const column = header.getAttribute('data-column') || header.getAttribute('onclick')?.match(/'([^']+)'/)?.[1];
        const icon = header.querySelector('.sort-icon');
        
        if (column === sortColumn && icon) {
            const isActive = icon.classList.contains('sort-icon-active');
            const isAsc = icon.classList.contains('fa-chevron-up') && !icon.classList.contains('fa-chevron-down');
            const isDesc = icon.classList.contains('fa-chevron-down');
            
            if (!isActive) {
                iconCorrect = false;
                logDebug('error', 'Icon for active column ' + column + ' is not marked as active');
            }
            
            if (sortDirection === 'asc' && !isAsc) {
                iconCorrect = false;
                logDebug('error', 'Icon should show ascending (up) but shows descending');
            }
            
            if (sortDirection === 'desc' && !isDesc) {
                iconCorrect = false;
                logDebug('error', 'Icon should show descending (down) but shows ascending');
            }
        }
    });
    
    if (iconCorrect) {
        logDebug('success', 'Sort icons are correct');
    }
    
    updateTestStatus(sortColumn, sortDirection, iconCorrect);
}

function updateTestStatus(sortColumn, sortDirection, iconCorrect) {
    const statusDiv = document.getElementById('testStatus');
    let statusHTML = '';
    
    if (sortColumn) {
        statusHTML += '<p><span class="status-indicator ' + (iconCorrect ? 'status-pass' : 'status-fail') + '"></span>';
        statusHTML += '<strong>Current Sort:</strong> ' + sortColumn + ' (' + sortDirection + ')';
        statusHTML += iconCorrect ? ' - Icons: ✓' : ' - Icons: ✗';
        statusHTML += '</p>';
    } else {
        statusHTML += '<p><span class="status-indicator status-pending"></span>No active sort</p>';
    }
    
    statusDiv.innerHTML = statusHTML;
}

// Make sortTable function available globally if not already
if (typeof sortTable === 'undefined') {
    // Initialize from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    let currentSortColumn = urlParams.get('sort') || '';
    let currentSortDirection = urlParams.get('dir') || 'desc';
    let currentTableType = 'pending';
    
    window.sortTable = function(tableType, column) {
        currentTableType = tableType;
        
        logDebug('info', 'sortTable called: tableType=' + tableType + ', column=' + column);
        
        // Get next sort direction (two-state: asc → desc → asc)
        if (currentSortColumn === column && currentTableType === tableType) {
            if (currentSortDirection === 'asc') {
                currentSortDirection = 'desc';
            } else {
                currentSortDirection = 'asc';
            }
        } else {
            currentSortColumn = column;
            currentSortDirection = 'asc';
        }
        
        // Update URL without page reload
        const params = new URLSearchParams(window.location.search);
        params.set('sort', currentSortColumn);
        params.set('dir', currentSortDirection);
        window.history.pushState({}, '', '?' + params.toString());
        
        logDebug('info', 'URL updated: sort=' + currentSortColumn + ', dir=' + currentSortDirection);
        updateURLDisplay();
        
        // Reload the appropriate table (check both variable names)
        const manager = window.leaveManager || window.leaveRequestManager;
        if (manager) {
            if (tableType === 'pending' && typeof manager.loadPendingRequests === 'function') {
                manager.loadPendingRequests();
            }
            if (tableType === 'total' && typeof manager.loadTotalLeaves === 'function') {
                manager.loadTotalLeaves();
            }
        }
        
        // Update current sort variables for compatibility
        currentSortColumn = currentSortColumn;
        currentSortDirection = currentSortDirection;
        currentTableType = tableType;
        
        // Update all sort icons - use custom function that reads from URL
        updateSortIconsFromURL();
        
        // Also try the standard functions
        if (typeof window.updateTableSortIcons === 'function') {
            window.updateTableSortIcons();
        } else if (typeof updateSortIcons === 'function') {
            // Update the global variables first
            if (typeof window.currentSortColumn !== 'undefined') {
                window.currentSortColumn = currentSortColumn;
            }
            if (typeof window.currentSortDirection !== 'undefined') {
                window.currentSortDirection = currentSortDirection;
            }
            if (typeof window.currentTableType !== 'undefined') {
                window.currentTableType = currentTableType;
            }
            updateSortIcons();
        }
        
        // Verify after a short delay
        setTimeout(() => {
            verifyCurrentSort();
        }, 500);
    };
}

// Custom function to update sort icons from URL parameters
function updateSortIconsFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    const sortColumn = urlParams.get('sort') || '';
    const sortDirection = urlParams.get('dir') || '';
    
    logDebug('info', 'Updating sort icons: column=' + sortColumn + ', direction=' + sortDirection);
    
    document.querySelectorAll('.sortable-header').forEach(header => {
        const link = header.querySelector('a') || header;
        const onclick = link.getAttribute('onclick');
        const dataColumn = header.getAttribute('data-column');
        
        // Get column name from onclick or data-column
        let column = dataColumn;
        let tableType = '';
        
        if (!column && onclick) {
            const match = onclick.match(/sortTable\(['"]([^'"]+)['"],\s*['"]([^'"]+)['"]\)/);
            if (match) {
                tableType = match[1];
                column = match[2];
            }
        }
        
        if (!column) return;
        
        // Find or create icon
        let icon = header.querySelector('.sort-icon');
        if (!icon) {
            icon = document.createElement('i');
            icon.className = 'fas fa-chevron-up sort-icon sort-icon-inactive';
            header.appendChild(icon);
        }
        
        // Remove all icon classes first
        icon.classList.remove('fa-chevron-up', 'fa-chevron-down', 'sort-icon-active', 'sort-icon-inactive');
        
        if (column === sortColumn && sortColumn) {
            // Active column
            icon.classList.add('sort-icon-active');
            icon.style.opacity = '1';
            
            if (sortDirection.toLowerCase() === 'asc') {
                icon.classList.add('fa-chevron-up');
                icon.classList.remove('fa-chevron-down');
                icon.title = 'Sorted Ascending - Click to sort descending';
            } else {
                icon.classList.add('fa-chevron-down');
                icon.classList.remove('fa-chevron-up');
                icon.title = 'Sorted Descending - Click to sort ascending';
            }
        } else {
            // Inactive column
            icon.classList.add('fa-chevron-up', 'sort-icon-inactive');
            icon.classList.remove('fa-chevron-down');
            icon.style.opacity = '0.35';
            icon.title = 'Click to sort';
        }
    });
    
    logDebug('info', 'Sort icons updated');
}

// window.LEAVE is already initialized above

// Load the leave request manager script
// Note: leave_request.js stores the manager as window.leaveManager (not leaveRequestManager)
function loadLeaveRequestManager() {
    return new Promise((resolve, reject) => {
        // Check if already loaded (check both variable names for compatibility)
        const manager = window.leaveManager || window.leaveRequestManager;
        if (manager) {
            logDebug('info', 'LeaveRequestManager already loaded');
            // Ensure both variable names are set
            if (!window.leaveRequestManager) {
                window.leaveRequestManager = manager;
            }
            if (!window.leaveManager) {
                window.leaveManager = manager;
            }
            resolve();
            return;
        }
        
        // Check if script already exists
        const existingScript = document.querySelector('script[src="../assets/js/leave_request.js"]');
        if (existingScript) {
            logDebug('warning', 'Script tag already exists, waiting for load...');
            // Wait for manager to be available
            const checkInterval = setInterval(() => {
                const manager = window.leaveManager || window.leaveRequestManager;
                if (manager) {
                    clearInterval(checkInterval);
                    logDebug('success', 'LeaveRequestManager loaded');
                    // Ensure both variable names are set
                    if (!window.leaveRequestManager) {
                        window.leaveRequestManager = manager;
                    }
                    if (!window.leaveManager) {
                        window.leaveManager = manager;
                    }
                    resolve();
                }
            }, 100);
            
            // Timeout after 3 seconds
            setTimeout(() => {
                clearInterval(checkInterval);
                const manager = window.leaveManager || window.leaveRequestManager;
                if (!manager) {
                    // Try to manually initialize if DOMContentLoaded already fired
                    logDebug('warning', 'LeaveRequestManager not auto-initialized, trying manual init...');
                    tryManualInit().then(resolve).catch(reject);
                }
            }, 3000);
            return;
        }
        
        // Check if DOMContentLoaded already fired
        const domReady = document.readyState === 'complete' || document.readyState === 'interactive';
        
        // Load the script
        const script = document.createElement('script');
        script.src = '../assets/js/leave_request.js';
        script.onload = function() {
            logDebug('success', 'leave_request.js script loaded');
            
            // If DOMContentLoaded already fired, manually initialize
            if (domReady) {
                logDebug('info', 'DOMContentLoaded already fired, manually initializing LeaveRequestManager...');
                tryManualInit().then(resolve).catch(reject);
            } else {
                // Wait for DOMContentLoaded to fire
                logDebug('info', 'Waiting for DOMContentLoaded event...');
                const checkInterval = setInterval(() => {
                    const manager = window.leaveManager || window.leaveRequestManager;
                    if (manager) {
                        clearInterval(checkInterval);
                        logDebug('success', 'LeaveRequestManager initialized via DOMContentLoaded');
                        // Ensure both variable names are set
                        if (!window.leaveRequestManager) {
                            window.leaveRequestManager = manager;
                        }
                        if (!window.leaveManager) {
                            window.leaveManager = manager;
                        }
                        resolve();
                    }
                }, 100);
                
                // Timeout after 3 seconds
                setTimeout(() => {
                    clearInterval(checkInterval);
                    const manager = window.leaveManager || window.leaveRequestManager;
                    if (!manager) {
                        logDebug('warning', 'LeaveRequestManager not initialized, trying manual init...');
                        tryManualInit().then(resolve).catch(reject);
                    }
                }, 3000);
            }
        };
        script.onerror = function() {
            logDebug('error', 'Failed to load leave_request.js script');
            reject(new Error('Failed to load script'));
        };
        document.head.appendChild(script);
    });
}

// Try to manually initialize LeaveRequestManager
function tryManualInit() {
    return new Promise((resolve, reject) => {
        logDebug('info', 'Attempting manual initialization of LeaveRequestManager...');
        
        // Check if LeaveRequestManager class is available
        if (typeof LeaveRequestManager === 'undefined') {
            logDebug('error', 'LeaveRequestManager class not found in global scope');
            reject(new Error('LeaveRequestManager class not available'));
            return;
        }
        
        try {
            // Manually create instance - leave_request.js uses window.leaveManager
            const manager = new LeaveRequestManager();
            window.leaveManager = manager;
            window.leaveRequestManager = manager; // Also set for compatibility
            logDebug('success', 'LeaveRequestManager manually initialized');
            resolve();
        } catch (error) {
            logDebug('error', 'Failed to manually initialize LeaveRequestManager: ' + error.message);
            reject(error);
        }
    });
}

// Load LeaveRequestManager after initial setup
function initializeDataLoading() {
    loadLeaveRequestManager()
        .then(() => {
            logDebug('success', 'LeaveRequestManager ready, loading data...');
            // Load data after a short delay to ensure everything is ready
            setTimeout(() => {
                const manager = window.leaveManager || window.leaveRequestManager;
                if (manager) {
                    manager.loadPendingRequests();
                    manager.loadTotalLeaves();
                    logDebug('info', 'Data load initiated');
                } else {
                    logDebug('error', 'LeaveRequestManager not available for data loading');
                }
            }, 500);
        })
        .catch(error => {
            logDebug('error', 'Failed to initialize LeaveRequestManager: ' + error.message);
        });
}

// Call initialization after DOM is ready (in the existing DOMContentLoaded handler)
// This will be called from the main DOMContentLoaded handler below
</script>

<?php require_once '../includes/footer.php'; ?>

