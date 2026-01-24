<?php
$page_title = "Manage Forms";
require_once "../includes/header.php";

// Check if the user is logged in and is an admin
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

if(!isAdmin()) {
    header("location: ../index.php");
    exit;
}

// Additional security: Check if user is active and has proper permissions
if(!isset($_SESSION['id']) || !isset($_SESSION['user_type'])) {
    session_destroy();
    header("location: ../login.php");
    exit;
}

// Handle form submissions
$success_msg = "";
$error_msg = "";

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting: Check if user has made too many requests (only for POST requests)
if (!isset($_SESSION['form_requests'])) {
    $_SESSION['form_requests'] = [];
}

$current_time = time();

if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Track this request for rate limiting
    $_SESSION['form_requests'][] = $current_time;
    
    // Count requests in the last minute
    $requests_in_last_minute = 0;
    foreach ($_SESSION['form_requests'] as $timestamp) {
        if ($current_time - $timestamp < 60) {
            $requests_in_last_minute++;
        }
    }
    
    // Remove old requests (older than 1 minute)
    $_SESSION['form_requests'] = array_filter($_SESSION['form_requests'], function($timestamp) use ($current_time) {
        return $current_time - $timestamp < 60;
    });
    
    // Limit to 10 requests per minute
    if ($requests_in_last_minute >= 10) {
        $error_msg = "Too many requests. Please wait a moment before trying again.";
    } else {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_msg = "Invalid request. Please try again.";
        } else {
            $action = $_POST['action'] ?? '';
            
            if($action == 'add_form') {
                $form_name = trim($_POST['form_name']);
                $form_url = trim($_POST['form_url']);
                $visible_for = $_POST['visible_for'];
                $assigned_users = $_POST['assigned_users'] ?? [];
                
                // Input validation
                if(empty($form_name) || empty($form_url)) {
                    $error_msg = "Form name and URL are required.";
                } elseif(strlen($form_name) > 255) {
                    $error_msg = "Form name must be less than 255 characters.";
                } elseif(strlen($form_url) > 500) {
                    $error_msg = "Form URL must be less than 500 characters.";
                } elseif(!filter_var($form_url, FILTER_VALIDATE_URL)) {
                    $error_msg = "Please enter a valid URL.";
                } elseif(!in_array($visible_for, ['admin', 'manager', 'doer', 'all'])) {
                    $error_msg = "Invalid visibility option selected.";
                } else {
                    // Check if form URL already exists
                    $check_sql = "SELECT id FROM forms WHERE form_url = ?";
                    if($check_stmt = mysqli_prepare($conn, $check_sql)) {
                        mysqli_stmt_bind_param($check_stmt, "s", $form_url);
                        mysqli_stmt_execute($check_stmt);
                        $check_result = mysqli_stmt_get_result($check_stmt);
                        
                        if(mysqli_num_rows($check_result) > 0) {
                            $error_msg = "A form with this URL already exists. Please use a different URL.";
                            mysqli_stmt_close($check_stmt);
                        } else {
                            mysqli_stmt_close($check_stmt);
                            
                            // Insert form
                            $sql = "INSERT INTO forms (form_name, form_url, visible_for, created_by) VALUES (?, ?, ?, ?)";
                            if($stmt = mysqli_prepare($conn, $sql)) {
                                mysqli_stmt_bind_param($stmt, "sssi", $form_name, $form_url, $visible_for, $_SESSION['id']);
                                if(mysqli_stmt_execute($stmt)) {
                                    $form_id = mysqli_insert_id($conn);
                                    
                                    // Insert user assignments if not 'all'
                                    if($visible_for != 'all' && !empty($assigned_users)) {
                                        $user_sql = "INSERT INTO form_user_map (form_id, user_id) VALUES (?, ?)";
                                        $user_stmt = mysqli_prepare($conn, $user_sql);
                                        foreach($assigned_users as $user_id) {
                                            mysqli_stmt_bind_param($user_stmt, "ii", $form_id, $user_id);
                                            mysqli_stmt_execute($user_stmt);
                                        }
                                        mysqli_stmt_close($user_stmt);
                                    }
                                    
                                    $success_msg = "Form added successfully!";
                                } else {
                                    $error_msg = "Error adding form. Please try again.";
                                }
                                mysqli_stmt_close($stmt);
                            }
                        }
                    }
                }
            }
            
            if($action == 'edit_form') {
                $form_id = intval($_POST['form_id']);
                $form_name = trim($_POST['form_name']);
                $form_url = trim($_POST['form_url']);
                $visible_for = $_POST['visible_for'];
                $assigned_users = $_POST['assigned_users'] ?? [];
                
                // Input validation
                if(empty($form_name) || empty($form_url)) {
                    $error_msg = "Form name and URL are required.";
                } elseif(strlen($form_name) > 255) {
                    $error_msg = "Form name must be less than 255 characters.";
                } elseif(strlen($form_url) > 500) {
                    $error_msg = "Form URL must be less than 500 characters.";
                } elseif(!filter_var($form_url, FILTER_VALIDATE_URL)) {
                    $error_msg = "Please enter a valid URL.";
                } elseif(!in_array($visible_for, ['admin', 'manager', 'doer', 'all'])) {
                    $error_msg = "Invalid visibility option selected.";
                } else {
                    // Check if user has permission to edit this form
                    $check_sql = "SELECT id FROM forms WHERE id = ? AND created_by = ?";
                    if($check_stmt = mysqli_prepare($conn, $check_sql)) {
                        mysqli_stmt_bind_param($check_stmt, "ii", $form_id, $_SESSION['id']);
                        mysqli_stmt_execute($check_stmt);
                        $result = mysqli_stmt_get_result($check_stmt);
                        
                        if(mysqli_num_rows($result) > 0) {
                            // Check if form URL already exists (excluding current form)
                            $check_url_sql = "SELECT id FROM forms WHERE form_url = ? AND id != ?";
                            if($check_url_stmt = mysqli_prepare($conn, $check_url_sql)) {
                                mysqli_stmt_bind_param($check_url_stmt, "si", $form_url, $form_id);
                                mysqli_stmt_execute($check_url_stmt);
                                $url_result = mysqli_stmt_get_result($check_url_stmt);
                                
                                if(mysqli_num_rows($url_result) > 0) {
                                    $error_msg = "A form with this URL already exists. Please use a different URL.";
                                    mysqli_stmt_close($check_url_stmt);
                                } else {
                                    mysqli_stmt_close($check_url_stmt);
                                    
                                    // Update form
                                    $sql = "UPDATE forms SET form_name = ?, form_url = ?, visible_for = ? WHERE id = ?";
                                    if($stmt = mysqli_prepare($conn, $sql)) {
                                        mysqli_stmt_bind_param($stmt, "sssi", $form_name, $form_url, $visible_for, $form_id);
                                        if(mysqli_stmt_execute($stmt)) {
                                            // Update user assignments
                                            // First, delete existing assignments
                                            $delete_sql = "DELETE FROM form_user_map WHERE form_id = ?";
                                            $delete_stmt = mysqli_prepare($conn, $delete_sql);
                                            mysqli_stmt_bind_param($delete_stmt, "i", $form_id);
                                            mysqli_stmt_execute($delete_stmt);
                                            mysqli_stmt_close($delete_stmt);
                                            
                                            // Insert new assignments if not 'all'
                                            if($visible_for != 'all' && !empty($assigned_users)) {
                                                $user_sql = "INSERT INTO form_user_map (form_id, user_id) VALUES (?, ?)";
                                                $user_stmt = mysqli_prepare($conn, $user_sql);
                                                foreach($assigned_users as $user_id) {
                                                    mysqli_stmt_bind_param($user_stmt, "ii", $form_id, $user_id);
                                                    mysqli_stmt_execute($user_stmt);
                                                }
                                                mysqli_stmt_close($user_stmt);
                                            }
                                            
                                            $success_msg = "Form updated successfully!";
                                        } else {
                                            $error_msg = "Error updating form. Please try again.";
                                        }
                                        mysqli_stmt_close($stmt);
                                    }
                                }
                            }
                        } else {
                            $error_msg = "You don't have permission to edit this form.";
                        }
                        mysqli_stmt_close($check_stmt);
                    }
                }
            }
    
            if($action == 'delete_form') {
                $form_id = intval($_POST['form_id']);
                
                // Verify the form exists and user has permission to delete it
                $check_sql = "SELECT id FROM forms WHERE id = ? AND created_by = ?";
                if($check_stmt = mysqli_prepare($conn, $check_sql)) {
                    mysqli_stmt_bind_param($check_stmt, "ii", $form_id, $_SESSION['id']);
                    mysqli_stmt_execute($check_stmt);
                    $result = mysqli_stmt_get_result($check_stmt);
                    
                    if(mysqli_num_rows($result) > 0) {
                        $sql = "DELETE FROM forms WHERE id = ?";
                        if($stmt = mysqli_prepare($conn, $sql)) {
                            mysqli_stmt_bind_param($stmt, "i", $form_id);
                            if(mysqli_stmt_execute($stmt)) {
                                $success_msg = "Form deleted successfully!";
                            } else {
                                $error_msg = "Error deleting form. Please try again.";
                            }
                            mysqli_stmt_close($stmt);
                        }
                    } else {
                        $error_msg = "You don't have permission to delete this form.";
                    }
                    mysqli_stmt_close($check_stmt);
                }
            }
        }
    }
}

// Fetch all forms with user assignments
$forms = [];
$sql = "SELECT f.*, GROUP_CONCAT(fum.user_id) as assigned_user_ids 
        FROM forms f 
        LEFT JOIN form_user_map fum ON f.id = fum.form_id 
        WHERE f.is_active = 1 
        GROUP BY f.id 
        ORDER BY f.created_at DESC";
$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $row['assigned_user_ids'] = $row['assigned_user_ids'] ? explode(',', $row['assigned_user_ids']) : [];
        $forms[] = $row;
    }
}

// Fetch all users for assignment dropdown
$users = [];
$user_sql = "SELECT id, username, user_type FROM users ORDER BY username";
$user_result = mysqli_query($conn, $user_sql);
if($user_result) {
    while($row = mysqli_fetch_assoc($user_result)) {
        $users[] = $row;
    }
}
?>

<div class="content-area">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Forms</h2>
                </div>

                <?php if(!empty($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_msg; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if(!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error_msg; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Add New Form Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Add New Form</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="addFormForm">
                            <input type="hidden" name="action" value="add_form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="form_name">Form Name *</label>
                                        <input type="text" class="form-control" id="form_name" name="form_name" required autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="form_url">Form URL *</label>
                                        <input type="url" class="form-control" id="form_url" name="form_url" required placeholder="https://example.com/form" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="visible_for">Visible For *</label>
                                        <select class="form-control" id="visible_for" name="visible_for" required>
                                            <option value="all">All Users</option>
                                            <option value="admin">Admin Only</option>
                                            <option value="manager">Manager Only</option>
                                            <option value="doer">Doer Only</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-plus"></i> Add Form
                                        </button>
                                        <button type="reset" class="btn btn-secondary btn-block mt-2">
                                            <i class="fas fa-undo"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="row" id="user_selection_group" style="display: none;">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label>Select Users</label>
                                        <div class="row">
                                            <?php foreach($users as $user): ?>
                                                <div class="col-md-3 user-checkbox-container" data-user-type="<?php echo strtolower($user['user_type']); ?>" style="display: none;">
                                                    <div class="form-check">
                                                        <input class="form-check-input user-checkbox" type="checkbox" name="assigned_users[]" value="<?php echo $user['id']; ?>" id="user_<?php echo $user['id']; ?>" data-user-type="<?php echo strtolower($user['user_type']); ?>">
                                                        <label class="form-check-label" for="user_<?php echo $user['id']; ?>">
                                                            <?php echo htmlspecialchars($user['username']); ?>
                                                            <small class="text-muted">(<?php echo ucfirst($user['user_type']); ?>)</small>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Existing Forms</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($forms)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No forms found. Add your first form using the "Add New Form" button.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th style="width: 25%;">Form Name</th>
                                            <th style="width: 20%;">URL</th>
                                            <th style="width: 15%;">Visible For</th>
                                            <th style="width: 20%;">Assigned Users</th>
                                            <th style="width: 10%;">Created</th>
                                            <th style="width: 10%;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($forms as $form): ?>
                                            <tr data-form-id="<?php echo $form['id']; ?>" data-assigned-user-ids="<?php echo htmlspecialchars(json_encode($form['assigned_user_ids'])); ?>">
                                                <td><strong><?php echo htmlspecialchars($form['form_name']); ?></strong></td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($form['form_url']); ?>" target="_blank" class="text-primary">
                                                        <?php echo htmlspecialchars(substr($form['form_url'], 0, 50)) . (strlen($form['form_url']) > 50 ? '...' : ''); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $form['visible_for'] == 'all' ? 'success' : ($form['visible_for'] == 'admin' ? 'danger' : ($form['visible_for'] == 'manager' ? 'warning' : 'info')); ?>">
                                                        <?php echo ucfirst($form['visible_for']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if($form['visible_for'] == 'all'): ?>
                                                        <span class="text-muted">All Users</span>
                                                    <?php else: ?>
                                                        <?php 
                                                        $assigned_user_names = [];
                                                        foreach($users as $user) {
                                                            if(in_array($user['id'], $form['assigned_user_ids'])) {
                                                                $assigned_user_names[] = $user['username'];
                                                            }
                                                        }
                                                        // If no specific users assigned, show the visibility type name
                                                        if(empty($assigned_user_names)) {
                                                            echo '<span class="text-muted">' . ucfirst($form['visible_for']) . ' users</span>';
                                                        } else {
                                                            echo implode(', ', $assigned_user_names);
                                                        }
                                                        ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($form['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning edit-form-btn" data-form-id="<?php echo $form['id']; ?>" title="Edit Form">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger delete-form-btn" data-form-id="<?php echo $form['id']; ?>" data-form-name="<?php echo htmlspecialchars($form['form_name']); ?>" title="Delete Form">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
$(document).ready(function() {
    console.log("Manage Forms page loaded");
    
    // Show/hide and filter user selection based on visible_for selection
    $('#visible_for').change(function() {
        const selectedType = $(this).val();
        console.log("Visible for changed:", selectedType);
        
        if(selectedType === 'all') {
            $('#user_selection_group').hide();
            $('.user-checkbox').prop('checked', false);
        } else {
            // Filter users by type
            $('.user-checkbox').each(function() {
                const userType = $(this).data('user-type');
                const checkboxContainer = $(this).closest('.col-md-3');
                
                if(userType === selectedType) {
                    checkboxContainer.show();
                } else {
                    checkboxContainer.hide();
                    $(this).prop('checked', false);
                }
            });
            $('#user_selection_group').show();
        }
    });
    
    // Debug form submission
    $('#addFormForm').on('submit', function(e) {
        console.log("Add form submitted");
        console.log("Form name:", $('#form_name').val());
        console.log("Form URL:", $('#form_url').val());
        console.log("Visible for:", $('#visible_for').val());
        const selectedUsers = $('.user-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        console.log("Selected users:", selectedUsers);
        
        // Ensure checkboxes are properly submitted
        if($('#visible_for').val() !== 'all' && selectedUsers.length === 0) {
            // This is okay - means all users of that type will see it
            console.log("No specific users selected - form will be visible to all " + $('#visible_for').val() + " users");
        }
    });
    
    // Form reset functionality
    $('button[type="reset"]').click(function() {
        $('#form_name').val('');
        $('#form_url').val('');
        $('#visible_for').val('all');
        $('#user_selection_group').hide();
        $('.user-checkbox').prop('checked', false);
    });
    
    // Get assigned user IDs for a form (for edit mode)
    function getAssignedUserIds(formId) {
        // This will be populated from the table data
        const row = $(`tr[data-form-id="${formId}"]`);
        if(row.length) {
            return row.data('assigned-user-ids') || [];
        }
        return [];
    }
    
    // Edit form button click - direct edit
    $('.edit-form-btn').click(function() {
        const formId = $(this).data('form-id');
        const row = $(this).closest('tr');
        
        // Get current values
        const formName = row.find('td:eq(0)').text().trim();
        const formUrl = row.find('td:eq(1) a').attr('href');
        const visibleFor = row.find('td:eq(2) .badge').text().toLowerCase();
        
        // Get assigned user IDs from the row data
        let assignedUserIds = [];
        try {
            const assignedUserIdsStr = row.data('assigned-user-ids');
            if(assignedUserIdsStr) {
                assignedUserIds = typeof assignedUserIdsStr === 'string' ? JSON.parse(assignedUserIdsStr) : assignedUserIdsStr;
            }
        } catch(e) {
            console.error('Error parsing assigned user IDs:', e);
        }
        
        // Build user checkboxes HTML based on visible_for type
        let userCheckboxesHtml = '';
        const userTypeMap = {
            'admin': 'admin',
            'manager': 'manager', 
            'doer': 'doer'
        };
        
        <?php foreach($users as $user): ?>
        const user_<?php echo $user['id']; ?> = {
            id: <?php echo $user['id']; ?>,
            username: '<?php echo addslashes($user['username']); ?>',
            userType: '<?php echo strtolower($user['user_type']); ?>'
        };
        <?php endforeach; ?>
        
        const allUsers = [
            <?php foreach($users as $user): ?>
            {id: <?php echo $user['id']; ?>, username: '<?php echo addslashes($user['username']); ?>', userType: '<?php echo strtolower($user['user_type']); ?>'}<?php echo ($user !== end($users)) ? ',' : ''; ?>
            <?php endforeach; ?>
        ];
        
        // Create inline edit form
        const editForm = `
            <form class="edit-form-inline" data-form-id="${formId}">
                <input type="hidden" name="action" value="edit_form">
                <input type="hidden" name="form_id" value="${formId}">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="row">
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" name="form_name" value="${formName}" required>
                    </div>
                    <div class="col-md-3">
                        <input type="url" class="form-control form-control-sm" name="form_url" value="${formUrl}" required>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm edit-visible-for" name="visible_for" required style="min-width: 140px;">
                            <option value="all" ${visibleFor === 'all' ? 'selected' : ''}>All Users</option>
                            <option value="admin" ${visibleFor === 'admin' ? 'selected' : ''}>Admin Only</option>
                            <option value="manager" ${visibleFor === 'manager' ? 'selected' : ''}>Manager Only</option>
                            <option value="doer" ${visibleFor === 'doer' ? 'selected' : ''}>Doer Only</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-success btn-sm save-edit-form">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm cancel-edit">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="row edit-user-selection-group" style="display: ${visibleFor === 'all' ? 'none' : 'block'}; margin-top: 10px;">
                    <div class="col-12">
                        <label style="color: #e0e0e0; margin-bottom: 8px;">Select Users:</label>
                        <div class="row" style="max-height: 150px; overflow-y: auto;">
                            ${allUsers.map(user => {
                                const isVisible = visibleFor !== 'all' && user.userType === visibleFor;
                                const isChecked = assignedUserIds.includes(user.id);
                                return `
                                    <div class="col-md-3 edit-user-checkbox-container" data-user-type="${user.userType}" style="display: ${isVisible ? 'block' : 'none'};">
                                        <div class="form-check">
                                            <input class="form-check-input edit-user-checkbox" type="checkbox" name="assigned_users[]" value="${user.id}" id="edit_user_${user.id}" ${isChecked ? 'checked' : ''} data-user-type="${user.userType}">
                                            <label class="form-check-label" for="edit_user_${user.id}" style="color: #e0e0e0;">
                                                ${user.username}
                                                <small class="text-muted">(${user.userType.charAt(0).toUpperCase() + user.userType.slice(1)})</small>
                                            </label>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                </div>
            </form>
        `;
        
        // Replace row content with edit form
        row.addClass('editing-row');
        row.html(`<td colspan="5">${editForm}</td>`);
        
        // Trigger change to show/hide users based on initial selection
        row.find('.edit-visible-for').trigger('change');
    });
    
    // Handle visible_for change in edit form
    $(document).on('change', '.edit-visible-for', function() {
        const selectedType = $(this).val();
        const container = $(this).closest('.edit-form-inline');
        const userSelectionGroup = container.find('.edit-user-selection-group');
        
        if(selectedType === 'all') {
            userSelectionGroup.hide();
            container.find('.edit-user-checkbox').prop('checked', false);
        } else {
            container.find('.edit-user-checkbox-container').each(function() {
                const userType = $(this).data('user-type');
                if(userType === selectedType) {
                    $(this).show();
                } else {
                    $(this).hide();
                    $(this).find('.edit-user-checkbox').prop('checked', false);
                }
            });
            userSelectionGroup.show();
        }
    });
    
    // Handle AJAX form submission for edit
    $(document).on('click', '.save-edit-form', function(e) {
        e.preventDefault();
        const form = $(this).closest('.edit-form-inline');
        const formId = form.data('form-id');
        const formData = {
            action: 'edit_form',
            form_id: formId,
            form_name: form.find('input[name="form_name"]').val(),
            form_url: form.find('input[name="form_url"]').val(),
            visible_for: form.find('select[name="visible_for"]').val(),
            assigned_users: form.find('.edit-user-checkbox:checked').map(function() {
                return $(this).val();
            }).get(),
            csrf_token: form.find('input[name="csrf_token"]').val()
        };
        
        // Disable button during submission
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            success: function(response) {
                // Reload page to show updated data
                location.reload();
            },
            error: function(xhr, status, error) {
                alert('Error updating form. Please try again.');
                $(this).prop('disabled', false).html('<i class="fas fa-check"></i>');
                location.reload();
            }
        });
    });
    
    // Cancel edit
    $(document).on('click', '.cancel-edit', function() {
        location.reload();
    });
    
    // Delete form button click - direct delete
    $('.delete-form-btn').click(function() {
        const formId = $(this).data('form-id');
        const formName = $(this).data('form-name');
        
        if(confirm(`Are you sure you want to delete the form "${formName}"?\n\nThis action cannot be undone.`)) {
            // Create delete form and submit
            const deleteForm = `
                <form method="POST" style="display: none;">
                    <input type="hidden" name="action" value="delete_form">
                    <input type="hidden" name="form_id" value="${formId}">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                </form>
            `;
            $('body').append(deleteForm);
            $('form[style*="display: none"]').submit();
        }
    });
            // Tooltip hover functionality
            $('.delay-hover').each(function() {
                var fullDelay = $(this).attr('data-full-delay');
                if (fullDelay) {
                    var fullFormatDelay = convertDelayToFullFormat(fullDelay);
                    $(this).attr('title', fullFormatDelay);
                }
            });
            
            $('.description-hover').each(function() {
                var fullDescription = $(this).attr('data-full-description');
                if (fullDescription) {
                    var tooltipDescription = convertDescriptionForTooltip(fullDescription);
                    $(this).attr('title', tooltipDescription);
                }
            });
            
            // Convert abbreviated delay format to full format
            function convertDelayToFullFormat(delay) {
                if (!delay || delay === 'N/A' || delay === 'On Time') {
                    return delay;
                }
                return delay
                    .replace(/(\d+)\s*D\b/g, '$1 days')
                    .replace(/(\d+)\s*d\b/g, '$1 days')
                    .replace(/(\d+)\s*h\b/g, '$1 hours')
                    .replace(/(\d+)\s*m\b/g, '$1 minutes');
            }
            
            // Convert description format for tooltip
            function convertDescriptionForTooltip(description) {
                if (!description || description === 'N/A') {
                    return description;
                }
                return description.replace(/\n/g, '<br>');
            }
});
</script>

<style>
/* Form styling */
.form-control:focus {
    border-color: #007bff !important;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
}

.form-check-label {
    cursor: pointer !important;
}

/* User selection group styling - Dark Theme */
#user_selection_group {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #444;
    border-radius: 4px;
    padding: 10px;
    background-color: #2a2a2a;
    margin-top: 15px;
}

/* User checkbox container styling - Dark Theme */
#user_selection_group .user-checkbox-container {
    margin-bottom: 8px;
}

#user_selection_group .form-check-label {
    color: #e0e0e0 !important;
    cursor: pointer;
}

#user_selection_group .form-check-input {
    background-color: #2a2a2a !important;
    border: 1px solid #555 !important;
}

#user_selection_group .form-check-input:checked {
    background-color: var(--fms-primary, #6366f1) !important;
    border-color: var(--fms-primary, #6366f1) !important;
}

#user_selection_group .form-check-input:hover {
    border-color: var(--fms-primary, #6366f1) !important;
    box-shadow: 0 0 8px rgba(99, 102, 241, 0.3);
}

#user_selection_group label {
    color: #e0e0e0 !important;
    font-weight: 500;
}

#user_selection_group .text-muted {
    color: #999 !important;
}

/* Responsive form layout */
@media (max-width: 768px) {
    .row.align-items-end .col-md-3 {
        margin-bottom: 15px;
    }
    
    .btn-block {
        width: 100%;
    }
}

/* Form alignment */
.row.align-items-end {
    align-items: flex-end;
}

.form-group {
    margin-bottom: 0;
}

/* Action buttons styling */
.edit-form-btn, .delete-form-btn {
    margin-right: 5px;
    margin-bottom: 5px;
}

.edit-form-btn:hover {
    background-color: #e0a800;
    border-color: #d39e00;
}

.delete-form-btn:hover {
    background-color: #c82333;
    border-color: #bd2130;
}

/* Button spacing in table */
td .btn {
    margin: 2px;
}

/* Inline edit form styling - Dark Theme */
tr.editing-row,
tr.editing-row td,
tr:has(.edit-form-inline),
tr:has(.edit-form-inline) td {
    background-color: #2a2a2a !important;
}

.edit-form-inline {
    padding: 10px;
    background-color: #2a2a2a !important;
    border: 1px solid #444 !important;
    border-radius: 4px;
}

.edit-form-inline .form-control-sm,
.edit-form-inline input,
.edit-form-inline select,
.edit-form-inline textarea {
    background-color: #2a2a2a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
}

.edit-form-inline .form-control-sm:focus,
.edit-form-inline input:focus,
.edit-form-inline select:focus,
.edit-form-inline textarea:focus {
    background-color: #1e1e1e !important;
    color: #fff !important;
    border-color: var(--fms-primary, #6366f1) !important;
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25) !important;
    outline: none !important;
}

.edit-form-inline input::placeholder,
.edit-form-inline textarea::placeholder {
    color: #bbb !important;
    opacity: 1;
}

.edit-form-inline select {
    height: auto !important;
    padding: 6px 10px !important;
    overflow: visible !important;
    min-width: 120px !important;
    white-space: nowrap !important;
    text-overflow: clip !important;
}

.edit-form-inline select option {
    background-color: #2a2a2a !important;
    color: #fff !important;
    padding: 8px 12px !important;
    white-space: normal !important;
}

.edit-form-inline .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    margin: 0 2px;
}

/* Edit user selection styling - Dark Theme */
.edit-user-selection-group {
    background-color: #2a2a2a !important;
    padding: 10px;
    border-radius: 4px;
    margin-top: 10px;
}

.edit-user-selection-group label {
    color: #e0e0e0 !important;
    font-weight: 500;
}

.edit-user-checkbox-container {
    margin-bottom: 8px;
}

.edit-user-checkbox-container .form-check-label {
    color: #e0e0e0 !important;
    cursor: pointer;
}

.edit-user-checkbox-container .form-check-input {
    background-color: #2a2a2a !important;
    border: 1px solid #555 !important;
}

.edit-user-checkbox-container .form-check-input:checked {
    background-color: var(--fms-primary, #6366f1) !important;
    border-color: var(--fms-primary, #6366f1) !important;
}

.edit-user-checkbox-container .form-check-input:hover {
    border-color: var(--fms-primary, #6366f1) !important;
    box-shadow: 0 0 8px rgba(99, 102, 241, 0.3);
}

/* Table column styling */
.table th[style*="width: 20%"] {
    max-width: 200px;
}

.table td:nth-child(2) {
    max-width: 200px;
    word-wrap: break-word;
    word-break: break-all;
}

.table td:nth-child(2) a {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Action column styling */
.table th[style*="width: 10%"]:last-child,
.table td:last-child {
    width: 10%;
    min-width: 120px;
    text-align: center;
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
}</style>

<?php require_once "../includes/footer.php"; ?>
