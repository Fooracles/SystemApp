// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('FMS Script loaded successfully');
    
    // Cache frequently used DOM elements
    const DOM_CACHE = {
        body: document.body,
        sidebar: document.getElementById('sidebar'),
        toggleButton: document.getElementById('sidebarToggleBtn'),
        logoImage: document.querySelector('#sidebar .logo-image')
    };
    
    // Initialize tooltips if Bootstrap is available (only if not already initialized)
    if(typeof($.fn.tooltip) !== 'undefined' && !window.tooltipsInitialized) {
        window.tooltipsInitialized = true;
        $('[data-toggle="tooltip"]').tooltip();
    }

    // Sidebar collapse / expand toggle handling
    (function() {
        const { body, toggleButton, logoImage } = DOM_CACHE;
        const SIDEBAR_COLLAPSED_KEY = 'fmsSidebarCollapsed';

        console.log('Sidebar elements found:', {
            toggleButton: !!toggleButton,
            logoImage: !!logoImage,
            twoFrameLayout: body.classList.contains('two-frame-layout')
        });

        if (!toggleButton || !logoImage || !body.classList.contains('two-frame-layout')) {
            console.warn('Sidebar initialization skipped - missing elements or wrong layout');
            return;
        }

        const expandedLogo = logoImage.dataset.logoExpanded || logoImage.getAttribute('data-logo-expanded') || logoImage.src;
        const collapsedLogo = logoImage.dataset.logoCollapsed || logoImage.getAttribute('data-logo-collapsed') || expandedLogo;

        const setLogo = (useCollapsed) => {
            logoImage.src = useCollapsed ? collapsedLogo : expandedLogo;
        };

        const applySidebarState = (collapsed) => {
            body.classList.toggle('sidebar-collapsed', collapsed);
            setLogo(collapsed);
            try {
                localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? 'true' : 'false');
            } catch (error) {
                console.warn('Unable to persist sidebar state:', error);
            }
        };

        // Restore previous state for large screens only
        try {
            const storedState = localStorage.getItem(SIDEBAR_COLLAPSED_KEY);
            if (window.innerWidth >= 992 && storedState === 'true') {
                applySidebarState(true);
            }
        } catch (error) {
            console.warn('Unable to read sidebar state:', error);
        }

        toggleButton.addEventListener('click', function() {
            console.log('Sidebar toggle clicked');
            const shouldCollapse = !body.classList.contains('sidebar-collapsed');
            console.log('Should collapse:', shouldCollapse);
            applySidebarState(shouldCollapse);
        });

        // Ensure correct logo when resizing between breakpoints
        window.addEventListener('resize', function() {
            if (window.innerWidth < 992 && body.classList.contains('sidebar-collapsed')) {
                setLogo(false);
            } else if (window.innerWidth >= 992) {
                const collapsed = body.classList.contains('sidebar-collapsed');
                setLogo(collapsed);
            }
        });
    })();

    // Sidebar Submenu Toggle Functionality
    (function() {
        const submenuToggles = document.querySelectorAll('.nav-submenu-toggle');
        
        submenuToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const submenu = this.closest('.nav-submenu');
                const submenuItems = submenu.querySelector('.nav-submenu-items');
                
                if (!submenu || !submenuItems) return;
                
                const isActive = submenu.classList.contains('active');
                
                if (isActive) {
                    submenu.classList.remove('active');
                } else {
                    submenu.classList.add('active');
                }
            });
        });
    })();

    // Ensure dropdowns are working (only if not already initialized)
    // Exclude header dropdowns as they are handled by HeaderDropdownManager
    if(typeof($.fn.dropdown) !== 'undefined' && !window.dropdownsInitialized) {
        window.dropdownsInitialized = true;
        // Only initialize Bootstrap dropdowns that are NOT in the header
        $('.dropdown-toggle').not('.form-dropdown .btn, .profile-dropdown .btn, .day-special-dropdown .day-special-pill').dropdown();
    }

    // Task completion function
    window.markTaskComplete = function(taskId) {
        if(confirm("Are you sure you want to mark this task as completed?")) {
            document.getElementById('complete-task-' + taskId).submit();
        }
    };

    // Initialize tab functionality for login/registration page
    const loginTabs = document.querySelectorAll('.login-tabs .nav-link');
    const loginTabContents = document.querySelectorAll('.tab-pane');

    if(loginTabs.length > 0) {
        loginTabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all tabs and contents
                loginTabs.forEach(t => t.classList.remove('active'));
                loginTabContents.forEach(c => c.classList.remove('show', 'active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Get target content and activate it
                const target = this.getAttribute('href');
                document.querySelector(target).classList.add('show', 'active');
            });
        });
    }

    // Task filter functionality
    const taskFilterSelect = document.getElementById('task-filter');
    if(taskFilterSelect) {
        taskFilterSelect.addEventListener('change', function() {
            const filterValue = this.value;
            const taskRows = document.querySelectorAll('.task-row');
            
            if(filterValue === 'all') {
                taskRows.forEach(row => row.style.display = '');
            } else {
                taskRows.forEach(row => {
                    const status = row.getAttribute('data-status');
                    row.style.display = (status === filterValue) ? '' : 'none';
                });
            }
        });
    }

    // Confirmation dialogs for important actions
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const confirmMessage = this.getAttribute('data-confirm');
            if(!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    if(forms.length > 0) {
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }

    // Dropdown for selecting doers based on department
    // Only apply this logic if NOT on add_task.php page
    if (!window.location.pathname.includes('add_task.php')) {
        const departmentSelect = document.getElementById('department_id');
        const doerSelect = document.getElementById('doer_id');
        
        if(departmentSelect && doerSelect) {
            departmentSelect.addEventListener('change', function() {
                const departmentId = this.value;
                
                // Clear current options except the first one
                while (doerSelect.options.length > 1) {
                    doerSelect.remove(1);
                }
                
                if (departmentId !== '') {
                    // Fetch doers for selected department via AJAX
                    fetch(`../ajax/get_doers.php?department_id=${departmentId}`)
                        .then(response => response.json())
                        .then(data => {
                            // Add new options based on response
                            if (data.length > 0) {
                                data.forEach(doer => {
                                    const option = document.createElement('option');
                                    option.value = doer.id;
                                    // Prefer username in UI; fallback to name if missing
                                    option.textContent = doer.username || doer.name;
                                    doerSelect.appendChild(option);
                                });
                            } else {
                                const option = document.createElement('option');
                                option.value = '';
                                option.textContent = 'No doers available in this department';
                                doerSelect.appendChild(option);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching doers:', error);
                        });
                }
            });
        }
    }

    // Task completion confirmation
    const completeTaskButtons = document.querySelectorAll('.complete-task-btn');
    if(completeTaskButtons.length > 0) {
        completeTaskButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if(!confirm('Are you sure you want to mark this task as completed?')) {
                    e.preventDefault();
                }
            });
        });
    }

    // Date picker initialization if flatpickr is available
    if(typeof flatpickr !== 'undefined') {
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d",
            minDate: "today"
        });
        
        flatpickr(".timepicker", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true
        });
    }

    // Fix for Bootstrap datepicker calendar positioning
    // Ensure calendar stays fixed when scrolling
    $(document).on('show.bs.modal', function() {
        // Hide any open datepickers when modal opens (only if datepicker is available)
        if(typeof($.fn.datepicker) !== 'undefined') {
            $('.datepicker').datepicker('hide');
        }
    });

    // Fix datepicker positioning when shown (only if datepicker is available)
    if(typeof($.fn.datepicker) !== 'undefined') {
        $(document).on('show', '.datepicker', function() {
            var $datepicker = $(this);
            // Force fixed positioning
            $datepicker.css({
                'position': 'fixed',
                'z-index': '10000'
            });
        });
    }

    // Additional fix for datepicker dropdown positioning (only if datepicker is available)
    if(typeof($.fn.datepicker) !== 'undefined') {
        $(document).on('show', '.datepicker-dropdown', function() {
            var $dropdown = $(this);
            $dropdown.css({
                'position': 'fixed',
                'z-index': '10000'
            });
        });
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-persistent)');
    if(alerts.length > 0) {
        alerts.forEach(alert => {
            setTimeout(function() {
                alert.classList.add('fade');
                setTimeout(function() {
                    alert.remove();
                }, 500);
            }, 5000);
        });
    }
    
    // Auto refresh delay information on dashboard pages every 30 seconds
    if (
        window.location.href.includes('doer_dashboard.php') || 
        window.location.href.includes('manager_dashboard.php') || 
        window.location.href.includes('admin_dashboard.php')
    ) {
        // Create an AJAX request to update delay status
        function updateDelayStatus() {
            // Create hidden form for AJAX
            if (!document.getElementById('delay-update-form')) {
                const form = document.createElement('form');
                form.id = 'delay-update-form';
                form.style.display = 'none';
                document.body.appendChild(form);
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '../ajax/update_delays.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // Update delayed tasks in the UI
                                response.delayed_tasks.forEach(task => {
                                    const taskRow = document.querySelector(`tr[data-task-id="${task.id}"]`);
                                    if (taskRow) {
                                        // Add delay class
                                        if (task.is_delayed == 1 && task.status == 'pending') {
                                            taskRow.classList.add('table-danger');
                                            // Update delay duration in the correct column
                                            const delayCell = taskRow.querySelector('td:nth-child(6)');
                                            if (delayCell) {
                                                delayCell.innerHTML = `<span class="text-danger">${task.delay_duration}</span>`;
                                            }
                                        }
                                    }
                                });
                            }
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                        }
                    }
                };
                xhr.send('update_delays=1');
            }
        }
        
        // Update immediately and then every 30 seconds
        updateDelayStatus();
        setInterval(updateDelayStatus, 30000); // 30 seconds
        
        // Still refresh the page every 5 minutes to ensure all data is synchronized
        setInterval(function() {
            // Store scroll position
            const scrollPos = window.scrollY;
            
            // Reload the page
            window.location.href = window.location.href.split('#')[0];
            
            // After reload, restore scroll position
            window.onload = function() {
                window.scrollTo(0, scrollPos);
            };
        }, 5 * 60 * 1000); // 5 minutes
    }

    // Handle dropdown form submissions
    const dropdownForms = document.querySelectorAll('.dropdown-item-form');
    dropdownForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Get the dropdown button that triggered this form
            const dropdownButton = this.closest('.dropdown').querySelector('.dropdown-toggle');
            // Close the dropdown
            const dropdownMenu = this.closest('.dropdown-menu');
            dropdownMenu.classList.remove('show');
            dropdownButton.setAttribute('aria-expanded', 'false');
        });
    });
}); 

// Fix for dropdown text truncation - ensure selected option text is fully visible
$(document).ready(function() {
    // Apply fix on page load
    fixDropdownTextTruncation();
    
    // Apply fix whenever a dropdown value changes
    $(document).on('change', '.task-status-dropdown, .all-tasks-status-dropdown, .action-select', function() {
        fixDropdownTextTruncation();
    });
    
    function fixDropdownTextTruncation() {
        $('.task-status-dropdown, .all-tasks-status-dropdown, .action-select').each(function() {
            // Remove any width restrictions that might cause truncation
            $(this).css({
                'width': 'auto',
                'min-width': '140px',
                'max-width': 'none',
                'text-overflow': 'unset',
                'overflow': 'visible',
                'white-space': 'nowrap'
            });
        });
    }
}); 

// Function to highlight delayed task rows based on "Delayed" column content
function highlightDelayedTasks() {
    // Target all tables that might contain delayed tasks
    const tables = [
        'table', // General table selector
        '.table', // Bootstrap table class
        '#manage-tasks-table', // Specific table IDs if they exist
        '#delegation-tasks-table',
        '#checklist-tasks-table',
        '#doer-tasks-table'
    ];
    
    tables.forEach(tableSelector => {
        $(tableSelector).each(function() {
            const $table = $(this);
            
            // Find the "Delayed" column index
            let delayedColumnIndex = -1;
            $table.find('thead th').each(function(index) {
                const headerText = $(this).text().trim().toLowerCase();
                if (headerText === 'delayed') {
                    delayedColumnIndex = index;
                    return false; // Break the loop
                }
            });
            
            if (delayedColumnIndex === -1) {
                return; // Skip this table if no "Delayed" column found
            }
            
            // Check each row in the table body
            $table.find('tbody tr').each(function() {
                const $row = $(this);
                const $delayedCell = $row.find('td').eq(delayedColumnIndex);
                
                if ($delayedCell.length > 0) {
                    const delayedText = $delayedCell.text().trim();
                    
                    // Check if the delayed text indicates a delay (not blank/null/N/A/On Time/00:00:00)
                    const isDelayed = delayedText !== 'N/A' && 
                                    delayedText !== 'On Time' && 
                                    delayedText !== '' &&
                                    delayedText !== 'null' &&
                                    delayedText !== 'NULL' &&
                                    delayedText !== 'Null' &&
                                    delayedText !== '00:00:00' &&
                                    delayedText.length > 0 &&
                                    (delayedText.includes('mins') || 
                                     delayedText.includes('hrs') || 
                                     delayedText.includes('days') ||
                                     delayedText.includes('secs') ||
                                     /\d/.test(delayedText)); // Contains any number
                    
                    if (isDelayed) {
                        $row.addClass('delayed-task-row');
                    } else {
                        $row.removeClass('delayed-task-row');
                    }
                }
            });
        });
    });
}

// Function to highlight delayed tasks in specific table by ID
function highlightDelayedTasksInTable(tableId) {
    const $table = $('#' + tableId);
    if ($table.length === 0) return;
    
    // Find the "Delayed" column index
    let delayedColumnIndex = -1;
    $table.find('thead th').each(function(index) {
        const headerText = $(this).text().trim().toLowerCase();
        if (headerText === 'delayed') {
            delayedColumnIndex = index;
            return false;
        }
    });
    
    if (delayedColumnIndex === -1) return;
    
    // Check each row
    $table.find('tbody tr').each(function() {
        const $row = $(this);
        const $delayedCell = $row.find('td').eq(delayedColumnIndex);
        
        if ($delayedCell.length > 0) {
            const delayedText = $delayedCell.text().trim();
            
            const isDelayed = delayedText !== 'N/A' && 
                            delayedText !== 'On Time' && 
                            delayedText !== '' &&
                            delayedText !== 'null' &&
                            delayedText !== 'NULL' &&
                            delayedText !== 'Null' &&
                            delayedText !== '00:00:00' &&
                            delayedText.length > 0 &&
                            (delayedText.includes('mins') || 
                             delayedText.includes('hrs') || 
                             delayedText.includes('days') ||
                             delayedText.includes('secs') ||
                             /\d/.test(delayedText));
            
            if (isDelayed) {
                $row.addClass('delayed-task-row');
            } else {
                $row.removeClass('delayed-task-row');
            }
        }
    });
}

// Initialize delayed task highlighting when document is ready
$(document).ready(function() {
    // Initial highlighting
    highlightDelayedTasks();
    
    // Re-highlight after any AJAX content updates
    $(document).on('ajaxComplete', function() {
        setTimeout(highlightDelayedTasks, 100);
    });
    
    // Re-highlight after any dynamic content changes
    $(document).on('DOMNodeInserted', 'table', function() {
        setTimeout(highlightDelayedTasks, 100);
    });
    
    // Re-highlight when tables are updated via JavaScript
    $(document).on('tableUpdated', function() {
        highlightDelayedTasks();
    });
    
    // Manual trigger for immediate highlighting
    setTimeout(highlightDelayedTasks, 500);
});

// Global function that can be called from PHP or other scripts
window.highlightDelayedTasks = highlightDelayedTasks;
window.highlightDelayedTasksInTable = highlightDelayedTasksInTable;

// Table Sorting Functionality
// Note: Primary sorting is handled by table-sorter.js
// This function is kept for backward compatibility but defers to table-sorter.js
function initializeTableSorting() {
    // Check if table-sorter.js is available and has initialized
    if (typeof window.initTableSorting === 'function') {
        // table-sorter.js handles sorting, just update icons
        if (typeof window.updateTableSortIcons === 'function') {
            window.updateTableSortIcons();
        }
        return;
    }
    
    // Fallback for pages without table-sorter.js (shouldn't happen, but kept for safety)
    $('.sortable-header').off('click.legacySort').on('click.legacySort', function(e) {
        // Check if event was already handled
        if (e.isDefaultPrevented()) {
            return;
        }
        
        const $header = $(this);
        const $table = $header.closest('table');
        const column = $header.data('column');
        
        if (!column) return;
        
        // Remove sort classes from all headers
        $table.find('.sortable-header').removeClass('sort-asc sort-desc');
        
        // Determine sort order
        let sortOrder = 'asc';
        if ($header.hasClass('sort-asc')) {
            sortOrder = 'desc';
        }
        
        // Add sort class to current header
        $header.addClass('sort-' + sortOrder);
        
        // Get current URL and parameters
        const url = new URL(window.location);
        url.searchParams.set('sort_column', column);
        url.searchParams.set('sort_order', sortOrder);
        
        // Navigate to sorted URL
        window.location.href = url.toString();
    });
}

// Initialize sorting when document is ready
$(document).ready(function() {
    initializeTableSorting();
    
    // Highlight current sort column
    const urlParams = new URLSearchParams(window.location.search);
    const currentSortColumn = urlParams.get('sort_column');
    const currentSortOrder = urlParams.get('sort_order');
    
    if (currentSortColumn && currentSortOrder) {
        $(`.sortable-header[data-column="${currentSortColumn}"]`).addClass(`sort-${currentSortOrder}`);
    }
}); 

// Status Column System - Integrated Functions
// Status icon helper function - Exact Implementation
function getStatusIcon(status) {
    const status_val = (status || "pending").toLowerCase();
    const status_text = getStatusDisplayText(status_val);
    
    const icon_map = {
        "pending": "⏳",
        "completed": "✅",
        "not_done": "❌",
        "not done": "❌",
        "cant_be_done": "⛔",
        "can not be done": "⛔",
        "shifted": "🔁",
        "priority": "⭐"
    };
    
    const icon = icon_map[status_val] || "⏳";
    const css_class = "status-" + status_val.replace(/[_\s]/g, "-");
    
    return `<span class="status-icon ${css_class}" title="${status_text}">${icon}</span>`;
}

function getStatusDisplayText(status) {
    const status_val = (status || "pending").toLowerCase();
    let status_text = status_val.charAt(0).toUpperCase() + status_val.slice(1).replace(/_/g, " ");
    
    if (status_val === "not_done" || status_val === "not done") status_text = "Not Done";
    if (status_val === "cant_be_done" || status_val === "can not be done") status_text = "Can't be done";
    
    return status_text;
}

// Function to update status column in table row
function updateStatusColumn(row, newStatus) {
    const statusCell = row.find("td.status-column");
    if (statusCell.length) {
        const newStatusIcon = getStatusIcon(newStatus);
        statusCell.html(newStatusIcon);
    }
}

// Function to initialize status columns on page load
function initializeStatusColumns() {
    $(".status-column").each(function() {
        const $this = $(this);
        const currentStatus = $this.data("status") || $this.text().trim();
        
        // Only update if it doesn't already have the proper HTML structure
        if (!$this.find(".status-icon").length) {
            const statusIcon = getStatusIcon(currentStatus);
            $this.html(statusIcon);
        }
    });
}

// Initialize status columns when document is ready
$(document).ready(function() {
    initializeStatusColumns();
    
    // Re-initialize after any AJAX content updates
    $(document).on('ajaxComplete', function() {
        setTimeout(initializeStatusColumns, 100);
    });
    
    // Re-initialize after any dynamic content changes
    $(document).on('DOMNodeInserted', 'table', function() {
        setTimeout(initializeStatusColumns, 100);
    });
    
    // Re-initialize when tables are updated via JavaScript
    $(document).on('tableUpdated', function() {
        initializeStatusColumns();
    });
});

// Make functions globally available
window.getStatusIcon = getStatusIcon;
window.getStatusDisplayText = getStatusDisplayText;
window.updateStatusColumn = updateStatusColumn;
window.initializeStatusColumns = initializeStatusColumns;

// Notification System
$(document).ready(function() {
    // Initialize notification system
    initNotificationSystem();
    
    // ... existing ready function code ...
});

function initNotificationSystem() {
    const notificationBell = $('#notification-bell');
    const notificationPanel = $('#notification-panel');
    const notificationContent = $('#notification-content');
    const closeNotifications = $('#close-notifications');
    
    // Toggle notification panel
    notificationBell.on('click', function(e) {
        e.stopPropagation();
        if (notificationPanel.is(':visible')) {
            hideNotificationPanel();
        } else {
            showNotificationPanel();
            loadNotifications();
        }
    });
    
    // Close notification panel
    closeNotifications.on('click', function() {
        hideNotificationPanel();
    });
    
    // Close panel when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.notification-bell').length) {
            hideNotificationPanel();
        }
    });
    
    // Load notifications from server
    function loadNotifications() {
        $.ajax({
            url: 'ajax/get_notifications.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    displayNotifications(response.notifications);
                    updateNotificationCount(response.count);
                } else {
                    console.error('Error loading notifications:', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading notifications:', error);
            }
        });
    }
    
    // Display notifications in the panel
    function displayNotifications(notifications) {
        if (!notifications || notifications.length === 0) {
            notificationContent.html(`
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No pending password reset requests</p>
                </div>
            `);
            return;
        }
        
        let html = '';
        notifications.forEach(function(notification) {
            html += `
                <div class="notification-item" data-id="${notification.id}">
                    <div class="user-info">
                        <span class="username">${notification.username}</span>
                        <span class="timestamp">${formatTimestamp(notification.requested_at)}</span>
                    </div>
                    <div class="email">${notification.email}</div>
                    ${notification.status === 'pending' ? `
                        <div class="actions">
                            <button type="button" class="btn btn-approve btn-sm approve-request" data-id="${notification.id}">
                                Approve
                            </button>
                            <button type="button" class="btn btn-reject btn-sm reject-request" data-id="${notification.id}">
                                Reject
                            </button>
                        </div>
                    ` : `
                        <div class="status-${notification.status}">
                            Status: ${notification.status.charAt(0).toUpperCase() + notification.status.slice(1)}
                        </div>
                        ${notification.reset_code ? `
                            <div class="reset-code">Reset Code: ${notification.reset_code}</div>
                        ` : ''}
                    `}
                </div>
            `;
        });
        
        notificationContent.html(html);
        
        // Bind action buttons
        bindNotificationActions();
    }
    
    // Bind notification action buttons
    function bindNotificationActions() {
        // Approve request
        $('.approve-request').on('click', function() {
            const requestId = $(this).data('id');
            approvePasswordReset(requestId);
        });
        
        // Reject request
        $('.reject-request').on('click', function() {
            const requestId = $(this).data('id');
            rejectPasswordReset(requestId);
        });
    }
    
    // Approve password reset request
    function approvePasswordReset(requestId) {
        $.ajax({
            url: 'ajax/approve_password_reset.php',
            type: 'POST',
            data: { request_id: requestId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Play notification sound
                    playNotificationSound();
                    
                    // Show success message
                    showToast('Password reset request approved! Reset code: ' + response.reset_code, 'success');
                    
                    // Reload notifications
                    loadNotifications();
                    
                    // Update notification count
                    updateNotificationCount(response.count);
                } else {
                    showToast('Error: ' + response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error approving request:', error);
                showToast('Error approving request. Please try again.', 'danger');
            }
        });
    }
    
    // Reject password reset request
    function rejectPasswordReset(requestId) {
        if (!confirm('Are you sure you want to reject this password reset request?')) {
            return;
        }
        
        $.ajax({
            url: 'ajax/reject_password_reset.php',
            type: 'POST',
            data: { request_id: requestId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    showToast('Password reset request rejected.', 'warning');
                    
                    // Reload notifications
                    loadNotifications();
                    
                    // Update notification count
                    updateNotificationCount(response.count);
                } else {
                    showToast('Error: ' + response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error rejecting request:', error);
                showToast('Error rejecting request. Please try again.', 'danger');
            }
        });
    }
    
    // Update notification count
    function updateNotificationCount(count) {
        const countElement = $('#notification-count');
        if (count > 0) {
            countElement.text(count).show();
        } else {
            countElement.hide();
        }
    }
    
    // Show notification panel
    function showNotificationPanel() {
        notificationPanel.fadeIn(200);
    }
    
    // Hide notification panel
    function hideNotificationPanel() {
        notificationPanel.fadeOut(200);
    }
    
    // Format timestamp
    function formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return diffMins + 'm ago';
        if (diffHours < 24) return diffHours + 'h ago';
        if (diffDays < 7) return diffDays + 'd ago';
        
        return date.toLocaleDateString();
    }
    
    // Play notification sound
    function playNotificationSound() {
        const audio = document.getElementById('notification-sound');
        if (audio) {
            audio.currentTime = 0;
            audio.play().catch(function(error) {
                console.log('Audio play failed:', error);
            });
        }
    }
    
    // Show toast message
    function showToast(message, type) {
        // You can implement your own toast system here
        // For now, using Bootstrap alert
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        
        // Insert at the top of the page
        $('body').prepend(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Check for new notifications periodically (every 30 seconds)
    setInterval(function() {
        if (notificationPanel.is(':visible')) {
            loadNotifications();
        }
    }, 30000);
} 