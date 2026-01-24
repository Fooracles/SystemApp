<?php
$page_title = "Logged-In Users";
require_once "../includes/header.php";

// Check if the user is logged in and is an admin
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

if(!isAdmin()) {
    header("location: " . (isManager() ? "manager_dashboard.php" : "doer_dashboard.php"));
    exit;
}
?>

<!-- Alpine.js and Lucide Icons -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
<script>
    // Load Lucide icons
    window.loadLucideIcons = function() {
        if (typeof window.lucide === 'undefined') {
            var script = document.createElement('script');
            script.src = 'https://unpkg.com/lucide@latest/dist/umd/lucide.js';
            script.async = true;
            script.onload = function() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            };
            document.head.appendChild(script);
        } else {
            lucide.createIcons();
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(window.loadLucideIcons, 100);
        });
    } else {
        setTimeout(window.loadLucideIcons, 100);
    }
</script>
<style>
/* Alpine.js cloak */
[x-cloak] { display: none !important; }

/* Fix overflow issues at 100% zoom */
.logged-in-page-container {
    max-width: 98%;
    margin: 0 auto;
    padding: 0 15px;
}

.logged-in-page-container .card {
    margin: 0;
    border: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

/* Header section - responsive */
.logged-in-page-container .card-header {
    flex-wrap: wrap;
    gap: 10px;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 0.5rem 0.5rem 0 0 !important;
}

.logged-in-page-container .card-header h4 {
    font-size: 1.25rem;
    white-space: nowrap;
    margin: 0;
    flex-shrink: 0;
    font-weight: 600;
}

.logged-in-page-container .card-header > div {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.logged-in-page-container .card-header .btn {
    white-space: nowrap;
    flex-shrink: 0;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
    color: white;
    transition: all 0.3s ease;
}

.logged-in-page-container .card-header .btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Modern Filter Section - Dark Theme */
.filter-section-modern {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 0.75rem;
    padding: 1.75rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    border: 1px solid rgba(102, 126, 234, 0.2);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.filter-section-modern.collapsed {
    max-height: 0;
    opacity: 0;
    padding: 0;
    margin: 0;
    overflow: hidden;
}

.filter-section-modern.expanded {
    max-height: 1000px;
    opacity: 1;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr) auto;
    gap: 1rem;
    margin-bottom: 0;
    align-items: end;
}

.filter-group {
    position: relative;
    max-width: 220px;
}

.filter-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.filter-group .input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.filter-group .input-wrapper i {
    position: absolute;
    left: 12px;
    color: #64748b;
    pointer-events: none;
    z-index: 1;
    width: 16px;
    height: 16px;
}

.filter-group input {
    width: 100%;
    padding: 0.5rem 0.625rem 0.5rem 3.25rem;
    border: 1.5px solid rgba(148, 163, 184, 0.2);
    border-radius: 0.5rem;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    background: rgba(15, 23, 42, 0.6);
    color: #e2e8f0;
    backdrop-filter: blur(10px);
    height: 38px;
    box-sizing: border-box;
}

.filter-group input::placeholder {
    color: #64748b;
}

.filter-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
    background: rgba(15, 23, 42, 0.8);
}

.filter-group input[type="date"] {
    padding-left: 3.25rem;
    padding-right: 0.625rem;
    cursor: pointer;
    color-scheme: dark;
    height: 38px;
}

.filter-group input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    position: absolute;
    right: 8px;
    width: auto;
    height: 100%;
    padding: 0 8px;
    opacity: 1;
    filter: invert(1);
}

.filter-group select.filter-select {
    width: 100%;
    padding: 0.5rem 0.625rem 0.5rem 3.25rem;
    border: 1.5px solid rgba(148, 163, 184, 0.2);
    border-radius: 0.5rem;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    background: rgba(15, 23, 42, 0.6);
    color: #e2e8f0;
    backdrop-filter: blur(10px);
    height: 38px;
    box-sizing: border-box;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.625rem center;
    padding-right: 2rem;
}

.filter-group select.filter-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
    background-color: rgba(15, 23, 42, 0.8);
    }

.filter-group select.filter-select option {
    background: #1e293b;
    color: #e2e8f0;
    padding: 0.5rem;
}

.filter-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.filter-toggle-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-toggle-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.filter-clear-wrapper {
    display: flex;
    align-items: flex-end;
    width: auto;
    min-width: fit-content;
    }

.filter-clear-btn {
    background: rgba(148, 163, 184, 0.2);
    color: #e2e8f0;
    border: 1.5px solid rgba(148, 163, 184, 0.3);
    padding: 0.5rem 0.875rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    height: 38px;
    box-sizing: border-box;
    white-space: nowrap;
}

.filter-clear-btn:hover {
    background: rgba(148, 163, 184, 0.3);
    border-color: rgba(148, 163, 184, 0.5);
    transform: translateY(-1px);
    color: #fff;
}

/* Session View Toggle in Header - Light Theme */
.session-view-toggle-header {
    display: flex;
    gap: 0.25rem;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.25rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
}

.session-view-option-header {
    padding: 0.375rem 0.875rem;
    border: none;
    background: transparent;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    color: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    white-space: nowrap;
    }

.session-view-option-header:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
}

.session-view-option-header.active {
    background: rgba(255, 255, 255, 0.25);
    color: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.session-view-option-header i {
    width: 16px;
    height: 16px;
}

/* Session View Toggle - Dark Theme (for filter section if needed) */
.session-view-toggle {
    display: flex;
    gap: 0.5rem;
    background: rgba(15, 23, 42, 0.6);
    padding: 0.25rem;
    border-radius: 0.5rem;
    border: 1.5px solid rgba(148, 163, 184, 0.2);
    backdrop-filter: blur(10px);
}

.session-view-option {
    flex: 1;
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.session-view-option:hover {
    color: #e2e8f0;
    background: rgba(148, 163, 184, 0.1);
}

.session-view-option.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }

/* Card body */
.logged-in-page-container .card-body {
    padding: 1.5rem;
}

/* Table responsive styling */
.logged-in-page-container .table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%;
    max-width: 100%;
}

.logged-in-page-container #sessionsTable {
    width: 100%;
    table-layout: fixed;
    font-size: 0.875rem;
    min-width: 800px;
}

.logged-in-page-container #sessionsTable thead th {
    font-size: 0.75rem;
    padding: 0.5rem 0.35rem;
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
    min-width: fit-content;
}

.logged-in-page-container #sessionsTable tbody td {
    padding: 0.5rem 0.35rem;
    font-size: 0.85rem;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

/* Device column - allow wrapping for long text */
.logged-in-page-container #sessionsTable tbody td:nth-child(5),
.logged-in-page-container #sessionsTable thead th:nth-child(5) {
    max-width: 200px;
    min-width: 120px;
    white-space: normal;
    word-break: break-word;
    overflow-wrap: break-word;
}

/* Login column - ensure two-line format doesn't overflow */
.logged-in-page-container #sessionsTable tbody td:nth-child(2) {
    white-space: normal;
    min-width: 120px;
}

/* IP column - prevent overflow */
.logged-in-page-container #sessionsTable tbody td:nth-child(4),
.logged-in-page-container #sessionsTable thead th:nth-child(4) {
    max-width: 120px;
    word-break: break-all;
}

/* User column */
.logged-in-page-container #sessionsTable tbody td:nth-child(1),
.logged-in-page-container #sessionsTable thead th:nth-child(1) {
    min-width: 100px;
    max-width: 150px;
}

/* Duration column */
.logged-in-page-container #sessionsTable tbody td:nth-child(3),
.logged-in-page-container #sessionsTable thead th:nth-child(3) {
    min-width: 80px;
    max-width: 100px;
}

/* Session History view - additional columns */
.logged-in-page-container #sessionsTable tbody td:nth-child(6),
.logged-in-page-container #sessionsTable thead th:nth-child(6) {
    min-width: 120px;
    max-width: 150px;
    white-space: normal;
}

.logged-in-page-container #sessionsTable tbody td:nth-child(7),
.logged-in-page-container #sessionsTable thead th:nth-child(7) {
    min-width: 100px;
    max-width: 130px;
}

/* Ensure table fits container */
.logged-in-page-container .table-responsive {
    max-width: 100%;
}

/* Filter inputs - prevent overflow */
.logged-in-page-container .form-control {
    font-size: 0.875rem;
    padding: 0.375rem 0.5rem;
}

/* Radio buttons section */
.logged-in-page-container .form-check-inline {
    margin-right: 1rem;
}

.logged-in-page-container .form-check-label {
    font-size: 0.875rem;
    white-space: nowrap;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .filter-grid {
        grid-template-columns: repeat(2, 1fr) auto;
    }
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-group {
        max-width: 100%;
    }
    
    .filter-clear-wrapper {
        width: 100%;
    }
    
    .filter-clear-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        width: 100%;
    }
    
    .session-view-toggle {
        width: 100%;
    }
    
    .filter-clear-btn {
        width: 100%;
        justify-content: center;
}

    .card-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 1rem;
    }
    
    .card-header > div:first-child {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
}

    .session-view-toggle-header {
        width: 100%;
    }
    
    .card-header > div:last-child {
        width: 100%;
        flex-wrap: wrap;
    }
}

/* Date input - ensure click anywhere opens picker */
.logged-in-page-container input[type="date"] {
    cursor: pointer;
    position: relative;
}

.logged-in-page-container input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    position: absolute;
    right: 0;
    width: auto;
    height: 100%;
    padding: 0 8px;
    opacity: 1;
}

.logged-in-page-container input[type="date"]::-webkit-inner-spin-button {
    display: none;
}
</style>

<div class="container-fluid logged-in-page-container" x-data="filterData()" x-init="initFilters()">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <h4 class="mb-0">
                            <i data-lucide="users" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px;"></i>
                            Sessions
                        </h4>
                        <!-- Session View Toggle in Header -->
                        <div class="session-view-toggle-header">
                            <button type="button" 
                                    class="session-view-option-header"
                                    :class="filters.view === 'active' ? 'active' : ''"
                                    x-on:click="filters.view = 'active'; syncViewInputs(); applyFilters()">
                                <i data-lucide="activity" style="width: 16px; height: 16px;"></i>
                                <span>Active</span>
                        </button>
                            <button type="button" 
                                    class="session-view-option-header"
                                    :class="filters.view === 'history' ? 'active' : ''"
                                    x-on:click="filters.view = 'history'; syncViewInputs(); applyFilters()">
                                <i data-lucide="history" style="width: 16px; height: 16px;"></i>
                                <span>History</span>
                        </button>
                    </div>
                </div>
                    <div>
                        <button class="btn btn-sm me-2" id="toggleFiltersBtn" x-on:click="filtersExpanded = !filtersExpanded" title="Toggle Filters">
                            <i data-lucide="filter" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i>
                            <span x-text="filtersExpanded ? 'Hide Filters' : 'Show Filters'">Show Filters</span>
                        </button>
                        <button class="btn btn-sm" onclick="exportSessions()">
                            <i data-lucide="download" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i>
                            Export CSV
                        </button>
                            </div>
                            </div>
                <div class="card-body" x-cloak>
                    <!-- Modern Filter Section -->
                    <div class="filter-section-modern" 
                         :class="filtersExpanded ? 'expanded' : 'collapsed'"
                         x-show="filtersExpanded"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 max-h-0"
                         x-transition:enter-end="opacity-100 max-h-screen"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 max-h-screen"
                         x-transition:leave-end="opacity-0 max-h-0">
                        
                        <!-- Filter Grid -->
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label>Username</label>
                                <div class="input-wrapper">
                                    <i data-lucide="user" style="width: 16px; height: 16px;"></i>
                                    <select id="filter_username" 
                                            x-model="filters.username"
                                            x-on:change="applyFilters()"
                                            class="filter-select">
                                        <option value="">All Users</option>
                                        <template x-for="user in users" :key="user.username">
                                            <option :value="user.username" x-text="user.name + ' (' + user.username + ')'"></option>
                                        </template>
                                    </select>
                            </div>
                            </div>
                            
                            <div class="filter-group">
                                <label>Date From</label>
                                <div class="input-wrapper">
                                    <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                    <input type="date" 
                                           id="filter_date_from" 
                                           x-model="filters.date_from"
                                           x-on:change="applyFilters()"
                                           onclick="openDatePicker(this)">
                            </div>
                            </div>
                            
                            <div class="filter-group">
                                <label>Date To</label>
                                <div class="input-wrapper">
                                    <i data-lucide="calendar" style="width: 16px; height: 16px;"></i>
                                    <input type="date" 
                                           id="filter_date_to" 
                                           x-model="filters.date_to"
                                           x-on:change="applyFilters()"
                                           onclick="openDatePicker(this)">
                            </div>
                        </div>
                        
                            <div class="filter-group">
                                <label>Device</label>
                                <div class="input-wrapper">
                                    <i data-lucide="smartphone" style="width: 16px; height: 16px;"></i>
                                    <input type="text" 
                                           id="filter_device" 
                                           x-model="filters.device"
                                           x-on:input.debounce.300ms="applyFilters()"
                                           placeholder="Search device...">
                            </div>
                            </div>
                            
                            <!-- Clear Filters Button in same row -->
                            <div class="filter-clear-wrapper">
                                <button class="filter-clear-btn" x-on:click="clearFilters()">
                                    <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                                    <span>Clear All</span>
                                </button>
                        </div>
                        </div>
                        
                        <!-- Hidden radio inputs for compatibility -->
                        <input type="radio" name="session_view" id="view_active" value="active" x-model="filters.view" style="display: none;">
                        <input type="radio" name="session_view" id="view_history" value="history" x-model="filters.view" style="display: none;">
                    </div>
                    
                    <!-- Sessions Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="sessionsTable">
                            <thead id="sessionsTableHead">
                                <tr>
                                    <th>User</th>
                                    <th>Login</th>
                                    <th>Duration</th>
                                    <th>IP</th>
                                    <th>Device</th>
                                </tr>
                            </thead>
                            <tbody id="sessionsTableBody">
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Loading sessions...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div id="pagination" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;

// Alpine.js filter data
function filterData() {
    return {
        filtersExpanded: false, // Start collapsed by default
        users: [],
        filters: {
            username: '',
            date_from: '',
            date_to: '',
            device: '',
            view: 'active'
        },
        initFilters() {
            // Load users for dropdown
            this.loadUsers();
            
            // Sync with existing filter inputs if they exist
            const usernameInput = document.getElementById('filter_username');
            const dateFromInput = document.getElementById('filter_date_from');
            const dateToInput = document.getElementById('filter_date_to');
            const deviceInput = document.getElementById('filter_device');
            const viewActive = document.getElementById('view_active');
            const viewHistory = document.getElementById('view_history');
            
            if (usernameInput) this.filters.username = usernameInput.value || '';
            if (dateFromInput) this.filters.date_from = dateFromInput.value || '';
            if (dateToInput) this.filters.date_to = dateToInput.value || '';
            if (deviceInput) this.filters.device = deviceInput.value || '';
            if (viewHistory && viewHistory.checked) {
                this.filters.view = 'history';
            } else {
                this.filters.view = 'active';
            }
            
            // Sync view inputs
            this.syncViewInputs();
            
            // Re-initialize Lucide icons after Alpine renders
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                } else {
                    setTimeout(() => {
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    }, 500);
                }
            });
        },
        syncViewInputs() {
            const viewActive = document.getElementById('view_active');
            const viewHistory = document.getElementById('view_history');
            if (viewActive) viewActive.checked = this.filters.view === 'active';
            if (viewHistory) viewHistory.checked = this.filters.view === 'history';
        },
        applyFilters() {
            // Sync Alpine data to DOM inputs for compatibility
            const usernameInput = document.getElementById('filter_username');
            const dateFromInput = document.getElementById('filter_date_from');
            const dateToInput = document.getElementById('filter_date_to');
            const deviceInput = document.getElementById('filter_device');
            
            if (usernameInput) usernameInput.value = this.filters.username;
            if (dateFromInput) dateFromInput.value = this.filters.date_from;
            if (dateToInput) dateToInput.value = this.filters.date_to;
            if (deviceInput) deviceInput.value = this.filters.device;
            
            // Sync view inputs
            this.syncViewInputs();
            
            // Call the existing loadSessions function
            loadSessions(1);
        },
        clearFilters() {
            this.filters = {
                username: '',
                date_from: '',
                date_to: '',
                device: '',
                view: 'active'
            };
            this.syncViewInputs();
            this.applyFilters();
            
            // Re-initialize icons after clearing
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        },
        loadUsers() {
            $.ajax({
                url: '../ajax/sessions_handler.php',
                method: 'POST',
                data: {
                    action: 'get_users'
                },
                dataType: 'json',
                success: (response) => {
                    if (response.success && response.users) {
                        this.users = response.users;
                    }
                },
                error: () => {
                    console.error('Error loading users');
                }
            });
        }
    };
}

// Load sessions on page load
$(document).ready(function() {
    loadSessions();
    
    // Auto-refresh every 30 seconds
    setInterval(function() {
        const viewActive = document.getElementById('view_active');
        if (viewActive && viewActive.checked) {
            loadSessions();
        }
    }, 30000);
    
    // Ensure date pickers open on focus as well (backup to onclick)
    $(document).on('focus', 'input[type="date"]', function() {
        if (this.showPicker && typeof this.showPicker === 'function') {
            try {
                this.showPicker();
            } catch (e) {
                // If showPicker() fails, just focus (already focused)
            }
        }
    });
    
    // Re-initialize Lucide icons periodically and after Alpine updates
    setInterval(function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }, 1000);
    
    // Initialize icons when Alpine is ready
    document.addEventListener('alpine:init', function() {
        setTimeout(function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }, 200);
    });
    
    // Also initialize after a short delay to catch any late renders
    setTimeout(function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
    }
    }, 500);
});

// Open date picker when clicking anywhere on the date field
function openDatePicker(input) {
    // For modern browsers that support showPicker() API
    if (input.showPicker && typeof input.showPicker === 'function') {
        try {
            input.showPicker();
            return;
        } catch (e) {
            // If showPicker() fails, fall through to focus
        }
    }
    
    // Fallback: Focus the input which should trigger the native picker
    input.focus();
    
    // For browsers that need explicit click on calendar icon
    // The CSS already makes the calendar icon cover the entire field
    // So clicking anywhere will trigger it
}

function loadSessions(page = 1) {
    currentPage = page;
    
    // Get view type from either Alpine.js or DOM
    let viewType = 'active';
    const viewActive = document.getElementById('view_active');
    const viewHistory = document.getElementById('view_history');
    if (viewHistory && viewHistory.checked) {
        viewType = 'history';
    } else if (viewActive && viewActive.checked) {
        viewType = 'active';
    } else {
        // Fallback to jQuery if Alpine hasn't initialized
        const checked = $('input[name="session_view"]:checked');
        if (checked.length) {
            viewType = checked.val();
        }
    }
    
    const isHistory = viewType === 'history';
    
    // Update table headers based on view type
    updateTableHeaders(isHistory);
    
    // Get filters from DOM inputs (compatible with both Alpine and vanilla JS)
    const filters = {
        username: document.getElementById('filter_username')?.value || '',
        date_from: document.getElementById('filter_date_from')?.value || '',
        date_to: document.getElementById('filter_date_to')?.value || '',
        device: document.getElementById('filter_device')?.value || '',
        view: viewType,
        page: page
    };
    
    const colSpan = isHistory ? 7 : 5;
    
    $.ajax({
        url: '../ajax/sessions_handler.php',
        method: 'POST',
        data: {
            action: 'get_sessions',
            ...filters
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displaySessions(response.sessions, isHistory);
                updatePagination(response.total_pages, response.current_page);
            } else {
                $('#sessionsTableBody').html('<tr><td colspan="' + colSpan + '" class="text-center text-danger">' + response.message + '</td></tr>');
            }
        },
        error: function() {
            $('#sessionsTableBody').html('<tr><td colspan="' + colSpan + '" class="text-center text-danger">Error loading sessions</td></tr>');
        }
    });
}

function updateTableHeaders(isHistory) {
    let headerHtml = '<tr>';
    headerHtml += '<th>User</th>';
    headerHtml += '<th>Login</th>';
    headerHtml += '<th>Duration</th>';
    headerHtml += '<th>IP</th>';
    headerHtml += '<th>Device</th>';
    
    if (isHistory) {
        headerHtml += '<th>Logout</th>';
        headerHtml += '<th>Status</th>';
    }
    
    headerHtml += '</tr>';
    $('#sessionsTableHead').html(headerHtml);
}

function displaySessions(sessions, isHistory = false) {
    const colSpan = isHistory ? 7 : 5;
    
    if (sessions.length === 0) {
        $('#sessionsTableBody').html('<tr><td colspan="' + colSpan + '" class="text-center">No sessions found</td></tr>');
        return;
    }
    
    let html = '';
    sessions.forEach(function(session) {
        const loginTimeFormatted = formatDateTimeWrapped(session.login_time);
        const duration = session.duration_seconds !== null ? formatDuration(session.duration_seconds) : 
                        (session.is_active ? calculateActiveDuration(session.login_time) : '-');
        
        html += '<tr>';
        html += '<td>' + escapeHtml(session.username) + '</td>';
        html += '<td>' + loginTimeFormatted + '</td>';
        html += '<td>' + duration + '</td>';
        html += '<td>' + escapeHtml(session.ip_address) + '</td>';
        html += '<td>' + escapeHtml(session.device_info || 'Unknown') + '</td>';
        
        // Only show Logout Time and Status columns in Session History view
        if (isHistory) {
            const logoutTimeFormatted = session.logout_time ? formatDateTimeWrapped(session.logout_time) : '-';
            html += '<td>' + logoutTimeFormatted + '</td>';
            html += '<td>';
            
            let logoutReason = session.logout_reason || 'manual';
            let badgeClass = 'badge-secondary';
            let badgeText = 'Logged Out';
            
            if (logoutReason === 'user_inactive') {
                badgeClass = 'badge-warning';
                badgeText = 'Auto (Inactive User)';
            } else if (logoutReason === 'auto') {
                badgeClass = 'badge-info';
                badgeText = 'Auto (Expired)';
            }
            
            html += '<span class="badge ' + badgeClass + '">' + badgeText + '</span>';
            html += '</td>';
        }
        
        html += '</tr>';
    });
    
    $('#sessionsTableBody').html(html);
}

// Format datetime as DD/MM/YYYY on first line and HH:MM:SS - AM/PM on second line
function formatDateTimeWrapped(dateTimeStr) {
    if (!dateTimeStr) return '-';
    
    const date = new Date(dateTimeStr);
    if (isNaN(date.getTime())) return '-';
    
    // Format date as DD/MM/YYYY
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const dateFormatted = day + '/' + month + '/' + year;
    
    // Format time as HH:MM:SS - AM/PM
    let hours = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 should be 12
    const hoursFormatted = String(hours).padStart(2, '0');
    const timeFormatted = hoursFormatted + ':' + minutes + ':' + seconds + ' ' + ampm;
    
    return '<div style="white-space: nowrap;">' + dateFormatted + '<br><small>' + timeFormatted + '</small></div>';
}

function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    
    if (hours > 0) {
        return hours + 'h ' + minutes + 'm';
    }
    return minutes + 'm';
}

function calculateActiveDuration(loginTime) {
    const login = new Date(loginTime);
    const now = new Date();
    const diff = Math.floor((now - login) / 1000);
    return formatDuration(diff);
}

function applyFilters() {
    loadSessions(1);
}

function clearFilters() {
    // Clear DOM inputs
    const usernameInput = document.getElementById('filter_username');
    const dateFromInput = document.getElementById('filter_date_from');
    const dateToInput = document.getElementById('filter_date_to');
    const deviceInput = document.getElementById('filter_device');
    const viewActive = document.getElementById('view_active');
    
    if (usernameInput) usernameInput.value = '';
    if (dateFromInput) dateFromInput.value = '';
    if (dateToInput) dateToInput.value = '';
    if (deviceInput) deviceInput.value = '';
    if (viewActive) viewActive.checked = true;
    
    // Also clear via jQuery for compatibility
    $('#filter_username').val('');
    $('#filter_date_from').val('');
    $('#filter_date_to').val('');
    $('#filter_device').val('');
    $('#view_active').prop('checked', true);
    
    loadSessions(1);
    
    // Re-initialize icons
    if (typeof lucide !== 'undefined') {
        setTimeout(() => lucide.createIcons(), 100);
    }
}

function refreshSessions() {
    loadSessions(currentPage);
}

function exportSessions() {
    // Get filters from DOM (compatible with Alpine.js)
    const usernameInput = document.getElementById('filter_username');
    const dateFromInput = document.getElementById('filter_date_from');
    const dateToInput = document.getElementById('filter_date_to');
    const deviceInput = document.getElementById('filter_device');
    const viewActive = document.getElementById('view_active');
    
    let viewType = 'active';
    if (viewActive && !viewActive.checked) {
        const viewHistory = document.getElementById('view_history');
        if (viewHistory && viewHistory.checked) {
            viewType = 'history';
        }
    }
    
    const filters = {
        username: usernameInput?.value || '',
        date_from: dateFromInput?.value || '',
        date_to: dateToInput?.value || '',
        device: deviceInput?.value || '',
        view: viewType
    };
    
    const params = new URLSearchParams({
        action: 'export_sessions',
        ...filters
    });
    
    window.location.href = '../ajax/sessions_handler.php?' + params.toString();
}

function updatePagination(total, current) {
    totalPages = total;
    currentPage = current;
    
    if (total <= 1) {
        $('#pagination').html('');
        return;
    }
    
    let html = '<nav><ul class="pagination justify-content-center">';
    
    // Previous button
    html += '<li class="page-item ' + (current <= 1 ? 'disabled' : '') + '">';
    html += '<a class="page-link" href="#" onclick="loadSessions(' + (current - 1) + '); return false;">Previous</a>';
    html += '</li>';
    
    // Page numbers
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= current - 2 && i <= current + 2)) {
            html += '<li class="page-item ' + (i === current ? 'active' : '') + '">';
            html += '<a class="page-link" href="#" onclick="loadSessions(' + i + '); return false;">' + i + '</a>';
            html += '</li>';
        } else if (i === current - 3 || i === current + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Next button
    html += '<li class="page-item ' + (current >= total ? 'disabled' : '') + '">';
    html += '<a class="page-link" href="#" onclick="loadSessions(' + (current + 1) + '); return false;">Next</a>';
    html += '</li>';
    
    html += '</ul></nav>';
    $('#pagination').html(html);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? text.toString().replace(/[&<>"']/g, m => map[m]) : '';
}

function showToast(message, type) {
    // Use existing toast system if available, otherwise use alert
    if (typeof toastr !== 'undefined') {
        toastr[type === 'success' ? 'success' : 'error'](message);
    } else {
        alert(message);
    }
}
</script>

<?php require_once "../includes/footer.php"; ?>


