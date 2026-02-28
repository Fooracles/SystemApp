<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = "View Sheet Data";
require_once "../includes/header.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: login.php");
    exit;
}

// Access Control: Admin Only
if(!isAdmin()) {
    if (isManager()) {
        header("location: pages/manager_dashboard.php");
    } elseif (isDoer()) {
        header("location: pages/doer_dashboard.php");
    } else {
        header("location: index.php");
    }
    exit;
}

// Check if sheet_id is provided
if (!isset($_GET['sheet_id'])) {
    $_SESSION['error_message'] = "Sheet ID is required.";
    header("Location: manage_sheets.php");
    exit;
}

$sheet_id = $_GET['sheet_id'];

// Get sheet details
$sheet_query = "SELECT * FROM fms_sheets WHERE id = ?";
$stmt = $conn->prepare($sheet_query);
$stmt->bind_param("i", $sheet_id);
$stmt->execute();
$sheet_result = $stmt->get_result();

if (!$sheet_result || $sheet_result->num_rows === 0) {
    $_SESSION['error_message'] = "Sheet not found.";
    header("Location: manage_sheets.php");
    exit;
}

$sheet = $sheet_result->fetch_assoc();
$google_sheet_id = $sheet['sheet_id'];

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 50;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "SELECT COUNT(*) as total FROM fms_tasks WHERE sheet_id = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("s", $google_sheet_id);
$count_stmt->execute();
$total_count = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_count / $limit);

// Get tasks for this sheet
$tasks_query = "
    SELECT * FROM fms_tasks 
    WHERE sheet_id = ? 
    ORDER BY imported_at DESC 
    LIMIT ? OFFSET ?
";
$tasks_stmt = $conn->prepare($tasks_query);
$tasks_stmt->bind_param("sii", $google_sheet_id, $limit, $offset);
$tasks_stmt->execute();
$tasks_result = $tasks_stmt->get_result();
$tasks = [];

while ($row = $tasks_result->fetch_assoc()) {
    $tasks[] = $row;
}

// Header already included at the top
?>

<style>
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

<script>
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

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2 class="mb-0">
                <i class="fas fa-eye"></i> Sheet Data: <?php echo htmlspecialchars($sheet['label']); ?>
            </h2>
            <small class="text-muted">
                Sheet ID: <?php echo htmlspecialchars($google_sheet_id); ?> | 
                Tab: <?php echo htmlspecialchars($sheet['tab_name']); ?>
            </small>
        </div>
        <div class="col-md-4">
            <div class="text-right">
                <a href="manage_sheets.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Manage Sheets
                </a>
                <a href="fms_task.php" class="btn btn-info">
                    <i class="fas fa-tasks"></i> Back to FMS Tasks
                </a>
                <a href="?refresh_sheet=<?php echo $sheet_id; ?>" class="btn btn-info">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                </a>
            </div>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body py-2">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Showing <?php echo number_format($offset + 1); ?>-<?php echo number_format(min($offset + $limit, $total_count)); ?> 
                        of <?php echo number_format($total_count); ?> tasks
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="text-right">
                <form method="GET" class="form-inline">
                    <input type="hidden" name="sheet_id" value="<?php echo $sheet_id; ?>">
                    <label for="limit" class="mr-2">Show:</label>
                    <select name="limit" id="limit" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                    <span class="text-muted">per page</span>
                </form>
            </div>
        </div>
    </div>
    
    <?php if (!empty($tasks)): ?>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Unique Key</th>
                                <th>Step Name</th>
                                <th>Client Name</th>
                                <th>Planned</th>
                                <th>Actual</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Doer</th>
                                <th>Department</th>
                                <th>Step Code</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['unique_key']); ?></td>
                                    <td>
                                        <?php 
                                        $description = $task['step_name'] ?? 'N/A';
                                        $full_description = htmlspecialchars($description);
                                        $truncated_description = strlen($description) > 50 ? substr($description, 0, 50) . '..' : $description;
                                        ?>
                                        <span class="description-hover" data-full-description="<?php echo htmlspecialchars($full_description); ?>">
                                            <?php echo htmlspecialchars($truncated_description); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($task['planned']); ?></td>
                                    <td><?php echo htmlspecialchars($task['actual']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo strtolower($task['status']) === 'completed' ? 'success' : 
                                                (strtolower($task['status']) === 'pending' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo htmlspecialchars($task['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php 
                                        $duration_display = "N/A";
                                        if (!empty($task['duration'])) {
                                            // For FMS tasks, assume duration is already in HH:MM:SS string format
                                            $duration_display = htmlspecialchars($task['duration']); 
                                        }
                                        echo $duration_display;
                                    ?></td>
                                    <td><?php echo htmlspecialchars($task['doer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($task['department']); ?></td>
                                    <td><?php echo htmlspecialchars($task['step_code']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Sheet data pagination" class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?sheet_id=<?php echo $sheet_id; ?>&page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?sheet_id=<?php echo $sheet_id; ?>&page=<?php echo $i; ?>&limit=<?php echo $limit; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?sheet_id=<?php echo $sheet_id; ?>&page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No data found for this sheet. 
            <a href="?refresh_sheet=<?php echo $sheet_id; ?>" class="alert-link">Click here to refresh the data</a>.
        </div>
    <?php endif; ?>
</div>

<?php
// Include the universal footer
require_once "../includes/footer.php";
?>
