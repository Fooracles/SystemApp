<?php
require_once 'config.php';
require_once 'functions.php';

// Protect all pages: Redirect to login if user is not logged in
// Skip this check for login.php and index.php to avoid redirect loops
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$php_self = $_SERVER['PHP_SELF'] ?? '';

// Check if this is a login page or index (which redirects to login)
$is_login_page = (strpos($request_uri, 'login.php') !== false || 
                  strpos($php_self, 'login.php') !== false ||
                  strpos($request_uri, 'index.php') !== false ||
                  strpos($php_self, 'index.php') !== false);

// Check if this is an AJAX/action/API file (they handle auth separately)
$is_action_file = (strpos($request_uri, '/ajax/') !== false ||
                  strpos($request_uri, '/api/') !== false ||
                  strpos($php_self, '/ajax/') !== false ||
                  strpos($php_self, '/api/') !== false ||
                  strpos(basename($php_self), 'action_') === 0);

// Only redirect regular pages if user is not logged in
if (!$is_login_page && !$is_action_file && !isLoggedIn()) {
    // Save the current URL for redirect after login
    $current_url = $request_uri;
    $_SESSION['redirect_after_login'] = $current_url;
    
    // Calculate the path to login.php relative to document root
    // Extract the base directory from PHP_SELF (e.g., /FMS-4.31/pages/)
    $script_dir = dirname($php_self);
    
    // If the script is in pages/ directory, go up one level to reach login.php
    if (strpos($script_dir, '/pages') !== false || strpos($script_dir, '\\pages') !== false) {
        // Remove /pages or \pages from the path
        $base_path = preg_replace('#[/\\\\]pages[/\\\\]?$#', '', $script_dir);
    } else {
        $base_path = $script_dir;
    }
    
    // Normalize the path
    if ($base_path === '/' || $base_path === '\\' || empty($base_path)) {
        $login_path = '/login.php';
    } else {
        $login_path = rtrim($base_path, '/\\') . '/login.php';
    }
    
    header("Location: " . $login_path);
    exit;
}

// Auto-logout all sessions at 8:00 PM (20:00)
if (!$is_login_page && !$is_action_file && isset($conn)) {
    autoLogoutAllSessionsAt8PM($conn);
}

// Check if current time is 8:00 PM - logout current user if logged in
if (!$is_login_page && !$is_action_file && isLoggedIn() && isset($conn)) {
    if (isSessionExpired()) {
        // Log the logout
        if (isset($_SESSION["username"])) {
            $username = $_SESSION["username"];
            logUserLogout($conn, $username, 'auto');
        }
        
        // Destroy session and redirect to login
        session_destroy();
        
        // Calculate login path
        $script_dir = dirname($php_self);
        if (strpos($script_dir, '/pages') !== false || strpos($script_dir, '\\pages') !== false) {
            $base_path = preg_replace('#[/\\\\]pages[/\\\\]?$#', '', $script_dir);
        } else {
            $base_path = $script_dir;
        }
        
        if ($base_path === '/' || $base_path === '\\' || empty($base_path)) {
            $login_path = '/login.php';
        } else {
            $login_path = rtrim($base_path, '/\\') . '/login.php';
        }
        
        header("Location: " . $login_path . "?expired=1");
        exit;
    }
    
    // Check if user is marked as Inactive in the database
    if (isset($_SESSION["username"])) {
        $username = $_SESSION["username"];
        
        // First check if Status column exists in users table
        $column_check = "SHOW COLUMNS FROM users LIKE 'Status'";
        $column_result = mysqli_query($conn, $column_check);
        
        if ($column_result && mysqli_num_rows($column_result) > 0) {
            // Status column exists, proceed with status check
            $check_status_sql = "SELECT Status FROM users WHERE username = ?";
            if ($check_stmt = mysqli_prepare($conn, $check_status_sql)) {
                mysqli_stmt_bind_param($check_stmt, "s", $username);
                mysqli_stmt_execute($check_stmt);
                $status_result = mysqli_stmt_get_result($check_stmt);
                if ($status_row = mysqli_fetch_assoc($status_result)) {
                    $user_status = ucfirst(strtolower(trim($status_row['Status'] ?? 'Active')));
                    if ($user_status === 'Inactive') {
                        // Log the logout with reason
                        logUserLogout($conn, $username, 'user_inactive');
                        
                        // Destroy session and redirect to login
                        session_destroy();
                        
                        // Calculate login path
                        $script_dir = dirname($php_self);
                        if (strpos($script_dir, '/pages') !== false || strpos($script_dir, '\\pages') !== false) {
                            $base_path = preg_replace('#[/\\\\]pages[/\\\\]?$#', '', $script_dir);
                        } else {
                            $base_path = $script_dir;
                        }
                        
                        if ($base_path === '/' || $base_path === '\\' || empty($base_path)) {
                            $login_path = '/login.php';
                        } else {
                            $login_path = rtrim($base_path, '/\\') . '/login.php';
                        }
                        
                        header("Location: " . $login_path . "?inactive=1");
                        exit;
                    }
                }
                mysqli_stmt_close($check_stmt);
            }
        }
        // If Status column doesn't exist, skip the check (assume user is active)
    }
}

// Get today's special events (with caching) - moved to top for availability
$special_events = [];
if (isset($conn) && $conn) {
    $cache_key = 'todays_events_' . date('Y-m-d');
    if (!isset($_SESSION[$cache_key])) {
        if (isset($_GET['debug_special_events'])) {
            error_log("Header: Cache miss, calling getTodaysSpecialEvents for user: " . ($_SESSION['username'] ?? 'unknown'));
        }
        $_SESSION[$cache_key] = getTodaysSpecialEvents($conn);
    } else {
        if (isset($_GET['debug_special_events'])) {
            error_log("Header: Using cached events for user: " . ($_SESSION['username'] ?? 'unknown') . ", Count: " . count($_SESSION[$cache_key]));
        }
    }
    $special_events = $_SESSION[$cache_key];
    
    // Check and trigger day special notifications (only once per day globally)
    // Use a flag file to ensure we only run once per day across all users
    $flag_file = sys_get_temp_dir() . '/day_special_notifications_' . date('Y-m-d') . '.flag';
    if (!file_exists($flag_file)) {
        require_once __DIR__ . '/notification_triggers.php';
        triggerDaySpecialNotifications($conn);
        // Flag file is created inside triggerDaySpecialNotifications function
    }
    
    // Check for overdue checklist tasks (only once per day globally)
    // Use a flag file to ensure we only run once per day across all users
    $checklist_flag_file = sys_get_temp_dir() . '/checklist_overdue_notifications_' . date('Y-m-d') . '.flag';
    if (!file_exists($checklist_flag_file)) {
        require_once __DIR__ . '/notification_triggers.php';
        checkOverdueChecklistTasks($conn);
        // Create flag file to mark that we've processed today
        file_put_contents($checklist_flag_file, date('Y-m-d H:i:s'));
    }
    
    if (isset($_GET['debug_special_events'])) {
        error_log("Header: Final special_events count: " . count($special_events) . " for user: " . ($_SESSION['username'] ?? 'unknown'));
    }
} else {
    if (isset($_GET['debug_special_events'])) {
        error_log("Header: No database connection available");
    }
}

// Debug information (remove in production)
if (isset($_GET['debug_special_events'])) {
    echo "<!-- DEBUG: Special Events Data -->";
    echo "<!-- Cache Key: " . (isset($cache_key) ? $cache_key : 'Not set') . " -->";
    echo "<!-- Special Events Count: " . count($special_events) . " -->";
    echo "<!-- Special Events Data: " . json_encode($special_events) . " -->";
    echo "<!-- Today's Date: " . date('Y-m-d') . " -->";
    echo "<!-- Today's MM-DD: " . date('m-d') . " -->";
    echo "<!-- Database Connection: " . (isset($conn) && $conn ? 'Available' : 'Not available') . " -->";
    echo "<!-- User Type: " . ($_SESSION['user_type'] ?? 'Not set') . " -->";
    echo "<!-- Is Logged In: " . (isLoggedIn() ? 'Yes' : 'No') . " -->";
    echo "<!-- Day Special Pill Classes: " . (!empty($special_events) ? 'has-special-events' : '') . " -->";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fooracles System</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="../assets/images/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="../assets/images/android-chrome-512x512.png">
    
    <?php echo csrfMetaTag(); ?>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime('../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="../assets/css/table-sorter.css?v=<?php echo filemtime('../assets/css/table-sorter.css'); ?>">
    <?php if(isset($page_title) && $page_title === "Doer Dashboard"): ?>
    <link rel="stylesheet" href="../assets/css/doer_dashboard.css?v=<?php echo filemtime('../assets/css/doer_dashboard.css'); ?>">
    <?php endif; ?>
</head>
<body class="<?php echo isLoggedIn() ? 'two-frame-layout' : ''; ?>">
    <?php if(isLoggedIn()): ?>
    <div class="app-frame">
        <div class="main-wrapper">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <!-- Sticky Header -->
                <div class="sticky-header">
                    <div class="header-content">
                        <div class="header-left-controls">
                            <button type="button" class="sidebar-toggle" id="sidebarToggleBtn" aria-label="Toggle sidebar">
                                <i class="fas fa-bars" aria-hidden="true"></i>
                            </button>
                        </div>
                        <!-- Centered Title -->
                        <div class="header-title">
                            <h2></h2>
                        </div> 
                        
                        <!-- Right Side Controls -->
                        <div class="header-controls">
                            <!-- All Notifications Bell -->
                            <div class="dropdown day-special-dropdown notifications-dropdown">
                                <span class="day-special-pill notifications-bell" id="notificationsBell" title="Notifications">
                                    <i class="fas fa-bell"></i>
                                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                                </span>
                                
                                <!-- Dropdown Menu -->
                                <div class="dropdown-menu day-special-menu notifications-menu" id="notificationsMenu">
                                    <div class="dropdown-header">
                                        <h6><i class="fas fa-bell"></i> All Notifications</h6>
                                        <button class="btn-mark-all-read" id="markAllReadBtn" title="Mark all as read">
                                            <i class="fas fa-check-double"></i>
                                        </button>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <div class="notifications-content" id="notificationsContent">
                                        <div class="notification-loading">
                                            <i class="fas fa-spinner fa-spin"></i> Loading notifications...
                                        </div>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <div class="dropdown-item-text text-center">
                                        <small id="notificationsFooter">No notifications</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Dropdowns -->
                            <?php if (!isClient()): ?>
                            <div class="dropdown form-dropdown">
                                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="form1Dropdown" data-toggle="dropdown">
                                    All Forms
                                </button>
                                <div class="dropdown-menu">
                                    <?php 
                                    // Get accessible forms for current user (with caching)
                                    $cache_key = 'accessible_forms_' . $_SESSION['id'] . '_' . $_SESSION['user_type'];
                                    if (!isset($_SESSION[$cache_key])) {
                                        $_SESSION[$cache_key] = getAccessibleForms($conn);
                                    }
                                    $accessible_forms = $_SESSION[$cache_key];
                                    if (!empty($accessible_forms)): 
                                        foreach($accessible_forms as $form): 
                                    ?>
                                        <a class="dropdown-item" href="<?php echo htmlspecialchars($form['form_url']); ?>" target="_blank">
                                            <i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($form['form_name']); ?>
                                        </a>
                                    <?php 
                                        endforeach; 
                                    else: 
                                    ?>
                                        <a class="dropdown-item disabled" href="#">
                                            <i class="fas fa-info-circle"></i> No forms available
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                             
                            <!-- Profile Dropdown -->
                            <div class="dropdown profile-dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="profileDropdown" data-toggle="dropdown">
                                    My Account
            </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                                    <?php if (isClient()): ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="javascript:void(0);" onclick="openRaiseTicketModal()"><i class="fas fa-ticket-alt"></i> Raise Ticket</a>
                                    <a class="dropdown-item" href="https://forms.gle/hepvHsZb4PY36Qrp6" target="_blank" rel="noopener noreferrer"><i class="fas fa-comment-dots"></i> Feedback</a>
                                    <?php endif; ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                                 </div>
                            </div>
                        </div>
            </div>
        </div>
                
                <div class="content-area">
    <?php else: ?>
    <div class="container mt-4"> 
    <?php endif; ?>

<!-- Raise Ticket Modal - Global (Available on all pages) -->
<div id="raiseTicketModal" class="modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">
    <div class="modal-content" style="background-color: #1e293b; color: #fff; border-radius: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,0.6); padding: 0; width: 90%; max-width: 500px; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff;">Raise Ticket</h2>
            <button onclick="closeRaiseTicketModal()" style="background: transparent; border: none; color: rgba(255,255,255,0.7); font-size: 1.5rem; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 0.375rem; transition: all 0.2s ease;" onmouseover="this.style.color='#fff'; this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.color='rgba(255,255,255,0.7)'; this.style.background='transparent'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding: 1.25rem; overflow-y: auto; flex: 1;">
            <form id="raiseTicketForm" onsubmit="handleRaiseTicketSubmit(event)">
                <!-- Title Field -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; color: #cbd5e1; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.875rem;">Title <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="ticketTitle" name="title" required placeholder="Enter ticket title..." style="width: 100%; padding: 0.625rem; background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.5rem; color: #e2e8f0; font-size: 0.875rem; transition: all 0.3s ease;" onfocus="this.style.borderColor='rgba(139,92,246,0.5)'; this.style.boxShadow='0 0 0 3px rgba(139,92,246,0.1)'" onblur="this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='none'">
                </div>

                <!-- Description Field -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; color: #cbd5e1; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.875rem;">Description <span style="color: #ef4444;">*</span></label>
                    <textarea id="ticketDescription" name="description" required placeholder="Enter ticket description..." rows="4" style="width: 100%; padding: 0.625rem; background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.5rem; color: #e2e8f0; font-size: 0.875rem; resize: vertical; transition: all 0.3s ease; font-family: inherit;" onfocus="this.style.borderColor='rgba(139,92,246,0.5)'; this.style.boxShadow='0 0 0 3px rgba(139,92,246,0.1)'" onblur="this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='none'"></textarea>
                </div>

                <!-- Attach Media Field -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; color: #cbd5e1; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.875rem;">Attach Media (Optional)</label>
                    <div id="ticketFileDropZone" onclick="document.getElementById('ticketFileInput').click()" style="border: 2px dashed rgba(255,255,255,0.2); border-radius: 0.5rem; padding: 1.5rem; text-align: center; cursor: pointer; background: rgba(30, 41, 59, 0.3); transition: all 0.3s ease;" onmouseover="this.style.borderColor='rgba(139,92,246,0.5)'; this.style.background='rgba(30, 41, 59, 0.5)'" onmouseout="this.style.borderColor='rgba(255,255,255,0.2)'; this.style.background='rgba(30, 41, 59, 0.3)'">
                        <i class="fas fa-upload" style="font-size: 2rem; color: rgba(255,255,255,0.5); margin-bottom: 0.5rem; display: block;"></i>
                        <p style="color: #cbd5e1; margin: 0.25rem 0; font-size: 0.875rem;">Drag & drop or click to browse</p>
                        <p style="color: rgba(255,255,255,0.5); margin: 0; font-size: 0.75rem;">Docs, Images, Video, Audio</p>
                        <p style="color: rgba(255,255,255,0.5); margin: 0.25rem 0 0 0; font-size: 0.75rem;">
                            <i class="fas fa-info-circle"></i> Max file size: 50 MB
                        </p>
                    </div>
                    <input type="file" id="ticketFileInput" name="attachments[]" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt" style="display: none;" onchange="handleTicketFileSelect(event)">
                    
                    <!-- File Previews -->
                    <div id="ticketFilePreviews" style="margin-top: 0.75rem; display: none;"></div>
                </div>

                <!-- Form Actions -->
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 1rem;">
                    <button type="button" onclick="closeRaiseTicketModal()" style="padding: 0.625rem 1.25rem; background: rgba(51, 65, 85, 0.8); border: none; color: #fff; border-radius: 0.5rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: 0.875rem;" onmouseover="this.style.background='rgba(51, 65, 85, 1)'" onmouseout="this.style.background='rgba(51, 65, 85, 0.8)'">
                        Cancel
                    </button>
                    <button type="submit" style="padding: 0.625rem 1.25rem; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border: none; color: #fff; border-radius: 0.5rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4); font-size: 0.875rem;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 20px rgba(139, 92, 246, 0.5)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(139, 92, 246, 0.4)'">
                        <i class="fas fa-ticket-alt"></i> Raise Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Meeting Book Modal - Global (Available on all pages) -->
<div id="meetingBookModal" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content meeting-modal" style="background-color: #1e1e1e; color: #fff; border-radius: 10px; box-shadow: 0 0 25px rgba(0,0,0,0.6); padding: 20px; width: 90%; max-width: 500px; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #fff;">Book a Meeting with Admin</h3>
            <span class="close-meeting-modal" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
        </div>
        <form id="meetingBookForm">
            <div class="form-group mb-3">
                <label for="reason" style="color: #fff;">Agenda <span style="color: red;">*</span></label>
                <textarea class="form-control" id="reason" name="reason" rows="3" required placeholder="Enter the agenda for the meeting" style="background-color: #2a2a2a; color: #fff; border-color: #444;"></textarea>
            </div>
            <div class="form-group mb-3">
                <label for="duration" style="color: #fff;">Duration <span style="color: red;">*</span></label>
                <select class="form-control" id="duration" name="duration" required style="background-color: #2a2a2a; color: #fff; border-color: #444;">
                    <option value="">Select Duration</option>
                    <option value="00:15:00">15 minutes</option>
                    <option value="00:30:00">30 minutes</option>
                    <option value="00:45:00">45 minutes</option>
                    <option value="01:00:00">1 hour</option>
                    <option value="02:00:00">2 hours</option>
                </select>
            </div>
            <div class="form-group mb-3">
                <label for="preferred_datetime" style="color: #fff;">Preferred Date & Time <span style="color: red;">*</span></label>
                <div style="position: relative;" id="preferredDatetimeContainer">
                    <input type="datetime-local" class="form-control" id="preferred_datetime" name="preferred_datetime" required style="background-color: #2a2a2a; color: #fff; border-color: #444; cursor: pointer; position: relative; z-index: 2; padding-right: 40px;" lang="en-GB" min="">
                    <i class="fas fa-calendar-alt" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #fff; pointer-events: none; z-index: 1;"></i>
                    <span id="preferredDatetimePlaceholder" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #888; pointer-events: none; z-index: 1; font-size: 14px; user-select: none;">dd/mm/yyyy --:-- --</span>
                </div>
            </div>
            <style>
                /* Hide placeholder when input has value or is focused */
                #preferred_datetime:not(:placeholder-shown) ~ #preferredDatetimePlaceholder,
                #preferred_datetime:focus ~ #preferredDatetimePlaceholder,
                #preferred_datetime:valid ~ #preferredDatetimePlaceholder {
                    display: none;
                }
                
                /* Style datetime-local input */
                #preferred_datetime::-webkit-datetime-edit-day-field,
                #preferred_datetime::-webkit-datetime-edit-month-field,
                #preferred_datetime::-webkit-datetime-edit-year-field {
                    color: #fff;
                }
                
                #preferred_datetime::-webkit-datetime-edit-text {
                    color: #888;
                }
                
                /* Make calendar icon white and make whole field clickable */
                #preferred_datetime::-webkit-calendar-picker-indicator {
                    opacity: 0;
                    position: absolute;
                    right: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    cursor: pointer;
                    z-index: 10;
                }
                
                /* Make the text area also clickable */
                #preferred_datetime::-webkit-datetime-edit {
                    cursor: pointer;
                    width: 100%;
                    position: relative;
                    z-index: 1;
                }
                
                #preferred_datetime::-webkit-datetime-edit-fields-wrapper {
                    cursor: pointer;
                }
                
                #preferredDatetimeContainer {
                    cursor: pointer;
                }
                
                #preferredDatetimeContainer input {
                    cursor: pointer;
                }
                
                /* For Firefox - make entire field clickable */
                @-moz-document url-prefix() {
                    #preferred_datetime {
                        cursor: pointer;
                    }
                }
            </style>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- Meeting Reschedule Modal - Global (Available on all pages) -->
<div id="meetingRescheduleModal" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content meeting-modal" style="background-color: #1e1e1e; color: #fff; border-radius: 10px; box-shadow: 0 0 25px rgba(0,0,0,0.6); padding: 20px; width: 90%; max-width: 500px; position: fixed; top: 20px; left: 50%; transform: translateX(-50%);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #fff;">Re-Schedule Meeting</h3>
            <span class="close-reschedule-modal" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
        </div>
        <form id="meetingRescheduleForm">
            <input type="hidden" id="rescheduleMeetingId" name="meeting_id">
            <input type="hidden" id="rescheduleNotificationId" name="notification_id">
            <div class="form-group mb-3">
                <label for="rescheduleDateTime" style="color: #fff;">Date & Time <span style="color: red;">*</span></label>
                <div style="position: relative;" id="rescheduleDatetimeContainer">
                    <input type="datetime-local" class="form-control" id="rescheduleDateTime" name="scheduled_date" required style="background-color: #2a2a2a; color: #fff; border-color: #444; cursor: pointer; position: relative; z-index: 2; padding-right: 40px;" lang="en-GB" min="">
                    <i class="fas fa-calendar-alt" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #fff; pointer-events: none; z-index: 1;"></i>
                    <span id="rescheduleDatetimePlaceholder" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #888; pointer-events: none; z-index: 1; font-size: 14px; user-select: none;">dd/mm/yyyy --:-- --</span>
                </div>
            </div>
            <div id="rescheduleCurrentScheduleInfo" style="display: none; padding: 10px; background-color: #2a2a2a; border-radius: 5px; margin-bottom: 15px; color: #fff;">
                <small><strong>Current Schedule:</strong> <span id="rescheduleCurrentScheduleText"></span></small>
            </div>
            <div class="form-group mb-3">
                <label for="rescheduleComment" style="color: #fff;">Comment/Reason <span style="color: #888; font-size: 0.85em;">(Optional)</span></label>
                <textarea class="form-control" id="rescheduleComment" name="schedule_comment" rows="3" placeholder="Add any comments or reasons for re-scheduling this meeting..." style="background-color: #2a2a2a; color: #fff; border-color: #444; resize: vertical;"></textarea>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary close-reschedule-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
// CSRF Token Auto-Attach for fetch() API
// This must run BEFORE any fetch() calls on the page
(function() {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    if (csrfToken) {
        window.CSRF_TOKEN = csrfToken;

        var originalFetch = window.fetch;
        window.fetch = function(url, options) {
            options = options || {};
            var method = (options.method || 'GET').toUpperCase();
            var isJsonRequest = false;

            if (options.headers instanceof Headers) {
                var h = options.headers.get('Content-Type');
                isJsonRequest = !!(h && h.toLowerCase().indexOf('application/json') !== -1);
            } else if (options.headers && typeof options.headers === 'object') {
                var ct = options.headers['Content-Type'] || options.headers['content-type'] || '';
                isJsonRequest = String(ct).toLowerCase().indexOf('application/json') !== -1;
            }

            // Fallback heuristic for JSON-string bodies even when header is absent.
            if (!isJsonRequest && typeof options.body === 'string') {
                var trimmed = options.body.trim();
                isJsonRequest = trimmed.startsWith('{') || trimmed.startsWith('[');
            }

            if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
                // Append token to FormData bodies
                if (options.body instanceof FormData) {
                    if (!options.body.has('csrf_token')) {
                        options.body.append('csrf_token', csrfToken);
                    }
                }
                // Append token to URLSearchParams bodies
                else if (options.body instanceof URLSearchParams) {
                    if (!options.body.has('csrf_token')) {
                        options.body.append('csrf_token', csrfToken);
                    }
                }
                // For string bodies (application/x-www-form-urlencoded)
                else if (!isJsonRequest && typeof options.body === 'string' && options.body.indexOf('csrf_token=') === -1) {
                    options.body += (options.body ? '&' : '') + 'csrf_token=' + encodeURIComponent(csrfToken);
                }

                // Also set the header as a fallback
                if (!options.headers) {
                    options.headers = {};
                }
                if (options.headers instanceof Headers) {
                    if (!options.headers.has('X-CSRF-TOKEN')) {
                        options.headers.set('X-CSRF-TOKEN', csrfToken);
                    }
                } else if (typeof options.headers === 'object') {
                    if (!options.headers['X-CSRF-TOKEN']) {
                        options.headers['X-CSRF-TOKEN'] = csrfToken;
                    }
                }
            }

            return originalFetch.call(this, url, options);
        };
    }
})();
</script>

<script>
// Global Meeting Booking Functionality
(function() {
    // Close Modal Handlers
    const closeModalBtns = document.querySelectorAll('.close-meeting-modal');
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('meetingBookModal').style.display = 'none';
            const form = document.getElementById('meetingBookForm');
            if (form) form.reset();
        });
    });
    
    // Modal should NOT close when clicking outside - only via close button
    // Removed click-outside-to-close functionality
    
    // Set minimum date/time for preferred datetime picker (now) and handle placeholder
    const preferredDatetimeInput = document.getElementById('preferred_datetime');
    const preferredDatetimePlaceholder = document.getElementById('preferredDatetimePlaceholder');
    const preferredDatetimeContainer = document.getElementById('preferredDatetimeContainer');
    
    if (preferredDatetimeInput) {
        // Set minimum to current date and time
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        preferredDatetimeInput.min = `${year}-${month}-${day}T${hours}:${minutes}`;
        
        // Make the whole field clickable to open date picker
        if (preferredDatetimeContainer) {
            preferredDatetimeContainer.addEventListener('click', function(e) {
                // Prevent double trigger if clicking directly on input
                if (e.target !== preferredDatetimeInput) {
                    preferredDatetimeInput.focus();
                    // Use showPicker if available, otherwise just focus (browser will show native picker)
                    if (preferredDatetimeInput.showPicker && typeof preferredDatetimeInput.showPicker === 'function') {
                        preferredDatetimeInput.showPicker();
                    }
                }
            });
            
            // Also make input clickable
            preferredDatetimeInput.addEventListener('click', function(e) {
                e.stopPropagation();
                if (this.showPicker && typeof this.showPicker === 'function') {
                    this.showPicker();
                }
            });
        }
        
        // Hide placeholder when input has value
        if (preferredDatetimePlaceholder) {
            preferredDatetimeInput.addEventListener('input', function() {
                if (this.value) {
                    preferredDatetimePlaceholder.style.display = 'none';
                } else {
                    preferredDatetimePlaceholder.style.display = 'block';
                }
            });
            
            preferredDatetimeInput.addEventListener('focus', function() {
                preferredDatetimePlaceholder.style.display = 'none';
            });
            
            preferredDatetimeInput.addEventListener('blur', function() {
                if (!this.value) {
                    preferredDatetimePlaceholder.style.display = 'block';
                }
            });
            
            // Check initial state
            if (preferredDatetimeInput.value) {
                preferredDatetimePlaceholder.style.display = 'none';
            }
        }
    }
    
    // Form Submission Handler
    const meetingBookForm = document.getElementById('meetingBookForm');
    if (meetingBookForm) {
        meetingBookForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create');
            
            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            
            // Determine correct path based on current page location
            let ajaxPath = '../ajax/meeting_handler.php';
            if (window.location.pathname.includes('/pages/')) {
                ajaxPath = '../ajax/meeting_handler.php';
            } else {
                ajaxPath = 'ajax/meeting_handler.php';
            }
            
            fetch(ajaxPath, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Invalid response from server');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // Show success toast
                    showMeetingToast('success', data.message);
                    
                    // Close modal and reset form
                    document.getElementById('meetingBookModal').style.display = 'none';
                    this.reset();
                    
                    // Reload meetings if on meetings page
                    if (typeof loadMyHistoryMeetings === 'function') {
                        loadMyHistoryMeetings();
                    }
                    // Also refresh admin views if admin is viewing meetings page
                    if (typeof loadScheduledMeetings === 'function') {
                        loadScheduledMeetings();
                    }
                    if (typeof loadHistoryMeetings === 'function') {
                        loadHistoryMeetings();
                    }
                } else {
                    showMeetingToast('error', data.error || 'Failed to submit meeting request');
                }
            })
            .catch(error => {
                console.error('Meeting booking error:', error);
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack,
                    url: ajaxPath
                });
                showMeetingToast('error', 'An error occurred while submitting the request: ' + error.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // Toast notification function
    function showMeetingToast(type, message) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close"></button>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }
})();

// Global Meeting Reschedule Functionality
(function() {
    // Close Modal Handlers
    const closeRescheduleModalBtns = document.querySelectorAll('.close-reschedule-modal');
    closeRescheduleModalBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('meetingRescheduleModal').style.display = 'none';
            const form = document.getElementById('meetingRescheduleForm');
            if (form) form.reset();
        });
    });
    
    // Modal should NOT close when clicking outside - only via close button
    // Removed click-outside-to-close functionality
    
    // Set minimum date/time for reschedule datetime picker and handle placeholder
    const rescheduleDatetimeInput = document.getElementById('rescheduleDateTime');
    const rescheduleDatetimePlaceholder = document.getElementById('rescheduleDatetimePlaceholder');
    const rescheduleDatetimeContainer = document.getElementById('rescheduleDatetimeContainer');
    
    if (rescheduleDatetimeInput) {
        // Set minimum to current date and time
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        rescheduleDatetimeInput.min = `${year}-${month}-${day}T${hours}:${minutes}`;
        
        // Make the whole field clickable to open date picker
        if (rescheduleDatetimeContainer) {
            rescheduleDatetimeContainer.addEventListener('click', function(e) {
                if (e.target !== rescheduleDatetimeInput) {
                    rescheduleDatetimeInput.focus();
                    if (rescheduleDatetimeInput.showPicker && typeof rescheduleDatetimeInput.showPicker === 'function') {
                        rescheduleDatetimeInput.showPicker();
                    }
                }
            });
            
            rescheduleDatetimeInput.addEventListener('click', function(e) {
                e.stopPropagation();
                if (this.showPicker && typeof this.showPicker === 'function') {
                    this.showPicker();
                }
            });
        }
        
        // Hide placeholder when input has value
        if (rescheduleDatetimePlaceholder) {
            rescheduleDatetimeInput.addEventListener('input', function() {
                if (this.value) {
                    rescheduleDatetimePlaceholder.style.display = 'none';
                } else {
                    rescheduleDatetimePlaceholder.style.display = 'block';
                }
            });
            
            rescheduleDatetimeInput.addEventListener('focus', function() {
                rescheduleDatetimePlaceholder.style.display = 'none';
            });
            
            rescheduleDatetimeInput.addEventListener('blur', function() {
                if (!this.value) {
                    rescheduleDatetimePlaceholder.style.display = 'block';
                }
            });
        }
    }
    
    // Form Submission Handler
    const meetingRescheduleForm = document.getElementById('meetingRescheduleForm');
    if (meetingRescheduleForm) {
        meetingRescheduleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const meetingId = document.getElementById('rescheduleMeetingId').value;
            const notificationId = document.getElementById('rescheduleNotificationId').value;
            const dateTime = rescheduleDatetimeInput.value;
            const comment = document.getElementById('rescheduleComment').value;
            
            if (!dateTime) {
                alert('Please select a date and time');
                return;
            }
            
            // Convert datetime-local format to MySQL format
            const mysqlDateTime = dateTime.replace('T', ' ') + ':00';
            
            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rescheduling...';
            
            // Determine correct path based on current page location
            let ajaxPath = '../ajax/meeting_handler.php';
            if (window.location.pathname.includes('/pages/')) {
                ajaxPath = '../ajax/meeting_handler.php';
            } else {
                ajaxPath = 'ajax/meeting_handler.php';
            }
            
            fetch(ajaxPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'schedule',
                    meeting_id: meetingId,
                    scheduled_date: mysqlDateTime,
                    schedule_comment: comment || 'Rescheduled from notification'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and reset form
                    document.getElementById('meetingRescheduleModal').style.display = 'none';
                    this.reset();
                    
                    // If NotificationsManager is available, update the notification
                    if (window.NotificationsManager && notificationId) {
                        window.NotificationsManager.updateNotificationAfterAction(notificationId, 'reschedule_meeting', data, dateTime);
                        setTimeout(() => {
                            window.NotificationsManager.loadNotifications();
                            window.NotificationsManager.updateUnreadCount();
                        }, 500);
                    }
                    
                    // Reload meetings if on meetings page
                    if (typeof loadMyHistoryMeetings === 'function') {
                        loadMyHistoryMeetings();
                    }
                    if (typeof loadScheduledMeetings === 'function') {
                        loadScheduledMeetings();
                    }
                    if (typeof loadHistoryMeetings === 'function') {
                        loadHistoryMeetings();
                    }
                } else {
                    alert(data.error || 'Failed to reschedule meeting');
                }
            })
            .catch(error => {
                console.error('Meeting reschedule error:', error);
                alert('An error occurred while rescheduling the meeting: ' + error.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // Global function to open reschedule modal (called from notifications.js)
    // Raise Ticket Modal Functions
    window.openRaiseTicketModal = function() {
        const modal = document.getElementById('raiseTicketModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeRaiseTicketModal = function() {
        const modal = document.getElementById('raiseTicketModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            // Reset form
            const form = document.getElementById('raiseTicketForm');
            if (form) {
                form.reset();
                document.getElementById('ticketFilePreviews').innerHTML = '';
                document.getElementById('ticketFilePreviews').style.display = 'none';
            }
        }
    };

    window.handleTicketFileSelect = function(event) {
        const maxSize = 50 * 1024 * 1024; // 50MB
        const files = Array.from(event.target.files);
        
        // Check file sizes
        for (let file of files) {
            if (file.size > maxSize) {
                alert(`File "${file.name}" exceeds 50MB limit. Please select a smaller file.`);
                event.target.value = ''; // Clear the input
                document.getElementById('ticketFilePreviews').style.display = 'none';
                document.getElementById('ticketFilePreviews').innerHTML = '';
                return;
            }
        }
        
        const previewsContainer = document.getElementById('ticketFilePreviews');
        
        if (files.length > 0) {
            previewsContainer.style.display = 'block';
            previewsContainer.innerHTML = '';
            
            files.forEach((file, index) => {
                const fileDiv = document.createElement('div');
                fileDiv.style.cssText = 'display: flex; align-items: center; justify-content: space-between; background: rgba(30, 41, 59, 0.5); border-radius: 0.5rem; padding: 0.75rem; margin-bottom: 0.5rem;';
                
                const fileInfo = document.createElement('div');
                fileInfo.style.cssText = 'display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 0;';
                
                const icon = document.createElement('i');
                if (file.type.startsWith('image/')) {
                    icon.className = 'fas fa-image';
                } else if (file.type.startsWith('video/')) {
                    icon.className = 'fas fa-video';
                } else if (file.type.startsWith('audio/')) {
                    icon.className = 'fas fa-music';
                } else {
                    icon.className = 'fas fa-file';
                }
                icon.style.cssText = 'color: rgba(255,255,255,0.5); font-size: 1rem; flex-shrink: 0;';
                
                const fileName = document.createElement('div');
                fileName.style.cssText = 'min-width: 0; flex: 1;';
                const nameP = document.createElement('p');
                nameP.style.cssText = 'color: #fff; margin: 0; font-size: 0.8125rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;';
                nameP.textContent = file.name;
                const sizeP = document.createElement('p');
                sizeP.style.cssText = 'color: rgba(255,255,255,0.5); margin: 0.25rem 0 0 0; font-size: 0.75rem;';
                sizeP.textContent = formatFileSize(file.size);
                
                fileName.appendChild(nameP);
                fileName.appendChild(sizeP);
                
                fileInfo.appendChild(icon);
                fileInfo.appendChild(fileName);
                
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.onclick = function() {
                    // Remove file from input
                    const dt = new DataTransfer();
                    const input = document.getElementById('ticketFileInput');
                    Array.from(input.files).forEach((f, i) => {
                        if (i !== index) dt.items.add(f);
                    });
                    input.files = dt.files;
                    fileDiv.remove();
                    if (input.files.length === 0) {
                        previewsContainer.style.display = 'none';
                    }
                };
                removeBtn.style.cssText = 'background: transparent; border: none; color: rgba(255,255,255,0.5); cursor: pointer; padding: 0.25rem; flex-shrink: 0;';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                
                fileDiv.appendChild(fileInfo);
                fileDiv.appendChild(removeBtn);
                previewsContainer.appendChild(fileDiv);
            });
        }
    };

    window.formatFileSize = function(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };

    window.handleRaiseTicketSubmit = async function(event) {
        event.preventDefault();
        
        const form = document.getElementById('raiseTicketForm');
        const title = document.getElementById('ticketTitle').value.trim();
        const description = document.getElementById('ticketDescription').value.trim();
        const files = document.getElementById('ticketFileInput').files;
        
        if (!title || !description) {
            alert('Please fill in all required fields.');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        try {
            // Prepare FormData for AJAX request
            const formData = new FormData();
            formData.append('action', 'create_item');
            formData.append('type', 'Ticket');
            formData.append('title', title);
            formData.append('description', description);
            
            // Handle file uploads
            if (files && files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    // Add file to FormData for server upload
                    formData.append('attachments[]', file);
                }
            }
            
            // Determine the correct path to the handler
            // More robust path detection
            let handlerPath;
            const currentPath = window.location.pathname;
            const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
            
            if (currentPath.includes('/pages/')) {
                // We're in pages directory, go up one level
                handlerPath = '../ajax/task_ticket_handler.php';
            } else if (currentPath.includes('/ajax/')) {
                // We're in ajax directory (shouldn't happen, but just in case)
                handlerPath = 'task_ticket_handler.php';
            } else if (currentPath.endsWith('/') || currentPath === '') {
                // We're at root
                handlerPath = 'ajax/task_ticket_handler.php';
            } else {
                // Default: try relative to current location
                handlerPath = 'ajax/task_ticket_handler.php';
            }
            
            console.log('Handler path:', handlerPath, 'Current path:', currentPath);
            
            // Submit to database via AJAX
            const response = await fetch(handlerPath, {
                method: 'POST',
                body: formData
            });
            
            // Check if response is OK
            if (!response.ok) {
                const text = await response.text();
                console.error('HTTP Error:', response.status, text);
                throw new Error('Server error: ' + response.status);
            }
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Response is not JSON:', text.substring(0, 500));
                throw new Error('Server returned invalid response');
            }
            
            const result = await response.json();
            
            if (result.success) {
            alert('Ticket raised successfully!');
            closeRaiseTicketModal();
            
                // Reset form
                form.reset();
                document.getElementById('ticketFilePreviews').innerHTML = '';
                document.getElementById('ticketFilePreviews').style.display = 'none';
                
                // Redirect to task_ticket.php page with cache-busting parameter
            let redirectPath = 'pages/task_ticket.php';
            if (window.location.pathname.includes('/pages/')) {
                redirectPath = 'task_ticket.php';
            } else if (window.location.pathname.includes('/')) {
                redirectPath = 'pages/task_ticket.php';
            }
            
                // Add timestamp to force fresh load and ensure new ticket is visible
                redirectPath += '?refresh=' + Date.now();
                
                console.log('Redirecting to:', redirectPath);
            window.location.href = redirectPath;
            } else {
                throw new Error(result.message || 'Failed to create ticket');
            }
        } catch (error) {
            console.error('Error saving ticket:', error);
            alert('Error saving ticket: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    };

    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('raiseTicketModal');
            if (modal && modal.style.display !== 'none') {
                closeRaiseTicketModal();
            }
        }
    });

    // Close modal when clicking outside
    document.getElementById('raiseTicketModal')?.addEventListener('click', function(e) {
        if (e.target.id === 'raiseTicketModal') {
            closeRaiseTicketModal();
        }
    });

    window.openMeetingRescheduleModal = function(meetingId, notificationId) {
        const modal = document.getElementById('meetingRescheduleModal');
        const meetingIdInput = document.getElementById('rescheduleMeetingId');
        const notificationIdInput = document.getElementById('rescheduleNotificationId');
        const currentScheduleInfo = document.getElementById('rescheduleCurrentScheduleInfo');
        const currentScheduleText = document.getElementById('rescheduleCurrentScheduleText');
        
        if (!modal || !meetingIdInput || !notificationIdInput) {
            console.error('Reschedule modal elements not found');
            return;
        }
        
        // Set meeting and notification IDs
        meetingIdInput.value = meetingId;
        notificationIdInput.value = notificationId || '';
        
        // Reset form
        document.getElementById('meetingRescheduleForm').reset();
        meetingIdInput.value = meetingId;
        notificationIdInput.value = notificationId || '';
        
        // Hide current schedule info initially
        if (currentScheduleInfo) {
            currentScheduleInfo.style.display = 'none';
        }
        
        // Fetch meeting details to pre-fill date/time
        let ajaxPath = '../ajax/meeting_handler.php';
        if (window.location.pathname.includes('/pages/')) {
            ajaxPath = '../ajax/meeting_handler.php';
        } else {
            ajaxPath = 'ajax/meeting_handler.php';
        }
        
        fetch(`${ajaxPath}?action=get_meeting_details&meeting_id=${meetingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.meeting) {
                    let dateToUse = null;
                    let timeToUse = '09:00';
                    
                    if (data.meeting.preferred_date) {
                        dateToUse = data.meeting.preferred_date;
                        if (data.meeting.preferred_time) {
                            timeToUse = data.meeting.preferred_time.substring(0, 5);
                        }
                    } else if (data.meeting.scheduled_date) {
                        const scheduledDateStr = data.meeting.scheduled_date;
                        const dateParts = scheduledDateStr.split(' ');
                        if (dateParts.length === 2) {
                            dateToUse = dateParts[0];
                            timeToUse = dateParts[1].substring(0, 5);
                        }
                    }
                    
                    if (dateToUse && rescheduleDatetimeInput) {
                        rescheduleDatetimeInput.value = dateToUse + 'T' + timeToUse;
                        if (rescheduleDatetimePlaceholder) {
                            rescheduleDatetimePlaceholder.style.display = 'none';
                        }
                    }
                    
                    // Show current schedule info if rescheduling
                    if (data.meeting.scheduled_date && currentScheduleInfo && currentScheduleText) {
                        const scheduledDateStr = data.meeting.scheduled_date;
                        const dateParts = scheduledDateStr.split(' ');
                        if (dateParts.length === 2) {
                            const datePart = dateParts[0];
                            const timePart = dateParts[1].substring(0, 5);
                            const dateFormatted = datePart.split('-').reverse().join('/');
                            currentScheduleText.textContent = dateFormatted + ' ' + timePart;
                            currentScheduleInfo.style.display = 'block';
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching meeting details:', error);
            });
        
        // Show modal and scroll to top
        modal.style.display = 'block';
        // Scroll to top of page to ensure modal is visible
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
})();
</script>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../assets/js/table-sorter.js?v=<?php echo filemtime('../assets/js/table-sorter.js'); ?>"></script>
<script src="../assets/js/script.js?v=<?php echo filemtime('../assets/js/script.js'); ?>"></script>
<script>
// Set current user type for notifications
window.currentUserType = '<?php echo htmlspecialchars($_SESSION['user_type'] ?? 'doer', ENT_QUOTES); ?>';

// CSRF Token Auto-Attach for jQuery AJAX
(function() {
    if (typeof $ !== 'undefined' && window.CSRF_TOKEN) {
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                if (settings.type && settings.type.toUpperCase() !== 'GET') {
                    xhr.setRequestHeader('X-CSRF-TOKEN', window.CSRF_TOKEN);

                    // Also append to data if it's a string (form-encoded)
                    if (typeof settings.data === 'string' && settings.data.indexOf('csrf_token=') === -1) {
                        settings.data += (settings.data ? '&' : '') + 'csrf_token=' + encodeURIComponent(window.CSRF_TOKEN);
                    }
                }
            }
        });
    }
})();
</script>
<script src="../assets/js/notifications.js?v=<?php echo filemtime('../assets/js/notifications.js'); ?>"></script>

<script>
// Global dropdown manager - ensures consistent behavior across all pages
window.HeaderDropdownManager = {
    init: function() {
        // Enhanced dropdown functionality with proper toggle behavior
        $('.form-dropdown, .profile-dropdown, .day-special-dropdown').off('click.headerDropdown').on('click.headerDropdown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $currentDropdown = $(this);
            const $currentMenu = $currentDropdown.find('.dropdown-menu');
            const isCurrentlyOpen = $currentMenu.hasClass('show');
            
            // Close all dropdowns first (including notifications menu)
            $('.dropdown-menu').removeClass('show');
            $('.form-dropdown, .profile-dropdown, .day-special-dropdown').removeClass('active');
            
            // Also close notifications menu if it's open
            if (window.NotificationsManager && window.NotificationsManager.isMenuOpen) {
                window.NotificationsManager.isMenuOpen = false;
            }
            
            // If the clicked dropdown was not open, open it
            // If it was open, it stays closed (toggle behavior)
            if (!isCurrentlyOpen) {
                $currentMenu.addClass('show');
                $currentDropdown.addClass('active');
                
                // Position profile dropdown to prevent right-side overflow
                if ($currentDropdown.hasClass('profile-dropdown')) {
                    window.HeaderDropdownManager.positionProfileDropdown($currentDropdown, $currentMenu);
                }
            }
        });
        
        // Close dropdowns when clicking outside
        $(document).off('click.headerDropdown').on('click.headerDropdown', function(e) {
            // Check if click is outside any dropdown
            if (!$(e.target).closest('.form-dropdown, .profile-dropdown, .day-special-dropdown, .dropdown-menu, .notifications-bell').length) {
                $('.dropdown-menu').removeClass('show');
                $('.form-dropdown, .profile-dropdown, .day-special-dropdown').removeClass('active');
                
                // Also close notifications menu if it's open
                if (window.NotificationsManager && window.NotificationsManager.isMenuOpen) {
                    window.NotificationsManager.isMenuOpen = false;
                }
            }
        });
        
        // Prevent dropdown from closing when clicking inside
        $('.dropdown-menu').off('click.headerDropdown').on('click.headerDropdown', function(e) {
            e.stopPropagation();
        });
    },
    
    reinit: function() {
        // Re-initialize dropdowns
        this.init();
    },
    
    positionProfileDropdown: function($dropdown, $menu) {
        // Get dropdown button position
        const buttonOffset = $dropdown.find('.btn').offset();
        const buttonWidth = $dropdown.find('.btn').outerWidth();
        const menuWidth = $menu.outerWidth() || 200; // fallback to min-width
        const viewportWidth = $(window).width();
        
        // Calculate right edge position
        const rightEdge = buttonOffset.left + buttonWidth;
        const spaceOnRight = viewportWidth - rightEdge;
        
        // If menu would overflow on right, adjust position
        if (spaceOnRight < menuWidth) {
            const overflow = menuWidth - spaceOnRight;
            const newRight = Math.max(10, spaceOnRight - 10);
            $menu.css({
                'right': newRight + 'px',
                'left': 'auto'
            });
        } else {
            // Reset to default positioning
            $menu.css({
                'right': '0',
                'left': 'auto'
            });
        }
    }
};

// Universal dropdown functionality - works on all pages
$(document).ready(function() {
    window.HeaderDropdownManager.init();
    window.SpecialEventsManager.init();
});

// Re-initialize dropdowns when navigating between pages (for SPA-like behavior)
$(window).on('load', function() {
    window.HeaderDropdownManager.reinit();
});

// Force re-initialization of dropdowns on any page change
$(document).on('DOMNodeInserted', function() {
    // Re-bind dropdown events when new content is added
    setTimeout(function() {
        window.HeaderDropdownManager.reinit();
    }, 100);
});

// Additional re-initialization for pages that might load content dynamically
$(document).on('ajaxComplete', function() {
    setTimeout(function() {
        window.HeaderDropdownManager.reinit();
    }, 50);
});

// Special Events Notification Manager
window.SpecialEventsManager = {
    init: function() {
        // Check if jQuery is available
        if (typeof $ === 'undefined') {
            console.warn('jQuery not available for SpecialEventsManager');
            return;
        }
        
        // Check if there are special events today
        const $daySpecialPill = $('#daySpecialPill');
        console.log('Day Special Pill found:', $daySpecialPill.length);
        console.log('Has special events class:', $daySpecialPill.hasClass('has-special-events'));
        
        if ($daySpecialPill.hasClass('has-special-events')) {
            console.log('Special events detected, showing notification');
            // Add a subtle notification effect after page load
            setTimeout(() => {
                this.showSpecialEventsNotification();
            }, 2000);
        } else {
            console.log('No special events today');
        }
    },
    
    showSpecialEventsNotification: function() {
        const $pill = $('#daySpecialPill');
        
        // Add a temporary "attention" class for extra emphasis
        $pill.addClass('special-events-attention');
        
        // Remove the attention class after 3 seconds
        setTimeout(() => {
            $pill.removeClass('special-events-attention');
        }, 3000);
        
        // Optional: Show a subtle tooltip or notification
        this.showSpecialEventsTooltip();
    },
    
    showSpecialEventsTooltip: function() {
        console.log('Creating special events tooltip...');
        // Create a temporary tooltip-like notification
        const $tooltip = $('<div class="special-events-notification">🎉 Some unread notifications today!</div>');
        $tooltip.css({
            position: 'absolute',
            top: '100%',
            right: '0',
            marginTop: '8px',
            background: 'linear-gradient(105deg,rgb(9, 63, 7),rgb(13, 135, 18))',
            color: 'white',
            padding: '8px 12px',
            borderRadius: '20px',
            fontSize: '12px',
            fontWeight: '600',
            boxShadow: '0 4px 15px rgba(132, 255, 107, 0.4)',
            zIndex: '9999',
            animation: 'special-events-notification-slide 0.5s ease-out',
            whiteSpace: 'nowrap'
        });
        
        // Add CSS animation for the notification
        if (!$('#special-events-notification-style').length) {
            $('head').append(`
                <style id="special-events-notification-style">
                    @keyframes special-events-notification-slide {
                        0% { transform: translateY(-20px); opacity: 0; }
                        100% { transform: translateY(0); opacity: 1; }
                    }
                    .special-events-attention {
                        animation: special-events-glow 1s ease-in-out infinite alternate !important;
                    }
                </style>
            `);
        }
        
        // Position relative to the pill
        const $pill = $('#daySpecialPill');
        console.log('Pill element found for tooltip:', $pill.length);
        console.log('Pill position:', $pill.offset());
        $pill.css('position', 'relative').append($tooltip);
        console.log('Tooltip appended to pill');
        
        // Remove the notification after 4 seconds
        setTimeout(() => {
            $tooltip.fadeOut(500, function() {
                $(this).remove();
            });
        }, 4000);
    }
};

// Ensure dropdown manager is available globally
if (typeof window.HeaderDropdownManager === 'undefined') {
    window.HeaderDropdownManager = {
        init: function() {
            // Enhanced dropdown functionality with proper toggle behavior
            $('.form-dropdown, .profile-dropdown, .day-special-dropdown').off('click.headerDropdown').on('click.headerDropdown', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $currentDropdown = $(this);
                const $currentMenu = $currentDropdown.find('.dropdown-menu');
                const isCurrentlyOpen = $currentMenu.hasClass('show');
                
                // Close all dropdowns first (including notifications menu)
                $('.dropdown-menu').removeClass('show');
                $('.form-dropdown, .profile-dropdown, .day-special-dropdown').removeClass('active');
                
                // Also close notifications menu if it's open
                if (window.NotificationsManager && window.NotificationsManager.isMenuOpen) {
                    window.NotificationsManager.isMenuOpen = false;
                }
                
                // If the clicked dropdown was not open, open it
                if (!isCurrentlyOpen) {
                    $currentMenu.addClass('show');
                    $currentDropdown.addClass('active');
                    
                    // Position profile dropdown to prevent right-side overflow
                    if ($currentDropdown.hasClass('profile-dropdown')) {
                        window.HeaderDropdownManager.positionProfileDropdown($currentDropdown, $currentMenu);
                    }
                }
            });
            
            // Close dropdowns when clicking outside
            $(document).off('click.headerDropdown').on('click.headerDropdown', function(e) {
                if (!$(e.target).closest('.form-dropdown, .profile-dropdown, .day-special-dropdown, .dropdown-menu, .notifications-bell').length) {
                    $('.dropdown-menu').removeClass('show');
                    $('.form-dropdown, .profile-dropdown, .day-special-dropdown').removeClass('active');
                    
                    // Also close notifications menu if it's open
                    if (window.NotificationsManager && window.NotificationsManager.isMenuOpen) {
                        window.NotificationsManager.isMenuOpen = false;
                    }
                }
            });
            
            // Prevent dropdown from closing when clicking inside
            $('.dropdown-menu').off('click.headerDropdown').on('click.headerDropdown', function(e) {
                e.stopPropagation();
            });
        },
        
        reinit: function() {
            this.init();
        },
        
        positionProfileDropdown: function($dropdown, $menu) {
            // Get dropdown button position
            const buttonOffset = $dropdown.find('.btn').offset();
            const buttonWidth = $dropdown.find('.btn').outerWidth();
            const menuWidth = $menu.outerWidth() || 200; // fallback to min-width
            const viewportWidth = $(window).width();
            
            // Calculate right edge position
            const rightEdge = buttonOffset.left + buttonWidth;
            const spaceOnRight = viewportWidth - rightEdge;
            
            // If menu would overflow on right, adjust position
            if (spaceOnRight < menuWidth) {
                const overflow = menuWidth - spaceOnRight;
                const newRight = Math.max(10, spaceOnRight - 10);
                $menu.css({
                    'right': newRight + 'px',
                    'left': 'auto'
                });
            } else {
                // Reset to default positioning
                $menu.css({
                    'right': '0',
                    'left': 'auto'
                });
            }
        }
    };
}
</script>


