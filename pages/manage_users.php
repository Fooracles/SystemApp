<?php
$page_title = "Manage Users"; // Default title
require_once "../includes/header.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Only Admin and Manager can access this page (Doers cannot)
if(!isAdmin() && !isManager()) {
    header("location: doer_dashboard.php");
    exit;
}

// Determine which sections the user can see
$is_admin = isAdmin();
$is_manager = isManager();

// Set page title based on user role (after functions are loaded via header.php)
$page_title = ($is_manager && !$is_admin) ? "Manage Clients" : "Manage Users";
$can_manage_team = $is_admin; // Only Admin can manage team
$can_manage_clients = $is_admin || $is_manager; // Both Admin and Manager can manage clients

// Default active section based on user role
$requested_section = isset($_GET['section']) ? $_GET['section'] : '';
$active_section = $is_admin ? 'team' : 'clients'; // Default based on role

// Validate requested section based on permissions
if ($requested_section === 'team' && !$can_manage_team) {
    // Manager trying to access team section - redirect to clients
    header("Location: manage_users.php?section=clients");
    exit;
} elseif ($requested_section === 'clients' && !$can_manage_clients) {
    // User without permission trying to access clients - redirect to team
    header("Location: manage_users.php?section=team");
    exit;
} elseif (!empty($requested_section) && ($requested_section === 'team' || $requested_section === 'clients')) {
    // Valid section requested
    $active_section = $requested_section;
}

// Define variables and initialize with empty values
$username = $name = $email = $password = $confirm_password = $department_id = $user_type = $joining_date = $date_of_birth = $manager = "";
$username_err = $name_err = $email_err = $password_err = $confirm_password_err = $department_err = $user_type_err = $joining_date_err = $date_of_birth_err = $manager_err = "";

// Process delete user request
if(isset($_POST['delete_user']) && !empty($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    
    // Check if trying to delete self
    if($user_id == $_SESSION['id']) {
        $error_msg = "You cannot delete your own account.";
    } else {
        // Check if manager is trying to delete a client account (managers can only manage client users, not client accounts)
        if($is_manager && !$is_admin) {
            $check_user_type_sql = "SELECT user_type, password FROM users WHERE id = ?";
            if($check_user_type_stmt = mysqli_prepare($conn, $check_user_type_sql)) {
                mysqli_stmt_bind_param($check_user_type_stmt, "i", $user_id);
                mysqli_stmt_execute($check_user_type_stmt);
                $check_user_type_result = mysqli_stmt_get_result($check_user_type_stmt);
                if($check_user_type_row = mysqli_fetch_assoc($check_user_type_result)) {
                    // Client Accounts: user_type = 'client' AND password is empty or NULL
                    // Client Users: user_type = 'client' AND password is NOT empty
                    if($check_user_type_row['user_type'] === 'client' && (empty($check_user_type_row['password']) || $check_user_type_row['password'] === '')) {
                        $error_msg = "Managers cannot delete client accounts. You can only manage users under client accounts.";
                        mysqli_stmt_close($check_user_type_stmt);
                        // Skip the rest of the delete processing
                        goto skip_delete;
                    }
                }
                mysqli_stmt_close($check_user_type_stmt);
            }
        }
        // First, get the user's name
        $user_name = "";
        $get_user_sql = "SELECT name FROM users WHERE id = ?";
        if($get_user_stmt = mysqli_prepare($conn, $get_user_sql)) {
            mysqli_stmt_bind_param($get_user_stmt, "i", $user_id);
            if(mysqli_stmt_execute($get_user_stmt)) {
                $get_user_result = mysqli_stmt_get_result($get_user_stmt);
                if($user_row = mysqli_fetch_assoc($get_user_result)) {
                    $user_name = $user_row['name'];
                }
            }
            mysqli_stmt_close($get_user_stmt);
        }
        
        // Then ensure doer_name is set for all tasks assigned to this user
        if(!empty($user_name)) {
            // Get the username for this user to update tasks
            $get_username_sql = "SELECT username FROM users WHERE id = ?";
            if($get_username_stmt = mysqli_prepare($conn, $get_username_sql)) {
                mysqli_stmt_bind_param($get_username_stmt, "i", $user_id);
                mysqli_stmt_execute($get_username_stmt);
                $username_result = mysqli_stmt_get_result($get_username_stmt);
                if($username_row = mysqli_fetch_assoc($username_result)) {
                    $username_to_store = $username_row['username'];
                    $update_tasks_sql = "UPDATE tasks SET doer_name = ? WHERE doer_id = ? AND (doer_name IS NULL OR doer_name = '')";
                    if($update_stmt = mysqli_prepare($conn, $update_tasks_sql)) {
                        mysqli_stmt_bind_param($update_stmt, "si", $username_to_store, $user_id);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }
                }
                mysqli_stmt_close($get_username_stmt);
            }
        }
        
        // Check if the user being deleted is a Manager or Admin
        // If so, update all users who have this person as their manager to "Shubham Tyagi"
        $check_manager_sql = "SELECT user_type, name FROM users WHERE id = ?";
        if($check_manager_stmt = mysqli_prepare($conn, $check_manager_sql)) {
            mysqli_stmt_bind_param($check_manager_stmt, "i", $user_id);
            if(mysqli_stmt_execute($check_manager_stmt)) {
                $check_manager_result = mysqli_stmt_get_result($check_manager_stmt);
                if($check_manager_row = mysqli_fetch_assoc($check_manager_result)) {
                    $deleted_user_type = $check_manager_row["user_type"];
                    $deleted_user_name = $check_manager_row["name"];
                    
                    // If the deleted user is a Manager or Admin, update all users who had them as manager
                    if($deleted_user_type === "manager" || $deleted_user_type === "admin") {
                        $update_managers_sql = "UPDATE users SET manager = \"Shubham Tyagi\" WHERE manager = ?";
                        if($update_managers_stmt = mysqli_prepare($conn, $update_managers_sql)) {
                            mysqli_stmt_bind_param($update_managers_stmt, "s", $deleted_user_name);
                            mysqli_stmt_execute($update_managers_stmt);
                            mysqli_stmt_close($update_managers_stmt);
                        }
                    }
                }
            }
            mysqli_stmt_close($check_manager_stmt);
        }
        
        // Now delete the user
        $sql = "DELETE FROM users WHERE id = ?";
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            
            if(mysqli_stmt_execute($stmt)) {
                $success_msg = "User deleted successfully!";
            } else {
                $error_msg = "Something went wrong. Please try again later.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    skip_delete: // Label for skipping delete when manager tries to delete client account
}

// Process edit user request
if(isset($_POST['edit_user']) && !empty($_POST['user_id'])) {
    $edit_user_id = $_POST['user_id'];
    
    // Check if manager is trying to edit a client account (managers can only manage client users, not client accounts)
    if($is_manager && !$is_admin) {
        $check_edit_user_type_sql = "SELECT user_type, password FROM users WHERE id = ?";
        if($check_edit_user_type_stmt = mysqli_prepare($conn, $check_edit_user_type_sql)) {
            mysqli_stmt_bind_param($check_edit_user_type_stmt, "i", $edit_user_id);
            mysqli_stmt_execute($check_edit_user_type_stmt);
            $check_edit_user_type_result = mysqli_stmt_get_result($check_edit_user_type_stmt);
            if($check_edit_user_type_row = mysqli_fetch_assoc($check_edit_user_type_result)) {
                // Client Accounts: user_type = 'client' AND password is empty or NULL
                // Client Users: user_type = 'client' AND password is NOT empty
                if($check_edit_user_type_row['user_type'] === 'client' && (empty($check_edit_user_type_row['password']) || $check_edit_user_type_row['password'] === '')) {
                    $error_msg = "Managers cannot edit client accounts. You can only manage users under client accounts.";
                    mysqli_stmt_close($check_edit_user_type_stmt);
                    // Skip the rest of the edit processing
                    goto skip_edit;
                }
            }
            mysqli_stmt_close($check_edit_user_type_stmt);
        }
    }
    
    // Get the user data
    $sql = "SELECT * FROM users WHERE id = ?";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $edit_user_id);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if(mysqli_num_rows($result) == 1) {
                $edit_user = mysqli_fetch_assoc($result);
                
                // Set form values
                $edit_id = $edit_user['id'];
                $username = $edit_user['username'];
                $name = $edit_user['name'];
                $email = $edit_user['email'];
                $department_id = $edit_user['department_id'];
                $user_type = $edit_user['user_type'];
                $joining_date = $edit_user['joining_date'] ? $edit_user['joining_date'] : '';
                $date_of_birth = $edit_user['date_of_birth'] ? $edit_user['date_of_birth'] : '';
                $manager = $edit_user['manager'] ?? 'Shubham Tyagi'; // Default if no manager set
            } else {
                $error_msg = "User not found.";
            }
        } else {
            $error_msg = "Something went wrong. Please try again later.";
        }
        
        mysqli_stmt_close($stmt);
    }
    skip_edit: // Label for skipping edit when manager tries to edit client account
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_user'])) {
    
    // Validate username
    if(empty(trim($_POST["username"]))) {
        if(!isset($_POST['edit_id'])) {
            $username_err = "Please enter a username.";
        }
    } else {
        $username = trim($_POST["username"]);
        
        // Check if username exists (for new users or changed usernames)
        $check_username = true;
        if(isset($_POST['edit_id'])) {
            $sql = "SELECT username FROM users WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $_POST['edit_id']);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $current_user = mysqli_fetch_assoc($result);
                
                // If username hasn't changed, no need to check (case-sensitive comparison)
                if($current_user['username'] === $username) {
                    $check_username = false;
                }
                
                mysqli_stmt_close($stmt);
            }
        }
        
        if($check_username) {
            // Use BINARY comparison for case-sensitive username check (works with any character set)
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
        
        // Check if email exists (for new users or changed emails)
        $check_email = true;
        if(isset($_POST['edit_id'])) {
            $sql = "SELECT email FROM users WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $_POST['edit_id']);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $current_user = mysqli_fetch_assoc($result);
                
                // If email hasn't changed, no need to check
                if($current_user['email'] == $email) {
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
    
    // Validate department
    if(empty($_POST["department_id"])) {
        if(!isset($_POST['edit_id'])) {
            $department_err = "Please select a department.";
        }
    } else {
        $department_id = $_POST["department_id"];
    }
    
    // Validate user type
    if(empty($_POST["user_type"])) {
        if(!isset($_POST['edit_id'])) {
            $user_type_err = "Please select a user type.";
        }
    } else {
        $user_type = $_POST["user_type"];
    }
    
    // Get all Manager and Admin users for validation (needed before form processing)
    $managers_for_validation = array();
    $sql_managers_validation = "SELECT id, name FROM users WHERE user_type IN ('manager', 'admin') ORDER BY name";
    $result_managers_validation = mysqli_query($conn, $sql_managers_validation);
    if($result_managers_validation) {
        while($row = mysqli_fetch_assoc($result_managers_validation)) {
            $managers_for_validation[] = $row;
        }
    }
    
    // Validate manager (mandatory for non-admin and non-client users)
    if(empty(trim($_POST["manager"]))) {
        if(!isset($_POST['edit_id']) && $user_type !== "admin" && $user_type !== "client") {
            $manager_err = "Please select a manager.";
        } else {
            $manager = "Shubham Tyagi"; // Default for admin and client users
        }
    } else {
        $manager = trim($_POST["manager"]);
        
        // Validate that selected manager exists in the system
        $valid_managers = array("Shubham Tyagi");
        foreach($managers_for_validation as $manager_user) {
            $valid_managers[] = $manager_user["name"];
        }
        
        if(!in_array($manager, $valid_managers)) {
            $manager_err = "Please select a valid manager from the list.";
        }
    }
    
    // Validate joining date
    if(empty($_POST["joining_date"])) {
        if(!isset($_POST['edit_id'])) {
            $joining_date_err = "Please enter joining date.";
        }
        $joining_date = null; // Set to null if empty
        $joining_date_db = null; // Set to null if empty
    } else {
        $joining_date = $_POST["joining_date"]; // Keep original YYYY-MM-DD format for display
        
        // Validate date format YYYY-MM-DD - strict validation
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $joining_date)) {
            $date = DateTime::createFromFormat('Y-m-d', $joining_date);
            if ($date && $date->format('Y-m-d') === $joining_date) {
                // Additional validation to ensure the date is valid and not default
                if ($joining_date !== '1970-01-01' && $joining_date !== '0000-00-00') {
                    $joining_date_db = $joining_date;
                } else {
                    $joining_date_err = "Please enter a valid joining date (YYYY-MM-DD).";
                    $joining_date_db = null;
                }
            } else {
                $joining_date_err = "Please enter a valid joining date (YYYY-MM-DD).";
                $joining_date_db = null;
            }
        } else {
            $joining_date_err = "Please enter a valid joining date in YYYY-MM-DD format.";
            $joining_date_db = null;
        }
    }
    
    // Validate date of birth
    if(empty($_POST["date_of_birth"])) {
        if(!isset($_POST['edit_id'])) {
            $date_of_birth_err = "Please enter date of birth.";
        }
        $date_of_birth = null; // Set to null if empty
        $date_of_birth_db = null; // Set to null if empty
    } else {
        $date_of_birth = $_POST["date_of_birth"]; // Keep original YYYY-MM-DD format for display
        
        // Validate date format YYYY-MM-DD - strict validation
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
            $date = DateTime::createFromFormat('Y-m-d', $date_of_birth);
            if ($date && $date->format('Y-m-d') === $date_of_birth) {
                // Additional validation to ensure the date is valid and not default
                if ($date_of_birth !== '1970-01-01' && $date_of_birth !== '0000-00-00') {
                    // Validate that date of birth is not in the future
                    if ($date_of_birth <= date('Y-m-d')) {
                        $date_of_birth_db = $date_of_birth;
                    } else {
                        $date_of_birth_err = "Date of birth cannot be in the future.";
                        $date_of_birth_db = null;
                    }
                } else {
                    $date_of_birth_err = "Please enter a valid date of birth (YYYY-MM-DD).";
                    $date_of_birth_db = null;
                }
            } else {
                $date_of_birth_err = "Please enter a valid date of birth (YYYY-MM-DD).";
                $date_of_birth_db = null;
            }
        } else {
            $date_of_birth_err = "Please enter a valid date of birth in YYYY-MM-DD format.";
            $date_of_birth_db = null;
        }
    }
    
    // Validate password if adding new user or changing password
    if(!isset($_POST['edit_id']) || !empty($_POST["password"])) {
        if(empty(trim($_POST["password"]))) {
            $password_err = "Please enter a password.";     
        } elseif(strlen(trim($_POST["password"])) < 6) {
            $password_err = "Password must have at least 6 characters.";
        } else {
            $password = trim($_POST["password"]);
        }
        
        // Validate confirm password
        if(empty(trim($_POST["confirm_password"]))) {
            $confirm_password_err = "Please confirm password.";     
        } else {
            $confirm_password = trim($_POST["confirm_password"]);
            if(empty($password_err) && ($password != $confirm_password)) {
                $confirm_password_err = "Password did not match.";
            }
        }
    }
    
    // Check input errors before inserting in database
    if(empty($username_err) && empty($name_err) && empty($email_err) && 
       empty($department_err) && empty($user_type_err) && empty($manager_err) && 
       empty($joining_date_err) && empty($date_of_birth_err) &&
       (isset($_POST['edit_id']) && empty($_POST["password"]) || empty($password_err) && empty($confirm_password_err))) {
        
        if(isset($_POST['edit_id'])) {
            // Update existing user - use current values for empty fields
            $current_user_sql = "SELECT username, name, email, department_id, user_type, manager, joining_date, date_of_birth FROM users WHERE id = ?";
            $current_stmt = mysqli_prepare($conn, $current_user_sql);
            mysqli_stmt_bind_param($current_stmt, "i", $_POST['edit_id']);
            mysqli_stmt_execute($current_stmt);
            $current_result = mysqli_stmt_get_result($current_stmt);
            $current_user = mysqli_fetch_assoc($current_result);
            mysqli_stmt_close($current_stmt);
            
            // Use current values for empty fields in edit mode
            $username = !empty($username) ? $username : $current_user['username'];
            $name = !empty($name) ? $name : $current_user['name'];
            $email = !empty($email) ? $email : $current_user['email'];
            $department_id = !empty($department_id) ? $department_id : $current_user['department_id'];
            $user_type = !empty($user_type) ? $user_type : $current_user['user_type'];
            $manager = !empty($manager) ? $manager : $current_user['manager'];
            $joining_date_db_final = $joining_date_db !== null ? $joining_date_db : $current_user['joining_date'];
            $date_of_birth_db_final = $date_of_birth_db !== null ? $date_of_birth_db : $current_user['date_of_birth'];
            
            if(!empty($_POST["password"])) {
                // Update with new password
                // Get manager_id from manager name
                // Check if manager is trying to update a client account (managers can only manage client users, not client accounts)
                if($is_manager && !$is_admin && isset($_POST['edit_id'])) {
                    $check_update_user_type_sql = "SELECT user_type FROM users WHERE id = ?";
                    if($check_update_user_type_stmt = mysqli_prepare($conn, $check_update_user_type_sql)) {
                        mysqli_stmt_bind_param($check_update_user_type_stmt, "i", $_POST['edit_id']);
                        mysqli_stmt_execute($check_update_user_type_stmt);
                        $check_update_user_type_result = mysqli_stmt_get_result($check_update_user_type_stmt);
                        if($check_update_user_type_row = mysqli_fetch_assoc($check_update_user_type_result)) {
                            if($check_update_user_type_row['user_type'] === 'client') {
                                // Check if this is a client account (password is NULL) vs client user (password is not NULL)
                                $check_client_account_sql = "SELECT password FROM users WHERE id = ?";
                                if($check_client_account_stmt = mysqli_prepare($conn, $check_client_account_sql)) {
                                    mysqli_stmt_bind_param($check_client_account_stmt, "i", $_POST['edit_id']);
                                    mysqli_stmt_execute($check_client_account_stmt);
                                    $check_client_account_result = mysqli_stmt_get_result($check_client_account_stmt);
                                    if($check_client_account_row = mysqli_fetch_assoc($check_client_account_result)) {
                                        // If password is NULL, it's a client account (not a client user)
                                        if($check_client_account_row['password'] === null) {
                                            $error_msg = "Managers cannot update client accounts. You can only manage users under client accounts.";
                                            mysqli_stmt_close($check_client_account_stmt);
                                            mysqli_stmt_close($check_update_user_type_stmt);
                                            // Skip the rest of the update processing
                                            goto skip_update;
                                        }
                                    }
                                    mysqli_stmt_close($check_client_account_stmt);
                                }
                            }
                        }
                        mysqli_stmt_close($check_update_user_type_stmt);
                    }
                }
                
                $manager_id = null;
                if (!empty($manager) && $manager !== "Shubham Tyagi") {
                    $manager_query = "SELECT id FROM users WHERE name = ? AND user_type IN ('manager', 'admin') LIMIT 1";
                    $manager_stmt = mysqli_prepare($conn, $manager_query);
                    if ($manager_stmt) {
                        mysqli_stmt_bind_param($manager_stmt, "s", $manager);
                        mysqli_stmt_execute($manager_stmt);
                        $manager_result = mysqli_stmt_get_result($manager_stmt);
                        if ($manager_row = mysqli_fetch_assoc($manager_result)) {
                            $manager_id = $manager_row['id'];
                        }
                        mysqli_stmt_close($manager_stmt);
                    }
                }
                
                $sql = "UPDATE users SET username = ?, name = ?, email = ?, department_id = ?, user_type = ?, manager = ?, manager_id = ?, joining_date = ?, date_of_birth = ?, password = ? WHERE id = ?";
                if($stmt = mysqli_prepare($conn, $sql)) {
                    $param_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    mysqli_stmt_bind_param($stmt, "sssissssssi", $username, $name, $email, $department_id, $user_type, $manager, $manager_id, $joining_date_db_final, $date_of_birth_db_final, $param_password, $_POST['edit_id']);
                    
                    try {
                        if(mysqli_stmt_execute($stmt)) {
                            $success_msg = "User updated successfully!";
                            // Clear form data
                            $username = $name = $email = $password = $confirm_password = $department_id = $user_type = $manager = $joining_date = $date_of_birth = "";
                            unset($edit_id);
                        } else {
                            $error_msg = "Something went wrong. Please try again later. Error: " . mysqli_stmt_error($stmt);
                        }
                    } catch (Exception $e) {
                        // Handle MySQLi exceptions
                        $error_msg = "Database error occurred. ";
                        
                        if(strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'username') !== false) {
                            $username_err = "This username is already taken.";
                            $error_msg = "Username already exists. Please choose a different username.";
                        } elseif(strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email') !== false) {
                            $email_err = "This email is already registered.";
                            $error_msg = "Email already exists. Please choose a different email.";
                        } else {
                            $error_msg .= "Error: " . $e->getMessage();
                        }
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            } else {
                // Update without changing password
                // Check if manager is trying to update a client account (managers can only manage client users, not client accounts)
                if($is_manager && !$is_admin && isset($_POST['edit_id'])) {
                    $check_update2_user_type_sql = "SELECT user_type, password FROM users WHERE id = ?";
                    if($check_update2_user_type_stmt = mysqli_prepare($conn, $check_update2_user_type_sql)) {
                        mysqli_stmt_bind_param($check_update2_user_type_stmt, "i", $_POST['edit_id']);
                        mysqli_stmt_execute($check_update2_user_type_stmt);
                        $check_update2_user_type_result = mysqli_stmt_get_result($check_update2_user_type_stmt);
                        if($check_update2_user_type_row = mysqli_fetch_assoc($check_update2_user_type_result)) {
                            if($check_update2_user_type_row['user_type'] === 'client' && $check_update2_user_type_row['password'] === null) {
                                $error_msg = "Managers cannot update client accounts. You can only manage users under client accounts.";
                                mysqli_stmt_close($check_update2_user_type_stmt);
                                // Skip the rest of the update processing
                                goto skip_update;
                            }
                        }
                        mysqli_stmt_close($check_update2_user_type_stmt);
                    }
                }
                
                // Get manager_id from manager name
                $manager_id = null;
                if (!empty($manager) && $manager !== "Shubham Tyagi") {
                    $manager_query = "SELECT id FROM users WHERE name = ? AND user_type IN ('manager', 'admin') LIMIT 1";
                    $manager_stmt = mysqli_prepare($conn, $manager_query);
                    if ($manager_stmt) {
                        mysqli_stmt_bind_param($manager_stmt, "s", $manager);
                        mysqli_stmt_execute($manager_stmt);
                        $manager_result = mysqli_stmt_get_result($manager_stmt);
                        if ($manager_row = mysqli_fetch_assoc($manager_result)) {
                            $manager_id = $manager_row['id'];
                        }
                        mysqli_stmt_close($manager_stmt);
                    }
                }
                
                $sql = "UPDATE users SET username = ?, name = ?, email = ?, department_id = ?, user_type = ?, manager = ?, manager_id = ?, joining_date = ?, date_of_birth = ? WHERE id = ?";
                if($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "sssisssssi", $username, $name, $email, $department_id, $user_type, $manager, $manager_id, $joining_date_db_final, $date_of_birth_db_final, $_POST['edit_id']);
                    
                    try {
                        if(mysqli_stmt_execute($stmt)) {
                            $success_msg = "User updated successfully!";
                            // Clear form data
                            $username = $name = $email = $password = $confirm_password = $department_id = $user_type = $manager = $joining_date = $date_of_birth = "";
                            unset($edit_id);
                        } else {
                            $error_msg = "Something went wrong. Please try again later. Error: " . mysqli_stmt_error($stmt);
                        }
                    } catch (Exception $e) {
                        // Handle MySQLi exceptions
                        $error_msg = "Database error occurred. ";
                        
                        if(strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'username') !== false) {
                            $username_err = "This username is already taken.";
                            $error_msg = "Username already exists. Please choose a different username.";
                        } elseif(strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email') !== false) {
                            $error_msg = "Email already exists. Please choose a different email.";
                        } else {
                            $error_msg .= "Error: " . $e->getMessage();
                        }
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            }
        } else {
            // Create new user
            // Get manager_id from manager name
            $manager_id = null;
            if (!empty($manager) && $manager !== "Shubham Tyagi") {
                $manager_query = "SELECT id FROM users WHERE name = ? AND user_type IN ('manager', 'admin') LIMIT 1";
                $manager_stmt = mysqli_prepare($conn, $manager_query);
                if ($manager_stmt) {
                    mysqli_stmt_bind_param($manager_stmt, "s", $manager);
                    mysqli_stmt_execute($manager_stmt);
                    $manager_result = mysqli_stmt_get_result($manager_stmt);
                    if ($manager_row = mysqli_fetch_assoc($manager_result)) {
                        $manager_id = $manager_row['id'];
                    }
                    mysqli_stmt_close($manager_stmt);
                }
            }
            
            $sql = "INSERT INTO users (username, name, email, password, department_id, user_type, manager, manager_id, joining_date, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if($stmt = mysqli_prepare($conn, $sql)) {
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Handle NULL dates properly for database operations
                $joining_date_db_final = $joining_date_db === null ? null : $joining_date_db;
                $date_of_birth_db_final = $date_of_birth_db === null ? null : $date_of_birth_db;
                
                mysqli_stmt_bind_param($stmt, "ssssisssss", $username, $name, $email, $param_password, $department_id, $user_type, $manager, $manager_id, $joining_date_db_final, $date_of_birth_db_final);
                
                try {
                    if(mysqli_stmt_execute($stmt)) {
                        $success_msg = "User created successfully!";
                        // Clear form data
                        $username = $name = $email = $password = $confirm_password = $department_id = $user_type = "";
                    } else {
                        $error_msg = "Something went wrong. Please try again later. Error: " . mysqli_stmt_error($stmt);
                    }
                } catch (Exception $e) {
                    // Handle MySQLi exceptions
                    $error_msg = "Database error occurred. ";
                    
                    if(strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'username') !== false) {
                        $username_err = "This username is already taken.";
                        $error_msg = "Username already exists. Please choose a different username.";
                    } elseif(strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email') !== false) {
                        $email_err = "This email is already registered.";
                        $error_msg = "Email already exists. Please choose a different email.";
                    } else {
                        $error_msg .= "Error: " . $e->getMessage();
                    }
                }
                
                mysqli_stmt_close($stmt);
            }
        }
        skip_update: // Label for skipping update when manager tries to update client account
    }
}

// Process Client Account Creation
$client_username = $client_name = $client_email = $client_manager = $client_joining_date = "";
$client_username_err = $client_name_err = $client_email_err = $client_manager_err = $client_joining_date_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_client_account'])) {
    
    // Validate username (Account Name)
    if(empty(trim($_POST["client_username"]))) {
        $client_username_err = "Please enter an account name.";
    } else {
        $client_username = trim($_POST["client_username"]);
        
        // Check if username exists
        $sql = "SELECT id FROM users WHERE BINARY username = ?";
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $client_username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if(mysqli_stmt_num_rows($stmt) > 0) {
                $client_username_err = "This account name is already taken.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    // Set default name and email (use username for name, generate email from username)
    $client_name = !empty(trim($_POST["client_username"])) ? trim($_POST["client_username"]) : "";
    $client_email = !empty(trim($_POST["client_username"])) ? trim($_POST["client_username"]) . "@client.local" : "";
    
    // Validate manager
    if(empty(trim($_POST["client_manager"]))) {
        $client_manager_err = "Please select a manager.";
    } else {
        $client_manager = trim($_POST["client_manager"]);
        
        // Validate that selected manager exists
        $valid_managers = array("Shubham Tyagi");
        // Fetch managers for validation
        $managers_validation_sql = "SELECT id, name FROM users WHERE user_type IN ('manager', 'admin') ORDER BY name";
        $managers_validation_result = mysqli_query($conn, $managers_validation_sql);
        if($managers_validation_result) {
            while($manager_user = mysqli_fetch_assoc($managers_validation_result)) {
            $valid_managers[] = $manager_user["name"];
            }
        }
        
        if(!in_array($client_manager, $valid_managers)) {
            $client_manager_err = "Please select a valid manager from the list.";
        }
    }
    
    // Validate onboarding date (joining date)
    if(empty($_POST["client_joining_date"])) {
        $client_joining_date_err = "Please enter onboarding date.";
        $client_joining_date_db = null;
    } else {
        $client_joining_date = $_POST["client_joining_date"];
        
        // Validate date format YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $client_joining_date)) {
            $date = DateTime::createFromFormat('Y-m-d', $client_joining_date);
            if ($date && $date->format('Y-m-d') === $client_joining_date) {
                if ($client_joining_date !== '1970-01-01' && $client_joining_date !== '0000-00-00') {
                    $client_joining_date_db = $client_joining_date;
                } else {
                    $client_joining_date_err = "Please enter a valid onboarding date (YYYY-MM-DD).";
                    $client_joining_date_db = null;
                }
            } else {
                $client_joining_date_err = "Please enter a valid onboarding date (YYYY-MM-DD).";
                $client_joining_date_db = null;
            }
        } else {
            $client_joining_date_err = "Please enter a valid onboarding date in YYYY-MM-DD format.";
            $client_joining_date_db = null;
        }
    }
    
    // Client Accounts do not have passwords - they are parent entities that cannot log in
    // Only Client Users (created under Client Accounts) have passwords and can log in
    
    // Check input errors before inserting/updating in database
    if(empty($client_username_err) && empty($client_manager_err) && empty($client_joining_date_err)) {
        
        // Get manager_id from manager name
        $client_manager_id = null;
        if (!empty($client_manager) && $client_manager !== "Shubham Tyagi") {
            $manager_query = "SELECT id FROM users WHERE name = ? AND user_type IN ('manager', 'admin') LIMIT 1";
            $manager_stmt = mysqli_prepare($conn, $manager_query);
            if ($manager_stmt) {
                mysqli_stmt_bind_param($manager_stmt, "s", $client_manager);
                mysqli_stmt_execute($manager_stmt);
                $manager_result = mysqli_stmt_get_result($manager_stmt);
                if ($manager_row = mysqli_fetch_assoc($manager_result)) {
                    $client_manager_id = $manager_row['id'];
                }
                mysqli_stmt_close($manager_stmt);
            }
        }
        
        // Check if editing existing client account
        if(isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
            // Update existing client account
            $edit_client_id = intval($_POST['edit_id']);
            
            // Check if username changed - if so, validate it's not taken by another user
            $check_username_sql = "SELECT id, username FROM users WHERE id = ?";
            $check_username_stmt = mysqli_prepare($conn, $check_username_sql);
            $current_username = '';
            if($check_username_stmt) {
                mysqli_stmt_bind_param($check_username_stmt, "i", $edit_client_id);
                mysqli_stmt_execute($check_username_stmt);
                $check_username_result = mysqli_stmt_get_result($check_username_stmt);
                if($check_username_row = mysqli_fetch_assoc($check_username_result)) {
                    $current_username = $check_username_row['username'];
                }
                mysqli_stmt_close($check_username_stmt);
            }
            
            // Only check for duplicate username if it changed
            if($current_username !== $client_username) {
                $check_duplicate_sql = "SELECT id FROM users WHERE BINARY username = ? AND id != ?";
                $check_duplicate_stmt = mysqli_prepare($conn, $check_duplicate_sql);
                if($check_duplicate_stmt) {
                    mysqli_stmt_bind_param($check_duplicate_stmt, "si", $client_username, $edit_client_id);
                    mysqli_stmt_execute($check_duplicate_stmt);
                    mysqli_stmt_store_result($check_duplicate_stmt);
                    if(mysqli_stmt_num_rows($check_duplicate_stmt) > 0) {
                        $client_username_err = "This account name is already taken.";
                        mysqli_stmt_close($check_duplicate_stmt);
                        goto skip_client_account_save;
                    }
                    mysqli_stmt_close($check_duplicate_stmt);
                }
            }
            
            $client_joining_date_db_final = isset($client_joining_date_db) && $client_joining_date_db !== null ? $client_joining_date_db : null;
            
            $sql = "UPDATE users SET username = ?, name = ?, email = ?, manager = ?, manager_id = ?, joining_date = ? WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssssisi", $client_username, $client_name, $client_email, $client_manager, $client_manager_id, $client_joining_date_db_final, $edit_client_id);
                
                try {
                    if(mysqli_stmt_execute($stmt)) {
                        $success_msg = "Client account updated successfully!";
                        // Clear form data
                        $client_username = $client_name = $client_email = $client_manager = $client_joining_date = "";
                    } else {
                        $error_msg = "Something went wrong. Please try again later. Error: " . mysqli_stmt_error($stmt);
                    }
                } catch (Exception $e) {
                    $error_msg = "Database error occurred. ";
                    
                    if(strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'username') !== false) {
                        $client_username_err = "This account name is already taken.";
                        $error_msg = "Account name already exists. Please choose a different account name.";
                    } else {
                        $error_msg .= "Error: " . $e->getMessage();
                    }
                }
                
                mysqli_stmt_close($stmt);
            }
        } else {
            // Create client account - user_type is automatically set to 'client'
            // Client Accounts do NOT have passwords - they are parent entities that cannot log in
            // Only Client Users (created under Client Accounts) have passwords and can log in
            // Note: password is set to empty string since database requires NOT NULL, but client accounts can't log in
            $sql = "INSERT INTO users (username, name, email, password, user_type, manager, manager_id, joining_date) VALUES (?, ?, ?, '', 'client', ?, ?, ?)";
            
            if($stmt = mysqli_prepare($conn, $sql)) {
                $client_joining_date_db_final = isset($client_joining_date_db) && $client_joining_date_db !== null ? $client_joining_date_db : null;
                
                mysqli_stmt_bind_param($stmt, "ssssis", $client_username, $client_name, $client_email, $client_manager, $client_manager_id, $client_joining_date_db_final);
                
                try {
                    if(mysqli_stmt_execute($stmt)) {
                        $success_msg = "Client account created successfully!";
                        // Clear form data
                        $client_username = $client_name = $client_email = $client_manager = $client_joining_date = "";
                    } else {
                        $error_msg = "Something went wrong. Please try again later. Error: " . mysqli_stmt_error($stmt);
                    }
                } catch (Exception $e) {
                    $error_msg = "Database error occurred. ";
                    
                    if(strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'username') !== false) {
                        $client_username_err = "This account name is already taken.";
                        $error_msg = "Account name already exists. Please choose a different account name.";
                    } else {
                        $error_msg .= "Error: " . $e->getMessage();
                    }
                }
                
                mysqli_stmt_close($stmt);
            }
        }
        skip_client_account_save:
    }
}

// Process Client User Creation (for Managers)
$client_user_username = $client_user_name = $client_user_email = $client_user_password = $client_user_confirm_password = $client_user_client_id = $client_user_user_type = $client_user_joining_date = $client_user_date_of_birth = "";
$client_user_username_err = $client_user_name_err = $client_user_email_err = $client_user_password_err = $client_user_confirm_password_err = $client_user_client_id_err = $client_user_joining_date_err = $client_user_date_of_birth_err = "";

// Allow both Manager and Admin to create client users
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_client_user']) && ($is_manager || $is_admin)) {
    // Log: Start of client_user creation/update process
    $current_user_id = isset($_SESSION['id']) ? $_SESSION['id'] : 'unknown';
    $current_username = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';
    $is_edit = isset($_POST['edit_id']) ? 'UPDATE' : 'CREATE';
    log_activity("CLIENT_USER_FLOW [{$is_edit}]: Process started by user ID {$current_user_id} ({$current_username}) in manage_users.php");
    
    // Validate username
    if(empty(trim($_POST["client_user_username"]))) {
        $client_user_username_err = "Please enter a username.";
    } else {
        $client_user_username = trim($_POST["client_user_username"]);
        
        // Check if editing - if so, allow same username
        $check_username = true;
        if(isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
            $check_current_sql = "SELECT username FROM users WHERE id = ?";
            if($check_current_stmt = mysqli_prepare($conn, $check_current_sql)) {
                mysqli_stmt_bind_param($check_current_stmt, "i", $_POST['edit_id']);
                mysqli_stmt_execute($check_current_stmt);
                $check_current_result = mysqli_stmt_get_result($check_current_stmt);
                if($check_current_row = mysqli_fetch_assoc($check_current_result)) {
                    if($check_current_row['username'] === $client_user_username) {
                        $check_username = false; // Same username, no need to check
                    }
                }
                mysqli_stmt_close($check_current_stmt);
            }
        }
        
        if($check_username) {
            // Check if username exists
            $sql = "SELECT id FROM users WHERE BINARY username = ?";
            if(isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
                $sql .= " AND id != ?";
            }
            if($stmt = mysqli_prepare($conn, $sql)) {
                if(isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
                    mysqli_stmt_bind_param($stmt, "si", $client_user_username, $_POST['edit_id']);
                } else {
                    mysqli_stmt_bind_param($stmt, "s", $client_user_username);
                }
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) > 0) {
                    $client_user_username_err = "This username is already taken.";
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Validate name
    if(empty(trim($_POST["client_user_name"]))) {
        $client_user_name_err = "Please enter a name.";
    } else {
        $client_user_name = trim($_POST["client_user_name"]);
    }
    
    // Validate email
    if(empty(trim($_POST["client_user_email"]))) {
        $client_user_email_err = "Please enter an email.";
    } else {
        $client_user_email = trim($_POST["client_user_email"]);
        
        // Check if editing - if so, allow same email
        $check_email = true;
        if(isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
            $check_current_email_sql = "SELECT email FROM users WHERE id = ?";
            if($check_current_email_stmt = mysqli_prepare($conn, $check_current_email_sql)) {
                mysqli_stmt_bind_param($check_current_email_stmt, "i", $_POST['edit_id']);
                mysqli_stmt_execute($check_current_email_stmt);
                $check_current_email_result = mysqli_stmt_get_result($check_current_email_stmt);
                if($check_current_email_row = mysqli_fetch_assoc($check_current_email_result)) {
                    if($check_current_email_row['email'] == $client_user_email) {
                        $check_email = false; // Same email, no need to check
                    }
                }
                mysqli_stmt_close($check_current_email_stmt);
            }
        }
        
        if($check_email) {
            // Check if email exists
            $sql = "SELECT id FROM users WHERE email = ?";
            if(isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
                $sql .= " AND id != ?";
            }
            if($stmt = mysqli_prepare($conn, $sql)) {
                if(isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
                    mysqli_stmt_bind_param($stmt, "si", $client_user_email, $_POST['edit_id']);
                } else {
                    mysqli_stmt_bind_param($stmt, "s", $client_user_email);
                }
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) > 0) {
                    $client_user_email_err = "This email is already registered.";
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Validate client account
    if(empty($_POST["client_user_client_id"])) {
        $client_user_client_id_err = "Please select a client account.";
    } else {
        $client_user_client_id = intval($_POST["client_user_client_id"]);
        
        // Verify client account exists and is a client
        $sql = "SELECT id FROM users WHERE id = ? AND user_type = 'client'";
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $client_user_client_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if(mysqli_stmt_num_rows($stmt) == 0) {
                $client_user_client_id_err = "Invalid client account selected.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    // Client Users don't have departments - set to NULL
    $client_user_department_id = null;
    
    // Client Users must always have user_type = 'client' (as per requirement)
    // They are distinguished from Client Accounts by having manager_id pointing to a Client Account
    // Client Accounts: user_type = 'client', manager_id = manager's id, password = NULL
    // Client Users: user_type = 'client', manager_id = client account's id, password = hashed
    $client_user_user_type = 'client';
    
    // Validate joining date (optional for edit mode)
    $client_user_joining_date_db = null;
    if(empty($_POST["client_user_joining_date"])) {
        if(!isset($_POST['edit_id'])) {
            $client_user_joining_date_err = "Please enter joining date.";
        }
    } else {
        $client_user_joining_date = $_POST["client_user_joining_date"];
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $client_user_joining_date)) {
            $date = DateTime::createFromFormat('Y-m-d', $client_user_joining_date);
            if ($date && $date->format('Y-m-d') === $client_user_joining_date && $client_user_joining_date !== '1970-01-01' && $client_user_joining_date !== '0000-00-00') {
                $client_user_joining_date_db = $client_user_joining_date;
            } else {
                $client_user_joining_date_err = "Please enter a valid joining date (YYYY-MM-DD).";
            }
        } else {
            $client_user_joining_date_err = "Please enter a valid joining date in YYYY-MM-DD format.";
        }
    }
    
    // Validate date of birth
    $client_user_date_of_birth_db = null;
    if(!empty($_POST["client_user_date_of_birth"])) {
        $client_user_date_of_birth = $_POST["client_user_date_of_birth"];
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $client_user_date_of_birth)) {
            $date = DateTime::createFromFormat('Y-m-d', $client_user_date_of_birth);
            if ($date && $date->format('Y-m-d') === $client_user_date_of_birth && $client_user_date_of_birth !== '1970-01-01' && $client_user_date_of_birth !== '0000-00-00') {
                if ($client_user_date_of_birth <= date('Y-m-d')) {
                    $client_user_date_of_birth_db = $client_user_date_of_birth;
                } else {
                    $client_user_date_of_birth_err = "Date of birth cannot be in the future.";
                }
            } else {
                $client_user_date_of_birth_err = "Please enter a valid date of birth (YYYY-MM-DD).";
            }
        } else {
            $client_user_date_of_birth_err = "Please enter a valid date of birth in YYYY-MM-DD format.";
        }
    }
    
    // Validate password (only required for new users or if changing password)
    if(!isset($_POST['edit_id']) || !empty(trim($_POST["client_user_password"]))) {
        if(empty(trim($_POST["client_user_password"]))) {
            $client_user_password_err = "Please enter a password.";     
        } elseif(strlen(trim($_POST["client_user_password"])) < 6) {
            $client_user_password_err = "Password must have at least 6 characters.";
        } else {
            $client_user_password = trim($_POST["client_user_password"]);
        }
        
        // Validate confirm password
        if(empty(trim($_POST["client_user_confirm_password"]))) {
            $client_user_confirm_password_err = "Please confirm password.";     
        } else {
            $client_user_confirm_password = trim($_POST["client_user_confirm_password"]);
            if(empty($client_user_password_err) && ($client_user_password != $client_user_confirm_password)) {
                $client_user_confirm_password_err = "Password did not match.";
            }
        }
    }
    
    // Check input errors before inserting/updating in database
    if(empty($client_user_username_err) && empty($client_user_name_err) && empty($client_user_email_err) && 
       empty($client_user_client_id_err) && empty($client_user_joining_date_err) &&
       (isset($_POST['edit_id']) && empty($_POST["client_user_password"]) || empty($client_user_password_err) && empty($client_user_confirm_password_err))) {
        
        // Log: Validation passed
        log_activity("CLIENT_USER_FLOW [{$is_edit}]: Validation passed for username '{$client_user_username}', email '{$client_user_email}', name '{$client_user_name}', client_id {$client_user_client_id}, joining_date: " . ($client_user_joining_date_db ?? 'NULL') . ", date_of_birth: " . ($client_user_date_of_birth_db ?? 'NULL'));
        
        // Check if editing existing client user
        if(isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
            // Update existing client user
            $edit_client_user_id = intval($_POST['edit_id']);
            
            if(!empty($client_user_password)) {
                // Update with new password - ensure user_type remains 'client'
                // Log: Before executing UPDATE query with password change
                log_activity("CLIENT_USER_FLOW [UPDATE]: Executing UPDATE query with password change for user_id {$edit_client_user_id}, username '{$client_user_username}', email '{$client_user_email}', client_id {$client_user_client_id}");
                
                $sql = "UPDATE users SET username = ?, name = ?, email = ?, password = ?, user_type = 'client', manager_id = ?, joining_date = ?, date_of_birth = ? WHERE id = ?";
                if($stmt = mysqli_prepare($conn, $sql)) {
                    $param_password = password_hash($client_user_password, PASSWORD_DEFAULT);
                    mysqli_stmt_bind_param($stmt, "sssssissi", $client_user_username, $client_user_name, $client_user_email, $param_password, $client_user_client_id, $client_user_joining_date_db, $client_user_date_of_birth_db, $edit_client_user_id);
                    
                    try {
                        if(mysqli_stmt_execute($stmt)) {
                            $success_msg = "Client user updated successfully!";
                            log_activity("CLIENT_USER_FLOW [UPDATE]: Successfully updated client_user with user_id {$edit_client_user_id}, username '{$client_user_username}', email '{$client_user_email}', name '{$client_user_name}', client_id {$client_user_client_id} (password changed)");
                            // Clear form data
                            $client_user_username = $client_user_name = $client_user_email = $client_user_password = $client_user_confirm_password = $client_user_client_id = $client_user_department_id = $client_user_joining_date = $client_user_date_of_birth = "";
                        } else {
                            $error_msg = "Something went wrong. Please try again later. Error: " . mysqli_stmt_error($stmt);
                            $error_detail = mysqli_stmt_error($stmt);
                            log_activity("CLIENT_USER_FLOW [UPDATE]: Failed to update client_user with user_id {$edit_client_user_id}, username '{$client_user_username}', client_id {$client_user_client_id}. Error: {$error_detail}");
                        }
                    } catch (Exception $e) {
                        $error_msg = "Database error occurred. " . $e->getMessage();
                        log_activity("CLIENT_USER_FLOW [UPDATE]: Exception occurred while updating client_user with user_id {$edit_client_user_id}, username '{$client_user_username}', client_id {$client_user_client_id}. Exception: " . $e->getMessage());
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    log_activity("CLIENT_USER_FLOW [UPDATE]: Failed to prepare UPDATE statement with password for user_id {$edit_client_user_id}, client_id {$client_user_client_id}. Error: " . mysqli_error($conn));
                }
            } else {
                // Update without changing password - ensure user_type remains 'client'
                // Log: Before executing UPDATE query without password change
                log_activity("CLIENT_USER_FLOW [UPDATE]: Executing UPDATE query without password change for user_id {$edit_client_user_id}, username '{$client_user_username}', email '{$client_user_email}', client_id {$client_user_client_id}");
                
                $sql = "UPDATE users SET username = ?, name = ?, email = ?, user_type = 'client', manager_id = ?, joining_date = ?, date_of_birth = ? WHERE id = ?";
                if($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssssissi", $client_user_username, $client_user_name, $client_user_email, $client_user_client_id, $client_user_joining_date_db, $client_user_date_of_birth_db, $edit_client_user_id);
                    
                    try {
                        if(mysqli_stmt_execute($stmt)) {
                            $success_msg = "Client user updated successfully!";
                            log_activity("CLIENT_USER_FLOW [UPDATE]: Successfully updated client_user with user_id {$edit_client_user_id}, username '{$client_user_username}', email '{$client_user_email}', name '{$client_user_name}', client_id {$client_user_client_id} (password unchanged)");
                            // Clear form data
                            $client_user_username = $client_user_name = $client_user_email = $client_user_password = $client_user_confirm_password = $client_user_client_id = $client_user_department_id = $client_user_joining_date = $client_user_date_of_birth = "";
                        } else {
                            $error_msg = "Something went wrong. Please try again later. Error: " . mysqli_stmt_error($stmt);
                            $error_detail = mysqli_stmt_error($stmt);
                            log_activity("CLIENT_USER_FLOW [UPDATE]: Failed to update client_user with user_id {$edit_client_user_id}, username '{$client_user_username}', client_id {$client_user_client_id}. Error: {$error_detail}");
                        }
                    } catch (Exception $e) {
                        $error_msg = "Database error occurred. " . $e->getMessage();
                        log_activity("CLIENT_USER_FLOW [UPDATE]: Exception occurred while updating client_user with user_id {$edit_client_user_id}, username '{$client_user_username}', client_id {$client_user_client_id}. Exception: " . $e->getMessage());
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    log_activity("CLIENT_USER_FLOW [UPDATE]: Failed to prepare UPDATE statement without password for user_id {$edit_client_user_id}, client_id {$client_user_client_id}. Error: " . mysqli_error($conn));
                }
            }
        } else {
            // Create client user - manager_id is set to the client account ID
            // Client Users must always have user_type = 'client' (hardcoded to ensure it's always set)
            // Log: Before creating new client_user
            log_activity("CLIENT_USER_FLOW [CREATE]: Preparing to create new client_user - username '{$client_user_username}', name '{$client_user_name}', email '{$client_user_email}', client_id {$client_user_client_id}, joining_date: " . ($client_user_joining_date_db ?? 'NULL') . ", date_of_birth: " . ($client_user_date_of_birth_db ?? 'NULL'));
            
            $sql = "INSERT INTO users (username, name, email, password, department_id, user_type, manager_id, joining_date, date_of_birth) VALUES (?, ?, ?, ?, NULL, 'client', ?, ?, ?)";
            
            if($stmt = mysqli_prepare($conn, $sql)) {
                $param_password = password_hash($client_user_password, PASSWORD_DEFAULT);
                
                mysqli_stmt_bind_param($stmt, "ssssiss", $client_user_username, $client_user_name, $client_user_email, $param_password, $client_user_client_id, $client_user_joining_date_db, $client_user_date_of_birth_db);
                
                // Log: Before executing INSERT query
                log_activity("CLIENT_USER_FLOW [CREATE]: Executing INSERT query for username '{$client_user_username}', email '{$client_user_email}', name '{$client_user_name}', client_id {$client_user_client_id}");
                
                try {
                    if(mysqli_stmt_execute($stmt)) {
                        $new_user_id = mysqli_insert_id($conn);
                        $success_msg = "Client user created successfully!";
                        log_activity("CLIENT_USER_FLOW [CREATE]: Successfully created client_user with user_id {$new_user_id}, username '{$client_user_username}', email '{$client_user_email}', name '{$client_user_name}', client_id {$client_user_client_id}, joining_date: " . ($client_user_joining_date_db ?? 'NULL') . ", date_of_birth: " . ($client_user_date_of_birth_db ?? 'NULL'));
                        // Clear form data
                        $client_user_username = $client_user_name = $client_user_email = $client_user_password = $client_user_confirm_password = $client_user_client_id = $client_user_department_id = $client_user_joining_date = $client_user_date_of_birth = "";
                    } else {
                        $error_msg = "Something went wrong. Please try again later. Error: " . mysqli_stmt_error($stmt);
                        $error_detail = mysqli_stmt_error($stmt);
                        log_activity("CLIENT_USER_FLOW [CREATE]: Failed to create client_user - username '{$client_user_username}', email '{$client_user_email}', client_id {$client_user_client_id}. Error: {$error_detail}");
                    }
                } catch (Exception $e) {
                    $error_msg = "Database error occurred. ";
                    $exception_msg = $e->getMessage();
                    
                    if(strpos($exception_msg, 'Duplicate entry') !== false && strpos($exception_msg, 'username') !== false) {
                        $client_user_username_err = "This username is already taken.";
                        $error_msg = "Username already exists. Please choose a different username.";
                        log_activity("CLIENT_USER_FLOW [CREATE]: Duplicate username error - username '{$client_user_username}' already exists. Client_id: {$client_user_client_id}");
                    } elseif(strpos($exception_msg, 'Duplicate entry') !== false && strpos($exception_msg, 'email') !== false) {
                        $client_user_email_err = "This email is already registered.";
                        $error_msg = "Email already exists. Please choose a different email.";
                        log_activity("CLIENT_USER_FLOW [CREATE]: Duplicate email error - email '{$client_user_email}' already exists. Client_id: {$client_user_client_id}");
                    } else {
                        $error_msg .= "Error: " . $exception_msg;
                        log_activity("CLIENT_USER_FLOW [CREATE]: Exception occurred while creating client_user - username '{$client_user_username}', email '{$client_user_email}', client_id {$client_user_client_id}. Exception: {$exception_msg}");
                    }
                }
                
                mysqli_stmt_close($stmt);
            } else {
                log_activity("CLIENT_USER_FLOW [CREATE]: Failed to prepare INSERT statement for username '{$client_user_username}', email '{$client_user_email}', client_id {$client_user_client_id}. Error: " . mysqli_error($conn));
            }
        }
    } else {
        // Log: Validation failed
        $validation_errors = [];
        if(!empty($client_user_username_err)) $validation_errors[] = "username: {$client_user_username_err}";
        if(!empty($client_user_name_err)) $validation_errors[] = "name: {$client_user_name_err}";
        if(!empty($client_user_email_err)) $validation_errors[] = "email: {$client_user_email_err}";
        if(!empty($client_user_client_id_err)) $validation_errors[] = "client_id: {$client_user_client_id_err}";
        if(!empty($client_user_joining_date_err)) $validation_errors[] = "joining_date: {$client_user_joining_date_err}";
        if(!empty($client_user_password_err)) $validation_errors[] = "password: {$client_user_password_err}";
        if(!empty($client_user_confirm_password_err)) $validation_errors[] = "confirm_password: {$client_user_confirm_password_err}";
        $error_summary = !empty($validation_errors) ? implode(", ", $validation_errors) : "unknown validation error";
        log_activity("CLIENT_USER_FLOW [{$is_edit}]: Validation failed in manage_users.php. Errors: {$error_summary}");
    }
}

// Get all departments for dropdown
$departments = array();
$sql = "SELECT id, name FROM departments ORDER BY name";
$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }
}

// Get all Manager and Admin users for manager dropdown
$managers = array();
$sql_managers = "SELECT id, name FROM users WHERE user_type IN ('manager', 'admin') ORDER BY name";
$result_managers = mysqli_query($conn, $sql_managers);
if($result_managers) {
    while($row = mysqli_fetch_assoc($result_managers)) {
        $managers[] = $row;
    }
} else {
    $managers_error = "Error loading managers: " . mysqli_error($conn);
}

// Get all users for Team section (exclude Client Accounts AND Client Users)
// Client Accounts: user_type = 'client', password = '' or NULL
// Client Users: user_type = 'client', password = hashed, manager_id = client account id
// Team section should only show: admin, manager, doer (NOT client accounts or client users)
$users = array();
// Explicitly include only team user types to avoid NULL/empty user_type issues
$sql = "SELECT u.*, d.name as department_name, COALESCE(u.Status, 'Active') as Status 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE u.user_type IN ('admin', 'manager', 'doer')
        ORDER BY u.user_type, u.name";
$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

// Count users by type
$admin_count = $manager_count = $doer_count = $client_count = 0;
$sql = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
$result = mysqli_query($conn, $sql);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        switch($row['user_type']) {
            case 'admin':
                $admin_count = $row['count'];
                break;
            case 'manager':
                $manager_count = $row['count'];
                break;
            case 'doer':
                $doer_count = $row['count'];
                break;
            case 'client':
                $client_count = $row['count'];
                break;
        }
    }
}

// Count users by status (Active and Inactive)
$active_count = $inactive_count = 0;
$status_sql = "SELECT Status, COUNT(*) as count FROM users GROUP BY Status";
$status_result = mysqli_query($conn, $status_sql);
if($status_result) {
    while($status_row = mysqli_fetch_assoc($status_result)) {
        $status = ucfirst(strtolower(trim($status_row['Status'] ?? 'Active')));
        if($status === 'Active') {
            $active_count = $status_row['count'];
        } elseif($status === 'Inactive') {
            $inactive_count = $status_row['count'];
        }
    }
}
// If no status found, default active count to total users
if($active_count == 0 && $inactive_count == 0) {
    $active_count = $admin_count + $manager_count + $doer_count + $client_count;
}

// Calculate team-specific statistics (excluding clients)
$total_team = $admin_count + $manager_count + $doer_count;
$team_active_count = 0;
$team_inactive_count = 0;
$team_status_sql = "SELECT Status, COUNT(*) as count FROM users WHERE user_type IN ('admin', 'manager', 'doer') GROUP BY Status";
$team_status_result = mysqli_query($conn, $team_status_sql);
if($team_status_result) {
    while($team_status_row = mysqli_fetch_assoc($team_status_result)) {
        $status = ucfirst(strtolower(trim($team_status_row['Status'] ?? 'Active')));
        if($status === 'Active') {
            $team_active_count = $team_status_row['count'];
        } elseif($status === 'Inactive') {
            $team_inactive_count = $team_status_row['count'];
        }
    }
}
// If no team status found, default active count to total team
if($team_active_count == 0 && $team_inactive_count == 0) {
    $team_active_count = $total_team;
}

// Calculate client statistics (for stats card)
$total_clients_for_stats = 0;
$active_clients_for_stats = 0;
$inactive_clients_for_stats = 0;
$total_client_users_for_stats = 0;
$active_client_users_for_stats = 0;
$inactive_client_users_for_stats = 0;

if($can_manage_clients) {
    // Count client accounts (exclude client users - they have non-empty password)
    // Client Accounts: user_type = 'client', password = '' (empty) or NULL
    $clients_stats_query = "SELECT COUNT(*) as total, 
                           SUM(CASE WHEN COALESCE(Status, 'Active') = 'Active' THEN 1 ELSE 0 END) as active,
                           SUM(CASE WHEN COALESCE(Status, 'Active') = 'Inactive' THEN 1 ELSE 0 END) as inactive
                           FROM users 
                           WHERE user_type = 'client'
                           AND (password IS NULL OR password = '')";
    $clients_stats_result = mysqli_query($conn, $clients_stats_query);
    if($clients_stats_result && $row = mysqli_fetch_assoc($clients_stats_result)) {
        $total_clients_for_stats = (int)$row['total'];
        $active_clients_for_stats = (int)$row['active'];
        $inactive_clients_for_stats = (int)$row['inactive'];
    }
    
    // Count client users (exclude client accounts - they have empty password)
    // Client Users: user_type = 'client', password is NOT NULL and NOT empty, manager_id points to client account
    $client_users_stats_query = "SELECT COUNT(*) as total,
                                SUM(CASE WHEN COALESCE(u.Status, 'Active') = 'Active' THEN 1 ELSE 0 END) as active,
                                SUM(CASE WHEN COALESCE(u.Status, 'Active') = 'Inactive' THEN 1 ELSE 0 END) as inactive
                                FROM users u 
                                INNER JOIN users c ON u.manager_id = c.id AND c.user_type = 'client' AND (c.password IS NULL OR c.password = '')
                                WHERE u.user_type = 'client'
                                AND u.password IS NOT NULL 
                                AND u.password != ''";
    // If Manager, only count client users whose parent client account is assigned to them
    if($is_manager && !$is_admin) {
        $current_manager_id = $_SESSION['id'];
        $client_users_stats_query .= " AND c.manager_id = $current_manager_id";
    }
    $client_users_stats_result = mysqli_query($conn, $client_users_stats_query);
    if($client_users_stats_result && $row = mysqli_fetch_assoc($client_users_stats_result)) {
        $total_client_users_for_stats = (int)$row['total'];
        $active_client_users_for_stats = (int)$row['active'];
        $inactive_client_users_for_stats = (int)$row['inactive'];
    }
}
?>

<!-- HTML structure is handled by header.php -->
    
    <style>
    /* Hide modal backdrop for all modals on this page */
    .modal-backdrop {
        display: none !important;
    }
    
    /* Ensure modals display without fade animation */
    .modal {
        transition: none !important;
    }
    
    .modal.show {
        display: block !important;
    }
    
    /* Table Container - No horizontal scroll */
    .table-responsive {
        overflow-x: visible !important;
        font-size: 0.85rem;
        width: 100%;
    }
    
    /* Table styling - optimized for all columns visibility */
    .table-sm {
        width: 100%;
        table-layout: auto;
        font-size: 0.85rem;
        margin-bottom: 0;
    }
    
    .table-sm th, .table-sm td {
        padding: 6px 8px !important;
        vertical-align: middle;
        white-space: nowrap;
        overflow: visible;
        text-overflow: clip;
    }
    
    /* Column widths - optimized to fit all columns without horizontal scroll */
    .table-sm th:nth-child(1), .table-sm td:nth-child(1) { /* Username */
        width: 9%;
    }
    
    .table-sm th:nth-child(2), .table-sm td:nth-child(2) { /* Name */
        width: 11%;
    }
    
    .table-sm th:nth-child(3), .table-sm td:nth-child(3) { /* Email */
        width: 14%;
    }
    
    .table-sm th:nth-child(4), .table-sm td:nth-child(4) { /* Department */
        width: 12%;
    }
    
    .table-sm th:nth-child(5), .table-sm td:nth-child(5) { /* Profile */
        width: 7%;
    }
    
    .table-sm th:nth-child(6), .table-sm td:nth-child(6) { /* Manager */
        width: 11%;
    }
    
    .table-sm th:nth-child(7), .table-sm td:nth-child(7) { /* Joining Date */
        width: 9%;
    }
    
    .table-sm th:nth-child(8), .table-sm td:nth-child(8) { /* Date of Birth */
        width: 9%;
    }
    
    .table-sm th:nth-child(9), .table-sm td:nth-child(9) { /* Status */
        width: 8%;
    }
    
    .table-sm th:nth-child(10), .table-sm td:nth-child(10) { /* Actions */
        width: 10%;
    }
    
    /* Table header styling - no wrapping, no ellipsis */
    .table-sm thead th {
        background: linear-gradient(135deg, var(--dark-bg-secondary), var(--dark-bg-primary)) !important;
        color: var(--dark-text-primary) !important;
        font-weight: 600 !important;
        font-size: 0.875rem !important;
        white-space: nowrap !important;
        text-overflow: clip !important;
        overflow: visible !important;
        padding: 10px 8px !important;
        border-bottom: 2px solid var(--glass-border) !important;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    /* Table body cells - allow text wrapping for long content */
    .table-sm tbody td {
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: break-word;
        font-size: 0.85rem;
    }
    
    /* Email column - allow wrapping for long emails */
    .table-sm tbody td:nth-child(3) {
        white-space: normal;
        word-break: break-all;
        max-width: 200px;
    }
    
    /* Department and Manager columns - allow wrapping */
    .table-sm tbody td:nth-child(4),
    .table-sm tbody td:nth-child(6) {
        white-space: normal;
        word-wrap: break-word;
    }

    .table-sm .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        margin: 1px;
    }

    .table-sm .badge {
        font-size: 0.7em;
        padding: 0.35em 0.65em;
        white-space: nowrap;
    }
    
    .user-status-dropdown {
        min-width: 90px !important;
        width: 100% !important;
        font-size: 0.8rem !important;
        padding: 0.25rem 0.5rem !important;
    }
    
    /* Ensure table fits container */
    .card-body {
        padding: 1rem;
        overflow-x: visible;
    }
    
    /* Responsive adjustments for smaller screens */
    @media (max-width: 1400px) {
        .table-sm {
            font-size: 0.8rem;
        }
        
        .table-sm th, .table-sm td {
            padding: 5px 6px !important;
        }
        
        .table-sm thead th {
            font-size: 0.8rem !important;
        }
    }
    
    @media (max-width: 1200px) {
        .table-sm {
            font-size: 0.75rem;
        }
        
        .table-sm th, .table-sm td {
            padding: 4px 5px !important;
        }
        
        .user-status-dropdown {
            font-size: 0.75rem !important;
            padding: 0.2rem 0.4rem !important;
        }
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
}

/* Date picker clickable enhancement */
.date-picker-clickable {
    cursor: pointer;
    position: relative;
}

/* Make the calendar icon white and keep it visible on the right side (normal position and size) */
.date-picker-clickable::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 1;
    filter: invert(1); /* Makes the calendar icon white */
    /* Keep default position and size - just make it white */
}

.date-picker-clickable::-webkit-calendar-picker-indicator:hover {
    opacity: 0.9;
    filter: invert(1); /* Keeps the calendar icon white on hover */
}

/* Make the text area also clickable */
.date-picker-clickable::-webkit-datetime-edit {
    cursor: pointer;
    width: 100%;
    position: relative;
    z-index: 1;
}

.date-picker-clickable::-webkit-datetime-edit-fields-wrapper {
    cursor: pointer;
}

/* For Firefox - make entire field clickable and white icon */
@-moz-document url-prefix() {
    .date-picker-clickable {
        cursor: pointer;
    }
    
    .date-picker-clickable::-webkit-calendar-picker-indicator {
        filter: invert(1);
    }
}

/* User Statistics Cards - Glassmorphism Style (Same as holiday_list.php) */
.user-stats-section {
    margin-bottom: 2rem;
}

.user-stats-container {
    display: flex;
    gap: 0.75rem;
    flex-wrap: nowrap;
    width: 100%;
}

.stat-item {
    background: var(--dark-bg-glass);
    padding: 1rem 0.75rem;
    border-radius: var(--radius-md);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    color: var(--dark-text-primary);
    flex: 1;
    min-width: 0;
    transition: all 0.3s ease;
    box-shadow: var(--glass-shadow);
    text-align: center;
}

.stat-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    border-color: var(--brand-primary);
}

.stat-number {
    font-size: 1.75rem;
    font-weight: bold;
    display: block;
    color: var(--brand-primary);
    margin-bottom: 0.25rem;
    background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-accent) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.75rem;
    opacity: 0.9;
    color: var(--dark-text-secondary);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    line-height: 1.2;
}

.stat-sub-label {
    font-size: 0.65rem;
    color: var(--dark-text-secondary);
    margin-top: 0.25rem;
    display: block;
    font-weight: 400;
    text-transform: none;
    letter-spacing: normal;
    line-height: 1.3;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-label {
        font-size: 0.7rem;
    }
    
    .stat-sub-label {
        font-size: 0.6rem;
    }
    
    .stat-item {
        padding: 0.875rem 0.5rem;
    }
    
    .user-stats-container {
        gap: 0.5rem;
    }
}

@media (max-width: 768px) {
    .user-stats-container {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .stat-item {
        padding: 0.75rem 0.5rem;
        min-width: calc(50% - 0.25rem);
        flex: 0 0 calc(50% - 0.25rem);
    }
    
    .stat-number {
        font-size: 1.25rem;
    }
    
    .stat-label {
        font-size: 0.65rem;
    }
    
    .stat-sub-label {
        font-size: 0.55rem;
    }
}

/* Collapsible Add User Form Styles - Smooth Animation */
.add-user-form-content {
    display: block;
    overflow: hidden;
    max-height: 2000px; /* Large enough to accommodate form content */
    opacity: 1;
    transition: max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                opacity 0.25s ease-in-out,
                padding-top 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                padding-bottom 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateZ(0); /* Force hardware acceleration */
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.add-user-form-content.collapsed {
    max-height: 0;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    opacity: 0;
    transition: max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                opacity 0.25s ease-in-out,
                padding-top 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                padding-bottom 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Client Account Form Content - Optimized for smooth animation */
.add-client-form-content {
    display: block;
    overflow: hidden;
    max-height: 600px; /* Reasonable height for form */
    opacity: 1;
    transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1),
                padding-top 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                padding-bottom 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: max-height, opacity;
    transform: translateZ(0);
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    -webkit-transform: translateZ(0);
}

.add-client-form-content.collapsed {
    max-height: 0;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    opacity: 0;
    transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1),
                padding-top 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                padding-bottom 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.add-client-toggle-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    transition: all 0.3s ease;
}

.add-client-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
}

.add-client-toggle-btn:focus {
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
}

/* Client User Form Content - Optimized for smooth animation */
.add-client-user-form-content {
    display: block;
    overflow: hidden;
    max-height: 600px;
    opacity: 1;
    transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1),
                padding-top 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                padding-bottom 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: max-height, opacity;
    transform: translateZ(0);
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    -webkit-transform: translateZ(0);
}

.add-client-user-form-content.collapsed {
    max-height: 0;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    opacity: 0;
    transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1),
                padding-top 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                padding-bottom 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.add-client-user-toggle-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    transition: all 0.3s ease;
}

.add-client-user-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
}

.add-client-user-toggle-btn:focus {
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
}
                opacity 0.2s ease-in-out,
                padding-top 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                padding-bottom 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.add-user-toggle-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 4px;
    transition: all 0.3s ease;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.add-user-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    transform: translateY(-1px);
}

.add-user-toggle-btn:focus {
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
}

/* User Status Toggle in Table Header */
.user-status-toggle-header {
    display: flex;
    align-items: center;
}

.user-status-toggle-header .btn-group {
    display: flex;
    border-radius: 4px;
    overflow: hidden;
}

.user-status-toggle-header .btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
    transition: all 0.3s ease;
    cursor: pointer;
    white-space: nowrap;
}

.user-status-toggle-header .btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
}

.user-status-toggle-header .btn.active,
.user-status-toggle-header .btn:focus {
    background: rgba(255, 255, 255, 0.9);
    color: #007bff;
    border-color: rgba(255, 255, 255, 0.5);
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
}

.user-status-toggle-header .btn input[type="radio"] {
    display: none;
}

/* Smooth row filtering animation */
#usersTableBody tr {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

#usersTableBody tr.hidden-row {
    display: none;
}

/* Toggle Section Styles */
.section-toggle-container {
    background: var(--dark-bg-glass);
    padding: 1rem;
    border-radius: var(--radius-md);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    margin-bottom: 2rem;
    box-shadow: var(--glass-shadow);
}

.section-toggle-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.section-toggle-btn {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--dark-text-secondary);
    padding: 0.5rem 1.5rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 0.9rem;
}

.section-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.3);
    color: var(--dark-text-primary);
}

.section-toggle-btn.active {
    background: var(--brand-primary);
    border-color: var(--brand-primary);
    color: white;
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
}

.section-content {
    display: none;
}

.section-content.active {
    display: block;
}

/* Optimize Manage Clients Section */
#section-clients {
    margin: 0;
    padding: 0;
    width: 100%;
    display: none !important; /* Hidden marker div for JS reference */
}

/* Client section content - show/hide based on section */
.client-section-content {
    display: none;
}

.client-section-content.active {
    display: block;
}

/* Client Account Form - match statistics card margins and width */
#section-clients > .card.mb-4 {
    margin-bottom: 1.5rem !important;
    width: 100%;
    margin-left: 0;
    margin-right: 0;
}

/* Manage Clients Table Card - match statistics card margins and width */
#section-clients > .card.mb-0 {
    width: 100%;
    margin-left: 0;
    margin-right: 0;
}

#section-clients .card-body {
    padding: 0.75rem !important;
}

#section-clients .table-responsive {
    margin: 0;
    padding: 0;
}

#section-clients .table {
    margin-bottom: 0 !important;
}

/* Client View Content Toggle */
.client-view-content {
    display: none;
}

.client-view-content.active {
    display: block;
}

/* Stats Content Toggle */
.stats-content {
    display: none;
    width: 100%;
}

.stats-content.active {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

/* Radio Button Toggle in Table Header */
.client-radio-toggle {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    padding: 0.25rem;
}

.client-radio-label {
    cursor: pointer;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    transition: all 0.3s ease;
    background: transparent;
    color: rgba(255, 255, 255, 0.8);
    border: none;
    outline: none;
    margin: 0;
    font-size: 0.9rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.client-radio-label:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.client-radio-label.active {
    background: rgba(255, 255, 255, 0.95);
    color: #007bff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.client-radio-label input[type="radio"] {
    display: none;
}

.client-radio-label i {
    font-size: 0.85rem;
}

</style>
    <!-- Content will be wrapped by header.php -->
    <div class="content-area">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0"><?php echo htmlspecialchars($page_title); ?></h2>
            <div id="headerActionButtons">
                <?php if($is_admin): ?>
                    <button type="button" id="newUserBtn" class="btn btn-primary" onclick="openNewUserModal()" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border: none; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4); <?php echo $active_section === 'team' ? '' : 'display: none;'; ?>">
                        <i class="fas fa-plus"></i> New User
                    </button>
                    <button type="button" id="newClientAccountBtn" class="btn btn-primary" onclick="openNewClientAccountModal()" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border: none; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4); <?php echo $active_section === 'clients' ? '' : 'display: none;'; ?>">
                        <i class="fas fa-plus"></i> New Client Account
                    </button>
                <?php endif; ?>
                <?php if($is_manager || $is_admin): ?>
                    <?php if($is_admin): ?>
                        <button type="button" id="newClientUserBtn" class="btn btn-primary" onclick="openNewClientUserModal()" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border: none; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4); <?php echo $active_section === 'clients' ? '' : 'display: none;'; ?>">
                            <i class="fas fa-plus"></i> New Client User
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary" onclick="openNewClientUserModal()" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border: none; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);">
                            <i class="fas fa-plus"></i> New Client User
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
                
        <!-- Section Toggle (Only show if user has access to multiple sections) -->
        <?php if($can_manage_team && $can_manage_clients): ?>
        <div class="section-toggle-container">
            <div class="section-toggle-buttons">
                <?php if($can_manage_team): ?>
                    <button class="section-toggle-btn <?php echo $active_section === 'team' ? 'active' : ''; ?>" 
                            data-section="team" 
                            onclick="switchSection('team')">
                        <i class="fas fa-users"></i> Manage Team
                    </button>
                <?php endif; ?>
                <?php if($can_manage_clients): ?>
                    <button class="section-toggle-btn <?php echo $active_section === 'clients' ? 'active' : ''; ?>" 
                            data-section="clients" 
                            onclick="switchSection('clients')">
                        <i class="fas fa-building"></i> Manage Clients
                    </button>
                <?php endif; ?>
            </div>
        </div>
                <?php endif; ?>

        <!-- Overall Statistics Card -->
        <div class="card mb-4" style="background: var(--dark-bg-glass); border: 1px solid var(--glass-border); backdrop-filter: var(--glass-blur); box-shadow: var(--glass-shadow);">
            <div class="card-body">
                <h5 class="card-title mb-3" style="color: var(--dark-text-primary);">
                    <i class="fas fa-chart-bar"></i> <span id="stats-title">Statistics</span>
                </h5>
            <div class="user-stats-container">
                    <!-- Team Statistics (shown when Manage Team is active) -->
                    <div id="team-stats" class="stats-content <?php echo $active_section === 'team' ? 'active' : ''; ?>">
                <div class="stat-item">
                            <span class="stat-number"><?php echo $total_team; ?></span>
                            <span class="stat-label">Total Team</span>
                    <div class="stat-sub-label">
                                <span style="color: #28a745;">Active: <?php echo $team_active_count; ?></span> | 
                                <span style="color: #dc3545;">Inactive: <?php echo $team_inactive_count; ?></span>
                    </div>
                </div>
                <div class="stat-item">
                            <span class="stat-number"><?php echo $admin_count; ?></span>
                    <span class="stat-label">Admins</span>
                </div>
                <div class="stat-item">
                            <span class="stat-number"><?php echo $manager_count; ?></span>
                    <span class="stat-label">Managers</span>
                </div>
                <div class="stat-item">
                            <span class="stat-number"><?php echo $doer_count; ?></span>
                    <span class="stat-label">Doers</span>
            </div>
        </div>
        
                    <!-- Client Statistics (shown when Manage Clients is active) -->
                    <?php if($can_manage_clients): ?>
                    <div id="client-stats" class="stats-content <?php echo $active_section === 'clients' ? 'active' : ''; ?>">
                <div class="stat-item">
                            <span class="stat-number"><?php echo $total_clients_for_stats; ?></span>
                            <span class="stat-label">Total Client Accounts</span>
                            <div class="stat-sub-label">
                                <span style="color: #28a745;">Active: <?php echo $active_clients_for_stats; ?></span> | 
                                <span style="color: #dc3545;">Inactive: <?php echo $inactive_clients_for_stats; ?></span>
                    </div>
                            </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $total_client_users_for_stats; ?></span>
                            <span class="stat-label">Total Client Users</span>
                            <div class="stat-sub-label">
                                <span style="color: #28a745;">Active: <?php echo $active_client_users_for_stats; ?></span> | 
                                <span style="color: #dc3545;">Inactive: <?php echo $inactive_client_users_for_stats; ?></span>
                            </div>
                            </div>
                            </div>
                    <?php endif; ?>
                            </div>
                        </div>
                            </div>
                            
        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error_msg)): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                                    <?php endif; ?>
        
        <!-- Manage Team Section (Admin Only) -->
        <?php if($can_manage_team): ?>
            <div id="section-team" class="section-content <?php echo $active_section === 'team' ? 'active' : ''; ?>">
            
        <!-- All Users Table -->
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">All Users</h4>
                        <div class="user-status-toggle-header">
                            <div class="btn-group btn-group-toggle" data-toggle="buttons" style="display: flex;">
                                <label class="btn btn-sm btn-light active" id="toggleActive" style="border-radius: 4px 0 0 4px; margin: 0;">
                                    <input type="radio" name="user_status_filter" value="Active" autocomplete="off" checked> Active
                                </label>
                                <label class="btn btn-sm btn-light" id="toggleInactive" style="border-radius: 0 4px 4px 0; margin: 0; border-left: 1px solid rgba(0,0,0,0.1);">
                                    <input type="radio" name="user_status_filter" value="Inactive" autocomplete="off"> Inactive
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" style="overflow-x: visible; width: 100%;">
                        <?php if(empty($users)): ?>
                            <div class="alert alert-info">No users found in the system.</div>
                        <?php else: ?>
                            <div class="table-responsive" style="overflow-x: visible;">
                                <table class="table table-bordered table-striped table-sm" style="width: 100%; table-layout: auto;">
                                    <thead>
                                        <tr>
                                            <th style="white-space: nowrap;">Username</th>
                                            <th style="white-space: nowrap;">Name</th>
                                            <th style="white-space: nowrap;">Email</th>
                                            <th style="white-space: nowrap;">Department</th>
                                            <th style="white-space: nowrap;">Profile</th>
                                            <th style="white-space: nowrap;">Manager</th>
                                            <th style="white-space: nowrap;">Joining Date</th>
                                            <th style="white-space: nowrap;">Date of Birth</th>
                                            <th style="white-space: nowrap;">Status</th>
                                            <th style="white-space: nowrap;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="usersTableBody">
                                        <?php foreach($users as $user): ?>
                                            <?php 
                                            // Get current status from database (handle both 'Status' and 'status' column names)
                                            $row_status = 'Active'; // Default
                                            if (isset($user['Status']) && !empty($user['Status'])) {
                                                $row_status = $user['Status'];
                                            } elseif (isset($user['status']) && !empty($user['status'])) {
                                                $row_status = $user['status'];
                                            }
                                            // Normalize status value (handle case variations)
                                            $row_status = ucfirst(strtolower(trim($row_status)));
                                            if ($row_status !== 'Active' && $row_status !== 'Inactive') {
                                                $row_status = 'Active';
                                            }
                                            ?>
                                            <tr data-user-status="<?php echo htmlspecialchars($row_status); ?>">
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['department_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    $badge_class = '';
                                                    switch($user['user_type']) {
                                                        case 'admin':
                                                            $badge_class = 'badge-danger';
                                                            break;
                                                        case 'manager':
                                                            $badge_class = 'badge-primary';
                                                            break;
                                                        case 'doer':
                                                            $badge_class = 'badge-success';
                                                            break;
                                                        case 'client':
                                                            $badge_class = 'badge-warning';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($user['user_type']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['manager'] ?? 'N/A'); ?></td>
                                        <td><?php echo $user['joining_date'] ? date('d-m-Y', strtotime($user['joining_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $user['date_of_birth'] ? date('d-m-Y', strtotime($user['date_of_birth'])) : 'N/A'; ?></td>
                                                <td>
                                                    <?php 
                                                    // Use the row_status we already calculated above
                                                    $current_status = $row_status;
                                                    ?>
                                                    <select class="form-control form-control-sm user-status-dropdown" 
                                                            data-user-id="<?php echo $user['id']; ?>" 
                                                            data-original-status="<?php echo htmlspecialchars($current_status); ?>">
                                                        <option value="Active" <?php echo ($current_status == 'Active') ? 'selected' : ''; ?>>Active</option>
                                                        <option value="Inactive" <?php echo ($current_status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info edit-user-btn" title="Edit User" 
                                                            data-user-id="<?php echo $user['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>"
                                                            data-name="<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>"
                                                            data-email="<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>"
                                                            data-department-id="<?php echo $user['department_id']; ?>"
                                                            data-user-type="<?php echo htmlspecialchars($user['user_type'], ENT_QUOTES); ?>"
                                                            data-manager="<?php echo htmlspecialchars($user['manager'] ?? 'Shubham Tyagi', ENT_QUOTES); ?>"
                                                            data-joining-date="<?php echo $user['joining_date'] ? htmlspecialchars($user['joining_date'], ENT_QUOTES) : ''; ?>"
                                                            data-date-of-birth="<?php echo $user['date_of_birth'] ? htmlspecialchars($user['date_of_birth'], ENT_QUOTES) : ''; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    
                                                    <?php if($user['id'] != $_SESSION['id']): // Don't allow deleting own account ?>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="delete_user" value="1">
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete User">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
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
<!-- End Manage Team Section -->
        <?php endif; ?>
        
        <!-- Manage Clients Section (Admin & Manager) -->
        <?php if($can_manage_clients): ?>
            <div id="section-clients" class="section-content <?php echo $active_section === 'clients' ? 'active' : ''; ?>" style="display: none;"></div>
            
                                            <?php 
                // Get client accounts (exclude client users - they have non-empty password)
                // Client Accounts: user_type = 'client', password = '' (empty) or NULL
                // Client Users: user_type = 'client', password = hashed (not empty), manager_id = client account id
                $clients_query = "SELECT u.*, d.name as department_name, 
                                  COALESCE(u.Status, 'Active') as Status,
                                  (SELECT COUNT(*) FROM users u2 WHERE u2.manager_id = u.id AND u2.user_type = 'client' AND u2.password IS NOT NULL AND u2.password != '') as user_count
                                  FROM users u 
                                  LEFT JOIN departments d ON u.department_id = d.id 
                                  WHERE u.user_type = 'client'
                                  AND (u.password IS NULL OR u.password = '')";
                
                // If Manager, only show clients assigned to them
                if($is_manager && !$is_admin) {
                    $current_manager_id = $_SESSION['id'];
                    $clients_query .= " AND u.manager_id = $current_manager_id";
                }
                
                $clients_query .= " ORDER BY u.name";
                $clients_result = mysqli_query($conn, $clients_query);
                $clients = array();
                if($clients_result) {
                    while($row = mysqli_fetch_assoc($clients_result)) {
                        $clients[] = $row;
                    }
                }
                
                // Count clients
                $total_clients = count($clients);
                $active_clients = 0;
                $inactive_clients = 0;
                foreach($clients as $client) {
                    $status = ucfirst(strtolower(trim($client['Status'] ?? 'Active')));
                    if($status === 'Active') {
                        $active_clients++;
                    } else {
                        $inactive_clients++;
                    }
                }
                
                // Get all client users (users where manager_id points to a client account)
                // Client Users: user_type = 'client', password is NOT NULL and NOT empty, manager_id points to client account
                $all_client_users_query = "SELECT u.*, d.name as department_name, 
                                          c.name as client_name, c.id as client_id,
                                          COALESCE(u.Status, 'Active') as Status
                                          FROM users u 
                                          LEFT JOIN departments d ON u.department_id = d.id 
                                          LEFT JOIN users c ON u.manager_id = c.id AND c.user_type = 'client' AND (c.password IS NULL OR c.password = '')
                                          WHERE u.user_type = 'client'
                                          AND u.password IS NOT NULL 
                                          AND u.password != ''
                                          AND u.manager_id IN (SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = ''))";
                
                // If Manager, only show client users under their assigned client accounts
                if($is_manager && !$is_admin) {
                    $current_manager_id = $_SESSION['id'];
                    $all_client_users_query .= " AND c.manager_id = $current_manager_id";
                }
                
                $all_client_users_query .= " ORDER BY c.name, u.name";
                $all_client_users_result = mysqli_query($conn, $all_client_users_query);
                $all_client_users = array();
                if($all_client_users_result) {
                    while($row = mysqli_fetch_assoc($all_client_users_result)) {
                        $all_client_users[] = $row;
                    }
                }
                
                // Count client users
                $total_client_users = count($all_client_users);
                $active_client_users = 0;
                $inactive_client_users = 0;
                foreach($all_client_users as $user) {
                    $status = ucfirst(strtolower(trim($user['Status'] ?? 'Active')));
                    if($status === 'Active') {
                        $active_client_users++;
                    } else {
                        $inactive_client_users++;
                    }
                }
                
                // Get active client card from URL parameter
                $active_client_card = isset($_GET['client_card']) ? $_GET['client_card'] : 'accounts';
                ?>
                
                <!-- Clients Table with Radio Toggle -->
                <div class="card mb-0 client-section-content <?php echo $active_section === 'clients' ? 'active' : ''; ?>">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0" id="manageClientsHeading"><?php echo $active_client_card === 'accounts' ? 'Manage Client Accounts' : 'Manage Client Users'; ?></h4>
                            <div class="client-radio-toggle">
                                <label class="client-radio-label <?php echo $active_client_card === 'accounts' ? 'active' : ''; ?>">
                                    <input type="radio" name="client_view" value="accounts" <?php echo $active_client_card === 'accounts' ? 'checked' : ''; ?> onchange="switchClientCard('accounts')" autocomplete="off">
                                    <i class="fas fa-building"></i> Client Accounts
                                </label>
                                <label class="client-radio-label <?php echo $active_client_card === 'users' ? 'active' : ''; ?>">
                                    <input type="radio" name="client_view" value="users" <?php echo $active_client_card === 'users' ? 'checked' : ''; ?> onchange="switchClientCard('users')" autocomplete="off">
                                    <i class="fas fa-users"></i> Client Users
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <!-- Client Accounts View -->
                        <div id="client-accounts-view" class="client-view-content <?php echo $active_client_card === 'accounts' ? 'active' : ''; ?>">
                        <?php if(empty($clients)): ?>
                            <div class="alert alert-info mb-0">No client accounts found.</div>
                        <?php else: ?>
                            <div class="table-responsive" style="overflow-x: auto; max-width: 100%;">
                                <table class="table table-bordered table-striped table-sm mb-0" style="width: 100%; table-layout: auto; margin-bottom: 0;">
                                    <thead>
                                        <tr>
                                                <th style="white-space: nowrap;">Account Name</th>
                                                <th style="white-space: nowrap;">Manager</th>
                                                <th style="white-space: nowrap;">On-Boarding</th>
                                                <th style="white-space: nowrap;">Users</th>
                                                <th style="white-space: nowrap;">Status</th>
                                                <th style="white-space: nowrap;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($clients as $client): ?>
                                            <?php
                                            $client_status = ucfirst(strtolower(trim($client['Status'] ?? 'Active')));
                                            if($client_status !== 'Active' && $client_status !== 'Inactive') {
                                                $client_status = 'Active';
                                            }
                                            $status_badge_class = $client_status === 'Active' ? 'badge-success' : 'badge-danger';
                                            
                                            // Get users under this client
                                            $client_users_query = "SELECT id, username, name, email, user_type, 
                                                                  COALESCE(Status, 'Active') as Status
                                                                  FROM users 
                                                                      WHERE manager_id = ? AND user_type = 'client'
                                                                  ORDER BY name";
                                            $client_users_stmt = mysqli_prepare($conn, $client_users_query);
                                            $client_users = array();
                                            if($client_users_stmt) {
                                                mysqli_stmt_bind_param($client_users_stmt, "i", $client['id']);
                                                mysqli_stmt_execute($client_users_stmt);
                                                $client_users_result = mysqli_stmt_get_result($client_users_stmt);
                                                while($user_row = mysqli_fetch_assoc($client_users_result)) {
                                                    $client_users[] = $user_row;
                                                }
                                                mysqli_stmt_close($client_users_stmt);
                                            }
                                            $actual_user_count = count($client_users);
                                            ?>
                                            <tr>
                                                <td style="white-space: normal;">
                                                    <strong><?php echo htmlspecialchars($client['name']); ?></strong>
                                                </td>
                                                    <td style="white-space: normal;"><?php echo htmlspecialchars($client['manager'] ?? 'N/A'); ?></td>
                                                <td style="white-space: nowrap;">
                                                        <?php echo $client['joining_date'] ? date('d-m-Y', strtotime($client['joining_date'])) : 'N/A'; ?>
                                                </td>
                                                <td style="white-space: nowrap; text-align: center;">
                                                    <?php echo isset($client['user_count']) ? intval($client['user_count']) : 0; ?>
                                                </td>
                                                <td style="white-space: nowrap;">
                                                        <?php if($is_admin): ?>
                                                        <!-- Admin can edit client account status -->
                                                        <select class="form-control form-control-sm user-status-dropdown" 
                                                                data-user-id="<?php echo $client['id']; ?>" 
                                                                data-user-type="client_account"
                                                                data-original-status="<?php echo htmlspecialchars($client_status); ?>">
                                                            <option value="Active" <?php echo ($client_status == 'Active') ? 'selected' : ''; ?>>Active</option>
                                                            <option value="Inactive" <?php echo ($client_status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                        </select>
                                                        <?php else: ?>
                                                        <!-- Manager can only view client account status -->
                                                        <span class="badge <?php echo $status_badge_class; ?>">
                                                            <?php echo $client_status; ?>
                                                        </span>
                                                        <?php endif; ?>
                                                </td>
                                                <td style="white-space: nowrap;">
                                                        <?php if($is_admin): // Only Admin can edit/delete client accounts ?>
                                                        <button type="button" class="btn btn-sm btn-info edit-client-account-btn" title="Edit Client Account" 
                                                                data-client-id="<?php echo $client['id']; ?>"
                                                                data-client-username="<?php echo htmlspecialchars($client['username'], ENT_QUOTES); ?>"
                                                                data-client-name="<?php echo htmlspecialchars($client['name'], ENT_QUOTES); ?>"
                                                                data-client-manager="<?php echo htmlspecialchars($client['manager'] ?? 'Shubham Tyagi', ENT_QUOTES); ?>"
                                                                data-client-joining-date="<?php echo $client['joining_date'] ? htmlspecialchars($client['joining_date'], ENT_QUOTES) : ''; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this client account?');">
                                                            <input type="hidden" name="user_id" value="<?php echo $client['id']; ?>">
                                                            <input type="hidden" name="delete_user" value="1">
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Client Account">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                        
                                                    <a href="manage_client_users.php?client_id=<?php echo $client['id']; ?>" 
                                                       class="btn btn-sm btn-primary" 
                                                           title="Manage Client Users">
                                                            <i class="fas fa-users"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Client Users View -->
                        <div id="client-users-view" class="client-view-content <?php echo $active_client_card === 'users' ? 'active' : ''; ?>">
                            <?php if(empty($all_client_users)): ?>
                                <div class="alert alert-info mb-0">No client users found.</div>
                            <?php else: ?>
                                <div class="table-responsive" style="overflow-x: auto; max-width: 100%;">
                                    <table class="table table-bordered table-striped table-sm mb-0" style="width: 100%; table-layout: auto; margin-bottom: 0;">
                                        <thead>
                                            <tr>
                                                <th style="white-space: nowrap; width: 15%;">Account</th>
                                                <th style="white-space: nowrap; width: 15%;">Full Name</th>
                                                <th style="white-space: nowrap; width: 12%;">Username</th>
                                                <th style="white-space: nowrap; width: 18%;">Email</th>
                                                <th style="white-space: nowrap; width: 8%;">Status</th>
                                                <th style="white-space: nowrap; width: 8%;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($all_client_users as $user): ?>
                                                <?php
                                                $user_status = ucfirst(strtolower(trim($user['Status'] ?? 'Active')));
                                                if($user_status !== 'Active' && $user_status !== 'Inactive') {
                                                    $user_status = 'Active';
                                                }
                                                $status_badge_class = $user_status === 'Active' ? 'badge-success' : 'badge-danger';
                                                
                                                // Determine user type badge class
                                                $user_type = strtolower($user['user_type'] ?? '');
                                                $user_type_badge_class = 'badge-secondary';
                                                switch($user_type) {
                                                    case 'admin':
                                                        $user_type_badge_class = 'badge-danger';
                                                        break;
                                                    case 'manager':
                                                        $user_type_badge_class = 'badge-primary';
                                                        break;
                                                    case 'doer':
                                                        $user_type_badge_class = 'badge-success';
                                                        break;
                                                }
                                                ?>
                                                <tr>
                                                    <td style="white-space: normal;">
                                                        <strong><?php echo htmlspecialchars($user['client_name'] ?? 'N/A'); ?></strong>
                                                    </td>
                                                    <td style="white-space: normal;">
                                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                    </td>
                                                    <td style="white-space: normal;"><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td style="white-space: normal; word-break: break-word;"><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td style="white-space: nowrap;">
                                                        <!-- Managers and Admins can edit client user status -->
                                                        <select class="form-control form-control-sm user-status-dropdown" 
                                                                data-user-id="<?php echo $user['id']; ?>" 
                                                                data-user-type="client_user"
                                                                data-original-status="<?php echo htmlspecialchars($user_status); ?>">
                                                            <option value="Active" <?php echo ($user_status == 'Active') ? 'selected' : ''; ?>>Active</option>
                                                            <option value="Inactive" <?php echo ($user_status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                        </select>
                                                    </td>
                                                    <td style="white-space: nowrap;">
                                                        <?php if($is_manager || $is_admin): // Both Manager and Admin can edit/delete client users ?>
                                                        <button type="button" class="btn btn-sm btn-info edit-client-user-btn" title="Edit Client User" 
                                                                data-user-id="<?php echo $user['id']; ?>"
                                                                data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>"
                                                                data-name="<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>"
                                                                data-email="<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>"
                                                                data-client-id="<?php echo $user['client_id'] ?? $user['manager_id']; ?>"
                                                                data-joining-date="<?php echo $user['joining_date'] ? htmlspecialchars($user['joining_date'], ENT_QUOTES) : ''; ?>"
                                                                data-date-of-birth="<?php echo $user['date_of_birth'] ? htmlspecialchars($user['date_of_birth'], ENT_QUOTES) : ''; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this client user?');">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="delete_user" value="1">
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Client User">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
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
            </div> <!-- End Manage Clients Table -->
        <?php endif; ?>
    </div>
    </div>
    </div>
    <script>
    // Section Toggle Functionality
    function switchSection(section) {
        // Hide all sections
        document.querySelectorAll('.section-content').forEach(function(el) {
            el.classList.remove('active');
        });
        
        // Show selected section
        const targetSection = document.getElementById('section-' + section);
        if(targetSection) {
            targetSection.classList.add('active');
        }
        
        // Update toggle buttons
        document.querySelectorAll('.section-toggle-btn').forEach(function(btn) {
            btn.classList.remove('active');
            if(btn.dataset.section === section) {
                btn.classList.add('active');
            }
        });
        
        // Update header action buttons (for admin users)
        const newUserBtn = document.getElementById('newUserBtn');
        const newClientAccountBtn = document.getElementById('newClientAccountBtn');
        const newClientUserBtn = document.getElementById('newClientUserBtn');
        
        if(section === 'team') {
            // Show "New User" button, hide "Client Account" and "New Client User" buttons
            if(newUserBtn) newUserBtn.style.display = '';
            if(newClientAccountBtn) newClientAccountBtn.style.display = 'none';
            if(newClientUserBtn) newClientUserBtn.style.display = 'none';
        } else if(section === 'clients') {
            // Show "Client Account" and "New Client User" buttons, hide "New User" button
            if(newUserBtn) newUserBtn.style.display = 'none';
            if(newClientAccountBtn) newClientAccountBtn.style.display = '';
            if(newClientUserBtn) newClientUserBtn.style.display = '';
        }
        
        // Update statistics display
        const teamStats = document.getElementById('team-stats');
        const clientStats = document.getElementById('client-stats');
        const statsTitle = document.getElementById('stats-title');
        
        // Show/hide client section content (form and table)
        const clientSectionContents = document.querySelectorAll('.client-section-content');
        
        if(section === 'team' && teamStats) {
            // Show team stats
            if(clientStats) clientStats.classList.remove('active');
            teamStats.classList.add('active');
            if(statsTitle) statsTitle.textContent = 'Team Statistics';
            // Hide client section content
            clientSectionContents.forEach(function(el) {
                el.classList.remove('active');
            });
        } else if(section === 'clients' && clientStats) {
            // Show client stats
            if(teamStats) teamStats.classList.remove('active');
            clientStats.classList.add('active');
            if(statsTitle) statsTitle.textContent = 'Client Statistics';
            // Show client section content
            clientSectionContents.forEach(function(el) {
                el.classList.add('active');
            });
        }
        
        // Update URL without page reload
        const url = new URL(window.location);
        url.searchParams.set('section', section);
        window.history.pushState({}, '', url);
    }
    
    // Client Card Toggle Functionality
    function switchClientCard(card) {
        // Update the heading based on selected radio button
        const heading = document.getElementById('manageClientsHeading');
        if (heading) {
            heading.textContent = card === 'accounts' ? 'Manage Client Accounts' : 'Manage Client Users';
        }
        // Hide all client view contents
        document.querySelectorAll('.client-view-content').forEach(function(el) {
            el.classList.remove('active');
        });
        
        // Show selected view
        const targetView = document.getElementById('client-' + card + '-view');
        if(targetView) {
            targetView.classList.add('active');
        }
        
        // Update radio buttons
        const radioButtons = document.querySelectorAll('input[name="client_view"]');
        const allLabels = document.querySelectorAll('.client-radio-label');
        
        radioButtons.forEach(function(radio) {
            if(radio.value === card) {
                radio.checked = true;
            }
        });
        
        // Update label active state
        allLabels.forEach(function(label) {
            const radio = label.querySelector('input[type="radio"]');
            if(radio && radio.value === card) {
                label.classList.add('active');
            } else {
                label.classList.remove('active');
            }
        });
        
        // Update URL without page reload
        const url = new URL(window.location);
        url.searchParams.set('client_card', card);
        window.history.pushState({}, '', url);
    }
    
    // View Client Users Function
    function viewClientUsers(clientId) {
        // For now, this will show an alert. Later we can implement a modal or separate page
        // to manage users under the client account
        window.location.href = 'manage_client_users.php?client_id=' + clientId;
    }
    
    // Date picker clickable enhancement
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize statistics display based on active section
        const activeSection = '<?php echo $active_section; ?>';
        const teamStats = document.getElementById('team-stats');
        const clientStats = document.getElementById('client-stats');
        const statsTitle = document.getElementById('stats-title');
        const clientSectionContents = document.querySelectorAll('.client-section-content');
        
        if(activeSection === 'team' && teamStats) {
            if(clientStats) clientStats.classList.remove('active');
            teamStats.classList.add('active');
            if(statsTitle) statsTitle.textContent = 'Team Statistics';
            // Hide client section content
            clientSectionContents.forEach(function(el) {
                el.classList.remove('active');
            });
        } else if(activeSection === 'clients' && clientStats) {
            if(teamStats) teamStats.classList.remove('active');
            clientStats.classList.add('active');
            if(statsTitle) statsTitle.textContent = 'Client Statistics';
            // Show client section content
            clientSectionContents.forEach(function(el) {
                el.classList.add('active');
            });
        } else {
            // Default to team if available
            if(teamStats) {
                teamStats.classList.add('active');
                if(statsTitle) statsTitle.textContent = 'Team Statistics';
            }
            // Hide client section content by default
            clientSectionContents.forEach(function(el) {
                el.classList.remove('active');
            });
        }
        
        // Add User Form Toggle Functionality
        const addUserToggleButton = document.getElementById('addUserToggle');
        const addUserContent = document.getElementById('addUserContent');
        const addUserToggleIcon = document.getElementById('addUserToggleIcon');
        const addUserToggleText = document.getElementById('addUserToggleText');
        
        if (addUserToggleButton && addUserContent && addUserToggleIcon && addUserToggleText) {
            // Check if form is in edit mode - if so, expand it by default
            const isEditMode = <?php echo isset($edit_id) ? 'true' : 'false'; ?>;
            
            if (!isEditMode) {
                // Form should be collapsed by default only when adding new user
                addUserContent.classList.add('collapsed');
            } else {
                // Expand form when editing
                addUserContent.classList.remove('collapsed');
                addUserToggleIcon.className = 'fas fa-chevron-up';
                addUserToggleText.textContent = 'Hide Form';
            }
            
            addUserToggleButton.addEventListener('click', function() {
                // Use requestAnimationFrame for smoother animation
                requestAnimationFrame(function() {
                    if (addUserContent.classList.contains('collapsed')) {
                        // Show form
                        addUserContent.classList.remove('collapsed');
                        addUserToggleIcon.className = 'fas fa-chevron-up';
                        addUserToggleText.textContent = 'Hide Form';
                    } else {
                        // Hide form
                        addUserContent.classList.add('collapsed');
                        addUserToggleIcon.className = 'fas fa-chevron-down';
                        addUserToggleText.textContent = 'Show Form';
                    }
                });
            });
        }
        
        // Add Client Account Form Toggle Functionality - Optimized for smooth animation
        const addClientToggleButton = document.getElementById('addClientToggle');
        const addClientContent = document.getElementById('addClientContent');
        const addClientToggleIcon = document.getElementById('addClientToggleIcon');
        const addClientToggleText = document.getElementById('addClientToggleText');
        
        if (addClientToggleButton && addClientContent && addClientToggleIcon && addClientToggleText) {
            // Form should be collapsed by default
            addClientContent.classList.add('collapsed');
            
            addClientToggleButton.addEventListener('click', function(e) {
                e.preventDefault();
                const isCollapsed = addClientContent.classList.contains('collapsed');
                
                // Use double requestAnimationFrame for smoother animation
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        if (isCollapsed) {
                            // Show form
                            addClientContent.classList.remove('collapsed');
                            addClientToggleIcon.className = 'fas fa-chevron-up';
                            addClientToggleText.textContent = 'Hide Form';
                        } else {
                            // Hide form
                            addClientContent.classList.add('collapsed');
                            addClientToggleIcon.className = 'fas fa-chevron-down';
                            addClientToggleText.textContent = 'Show Form';
                        }
                    });
                });
            });
        }
        
        // Add Client User Form Toggle Functionality (Manager Only) - Optimized for smooth animation
        const addClientUserToggleButton = document.getElementById('addClientUserToggle');
        const addClientUserContent = document.getElementById('addClientUserContent');
        const addClientUserToggleIcon = document.getElementById('addClientUserToggleIcon');
        const addClientUserToggleText = document.getElementById('addClientUserToggleText');
        
        if (addClientUserToggleButton && addClientUserContent && addClientUserToggleIcon && addClientUserToggleText) {
            // Form should be collapsed by default
            addClientUserContent.classList.add('collapsed');
            
            addClientUserToggleButton.addEventListener('click', function(e) {
                e.preventDefault();
                const isCollapsed = addClientUserContent.classList.contains('collapsed');
                
                // Use double requestAnimationFrame for smoother animation
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        if (isCollapsed) {
                            // Show form
                            addClientUserContent.classList.remove('collapsed');
                            addClientUserToggleIcon.className = 'fas fa-chevron-up';
                            addClientUserToggleText.textContent = 'Hide Form';
                        } else {
                            // Hide form
                            addClientUserContent.classList.add('collapsed');
                            addClientUserToggleIcon.className = 'fas fa-chevron-down';
                            addClientUserToggleText.textContent = 'Show Form';
                        }
                    });
                });
            });
        }
        
        // Make all date inputs clickable (including those that might not have the class yet)
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            // Ensure the class is added
            if (!input.classList.contains('date-picker-clickable')) {
                input.classList.add('date-picker-clickable');
            }
            
            // Make entire field clickable
            input.addEventListener('click', function(e) {
                // Only trigger if not clicking directly on the calendar icon
                if (e.target === this || e.target.closest('.date-picker-clickable')) {
                    this.showPicker();
                }
            });
            
            // Also make the input focusable and clickable
            input.addEventListener('focus', function() {
                this.showPicker();
            });
        });
        
        // User Status Toggle Functionality
        const toggleActive = document.getElementById('toggleActive');
        const toggleInactive = document.getElementById('toggleInactive');
        const usersTableBody = document.getElementById('usersTableBody');
        
        // Function to filter table rows by status (made global for use in status dropdown handler)
        let filterUsersByStatus = null;
        
        if (toggleActive && toggleInactive && usersTableBody) {
            // Function to filter table rows by status
            filterUsersByStatus = function(status) {
                const rows = usersTableBody.querySelectorAll('tr');
                let visibleCount = 0;
                
                rows.forEach(row => {
                    const rowStatus = row.getAttribute('data-user-status');
                    if (rowStatus === status) {
                        row.classList.remove('hidden-row');
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.classList.add('hidden-row');
                        row.style.display = 'none';
                    }
                });
                
                // Update toggle button states
                if (status === 'Active') {
                    toggleActive.classList.add('active');
                    toggleInactive.classList.remove('active');
                } else {
                    toggleInactive.classList.add('active');
                    toggleActive.classList.remove('active');
                }
            };
            
            // Set default to Active on page load
            filterUsersByStatus('Active');
            
            // Add event listeners to toggle buttons
            toggleActive.addEventListener('click', function() {
                filterUsersByStatus('Active');
            });
            
            toggleInactive.addEventListener('click', function() {
                filterUsersByStatus('Inactive');
            });
            
            // Also handle radio button changes
            const radioButtons = document.querySelectorAll('input[name="user_status_filter"]');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        filterUsersByStatus(this.value);
                    }
                });
            });
        }
        
        // Handle edit button clicks for team users
        const editUserButtons = document.querySelectorAll('.edit-user-btn');
        editUserButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const username = this.getAttribute('data-username');
                const name = this.getAttribute('data-name');
                const email = this.getAttribute('data-email');
                const departmentId = this.getAttribute('data-department-id');
                const userType = this.getAttribute('data-user-type');
                const manager = this.getAttribute('data-manager');
                const joiningDate = this.getAttribute('data-joining-date');
                const dateOfBirth = this.getAttribute('data-date-of-birth');
                openEditUserModal(userId, username, name, email, departmentId, userType, manager, joiningDate, dateOfBirth);
            });
        });
        
        // Handle edit button clicks for client accounts
        const editClientAccountButtons = document.querySelectorAll('.edit-client-account-btn');
        editClientAccountButtons.forEach(button => {
            button.addEventListener('click', function() {
                const clientId = this.getAttribute('data-client-id');
                const username = this.getAttribute('data-client-username');
                const name = this.getAttribute('data-client-name');
                const manager = this.getAttribute('data-client-manager');
                const joiningDate = this.getAttribute('data-client-joining-date');
                openEditClientAccountModal(clientId, username, name, manager, joiningDate);
            });
        });
        
        // Handle edit button clicks for client users
        const editClientUserButtons = document.querySelectorAll('.edit-client-user-btn');
        editClientUserButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const username = this.getAttribute('data-username');
                const name = this.getAttribute('data-name');
                const email = this.getAttribute('data-email');
                const clientId = this.getAttribute('data-client-id');
                const joiningDate = this.getAttribute('data-joining-date');
                const dateOfBirth = this.getAttribute('data-date-of-birth');
                openEditClientUserModal(userId, username, name, email, clientId, joiningDate, dateOfBirth);
            });
        });
        
        // Handle user status dropdown changes
        const statusDropdowns = document.querySelectorAll('.user-status-dropdown');
        statusDropdowns.forEach(dropdown => {
            // Store original value on page load
            const originalStatus = dropdown.dataset.originalStatus || dropdown.value;
            
            dropdown.addEventListener('change', function() {
                const userId = this.dataset.userId;
                const userType = this.dataset.userType || ''; // 'client_account' or 'client_user'
                const newStatus = this.value;
                const originalValue = this.dataset.originalStatus || originalStatus;
                const statusText = newStatus;
                
                // Check if manager is trying to update client account status (not allowed)
                const isManager = <?php echo ($is_manager && !$is_admin) ? 'true' : 'false'; ?>;
                if(isManager && userType === 'client_account') {
                    alert('Managers cannot change the status of client accounts. You can only view their status.');
                    // Revert the select value
                    this.value = originalValue;
                    return;
                }
                
                // Show confirmation dialog
                if (!confirm('Are you sure you want to change the user status to "' + statusText + '"?')) {
                    // User cancelled, revert the select value
                    this.value = originalValue;
                    return;
                }
                
                // Show loading state
                this.disabled = true;
                const originalHTML = this.innerHTML;
                this.innerHTML = '<option>Updating...</option>';
                
                // Make AJAX request
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
                        // Update the original status in data attribute
                        this.dataset.originalStatus = newStatus;
                        
                        // Update the row's data-user-status attribute
                        const row = this.closest('tr');
                        if (row) {
                            row.setAttribute('data-user-status', newStatus);
                            
                            // Refresh the filter to show/hide the row based on current toggle
                            if (filterUsersByStatus) {
                                const activeRadio = document.querySelector('input[name="user_status_filter"][value="Active"]');
                                if (activeRadio && activeRadio.checked) {
                                    filterUsersByStatus('Active');
                                } else {
                                    filterUsersByStatus('Inactive');
                                }
                            }
                        }
                        
                        // Show success message
                        alert('User status updated successfully!');
                    } else {
                        alert('Error: ' + (data.message || 'Failed to update user status'));
                        // Revert to original value
                        this.value = originalValue;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating user status. Please try again.');
                    // Revert to original value
                    this.value = originalValue;
                })
                .finally(() => {
                    // Re-enable dropdown
                    this.disabled = false;
                    this.innerHTML = originalHTML;
                    // Set the selected value
                    this.value = newStatus;
                });
            });
        });
    });
    </script>

<!-- Modals for Add New User, Client Account, and Client User -->
<!-- New User Modal (Admin - Team Section) -->
<?php if($is_admin): ?>
<div class="modal" id="newUserModal" tabindex="-1" role="dialog" aria-labelledby="newUserModalLabel" aria-hidden="true" data-backdrop="false">
    <div class="modal-dialog modal-lg" role="document" style="margin: 0; position: fixed; top: 50%; left: calc(250px + (100vw - 250px) / 2); transform: translate(-50%, -50%); max-width: 90%; width: auto;">
        <div class="modal-content" style="background: #1e293b; border: 1px solid rgba(255, 255, 255, 0.1);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="modal-title text-white" id="newUserModalLabel"><i class="fas fa-user-plus"></i> Add New User</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="closeNewUserModal()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="newUserForm">
                    <input type="hidden" name="save_user" value="1">
                    <input type="hidden" name="edit_id" id="editUserId" value="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-white">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="modalUsername" class="form-control bg-slate-700 text-white border-slate-600" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-white">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="modalName" class="form-control bg-slate-700 text-white border-slate-600" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-white">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="modalEmail" class="form-control bg-slate-700 text-white border-slate-600" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-white">Department <span class="text-danger">*</span></label>
                            <select name="department_id" id="modalDepartmentId" class="form-control bg-slate-700 text-white border-slate-600" required>
                                <option value="">Select Department</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-white">User Type <span class="text-danger">*</span></label>
                            <select name="user_type" id="modalUserType" class="form-control bg-slate-700 text-white border-slate-600" required>
                                <option value="">Select User Type</option>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="doer">Doer</option>
                                <option value="client">Client</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-white">Manager</label>
                            <select name="manager" id="modalManager" class="form-control bg-slate-700 text-white border-slate-600">
                                <option value="">Select Manager</option>
                                <option value="Shubham Tyagi">Shubham Tyagi</option>
                                <?php 
                                $managers_sql = "SELECT id, name FROM users WHERE user_type IN ('manager', 'admin') ORDER BY name";
                                $managers_result = mysqli_query($conn, $managers_sql);
                                if($managers_result) {
                                    while($manager_user = mysqli_fetch_assoc($managers_result)) {
                                        echo "<option value=\"" . htmlspecialchars($manager_user['name']) . "\">" . htmlspecialchars($manager_user['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-white">Joining Date</label>
                            <input type="date" name="joining_date" id="modalJoiningDate" class="form-control bg-slate-700 text-white border-slate-600">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-white">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="modalDateOfBirth" class="form-control bg-slate-700 text-white border-slate-600 date-picker-clickable">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-white" id="passwordLabel">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="modalPassword" class="form-control bg-slate-700 text-white border-slate-600">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-white" id="confirmPasswordLabel">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" id="modalConfirmPassword" class="form-control bg-slate-700 text-white border-slate-600">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <button type="button" class="btn btn-primary" onclick="submitUserForm();" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border: none;" id="submitUserBtn">Add User</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Client Account Modal (Admin - Clients Section) -->
<?php if($is_admin): ?>
<div class="modal" id="newClientAccountModal" tabindex="-1" role="dialog" aria-labelledby="newClientAccountModalLabel" aria-hidden="true" data-backdrop="false">
    <div class="modal-dialog modal-lg" role="document" style="margin: 0; position: fixed; top: 50%; left: calc(250px + (100vw - 250px) / 2); transform: translate(-50%, -50%); max-width: 90%; width: auto;">
        <div class="modal-content" style="background: #1e293b; border: 1px solid rgba(255, 255, 255, 0.1);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="modal-title text-white" id="newClientAccountModalLabel"><i class="fas fa-building"></i> Add Client Account</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="closeNewClientAccountModal()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="newClientAccountForm">
                    <input type="hidden" name="save_client_account" value="1">
                    <input type="hidden" name="edit_id" id="editClientAccountId" value="">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="text-white">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="client_username" id="modalClientUsername" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Enter account name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="text-white">Manager <span class="text-danger">*</span></label>
                            <select name="client_manager" id="modalClientManager" class="form-control bg-slate-700 text-white border-slate-600" required>
                                <option value="">Select Manager</option>
                                <option value="Shubham Tyagi">Shubham Tyagi</option>
                                <?php 
                                $managers_sql = "SELECT id, name FROM users WHERE user_type IN ('manager', 'admin') ORDER BY name";
                                $managers_result = mysqli_query($conn, $managers_sql);
                                if($managers_result) {
                                    while($manager_user = mysqli_fetch_assoc($managers_result)) {
                                        echo "<option value=\"" . htmlspecialchars($manager_user['name']) . "\">" . htmlspecialchars($manager_user['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="text-white">On-boarding Date <span class="text-danger">*</span></label>
                            <input type="date" name="client_joining_date" id="modalClientJoiningDate" class="form-control bg-slate-700 text-white border-slate-600 date-picker-clickable" required>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3" style="background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3); color: #93c5fd;">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Client Accounts are parent entities that cannot log in. Only Client Users (created under Client Accounts) can log in with passwords.
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <button type="button" class="btn btn-primary" onclick="document.getElementById('newClientAccountForm').submit();" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border: none;" id="submitClientAccountBtn">Create Client Account</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Client User Modal (Manager & Admin) -->
<?php if($is_manager || $is_admin): ?>
<?php
// Get client accounts for manager to select from (exclude client users - they have non-empty password)
// Client Accounts: user_type = 'client', password = '' (empty) or NULL
$manager_clients_query = "SELECT id, name, username FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '')";
// If Manager, only show client accounts assigned to them
if($is_manager && !$is_admin) {
    $current_manager_id = $_SESSION['id'];
    $manager_clients_query .= " AND manager_id = $current_manager_id";
}
$manager_clients_query .= " ORDER BY name";
$manager_clients_result = mysqli_query($conn, $manager_clients_query);
$manager_clients = array();
if($manager_clients_result) {
    while($row = mysqli_fetch_assoc($manager_clients_result)) {
        $manager_clients[] = $row;
    }
}
?>
<div class="modal" id="newClientUserModal" tabindex="-1" role="dialog" aria-labelledby="newClientUserModalLabel" aria-hidden="true" data-backdrop="false">
    <div class="modal-dialog modal-lg" role="document" style="margin: 0; position: fixed; top: 50%; left: calc(250px + (100vw - 250px) / 2); transform: translate(-50%, -50%); max-width: 90%; width: auto;">
        <div class="modal-content" style="background: #1e293b; border: 1px solid rgba(255, 255, 255, 0.1);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="modal-title text-white" id="newClientUserModalLabel"><i class="fas fa-user-plus"></i> Add New Client User</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="closeNewClientUserModal()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="newClientUserForm">
                    <input type="hidden" name="save_client_user" value="1">
                    <input type="hidden" name="edit_id" id="editClientUserId" value="">
                    <input type="hidden" name="edit_user" value="1">
                    
                    <!-- Row 1: Client Account, Username (2 fields) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-white">Client Account <span class="text-danger">*</span></label>
                            <select name="client_user_client_id" id="modalClientUserClientId" class="form-control bg-slate-700 text-white border-slate-600" required>
                                <option value="">Select Client Account</option>
                                <?php foreach($manager_clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>">
                                        <?php echo htmlspecialchars($client['name'] . ' (' . $client['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="text-white">Username <span class="text-danger">*</span></label>
                            <input type="text" name="client_user_username" id="modalClientUserUsername" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Enter username" required>
                        </div>
                    </div>
                    
                    <!-- Row 2: Full Name, Email (2 fields) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-white">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="client_user_name" id="modalClientUserName" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Enter full name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="text-white">Email <span class="text-danger">*</span></label>
                            <input type="email" name="client_user_email" id="modalClientUserEmail" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Enter email" required>
                        </div>
                    </div>
                    
                    <!-- Row 3: Joining Date, Date of Birth (2 fields) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-white">Joining Date</label>
                            <input type="date" name="client_user_joining_date" id="modalClientUserJoiningDate" class="form-control bg-slate-700 text-white border-slate-600 date-picker-clickable">
                        </div>
                        <div class="col-md-6">
                            <label class="text-white">Date of Birth</label>
                            <input type="date" name="client_user_date_of_birth" id="modalClientUserDateOfBirth" class="form-control bg-slate-700 text-white border-slate-600 date-picker-clickable">
                        </div>
                    </div>
                    
                    <!-- Row 4: Password, Confirm Password (2 fields) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-white" id="clientUserPasswordLabel">Password <span class="text-danger">*</span></label>
                            <input type="password" name="client_user_password" id="modalClientUserPassword" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Enter password">
                        </div>
                        <div class="col-md-6">
                            <label class="text-white" id="clientUserConfirmPasswordLabel">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="client_user_confirm_password" id="modalClientUserConfirmPassword" class="form-control bg-slate-700 text-white border-slate-600" placeholder="Confirm password">
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
<?php endif; ?>

<script>
// Modal functions
function openNewUserModal() {
    // Reset form for new user
    document.getElementById('newUserForm').reset();
    document.getElementById('editUserId').value = '';
    document.getElementById('modalPassword').required = true;
    document.getElementById('modalConfirmPassword').required = true;
    document.getElementById('passwordLabel').innerHTML = 'Password <span class="text-danger">*</span>';
    document.getElementById('confirmPasswordLabel').innerHTML = 'Confirm Password <span class="text-danger">*</span>';
    document.getElementById('submitUserBtn').textContent = 'Add User';
    document.getElementById('newUserModalLabel').innerHTML = '<i class="fas fa-user-plus"></i> Add New User';
    
    $('#newUserModal').modal({backdrop: false, show: true});
    $('#newUserModal').css('display', 'block');
}

function openEditUserModal(userId, username, name, email, departmentId, userType, manager, joiningDate, dateOfBirth) {
    // Fill form with user data
    document.getElementById('editUserId').value = userId || '';
    document.getElementById('modalUsername').value = username || '';
    document.getElementById('modalName').value = name || '';
    document.getElementById('modalEmail').value = email || '';
    document.getElementById('modalDepartmentId').value = departmentId || '';
    document.getElementById('modalUserType').value = userType || '';
    document.getElementById('modalManager').value = manager || '';
    document.getElementById('modalJoiningDate').value = joiningDate || '';
    document.getElementById('modalDateOfBirth').value = dateOfBirth || '';
    document.getElementById('modalPassword').value = '';
    document.getElementById('modalConfirmPassword').value = '';
    
    // Make password fields optional for edit
    document.getElementById('modalPassword').required = false;
    document.getElementById('modalConfirmPassword').required = false;
    document.getElementById('passwordLabel').innerHTML = 'New Password (leave blank to keep current)';
    document.getElementById('confirmPasswordLabel').innerHTML = 'Confirm Password';
    document.getElementById('submitUserBtn').textContent = 'Update User';
    document.getElementById('newUserModalLabel').innerHTML = '<i class="fas fa-user-edit"></i> Edit User';
    
    $('#newUserModal').modal({backdrop: false, show: true});
    $('#newUserModal').css('display', 'block');
}

function closeNewUserModal() {
    $('#newUserModal').modal('hide');
    $('#newUserModal').css('display', 'none');
}

function submitUserForm() {
    const form = document.getElementById('newUserForm');
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

function openNewClientAccountModal() {
    // Reset form for new client account
    document.getElementById('newClientAccountForm').reset();
    document.getElementById('editClientAccountId').value = '';
    document.getElementById('submitClientAccountBtn').textContent = 'Create Client Account';
    document.getElementById('newClientAccountModalLabel').innerHTML = '<i class="fas fa-building"></i> Add Client Account';
    
    $('#newClientAccountModal').modal({backdrop: false, show: true});
    $('#newClientAccountModal').css('display', 'block');
}

function openEditClientAccountModal(clientId, username, name, manager, joiningDate) {
    // Fill form with client account data
    document.getElementById('editClientAccountId').value = clientId || '';
    document.getElementById('modalClientUsername').value = username || '';
    document.getElementById('modalClientManager').value = manager || '';
    document.getElementById('modalClientJoiningDate').value = joiningDate || '';
    document.getElementById('submitClientAccountBtn').textContent = 'Update Client Account';
    document.getElementById('newClientAccountModalLabel').innerHTML = '<i class="fas fa-building"></i> Edit Client Account';
    
    $('#newClientAccountModal').modal({backdrop: false, show: true});
    $('#newClientAccountModal').css('display', 'block');
}

function closeNewClientAccountModal() {
    $('#newClientAccountModal').modal('hide');
    $('#newClientAccountModal').css('display', 'none');
}

function openNewClientUserModal() {
    // Reset form for new client user
    document.getElementById('newClientUserForm').reset();
    document.getElementById('editClientUserId').value = '';
    document.getElementById('modalClientUserPassword').required = true;
    document.getElementById('modalClientUserConfirmPassword').required = true;
    document.getElementById('clientUserPasswordLabel').innerHTML = 'Password <span class="text-danger">*</span>';
    document.getElementById('clientUserConfirmPasswordLabel').innerHTML = 'Confirm Password <span class="text-danger">*</span>';
    document.getElementById('submitClientUserBtn').textContent = 'Create Client User';
    document.getElementById('newClientUserModalLabel').innerHTML = '<i class="fas fa-user-plus"></i> Add New Client User';
    
    $('#newClientUserModal').modal({backdrop: false, show: true});
    $('#newClientUserModal').css('display', 'block');
}

function openEditClientUserModal(userId, username, name, email, clientId, joiningDate, dateOfBirth) {
    // Fill form with client user data
    document.getElementById('editClientUserId').value = userId || '';
    document.getElementById('modalClientUserUsername').value = username || '';
    document.getElementById('modalClientUserName').value = name || '';
    document.getElementById('modalClientUserEmail').value = email || '';
    document.getElementById('modalClientUserClientId').value = clientId || '';
    document.getElementById('modalClientUserJoiningDate').value = joiningDate || '';
    document.getElementById('modalClientUserDateOfBirth').value = dateOfBirth || '';
    document.getElementById('modalClientUserPassword').value = '';
    document.getElementById('modalClientUserConfirmPassword').value = '';
    
    // Make password fields optional for edit
    document.getElementById('modalClientUserPassword').required = false;
    document.getElementById('modalClientUserConfirmPassword').required = false;
    document.getElementById('clientUserPasswordLabel').innerHTML = 'New Password (leave blank to keep current)';
    document.getElementById('clientUserConfirmPasswordLabel').innerHTML = 'Confirm Password';
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
    const editId = document.getElementById('editClientUserId').value;
    const password = document.getElementById('modalClientUserPassword').value;
    const confirmPassword = document.getElementById('modalClientUserConfirmPassword').value;
    
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

<?php require_once "../includes/footer.php"; ?>