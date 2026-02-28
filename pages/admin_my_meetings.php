<?php
// Set page title and include header (which handles session and config)
$page_title = 'My Meetings';
require_once '../includes/header.php';
require_once '../includes/sorting_helpers.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Get user information from session
$user_role = $_SESSION["user_type"] ?? 'doer';
$user_name = $_SESSION["username"] ?? 'User';
$is_admin = isAdmin();

// Get sorting parameters from URL
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : '';
$sort_direction = isset($_GET['dir']) ? $_GET['dir'] : 'default';
?>

<link rel="stylesheet" href="../assets/css/leave_request.css">

<style>
/* Meeting Modal Styles - High z-index */
.meeting-modal {
    z-index: 9999 !important;
    position: fixed !important;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: #1e1e1e;
    color: #fff;
    border-radius: 10px;
    box-shadow: 0 0 25px rgba(0,0,0,0.6);
    padding: 20px;
    width: 90%;
    max-width: 500px;
}

.modal-backdrop {
    z-index: 9998 !important;
    background: rgba(0,0,0,0.5) !important;
    position: fixed !important;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

/* Dark theme button for Book Meeting */
.btn-book-meeting {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.btn-book-meeting:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    color: white;
}

/* Section header with button */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h3 {
    margin: 0;
}

/* Make datetime-local input clickable anywhere */
#scheduledDateTime {
    cursor: pointer;
    position: relative;
}

/* Hide placeholder when input has value or is focused */
#scheduledDateTime:not(:placeholder-shown) ~ #datetimePlaceholder,
#scheduledDateTime:focus ~ #datetimePlaceholder,
#scheduledDateTime:valid ~ #datetimePlaceholder {
    display: none;
}

/* Style datetime-local input to show DD/MM/YYYY format */
#scheduledDateTime::-webkit-datetime-edit-day-field,
#scheduledDateTime::-webkit-datetime-edit-month-field,
#scheduledDateTime::-webkit-datetime-edit-year-field {
    color: #fff;
}

#scheduledDateTime::-webkit-datetime-edit-text {
    color: #888;
}

/* Make the calendar icon cover the entire field for WebKit browsers (Chrome, Edge, Safari) */
#scheduledDateTime::-webkit-calendar-picker-indicator {
    cursor: pointer;
    position: absolute;
    right: 0;
    top: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    z-index: 10;
}

/* Make the text area also clickable */
#scheduledDateTime::-webkit-datetime-edit {
    cursor: pointer;
    width: 100%;
    position: relative;
    z-index: 1;
}

#scheduledDateTime::-webkit-datetime-edit-fields-wrapper {
    cursor: pointer;
}

/* For Firefox - make entire field clickable */
@-moz-document url-prefix() {
    #scheduledDateTime {
        cursor: pointer;
    }
}

/* Badge Color Fixes - Ensure text is always readable with improved colors */
.badge-urgency-high,
.badge.bg-danger {
    background-color: #dc3545 !important;
    color: #fff !important;
    font-weight: 600 !important;
    border: 1px solid #c82333 !important;
}

/* Improved Status Badge Styling - Better Clarity and Visual Consistency */
.badge-urgency-medium,
.badge.bg-warning {
    background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%) !important;
    color: #000 !important;
    font-weight: 600 !important;
    border: 1px solid #e0a800 !important;
    box-shadow: 0 2px 4px rgba(255, 193, 7, 0.3) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    font-size: 0.7rem !important;
}

.badge-urgency-low,
.badge.bg-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
    color: #fff !important;
    font-weight: 600 !important;
    border: 1px solid #138496 !important;
    box-shadow: 0 2px 4px rgba(23, 162, 184, 0.3) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    font-size: 0.7rem !important;
}

.badge.bg-success {
    background: linear-gradient(135deg, #52c41a 0%, #389e0d 100%) !important;
    color: #fff !important;
    font-weight: 600 !important;
    border: 1px solid #389e0d !important;
    box-shadow: 0 2px 4px rgba(82, 196, 26, 0.3) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    font-size: 0.7rem !important;
}

/* Table Layout Fixes - Perfect Alignment */
.meetings-table,
#scheduledMeetingsTable,
#historyMeetingsTable,
#myHistoryMeetingsTable {
    width: 100% !important;
    max-width: 100% !important;
    table-layout: fixed !important;
    border-collapse: collapse !important;
}

/* Increase table container width to use available space */
.table-container {
    width: 100% !important;
    overflow-x: auto !important;
}

.card-body {
    padding: 1.5rem !important;
}

.meetings-table th,
#scheduledMeetingsTable th,
#historyMeetingsTable th,
#myHistoryMeetingsTable th {
    font-weight: 600 !important;
    text-transform: uppercase !important;
    color: #eaeaea !important;
    background-color: #343a40 !important;
    padding: 12px 16px !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    border-bottom: 2px solid #667eea !important;
}

/* Admin side table headers and cells - left aligned */
#scheduledMeetingsTable th,
#historyMeetingsTable th,
#scheduledMeetingsTable td,
#historyMeetingsTable td {
    text-align: left !important;
}

.meetings-table td,
#scheduledMeetingsTable td,
#historyMeetingsTable td,
#myHistoryMeetingsTable td {
    color: #fff !important;
    padding: 12px 16px !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    border-bottom: 1px solid #444 !important;
}

/* Column width adjustments - Agenda column reduced to allow other columns more space */
#scheduledMeetingsTable th:nth-child(2),
#historyMeetingsTable th:nth-child(2) {
    width: 20% !important; /* Reduced from 30% to allow other columns to display fully */
}

/* Also update corresponding td widths for Agenda column */
#scheduledMeetingsTable td:nth-child(2),
#historyMeetingsTable td:nth-child(2) {
    width: 20% !important;
}

/* My History Meetings Table - Agenda column (1st column) reduced */
#myHistoryMeetingsTable th:nth-child(1) {
    width: 20% !important; /* Reduced from 30% to allow other columns to display fully */
}

#myHistoryMeetingsTable th:nth-child(5) {
    width: 30% !important; /* Comment column remains at 30% */
}

/* Also update corresponding td widths */
#myHistoryMeetingsTable td:nth-child(1) {
    width: 20% !important;
}

/* Duration column - increased width to show full text */
#scheduledMeetingsTable th:nth-child(3),
#historyMeetingsTable th:nth-child(3),
#myHistoryMeetingsTable th:nth-child(2) {
    width: 12% !important; /* Increased from 8% to show full "DURATION" text */
}

/* Also update corresponding td widths for Duration column */
#scheduledMeetingsTable td:nth-child(3),
#historyMeetingsTable td:nth-child(3),
#myHistoryMeetingsTable td:nth-child(2) {
    width: 12% !important;
}

/* Status column - increased width to accommodate larger badges with full text */
#scheduledMeetingsTable th:nth-child(5),
#historyMeetingsTable th:nth-child(5),
#myHistoryMeetingsTable th:nth-child(3) {
    width: 14% !important; /* Increased from 10% to ensure badges display full text */
}

/* Also update corresponding td widths for Status column */
#scheduledMeetingsTable td:nth-child(5),
#historyMeetingsTable td:nth-child(5),
#myHistoryMeetingsTable td:nth-child(3) {
    width: 14% !important;
    overflow: visible !important; /* Allow badge to expand if needed */
}

#scheduledMeetingsTable th:nth-child(6) {
    width: 22% !important;
}

/* Allow text wrapping for Agenda and Comment columns */
#scheduledMeetingsTable td:nth-child(2),
#historyMeetingsTable td:nth-child(2),
#myHistoryMeetingsTable td:nth-child(1),
#myHistoryMeetingsTable td:nth-child(5) {
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow: visible !important;
    text-overflow: initial !important;
}

/* Actions column - no ellipsis, allow buttons to display properly */
.meetings-table td:last-child,
#scheduledMeetingsTable td:last-child,
#historyMeetingsTable td:last-child,
#myHistoryMeetingsTable td:last-child {
    overflow: visible !important;
    text-overflow: initial !important;
    white-space: normal !important;
}

/* Badge styling - inline-flex to prevent row expansion - Improved for consistency and better text visibility */
.badge {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 10px 18px !important; /* Increased from 6px 12px to ensure text is fully visible */
    font-size: 0.7rem !important;
    line-height: 1.4 !important; /* Increased from 1.2 for better text spacing */
    white-space: nowrap !important;
    min-width: fit-content !important; /* Ensure badge expands to fit content */
    width: auto !important; /* Allow badge to size based on content */
    border-radius: 4px !important;
    transition: all 0.2s ease !important;
}

/* Hover effect for badges */
.badge:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2) !important;
}

/* Action button styling */
.action-btn,
.btn-sm.btn-primary {
    min-width: 110px !important;
    display: inline-flex !important;
    justify-content: center !important;
    align-items: center !important;
    white-space: nowrap !important;
    padding: 6px 12px !important;
}

/* User View Table - My Meetings History - Left Aligned */
#myHistoryMeetingsTable.meetings-table,
.meetings-history-table {
    width: 100% !important;
    table-layout: fixed !important;
    border-collapse: collapse !important;
}

#myHistoryMeetingsTable.meetings-table th,
.meetings-history-table th {
    text-align: left !important;
    padding: 12px 18px !important;
    vertical-align: middle !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    color: #eaeaea !important;
    background-color: #343a40 !important;
    border-bottom: 2px solid #667eea !important;
}

#myHistoryMeetingsTable.meetings-table td,
.meetings-history-table td {
    text-align: left !important;
    padding: 12px 18px !important;
    vertical-align: middle !important;
    color: #fff !important;
    white-space: nowrap !important;
    overflow: visible !important;
    text-overflow: initial !important;
    border-bottom: 1px solid #444 !important;
}

/* Tooltip styling for table cells */
.meetings-table td[title],
.meetings-table th[title] {
    cursor: help;
    position: relative;
}

.meetings-table td[title]:hover,
.meetings-table th[title]:hover {
    text-decoration: underline;
    text-decoration-style: dotted;
}

/* Sort icon styles */
.sort-icon {
    font-size: 0.55em;
    opacity: 0.4;
    margin-left: 4px;
    display: inline-block;
    transition: opacity 0.2s ease;
    vertical-align: middle;
}

.sortable-header:hover .sort-icon,
.sort-icon.active {
    opacity: 1;
}

.sort-icon.active {
    color: #7b07ff;
    font-weight: bold;
}

.sortable-header {
    cursor: pointer;
    user-select: none;
    transition: opacity 0.2s ease;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sortable-header:hover {
    opacity: 0.8;
}

/* Prevent wrapping in table headers */
.table thead th,
.meetings-table thead th {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.85rem !important;
}

.table thead th a,
.meetings-table thead th a {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    font-size: 0.85rem !important;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">My Meetings</h2>
            
            <?php if ($is_admin): ?>
                <!-- Admin View: Tabs -->
                <div class="tabs-container">
                    <div class="tabs">
                        <div class="tab active" onclick="switchMeetingTab('scheduled')">
                            <i class="fas fa-calendar-check"></i> Scheduled Meetings
                        </div>
                        <div class="tab" onclick="switchMeetingTab('history')">
                            <i class="fas fa-history"></i> Meetings History
                        </div>
                    </div>
                    
                    <!-- Scheduled Meetings Tab Content (Admin) -->
                    <div class="tab-content active" id="scheduledMeetingsTab">
                        <div class="card shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #007bff; color: white;">
                                <h5 class="mb-0">Scheduled Meetings</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-container">
                                    <table class="table table-hover table-sm meetings-table" id="scheduledMeetingsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th title="Name of the person requesting the meeting" style="width: 12%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('scheduled', 'doer_name')" class="text-dark text-decoration-none sortable-header">
                                                        Doer <?php echo getSortIcon('doer_name', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th title="Agenda for the meeting request" style="width: 20%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('scheduled', 'reason')" class="text-dark text-decoration-none sortable-header">
                                                        Agenda <?php echo getSortIcon('reason', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th title="Expected duration of the meeting" style="width: 12%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('scheduled', 'duration')" class="text-dark text-decoration-none sortable-header">
                                                        Duration <?php echo getSortIcon('duration', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th title="Preferred date and time selected by doer/manager while booking" style="width: 18%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('scheduled', 'scheduled_date')" class="text-dark text-decoration-none sortable-header">
                                                        Scheduled Date <?php echo getSortIcon('scheduled_date', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th title="Current status of the meeting" style="width: 14%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('scheduled', 'status')" class="text-dark text-decoration-none sortable-header">
                                                        Status <?php echo getSortIcon('status', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th title="Actions available for this meeting" style="width: 22%;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr id="loadingScheduledRow">
                                                <td colspan="6" class="text-center">
                                                    <div class="spinner-border text-warning" role="status"></div>
                                                    <p class="mt-2">Loading scheduled meetings...</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Meetings History Tab Content (Admin) -->
                    <div class="tab-content" id="meetingsHistoryTab">
                        <div class="card shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #6c757d; color: white;">
                                <h5 class="mb-0">Meetings History</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-container">
                                    <table class="table table-hover table-sm meetings-table" id="historyMeetingsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th title="Name of the person who requested the meeting" style="width: 12%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('history', 'doer_name')" class="text-dark text-decoration-none sortable-header">
                                                        Doer <?php echo getSortIcon('doer_name', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th title="Agenda for the meeting request" style="width: 30%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('history', 'reason')" class="text-dark text-decoration-none sortable-header">
                                                        Agenda <?php echo getSortIcon('reason', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th title="Expected duration of the meeting" style="width: 8%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('history', 'duration')" class="text-dark text-decoration-none sortable-header">
                                                        Duration <?php echo getSortIcon('duration', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th title="Date and time when the meeting was scheduled" style="width: 18%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('history', 'scheduled_date')" class="text-dark text-decoration-none sortable-header">
                                                        Scheduled Date <?php echo getSortIcon('scheduled_date', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th title="Current status of the meeting" style="width: 10%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('history', 'status')" class="text-dark text-decoration-none sortable-header">
                                                        Status <?php echo getSortIcon('status', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                                <th title="Last updated date and time" style="width: 22%;">
                                                    <a href="javascript:void(0)" onclick="sortTable('history', 'updated_at')" class="text-dark text-decoration-none sortable-header">
                                                        Updated On <?php echo getSortIcon('updated_at', $sort_column, $sort_direction); ?>
                                                    </a>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr id="loadingHistoryRow">
                                                <td colspan="6" class="text-center">
                                                    <div class="spinner-border text-secondary" role="status"></div>
                                                    <p class="mt-2">Loading meetings history...</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Non-Admin View: Single Section -->
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #6c757d; color: white;">
                        <h5 class="mb-0">Meetings History</h5>
                        <div>
                            <button class="btn btn-book-meeting btn-sm" id="nonAdminBookMeetingBtn" title="Book a Meeting">
                                <i class="fas fa-calendar-plus"></i> Book Meeting
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                                    <table class="table table-hover table-sm meetings-table meetings-history-table" id="myHistoryMeetingsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th title="Agenda for the meeting request" style="width: 20%;">Agenda</th>
                                        <th title="Expected duration of the meeting" style="width: 12%;">Duration</th>
                                        <th title="Current status of the meeting" style="width: 14%;">Status</th>
                                        <th title="Date and time when the meeting is scheduled" style="width: 18%;">Scheduled Date</th>
                                        <th title="Admin comment/reason for scheduling" style="width: 34%;">Comment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="loadingMyHistoryRow">
                                        <td colspan="5" class="text-center">
                                            <div class="spinner-border text-secondary" role="status"></div>
                                            <p class="mt-2">Loading your meetings...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Schedule Meeting Modal (Admin Only) -->
<?php if ($is_admin): ?>
<div id="scheduleMeetingModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content meeting-modal">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Schedule Meeting</h3>
            <span class="close-schedule-modal" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
        </div>
        <form id="scheduleMeetingForm">
            <input type="hidden" id="scheduleMeetingId" name="meeting_id">
            <div class="form-group mb-3">
                <label for="scheduledDateTime" style="color: #fff;">Date & Time <span style="color: red;">*</span></label>
                <div style="position: relative;">
                    <input type="datetime-local" class="form-control" id="scheduledDateTime" name="scheduled_date" required style="background-color: #2a2a2a; color: #fff; border-color: #444; cursor: pointer; position: relative; z-index: 2;" lang="en-GB">
                    <span id="datetimePlaceholder" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #888; pointer-events: none; z-index: 1; font-size: 14px; user-select: none;">dd/mm/yyyy --:-- --</span>
                </div>
            </div>
            <div id="currentScheduleInfo" style="display: none; padding: 10px; background-color: #2a2a2a; border-radius: 5px; margin-bottom: 15px; color: #fff;">
                <small><strong>Current Schedule:</strong> <span id="currentScheduleText"></span></small>
            </div>
            <div class="form-group mb-3">
                <label for="scheduleComment" style="color: #fff;">Comment/Reason <span style="color: #888; font-size: 0.85em;">(Optional)</span></label>
                <textarea class="form-control" id="scheduleComment" name="schedule_comment" rows="3" placeholder="Add any comments or reasons for scheduling/re-scheduling this meeting..." style="background-color: #2a2a2a; color: #fff; border-color: #444; resize: vertical;"></textarea>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary close-schedule-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Inject user data for JavaScript
window.MEETING = {
    role: '<?php echo $user_role; ?>',
    isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>,
    name: '<?php echo addslashes($user_name); ?>'
};

// Sorting state
let currentSortColumn = '<?php echo $sort_column; ?>';
let currentSortDirection = '<?php echo $sort_direction; ?>';
let currentTableType = 'scheduled'; // 'scheduled', 'history', or 'my_history'

// Sort table function
function sortTable(tableType, column) {
    currentTableType = tableType;
    
    // Get next sort direction (two-state: asc → desc → asc)
    if (currentSortColumn === column) {
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
    
    // Reload the appropriate table
    if (tableType === 'scheduled') {
        loadScheduledMeetings();
    } else if (tableType === 'history') {
        loadHistoryMeetings();
    } else if (tableType === 'my_history') {
        loadMyHistoryMeetings();
    }
    
    // Update all sort icons
    updateSortIcons();
}

// Update sort icons in headers
function updateSortIcons() {
    document.querySelectorAll('.sortable-header').forEach(header => {
        const link = header.querySelector('a') || header;
        const onclick = link.getAttribute('onclick');
        if (onclick) {
            const match = onclick.match(/sortTable\(['"]([^'"]+)['"],\s*['"]([^'"]+)['"]\)/);
            if (match) {
                const tableType = match[1];
                const column = match[2];
                
                // Find or create icon
                let icon = header.querySelector('.sort-icon');
                if (!icon) {
                    icon = document.createElement('i');
                    icon.className = 'fas fa-chevron-up sort-icon sort-icon-inactive';
                    header.appendChild(icon);
                }
                
                // Remove all icon classes first
                icon.classList.remove('fa-chevron-up', 'fa-chevron-down', 'sort-icon-active', 'sort-icon-inactive');
                
                if (currentSortColumn === column && currentTableType === tableType) {
                    // Active column
                    icon.classList.add('sort-icon-active');
                    icon.style.opacity = '1';
                    
                    if (currentSortDirection === 'asc') {
                        icon.classList.add('fa-chevron-up');
                        icon.title = 'Sorted Ascending - Click to sort descending';
                    } else {
                        icon.classList.add('fa-chevron-down');
                        icon.title = 'Sorted Descending - Click to sort ascending';
                    }
                } else {
                    // Inactive column
                    icon.classList.add('fa-chevron-up', 'sort-icon-inactive');
                    icon.style.opacity = '0.35';
                    icon.title = 'Click to sort';
                }
            }
        }
    });
}

// Tab switching function (Admin only)
function switchMeetingTab(tabName) {
    if (!window.MEETING.isAdmin) return;
    
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    if (tabName === 'scheduled') {
        document.getElementById('scheduledMeetingsTab').classList.add('active');
        document.querySelectorAll('.tab')[0].classList.add('active');
        loadScheduledMeetings();
    } else if (tabName === 'history') {
        document.getElementById('meetingsHistoryTab').classList.add('active');
        document.querySelectorAll('.tab')[1].classList.add('active');
        loadHistoryMeetings();
    }
}

// Load scheduled meetings (Admin only) - Includes both Pending and Scheduled (future/today only)
function loadScheduledMeetings() {
    if (!window.MEETING.isAdmin) return;
    
    const tbody = document.querySelector('#scheduledMeetingsTable tbody');
    if (!tbody) {
        return;
    }
    tbody.innerHTML = '<tr id="loadingScheduledRow"><td colspan="6" class="text-center"><div class="spinner-border text-warning" role="status"></div><p class="mt-2">Loading...</p></td></tr>';
    
    fetch('../ajax/meeting_handler.php?action=get_scheduled_meetings')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid response from server');
                }
            });
        })
        .then(data => {
            if (data.success) {
                tbody.innerHTML = '';
                if (data.meetings.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No scheduled meetings</td></tr>';
                } else {
                    data.meetings.forEach(meeting => {
                        const row = document.createElement('tr');
                        // Format dates without timezone conversion
                        const createdDate = formatDateTimeString(meeting.created_at);
                        const isScheduled = meeting.status === 'Scheduled';
                        const isPending = meeting.status === 'Pending';
                        const isApproved = meeting.status === 'Approved';
                        let statusBadge = 'bg-warning';
                        if (isScheduled) statusBadge = 'bg-info';
                        else if (isApproved) statusBadge = 'bg-success';
                        else if (meeting.status === 'Completed') statusBadge = 'bg-success';
                        
                        // Display logic for Scheduled Date column:
                        // - For Scheduled/Approved: Show scheduled_date (finalized date)
                        // - For Pending: Show preferred_date + preferred_time (original booking)
                        let scheduledDateTime = 'N/A';
                        if (isScheduled || isApproved) {
                            // For scheduled/approved meetings, show the finalized scheduled_date
                            if (meeting.scheduled_date) {
                                const dateTimeParts = meeting.scheduled_date.split(' ');
                                if (dateTimeParts.length === 2) {
                                    const datePart = formatDateOnly(dateTimeParts[0]);
                                    const timePart = formatTimeOnly(dateTimeParts[1]);
                                    scheduledDateTime = `${datePart} ${timePart}`;
                                } else {
                                    scheduledDateTime = formatDateUserView(meeting.scheduled_date);
                                }
                            }
                        } else if (isPending) {
                            // For pending meetings, show the original preferred date/time
                            if (meeting.preferred_date) {
                                const datePart = formatDateOnly(meeting.preferred_date);
                                let timePart = '';
                                if (meeting.preferred_time) {
                                    timePart = formatTimeOnly(meeting.preferred_time);
                                } else {
                                    timePart = '09:00 AM'; // Default time
                                }
                                scheduledDateTime = `${datePart} ${timePart}`;
                            }
                        }
                        
                        // Build action buttons
                        let actionButtons = '';
                        if (isPending) {
                            // Show Approve button (small square, green tick) for pending meetings
                            actionButtons += `<button class="btn btn-sm btn-success" onclick="approveMeeting(${meeting.id})" title="Approve this meeting request" style="width: 36px; height: 36px; border-radius: 6px; padding: 0; min-width: 36px !important; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px;">
                                <i class="fas fa-check" style="color: #fff; font-size: 14px;"></i>
                            </button>`;
                        }
                        // Always show Re-schedule button (with text)
                        actionButtons += `<button class="btn btn-sm btn-primary action-btn" onclick="openScheduleModal(${meeting.id}, ${isScheduled ? 'true' : 'false'})" title="Click to re-schedule this meeting">
                            Re-schedule
                        </button>`;
                        
                        row.innerHTML = `
                            <td title="${escapeHtml(meeting.doer_name)}">${escapeHtml(meeting.doer_name)}</td>
                            <td title="${escapeHtml(meeting.reason)}">${escapeHtml(meeting.reason)}</td>
                            <td title="${formatDuration(meeting.duration)}">${formatDuration(meeting.duration)}</td>
                            <td title="${scheduledDateTime}">${scheduledDateTime}</td>
                            <td title="${escapeHtml(meeting.status)}"><span class="badge ${statusBadge}">${escapeHtml(meeting.status)}</span></td>
                            <td>
                                ${actionButtons}
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading data: ' + (data.error || 'Unknown error') + '</td></tr>';
            }
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading data: ' + error.message + '</td></tr>';
        });
}

// Load history meetings (Admin - all meetings)
function loadHistoryMeetings() {
    if (!window.MEETING.isAdmin) return;
    
    const tbody = document.querySelector('#historyMeetingsTable tbody');
    if (!tbody) {
        return;
    }
    tbody.innerHTML = '<tr id="loadingHistoryRow"><td colspan="6" class="text-center"><div class="spinner-border text-secondary" role="status"></div><p class="mt-2">Loading...</p></td></tr>';
    
    // Build URL with sort parameters
    let url = '../ajax/meeting_handler.php?action=get_history';
    if (currentSortColumn && currentSortDirection !== 'default' && currentTableType === 'history') {
        url += '&sort=' + encodeURIComponent(currentSortColumn) + '&dir=' + encodeURIComponent(currentSortDirection);
    }
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid response from server');
                }
            });
        })
        .then(data => {
            if (data.success) {
                tbody.innerHTML = '';
                if (data.meetings.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No meetings history</td></tr>';
                } else {
                    data.meetings.forEach(meeting => {
                        const row = document.createElement('tr');
                        // Format dates - show scheduled_date if available (finalized), otherwise preferred_date
                        let scheduledDate = 'N/A';
                        if (meeting.scheduled_date) {
                            // Show finalized scheduled date (from approve or re-schedule)
                            const dateTimeParts = meeting.scheduled_date.split(' ');
                            if (dateTimeParts.length === 2) {
                                const datePart = formatDateOnly(dateTimeParts[0]);
                                const timePart = formatTimeOnly(dateTimeParts[1]);
                                scheduledDate = `${datePart} ${timePart}`;
                            } else {
                                scheduledDate = formatDateUserView(meeting.scheduled_date);
                            }
                        } else if (meeting.preferred_date) {
                            // Fallback to preferred_date if scheduled_date not set (shouldn't happen in history, but safe fallback)
                            const datePart = formatDateOnly(meeting.preferred_date);
                            let timePart = '';
                            if (meeting.preferred_time) {
                                timePart = formatTimeOnly(meeting.preferred_time);
                            } else {
                                timePart = '09:00 AM';
                            }
                            scheduledDate = `${datePart} ${timePart}`;
                        }
                        const updatedDate = formatDateTimeString(meeting.updated_at);
                        const statusBadge = meeting.status === 'Scheduled' ? 'bg-info' : 'bg-success';
                        row.innerHTML = `
                            <td title="${escapeHtml(meeting.doer_name)}">${escapeHtml(meeting.doer_name)}</td>
                            <td title="${escapeHtml(meeting.reason)}">${escapeHtml(meeting.reason)}</td>
                            <td title="${formatDuration(meeting.duration)}">${formatDuration(meeting.duration)}</td>
                            <td title="${scheduledDate}">${scheduledDate}</td>
                            <td title="${escapeHtml(meeting.status)}"><span class="badge ${statusBadge}">${escapeHtml(meeting.status)}</span></td>
                            <td title="${updatedDate}">${updatedDate}</td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading data: ' + (data.error || 'Unknown error') + '</td></tr>';
            }
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading data: ' + error.message + '</td></tr>';
        });
}

// Load my meetings history (Non-Admin - only their meetings)
function loadMyHistoryMeetings() {
    if (window.MEETING.isAdmin) return;
    
    const tbody = document.querySelector('#myHistoryMeetingsTable tbody');
    if (!tbody) {
        return;
    }
    tbody.innerHTML = '<tr id="loadingMyHistoryRow"><td colspan="5" class="text-center"><div class="spinner-border text-secondary" role="status"></div><p class="mt-2">Loading...</p></td></tr>';
    
    // Build URL with sort parameters
    let url = '../ajax/meeting_handler.php?action=get_my_all';
    if (currentSortColumn && currentSortDirection !== 'default' && currentTableType === 'my_history') {
        url += '&sort=' + encodeURIComponent(currentSortColumn) + '&dir=' + encodeURIComponent(currentSortDirection);
    }
    
    // Get all meetings for current user (pending, scheduled, completed)
    Promise.all([
        fetch(url).then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid response from server');
                }
            });
        })
    ]).then(([data]) => {
        if (data.success) {
            tbody.innerHTML = '';
            if (data.meetings.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No meetings found. Click "Book Meeting" to create one.</td></tr>';
            } else {
                data.meetings.forEach(meeting => {
                    const row = document.createElement('tr');
                    // Format dates - show scheduled_date if available (finalized), otherwise preferred_date for pending
                    let scheduledDate = 'Not scheduled yet';
                    const isPending = meeting.status === 'Pending';
                    const isScheduled = meeting.status === 'Scheduled';
                    
                    if (isScheduled && meeting.scheduled_date) {
                        // For scheduled meetings, show the finalized scheduled_date
                        const dateTimeParts = meeting.scheduled_date.split(' ');
                        if (dateTimeParts.length === 2) {
                            const datePart = formatDateOnly(dateTimeParts[0]);
                            const timePart = formatTimeOnly(dateTimeParts[1]);
                            scheduledDate = `${datePart} ${timePart}`;
                        } else {
                            scheduledDate = formatDateUserView(meeting.scheduled_date);
                        }
                    } else if (isPending && meeting.preferred_date) {
                        // For pending meetings, show the original preferred date/time
                        const datePart = formatDateOnly(meeting.preferred_date);
                        let timePart = '';
                        if (meeting.preferred_time) {
                            timePart = formatTimeOnly(meeting.preferred_time);
                        } else {
                            timePart = '09:00 AM'; // Default time
                        }
                        scheduledDate = `${datePart} ${timePart}`;
                    } else if (meeting.scheduled_date) {
                        // Fallback: show scheduled_date if available
                        const dateTimeParts = meeting.scheduled_date.split(' ');
                        if (dateTimeParts.length === 2) {
                            const datePart = formatDateOnly(dateTimeParts[0]);
                            const timePart = formatTimeOnly(dateTimeParts[1]);
                            scheduledDate = `${datePart} ${timePart}`;
                        } else {
                            scheduledDate = formatDateUserView(meeting.scheduled_date);
                        }
                    }
                    const statusBadge = meeting.status === 'Pending' ? 'bg-warning' : 
                                      meeting.status === 'Scheduled' ? 'bg-info' : 'bg-success';
                    const scheduleComment = meeting.schedule_comment ? escapeHtml(meeting.schedule_comment) : '-';
                    row.innerHTML = `
                        <td title="${escapeHtml(meeting.reason)}">${escapeHtml(meeting.reason)}</td>
                        <td title="${formatDuration(meeting.duration)}">${formatDuration(meeting.duration)}</td>
                        <td title="${escapeHtml(meeting.status)}"><span class="badge ${statusBadge}">${escapeHtml(meeting.status)}</span></td>
                        <td title="${scheduledDate}">${scheduledDate}</td>
                        <td title="${scheduleComment}" style="word-wrap: break-word;">${scheduleComment}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading data</td></tr>';
        }
    }).catch(error => {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading data</td></tr>';
    });
}

// Open schedule modal (Admin only)
function openScheduleModal(meetingId, isReschedule = false) {
    if (!window.MEETING.isAdmin) return;
    
    document.getElementById('scheduleMeetingId').value = meetingId;
    document.getElementById('scheduleMeetingModal').style.display = 'block';
    
    // Update modal title based on action
    const modalTitle = document.querySelector('#scheduleMeetingModal h3');
    if (modalTitle) {
        modalTitle.textContent = isReschedule ? 'Re-Schedule Meeting' : 'Schedule Meeting';
    }
    
    // Fetch current meeting details if re-scheduling
    const currentScheduleInfo = document.getElementById('currentScheduleInfo');
    const currentScheduleText = document.getElementById('currentScheduleText');
    const dateTimeInput = document.getElementById('scheduledDateTime');
    const datetimePlaceholder = document.getElementById('datetimePlaceholder');
    const scheduleCommentInput = document.getElementById('scheduleComment');
    
    // Clear comment field when opening modal
    if (scheduleCommentInput) {
        scheduleCommentInput.value = '';
    }
    
    // Hide placeholder when input has value
    if (dateTimeInput && datetimePlaceholder) {
        dateTimeInput.addEventListener('input', function() {
            if (this.value) {
                datetimePlaceholder.style.display = 'none';
            } else {
                datetimePlaceholder.style.display = 'block';
            }
        });
        
        dateTimeInput.addEventListener('focus', function() {
            datetimePlaceholder.style.display = 'none';
        });
        
        dateTimeInput.addEventListener('blur', function() {
            if (!this.value) {
                datetimePlaceholder.style.display = 'block';
            }
        });
        
        // Check initial state
        if (dateTimeInput.value) {
            datetimePlaceholder.style.display = 'none';
        }
    }
    
    // Always fetch meeting details to get preferred_date
    fetch(`../ajax/meeting_handler.php?action=get_meeting_details&meeting_id=${meetingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.meeting) {
                let dateToUse = null;
                let timeToUse = '09:00'; // Default time
                
                // Priority: Use preferred_date and preferred_time if available, otherwise use scheduled_date (for re-schedule)
                if (data.meeting.preferred_date) {
                    // preferred_date is in YYYY-MM-DD format
                    dateToUse = data.meeting.preferred_date;
                    // Use preferred_time if available, otherwise default to 09:00
                    if (data.meeting.preferred_time) {
                        // preferred_time is in HH:mm:ss or HH:mm format
                        timeToUse = data.meeting.preferred_time.substring(0, 5); // Get HH:mm
                    }
                } else if (data.meeting.scheduled_date) {
                    // For re-schedule, use existing scheduled_date
                    const scheduledDateStr = data.meeting.scheduled_date;
                    const dateParts = scheduledDateStr.split(' ');
                    if (dateParts.length === 2) {
                        dateToUse = dateParts[0];
                        timeToUse = dateParts[1].substring(0, 5); // HH:mm
                    }
                }
                
                if (dateToUse) {
                    // Pre-fill the input with preferred_date or scheduled_date
                    dateTimeInput.value = dateToUse + 'T' + timeToUse;
                    
                    // Hide placeholder
                    if (datetimePlaceholder) {
                        datetimePlaceholder.style.display = 'none';
                    }
                } else {
                    dateTimeInput.value = '';
                }
                
                // Show current schedule info only for re-schedule
                if (isReschedule && data.meeting.scheduled_date) {
                    const scheduledDateStr = data.meeting.scheduled_date;
                    const dateParts = scheduledDateStr.split(' ');
                    if (dateParts.length === 2) {
                        const datePart = dateParts[0];
                        const timePart = dateParts[1].substring(0, 5); // HH:mm
                        // Format date as DD/MM/YYYY for display
                        const dateFormatted = formatDateOnly(datePart);
                        currentScheduleText.textContent = dateFormatted + ' ' + timePart;
                        currentScheduleInfo.style.display = 'block';
                    } else {
                        currentScheduleText.textContent = scheduledDateStr;
                        currentScheduleInfo.style.display = 'block';
                    }
                } else {
                    currentScheduleInfo.style.display = 'none';
                }
            } else {
                currentScheduleInfo.style.display = 'none';
                dateTimeInput.value = '';
            }
        })
        .catch(() => {
            currentScheduleInfo.style.display = 'none';
            dateTimeInput.value = '';
        });
    
    // Set minimum date to today (in local timezone format)
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    dateTimeInput.min = `${year}-${month}-${day}T${hours}:${minutes}`;
    
    // Make the entire input field clickable to open the picker
    // The CSS already makes the calendar icon cover the entire field
    // For browsers that support showPicker(), we can enhance it
    setTimeout(() => {
        // Add click handler for browsers that support showPicker()
        if (dateTimeInput.showPicker && typeof dateTimeInput.showPicker === 'function') {
            dateTimeInput.addEventListener('click', function(e) {
                // Small delay to ensure the click event completes
                setTimeout(() => {
                    if (this.showPicker) {
                        this.showPicker();
                    }
                }, 10);
            });
        }
        
        // Also trigger on focus for better UX
        dateTimeInput.addEventListener('focus', function() {
            if (this.showPicker && typeof this.showPicker === 'function') {
                setTimeout(() => {
                    this.showPicker();
                }, 10);
            }
        });
    }, 100);
}

// Close schedule modal
if (window.MEETING.isAdmin) {
    document.querySelectorAll('.close-schedule-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('scheduleMeetingModal').style.display = 'none';
            document.getElementById('scheduleMeetingForm').reset();
            // Show placeholder again when form is reset
            const datetimePlaceholder = document.getElementById('datetimePlaceholder');
            if (datetimePlaceholder) {
                datetimePlaceholder.style.display = 'block';
            }
        });
    });
    
    // Modal should NOT close when clicking outside - only via close button
    // Removed click-outside-to-close functionality
}

// Schedule form submission (Admin only)
if (window.MEETING.isAdmin) {
    document.getElementById('scheduleMeetingForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'schedule');
        
        const dateTimeValue = document.getElementById('scheduledDateTime').value;
        // datetime-local returns value in format: YYYY-MM-DDTHH:mm
        // Convert directly to MySQL format YYYY-MM-DD HH:mm:ss without timezone conversion
        const mysqlDateTime = dateTimeValue.replace('T', ' ') + ':00';
        
        formData.set('scheduled_date', mysqlDateTime);
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scheduling...';
        
        fetch('../ajax/meeting_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', data.message);
                document.getElementById('scheduleMeetingModal').style.display = 'none';
                this.reset();
                // Show placeholder again when form is reset
                const datetimePlaceholder = document.getElementById('datetimePlaceholder');
                if (datetimePlaceholder) {
                    datetimePlaceholder.style.display = 'block';
                }
                // Auto-refresh scheduled meetings table
                loadScheduledMeetings();
                // Also refresh history if on that tab
                if (document.getElementById('meetingsHistoryTab').classList.contains('active')) {
                    loadHistoryMeetings();
                }
            } else {
                showToast('error', data.error || 'Failed to schedule meeting');
            }
        })
        .catch(error => {
            showToast('error', 'An error occurred while scheduling the meeting');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
}

// Approve Meeting Function (Admin only)
function approveMeeting(meetingId) {
    if (!window.MEETING.isAdmin) return;
    
    if (!confirm('Are you sure you want to approve this meeting request?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'approve');
    formData.append('meeting_id', meetingId);
    
    fetch('../ajax/meeting_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            // Auto-refresh scheduled meetings table
            loadScheduledMeetings();
        } else {
            showToast('error', data.error || 'Failed to approve meeting');
        }
    })
    .catch(error => {
        showToast('error', 'An error occurred while approving the meeting');
    });
}

// Non-Admin Book Meeting Button
if (!window.MEETING.isAdmin) {
    document.getElementById('nonAdminBookMeetingBtn').addEventListener('click', function() {
        // Use the modal from header
        const modal = document.getElementById('meetingBookModal');
        if (modal) {
            modal.style.display = 'block';
        }
    });
}

// Auto-refresh functionality - meetings will refresh automatically when scheduled
// No refresh buttons needed

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDuration(duration) {
    const parts = duration.split(':');
    const hours = parseInt(parts[0]);
    const minutes = parseInt(parts[1]);
    
    if (hours === 0) {
        return `${minutes} min`;
    } else if (minutes === 0) {
        return `${hours} hr`;
    } else {
        return `${hours} hr ${minutes} min`;
    }
}

function getUrgencyBadgeClass(urgency) {
    if (urgency.includes('High')) {
        return 'badge-urgency-high bg-danger';
    } else if (urgency.includes('Medium') || urgency.includes('Mid')) {
        return 'badge-urgency-medium bg-warning';
    } else {
        return 'badge-urgency-low bg-info';
    }
}

// Get short urgency text (High, Mid, Low)
function getUrgencyShort(urgency) {
    if (!urgency) return 'Low';
    const urgencyLower = urgency.toLowerCase();
    if (urgencyLower.includes('high')) {
        return 'High';
    } else if (urgencyLower.includes('medium') || urgencyLower.includes('mid')) {
        return 'Mid';
    } else {
        return 'Low';
    }
}

// Format datetime string without timezone conversion
// Input: MySQL datetime format "YYYY-MM-DD HH:mm:ss"
// Output: Formatted string "DD/MM/YYYY, HH:mm:ss AM/PM"
function formatDateTimeString(dateTimeStr) {
    if (!dateTimeStr) return 'N/A';
    
    // Parse MySQL datetime format: YYYY-MM-DD HH:mm:ss
    const parts = dateTimeStr.split(' ');
    if (parts.length !== 2) return dateTimeStr;
    
    const datePart = parts[0].split('-');
    const timePart = parts[1].split(':');
    
    if (datePart.length !== 3 || timePart.length < 2) return dateTimeStr;
    
    const year = datePart[0];
    const month = String(parseInt(datePart[1])).padStart(2, '0');
    const day = String(parseInt(datePart[2])).padStart(2, '0');
    let hours = parseInt(timePart[0]);
    const minutes = String(parseInt(timePart[1])).padStart(2, '0');
    const seconds = timePart[2] ? String(parseInt(timePart[2])).padStart(2, '0') : '00';
    
    // Convert to 12-hour format
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 should be 12
    const hoursStr = String(hours).padStart(2, '0');
    
    return `${day}/${month}/${year}, ${hoursStr}:${minutes}:${seconds} ${ampm}`;
}

// Format date only (YYYY-MM-DD to DD/MM/YYYY)
function formatDateOnly(dateStr) {
    if (!dateStr) return 'N/A';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

// Format time only (HH:mm:ss to HH:mm AM/PM)
function formatTimeOnly(timeStr) {
    if (!timeStr) return 'N/A';
    const parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    
    let hours = parseInt(parts[0]);
    const minutes = String(parseInt(parts[1])).padStart(2, '0');
    
    // Convert to 12-hour format
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 should be 12
    const hoursStr = String(hours).padStart(2, '0');
    
    return `${hoursStr}:${minutes} ${ampm}`;
}

// Format date in DD/MM/YYYY HH:mm AM/PM format (for user view)
function formatDateUserView(dateTimeStr) {
    if (!dateTimeStr) return 'N/A';
    
    // Parse MySQL datetime format: YYYY-MM-DD HH:mm:ss
    const parts = dateTimeStr.split(' ');
    if (parts.length !== 2) return dateTimeStr;
    
    const datePart = parts[0].split('-');
    const timePart = parts[1].split(':');
    
    if (datePart.length !== 3 || timePart.length < 2) return dateTimeStr;
    
    // Format: DD/MM/YYYY
    const day = String(parseInt(datePart[2])).padStart(2, '0');
    const month = String(parseInt(datePart[1])).padStart(2, '0');
    const year = datePart[0]; // Full year
    
    // Format time: HH:mm AM/PM
    let hours = parseInt(timePart[0]);
    const minutes = String(parseInt(timePart[1])).padStart(2, '0');
    
    // Convert to 12-hour format
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 should be 12
    const hoursStr = String(hours).padStart(2, '0');
    
    return `${day}/${month}/${year} ${hoursStr}:${minutes} ${ampm}`;
}

function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
    toast.innerHTML = `
        <strong>${type === 'success' ? 'Success' : 'Error'}:</strong> ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close"></button>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// Load initial data when DOM is ready
function initializeMeetingTables() {
    if (window.MEETING && window.MEETING.isAdmin) {
        loadScheduledMeetings();
    } else if (window.MEETING) {
        loadMyHistoryMeetings();
    } else {
    }
}

// Try to load immediately (if DOM is already ready)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMeetingTables);
} else {
    // DOM is already ready
    setTimeout(initializeMeetingTables, 100);
}

// Initialize sort icons on page load
setTimeout(updateSortIcons, 100);
</script>

<?php require_once '../includes/footer.php'; ?>
