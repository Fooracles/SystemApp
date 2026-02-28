<?php
// Sidebar component for the FMS application
if (!isLoggedIn()) {
    return;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <?php
            // Determine the appropriate dashboard URL based on user role
            $dashboard_url = '';
            if (isAdmin()) {
                $dashboard_url = 'admin_dashboard.php';
            } elseif (isManager()) {
                $dashboard_url = 'manager_dashboard.php';
            } elseif (isDoer()) {
                $dashboard_url = 'doer_dashboard.php';
            } elseif (isClient()) {
                $dashboard_url = 'client_dashboard.php';
            }
            ?>
            <a href="<?php echo $dashboard_url; ?>" class="logo-link" title="Go to Dashboard">
                <img
                    src="../assets/images/logo.png"
                    alt="FMS Logo"
                    class="logo-image"
                    data-logo-expanded="../assets/images/logo.png"
                    data-logo-collapsed="../assets/images/favicon-32x32.png"
                    onerror="this.style.display='none'"
                >
            </a>
        </div>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav">
        <!-- Dashboard Link -->
        <?php if (isAdmin()): ?>
            <a href="admin_dashboard.php" title="Admin Dashboard" class="nav-link <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="link-text">Dashboard</span>
            </a>
        <?php elseif (isManager()): ?>
            <a href="manager_dashboard.php" title="Manager Dashboard" class="nav-link <?php echo ($current_page == 'manager_dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="link-text">Dashboard</span>
            </a>
        <?php elseif (isDoer()): ?>
            <a href="doer_dashboard.php" title="Doer Dashboard" class="nav-link <?php echo ($current_page == 'doer_dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="link-text">Dashboard</span>
            </a>
        <?php elseif (isClient()): ?>
            <a href="client_dashboard.php" title="Client Dashboard" class="nav-link <?php echo ($current_page == 'client_dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="link-text">Dashboard</span>
            </a>
        <?php endif; ?>
        

        <?php if (isClient()): ?>
            <!-- Client-specific navigation -->
            <a href="task_ticket.php" title="Task & Ticket" class="nav-link <?php echo ($current_page == 'task_ticket.php') ? 'active' : ''; ?>">
                <i class="fas fa-ticket-alt"></i>
                <span class="link-text">Action Center</span>
            </a>

            <a href="report.php" title="Report" class="nav-link <?php echo ($current_page == 'report.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span class="link-text">Report</span>
            </a>

            <a href="updates.php" title="Updates" class="nav-link <?php echo ($current_page == 'updates.php') ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i>
                <span class="link-text">Updates</span>
            </a>
        <?php else: ?>
            <!-- Non-client navigation -->
        
        <!-- My Tasks Link (All Users) -->
        <a href="my_task.php" title="My Tasks" class="nav-link <?php echo ($current_page == 'my_task.php') ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i>
            <span class="link-text">My Tasks</span>
        </a>

        <!-- Manage Work Section (Admin and Manager only) -->
        <?php if (isAdmin() || isManager()): ?>
            <?php 
            // Check if any Manage Work page is active
            $manage_work_pages = ['manage_tasks.php', 'checklist_task.php', 'add_task.php', 'fms_task.php'];
            $is_manage_work_active = in_array($current_page, $manage_work_pages);
            ?>
            <div class="nav-submenu <?php echo $is_manage_work_active ? 'active' : ''; ?>">
                <a href="#" class="nav-link nav-submenu-toggle" title="Manage Work">
                    <i class="fas fa-briefcase"></i>
                    <span class="link-text">Task Panel</span>
                    <i class="fas fa-chevron-down nav-submenu-icon"></i>
                </a>
                <div class="nav-submenu-items">
                    <a href="manage_tasks.php" title="Manage Tasks" class="nav-link nav-submenu-link <?php echo ($current_page == 'manage_tasks.php') ? 'active' : ''; ?>">
                        <i class="fas fa-tasks"></i>
                        <span class="link-text">Manage Tasks</span>
                    </a>
                    <a href="checklist_task.php" title="Checklist Tasks" class="nav-link nav-submenu-link <?php echo ($current_page == 'checklist_task.php') ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-check"></i>
                        <span class="link-text">Checklist Tasks</span>
                    </a>
                    <a href="add_task.php" title="Delegation Tasks" class="nav-link nav-submenu-link <?php echo ($current_page == 'add_task.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i>
                        <span class="link-text">Delegation Tasks</span>
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="fms_task.php" title="FMS Tasks" class="nav-link nav-submenu-link <?php echo ($current_page == 'fms_task.php') ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt"></i>
                            <span class="link-text">FMS Tasks</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Client Portal Section (Admin and Manager only) -->
        <?php if (isAdmin() || isManager()): ?>
            <?php 
            // Check if any Client Portal page is active
            $client_portal_pages = ['task_ticket.php', 'updates.php', 'report.php'];
            // Also check if Manage Clients page is active (for Managers)
            if (isManager() && !isAdmin() && $current_page == 'manage_users.php' && isset($_GET['section']) && $_GET['section'] == 'clients') {
                $is_client_portal_active = true;
            } else {
                $is_client_portal_active = in_array($current_page, $client_portal_pages);
            }
            ?>
            <div class="nav-submenu <?php echo $is_client_portal_active ? 'active' : ''; ?>">
                <a href="#" class="nav-link nav-submenu-toggle" title="Client Portal">
                    <i class="fas fa-building"></i>
                    <span class="link-text">Client Portal</span>
                    <i class="fas fa-chevron-down nav-submenu-icon"></i>
                </a>
                <div class="nav-submenu-items">
                    <a href="task_ticket.php" title="Action Center" class="nav-link nav-submenu-link <?php echo ($current_page == 'task_ticket.php') ? 'active' : ''; ?>">
                        <i class="fas fa-ticket-alt"></i>
                        <span class="link-text">Action Center</span>
                    </a>
                    <a href="updates.php" title="Updates" class="nav-link nav-submenu-link <?php echo ($current_page == 'updates.php') ? 'active' : ''; ?>">
                        <i class="fas fa-bullhorn"></i>
                        <span class="link-text">Updates</span>
                    </a>
                    <a href="report.php" title="Reports" class="nav-link nav-submenu-link <?php echo ($current_page == 'report.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span class="link-text">Reports</span>
                    </a>
                    <?php if (isManager() && !isAdmin()): ?>
                        <a href="manage_users.php?section=clients" title="Manage Clients" class="nav-link nav-submenu-link <?php echo ($current_page == 'manage_users.php' && isset($_GET['section']) && $_GET['section'] == 'clients') ? 'active' : ''; ?>">
                            <i class="fas fa-user-tie"></i>
                            <span class="link-text">Manage Clients</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tools Section (All Users) -->
        <?php 
        // Check if any Tools page is active
        $tools_pages = ['my_notes.php', 'useful_urls.php'];
        $is_tools_active = in_array($current_page, $tools_pages);
        ?>
        <div class="nav-submenu <?php echo $is_tools_active ? 'active' : ''; ?>">
            <a href="#" class="nav-link nav-submenu-toggle" title="Tools">
                <i class="fas fa-tools"></i>
                <span class="link-text">Tools</span>
                <i class="fas fa-chevron-down nav-submenu-icon"></i>
            </a>
            <div class="nav-submenu-items">
                <a href="my_notes.php" title="My Notes" class="nav-link nav-submenu-link <?php echo ($current_page == 'my_notes.php') ? 'active' : ''; ?>">
                    <i class="fas fa-sticky-note"></i>
                    <span class="link-text">My Notes</span>
                </a>
                <a href="useful_urls.php" title="Useful URLs" class="nav-link nav-submenu-link <?php echo ($current_page == 'useful_urls.php') ? 'active' : ''; ?>">
                    <i class="fas fa-link"></i>
                    <span class="link-text">Useful URLs</span>
                </a>
            </div>
        </div>

        <!-- Records Section (Admin Only) -->
        <?php if (isAdmin()): ?>
            <?php 
            // Check if any Records page is active
            $records_pages = ['manage_users.php', 'manage_forms.php', 'manage_sheets.php', 'logged_in.php'];
            $is_records_active = in_array($current_page, $records_pages);
            ?>
            <div class="nav-submenu <?php echo $is_records_active ? 'active' : ''; ?>">
                <a href="#" class="nav-link nav-submenu-toggle" title="Records">
                    <i class="fas fa-database"></i>
                    <span class="link-text">Records</span>
                    <i class="fas fa-chevron-down nav-submenu-icon"></i>
                </a>
                <div class="nav-submenu-items">
                    <a href="manage_users.php" title="Manage Users" class="nav-link nav-submenu-link <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span class="link-text">Manage Users</span>
                    </a>
                    <a href="manage_forms.php" title="Manage Forms" class="nav-link nav-submenu-link <?php echo ($current_page == 'manage_forms.php') ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span class="link-text">Manage Forms</span>
                    </a>
                    <a href="manage_sheets.php" title="Manage Sheets" class="nav-link nav-submenu-link <?php echo ($current_page == 'manage_sheets.php') ? 'active' : ''; ?>">
                        <i class="fas fa-table"></i>
                        <span class="link-text">Manage Sheets</span>
                    </a>
                    <a href="logged_in.php" title="Logged-In Users" class="nav-link nav-submenu-link <?php echo ($current_page == 'logged_in.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-check"></i>
                        <span class="link-text">Logged-In</span>
                    </a>
                </div>
            </div>

            <!-- FMS Builder (Admin Only) -->
            <a href="fms_builder.php" title="FMS Builder" class="nav-link <?php echo ($current_page == 'fms_builder.php') ? 'active' : ''; ?>">
                <i class="fas fa-project-diagram"></i>
                <span class="link-text">FMS Builder</span>
            </a>
        <?php endif; ?>

        <!-- My Meetings Link (All Users) -->
        <a href="admin_my_meetings.php" title="My Meetings" class="nav-link <?php echo ($current_page == 'admin_my_meetings.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span class="link-text">My Meetings</span>
        </a>


         <!-- Leave Request Link -->
         <?php if (defined('ENABLE_LEAVE_REQUESTS_PAGE') && ENABLE_LEAVE_REQUESTS_PAGE): ?>
            <a href="leave_request.php" title="Leave Requests" class="nav-link <?php echo ($current_page == 'leave_request.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span class="link-text"><?php echo (isDoer()) ? 'My Leaves' : 'Leave Requests'; ?></span>
            </a>
        <?php endif; ?>

        <!-- Holiday List Link (All Users) -->
        <a href="holiday_list.php" title="Holiday List" class="nav-link <?php echo ($current_page == 'holiday_list.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-day"></i>
            <span class="link-text">Holiday List</span>
        </a>
        <?php endif; ?>

    </nav>
</div>
