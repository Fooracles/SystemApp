<?php
$page_title = "My Profile";
require_once "../includes/header.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Ensure profile_photo column exists
ensureUsersColumns($conn);

// Define variables and initialize with empty values
$name = $email = $current_password = $new_password = $confirm_password = "";
$name_err = $email_err = $current_password_err = $new_password_err = $confirm_password_err = "";
$success_msg = $error_msg = "";
$profile_photo = "";

// Get user data
$user_id = $_SESSION["id"];
$sql = "SELECT * FROM users WHERE id = ?";
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            $name = $user['name'];
            $email = $user['email'];
            $department_id = $user['department_id'];
            $profile_photo = isset($user['profile_photo']) ? $user['profile_photo'] : "";
            $joining_date = isset($user['joining_date']) ? $user['joining_date'] : null;
            $date_of_birth = isset($user['date_of_birth']) ? $user['date_of_birth'] : null;
            $manager_id = isset($user['manager_id']) ? $user['manager_id'] : null;
        } else {
            $error_msg = "User not found.";
        }
    } else {
        $error_msg = "Something went wrong. Please try again later.";
    }
    
    mysqli_stmt_close($stmt);
}

// Get department name
$department_name = getDepartmentName($conn, $department_id);

// Get manager name if exists
$manager_name = "";
if($manager_id && isset($user)) {
    // Check if current user is a client user (manager_id points to client account)
    if(isClient() && isset($user['user_type']) && $user['user_type'] === 'client' && !empty($user['password'])) {
        // This is a client user - manager_id points to client account
        // Get the client account's manager_id to find the actual manager
        $client_account_sql = "SELECT manager_id FROM users WHERE id = ? AND user_type = 'client' AND (password IS NULL OR password = '')";
        if($client_stmt = mysqli_prepare($conn, $client_account_sql)) {
            mysqli_stmt_bind_param($client_stmt, "i", $manager_id);
            if(mysqli_stmt_execute($client_stmt)) {
                $client_result = mysqli_stmt_get_result($client_stmt);
                if($client_row = mysqli_fetch_assoc($client_result)) {
                    $actual_manager_id = $client_row['manager_id'];
                    if($actual_manager_id) {
                        // Get the actual manager name
                        $sql = "SELECT name FROM users WHERE id = ?";
                        if($stmt = mysqli_prepare($conn, $sql)) {
                            mysqli_stmt_bind_param($stmt, "i", $actual_manager_id);
                            if(mysqli_stmt_execute($stmt)) {
                                $result = mysqli_stmt_get_result($stmt);
                                if($row = mysqli_fetch_assoc($result)) {
                                    $manager_name = $row['name'];
                                }
                            }
                            mysqli_stmt_close($stmt);
                        }
                    }
                }
            }
            mysqli_stmt_close($client_stmt);
        }
    } else {
        // For non-client users, manager_id directly points to the manager
    $sql = "SELECT name FROM users WHERE id = ?";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $manager_id);
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if($row = mysqli_fetch_assoc($result)) {
                $manager_name = $row['name'];
            }
        }
        mysqli_stmt_close($stmt);
        }
    }
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Determine which form was submitted
    if(isset($_POST["update_profile"])) {
        // Update profile form - Only admins can update profile information
        if(!isAdmin()) {
            $error_msg = "You do not have permission to update profile information. Only administrators can modify profile details.";
        } else {
            // Validate name
            if(empty(trim($_POST["name"]))) {
                $name_err = "Please enter your name.";
            } else {
                $name = trim($_POST["name"]);
            }
            
            // Validate email
            if(empty(trim($_POST["email"]))) {
                $email_err = "Please enter your email.";
            } else {
                $email = trim($_POST["email"]);
                
                // Check if email is already taken (by another user)
                $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
                if($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
                    
                    if(mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_store_result($stmt);
                        
                        if(mysqli_stmt_num_rows($stmt) > 0) {
                            $email_err = "This email is already taken.";
                        }
                    } else {
                        $error_msg = "Something went wrong. Please try again later.";
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            }
            
            // Check input errors before updating the database
            if(empty($name_err) && empty($email_err)) {
                // Prepare an update statement
                $sql = "UPDATE users SET name = ?, email = ? WHERE id = ?";
                
                if($stmt = mysqli_prepare($conn, $sql)) {
                    // Bind variables to the prepared statement as parameters
                    mysqli_stmt_bind_param($stmt, "ssi", $name, $email, $user_id);
                    
                    // Attempt to execute the prepared statement
                    if(mysqli_stmt_execute($stmt)) {
                        $success_msg = "Profile updated successfully!";
                    } else {
                        $error_msg = "Something went wrong. Please try again later.";
                    }
                    
                    // Close statement
                    mysqli_stmt_close($stmt);
                }
            }
        }
    } else if(isset($_POST["change_password"])) {
        // Change password form
        
        // Initialize variables for reset code tracking
        $reset_code_valid = false;
        $reset_status = null;
        $is_current_password_valid = false;
        
        // Validate current password
        if(empty(trim($_POST["current_password"]))) {
            $current_password_err = "Please enter your current password or reset code.";
        } else {
            $current_password = trim($_POST["current_password"]);
            
            // Always verify against the user's existing password first
            $sql = "SELECT password FROM users WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                
                if(mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if(mysqli_num_rows($result) == 1) {
                        $row = mysqli_fetch_assoc($result);
                        $hashed_password = $row["password"];
                        
                        if(password_verify($current_password, $hashed_password)) {
                            $is_current_password_valid = true;
                        }
                    } else {
                        $error_msg = "Something went wrong. Please try again later.";
                    }
                } else {
                    $error_msg = "Something went wrong. Please try again later.";
                }
                
                mysqli_stmt_close($stmt);
            }
            
            // If current password didn't match, and the input looks like a reset code, validate it
            if(!$is_current_password_valid && strlen($current_password) == 6 && is_numeric($current_password)) {
                // Verify it's a valid reset code for this user (approved or previously used)
                $verify_sql = "SELECT username, email, reset_code, approved_at, status FROM password_reset_requests 
                              WHERE username = ? AND reset_code = ? AND (status = 'approved' OR status = 'used') 
                              ORDER BY approved_at DESC LIMIT 1";
                
                if($verify_stmt = mysqli_prepare($conn, $verify_sql)) {
                    mysqli_stmt_bind_param($verify_stmt, "ss", $_SESSION["username"], $current_password);
                    
                    if(mysqli_stmt_execute($verify_stmt)) {
                        mysqli_stmt_store_result($verify_stmt);
                        
                        if(mysqli_stmt_num_rows($verify_stmt) == 1) {
                            // Reset code is valid - get the result to check status
                            mysqli_stmt_bind_result($verify_stmt, $reset_username, $reset_email, $reset_code, $approved_at, $reset_status);
                            mysqli_stmt_fetch($verify_stmt);
                            $reset_code_valid = true;
                        } else {
                            // Prefer current password error messaging unless truly in reset flow
                            $current_password_err = "Current password is not correct.";
                        }
                    } else {
                        $current_password_err = "Oops! Something went wrong. Please try again later.";
                    }
                    
                    mysqli_stmt_close($verify_stmt);
                }
            } else if(!$is_current_password_valid) {
                // Not a reset code-looking input; just a wrong current password
                $current_password_err = "Current password is not correct.";
            }
        }
        
        // Validate new password
        if(empty(trim($_POST["new_password"]))) {
            $new_password_err = "Please enter a new password.";     
        } elseif(strlen(trim($_POST["new_password"])) < 6) {
            $new_password_err = "Password must have at least 6 characters.";
        } else {
            $new_password = trim($_POST["new_password"]);
        }
        
        // Validate confirm password
        if(empty(trim($_POST["confirm_password"]))) {
            $confirm_password_err = "Please confirm the password.";     
        } else {
            $confirm_password = trim($_POST["confirm_password"]);
            if(empty($new_password_err) && ($new_password != $confirm_password)) {
                $confirm_password_err = "Password did not match.";
            }
        }
        
        // Check input errors before updating the database
        if(empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
            // Prepare an update statement
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            
            if($stmt = mysqli_prepare($conn, $sql)) {
                // Bind variables to the prepared statement as parameters
                $param_password = password_hash($new_password, PASSWORD_DEFAULT);
                mysqli_stmt_bind_param($stmt, "si", $param_password, $user_id);
                
                // Attempt to execute the prepared statement
                if(mysqli_stmt_execute($stmt)) {
                    $success_msg = "Password changed successfully!";
                    
                    // If a reset code was used, mark it as 'used' now
                    if(isset($reset_code_valid) && $reset_code_valid && isset($reset_status) && $reset_status === 'approved') {
                        $mark_used_sql = "UPDATE password_reset_requests SET status = 'used' WHERE username = ? AND reset_code = ? AND status = 'approved'";
                        if($mark_stmt = mysqli_prepare($conn, $mark_used_sql)) {
                            mysqli_stmt_bind_param($mark_stmt, "ss", $_SESSION["username"], $current_password);
                            mysqli_stmt_execute($mark_stmt);
                            mysqli_stmt_close($mark_stmt);
                        }
                    }
                    
                    // Clear form fields
                    $current_password = $new_password = $confirm_password = "";
                } else {
                    $error_msg = "Something went wrong. Please try again later.";
                }
                
                // Close statement
                mysqli_stmt_close($stmt);
            }
        }
    } else if(isset($_POST["upload_photo"])) {
        // Handle profile photo upload
        $upload_dir = "../assets/uploads/profile_photos/";
        
        // Create directory if it doesn't exist
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if(isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] == 0) {
            $file = $_FILES["profile_photo"];
            $file_name = $file["name"];
            $file_tmp = $file["tmp_name"];
            $file_size = $file["size"];
            $file_type = $file["type"];
            
            // Validate file type
            $allowed_types = ["image/jpeg", "image/jpg", "image/png", "image/gif"];
            if(!in_array($file_type, $allowed_types)) {
                $error_msg = "Invalid file type. Please upload a JPEG, PNG, or GIF image.";
            } else if($file_size > 5242880) { // 5MB limit
                $error_msg = "File size too large. Please upload an image smaller than 5MB.";
            } else {
                // Get file extension
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Generate unique filename
                $new_filename = "user_" . $user_id . "_" . time() . "." . $file_ext;
                $target_path = $upload_dir . $new_filename;
                
                // Delete old profile photo if exists
                if(!empty($profile_photo) && file_exists("../assets/uploads/profile_photos/" . $profile_photo)) {
                    @unlink("../assets/uploads/profile_photos/" . $profile_photo);
                }
                
                // Move uploaded file
                if(move_uploaded_file($file_tmp, $target_path)) {
                    // Update database
                    $sql = "UPDATE users SET profile_photo = ? WHERE id = ?";
                    if($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, "si", $new_filename, $user_id);
                        
                        if(mysqli_stmt_execute($stmt)) {
                            $profile_photo = $new_filename;
                            $success_msg = "Profile photo uploaded successfully!";
                        } else {
                            $error_msg = "Failed to update profile photo in database.";
                            @unlink($target_path); // Delete uploaded file if DB update fails
                        }
                        
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    $error_msg = "Failed to upload profile photo. Please try again.";
                }
            }
        } else {
            $error_msg = "Please select a valid image file.";
        }
    }
}

// Refresh user data after updates
$sql = "SELECT * FROM users WHERE id = ?";
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            $name = $user['name'];
            $email = $user['email'];
            $profile_photo = isset($user['profile_photo']) ? $user['profile_photo'] : "";
            $joining_date = isset($user['joining_date']) ? $user['joining_date'] : null;
            $date_of_birth = isset($user['date_of_birth']) ? $user['date_of_birth'] : null;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Determine profile photo path
$profile_photo_path = "";
if(!empty($profile_photo) && file_exists("../assets/uploads/profile_photos/" . $profile_photo)) {
    $profile_photo_path = "../assets/uploads/profile_photos/" . $profile_photo;
} else {
    // Check for legacy format
    $legacy_path = "../assets/uploads/profile_photos/user_" . $user_id . ".png";
    if(file_exists($legacy_path)) {
        $profile_photo_path = $legacy_path;
    }
}
?>

<div class="content-area">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0" style="color: var(--dark-text-primary, #ffffff);"><?php echo htmlspecialchars($page_title); ?></h2>
            <?php if(isLoggedIn()): ?>
                <p class="mb-0" style="color: var(--dark-text-secondary, #b3b3b3);">Welcome, <strong style="color: var(--dark-text-primary, #ffffff);"><?php echo htmlspecialchars($_SESSION["username"]); ?></strong>!</p>
            <?php endif; ?>
        </div>
        
        <?php if(!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_msg); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_msg); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="row mt-3">
            <!-- Profile Photo Section -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100 profile-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fa fa-user-circle"></i> Profile Photo</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="profile-photo-container mb-3">
                            <?php if(!empty($profile_photo_path)): ?>
                                <img src="<?php echo htmlspecialchars($profile_photo_path); ?>" alt="Profile Photo" class="profile-photo" id="profilePhotoPreview">
                            <?php else: ?>
                                <div class="avatar-placeholder" id="profilePhotoPreview">
                                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" id="photoUploadForm">
                            <div class="form-group">
                                <label for="profile_photo" class="btn btn-primary btn-sm">
                                    <i class="fa fa-upload"></i> Choose Photo
                                </label>
                                <input type="file" name="profile_photo" id="profile_photo" accept="image/jpeg,image/jpg,image/png,image/gif" style="display: none;" onchange="previewPhoto(this)">
                                <small class="form-text text-muted d-block mt-2">Max size: 5MB (JPEG, PNG, GIF)</small>
                            </div>
                            <button type="submit" name="upload_photo" class="btn btn-success btn-sm" id="uploadBtn" style="display: none;">
                                <i class="fa fa-check"></i> Upload Photo
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="cancelBtn" style="display: none;" onclick="cancelPhotoUpload()">
                                <i class="fa fa-times"></i> Cancel
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Profile Information Section -->
            <div class="col-lg-8 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fa fa-info-circle"></i> Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if(!isAdmin()): ?>
                            <div class="alert alert-warning mb-3" style="background-color: rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.3); color: #f59e0b;">
                                <i class="fa fa-info-circle"></i> <strong>Note:</strong> Only administrators can update profile information. Please contact an administrator if you need to make changes.
                            </div>
                        <?php endif; ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name"><i class="fa fa-user"></i> Full Name</label>
                                        <input type="text" name="name" id="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" <?php echo !isAdmin() ? 'disabled' : 'required'; ?>>
                                        <span class="invalid-feedback"><?php echo $name_err; ?></span>
                                        <?php if(!isAdmin()): ?>
                                            <small class="form-text text-muted">Contact an administrator to update this field</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email"><i class="fa fa-envelope"></i> Email</label>
                                        <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" <?php echo !isAdmin() ? 'disabled' : 'required'; ?>>
                                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                                        <?php if(!isAdmin()): ?>
                                            <small class="form-text text-muted">Contact an administrator to update this field</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-user-tag"></i> Username</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION["username"]); ?>" disabled>
                                        <small class="form-text text-muted">Username cannot be changed</small>
                                    </div>
                                </div>
                                <?php if(!isClient()): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-building"></i> Department</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($department_name); ?>" disabled>
                                    </div>
                                </div>
                                <?php else: ?>
                                <?php if(!empty($manager_name)): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-user-tie"></i> Manager</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($manager_name); ?>" disabled>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row">
                                <?php if(!isClient()): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-briefcase"></i> User Type</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($_SESSION["user_type"]); ?>" disabled>
                                    </div>
                                </div>
                                <?php if(!empty($manager_name)): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-user-tie"></i> Manager</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($manager_name); ?>" disabled>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($joining_date): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-calendar-alt"></i> Joining Date</label>
                                        <input type="text" class="form-control" value="<?php echo date('F d, Y', strtotime($joining_date)); ?>" disabled>
                                    </div>
                                </div>
                                <?php if($date_of_birth): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fa fa-birthday-cake"></i> Date of Birth</label>
                                        <input type="text" class="form-control" value="<?php echo date('F d, Y', strtotime($date_of_birth)); ?>" disabled>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if(isAdmin()): ?>
                                <input type="hidden" name="update_profile" value="1">
                                <div class="form-group mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> Update Profile
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Change Password Section -->
        <div class="row mt-3">
            <div class="col-lg-12 mb-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fa fa-key"></i> Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Current Password or Reset Code</label>
                                        <div class="input-group">
                                            <input type="password" name="current_password" id="currentPassword" class="form-control <?php echo (!empty($current_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter current password or 6-digit reset code">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                                    <i class="fa fa-eye-slash" id="currentEyeIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <span class="invalid-feedback"><?php echo $current_password_err; ?></span>
                                        <small class="form-text text-muted">You can use your current password OR the reset code you used to log in</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>New Password</label>
                                        <div class="input-group">
                                            <input type="password" name="new_password" id="newPassword" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter new password">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                                    <i class="fa fa-eye-slash" id="newEyeIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <span class="invalid-feedback"><?php echo $new_password_err; ?></span>
                                        <small class="form-text text-muted">Minimum 6 characters</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Confirm New Password</label>
                                        <div class="input-group">
                                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Confirm new password">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                                    <i class="fa fa-eye-slash" id="confirmEyeIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fa fa-key"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        /* Dark Theme Profile Page Styling */
        .container {
            background-color: transparent;
        }
        
        h2, .card-header h5 {
            color: var(--dark-text-primary, #ffffff) !important;
        }
        
        .text-muted {
            color: var(--dark-text-muted, #808080) !important;
        }
        
        .profile-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            background-color: var(--dark-bg-card, rgba(26, 26, 26, 0.8));
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .profile-photo-container {
            position: relative;
            display: inline-block;
        }
        
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--brand-primary, #6366f1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
        }
        
        .avatar-placeholder {
            width: 150px;
            height: 150px;
            background: var(--gradient-primary, linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%));
            color: white;
            font-size: 64px;
            line-height: 150px;
            text-align: center;
            border-radius: 50%;
            margin: 0 auto;
            border: 4px solid var(--brand-primary, #6366f1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
            font-weight: bold;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            background-color: var(--dark-bg-card, rgba(26, 26, 26, 0.8));
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            color: var(--dark-text-primary, #ffffff);
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            border: none;
            font-weight: 600;
            background-color: var(--dark-bg-secondary, #1a1a1a) !important;
            color: var(--dark-text-primary, #ffffff) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .card-header.bg-primary {
            background: var(--gradient-primary, linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)) !important;
        }
        
        .card-header.bg-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%) !important;
            color: #000000 !important;
        }
        
        .card-body {
            background-color: transparent;
            color: var(--dark-text-primary, #ffffff);
        }
        
        .form-group label {
            font-weight: 500;
            color: var(--dark-text-primary, #ffffff);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            background-color: var(--dark-bg-tertiary, #2a2a2a);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--dark-text-primary, #ffffff);
            border-radius: 8px;
        }
        
        .form-control:focus {
            background-color: var(--dark-bg-tertiary, #2a2a2a);
            border-color: var(--brand-primary, #6366f1);
            color: var(--dark-text-primary, #ffffff);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        
        .form-control::placeholder {
            color: var(--dark-text-muted, #808080);
        }
        
        .form-control:disabled {
            background-color: var(--dark-bg-secondary, #1a1a1a);
            border-color: rgba(255, 255, 255, 0.1);
            color: var(--dark-text-secondary, #b3b3b3);
            opacity: 0.7;
        }
        
        .form-text.text-muted {
            color: var(--dark-text-muted, #808080) !important;
        }
        
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
            background-color: var(--dark-bg-tertiary, #2a2a2a);
            border-color: rgba(255, 255, 255, 0.2);
            color: var(--dark-text-secondary, #b3b3b3);
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            transition: all 0.15s ease-in-out;
        }
        
        .input-group-append .btn:hover {
            background-color: var(--dark-bg-secondary, #1a1a1a);
            border-color: rgba(255, 255, 255, 0.3);
            color: var(--dark-text-primary, #ffffff);
        }
        
        .input-group-append .btn:focus {
            box-shadow: none;
            outline: none;
        }
        
        .input-group-append .btn:active {
            background-color: var(--dark-bg-secondary, #1a1a1a);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .input-group > * {
            display: flex;
            align-items: stretch;
        }
        
        .input-group-append .btn {
            height: calc(1.5em + 0.75rem + 2px);
        }
        
        .btn {
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .btn-primary {
            background: var(--gradient-primary, linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%));
            border: none;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
        }
        
        .btn-success {
            background: var(--gradient-secondary, linear-gradient(135deg, #06b6d4 0%, #10b981 100%));
            border: none;
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            border: none;
            color: #000000;
        }
        
        .btn-warning:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
            background: linear-gradient(135deg, #fbbf24 0%, #fcd34d 100%);
        }
        
        .btn-secondary {
            background-color: var(--dark-bg-tertiary, #2a2a2a);
            border-color: rgba(255, 255, 255, 0.2);
            color: var(--dark-text-primary, #ffffff);
        }
        
        .btn-secondary:hover {
            background-color: var(--dark-bg-secondary, #1a1a1a);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            background-color: var(--dark-bg-card, rgba(26, 26, 26, 0.8));
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.2);
            border-color: rgba(16, 185, 129, 0.3);
            color: #10b981;
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        
        .alert .close {
            color: var(--dark-text-primary, #ffffff);
            opacity: 0.8;
        }
        
        .alert .close:hover {
            opacity: 1;
        }
        
        .invalid-feedback {
            color: #ef4444;
            font-size: 0.875rem;
        }
        
        .is-invalid {
            border-color: #ef4444 !important;
        }
        
        .is-invalid:focus {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 0.2rem rgba(239, 68, 68, 0.25) !important;
        }
    </style>
    
    <script>
        // Password visibility toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Current Password toggle
            document.getElementById('toggleCurrentPassword').addEventListener('click', function() {
                const passwordInput = document.getElementById('currentPassword');
                const eyeIcon = document.getElementById('currentEyeIcon');
                togglePasswordVisibility(passwordInput, eyeIcon);
            });
            
            // New Password toggle
            document.getElementById('toggleNewPassword').addEventListener('click', function() {
                const passwordInput = document.getElementById('newPassword');
                const eyeIcon = document.getElementById('newEyeIcon');
                togglePasswordVisibility(passwordInput, eyeIcon);
            });
            
            // Confirm Password toggle
            document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
                const passwordInput = document.getElementById('confirmPassword');
                const eyeIcon = document.getElementById('confirmEyeIcon');
                togglePasswordVisibility(passwordInput, eyeIcon);
            });
            
            // Function to toggle password visibility
            function togglePasswordVisibility(passwordInput, eyeIcon) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                } else {
                    passwordInput.type = 'password';
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                }
            }
        });
        
        // Profile photo preview and upload
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePhotoPreview');
                    const uploadBtn = document.getElementById('uploadBtn');
                    const cancelBtn = document.getElementById('cancelBtn');
                    
                    // Check if it's an image placeholder or actual image
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        // Replace placeholder with image
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Profile Photo Preview';
                        img.className = 'profile-photo';
                        img.id = 'profilePhotoPreview';
                        preview.parentNode.replaceChild(img, preview);
                    }
                    
                    // Show upload and cancel buttons
                    uploadBtn.style.display = 'inline-block';
                    cancelBtn.style.display = 'inline-block';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function cancelPhotoUpload() {
            const input = document.getElementById('profile_photo');
            const uploadBtn = document.getElementById('uploadBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const preview = document.getElementById('profilePhotoPreview');
            
            // Reset file input
            input.value = '';
            
            // Hide buttons
            uploadBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
            
            // Reset preview to original
            <?php if(!empty($profile_photo_path)): ?>
                preview.src = '<?php echo htmlspecialchars($profile_photo_path); ?>';
            <?php else: ?>
                // Replace image with placeholder if needed
                if (preview.tagName === 'IMG') {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'avatar-placeholder';
                    placeholder.id = 'profilePhotoPreview';
                    placeholder.textContent = '<?php echo strtoupper(substr($name, 0, 1)); ?>';
                    preview.parentNode.replaceChild(placeholder, preview);
                }
            <?php endif; ?>
        }
        
        // Show file name when selected
        document.getElementById('profile_photo').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
            }
        });
    </script>

<?php require_once "../includes/footer.php";
?>
