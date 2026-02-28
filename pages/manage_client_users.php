<?php
// Include required files first (before any output)
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Check if the user is logged in (before including header.php)
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Only Admin and Manager can access this page
if(!isAdmin() && !isManager()) {
    header("location: doer_dashboard.php");
    exit;
}

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if($client_id <= 0) {
    header("location: manage_users.php?section=clients");
    exit;
}

// Get client information
$client_sql = "SELECT u.*, d.name as department_name, COALESCE(u.Status, 'Active') as Status
               FROM users u 
               LEFT JOIN departments d ON u.department_id = d.id 
               WHERE u.id = ? AND u.user_type = 'client'";
$client_stmt = mysqli_prepare($conn, $client_sql);
$client = null;
if($client_stmt) {
    mysqli_stmt_bind_param($client_stmt, "i", $client_id);
    mysqli_stmt_execute($client_stmt);
    $client_result = mysqli_stmt_get_result($client_stmt);
    $client = mysqli_fetch_assoc($client_result);
    mysqli_stmt_close($client_stmt);
}

if(!$client) {
    header("location: manage_users.php?section=clients");
    exit;
}

// Now include header.php (after all redirects are done)
$page_title = "Manage Client Users";
require_once "../includes/header.php";

// Check if Manager has access to this client
$is_admin = isAdmin();
$is_manager = isManager();
if($is_manager && !$is_admin) {
    $current_manager_id = $_SESSION['id'];
    // For now, allow all managers to view all clients
    // Later we can add client-manager assignment logic
}

// Process delete user request
if(isset($_POST['delete_user']) && !empty($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    
    // Check if user belongs to this client
    $check_user_sql = "SELECT id FROM users WHERE id = ? AND manager_id = ?";
    $check_user_stmt = mysqli_prepare($conn, $check_user_sql);
    if($check_user_stmt) {
        mysqli_stmt_bind_param($check_user_stmt, "ii", $user_id, $client_id);
        mysqli_stmt_execute($check_user_stmt);
        $check_user_result = mysqli_stmt_get_result($check_user_stmt);
        if(mysqli_num_rows($check_user_result) > 0) {
            // Delete the user
            $delete_sql = "DELETE FROM users WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            if($delete_stmt) {
                mysqli_stmt_bind_param($delete_stmt, "i", $user_id);
                if(mysqli_stmt_execute($delete_stmt)) {
                    $success_msg = "User deleted successfully!";
                } else {
                    $error_msg = "Something went wrong. Please try again later.";
                }
                mysqli_stmt_close($delete_stmt);
            }
        } else {
            $error_msg = "User not found or does not belong to this client account.";
        }
        mysqli_stmt_close($check_user_stmt);
    }
}

// Process add/edit user request
$edit_id = null;
$username = $name = $email = $password = $confirm_password = $department_id = $user_type = $joining_date = $date_of_birth = "";
$username_err = $name_err = $email_err = $password_err = $confirm_password_err = $department_err = $user_type_err = $joining_date_err = $date_of_birth_err = "";

if(isset($_POST['edit_user']) && !empty($_POST['user_id'])) {
    $edit_user_id = $_POST['user_id'];
    $get_user_sql = "SELECT * FROM users WHERE id = ? AND manager_id = ?";
    $get_user_stmt = mysqli_prepare($conn, $get_user_sql);
    if($get_user_stmt) {
        mysqli_stmt_bind_param($get_user_stmt, "ii", $edit_user_id, $client_id);
        mysqli_stmt_execute($get_user_stmt);
        $get_user_result = mysqli_stmt_get_result($get_user_stmt);
        if($user_row = mysqli_fetch_assoc($get_user_result)) {
            $edit_id = $user_row['id'];
            $username = $user_row['username'];
            $name = $user_row['name'];
            $email = $user_row['email'];
            // Client Users always have user_type = 'client' and no department
            $joining_date = $user_row['joining_date'] ? $user_row['joining_date'] : '';
            $date_of_birth = $user_row['date_of_birth'] ? $user_row['date_of_birth'] : '';
        }
        mysqli_stmt_close($get_user_stmt);
    }
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_user'])) {
    // Log: Start of client_user creation/update process
    $current_user_id = isset($_SESSION['id']) ? $_SESSION['id'] : 'unknown';
    $current_username = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';
    $is_edit = isset($_POST['edit_id']) ? 'UPDATE' : 'CREATE';
    log_activity("CLIENT_USER_FLOW [{$is_edit}]: Process started by user ID {$current_user_id} ({$current_username}) for client_id {$client_id}");
    
    // Validate username
    if(empty(trim($_POST["username"]))) {
        if(!isset($_POST['edit_id'])) {
            $username_err = "Please enter a username.";
        }
    } else {
        $username = trim($_POST["username"]);
        $check_username = true;
        if(isset($_POST['edit_id'])) {
            $sql = "SELECT username FROM users WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $_POST['edit_id']);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $current_user = mysqli_fetch_assoc($result);
                if($current_user && isset($current_user['username']) && $current_user['username'] === $username) {
                    $check_username = false;
                }
                mysqli_stmt_close($stmt);
            }
        }
        
        if($check_username) {
            $sql = "SELECT id FROM users WHERE BINARY username = ?";
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) > 0) {
                    $username_err = "This username is already taken.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Validate name
    if(empty(trim($_POST["name"]))) {
        if(!isset($_POST['edit_id'])) {
            $name_err = "Please enter a name.";
        }
    } else {
        $name = trim($_POST["name"]);
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        if(!isset($_POST['edit_id'])) {
            $email_err = "Please enter an email.";
        }
    } else {
        $email = trim($_POST["email"]);
        $check_email = true;
        if(isset($_POST['edit_id'])) {
            $sql = "SELECT email FROM users WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $_POST['edit_id']);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $current_user = mysqli_fetch_assoc($result);
                if($current_user && isset($current_user['email']) && $current_user['email'] == $email) {
                    $check_email = false;
                }
                mysqli_stmt_close($stmt);
            }
        }
        
        if($check_email) {
            $sql = "SELECT id FROM users WHERE email = ?";
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) > 0) {
                    $email_err = "This email is already registered.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Client Users are automatically set with user_type = 'client' (not 'doer')
    // Department is not required for Client Users
    $user_type = 'client'; // Always set to 'client' for Client Users
    $department_id = null; // No department for Client Users
    
    // Validate password
    if(!isset($_POST['edit_id']) || !empty($_POST["password"])) {
        if(empty(trim($_POST["password"]))) {
            $password_err = "Please enter a password.";     
        } elseif(strlen(trim($_POST["password"])) < 6) {
            $password_err = "Password must have at least 6 characters.";
        } else {
            $password = trim($_POST["password"]);
        }
        
        if(empty(trim($_POST["confirm_password"]))) {
            $confirm_password_err = "Please confirm password.";     
        } else {
            $confirm_password = trim($_POST["confirm_password"]);
            if(empty($password_err) && ($password != $confirm_password)) {
                $confirm_password_err = "Password did not match.";
            }
        }
    }
    
    // Validate dates
    $joining_date_db = null;
    if(!empty($_POST["joining_date"])) {
        $joining_date = $_POST["joining_date"];
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $joining_date)) {
            $date = DateTime::createFromFormat('Y-m-d', $joining_date);
            if($date && $date->format('Y-m-d') === $joining_date && $joining_date !== '1970-01-01' && $joining_date !== '0000-00-00') {
                $joining_date_db = $joining_date;
            }
        }
    }
    
    $date_of_birth_db = null;
    if(!empty($_POST["date_of_birth"])) {
        $date_of_birth = $_POST["date_of_birth"];
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
            $date = DateTime::createFromFormat('Y-m-d', $date_of_birth);
            if($date && $date->format('Y-m-d') === $date_of_birth && $date_of_birth !== '1970-01-01' && $date_of_birth !== '0000-00-00') {
                if($date_of_birth <= date('Y-m-d')) {
                    $date_of_birth_db = $date_of_birth;
                }
            }
        }
    }
    
    // Insert or update if no errors
    // Client Users: user_type = 'client', department_id = NULL
    if(empty($username_err) && empty($name_err) && empty($email_err) && 
       (isset($_POST['edit_id']) && empty($_POST["password"]) || empty($password_err) && empty($confirm_password_err))) {
        
        // Log: Validation passed
        log_activity("CLIENT_USER_FLOW [{$is_edit}]: Validation passed for username '{$username}', email '{$email}', client_id {$client_id}");
        
        if(isset($_POST['edit_id'])) {
            // Update existing user
            // Client Users: user_type = 'client', department_id = NULL
            if(!empty($_POST["password"])) {
                $sql = "UPDATE users SET username = ?, name = ?, email = ?, department_id = NULL, user_type = 'client', 
                        joining_date = ?, date_of_birth = ?, password = ?, manager_id = ? 
                        WHERE id = ? AND manager_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                // 9 parameters: username(s), name(s), email(s), joining_date(s), date_of_birth(s), password(s), manager_id(i), id(i), manager_id(i)
                mysqli_stmt_bind_param($stmt, "ssssssiii", $username, $name, $email, 
                                      $joining_date_db, $date_of_birth_db, $param_password, $client_id, $_POST['edit_id'], $client_id);
            } else {
                $sql = "UPDATE users SET username = ?, name = ?, email = ?, department_id = NULL, user_type = 'client', 
                        joining_date = ?, date_of_birth = ?, manager_id = ? 
                        WHERE id = ? AND manager_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                // 8 parameters: username(s), name(s), email(s), joining_date(s), date_of_birth(s), manager_id(i), id(i), manager_id(i)
                mysqli_stmt_bind_param($stmt, "ssssssii", $username, $name, $email, 
                                      $joining_date_db, $date_of_birth_db, $client_id, $_POST['edit_id'], $client_id);
            }
            
            if($stmt) {
                // Log: Before executing UPDATE query
                log_activity("CLIENT_USER_FLOW [UPDATE]: Executing UPDATE query for user_id {$_POST['edit_id']}, username '{$username}', client_id {$client_id}");
                
                if(mysqli_stmt_execute($stmt)) {
                    $success_msg = "User updated successfully!";
                    log_activity("CLIENT_USER_FLOW [UPDATE]: Successfully updated client_user with user_id {$_POST['edit_id']}, username '{$username}', email '{$email}', client_id {$client_id}");
                    $username = $name = $email = $password = $confirm_password = $department_id = $user_type = $joining_date = $date_of_birth = "";
                    unset($edit_id);
                } else {
                    $error_msg = "Something went wrong. Please try again later.";
                    $error_detail = mysqli_stmt_error($stmt);
                    log_activity("CLIENT_USER_FLOW [UPDATE]: Failed to update client_user with user_id {$_POST['edit_id']}, username '{$username}', client_id {$client_id}. Error: {$error_detail}");
                }
                mysqli_stmt_close($stmt);
            } else {
                log_activity("CLIENT_USER_FLOW [UPDATE]: Failed to prepare UPDATE statement for user_id {$_POST['edit_id']}, client_id {$client_id}");
            }
        } else {
            // Create new user
            // Client Users: user_type = 'client', department_id = NULL
            // Log: Before creating new client_user
            log_activity("CLIENT_USER_FLOW [CREATE]: Preparing to create new client_user - username '{$username}', name '{$name}', email '{$email}', client_id {$client_id}, joining_date: " . ($joining_date_db ?? 'NULL') . ", date_of_birth: " . ($date_of_birth_db ?? 'NULL'));
            
            $sql = "INSERT INTO users (username, name, email, password, department_id, user_type, manager_id, joining_date, date_of_birth) 
                    VALUES (?, ?, ?, ?, NULL, 'client', ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if($stmt) {
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                // 7 parameters: username(s), name(s), email(s), password(s), manager_id(i), joining_date(s), date_of_birth(s)
                mysqli_stmt_bind_param($stmt, "ssssiss", $username, $name, $email, $param_password, 
                                      $client_id, $joining_date_db, $date_of_birth_db);
                
                // Log: Before executing INSERT query
                log_activity("CLIENT_USER_FLOW [CREATE]: Executing INSERT query for username '{$username}', email '{$email}', client_id {$client_id}");
                
                if(mysqli_stmt_execute($stmt)) {
                    $new_user_id = mysqli_insert_id($conn);
                    $success_msg = "User created successfully!";
                    log_activity("CLIENT_USER_FLOW [CREATE]: Successfully created client_user with user_id {$new_user_id}, username '{$username}', email '{$email}', name '{$name}', client_id {$client_id}, joining_date: " . ($joining_date_db ?? 'NULL') . ", date_of_birth: " . ($date_of_birth_db ?? 'NULL'));
                    $username = $name = $email = $password = $confirm_password = "";
                } else {
                    $error_msg = "Something went wrong. Please try again later.";
                    $error_detail = mysqli_stmt_error($stmt);
                    log_activity("CLIENT_USER_FLOW [CREATE]: Failed to create client_user - username '{$username}', email '{$email}', client_id {$client_id}. Error: {$error_detail}");
                }
                mysqli_stmt_close($stmt);
            } else {
                log_activity("CLIENT_USER_FLOW [CREATE]: Failed to prepare INSERT statement for username '{$username}', email '{$email}', client_id {$client_id}. Error: " . mysqli_error($conn));
            }
        }
    } else {
        // Log: Validation failed
        $validation_errors = [];
        if(!empty($username_err)) $validation_errors[] = "username: {$username_err}";
        if(!empty($name_err)) $validation_errors[] = "name: {$name_err}";
        if(!empty($email_err)) $validation_errors[] = "email: {$email_err}";
        if(!empty($password_err)) $validation_errors[] = "password: {$password_err}";
        if(!empty($confirm_password_err)) $validation_errors[] = "confirm_password: {$confirm_password_err}";
        $error_summary = !empty($validation_errors) ? implode(", ", $validation_errors) : "unknown validation error";
        log_activity("CLIENT_USER_FLOW [{$is_edit}]: Validation failed for client_id {$client_id}. Errors: {$error_summary}");
    }
}

// Get departments
$departments = array();
$dept_sql = "SELECT id, name FROM departments ORDER BY name";
$dept_result = mysqli_query($conn, $dept_sql);
if($dept_result) {
    while($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row;
    }
}

// Get users under this client
// Client Users have user_type = 'client' and manager_id pointing to the Client Account
$users_sql = "SELECT u.*, d.name as department_name, COALESCE(u.Status, 'Active') as Status 
              FROM users u 
              LEFT JOIN departments d ON u.department_id = d.id 
              WHERE u.manager_id = ? AND u.user_type = 'client'
              ORDER BY u.name";
$users_stmt = mysqli_prepare($conn, $users_sql);
$users = array();
if($users_stmt) {
    mysqli_stmt_bind_param($users_stmt, "i", $client_id);
    mysqli_stmt_execute($users_stmt);
    $users_result = mysqli_stmt_get_result($users_stmt);
    while($row = mysqli_fetch_assoc($users_result)) {
        $users[] = $row;
    }
    mysqli_stmt_close($users_stmt);
}
?>

<div class="content-area">
    <div class="container mt-4">
        <!-- Back to Clients Button (Above Heading) -->
        <div class="mb-3">
            <a href="manage_users.php?section=clients" class="btn btn-secondary" title="Back to Clients">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-0"><?php echo htmlspecialchars($page_title); ?></h2>
                <p class="text-muted mb-0">Client: <strong><?php echo htmlspecialchars($client['name']); ?></strong></p>
            </div>
            <button type="button" class="btn btn-primary" onclick="openNewClientUserModal()" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border: none; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);">
                <i class="fas fa-plus"></i> New Client User
            </button>
        </div>
        
        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error_msg)): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
        <?php endif; ?>
        
        <!-- Users Table -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Users Under This Client Account (<?php echo count($users); ?>)</h4>
            </div>
            <div class="card-body">
                <?php if(empty($users)): ?>
                    <div class="alert alert-info">No users found under this client account.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>On-Boarding Date</th>
                                    <th>Date of Birth</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $user): ?>
                                    <?php
                                    $row_status = ucfirst(strtolower(trim($user['Status'] ?? 'Active')));
                                    if($row_status !== 'Active' && $row_status !== 'Inactive') {
                                        $row_status = 'Active';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo $user['joining_date'] ? date('d-m-Y', strtotime($user['joining_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $user['date_of_birth'] ? date('d-m-Y', strtotime($user['date_of_birth'])) : 'N/A'; ?></td>
                                        <td>
                                            <select class="form-control form-control-sm user-status-dropdown" 
                                                    data-user-id="<?php echo $user['id']; ?>" 
                                                    data-original-status="<?php echo htmlspecialchars($row_status); ?>">
                                                <option value="Active" <?php echo ($row_status == 'Active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="Inactive" <?php echo ($row_status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info edit-user-btn" title="Edit User" 
                                                    data-user-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>"
                                                    data-name="<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>"
                                                    data-email="<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>"
                                                    data-joining-date="<?php echo $user['joining_date'] ? htmlspecialchars($user['joining_date'], ENT_QUOTES) : ''; ?>"
                                                    data-date-of-birth="<?php echo $user['date_of_birth'] ? htmlspecialchars($user['date_of_birth'], ENT_QUOTES) : ''; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="delete_user" value="1">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit button clicks
    const editButtons = document.querySelectorAll('.edit-user-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');
            const name = this.getAttribute('data-name');
            const email = this.getAttribute('data-email');
            const joiningDate = this.getAttribute('data-joining-date');
            const dateOfBirth = this.getAttribute('data-date-of-birth');
            openEditClientUserModal(userId, username, name, email, joiningDate, dateOfBirth);
        });
    });
    
    // Handle user status dropdown changes
    const statusDropdowns = document.querySelectorAll('.user-status-dropdown');
    statusDropdowns.forEach(dropdown => {
        dropdown.addEventListener('change', function() {
            const userId = this.dataset.userId;
            const newStatus = this.value;
            const originalValue = this.dataset.originalStatus;
            
            if (!confirm('Are you sure you want to change the user status to "' + newStatus + '"?')) {
                this.value = originalValue;
                return;
            }
            
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<option>Updating...</option>';
            
            fetch('action_update_user_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=' + userId + '&Status=' + encodeURIComponent(newStatus)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.dataset.originalStatus = newStatus;
                    alert('User status updated successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to update user status'));
                    this.value = originalValue;
                }
            })
            .catch(error => {
                alert('An error occurred while updating user status. Please try again.');
                this.value = originalValue;
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalHTML;
                this.value = newStatus;
            });
        });
    });
});
</script>

<!-- Add New Client User Modal -->
<div class="modal" id="newClientUserModal" tabindex="-1" role="dialog" aria-labelledby="newClientUserModalLabel" aria-hidden="true" data-backdrop="false">
    <div class="modal-dialog modal-lg" role="document" style="margin: 0; position: fixed; top: 50%; left: calc(50vw + 125px); transform: translate(-50%, -50%); max-width: 90%; width: 500px;">
        <div class="modal-content" style="background: #1e293b; border: 1px solid rgba(255, 255, 255, 0.1);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="modal-title text-white" id="newClientUserModalLabel"><i class="fas fa-user-plus"></i> Add New Client User</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="closeNewClientUserModal()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?client_id=<?php echo $client_id; ?>" method="post" id="newClientUserForm">
                    <input type="hidden" name="save_user" value="1">
                    <input type="hidden" name="edit_id" id="editUserId" value="">
                    
                    <!-- Row 1: Username, Full Name (2 fields) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-white">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="modalUsername" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Enter username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="text-white">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="modalName" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Enter full name" required>
                        </div>
                    </div>
                    
                    <!-- Row 2: Email, On-Boarding Date (2 fields) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-white">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="modalEmail" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Enter email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="text-white">On-Boarding Date</label>
                            <input type="date" name="joining_date" id="modalJoiningDate" class="form-control bg-slate-700 text-white border-slate-600">
                        </div>
                    </div>
                    
                    <!-- Row 3: Date of Birth, Password (2 fields) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-white">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="modalDateOfBirth" class="form-control bg-slate-700 text-white border-slate-600">
                        </div>
                        <div class="col-md-6">
                            <label class="text-white" id="passwordLabel">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="modalPassword" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Enter password">
                        </div>
                    </div>
                    
                    <!-- Row 4: Confirm Password (1 field) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-white" id="confirmPasswordLabel">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" id="modalConfirmPassword" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Confirm password">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <button type="button" class="btn btn-primary" onclick="submitClientUserForm();" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border: none;" id="submitClientUserBtn">Create Client User</button>
            </div>
        </div>
    </div>
</div>

<script>
// Modal functions for Add Client User
function openNewClientUserModal() {
    // Reset form for new user
    document.getElementById('newClientUserForm').reset();
    document.getElementById('editUserId').value = '';
    document.getElementById('modalPassword').required = true;
    document.getElementById('modalConfirmPassword').required = true;
    document.getElementById('passwordLabel').innerHTML = 'Password <span class="text-danger">*</span>';
    document.getElementById('confirmPasswordLabel').innerHTML = 'Confirm Password <span class="text-danger">*</span>';
    document.getElementById('submitClientUserBtn').textContent = 'Create Client User';
    document.getElementById('newClientUserModalLabel').innerHTML = '<i class="fas fa-user-plus"></i> Add New Client User';
    
    $('#newClientUserModal').modal({backdrop: false, show: true});
    $('#newClientUserModal').css('display', 'block');
}

function openEditClientUserModal(userId, username, name, email, joiningDate, dateOfBirth) {
    // Fill form with user data
    document.getElementById('editUserId').value = userId || '';
    document.getElementById('modalUsername').value = username || '';
    document.getElementById('modalName').value = name || '';
    document.getElementById('modalEmail').value = email || '';
    document.getElementById('modalJoiningDate').value = joiningDate || '';
    document.getElementById('modalDateOfBirth').value = dateOfBirth || '';
    document.getElementById('modalPassword').value = '';
    document.getElementById('modalConfirmPassword').value = '';
    
    // Make password fields optional for edit
    document.getElementById('modalPassword').required = false;
    document.getElementById('modalConfirmPassword').required = false;
    document.getElementById('passwordLabel').innerHTML = 'New Password (leave blank to keep current)';
    document.getElementById('confirmPasswordLabel').innerHTML = 'Confirm Password';
    document.getElementById('submitClientUserBtn').textContent = 'Update Client User';
    document.getElementById('newClientUserModalLabel').innerHTML = '<i class="fas fa-user-edit"></i> Edit Client User';
    
    $('#newClientUserModal').modal({backdrop: false, show: true});
    $('#newClientUserModal').css('display', 'block');
}

function closeNewClientUserModal() {
    $('#newClientUserModal').modal('hide');
    $('#newClientUserModal').css('display', 'none');
}

function submitClientUserForm() {
    const form = document.getElementById('newClientUserForm');
    const editId = document.getElementById('editUserId').value;
    const password = document.getElementById('modalPassword').value;
    const confirmPassword = document.getElementById('modalConfirmPassword').value;
    
    // Validate password if creating new user or if password is provided
    if (!editId || (editId && password)) {
        if (!password) {
            alert('Please enter a password.');
            return;
        }
        if (password.length < 6) {
            alert('Password must have at least 6 characters.');
            return;
        }
        if (password !== confirmPassword) {
            alert('Password and confirm password do not match.');
            return;
        }
    }
    
    form.submit();
}
</script>

<style>
/* Hide modal backdrop for modal on this page */
.modal-backdrop {
    display: none !important;
}

/* Ensure modal displays without fade animation */
.modal {
    transition: none !important;
}

.modal.show {
    display: block !important;
}
</style>

<?php require_once "../includes/footer.php"; ?>
