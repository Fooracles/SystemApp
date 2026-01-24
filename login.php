<?php
// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include config first to set session save path
require_once "includes/config.php";
require_once "includes/functions.php";

// Session will be started automatically by functions.php via startSession()
// But we can also explicitly start it here if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is already logged in
if(isLoggedIn()) {
    redirectToDashboard();
}

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";
$reset_message = "";
$reset_code_message = "";
$reset_login_success = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if this is a password reset request
    if(isset($_POST["reset_password"])) {
        // Validate username and email
        if(empty($_POST["reset_username"])) {
            $login_err = "Please enter username for password reset.";
        } else if(empty($_POST["reset_email"])) {
            $login_err = "Please enter email for password reset.";
        } else {
            $reset_username = $_POST["reset_username"];
            $reset_email = $_POST["reset_email"];
            
            // Check if username and email exist with strict case sensitivity
            $sql = "SELECT id FROM users WHERE BINARY username = ? AND email = ?";
            
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ss", $reset_username, $reset_email);
                
                if(mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    
                    if(mysqli_stmt_num_rows($stmt) == 1) {
                        // Check if there's already a pending request
                        $check_sql = "SELECT id FROM password_reset_requests WHERE username = ? AND status = 'pending'";
                        if($check_stmt = mysqli_prepare($conn, $check_sql)) {
                            mysqli_stmt_bind_param($check_stmt, "s", $reset_username);
                            mysqli_stmt_execute($check_stmt);
                            mysqli_stmt_store_result($check_stmt);
                            
                            if(mysqli_stmt_num_rows($check_stmt) > 0) {
                                $reset_message = "You already have a pending password reset request. Please wait for admin approval.";
                            } else {
                                // Insert new password reset request
                                $insert_sql = "INSERT INTO password_reset_requests (username, email) VALUES (?, ?)";
                                if($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                                    mysqli_stmt_bind_param($insert_stmt, "ss", $reset_username, $reset_email);
                                    
                                    if(mysqli_stmt_execute($insert_stmt)) {
                                        $reset_message = "Your request for password reset has been sent to the admin. You will receive a reset code once approved.";
                                    } else {
                                        $login_err = "Oops! Something went wrong. Please try again later.";
                                    }
                                    
                                    mysqli_stmt_close($insert_stmt);
                                }
                            }
                            mysqli_stmt_close($check_stmt);
                        }
                    } else {
                        $login_err = "No account found with that username and email. Check spelling and case.";
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    $login_err = "Oops! Something went wrong. Please try again later.";
                }
            }
        }
    }
    // Regular login process
    else if(isset($_POST["username"]) && isset($_POST["password"])) {
        
        // Check if username is empty
        if(empty(trim($_POST["username"]))) {
            $username_err = "Please enter username.";
        } else {
            $username = trim($_POST["username"]);
        }
        
        // Check if password is empty
        if(empty(trim($_POST["password"]))) {
            $password_err = "Please enter your password.";
        } else {
            $password = trim($_POST["password"]);
        }
        
        // Validate credentials
        if(empty($username_err) && empty($password_err)) {
            
            // ALWAYS try password login first - this allows 6-digit passwords to work
            $password_login_success = false;
            // Include Status in the query to check for deleted/inactive accounts
            // Try multiple approaches for case-sensitive comparison to handle different MySQL configurations
            $user_found = false;
            $id = $db_username = $hashed_password = $user_type = $user_status = null;
            
            // Use BINARY for case-sensitive comparison (works with binary and utf8mb4 character sets)
            $sql = "SELECT id, username, password, user_type, Status FROM users WHERE BINARY username = ?";
            $stmt = null;
            
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $username);
                if(mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    if(mysqli_stmt_num_rows($stmt) == 1) {
                        mysqli_stmt_bind_result($stmt, $id, $db_username, $hashed_password, $user_type, $user_status);
                        mysqli_stmt_fetch($stmt);
                        $user_found = true;
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            
            // If still not found, try case-insensitive (to see if user exists but case is wrong)
            if(!$user_found) {
                $sql = "SELECT id, username, password, user_type, Status FROM users WHERE username = ?";
                if($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "s", $username);
                    if(mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_store_result($stmt);
                        if(mysqli_stmt_num_rows($stmt) == 1) {
                            mysqli_stmt_bind_result($stmt, $id, $db_username, $hashed_password, $user_type, $user_status);
                            mysqli_stmt_fetch($stmt);
                            // User exists but case doesn't match - show case-sensitive error
                            $login_err = "Username case mismatch. Please check your username spelling and case. Found: " . htmlspecialchars($db_username);
                            mysqli_stmt_close($stmt);
                        } else {
                            mysqli_stmt_close($stmt);
                        }
                    }
                }
            }
            
            // If user was found with case-sensitive match, proceed with login
            if($user_found) {
                // Prevent Client Accounts from logging in - they are parent entities without passwords
                // Only Client Users (created under Client Accounts) can log in
                if($hashed_password === null || empty($hashed_password)) {
                    // Check if this is a client account (deleted or inactive)
                    if($user_type === 'client') {
                        $status_normalized = ucfirst(strtolower(trim($user_status ?? 'Active')));
                        if($status_normalized === 'Inactive') {
                            $login_err = "This account has been deleted and cannot log in.";
                        } else {
                            $login_err = "This account cannot log in. Client Accounts are parent entities. Please use a Client User account to log in.";
                        }
                    } else {
                        $login_err = "This account cannot log in. No password is set for this account.";
                    }
                    // Don't proceed with password verification - Client Accounts cannot log in
                } else {
                    // Additional check: Prevent Client Accounts from logging in even if they have passwords
                    // Client Accounts: user_type = 'client', manager_id points to manager/admin (NOT to client account)
                    // Client Users: user_type = 'client', manager_id points to client account, password is hashed
                    $is_client_account = false;
                    if($user_type === 'client') {
                        // Check if this is a client account (not a client user)
                        // Client accounts have manager_id pointing to manager/admin, not to another client account
                        $check_client_account_sql = "SELECT u.manager_id, 
                                                      CASE 
                                                          WHEN u.manager_id IS NULL THEN 1
                                                          WHEN EXISTS (
                                                              SELECT 1 FROM users c 
                                                              WHERE c.id = u.manager_id 
                                                              AND c.user_type = 'client' 
                                                              AND (c.password IS NULL OR c.password = '')
                                                          ) THEN 0
                                                          ELSE 1
                                                      END as is_client_account
                                                      FROM users u 
                                                      WHERE u.id = ?";
                        if($check_client_account_stmt = mysqli_prepare($conn, $check_client_account_sql)) {
                            mysqli_stmt_bind_param($check_client_account_stmt, "i", $id);
                            if(mysqli_stmt_execute($check_client_account_stmt)) {
                                $check_result = mysqli_stmt_get_result($check_client_account_stmt);
                                if($check_row = mysqli_fetch_assoc($check_result)) {
                                    $is_client_account = ($check_row['is_client_account'] == 1);
                                }
                            }
                            mysqli_stmt_close($check_client_account_stmt);
                        }
                        
                        if($is_client_account) {
                            $login_err = "This account cannot log in. Client Accounts are parent entities. Please use a Client User account to log in.";
                            // Don't proceed with password verification - Client Accounts cannot log in
                        }
                    }
                    
                    // For ALL user types (admin, manager, doer, client users), verify password
                    if(empty($login_err)) {
                        // The SQL query already handles case sensitivity with BINARY
                        // Trust the database result - if we got here, username matches
                        if(password_verify($password, $hashed_password)) {
                            // Check if user is active before allowing login
                            // First check if Status column exists in users table
                            $column_check = "SHOW COLUMNS FROM users LIKE 'Status'";
                            $column_result = mysqli_query($conn, $column_check);
                            
                            $is_user_active = true;
                            if ($column_result && mysqli_num_rows($column_result) > 0) {
                                // Status column exists, proceed with status check
                                $check_active_sql = "SELECT Status FROM users WHERE username = ?";
                                if($check_active_stmt = mysqli_prepare($conn, $check_active_sql)) {
                                    mysqli_stmt_bind_param($check_active_stmt, "s", $db_username);
                                    mysqli_stmt_execute($check_active_stmt);
                                    $active_result = mysqli_stmt_get_result($check_active_stmt);
                                    if($active_row = mysqli_fetch_assoc($active_result)) {
                                        $user_status = ucfirst(strtolower(trim($active_row['Status'] ?? 'Active')));
                                        if($user_status === 'Inactive') {
                                            $is_user_active = false;
                                            $login_err = "Your account has been deactivated. Please contact the administrator.";
                                        }
                                    }
                                    mysqli_stmt_close($check_active_stmt);
                                }
                            }
                            // If Status column doesn't exist, skip the check (assume user is active)
                            
                            if($is_user_active) {
                                // Password is correct - login successful
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id"] = $id;
                                $_SESSION["username"] = $db_username;
                                $_SESSION["user_type"] = $user_type;
                                
                                // Log the login session
                                $session_id = logUserLogin($conn, $db_username);
                                
                                // Check if this is a new device
                                $ip_address = getClientIpAddress();
                                $device_info = getDeviceInfo();
                                $is_new_device = isNewDevice($conn, $db_username, $ip_address, $device_info);
                                
                                if ($is_new_device) {
                                    $_SESSION['new_device_warning'] = true;
                                }
                                
                                // Mark password login as successful
                                $password_login_success = true;
                                
                                // Redirect user to appropriate dashboard or saved URL
                                // Note: $stmt is already closed after fetching user data (line 129)
                                redirectToDashboard();
                            }
                        } else {
                            // Password is not valid - continue to check if it might be a reset code
                            // $password_login_success remains false
                        }
                    }
                }
            } else {
                // Username doesn't exist (no case-sensitive match found)
                if(empty($login_err)) {
                    $login_err = "Username not found. Please check your username spelling and case.";
                }
            }
            
            // If password login failed AND the input looks like a reset code, try reset code login
            if(!$password_login_success && strlen($password) == 6 && is_numeric($password)) {
                // This might be a reset code - try reset code login
                $reset_login_username = $username;
                $reset_login_code = $password;
                
                // Verify the reset code is valid and approved
                $verify_sql = "SELECT username, email, reset_code, approved_at FROM password_reset_requests 
                              WHERE username = ? AND reset_code = ? AND status = 'approved' 
                              ORDER BY approved_at DESC LIMIT 1";
                
                if($verify_stmt = mysqli_prepare($conn, $verify_sql)) {
                    mysqli_stmt_bind_param($verify_stmt, "ss", $reset_login_username, $reset_login_code);
                    
                    if(mysqli_stmt_execute($verify_stmt)) {
                        mysqli_stmt_store_result($verify_stmt);
                        
                        if(mysqli_stmt_num_rows($verify_stmt) == 1) {
                            mysqli_stmt_bind_result($verify_stmt, $reset_username, $reset_email, $reset_code, $approved_at);
                            mysqli_stmt_fetch($verify_stmt);
                            
                            // Now get user details from users table
                            $user_sql = "SELECT id, username, user_type FROM users WHERE username = ?";
                            if($user_stmt = mysqli_prepare($conn, $user_sql)) {
                                mysqli_stmt_bind_param($user_stmt, "s", $reset_username);
                                
                                if(mysqli_stmt_execute($user_stmt)) {
                                    mysqli_stmt_store_result($user_stmt);
                                    
                                    if(mysqli_stmt_num_rows($user_stmt) == 1) {
                                        mysqli_stmt_bind_result($user_stmt, $user_id, $db_username, $user_type);
                                        mysqli_stmt_fetch($user_stmt);
                                        
                                        // Reset code is valid - log the user in
                                        $_SESSION["loggedin"] = true;
                                        $_SESSION["id"] = $user_id;
                                        $_SESSION["username"] = $db_username;
                                        $_SESSION["user_type"] = $user_type;
                                        
                                        // Log the login session
                                        $session_id = logUserLogin($conn, $db_username);
                                        
                                        // Check if this is a new device
                                        $ip_address = getClientIpAddress();
                                        $device_info = getDeviceInfo();
                                        $is_new_device = isNewDevice($conn, $db_username, $ip_address, $device_info);
                                        
                                        if ($is_new_device) {
                                            $_SESSION['new_device_warning'] = true;
                                        }
                                        
                                        // Mark this reset code as used
                                        $mark_used_sql = "UPDATE password_reset_requests SET status = 'used' WHERE username = ? AND reset_code = ? AND status = 'approved'";
                                        if($mark_stmt = mysqli_prepare($conn, $mark_used_sql)) {
                                            mysqli_stmt_bind_param($mark_stmt, "ss", $reset_username, $reset_code);
                                            mysqli_stmt_execute($mark_stmt);
                                            mysqli_stmt_close($mark_stmt);
                                        }
                                        
                                        mysqli_stmt_close($user_stmt);
                                        mysqli_stmt_close($verify_stmt);
                                        
                                        // Redirect to dashboard or saved URL
                                        redirectToDashboard();
                                        
                                    } else {
                                        $login_err = "User account not found. Please contact administrator.";
                                    }
                                } else {
                                    $login_err = "Error retrieving user information.";
                                }
                                mysqli_stmt_close($user_stmt);
                            }
                            
                        } else {
                            // Reset code is invalid - show password error since password login also failed
                            if(empty($login_err)) {
                                $login_err = "Password is incorrect. Please check your password.";
                            }
                        }
                    } else {
                        $login_err = "Oops! Something went wrong. Please try again later.";
                    }
                    
                    mysqli_stmt_close($verify_stmt);
                }
            } else if(!$password_login_success && empty($login_err)) {
                // If we get here, password login failed and it's not a reset code
                $login_err = "Password is incorrect. Please check your password.";
            }
        }
    }
    
    // Close connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Task Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card login-form">
                    <div class="card-header bg-primary text-white text-center">
                        <h2>Login</h2>
                    </div>
                    <div class="card-body">
                        <?php 
                        if(!empty($login_err)){
                            echo '<div class="alert alert-danger">' . $login_err . '</div>';
                        }
                        if(!empty($reset_message)){
                            echo '<div class="alert alert-success">' . $reset_message . '</div>';
                        }
                        if(!empty($reset_code_message)){
                            echo '<div class="alert alert-info">' . $reset_code_message . '</div>';
                        }
                        if(!empty($reset_login_success)){
                            echo '<div class="alert alert-success">' . $reset_login_success . '</div>';
                        }
                        if(isset($_GET['expired']) && $_GET['expired'] == '1'){
                            echo '<div class="alert alert-warning">You were logged out at 8:00 PM. Please login again.</div>';
                        }
                        if(isset($_GET['inactive']) && $_GET['inactive'] == '1'){
                            echo '<div class="alert alert-danger">Your account has been deactivated. Please contact the administrator.</div>';
                        }
                        ?>

                        <ul class="nav nav-tabs" id="loginTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="login-tab" data-toggle="tab" href="#login" role="tab" aria-controls="login" aria-selected="true">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="forgot-tab" data-toggle="tab" href="#forgot" role="tab" aria-controls="forgot" aria-selected="false">Forgot Password</a>
                            </li>
                        </ul>
                        
                        <div class="tab-content mt-3" id="loginTabsContent">
                            <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
                                <form id="loginForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>" placeholder="Enter your username">
                                        <span class="invalid-feedback"><?php echo $username_err; ?></span>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Password or Reset Code</label>
                                        <div class="input-group">
                                            <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter your password or 6-digit reset code">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                    <i class="fa fa-eye-slash" id="eyeIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                                        <small class="form-text text-muted">You can login with your password OR with your approved reset code</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-block">Login</button>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="tab-pane fade" id="forgot" role="tabpanel" aria-labelledby="forgot-tab">
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" name="reset_username" class="form-control" placeholder="Enter your username" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" name="reset_email" class="form-control" placeholder="Enter your email" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" name="reset_password" class="btn btn-warning btn-block">Request Password Reset</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <style>
        /* Password toggle button styling */
        .input-group {
            display: flex;
            flex-wrap: nowrap;
        }
        
        .input-group .form-control {
            flex: 1;
            border-right: 0;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .input-group-append {
            display: flex;
            flex-shrink: 0;
        }
        
        .input-group-append .btn {
            border-left: 0;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            white-space: nowrap;
            background-color: #fff;
            border-color: #ced4da;
            color: #6c757d;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            transition: all 0.15s ease-in-out;
        }
        
        .input-group-append .btn:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
            color: #495057;
        }
        
        .input-group-append .btn:focus {
            box-shadow: none;
            outline: none;
        }
        
        .input-group-append .btn:active {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }
        
        /* Ensure proper alignment */
        .input-group > * {
            display: flex;
            align-items: stretch;
        }
        
        /* Match button height with input field */
        .input-group-append .btn {
            height: calc(1.5em + 0.75rem + 2px);
        }
    </style>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        });
    </script>
</body>
</html> 