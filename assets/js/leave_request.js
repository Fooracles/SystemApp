/**
 * Leave Request Module JavaScript
 * Handles UI interactions and AJAX calls for leave request management
 */

class LeaveRequestManager {
    constructor() {
        this.userRole = window.LEAVE.role;
        this.userName = window.LEAVE.name;
        this.userDisplayName = window.LEAVE.displayName || this.userName;
        this.userId = window.LEAVE.id || null;
        this.userEmail = window.LEAVE.email;
        this.currentAction = null;
        this.currentServiceNo = null;
        this.currentNotificationId = null; // Store notification ID when action triggered from notification
        this.currentTotalPage = 1; // Track current page for Total Leaves table
        // Pagination is now handled server-side in PHP like checklist_task.php
        
        this.init();
    }

    init() {
        console.log('LeaveRequestManager initializing...');
        console.log('User role:', this.userRole);
        console.log('User name:', this.userName);
        this.bindEvents();
        this.initDatePickers();
        this.initFiltersFromURL(); // Initialize all filters from URL parameters
        this.loadData();
        this.loadMetrics();
        this.initSelect2();
        this.initTabs();
        this.loadLastRefreshTimestamps();
    }

    initFiltersFromURL() {
        // Get all filter values from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        
        // Initialize date filters
        const startDateParam = urlParams.get('filter_start_date') || urlParams.get('start_date');
        const endDateParam = urlParams.get('filter_end_date') || urlParams.get('end_date');
        
        const startDateFilter = document.getElementById('startDateFilter');
        const endDateFilter = document.getElementById('endDateFilter');
        
        if (startDateParam && startDateFilter) {
            // Convert to YYYY-MM-DD format for native date input
            const dateValue = this.convertToDateInputFormat(startDateParam);
            if (dateValue) {
                startDateFilter.value = dateValue;
            }
        }
        
        if (endDateParam && endDateFilter) {
            // Convert to YYYY-MM-DD format for native date input
            const dateValue = this.convertToDateInputFormat(endDateParam);
            if (dateValue) {
                endDateFilter.value = dateValue;
            }
        }
        
        // Initialize other filters
        const statusFilter = document.getElementById('statusFilter');
        const doerFilter = document.getElementById('doerFilter');
        const leaveTypeFilter = document.getElementById('leaveTypeFilter');
        const durationFilter = document.getElementById('durationFilter');
        
        const statusParam = urlParams.get('filter_status');
        const employeeParam = urlParams.get('filter_employee') || urlParams.get('filter_name');
        const leaveTypeParam = urlParams.get('filter_leave_type');
        const durationParam = urlParams.get('filter_duration');
        
        if (statusParam && statusFilter) {
            statusFilter.value = statusParam;
        }
        
        if (leaveTypeParam && leaveTypeFilter) {
            leaveTypeFilter.value = leaveTypeParam;
        }
        
        if (durationParam && durationFilter) {
            durationFilter.value = durationParam;
        }
        
        // For doer filter (Select2), we need to wait for options to load
        // This will be handled after loadDoerOptions completes
        if (employeeParam && doerFilter) {
            // Store the value to set after Select2 is initialized
            this.pendingDoerFilterValue = employeeParam;
        }
    }
    
    initDateFiltersFromURL() {
        // Legacy function name - redirects to initFiltersFromURL
        this.initFiltersFromURL();
    }
    
    convertToDateInputFormat(dateString) {
        if (!dateString || dateString.trim() === '') return '';
        
        // If already in YYYY-MM-DD format, return as is
        if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
            return dateString;
        }
        
        // If in DD/MM/YYYY format, convert to YYYY-MM-DD
        const dateRegex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
        const match = dateString.match(dateRegex);
        if (match) {
            const day = match[1];
            const month = match[2];
            const year = match[3];
            return `${year}-${month}-${day}`;
        }
        
        // Try to parse as Date object
        try {
            const date = new Date(dateString);
            if (!isNaN(date.getTime())) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
        } catch (e) {
            console.warn('Date parsing error:', e);
        }
        
        return '';
    }

    bindEvents() {
        // Apply filters button
        const applyFiltersBtn = document.getElementById('applyFilters');
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.applyFilters();
                this.loadMetrics();
            });
        }

        // Form submission
        const filterForm = document.querySelector('.filter-form');
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.applyFilters();
                this.loadMetrics();
            });
        }

        // Filter change events
        const filters = ['doerFilter', 'statusFilter', 'leaveTypeFilter', 'durationFilter', 'startDateFilter', 'endDateFilter'];
        filters.forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => {
                    // Reset to page 1 when filter changes
                    this.currentTotalPage = 1;
                    this.applyFilters();
                    this.loadMetrics();
                });
            }
        });

        // Clear filters button
        const clearFiltersBtn = document.getElementById('clearFilters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.clearFilters();
            });
        }

        // Filter toggle button for total leave requests
        const totalToggleFiltersBtn = document.getElementById('totalToggleFilters');
        if (totalToggleFiltersBtn) {
            totalToggleFiltersBtn.addEventListener('click', () => this.toggleTotalFilters());
        }

        // Refresh buttons
        const refreshPendingBtn = document.getElementById('refreshPendingRequests');
        if (refreshPendingBtn) {
            refreshPendingBtn.addEventListener('click', () => this.refreshPendingRequests());
        }

        const refreshTotalBtn = document.getElementById('refreshTotalLeaves');
        if (refreshTotalBtn) {
            refreshTotalBtn.addEventListener('click', () => this.refreshTotalLeaves());
        }

        // Clear Leave Data button (Admin only)
        const clearLeaveDataBtn = document.getElementById('clearLeaveDataBtn');
        if (clearLeaveDataBtn) {
            clearLeaveDataBtn.addEventListener('click', () => this.clearLeaveData());
        }

        // Confirmation modal events - Use event delegation for dynamic modals
        document.addEventListener('click', (e) => {
            console.log('Click event detected on:', e.target, 'ID:', e.target.id, 'Class:', e.target.className);
            
            // Handle Save button click
            if (e.target && e.target.id === 'confirmAction') {
                console.log('Save button clicked - executing action');
                e.preventDefault();
                e.stopPropagation();
                this.executeAction();
                return;
            }
            
            // Handle Cancel button click
            if (e.target && e.target.id === 'cancelAction') {
                console.log('Cancel button clicked - closing modal');
                e.preventDefault();
                e.stopPropagation();
                this.closeModal();
                return;
            }
        });
        
        // Also try direct binding as backup
        setTimeout(() => {
            const saveBtn = document.getElementById('confirmAction');
            const cancelBtn = document.getElementById('cancelAction');
            
            if (saveBtn) {
                console.log('Binding direct event to Save button');
                saveBtn.addEventListener('click', (e) => {
                    console.log('Direct Save button click');
                    e.preventDefault();
                    e.stopPropagation();
                    this.executeAction();
                });
            } else {
                console.log('Save button not found for direct binding');
            }
            
            if (cancelBtn) {
                console.log('Binding direct event to Cancel button');
                cancelBtn.addEventListener('click', (e) => {
                    console.log('Direct Cancel button click');
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeModal();
                });
            } else {
                console.log('Cancel button not found for direct binding');
            }
        }, 1000);

        // Auto-refresh disabled as per guide
        // this.startAutoRefresh();
    }

    loadData() {
        console.log('Loading leave request data...');
        this.loadDoerOptions();
        
        // Load data immediately if pending tab is active (for admin/manager)
        // Doer will load total tab by default
        if (this.userRole !== 'doer') {
            // For admin/manager, ensure pending tab loads
            const pendingTab = document.getElementById('pendingRequestsTab');
            if (pendingTab && pendingTab.classList.contains('active')) {
                console.log('Pending tab is active, loading pending requests...');
                setTimeout(() => {
                    this.loadPendingRequests();
                }, 100);
            }
        }
        
        // Also ensure initTabs handles it properly
    }

    refreshAllData() {
        console.log('Refreshing all data...');
        
        // Clear existing data first
        this.clearTableData();
        
        // Load all tables and metrics
        this.loadPendingRequests();
        this.loadTotalLeaves();
        this.loadMetrics();
    }
    
    clearTableData() {
        // Clear pending requests table
        const pendingTableBody = document.querySelector('#pendingRequestsTable tbody');
        if (pendingTableBody) {
            pendingTableBody.innerHTML = '<tr id="loadingPendingRow"><td colspan="8" class="text-center"><div class="spinner-border text-warning" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Loading pending requests...</p></td></tr>';
        }
        
        // Clear total leaves table
        const totalTableBody = document.querySelector('#totalLeavesTable tbody');
        if (totalTableBody) {
            totalTableBody.innerHTML = '<tr id="loadingTotalRow"><td colspan="8" class="text-center"><div class="spinner-border text-success" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Loading leave data...</p></td></tr>';
        }
    }

    loadPendingRequests() {
        console.log('Loading pending requests...');
        
        if (this.userRole !== 'admin' && this.userRole !== 'manager' && this.userRole !== 'doer') {
            console.log('Skipping pending requests - not admin/manager/doer');
            return;
        }

        const tableBody = document.querySelector('#pendingRequestsTable tbody');
        const loadingRow = document.getElementById('loadingPendingRow');

        console.log('Making AJAX call to leave_fetch_pending.php...');
        
        // Build query parameters with pagination and user info
        const params = new URLSearchParams();
        params.append('user_role', this.userRole);
        params.append('user_name', this.userName);
        if (this.userDisplayName && this.userDisplayName !== this.userName) {
            params.append('user_display_name', this.userDisplayName);
        }
        if (this.userId) {
            params.append('user_id', this.userId);
        }
        
        // Get current page from URL
        const urlParams = new URLSearchParams(window.location.search);
        const currentPage = urlParams.get('page') || '1';
        params.append('page', currentPage);
        
        // Add sorting parameters from URL
        const sortParam = urlParams.get('sort');
        const dirParam = urlParams.get('dir');
        // Always send sort parameters if they exist and are valid (asc/desc)
        // If no sort params, default will be applied server-side (unique_service_no DESC)
        if (sortParam && dirParam && (dirParam === 'asc' || dirParam === 'desc')) {
            params.append('sort', sortParam);
            params.append('dir', dirParam);
        }
        
        // Debug: Log parameters being sent
        console.log('=== PENDING REQUESTS DEBUG ===');
        console.log('User Role:', this.userRole);
        console.log('User Name:', this.userName);
        console.log('Current Page:', currentPage);
        console.log('Full URL:', `../ajax/leave_fetch_pending.php?${params.toString()}`);
        console.log('=== END DEBUG ===');
        
        // Pagination is now handled server-side in PHP like checklist_task.php
        
        fetch(`../ajax/leave_fetch_pending.php?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('Pending requests response status:', response.status);
            console.log('Pending requests response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Non-JSON response received:', text.substring(0, 500));
                    throw new Error('Server returned non-JSON response. Check console for details.');
                });
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Pending requests response data:', data);
            if (data && data.success) {
                const requestsData = data.data || [];
                console.log('Pending requests data count:', requestsData.length);
                this.renderPendingRequests(requestsData, data.pagination);
            } else {
                const errorMsg = (data && data.error) ? data.error : 'Failed to load pending requests';
                console.log('Pending requests error:', errorMsg);
                this.showToast(errorMsg, 'error');
                this.renderPendingRequests([]);
            }
            if (loadingRow) loadingRow.remove();
        })
        .catch(error => {
            console.error('Error loading pending requests:', error);
            this.showToast('Failed to load pending requests: ' + error.message, 'error');
            this.renderPendingRequests([]);
            if (loadingRow) loadingRow.remove();
        });
    }

    loadTotalLeaves(page = null) {
        // Only log in development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log('loadTotalLeaves called', { page });
            console.log('User role:', this.userRole);
            console.log('User name:', this.userName);
        }
        
        const tableBody = document.querySelector('#totalLeavesTable tbody');
        const loadingRow = document.getElementById('loadingTotalRow');
        
        if (!tableBody) {
            console.error('Total leaves table body not found in DOM!');
            return;
        }
        
        // Only log in development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log('Table body found:', !!tableBody);
            console.log('Loading row found:', !!loadingRow);
        }

        // Use provided page, or current page, or default to 1
        if (page !== null) {
            this.currentTotalPage = parseInt(page);
        } else {
            // Get current page from URL or use stored page
            const urlParams = new URLSearchParams(window.location.search);
            const urlPage = urlParams.get('page');
            if (urlPage) {
                this.currentTotalPage = parseInt(urlPage);
            } else if (!this.currentTotalPage) {
                this.currentTotalPage = 1;
            }
        }

        // Build query parameters
        const params = new URLSearchParams();
        params.append('user_role', this.userRole);
        params.append('user_name', this.userName);
        params.append('page', this.currentTotalPage.toString());
        
        // Add sorting parameters from URL
        const urlParams = new URLSearchParams(window.location.search);
        let sortParam = urlParams.get('sort');
        let dirParam = urlParams.get('dir');
        
        // Check if sort parameters are valid
        const isValidSort = sortParam && dirParam && (dirParam === 'asc' || dirParam === 'desc');
        
        // For Total Leave request table, use default sort (unique_service_no DESC) ONLY if:
        // 1. No valid sort parameters in URL (first load)
        // Do NOT override if user has explicitly sorted by clicking a column header
        if (!isValidSort) {
            sortParam = 'unique_service_no';
            dirParam = 'desc';
            // Update URL to reflect default sort
            urlParams.set('sort', sortParam);
            urlParams.set('dir', dirParam);
            window.history.replaceState({}, '', '?' + urlParams.toString());
            // Update global sort state
            if (typeof currentSortColumn !== 'undefined') {
                currentSortColumn = sortParam;
                currentSortDirection = dirParam;
            }
            if (typeof currentTableType !== 'undefined') {
                currentTableType = 'total';
            }
        } else {
            // User has explicitly set sort parameters - use them and update global state
            if (typeof currentSortColumn !== 'undefined') {
                currentSortColumn = sortParam;
                currentSortDirection = dirParam;
            }
            if (typeof currentTableType !== 'undefined') {
                currentTableType = 'total';
            }
        }
        
        // Always send sort parameters if they are valid (asc/desc)
        if (sortParam && dirParam && (dirParam === 'asc' || dirParam === 'desc')) {
            params.append('sort', sortParam);
            params.append('dir', dirParam);
        }
        
        // Debug: Log parameters being sent (only in development)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log('=== TOTAL REQUESTS DEBUG ===');
            console.log('User Role:', this.userRole);
            console.log('User Name:', this.userName);
            console.log('Current Page:', this.currentTotalPage);
            console.log('Full URL:', `../ajax/leave_fetch_totals.php?${params.toString()}`);
            console.log('=== END DEBUG ===');
        }
        
        const doerFilter = document.getElementById('doerFilter');
        const statusFilter = document.getElementById('statusFilter');
        const leaveTypeFilter = document.getElementById('leaveTypeFilter');
        const durationFilter = document.getElementById('durationFilter');
        const startDateFilter = document.getElementById('startDateFilter');
        const endDateFilter = document.getElementById('endDateFilter');
        
        // Add filter parameters
        if (doerFilter && doerFilter.value) {
            params.append('employee', doerFilter.value);
        }
        if (statusFilter && statusFilter.value) {
            params.append('status', statusFilter.value);
        }
        if (leaveTypeFilter && leaveTypeFilter.value) {
            params.append('leave_type', leaveTypeFilter.value);
        }
        if (durationFilter && durationFilter.value) {
            params.append('duration', durationFilter.value);
        }
        if (startDateFilter && startDateFilter.value) {
            // Native date input already returns YYYY-MM-DD format
            params.append('start_date', startDateFilter.value);
        }
        if (endDateFilter && endDateFilter.value) {
            // Native date input already returns YYYY-MM-DD format
            params.append('end_date', endDateFilter.value);
        }

        // Show loading state IMMEDIATELY (before AJAX call)
        if (tableBody) {
            // Remove existing loading row if present
            if (loadingRow) {
                loadingRow.remove();
            }
            const colspan = this.userRole === 'doer' ? '7' : '8';
            tableBody.innerHTML = `<tr id="loadingTotalRow"><td colspan="${colspan}" class="text-center"><div class="spinner-border text-success" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Loading leave data...</p></td></tr>`;
        }

        // Make AJAX call to fetch total leaves (non-blocking)
        fetch(`../ajax/leave_fetch_totals.php?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        })
        .then(response => {
            // Only log in development
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                console.log('Total leaves response status:', response.status);
            }
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                        console.error('Non-JSON response received:', text.substring(0, 500));
                    }
                    throw new Error('Server returned non-JSON response. Check console for details.');
                });
            }
            
            return response.json();
        })
        .then(data => {
            // Only log in development
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                console.log('Total leaves response data:', data);
            }
            if (data && data.success) {
                const leavesData = data.data || [];
                // Only log in development
                if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                    console.log('Total leaves data count:', leavesData.length);
                }
                this.renderTotalLeaves(leavesData, data.pagination);
            } else {
                const errorMsg = (data && data.error) ? data.error : 'Failed to load leave records';
                // Always log errors
                console.error('Total leaves error:', errorMsg);
                this.showToast(errorMsg, 'error');
                this.renderTotalLeaves([], null);
            }
            if (loadingRow) loadingRow.remove();
        })
        .catch(error => {
            console.error('Error loading total leaves:', error);
            this.showToast('Failed to load leave records', 'error');
            this.renderTotalLeaves([], null);
            if (loadingRow) loadingRow.remove();
        });
    }

    loadDoerOptions() {
        // Only for manager and admin
        if (this.userRole !== 'manager' && this.userRole !== 'admin') {
            return;
        }

        const doerFilter = document.getElementById('doerFilter');
        if (!doerFilter) return;

        // Load all unique employee names from the leave requests table
        const params = new URLSearchParams();
        params.append('user_role', this.userRole);
        params.append('user_name', this.userName);
        
        fetch(`../ajax/leave_fetch_employee_names.php?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && Array.isArray(data.data)) {
                // Use the employee names directly from the response
                doerFilter.innerHTML = '<option value="">All Names</option>';
                data.data.forEach(name => {
                    if (name && name.trim()) {
                        const option = document.createElement('option');
                        option.value = name;
                        option.textContent = name;
                        doerFilter.appendChild(option);
                    }
                });
                
                // Re-initialize Select2 after populating options
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    $('#doerFilter').select2({
                        placeholder: "Search or select a name...",
                        allowClear: true,
                        width: '100%'
                    });
                    
                    // Set pending filter value from URL if exists
                    if (this.pendingDoerFilterValue) {
                        $('#doerFilter').val(this.pendingDoerFilterValue).trigger('change');
                        this.pendingDoerFilterValue = null; // Clear after setting
                    }
                }
                
                console.log(`Loaded ${data.count} employee names for filter`);
            } else {
                console.error('Error loading employee names:', data.error);
                this.loadFallbackDoerOptions(doerFilter);
            }
        })
        .catch(error => {
            console.error('Error loading employee names:', error);
            this.loadFallbackDoerOptions(doerFilter);
        });
    }

    loadFallbackDoerOptions(doerFilter) {
        // Fallback to dummy data
        const dummyDoers = [
            { name: 'John Doe' },
            { name: 'Jane Smith' },
            { name: 'Mike Wilson' },
            { name: 'Sarah Jones' }
        ];
        doerFilter.innerHTML = '<option value="">All Names</option>';
        dummyDoers.forEach(doer => {
            const option = document.createElement('option');
            option.value = doer.name;
            option.textContent = doer.name;
            doerFilter.appendChild(option);
        });
    }

    renderPendingRequests(data, pagination = null) {
        const tableBody = document.querySelector('#pendingRequestsTable tbody');
        if (!tableBody) {
            console.error('Pending requests table body not found!');
            return;
        }

        // Remove loading row if it exists
        const loadingRow = document.getElementById('loadingPendingRow');
        if (loadingRow) {
            loadingRow.remove();
        }

        // Clear existing content
        tableBody.innerHTML = '';

        if (!data || !Array.isArray(data) || data.length === 0) {
            const colspan = (this.userRole === 'admin' || this.userRole === 'manager') ? '8' : '7';
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${colspan}" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p>No pending leave requests found.</p>
                        <small>All leave requests have been processed, or no data is available.</small>
                    </td>
                </tr>
            `;
            console.log('No pending requests to display');
            return;
        }
        
        console.log(`Rendering ${data.length} pending requests`);

        // Pagination is now handled server-side in PHP like checklist_task.php

        data.forEach(request => {
            const row = document.createElement('tr');
            
            // Debug: Log the request data to console for troubleshooting
            console.log('Rendering pending request:', {
                unique_service_no: request.unique_service_no,
                employee_name: request.employee_name,
                leave_type: request.leave_type,
                duration: request.duration,
                start_date: request.start_date,
                end_date: request.end_date,
                reason: request.reason,
                manager_name: request.manager_name
            });
            
            // Build Actions column conditionally
            let actionsColumn = '';
            if (this.userRole === 'admin' || this.userRole === 'manager') {
                // For managers, disable actions if this is their own request
                const isManagerOwnRequest = this.userRole === 'manager' && 
                    (request.employee_name === this.userName || 
                     request.employee_name === this.userDisplayName);
                
                const disabledAttr = isManagerOwnRequest ? 'disabled' : '';
                const disabledClass = isManagerOwnRequest ? 'disabled' : '';
                const disabledStyle = isManagerOwnRequest ? 'opacity: 0.5; cursor: not-allowed;' : '';
                const tooltip = isManagerOwnRequest ? 'title="You cannot approve/reject your own leave request"' : '';
                
                actionsColumn = `
                    <td>
                        <div class="btn-group action-icon-buttons" role="group" ${tooltip}>
                            <button class="btn btn-icon-approve ${disabledClass}" 
                                    onclick="${isManagerOwnRequest ? 'return false;' : `leaveManager.showActionModal('${request.unique_service_no}', 'Approve')`}"
                                    ${disabledAttr}
                                    style="${disabledStyle}"
                                    title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-icon-reject ${disabledClass}" 
                                    onclick="${isManagerOwnRequest ? 'return false;' : `leaveManager.showActionModal('${request.unique_service_no}', 'Reject')`}"
                                    ${disabledAttr}
                                    style="${disabledStyle}"
                                    title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                `;
            }
            
            row.innerHTML = `
                <td>
                    <div>
                        <div class="fw-bold">${this.cleanEmployeeName(request.employee_name || 'Unknown')}</div>
                        <small class="text-muted">${request.unique_service_no || 'No ID'}</small>
                    </div>
                </td>
                <td><span class="badge leave-type-badge ${this.getLeaveTypeClass(request.leave_type)}">${this.formatLeaveType(request.leave_type)}</span></td>
                <td>${request.duration || 'N/A'}</td>
                <td>${this.formatDate(request.start_date)}</td>
                <td>${this.formatDate(request.end_date)}</td>
                <td class="text-truncate" style="max-width: 200px;" title="${request.reason || ''}">
                    ${this.truncateText(request.reason, 50) || 'N/A'}
                </td>
                <td>${request.manager_name || 'N/A'}</td>
                ${actionsColumn}
            `;
            tableBody.appendChild(row);
        });
    }

    renderTotalLeaves(data, pagination = null) {
        const tableBody = document.querySelector('#totalLeavesTable tbody');
        if (!tableBody) {
            console.error('Total leaves table body not found!');
            return;
        }

        tableBody.innerHTML = '';

        if (!data || !Array.isArray(data) || data.length === 0) {
            const colspan = this.userRole === 'doer' ? '7' : '8';
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${colspan}" class="text-center empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h5>No Leave Records</h5>
                        <p>No leave records found for the selected criteria.</p>
                    </td>
                </tr>
            `;
            // Render empty pagination
            this.renderTotalPagination(pagination);
            // Adjust column widths for doer view
            this.adjustTotalLeavesTableWidths();
            return;
        }

        // Use DocumentFragment for batch DOM updates (better performance)
        const fragment = document.createDocumentFragment();
        
        data.forEach(leave => {
            const row = document.createElement('tr');
            const statusBadge = this.getStatusBadge(leave.status);
            
            // Only log in development (removed per-row logging for performance)
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                // Log only first row to avoid performance issues
                if (data.indexOf(leave) === 0) {
                    console.log('Rendering total leaves:', data.length, 'rows');
                }
            }
            
            let employeeColumn = '';
            if (this.userRole === 'manager' || this.userRole === 'admin') {
                employeeColumn = `
                    <td>
                        <div>
                            <div class="fw-bold">${this.cleanEmployeeName(leave.employee_name || 'Unknown')}</div>
                            <small class="text-muted">${leave.unique_service_no || 'No ID'}</small>
                        </div>
                    </td>
                `;
            }
            
            row.innerHTML = `
                ${employeeColumn}
                <td><span class="badge leave-type-badge ${this.getLeaveTypeClass(leave.leave_type)}">${this.formatLeaveType(leave.leave_type)}</span></td>
                <td>${leave.duration || 'N/A'}</td>
                <td>${this.formatDate(leave.start_date)}</td>
                <td>${this.formatDate(leave.end_date)}</td>
                <td>
                    <div class="reason-text" title="${leave.reason || ''}">
                        ${this.truncateText(leave.reason, 50) || 'N/A'}
                    </div>
                </td>
                <td><span class="badge bg-secondary leaves-count">${leave.leave_count || 'N/A'}</span></td>
                <td>${statusBadge}</td>
            `;
            fragment.appendChild(row);
        });
        
        // Append all rows at once (single DOM operation)
        tableBody.appendChild(fragment);

        // Render pagination controls
        this.renderTotalPagination(pagination);
        
        // Adjust column widths based on user role
        this.adjustTotalLeavesTableWidths();
    }

    adjustTotalLeavesTableWidths() {
        const table = document.getElementById('totalLeavesTable');
        if (!table) return;

        const headerRow = table.querySelector('thead tr');
        if (!headerRow) return;

        const columnCount = headerRow.querySelectorAll('th').length;

        // If doer view (7 columns), adjust widths
        if (columnCount === 7) {
            const style = document.createElement('style');
            style.id = 'totalLeavesTableDoerWidths';
            
            // Remove existing doer width styles if any
            const existingStyle = document.getElementById('totalLeavesTableDoerWidths');
            if (existingStyle) existingStyle.remove();

            style.textContent = `
                #totalLeavesTable th:nth-child(1),
                #totalLeavesTable td:nth-child(1) { width: 12% !important; } /* Leave Type */
                #totalLeavesTable th:nth-child(2),
                #totalLeavesTable td:nth-child(2) { width: 10% !important; } /* Duration */
                #totalLeavesTable th:nth-child(3),
                #totalLeavesTable td:nth-child(3) { width: 10% !important; } /* Start Date */
                #totalLeavesTable th:nth-child(4),
                #totalLeavesTable td:nth-child(4) { width: 10% !important; } /* End Date */
                #totalLeavesTable th:nth-child(5),
                #totalLeavesTable td:nth-child(5) { width: 20% !important; white-space: normal !important; word-wrap: break-word !important; } /* Reason - Matching Pending */
                #totalLeavesTable th:nth-child(6),
                #totalLeavesTable td:nth-child(6) { width: 20% !important; text-align: center; } /* No. of Leaves */
                #totalLeavesTable th:nth-child(7),
                #totalLeavesTable td:nth-child(7) { width: 18% !important; text-align: center; } /* Status */
            `;
            document.head.appendChild(style);
        } else {
            // Remove doer-specific styles if switching to admin/manager view
            const existingStyle = document.getElementById('totalLeavesTableDoerWidths');
            if (existingStyle) existingStyle.remove();
        }
    }

    renderTotalPagination(pagination) {
        const paginationContainer = document.getElementById('totalLeavesPagination');
        const paginationInfo = paginationContainer?.querySelector('.pagination-info');
        const paginationNav = document.getElementById('totalLeavesPaginationNav');
        const paginationList = document.getElementById('totalLeavesPaginationList');

        if (!paginationContainer || !paginationInfo) return;

        if (!pagination || pagination.total_pages === 0) {
            paginationInfo.innerHTML = '<span class="text-muted">No records found</span>';
            if (paginationNav) paginationNav.style.display = 'none';
            return;
        }

        // Update pagination info
        paginationInfo.innerHTML = `<span class="text-muted">Showing ${pagination.start} to ${pagination.end} of ${pagination.total_records} entries</span>`;

        // Update current page
        this.currentTotalPage = pagination.current_page;

        // Render pagination controls
        if (pagination.total_pages > 1 && paginationNav && paginationList) {
            paginationNav.style.display = 'block';
            paginationList.innerHTML = '';

            // Previous button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${pagination.current_page <= 1 ? 'disabled' : ''}`;
            const prevLink = document.createElement('a');
            prevLink.className = 'page-link';
            prevLink.href = '#';
            prevLink.textContent = 'Previous';
                    if (pagination.current_page > 1) {
                prevLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.updateURLWithPage(pagination.current_page - 1);
                    this.loadTotalLeaves(pagination.current_page - 1);
                    // Scroll to top of table
                    const tableContainer = document.querySelector('#totalLeavesTable');
                    if (tableContainer) {
                        tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            }
            prevLi.appendChild(prevLink);
            paginationList.appendChild(prevLi);

            // Page numbers
            for (let i = 1; i <= pagination.total_pages; i++) {
                // Page number jumping logic: show first, last, current, and pages around current
                const showPage = (i === 1 || i === pagination.total_pages || 
                                (i >= pagination.current_page - 2 && i <= pagination.current_page + 2));

                if (showPage) {
                    const pageLi = document.createElement('li');
                    pageLi.className = `page-item ${i === pagination.current_page ? 'active' : ''}`;
                    const pageLink = document.createElement('a');
                    pageLink.className = 'page-link';
                    pageLink.href = '#';
                    pageLink.textContent = i.toString();
                    if (i !== pagination.current_page) {
                        pageLink.addEventListener('click', (e) => {
                            e.preventDefault();
                            this.updateURLWithPage(i);
                            this.loadTotalLeaves(i);
                            // Scroll to top of table
                            const tableContainer = document.querySelector('#totalLeavesTable');
                            if (tableContainer) {
                                tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        });
                    }
                    pageLi.appendChild(pageLink);
                    paginationList.appendChild(pageLi);
                } else if ((i === pagination.current_page - 3 && pagination.current_page > 4) || 
                          (i === pagination.current_page + 3 && pagination.current_page < pagination.total_pages - 3)) {
                    // Ellipsis
                    const ellipsisLi = document.createElement('li');
                    ellipsisLi.className = 'page-item disabled';
                    const ellipsisSpan = document.createElement('span');
                    ellipsisSpan.className = 'page-link';
                    ellipsisSpan.textContent = '...';
                    ellipsisLi.appendChild(ellipsisSpan);
                    paginationList.appendChild(ellipsisLi);
                }
            }

            // Next button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''}`;
            const nextLink = document.createElement('a');
            nextLink.className = 'page-link';
            nextLink.href = '#';
            nextLink.textContent = 'Next';
            if (pagination.current_page < pagination.total_pages) {
                nextLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.updateURLWithPage(pagination.current_page + 1);
                    this.loadTotalLeaves(pagination.current_page + 1);
                    // Scroll to top of table
                    const tableContainer = document.querySelector('#totalLeavesTable');
                    if (tableContainer) {
                        tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            }
            nextLi.appendChild(nextLink);
            paginationList.appendChild(nextLi);
        } else {
            if (paginationNav) paginationNav.style.display = 'none';
        }
    }

    refreshPendingRequests() {
        const btn = document.getElementById('refreshPendingRequests');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Syncing...';
            btn.classList.add('btn-warning'); // Change color to indicate active state
        }
        
        // Show initial sync message
        this.showToast('Starting sync from Google Sheets...', 'info');
        
        this.syncDataFromGoogleSheets()
            .then((result) => {
                console.log('Sync result:', result);
                // Update global last refresh timestamp (both tables)
                this.updateLastRefreshGlobal();
                // Refresh all tables and metrics
                this.refreshAllData();
                
                if (result.data && result.data.synced > 0) {
                    let message = `All data refreshed! ${result.data.synced} records synced from Google Sheets.`;
                    if (result.data.errors && result.data.errors.length > 0) {
                        message += ` (${result.data.errors.length} errors occurred)`;
                    }
                    this.showToast(message, 'success');
                } else if (result.data && result.data.skipped) {
                    this.showToast('No changes detected in Google Sheets. Data is up to date.', 'info');
                } else {
                    this.showToast('All data refreshed successfully!', 'success');
                }
            })
            .catch(error => {
                console.error('Refresh error:', error);
                this.showToast('Failed to refresh pending requests: ' + (error.message || 'Unknown error'), 'error');
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Refresh';
                    btn.classList.remove('btn-warning'); // Remove warning color
                }
            });
    }

    refreshTotalLeaves() {
        const btn = document.getElementById('refreshTotalLeaves');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Syncing...';
            btn.classList.add('btn-warning'); // Change color to indicate active state
        }
        
        // Show initial sync message
        this.showToast('Starting sync from Google Sheets...', 'info');
        
        this.syncDataFromGoogleSheets()
            .then((result) => {
                console.log('Sync result:', result);
                // Update global last refresh timestamp (both tables)
                this.updateLastRefreshGlobal();
                // Refresh all tables and metrics
                this.refreshAllData();
                
                if (result.data && result.data.synced > 0) {
                    let message = `All data refreshed! ${result.data.synced} records synced from Google Sheets.`;
                    if (result.data.errors && result.data.errors.length > 0) {
                        message += ` (${result.data.errors.length} errors occurred)`;
                    }
                    this.showToast(message, 'success');
                } else if (result.data && result.data.skipped) {
                    this.showToast('No changes detected in Google Sheets. Data is up to date.', 'info');
                } else {
                    this.showToast('All data refreshed successfully!', 'success');
                }
            })
            .catch(error => {
                console.error('Refresh error:', error);
                this.showToast('Failed to refresh total leaves: ' + (error.message || 'Unknown error'), 'error');
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Refresh';
                    btn.classList.remove('btn-warning'); // Remove warning color
                }
            });
    }

    syncDataFromGoogleSheets() {
        console.log('Starting Google Sheets sync...');
        console.log('Making request to: ../ajax/leave_auto_sync.php');
        
        return fetch('../ajax/leave_auto_sync.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('Sync response status:', response.status);
            console.log('Sync response headers:', response.headers);
            
            if (!response.ok) {
                console.error('HTTP error response:', response.status, response.statusText);
                throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
            }
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                console.error('Response is not JSON. Content-Type:', contentType);
                return response.text().then(text => {
                    console.error('Response text:', text);
                    throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
                });
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Sync response data:', data);
            if (data.success) {
                console.log('Leave sync completed successfully:', data.data);
                // Log detailed sync results
                if (data.data && data.data.errors && data.data.errors.length > 0) {
                    console.warn('Sync completed with errors:', data.data.errors);
                }
                return data;
            } else {
                console.error('Sync failed with error:', data.error);
                throw new Error(data.error || 'Sync failed');
            }
        })
        .catch(error => {
            console.error('Sync error details:', error);
            console.error('Error message:', error.message);
            console.error('Error stack:', error.stack);
            throw error;
        });
    }

    startAutoRefresh() {
        // Auto-refresh every 30 seconds
        setInterval(() => {
            this.syncDataFromGoogleSheets()
                .then(() => {
                    this.loadPendingRequests();
                    this.loadTotalLeaves();
                    console.log('Auto-refresh completed');
                })
                .catch(error => {
                    console.error('Auto-refresh error:', error);
                });
        }, 30000); // 30 seconds
    }

    applyFilters() {
        // Get current filter values
        const doerFilter = document.getElementById('doerFilter');
        const statusFilter = document.getElementById('statusFilter');
        const leaveTypeFilter = document.getElementById('leaveTypeFilter');
        const durationFilter = document.getElementById('durationFilter');
        const startDateFilter = document.getElementById('startDateFilter');
        const endDateFilter = document.getElementById('endDateFilter');
        
        // Update URL with filter parameters
        const url = new URL(window.location);
        url.searchParams.delete('page'); // Reset to page 1 when filters change
        
        // Update filter parameters
        if (doerFilter && doerFilter.value) {
            url.searchParams.set('filter_name', doerFilter.value);
            url.searchParams.set('filter_employee', doerFilter.value); // Support both parameter names
        } else {
            url.searchParams.delete('filter_name');
            url.searchParams.delete('filter_employee');
        }
        
        if (statusFilter && statusFilter.value) {
            url.searchParams.set('filter_status', statusFilter.value);
        } else {
            url.searchParams.delete('filter_status');
        }
        
        if (leaveTypeFilter && leaveTypeFilter.value) {
            url.searchParams.set('filter_leave_type', leaveTypeFilter.value);
        } else {
            url.searchParams.delete('filter_leave_type');
        }
        
        if (durationFilter && durationFilter.value) {
            url.searchParams.set('filter_duration', durationFilter.value);
        } else {
            url.searchParams.delete('filter_duration');
        }
        
        if (startDateFilter && startDateFilter.value) {
            url.searchParams.set('start_date', startDateFilter.value);
            url.searchParams.set('filter_start_date', startDateFilter.value); // Support both parameter names
        } else {
            url.searchParams.delete('start_date');
            url.searchParams.delete('filter_start_date');
        }
        
        if (endDateFilter && endDateFilter.value) {
            url.searchParams.set('end_date', endDateFilter.value);
            url.searchParams.set('filter_end_date', endDateFilter.value); // Support both parameter names
        } else {
            url.searchParams.delete('end_date');
            url.searchParams.delete('filter_end_date');
        }
        
        // Update URL without page reload
        window.history.pushState({}, '', url);
        
        // Reset to page 1 when filters are applied
        this.currentTotalPage = 1;
        
        // Reload data with new filters
        this.loadTotalLeaves(1);
        this.loadMetrics();
        
        this.showToast('Filters applied successfully!', 'info');
    }

    showActionModal(uniqueServiceNo, action) {
        console.log('showActionModal called with:', { uniqueServiceNo, action });
        this.currentServiceNo = uniqueServiceNo;
        this.currentAction = action;
        
        const modalElement = document.getElementById('confirmationModal');
        const message = document.getElementById('confirmationMessage');
        const noteInput = document.getElementById('actionNote');
        
        console.log('Modal elements found:', {
            modalElement: !!modalElement,
            message: !!message,
            noteInput: !!noteInput
        });
        
        if (!modalElement) {
            console.error('Modal element not found!');
            return;
        }
        
        // Update modal content
        if (message) {
            message.textContent = `Are you sure you want to ${action.toLowerCase()} this leave request?`;
        }
        if (noteInput) {
            noteInput.value = '';
        }
        
        // Show modal using Bootstrap 5
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
        modal.show();
        
        console.log('Modal shown');
    }

    closeModal() {
        console.log('closeModal called');
        const modalElement = document.getElementById('confirmationModal');
        if (modalElement) {
            // Try Bootstrap 5 method first
            try {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    console.log('Using Bootstrap modal hide()');
                    modal.hide();
                } else {
                    // Create new modal instance and hide it
                    const newModal = new bootstrap.Modal(modalElement);
                    newModal.hide();
                    console.log('Created new modal instance and hiding');
                }
            } catch (error) {
                console.log('Bootstrap modal error, using fallback:', error);
                // Fallback: manually hide modal
                modalElement.classList.remove('show');
                modalElement.style.display = 'none';
                modalElement.setAttribute('aria-hidden', 'true');
                modalElement.removeAttribute('aria-modal');
                
                // Remove backdrop
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
                
                // Re-enable body scroll
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        }
        
        // Reset current action
        this.currentAction = null;
        this.currentServiceNo = null;
        this.currentNotificationId = null;
        console.log('Modal closed and state reset');
    }

    executeAction() {
        console.log('executeAction called with:', {
            serviceNo: this.currentServiceNo,
            action: this.currentAction
        });

        if (!this.currentServiceNo || !this.currentAction) {
            this.showToast('No action selected', 'error');
            return;
        }

        const noteInput = document.getElementById('actionNote');
        const note = noteInput ? noteInput.value.trim() : '';

        console.log('Sending data:', {
            unique_service_no: this.currentServiceNo,
            action: this.currentAction,
            note: note
        });

        // Disable the confirm button
        const confirmBtn = document.getElementById('confirmAction');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
        }

        // Make AJAX call to save action
        const formData = new FormData();
        formData.append('unique_service_no', this.currentServiceNo);
        formData.append('action', this.currentAction);
        formData.append('note', note);

        fetch('../ajax/leave_status_action.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                this.showToast(data.message || `Leave request ${this.currentAction.toLowerCase()}d successfully!`, 'success');
                
                // If action was triggered from notification, mark notification as read
                if (this.currentNotificationId) {
                    this.markNotificationAsRead(this.currentNotificationId);
                }
                
                // Close modal using our custom method
                this.closeModal();

                // Refresh data immediately after approve/reject
                this.loadPendingRequests();
                this.loadTotalLeaves();
                this.loadMetrics();
                
                // Reload notifications if notification system is available
                if (typeof window.NotificationManager !== 'undefined' && window.NotificationManager.loadNotifications) {
                    setTimeout(() => {
                        window.NotificationManager.loadNotifications();
                        window.NotificationManager.updateUnreadCount();
                    }, 500);
                }
            } else {
                this.showToast(data.error || 'Failed to save action', 'error');
            }
        })
        .catch(error => {
            console.error('Error saving action:', error);
            this.showToast('Failed to save action', 'error');
        })
        .finally(() => {
            // Re-enable button
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Save';
            }
        });
    }

    markNotificationAsRead(notificationId) {
        console.log('Marking notification as read:', notificationId);
        
        // Call notification_actions.php to mark as read
        fetch('../ajax/notification_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'mark_read',
                notification_id: notificationId
            }),
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Notification marked as read successfully');
            } else {
                console.warn('Failed to mark notification as read:', data.error);
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }

    getStatusBadge(status) {
        const statusMap = {
            'Approve': '<span class="status-badge status-approved">Approved</span>',
            'Pending': '<span class="status-badge status-pending">Pending</span>',
            'Reject': '<span class="status-badge status-rejected">Rejected</span>',
            'Cancelled': '<span class="status-badge status-cancelled">Cancelled</span>',
            // Handle variations
            'APPROVE': '<span class="status-badge status-approved">Approved</span>',
            'APPROVED': '<span class="status-badge status-approved">Approved</span>',
            'PENDING': '<span class="status-badge status-pending">Pending</span>',
            'REJECT': '<span class="status-badge status-rejected">Rejected</span>',
            'REJECTED': '<span class="status-badge status-rejected">Rejected</span>',
            'CANCELLED': '<span class="status-badge status-cancelled">Cancelled</span>',
            'CANCEL': '<span class="status-badge status-cancelled">Cancelled</span>'
        };
        return statusMap[status] || `<span class="status-badge status-pending">Unknown (${status})</span>`;
    }

    formatDate(dateString) {
        if (!dateString || dateString === 'null' || dateString === null || dateString === '') {
            return 'N/A';
        }
        
        // Handle different date formats and convert to DD/MM/YYYY
        let date;
        try {
            // Handle YYYY-MM-DD format (MySQL DATE format)
            if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                const [year, month, day] = dateString.split('-');
                return `${day}/${month}/${year}`;
            }
            // Handle MM/DD/YYYY format (US format from database) - user confirmed dates come in this format
            else if (dateString.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                // Assume MM/DD/YYYY format and convert to DD/MM/YYYY
                const [month, day, year] = dateString.split('/');
                return `${day}/${month}/${year}`;
            }
            // Try to parse as Date object and convert
            else {
                date = new Date(dateString);
                
                // Check if date is valid
                if (isNaN(date.getTime())) {
                    return 'N/A';
                }
                
                // Format as DD/MM/YYYY
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                return `${day}/${month}/${year}`;
            }
        } catch (e) {
            console.warn('Date formatting error for:', dateString, e);
            return 'N/A';
        }
    }

    truncateText(text, maxLength) {
        if (!text) return '';
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }

    cleanEmployeeName(name) {
        if (!name) return 'Unknown';
        return name.trim().replace(/[^\w\s-]/g, '');
    }

    formatLeaveType(leaveType) {
        if (!leaveType) return 'N/A';
        
        // Remove "Leave" from the end if it exists
        let formatted = leaveType.trim();
        if (formatted.toLowerCase().endsWith(' leave')) {
            formatted = formatted.substring(0, formatted.length - 6);
        }
        
        return formatted;
    }

    getLeaveTypeClass(leaveType) {
        if (!leaveType) return '';
        
        const type = leaveType.toLowerCase().trim();
        
        // Check for sick leave variations
        if (type.includes('sick') || type === 'sick') {
            return 'sick-badge';
        }
        
        // Default class (no additional class for casual leave)
        return '';
    }

    showToast(message, type = 'info') {
        const toast = document.getElementById('leaveToast');
        const toastMessage = document.getElementById('toastMessage');
        
        if (!toast || !toastMessage) return;

        // Update message
        toastMessage.textContent = message;
        
        // Update icon and color based on type
        const toastHeader = toast.querySelector('.toast-header');
        const icon = toastHeader.querySelector('i');
        
        icon.className = `fas me-2`;
        toastHeader.className = 'toast-header';
        
        switch (type) {
            case 'success':
                icon.className += ' fa-check-circle text-success';
                toastHeader.className += ' bg-success text-white';
                break;
            case 'error':
                icon.className += ' fa-exclamation-circle text-danger';
                toastHeader.className += ' bg-danger text-white';
                break;
            case 'warning':
                icon.className += ' fa-exclamation-triangle text-warning';
                toastHeader.className += ' bg-warning text-dark';
                break;
            default:
                icon.className += ' fa-info-circle text-primary';
                toastHeader.className += ' bg-primary text-white';
        }

        // Show toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    }

    loadMetrics() {
        console.log('Loading metrics...');
        
        // Get current filter values
        const statusFilter = document.getElementById('statusFilter');
        const doerFilter = document.getElementById('doerFilter');
        const leaveTypeFilter = document.getElementById('leaveTypeFilter');
        const durationFilter = document.getElementById('durationFilter');
        const startDateFilter = document.getElementById('startDateFilter');
        const endDateFilter = document.getElementById('endDateFilter');
        
        // Build query parameters
        const params = new URLSearchParams();
        params.append('user_role', this.userRole);
        params.append('user_name', this.userName);
        
        // Debug: Log parameters being sent
        console.log('=== METRICS DEBUG ===');
        console.log('User Role:', this.userRole);
        console.log('User Name:', this.userName);
        console.log('Full URL:', `../ajax/leave_metrics.php?${params.toString()}`);
        console.log('=== END DEBUG ===');
        
        if (statusFilter && statusFilter.value) {
            params.append('status', statusFilter.value);
        }
        if (doerFilter && doerFilter.value) {
            params.append('employee', doerFilter.value);
        }
        if (leaveTypeFilter && leaveTypeFilter.value) {
            params.append('leave_type', leaveTypeFilter.value);
        }
        if (durationFilter && durationFilter.value) {
            params.append('duration', durationFilter.value);
        }
        if (startDateFilter && startDateFilter.value) {
            // Native date input already returns YYYY-MM-DD format
            params.append('date_from', startDateFilter.value);
        }
        if (endDateFilter && endDateFilter.value) {
            // Native date input already returns YYYY-MM-DD format
            params.append('date_to', endDateFilter.value);
        }
        
        // Make AJAX call to fetch metrics
        fetch(`../ajax/leave_metrics.php?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Metrics response:', data);
                if (data.debug) {
                    console.log('Debug info:', data.debug);
                }
                this.updateMetricsDisplay(data.metrics);
            } else {
                console.error('Error loading metrics:', data.error);
                this.updateMetricsDisplay({ pending: 0, approved: 0, rejected: 0, cancelled: 0 });
            }
        })
        .catch(error => {
            console.error('Error loading metrics:', error);
            this.updateMetricsDisplay({ pending: 0, approved: 0, rejected: 0, cancelled: 0 });
        });
    }

    updateMetricsDisplay(metrics) {
        console.log('Updating metrics display:', metrics);
        
        // Update each metric card
        const pendingCount = document.getElementById('totalPendingCount');
        const approvedCount = document.getElementById('totalApprovedCount');
        const rejectedCount = document.getElementById('totalRejectedCount');
        const cancelledCount = document.getElementById('totalCancelledCount');
        
        if (pendingCount) pendingCount.textContent = metrics.pending || 0;
        if (approvedCount) approvedCount.textContent = metrics.approved || 0;
        if (rejectedCount) rejectedCount.textContent = metrics.rejected || 0;
        if (cancelledCount) cancelledCount.textContent = metrics.cancelled || 0;
    }

    clearFilters() {
        // Clear all filter inputs
        const filters = ['doerFilter', 'statusFilter', 'leaveTypeFilter', 'durationFilter', 'startDateFilter', 'endDateFilter'];
        filters.forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.value = '';
            }
        });
        
        // Reset pagination to first page
        this.currentTotalPage = 1;
        
        // Update URL without page refresh
        const url = new URL(window.location);
        url.searchParams.delete('page');
        url.searchParams.delete('filter_status');
        url.searchParams.delete('filter_name');
        url.searchParams.delete('filter_employee');
        url.searchParams.delete('filter_leave_type');
        url.searchParams.delete('filter_duration');
        url.searchParams.delete('start_date');
        url.searchParams.delete('end_date');
        window.history.pushState({}, '', url);
        
        // Reload data with cleared filters
        this.loadData();
        this.loadTotalLeaves(1); // Also reload the Total Leave Requests table
        this.loadMetrics();
        this.showToast('Filters cleared successfully!', 'success');
    }

    toggleTotalFilters() {
        const filterContent = document.getElementById('totalFilterContent');
        const toggleIcon = document.getElementById('totalFilterToggleIcon');
        const toggleButton = document.getElementById('totalToggleFilters');
        
        if (filterContent && toggleIcon && toggleButton) {
            if (filterContent.classList.contains('collapsed')) {
                // Show filters
                filterContent.classList.remove('collapsed');
                toggleIcon.className = 'fas fa-chevron-up';
                toggleButton.innerHTML = '<i class="fas fa-chevron-up" id="totalFilterToggleIcon"></i> Hide Filters';
            } else {
                // Hide filters
                filterContent.classList.add('collapsed');
                toggleIcon.className = 'fas fa-chevron-down';
                toggleButton.innerHTML = '<i class="fas fa-chevron-down" id="totalFilterToggleIcon"></i> Show Filters';
            }
        }
    }

    // Pagination methods removed - now using server-side PHP pagination like checklist_task.php

    initDatePickers() {
        // Native HTML5 date inputs - make entire input clickable and set constraints
        const dateInputs = document.querySelectorAll('#startDateFilter, #endDateFilter');
        dateInputs.forEach(input => {
            // Set max date to 2 years from now
            const maxDate = new Date();
            maxDate.setFullYear(maxDate.getFullYear() + 2);
            input.setAttribute('max', maxDate.toISOString().split('T')[0]);
            
            // Make the entire input field open the date picker when clicked
            // This ensures clicking anywhere on the input (not just the icon) opens the picker
            input.addEventListener('click', function(e) {
                // Use showPicker if available (modern browsers)
                if (typeof this.showPicker === 'function') {
                    try {
                        // Small delay to ensure the click event doesn't interfere
                        setTimeout(() => {
                            this.showPicker();
                        }, 10);
                    } catch (err) {
                        // Fallback: focus will open picker in most browsers
                        this.focus();
                    }
                } else {
                    // Fallback for older browsers - focus will open picker
                    this.focus();
                }
            });
            
            // Also open on focus (for keyboard navigation and programmatic focus)
            input.addEventListener('focus', function() {
                if (typeof this.showPicker === 'function') {
                    try {
                        // Small delay to ensure focus is set first
                        setTimeout(() => {
                            this.showPicker();
                        }, 50);
                    } catch (err) {
                        // showPicker not supported, focus is enough
                    }
                }
            });
            
            // Add visual feedback
            input.addEventListener('mouseenter', function() {
                this.style.borderColor = '#007bff';
                this.style.boxShadow = '0 0 0 0.2rem rgba(0, 123, 255, 0.25)';
            });
            
            input.addEventListener('mouseleave', function() {
                this.style.borderColor = '';
                this.style.boxShadow = '';
            });
        });
    }

    // Convert YYYY-MM-DD to DD/MM/YYYY for display
    convertDateToDisplayFormat(dateString) {
        if (!dateString || dateString === 'null' || dateString === null || dateString === '') {
            return '';
        }
        
        // Handle YYYY-MM-DD format (MySQL DATE format)
        if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
            const [year, month, day] = dateString.split('-');
            return `${day}/${month}/${year}`;
        }
        
        // If already in DD/MM/YYYY format, return as is
        if (dateString.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
            return dateString;
        }
        
        return '';
    }

    initSelect2() {
        // Initialize Select2 for searchable dropdown
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('#doerFilter').select2({
                placeholder: "Search or select a name...",
                allowClear: true,
                width: '100%'
            });
        }
    }

    initTabs() {
        // Initialize tabs functionality
        console.log('Initializing leave request tabs...');
        
        // Check localStorage for active tab
        const savedTab = localStorage.getItem('activeLeaveTab');
        let initialTab = 'pending';
        
        if (savedTab === 'total' || savedTab === 'pending') {
            initialTab = savedTab;
        } else {
            // Set initial active tab based on user role if no saved tab
            if (this.userRole === 'doer') {
                initialTab = 'total';
            } else {
                initialTab = 'pending';
            }
        }
        
        // Switch to the determined tab
        this.switchLeaveTab(initialTab);
        
        // Ensure the correct tab content is visible
        setTimeout(() => {
            this.loadCurrentTabData(initialTab);
        }, 100);
    }

    switchLeaveTab(tabName) {
        console.log('Switching to tab:', tabName);
        
        // Save active tab to localStorage
        localStorage.setItem('activeLeaveTab', tabName);
        
        // Update tab appearance
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.classList.remove('active');
        });
        
        const activeTab = document.querySelector(`.tab[onclick="switchLeaveTab('${tabName}')"]`);
        if (activeTab) {
            activeTab.classList.add('active');
            console.log('Active tab set:', activeTab);
        } else {
            console.log('Active tab not found for:', tabName);
        }
        
        // Update tab content
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => {
            content.classList.remove('active');
        });
        
        let activeContent;
        if (tabName === 'pending') {
            activeContent = document.getElementById('pendingRequestsTab');
        } else if (tabName === 'total') {
            activeContent = document.getElementById('totalLeaveRequestsTab');
        }
        
        console.log('Active content element:', activeContent);
        
        if (activeContent) {
            activeContent.classList.add('active');
            console.log('Active content class added');
        } else {
            console.log('Active content not found for:', tabName);
        }
        
        // Load appropriate data based on tab
        this.loadCurrentTabData(tabName);
    }

    loadCurrentTabData(tabName) {
        console.log('Loading data for tab:', tabName);
        console.log('User role:', this.userRole);
        
        switch(tabName) {
            case 'pending':
                console.log('Loading pending requests...');
                if (this.userRole === 'admin' || this.userRole === 'manager' || this.userRole === 'doer') {
                    // Small delay to ensure DOM is ready
                    setTimeout(() => {
                        this.loadPendingRequests();
                    }, 50);
                } else {
                    console.warn('User role does not have access to pending requests:', this.userRole);
                }
                break;
            case 'total':
                console.log('Loading total leaves...');
                // Ensure default sort is set for Total Leave table before loading
                const urlParams = new URLSearchParams(window.location.search);
                const sortParam = urlParams.get('sort');
                const dirParam = urlParams.get('dir');
                const isValidSort = sortParam && dirParam && (dirParam === 'asc' || dirParam === 'desc');
                
                // If no valid sort or if current sort is not for total table context, set default
                if (!isValidSort || (typeof currentTableType !== 'undefined' && currentTableType !== 'total')) {
                    urlParams.set('sort', 'unique_service_no');
                    urlParams.set('dir', 'desc');
                    window.history.replaceState({}, '', '?' + urlParams.toString());
                    if (typeof currentSortColumn !== 'undefined') {
                        currentSortColumn = 'unique_service_no';
                        currentSortDirection = 'desc';
                    }
                    if (typeof currentTableType !== 'undefined') {
                        currentTableType = 'total';
                    }
                }
                // Small delay to ensure DOM is ready
                setTimeout(() => {
                    this.loadTotalLeaves();
                }, 50);
                break;
            default:
                console.log('Unknown tab name:', tabName);
        }
        
        // Always load metrics
        this.loadMetrics();
    }

    /**
     * Format timestamp as DD/MM/YYYY, HH:MM:SS
     */
    formatTimestamp(date = new Date()) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        
        return `${day}/${month}/${year}, ${hours}:${minutes}:${seconds}`;
    }

    /**
     * Update one global last refresh timestamp and reflect it on both tables
     */
    updateLastRefreshGlobal() {
        const now = new Date();
        const iso = now.toISOString();
        const formattedTimestamp = this.formatTimestamp(now);
        
        // Store single global timestamp
        localStorage.setItem('leave_last_refresh', iso);
        
        // Update both UI spots
        const pendingTime = document.getElementById('pendingLastRefreshTime');
        const pendingWrap = document.getElementById('pendingLastRefresh');
        const totalTime = document.getElementById('totalLastRefreshTime');
        const totalWrap = document.getElementById('totalLastRefresh');
        
        if (pendingTime) pendingTime.textContent = formattedTimestamp;
        if (totalTime) totalTime.textContent = formattedTimestamp;
        if (pendingWrap) pendingWrap.style.display = 'inline';
        if (totalWrap) totalWrap.style.display = 'inline';
    }

    /**
     * Load last refresh timestamps from both database and localStorage, show most recent
     */
    loadLastRefreshTimestamps() {
        // First, get timestamp from localStorage
        let localStorageTimestamp = null;
        let localStorageDate = null;
        
        const iso = localStorage.getItem('leave_last_refresh');
        if (iso) {
            try {
                localStorageDate = new Date(iso);
                if (!isNaN(localStorageDate.getTime())) {
                    localStorageTimestamp = iso;
                }
            } catch (e) {
                console.warn('Error parsing localStorage timestamp:', e);
            }
        }
        
        // Then, fetch timestamp from database
        fetch('../ajax/get_last_sync_time.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            let databaseTimestamp = null;
            let databaseDate = null;
            
            if (data.success && data.timestamp) {
                try {
                    databaseDate = new Date(data.timestamp);
                    if (!isNaN(databaseDate.getTime())) {
                        databaseTimestamp = data.timestamp;
                    }
                } catch (e) {
                    console.warn('Error parsing database timestamp:', e);
                }
            }
            
            // Compare and use the most recent timestamp
            let mostRecentDate = null;
            let mostRecentSource = null;
            
            if (localStorageDate && databaseDate) {
                // Both exist - use the most recent one
                if (localStorageDate > databaseDate) {
                    mostRecentDate = localStorageDate;
                    mostRecentSource = 'localStorage';
                } else {
                    mostRecentDate = databaseDate;
                    mostRecentSource = 'database';
                    // Update localStorage with database timestamp if it's more recent
                    localStorage.setItem('leave_last_refresh', databaseDate.toISOString());
                }
            } else if (localStorageDate) {
                // Only localStorage exists
                mostRecentDate = localStorageDate;
                mostRecentSource = 'localStorage';
            } else if (databaseDate) {
                // Only database exists
                mostRecentDate = databaseDate;
                mostRecentSource = 'database';
                // Update localStorage with database timestamp
                localStorage.setItem('leave_last_refresh', databaseDate.toISOString());
            }
            
            // Display the most recent timestamp if available
            if (mostRecentDate) {
                const formattedTimestamp = this.formatTimestamp(mostRecentDate);
                const pendingTime = document.getElementById('pendingLastRefreshTime');
                const pendingWrap = document.getElementById('pendingLastRefresh');
                const totalTime = document.getElementById('totalLastRefreshTime');
                const totalWrap = document.getElementById('totalLastRefresh');
                
                if (pendingTime) pendingTime.textContent = formattedTimestamp;
                if (totalTime) totalTime.textContent = formattedTimestamp;
                if (pendingWrap) pendingWrap.style.display = 'inline';
                if (totalWrap) totalWrap.style.display = 'inline';
                
                console.log(`Last refresh loaded from ${mostRecentSource}:`, formattedTimestamp);
            }
        })
        .catch(error => {
            console.warn('Error fetching database timestamp, using localStorage only:', error);
            
            // Fallback: use localStorage if database fetch failed
            if (localStorageDate) {
                const formattedTimestamp = this.formatTimestamp(localStorageDate);
                const pendingTime = document.getElementById('pendingLastRefreshTime');
                const pendingWrap = document.getElementById('pendingLastRefresh');
                const totalTime = document.getElementById('totalLastRefreshTime');
                const totalWrap = document.getElementById('totalLastRefresh');
                
                if (pendingTime) pendingTime.textContent = formattedTimestamp;
                if (totalTime) totalTime.textContent = formattedTimestamp;
                if (pendingWrap) pendingWrap.style.display = 'inline';
                if (totalWrap) totalWrap.style.display = 'inline';
            }
        });
    }

    /**
     * Update URL with page number while maintaining filter parameters
     */
    updateURLWithPage(page) {
        const url = new URL(window.location);
        if (page > 1) {
            url.searchParams.set('page', page);
        } else {
            url.searchParams.delete('page');
        }
        window.history.pushState({}, '', url);
    }

    /**
     * Clear all leave data (Admin only)
     */
    clearLeaveData() {
        // Double check user role (should already be hidden if not admin, but extra safety)
        if (this.userRole !== 'admin') {
            this.showToast('You do not have permission to perform this action.', 'error');
            return;
        }

        // Show confirmation dialog
        const confirmed = window.confirm("Are you sure you want to clear all leave data? This action cannot be undone.");
        
        if (!confirmed) {
            return; // User cancelled
        }

        const btn = document.getElementById('clearLeaveDataBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Clearing...';
        }

        // Send AJAX request to clear data
        fetch('../ajax/clear_leave_data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                this.showToast('All leave data cleared successfully.', 'success');
                
                // Refresh all data to show empty state
                this.clearTableData();
                this.loadPendingRequests();
                this.loadTotalLeaves();
                this.loadMetrics();
            } else {
                this.showToast(data.error || 'Failed to clear leave data.', 'error');
            }
        })
        .catch(error => {
            console.error('Error clearing leave data:', error);
            this.showToast('An error occurred while clearing leave data. Please try again.', 'error');
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Clear Leave Data';
            }
        });
    }
}

// Global function for tab switching (called from HTML onclick)
function switchLeaveTab(tabName) {
    if (window.leaveManager) {
        window.leaveManager.switchLeaveTab(tabName);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize on leave_request.php page
    if (document.getElementById('pendingRequestsTable') || document.getElementById('totalLeavesTable')) {
        window.leaveManager = new LeaveRequestManager();
    } else {
        // Create minimal LeaveRequestManager for notification actions on other pages
        window.leaveManager = {
            currentAction: null,
            currentServiceNo: null,
            currentNotificationId: null,
            
            showActionModal: function(uniqueServiceNo, action, notificationId = null) {
                console.log('showActionModal called (minimal):', { uniqueServiceNo, action, notificationId });
                this.currentServiceNo = uniqueServiceNo;
                this.currentAction = action;
                this.currentNotificationId = notificationId;
                
                const modalElement = document.getElementById('confirmationModal');
                const message = document.getElementById('confirmationMessage');
                const noteInput = document.getElementById('actionNote');
                
                if (!modalElement) {
                    console.error('Modal element not found!');
                    return;
                }
                
                if (message) {
                    message.textContent = `Are you sure you want to ${action.toLowerCase()} this leave request?`;
                }
                if (noteInput) {
                    noteInput.value = '';
                }
                
                const modal = new bootstrap.Modal(modalElement, {
                    backdrop: true,
                    keyboard: true,
                    focus: true
                });
                modal.show();
                
                // Bind confirm action
                const confirmBtn = document.getElementById('confirmAction');
                if (confirmBtn) {
                    confirmBtn.onclick = () => this.executeAction();
                }
            },
            
            executeAction: function() {
                if (!this.currentServiceNo || !this.currentAction) {
                    alert('No action selected');
                    return;
                }
                
                const noteInput = document.getElementById('actionNote');
                const note = noteInput ? noteInput.value.trim() : '';
                
                const confirmBtn = document.getElementById('confirmAction');
                if (confirmBtn) {
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
                }
                
                const formData = new FormData();
                formData.append('unique_service_no', this.currentServiceNo);
                formData.append('action', this.currentAction);
                formData.append('note', note);
                
                fetch('../ajax/leave_status_action.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Mark notification as read if from notification
                        if (this.currentNotificationId) {
                            this.markNotificationAsRead(this.currentNotificationId);
                        }
                        
                        // Close modal
                        const modalElement = document.getElementById('confirmationModal');
                        if (modalElement) {
                            const modal = bootstrap.Modal.getInstance(modalElement);
                            if (modal) modal.hide();
                        }
                        
                        // Reload notifications
                        if (typeof window.NotificationManager !== 'undefined' && window.NotificationManager.loadNotifications) {
                            setTimeout(() => {
                                window.NotificationManager.loadNotifications();
                                window.NotificationManager.updateUnreadCount();
                            }, 500);
                        }
                    } else {
                        alert(data.error || 'Failed to save action');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to save action');
                })
                .finally(() => {
                    if (confirmBtn) {
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = 'Save';
                    }
                });
            },
            
            markNotificationAsRead: function(notificationId) {
                fetch('../ajax/notification_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mark_read',
                        notification_id: notificationId
                    }),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Notification marked as read');
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                });
            },
            
            closeModal: function() {
                const modalElement = document.getElementById('confirmationModal');
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) modal.hide();
                }
                this.currentAction = null;
                this.currentServiceNo = null;
                this.currentNotificationId = null;
            },
            
            showToast: function() {} // No-op for minimal version
        };
    }
});

// Add some CSS for status badges
const style = document.createElement('style');
style.textContent = `
    
    .reason-text {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .status-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-approved {
        background-color: #d1e7dd;
        color: #0f5132;
    }
    
    .status-pending {
        background-color: #fff3cd;
        color: #664d03;
    }
    
    .status-rejected {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .status-cancelled {
        background-color: #d3d3d3;
        color: #495057;
    }
    
    .action-buttons .btn {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    .empty-state {
        padding: 2rem;
    }
    
    .empty-state i {
        font-size: 3rem;
        color: #6c757d;
        margin-bottom: 1rem;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
        transition: background-color 0.15s ease-in-out;
    }
`;
document.head.appendChild(style);
