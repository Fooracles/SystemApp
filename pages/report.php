<?php
$page_title = "Reports";
require_once "../includes/header.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Check if user is Admin, Manager or Client
if(!isAdmin() && !isManager() && !isClient()) {
    // Redirect to appropriate dashboard
    if (isDoer()) {
        header("location: doer_dashboard.php");
    } else {
        header("location: ../login.php");
    }
    exit;
}

// Get user data
$user_id = $_SESSION["id"] ?? null;
$username = htmlspecialchars($_SESSION["username"] ?? '');
$user_type = $_SESSION["user_type"] ?? '';
$is_admin = isAdmin();
$is_manager = isManager();
$is_client = isClient();
?>

<style>
/* Reports Page Styles */
.reports-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.reports-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.reports-header h1 {
    color: var(--dark-text-primary, #e2e8f0);
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.reports-header h1 i {
    color: #8b5cf6;
}

.btn-upload-report {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border: 1px solid rgba(139, 92, 246, 0.35);
    color: #fff;
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 0 18px rgba(139, 92, 246, 0.35), 0 2px 6px rgba(0, 0, 0, 0.4);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-upload-report:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 22px rgba(139, 92, 246, 0.5), 0 4px 10px rgba(0, 0, 0, 0.5);
    color: #fff;
}

.btn-upload-report:active {
    transform: translateY(0);
}

/* Reports Grid */
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.25rem;
    margin-top: 2rem;
}

/* Report Card */
.report-card {
    background: rgba(17, 24, 39, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.875rem;
    padding: 1rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(8px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.report-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.report-card:hover::before {
    opacity: 1;
}

.report-card:hover {
    border-color: rgba(139, 92, 246, 0.4);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    transform: translateY(-2px);
}

/* Card Header */
.report-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    gap: 0.75rem;
}

.report-file-icon {
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.625rem;
    background: rgba(139, 92, 246, 0.15);
    color: #8b5cf6;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.report-card-title {
    flex: 1;
    min-width: 0;
}

.report-card-title h3 {
    color: var(--dark-text-primary, #e2e8f0);
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    line-height: 1.4;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.report-card-title .file-type-badge {
    display: inline-block;
    padding: 0.2rem 0.4rem;
    border-radius: 0.375rem;
    font-size: 0.6875rem;
    font-weight: 600;
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
    margin-top: 0.375rem;
    text-transform: uppercase;
}

/* Card Body */
.report-card-body {
    margin-bottom: 0.875rem;
}

.report-meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8125rem;
}

.report-meta-item:last-child {
    margin-bottom: 0;
}

.report-meta-item i {
    color: #a78bfa;
    width: 1rem;
    font-size: 0.875rem;
}

.report-meta-item strong {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

/* Card Footer */
.report-card-footer {
    display: flex;
    gap: 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-report-action {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.8125rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
}

.btn-view {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
    border: 1px solid rgba(139, 92, 246, 0.3);
}

.btn-view:hover {
    background: rgba(139, 92, 246, 0.25);
    color: #c4b5fd;
    border-color: rgba(139, 92, 246, 0.5);
    transform: translateY(-1px);
}

.btn-download {
    background: rgba(139, 92, 246, 0.2);
    color: #8b5cf6;
    border: 1px solid rgba(139, 92, 246, 0.4);
}

.btn-download:hover {
    background: rgba(139, 92, 246, 0.3);
    color: #a78bfa;
    border-color: rgba(139, 92, 246, 0.6);
    transform: translateY(-1px);
}

.btn-edit {
    background: rgba(255, 193, 7, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.btn-edit:hover {
    background: rgba(255, 193, 7, 0.25);
    color: #fcd34d;
    border-color: rgba(255, 193, 7, 0.5);
    transform: translateY(-1px);
}

.btn-delete {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.btn-delete:hover {
    background: rgba(239, 68, 68, 0.25);
    color: #fca5a5;
    border-color: rgba(239, 68, 68, 0.5);
    transform: translateY(-1px);
}

/* Empty State */
.reports-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: rgba(255, 255, 255, 0.6);
}

.reports-empty i {
    font-size: 4rem;
    color: rgba(255, 255, 255, 0.3);
    margin-bottom: 1rem;
    display: block;
}

.reports-empty h3 {
    color: var(--dark-text-primary, #e2e8f0);
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.reports-empty p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
}

/* Upload Modal */
.upload-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.upload-modal.active {
    display: flex;
}

.upload-modal-content {
    background: rgba(17, 24, 39, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    padding: 1.25rem;
    width: 100%;
    max-width: 480px;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.upload-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.upload-modal-header h2 {
    color: var(--dark-text-primary, #e2e8f0);
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
}

.btn-close-modal {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.btn-close-modal:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    color: var(--dark-text-primary, #e2e8f0);
    font-weight: 600;
    margin-bottom: 0.375rem;
    font-size: 0.875rem;
}

.form-label .required {
    color: #ef4444;
    margin-left: 0.25rem;
}

.form-control {
    width: 100%;
    padding: 0.625rem;
    background: rgba(6, 8, 14, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    color: var(--dark-text-primary, #e2e8f0);
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: rgba(139, 92, 246, 0.5);
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    background: rgba(6, 8, 14, 0.95);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.file-upload-area {
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 0.5rem;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: rgba(6, 8, 14, 0.5);
}

.file-upload-area:hover {
    border-color: rgba(139, 92, 246, 0.5);
    background: rgba(6, 8, 14, 0.7);
}

.file-upload-area.dragover {
    border-color: #8b5cf6;
    background: rgba(139, 92, 246, 0.1);
}

.file-upload-area i {
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.4);
    margin-bottom: 0.5rem;
    display: block;
}

.file-upload-area p {
    color: rgba(255, 255, 255, 0.7);
    margin: 0.25rem 0;
    font-size: 0.8125rem;
    line-height: 1.4;
}

.file-upload-area .file-info {
    margin-top: 0.75rem;
    padding: 0.5rem;
    background: rgba(139, 92, 246, 0.1);
    border-radius: 0.375rem;
    color: #a78bfa;
    font-size: 0.8125rem;
    display: none;
}

.file-upload-area.has-file .file-info {
    display: block;
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-submit {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border: 1px solid rgba(139, 92, 246, 0.35);
    color: #fff;
    padding: 0.625rem 1.25rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--dark-text-primary, #e2e8f0);
    padding: 0.625rem 1.25rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* Loading State */
.loading {
    text-align: center;
    padding: 3rem;
    color: rgba(255, 255, 255, 0.6);
}

.loading p {
    margin: 0;
    font-size: 1rem;
}

/* Responsive */
@media (max-width: 768px) {
    .reports-container {
        padding: 1rem;
    }
    
    .reports-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .reports-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .upload-modal-content {
        padding: 1.5rem;
    }
}
</style>

<div class="reports-container">
    <div class="reports-header">
        <h1>Reports</h1>
        <?php if ($is_admin || $is_manager): ?>
            <button class="btn-upload-report" onclick="openUploadModal()">
                <i class="fas fa-upload"></i> Upload Report
            </button>
        <?php endif; ?>
    </div>

    <div id="reportsGrid" class="reports-grid">
        <div class="loading">
            <p>Loading reports...</p>
        </div>
    </div>
</div>

<!-- Upload Modal (Admin and Manager Only) -->
<?php if ($is_admin || $is_manager): ?>
<div id="uploadModal" class="upload-modal" onclick="closeModalOnBackdrop(event)">
    <div class="upload-modal-content" onclick="event.stopPropagation()">
        <div class="upload-modal-header">
            <h2 id="modalTitle">Upload Report</h2>
            <button class="btn-close-modal" onclick="closeUploadModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="uploadReportForm" enctype="multipart/form-data">
            <input type="hidden" id="reportId" name="report_id">
            
            <!-- Client Account and Users Selection (Only for Managers/Admins) -->
            <div class="form-group">
                <label class="form-label" for="clientAccount">
                    Client Account <span class="required">*</span>
                </label>
                <select class="form-control" id="clientAccount" name="client_account_id" required>
                    <option value="">Select Client Account</option>
                </select>
            </div>

            <div class="form-group" id="clientUsersGroup" style="display: none;">
                <label class="form-label">
                    Client Users <span class="required">*</span>
                </label>
                <div id="clientUsersContainer" style="max-height: 200px; overflow-y: auto; background: rgba(6, 8, 14, 0.5); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 0.5rem; padding: 0.75rem;">
                    <!-- Client users checkboxes will be populated here -->
                </div>
                <p id="clientUsersMessage" style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.5); margin-top: 0.5rem; display: none;">
                    Please select at least one client user
                </p>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="reportTitle">
                    Report Title <span class="required">*</span>
                </label>
                <input type="text" class="form-control" id="reportTitle" name="title" required placeholder="e.g., Monthly Progress Report - January 2025">
            </div>

            <div class="form-group">
                <label class="form-label" for="projectName">
                    Project Name <span class="required">*</span>
                </label>
                <input type="text" class="form-control" id="projectName" name="project_name" required placeholder="e.g., Project Alpha">
            </div>

            <div class="form-group">
                <label class="form-label" for="reportFile">
                    Report File <span class="required">*</span>
                </label>
                <div class="file-upload-area" id="fileUploadArea" onclick="document.getElementById('reportFile').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to upload or drag and drop</p>
                    <p style="font-size: 0.85rem; margin-top: 0.5rem; color: rgba(255,255,255,0.5);">
                        PDF, PPT, PPTX, DOC, DOCX, TXT (Max 50MB)
                    </p>
                    <div class="file-info" id="fileInfo"></div>
                </div>
                <input type="file" id="reportFile" name="file" accept=".pdf,.ppt,.pptx,.doc,.docx,.txt" style="display: none;" required>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-upload"></i> Upload
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Load reports
function loadReports() {
    const grid = document.getElementById('reportsGrid');
    grid.innerHTML = '<div class="loading"><p>Loading reports...</p></div>';
    
    fetch('../ajax/reports_handler.php?action=get_reports')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayReports(data.reports);
            } else {
                grid.innerHTML = `<div class="reports-empty"><i class="fas fa-file-alt"></i><h3>No Reports</h3><p>${data.message || 'No reports available yet.'}</p></div>`;
            }
        })
        .catch(error => {
            grid.innerHTML = '<div class="reports-empty"><i class="fas fa-exclamation-triangle"></i><h3>Error</h3><p>Failed to load reports. Please try again.</p></div>';
        });
}

// Display reports
function displayReports(reports) {
    const grid = document.getElementById('reportsGrid');
    
    if (!reports || reports.length === 0) {
        grid.innerHTML = '<div class="reports-empty"><i class="fas fa-file-alt"></i><h3>No Reports</h3><p>No reports available yet.</p></div>';
        return;
    }
    
    grid.innerHTML = reports.map(report => {
        const fileIcon = getFileIcon(report.file_type);
        const fileType = getFileTypeName(report.file_type);
        const uploadDate = formatDate(report.uploaded_at);
        
        return `
            <div class="report-card" data-report-id="${report.id}">
                <div class="report-card-header">
                    <div class="report-file-icon">
                        <i class="${fileIcon}"></i>
                    </div>
                    <div class="report-card-title">
                        <h3>${escapeHtml(report.title)}</h3>
                        <span class="file-type-badge">${fileType}</span>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="report-meta-item">
                        <i class="fas fa-folder"></i>
                        <span><strong>Project:</strong> ${escapeHtml(report.project_name)}</span>
                    </div>
                    <div class="report-meta-item">
                        <i class="fas fa-user"></i>
                        <span><strong>Uploaded by:</strong> ${escapeHtml(report.uploaded_by_name)}</span>
                    </div>
                    <div class="report-meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><strong>Date:</strong> ${uploadDate}</span>
                    </div>
                </div>
                <div class="report-card-footer">
                    <button class="btn-report-action btn-view" onclick="viewReport(${report.id}, '${escapeHtml(report.file_path)}', '${escapeHtml(report.file_type)}')">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn-report-action btn-download" onclick="downloadReport(${report.id}, '${escapeHtml(report.file_path)}')">
                        <i class="fas fa-download"></i> Download
                    </button>
                    ${<?php echo ($is_admin || $is_manager) ? 'true' : 'false'; ?> ? `
                        <button class="btn-report-action btn-edit" onclick="editReport(${report.id})" title="Edit Report">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-report-action btn-delete" onclick="deleteReport(${report.id}, '${escapeHtml(report.title)}')" title="Delete Report">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
}

// Get file icon based on file type
function getFileIcon(fileType) {
    if (!fileType) return 'fas fa-file';
    const type = fileType.toLowerCase();
    if (type.includes('pdf')) return 'fas fa-file-pdf';
    if (type.includes('powerpoint') || type.includes('presentation') || type.includes('ppt')) return 'fas fa-file-powerpoint';
    if (type.includes('word') || type.includes('document') || type.includes('doc')) return 'fas fa-file-word';
    if (type.includes('text') || type.includes('txt')) return 'fas fa-file-alt';
    return 'fas fa-file';
}

// Get file type name
function getFileTypeName(fileType) {
    if (!fileType) return 'File';
    const type = fileType.toLowerCase();
    if (type.includes('pdf')) return 'PDF';
    if (type.includes('ppt')) return 'PPT';
    if (type.includes('doc')) return 'DOC';
    if (type.includes('txt')) return 'TXT';
    return fileType.split('/').pop().toUpperCase();
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const ampm = date.getHours() >= 12 ? 'PM' : 'AM';
    const hours12 = date.getHours() % 12 || 12;
    return `${day}/${month}/${year} ${String(hours12).padStart(2, '0')}:${minutes} ${ampm}`;
}

// View report
function viewReport(reportId, filePath, fileType) {
    const type = fileType.toLowerCase();
    if (type.includes('pdf') || type.includes('text') || type.includes('txt')) {
        // Open in new tab for PDF and text files
        window.open(`../ajax/reports_handler.php?action=view&id=${reportId}`, '_blank');
    } else {
        // For other file types, download instead
        downloadReport(reportId, filePath);
    }
}

// Download report
function downloadReport(reportId, filePath) {
    window.location.href = `../ajax/reports_handler.php?action=download&id=${reportId}`;
}

// Delete report (Admin/Manager only)
function deleteReport(reportId, reportTitle) {
    if (!confirm(`Are you sure you want to delete the report "${reportTitle}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch('../ajax/reports_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_report&report_id=${reportId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Report deleted successfully!');
            loadReports();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete report'));
        }
    })
    .catch(error => {
        alert('Error deleting report. Please try again.');
    });
}

// Edit report (Manager only)
function editReport(reportId) {
    fetch(`../ajax/reports_handler.php?action=get_report&id=${reportId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const report = data.report;
                document.getElementById('reportId').value = report.id;
                document.getElementById('reportTitle').value = report.title;
                document.getElementById('projectName').value = report.project_name;
                document.getElementById('modalTitle').textContent = 'Edit Report';
                document.getElementById('reportFile').removeAttribute('required');
                
                // Load client account and users if available
                if (report.client_account_id) {
                    fetchClientAccounts().then(() => {
                        document.getElementById('clientAccount').value = report.client_account_id;
                        if (report.assigned_to) {
                            fetchClientUsers(report.client_account_id).then(() => {
                                // Select the assigned user
                                const checkbox = document.querySelector(`input[name="client_user_ids[]"][value="${report.assigned_to}"]`);
                                if (checkbox) {
                                    checkbox.checked = true;
                                }
                            });
                        }
                    });
                }
                
                openUploadModal();
            } else {
                alert('Error loading report: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error loading report. Please try again.');
        });
}

// Upload modal functions
function openUploadModal() {
    document.getElementById('uploadModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    // Fetch client accounts when modal opens
    fetchClientAccounts();
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('active');
    document.getElementById('uploadReportForm').reset();
    document.getElementById('reportId').value = '';
    document.getElementById('modalTitle').textContent = 'Upload Report';
    document.getElementById('reportFile').setAttribute('required', 'required');
    document.getElementById('fileUploadArea').classList.remove('has-file');
    document.getElementById('fileInfo').textContent = '';
    // Reset client account and users
    document.getElementById('clientAccount').value = '';
    document.getElementById('clientUsersGroup').style.display = 'none';
    document.getElementById('clientUsersContainer').innerHTML = '';
    document.getElementById('clientUsersMessage').style.display = 'none';
    document.body.style.overflow = '';
}

function closeModalOnBackdrop(event) {
    if (event.target.id === 'uploadModal') {
        closeUploadModal();
    }
}

// File upload handling
document.getElementById('reportFile')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const area = document.getElementById('fileUploadArea');
        const info = document.getElementById('fileInfo');
        area.classList.add('has-file');
        info.innerHTML = `<i class="fas fa-file"></i> ${escapeHtml(file.name)} (${formatFileSize(file.size)})`;
    }
});

// Drag and drop
const fileUploadArea = document.getElementById('fileUploadArea');
if (fileUploadArea) {
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('reportFile').files = files;
            const file = files[0];
            const info = document.getElementById('fileInfo');
            this.classList.add('has-file');
            info.innerHTML = `<i class="fas fa-file"></i> ${escapeHtml(file.name)} (${formatFileSize(file.size)})`;
        }
    });
}

// Form submission
// Prevent duplicate event listener attachment
let uploadFormHandlerAttached = false;

function attachUploadFormHandler() {
    if (uploadFormHandlerAttached) return;
    
    const form = document.getElementById('uploadReportForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Prevent double submission
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn.disabled) {
            return;
        }
        
        // Validate client account and users selection
        const clientAccountId = document.getElementById('clientAccount').value;
        const selectedUsers = Array.from(document.querySelectorAll('input[name="client_user_ids[]"]:checked')).map(cb => parseInt(cb.value));
        
        // Remove duplicates
        const uniqueUsers = [...new Set(selectedUsers.filter(id => id > 0))];
        
        if (!clientAccountId) {
            alert('Please select a client account');
            return;
        }
        
        if (uniqueUsers.length === 0) {
            alert('Please select at least one client user');
            document.getElementById('clientUsersMessage').style.display = 'block';
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', document.getElementById('reportId').value ? 'update_report' : 'upload_report');
        formData.append('client_account_id', clientAccountId);
        formData.append('client_user_ids', JSON.stringify(uniqueUsers));
        
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Uploading...';
        
        fetch('../ajax/reports_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Report uploaded successfully!');
                closeUploadModal();
                loadReports();
            } else {
                alert('Error: ' + (data.message || 'Failed to upload report'));
            }
        })
        .catch(error => {
            alert('Error uploading report. Please try again.');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
    
    uploadFormHandlerAttached = true;
}

// Attach handler when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachUploadFormHandler);
} else {
    attachUploadFormHandler();
}

// Fetch client accounts
function fetchClientAccounts() {
    return fetch('../ajax/updates_handler.php?action=get_client_accounts')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('clientAccount');
                select.innerHTML = '<option value="">Select Client Account</option>';
                data.client_accounts.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = account.name;
                    select.appendChild(option);
                });
                
                // Remove existing event listeners by cloning
                const newSelect = select.cloneNode(true);
                select.parentNode.replaceChild(newSelect, select);
                
                // Add change event listener to new select
                document.getElementById('clientAccount').addEventListener('change', function() {
                    const accountId = this.value;
                    if (accountId) {
                        fetchClientUsers(accountId);
                    } else {
                        document.getElementById('clientUsersGroup').style.display = 'none';
                        document.getElementById('clientUsersContainer').innerHTML = '';
                    }
                });
            } else {
            }
        })
        .catch(error => {
        });
}

// Fetch client users
function fetchClientUsers(clientAccountId) {
    return fetch(`../ajax/updates_handler.php?action=get_client_users&client_account_id=${clientAccountId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('clientUsersContainer');
                const message = document.getElementById('clientUsersMessage');
                container.innerHTML = '';
                
                if (data.client_users && data.client_users.length > 0) {
                    data.client_users.forEach(user => {
                        const label = document.createElement('label');
                        label.style.cssText = 'display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 0.375rem; transition: background 0.2s;';
                        label.onmouseover = function() { this.style.background = 'rgba(255, 255, 255, 0.05)'; };
                        label.onmouseout = function() { this.style.background = 'transparent'; };
                        
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.name = 'client_user_ids[]';
                        checkbox.value = user.id;
                        checkbox.style.cssText = 'width: 1rem; height: 1rem; cursor: pointer;';
                        
                        const span = document.createElement('span');
                        span.textContent = user.name;
                        span.style.cssText = 'color: rgba(255, 255, 255, 0.9); font-size: 0.875rem;';
                        
                        label.appendChild(checkbox);
                        label.appendChild(span);
                        container.appendChild(label);
                    });
                    document.getElementById('clientUsersGroup').style.display = 'block';
                    message.style.display = 'none';
                } else {
                    container.innerHTML = '<p style="color: rgba(255, 255, 255, 0.5); font-size: 0.875rem; font-style: italic;">No users found for this account</p>';
                    document.getElementById('clientUsersGroup').style.display = 'block';
                    message.style.display = 'none';
                }
            } else {
                document.getElementById('clientUsersGroup').style.display = 'none';
            }
        })
        .catch(error => {
            document.getElementById('clientUsersGroup').style.display = 'none';
        });
}

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUploadModal();
    }
});

// Load reports on page load
document.addEventListener('DOMContentLoaded', function() {
    loadReports();
});
</script>

<?php require_once "../includes/footer.php";
?>
