<?php
$page_title = "Useful URLs";
require_once "../includes/header.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}
?>

<style>
/* ========================================
   FMS BRAND THEME INTEGRATION
   ======================================== */
:root {
    /* FMS Brand Colors */
    --fms-primary: #2f3c7e;
    --fms-secondary: #b85042;
    --fms-accent: #898d91;
    --fms-light: #fff2d7;
    --fms-dark: #101820;
    
    /* Color Variations */
    --primary-50: rgba(47, 60, 126, 0.05);
    --primary-100: rgba(47, 60, 126, 0.1);
    --primary-200: rgba(47, 60, 126, 0.2);
    --primary-300: rgba(47, 60, 126, 0.3);
    --primary-500: #2f3c7e;
    --primary-600: #253066;
    --primary-700: #1e254f;
    --primary-800: #171b38;
    --primary-900: #101820;
    
    --secondary-50: rgba(184, 80, 66, 0.05);
    --secondary-100: rgba(184, 80, 66, 0.1);
    --secondary-200: rgba(184, 80, 66, 0.2);
    --secondary-300: rgba(184, 80, 66, 0.3);
    --secondary-500: #b85042;
    --secondary-600: #a6453a;
    --secondary-700: #943a32;
    --secondary-800: #822f2a;
    --secondary-900: #702422;
    
    /* Glassmorphism Variables */
    --glass-bg: rgba(255, 255, 255, 0.1);
    --glass-border: rgba(255, 255, 255, 0.2);
    --glass-shadow: 0 8px 32px rgba(47, 60, 126, 0.1);
    --glass-blur: blur(10px);
    
    /* Semantic Colors */
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --info: #17a2b8;
    
    /* Spacing Scale */
    --space-xs: 0.25rem;
    --space-sm: 0.5rem;
    --space-md: 1rem;
    --space-lg: 1.5rem;
    --space-xl: 2rem;
    --space-2xl: 3rem;
    
    /* Typography Scale */
    --text-xs: 0.75rem;
    --text-sm: 0.875rem;
    --text-base: 1rem;
    --text-lg: 1.125rem;
    --text-xl: 1.25rem;
    --text-2xl: 1.5rem;
    --text-3xl: 1.875rem;
    --text-4xl: 2.25rem;
}

.urls-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: var(--space-xl);
    background: linear-gradient(135deg, var(--primary-50) 0%, var(--secondary-50) 100%);
    min-height: 100vh;
}

.urls-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-2xl);
    flex-wrap: wrap;
    gap: var(--space-lg);
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: var(--space-xl);
    box-shadow: var(--glass-shadow);
}

.urls-header h2 {
    color: #ffffff;
    font-size: var(--text-2xl);
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.urls-header h2 i {
    color: var(--fms-secondary);
}

.urls-filters {
    display: flex;
    gap: var(--space-sm);
    align-items: center;
    flex-wrap: nowrap;
}

.search-box {
    position: relative;
    min-width: 180px;
    flex: 1;
    max-width: 200px;
}

.search-box input {
    width: 100%;
    padding: 8px 40px 8px 12px;
    border: 2px solid var(--primary-200);
    border-radius: 20px;
    font-size: 13px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: var(--glass-blur);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: var(--fms-dark);
}

.search-box input:focus {
    outline: none;
    border-color: var(--fms-primary);
    box-shadow: 0 0 0 3px var(--primary-100);
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-1px);
}

.search-box input::placeholder {
    color: var(--fms-accent);
    font-weight: 500;
}

.search-box i {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--fms-primary);
    font-size: 14px;
}

.filter-select {
    padding: 8px 16px;
    border: 2px solid var(--primary-200);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: var(--glass-blur);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: var(--fms-dark);
    word-wrap: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
}

.filter-select:focus {
    outline: none;
    border-color: var(--fms-primary);
    box-shadow: 0 0 0 3px var(--primary-100);
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-1px);
}

.btn-add-url {
    background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    position: relative;
    overflow: hidden;
    white-space: nowrap;
}

.btn-add-url::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn-add-url:hover::before {
    left: 100%;
}

.btn-add-url:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

.btn-add-url:active {
    transform: translateY(-1px);
}

.tabs-container {
    margin-bottom: var(--space-2xl);
}

.tabs {
    display: flex;
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: var(--space-sm);
    margin-bottom: var(--space-xl);
    box-shadow: var(--glass-shadow);
}

.tab {
    flex: 1;
    padding: var(--space-md) var(--space-xl);
    text-align: center;
    cursor: pointer;
    border-radius: 15px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 600;
    color: var(--fms-accent);
    font-size: var(--text-base);
    position: relative;
    overflow: hidden;
}

.tab::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s;
}

.tab:hover::before {
    left: 100%;
}

.tab:hover {
    color: var(--fms-primary);
    transform: translateY(-1px);
}

.tab.active {
    background: rgba(255, 255, 255, 0.9);
    color: var(--fms-primary);
    box-shadow: 0 4px 15px rgba(47, 60, 126, 0.2);
    transform: translateY(-2px);
}

.tab i {
    margin-right: var(--space-sm);
}

.tab-content {
    display: none;
    animation: fadeIn 0.3s ease;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.urls-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-md);
    margin-bottom: var(--space-2xl);
}

.url-card {
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: var(--space-md);
    box-shadow: var(--glass-shadow);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    cursor: pointer;
    overflow: hidden;
    border-left: 3px solid var(--fms-primary);
    user-select: none;
}

.url-card::after {
    content: '↗';
    position: absolute;
    top: var(--space-sm);
    right: var(--space-sm);
    color: var(--fms-accent);
    font-size: var(--text-sm);
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.url-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(47, 60, 126, 0.1);
    border-color: var(--primary-300);
    background: rgba(255, 255, 255, 0.15);
}

.url-card:hover::after {
    opacity: 1;
}

.url-card:active {
    transform: translateY(0);
    box-shadow: 0 4px 15px rgba(47, 60, 126, 0.1);
}

.url-card.personal {
    border-left-color: var(--fms-primary);
}

.url-card.admin {
    border-left-color: var(--success);
}

.url-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-sm);
    gap: var(--space-sm);
}

.url-title {
    font-size: var(--text-base);
    font-weight: 600;
    color: #ffffff;
    margin: 0;
    line-height: 1.4;
    flex: 1;
    transition: color 0.3s ease;
}

.url-card:hover .url-title {
    color: #e0e0e0;
}

.url-actions {
    display: flex;
    gap: var(--space-xs);
    align-items: center;
    opacity: 0;
    transform: translateX(5px);
    transition: all 0.3s ease;
}

.url-card:hover .url-actions {
    opacity: 1;
    transform: translateX(0);
}

.url-action-btn {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    padding: var(--space-xs);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: var(--fms-accent);
    font-size: var(--text-xs);
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.url-action-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    color: var(--fms-primary);
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(47, 60, 126, 0.2);
}

.url-action-btn.edit {
    color: var(--warning);
}

.url-action-btn.edit:hover {
    color: #ff8c00;
    background: rgba(255, 193, 7, 0.1);
}

.url-action-btn.delete {
    color: var(--danger);
}

.url-action-btn.delete:hover {
    color: #c82333;
    background: rgba(220, 53, 69, 0.1);
}

.url-action-btn.share {
    color: var(--info);
}

.url-action-btn.share:hover {
    color: #17a2b8;
    background: rgba(23, 162, 184, 0.1);
}

.url-link {
    color: #b3d9ff;
    text-decoration: none;
    font-weight: 500;
    word-break: break-all;
    display: block;
    margin-bottom: var(--space-sm);
    font-size: var(--text-xs);
    padding: var(--space-xs) var(--space-sm);
    background: rgba(47, 60, 126, 0.08);
    border-radius: 6px;
    border: 1px solid var(--primary-200);
    pointer-events: none;
}

.url-card:hover .url-link {
    color: #ffffff;
    background: rgba(47, 60, 126, 0.12);
}

.url-description {
    color: #e0e0e0;
    line-height: 1.5;
    margin-bottom: var(--space-sm);
    font-size: var(--text-xs);
    font-weight: 400;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.url-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: var(--text-xs);
    color: #d0d0d0;
    margin-top: var(--space-sm);
    padding-top: var(--space-sm);
    border-top: 1px solid var(--glass-border);
    font-weight: 500;
}

.url-category {
    background: linear-gradient(135deg, var(--fms-accent), #6c757d);
    color: white;
    padding: 2px var(--space-xs);
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    box-shadow: 0 1px 4px rgba(137, 141, 145, 0.2);
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.url-type {
    background: linear-gradient(135deg, var(--fms-primary), #007bff);
    color: white;
    padding: 2px var(--space-xs);
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    box-shadow: 0 1px 4px rgba(47, 60, 126, 0.2);
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.url-type.admin {
    background: linear-gradient(135deg, var(--success), #20c997);
    box-shadow: 0 1px 4px rgba(40, 167, 69, 0.2);
}

.url-date {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    color: #d0d0d0;
}

.url-date i {
    color: var(--fms-primary);
}

/* URL Form Section */
.url-form-section {
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: var(--space-xl);
    margin-bottom: var(--space-2xl);
    box-shadow: var(--glass-shadow);
    animation: slideInDown 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 10;
    border-left: 4px solid var(--success);
}

.url-form-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--success), #20c997);
    border-radius: 20px 20px 0 0;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-container {
    max-width: 100%;
    margin: 0;
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-lg);
    padding-bottom: var(--space-md);
    border-bottom: 2px solid var(--glass-border);
}

.form-header h3 {
    color: #ffffff;
    margin: 0;
    font-weight: 700;
    font-size: var(--text-xl);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.form-header h3 i {
    color: var(--fms-secondary);
}

.form-group {
    margin-bottom: 0;
}

.form-label {
    font-weight: 600;
    color:var(--fms-light);
    margin-bottom: var(--space-xs);
    display: block;
    font-size: var(--text-sm);
}

.form-control {
    border: 2px solid var(--primary-200);
    border-radius: 10px;
    padding: var(--space-sm) var(--space-md);
    font-size: var(--text-sm);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: var(--glass-blur);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: #000000 !important;
    font-weight: 500;
}

/* Select dropdown styling - white text for Category and Visible For */
select.form-control {
    color: #ffffff !important;
    background: rgba(30, 30, 30, 0.9) !important;
    border: 2px solid var(--primary-200) !important;
    word-wrap: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
}

select.form-control option {
    color: #ffffff !important;
    background: rgba(30, 30, 30, 0.95) !important;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
}

select.form-control:focus {
    color: #ffffff !important;
    background: rgba(30, 30, 30, 0.9) !important;
    border-color: var(--fms-primary) !important;
}

/* Textarea styling - white text for Description field to match dark theme */
textarea.form-control {
    color: #ffffff !important;
    background: rgba(30, 30, 30, 0.9) !important;
    border: 2px solid var(--primary-200) !important;
}

textarea.form-control:focus {
    color: #ffffff !important;
    background: rgba(30, 30, 30, 0.9) !important;
    border-color: var(--fms-primary) !important;
}

textarea.form-control::placeholder {
    color: var(--fms-accent) !important;
    opacity: 0.7;
}

.form-control:focus {
    outline: none;
    border-color: var(--fms-primary);
    box-shadow: 0 0 0 3px var(--primary-100);
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-1px);
    color: #000000 !important;
}

.form-control::placeholder {
    color: var(--fms-accent);
    font-weight: 400;
}

/* Form alignment */
.row.align-items-end {
    align-items: flex-end;
}

/* Button styling for compact form */
.btn-block {
    width: 100%;
    padding: var(--space-sm) var(--space-md);
    font-size: var(--text-sm);
    border-radius: 8px;
}

/* Responsive form layout */
@media (max-width: 768px) {
    .row.align-items-end .col-md-3,
    .row.align-items-end .col-md-2 {
        margin-bottom: 15px;
    }
    
    .btn-block {
        width: 100%;
    }
}

.btn-primary {
    background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
    border: none;
    padding: var(--space-md) var(--space-xl);
    border-radius: 50px;
    font-weight: 600;
    font-size: var(--text-base);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
    position: relative;
    overflow: hidden;
}

.btn-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn-primary:hover::before {
    left: 100%;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(40, 167, 69, 0.4);
}

.btn-secondary {
    background: var(--fms-accent);
    border: none;
    padding: var(--space-md) var(--space-xl);
    border-radius: 50px;
    font-weight: 600;
    font-size: var(--text-base);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(137, 141, 145, 0.3);
}

.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(137, 141, 145, 0.4);
    background: var(--accent-600);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #c82333 100%);
    border: none;
    padding: var(--space-md) var(--space-xl);
    border-radius: 50px;
    font-weight: 600;
    font-size: var(--text-base);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
}

.btn-danger:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(220, 53, 69, 0.4);
}

.url-validation {
    font-size: var(--text-xs);
    margin-top: var(--space-xs);
    font-weight: 500;
}

.url-validation.valid {
    color: var(--success);
}

.url-validation.invalid {
    color: var(--danger);
}

.no-urls {
    text-align: center;
    padding: var(--space-2xl) var(--space-xl);
    color: var(--fms-accent);
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    margin: var(--space-2xl) 0;
}

.no-urls i {
    font-size: 4rem;
    color: var(--primary-200);
    margin-bottom: var(--space-xl);
    display: block;
}

.no-urls h3 {
    color: var(--fms-primary);
    margin-bottom: var(--space-sm);
    font-size: var(--text-2xl);
    font-weight: 700;
}

.no-urls p {
    color: var(--fms-accent);
    font-size: var(--text-lg);
    font-weight: 500;
}

.loading {
    text-align: center;
    padding: var(--space-2xl);
    color: var(--fms-accent);
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    margin: var(--space-2xl) 0;
}

.loading i {
    font-size: 2rem;
    animation: spin 1s linear infinite;
    color: var(--fms-primary);
    margin-bottom: var(--space-md);
    display: block;
}

.loading p {
    color: var(--fms-accent);
    font-weight: 500;
    font-size: var(--text-base);
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Drag & Drop Styles */
.sortable-ghost {
    opacity: 0.4;
    background: var(--primary-100);
    border: 2px dashed var(--fms-primary);
}

.sortable-chosen {
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(47, 60, 126, 0.3);
}

.sortable-drag {
    transform: rotate(5deg);
    box-shadow: 0 15px 40px rgba(47, 60, 126, 0.4);
}

.url-card.dragging {
    opacity: 0.8;
    transform: scale(1.05) rotate(2deg);
    z-index: 1000;
    box-shadow: 0 20px 50px rgba(47, 60, 126, 0.4);
}

.urls-grid.drag-over {
    background: linear-gradient(135deg, var(--primary-50) 0%, var(--secondary-50) 100%);
    border: 2px dashed var(--fms-primary);
    border-radius: 20px;
    padding: var(--space-lg);
    margin: var(--space-lg) 0;
}

/* Notification System */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: var(--space-md) var(--space-lg);
    border-radius: 15px;
    color: white;
    font-weight: 600;
    z-index: 9999;
    transform: translateX(400px);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    backdrop-filter: var(--glass-blur);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.notification.show {
    transform: translateX(0);
}

.notification.success {
    background: linear-gradient(135deg, var(--success), #20c997);
}

.notification.error {
    background: linear-gradient(135deg, var(--danger), #c82333);
}

.notification.warning {
    background: linear-gradient(135deg, var(--warning), #ff8c00);
}

.notification.info {
    background: linear-gradient(135deg, var(--info), #17a2b8);
}

/* Sharing Section Styles */
.sharing-section {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: var(--glass-blur);
    padding: var(--space-xl);
    border-radius: 15px;
    margin-top: var(--space-xl);
    border: 1px solid var(--glass-border);
    position: relative;
    z-index: 1;
    overflow: visible !important;
}

/* Dark theme overrides for sharing section */
body .sharing-section {
    background: rgba(17, 24, 39, 0.6) !important;
    border: 1px solid var(--glass-border) !important;
    color: var(--dark-text-primary) !important;
}

.sharing-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-md);
    padding-bottom: var(--space-sm);
    border-bottom: 1px solid var(--glass-border);
    position: relative;
    z-index: 1;
}

.sharing-list-header h6 {
    color: #ffffff;
    margin: 0;
    font-weight: 600;
    font-size: var(--text-base);
    display: flex;
    align-items: center;
    gap: var(--space-xs);
}

.sharing-list-header h6 i {
    color: var(--fms-secondary);
}

/* Dark theme for sharing headers */
body .sharing-list-header h6,
body .sharing-section h6 {
    color: var(--dark-text-primary) !important;
}

body .sharing-list-header h6 i,
body .sharing-section h6 i {
    color: #93c5fd !important;
}

.sharing-items {
    max-height: 200px;
    overflow-y: auto;
    padding-right: var(--space-xs);
    position: relative;
    z-index: 1;
}

.sharing-items::-webkit-scrollbar {
    width: 4px;
}

.sharing-items::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
}

.sharing-items::-webkit-scrollbar-thumb {
    background: var(--fms-primary);
    border-radius: 2px;
}

.sharing-items::-webkit-scrollbar-thumb:hover {
    background: var(--fms-secondary);
}

.sharing-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-md);
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: var(--glass-blur);
    border-radius: 12px;
    margin-bottom: var(--space-sm);
    border: 1px solid var(--glass-border);
    transition: all 0.3s ease;
}

.sharing-item:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
}

.sharing-user-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.sharing-user-name {
    font-weight: 600;
    color: #ffffff;
    font-size: var(--text-sm);
}

/* Dark theme for sharing items */
body .sharing-item {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid var(--glass-border) !important;
}

body .sharing-item:hover {
    background: rgba(34, 211, 238, 0.12) !important;
}

body .sharing-user-name {
    color: var(--dark-text-primary) !important;
}

.sharing-user-permission {
    display: flex;
    align-items: center;
}

.permission-badge {
    padding: 2px var(--space-xs);
    border-radius: 8px;
    font-size: var(--text-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.permission-badge.view {
    background: linear-gradient(135deg, var(--info), #17a2b8);
    color: white;
}

.permission-badge.comment {
    background: linear-gradient(135deg, var(--warning), #ff8c00);
    color: white;
}

.permission-badge.edit {
    background: linear-gradient(135deg, var(--success), #20c997);
    color: white;
}

.sharing-user-actions {
    display: flex;
    gap: var(--space-xs);
}

.sharing-action-btn {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.3);
    color: var(--danger);
    padding: var(--space-xs);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-xs);
}

.sharing-action-btn:hover {
    background: rgba(220, 53, 69, 0.2);
    border-color: var(--danger);
    transform: scale(1.05);
}

/* Dark theme for sharing action buttons */
body .sharing-action-btn {
    background: rgba(220, 53, 69, 0.1) !important;
    border: 1px solid rgba(220, 53, 69, 0.3) !important;
    color: #ff6b6b !important;
}

body .sharing-action-btn:hover {
    background: rgba(220, 53, 69, 0.2) !important;
    border-color: #ff6b6b !important;
}

/* Dark theme for sharing buttons */
body .sharing-actions .btn-primary {
    background: linear-gradient(135deg, #0ea5e9, #22d3ee) !important;
    border: 1px solid rgba(34, 211, 238, 0.35) !important;
    color: #0b1220 !important;
    box-shadow: 0 0 18px rgba(34, 211, 238, 0.35), 0 2px 6px rgba(0, 0, 0, 0.4) !important;
}

body .sharing-actions .btn-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 0 22px rgba(34, 211, 238, 0.5), 0 4px 10px rgba(0, 0, 0, 0.5) !important;
}

body .sharing-actions .btn-secondary {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid var(--glass-border) !important;
    color: var(--dark-text-primary) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35) !important;
}

body .sharing-actions .btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.45) !important;
}

/* Dropdown Checkbox Styles */
.dropdown-checkbox-container {
    position: relative;
    width: 100%;
    z-index: 2147483647;
    isolation: isolate;
    overflow: visible !important;
}

.dropdown-checkbox-menu {
    z-index: 2147483647 !important;
    position: absolute !important;
}

.dropdown-checkbox-menu.show {
    z-index: 2147483647 !important;
    position: absolute !important;
}

.dropdown-checkbox-container.open .dropdown-checkbox-menu {
    z-index: 2147483647 !important;
    position: absolute !important;
}

.dropdown-checkbox-toggle {
    width: 100%;
    padding: var(--space-sm) var(--space-md);
    border: 2px solid var(--primary-200);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: var(--glass-blur);
    font-size: var(--text-sm);
    font-weight: 500;
    color: var(--fms-dark);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    text-align: left;
    position: relative;
    z-index: 1;
}

/* Dark theme for dropdown toggle */
body .dropdown-checkbox-toggle {
    background: rgba(17, 24, 39, 0.6) !important;
    border: 1px solid var(--glass-border) !important;
    color: var(--dark-text-primary) !important;
}

body .dropdown-checkbox-toggle:hover {
    background: rgba(17, 24, 39, 0.8) !important;
    border-color: rgba(34, 211, 238, 0.6) !important;
}

body .dropdown-checkbox-toggle.active {
    background: rgba(17, 24, 39, 0.8) !important;
    border-color: rgba(34, 211, 238, 0.6) !important;
    box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.15) !important;
}

body .dropdown-checkbox-toggle i {
    color: rgba(255, 255, 255, 0.7) !important;
}

body .dropdown-checkbox-toggle.active i {
    color: #93c5fd !important;
}

body #selectedUsersText {
    color: var(--dark-text-primary) !important;
}

.dropdown-checkbox-toggle:hover {
    border-color: var(--fms-primary);
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-1px);
}

.dropdown-checkbox-toggle:focus {
    outline: none;
    border-color: var(--fms-primary);
    box-shadow: 0 0 0 3px var(--primary-100);
}

.dropdown-checkbox-toggle.active {
    border-color: var(--fms-primary);
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 0 0 3px var(--primary-100);
}

.dropdown-checkbox-toggle i {
    transition: transform 0.3s ease;
    color: var(--fms-accent);
}

.dropdown-checkbox-toggle.active i {
    transform: rotate(180deg);
    color: var(--fms-primary);
}

.dropdown-checkbox-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: var(--glass-blur);
    border: 2px solid var(--primary-200);
    border-top: none;
    border-radius: 0 0 10px 10px;
    box-shadow: 0 12px 35px rgba(47, 60, 126, 0.25);
    max-height: 200px;
    overflow-y: auto;
    display: none;
    min-width: 100%;
    margin-top: 2px;
}

/* Dark theme for dropdown menu */
body .dropdown-checkbox-menu {
    background: rgba(6, 8, 14, 0.98) !important;
    border: 1px solid var(--glass-border) !important;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.45) !important;
    z-index: 2147483647 !important;
}

body .dropdown-checkbox-menu.show {
    z-index: 2147483647 !important;
}

body .dropdown-checkbox-container.open .dropdown-checkbox-menu {
    z-index: 2147483647 !important;
    position: absolute !important;
}

.dropdown-checkbox-menu.show {
    display: block !important;
    animation: slideDown 0.3s ease;
    position: absolute !important;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-checkbox-menu::-webkit-scrollbar {
    width: 6px;
}

.dropdown-checkbox-menu::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

.dropdown-checkbox-menu::-webkit-scrollbar-thumb {
    background: var(--fms-primary);
    border-radius: 3px;
}

.dropdown-checkbox-menu::-webkit-scrollbar-thumb:hover {
    background: var(--fms-secondary);
}

.loading-users {
    text-align: center;
    padding: var(--space-lg);
    color: var(--fms-accent);
    font-style: italic;
}

.user-search-container {
    position: relative;
    padding: 0.75rem;
    border-bottom: 2px solid var(--primary-200);
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(5px);
}

/* Dark theme for search container */
body .dropdown-checkbox-menu .user-search-container {
    background: rgba(17, 24, 39, 0.8) !important;
    border-bottom-color: rgba(34, 211, 238, 0.3) !important;
}

.user-search-input {
    width: 100%;
    padding: 0.5rem 2.5rem 0.5rem 0.75rem;
    border: 2px solid var(--primary-200);
    border-radius: 6px;
    font-size: 0.875rem;
    color: var(--fms-dark);
    background: rgba(255, 255, 255, 0.95);
    transition: all 0.2s ease;
}

/* Dark theme for search input */
body .dropdown-checkbox-menu .user-search-input {
    background: rgba(6, 8, 14, 0.9) !important;
    border-color: rgba(34, 211, 238, 0.3) !important;
    color: var(--dark-text-primary) !important;
}

body .dropdown-checkbox-menu .user-search-input:focus {
    border-color: rgba(34, 211, 238, 0.6) !important;
    background: rgba(6, 8, 14, 1) !important;
}

body .dropdown-checkbox-menu .user-search-input::placeholder {
    color: rgba(255, 255, 255, 0.5) !important;
}

body .dropdown-checkbox-menu .user-search-icon {
    color: rgba(34, 211, 238, 0.7) !important;
}

.user-search-input:focus {
    outline: none;
    border-color: var(--fms-primary);
    box-shadow: 0 0 0 3px var(--primary-100);
    background: rgba(255, 255, 255, 1);
}

.user-search-input::placeholder {
    color: var(--fms-accent);
    font-style: italic;
}

.user-search-icon {
    position: absolute;
    right: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--fms-primary);
    pointer-events: none;
}

.user-checkbox-list {
    max-height: 250px;
    overflow-y: auto;
}

.user-checkbox-item {
    display: flex;
    align-items: center;
    padding: var(--space-sm);
    border-radius: 8px;
    margin-bottom: var(--space-xs);
    transition: all 0.3s ease;
    cursor: pointer;
    z-index: 2147483647 !important;
    position: relative !important;
}

.user-checkbox-item:hover {
    background: rgba(47, 60, 126, 0.1);
    transform: translateX(2px);
}

/* Dark theme for user checkbox items */
body .dropdown-checkbox-menu .user-checkbox-item {
    background: transparent !important;
    color: var(--dark-text-primary) !important;
}

body .dropdown-checkbox-menu .user-checkbox-item:hover {
    background: rgba(34, 211, 238, 0.12) !important;
}

body .dropdown-checkbox-menu .user-name {
    color: var(--dark-text-primary) !important;
}

body .dropdown-checkbox-menu .user-type {
    color: white !important;
}

body .dropdown-checkbox-menu .loading-users {
    color: rgba(255, 255, 255, 0.7) !important;
}

body .dropdown-checkbox-menu p.text-muted,
body .dropdown-checkbox-menu .text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

body .dropdown-checkbox-menu input[type="checkbox"] {
    accent-color: #22d3ee !important;
}

.user-checkbox-item input[type="checkbox"] {
    margin-right: var(--space-sm);
    accent-color: var(--fms-primary);
    transform: scale(1.1);
}

.user-checkbox-item label {
    margin: 0;
    cursor: pointer;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.user-name {
    font-weight: 600;
    color: var(--fms-dark);
    font-size: var(--text-sm);
}

.user-type {
    background: linear-gradient(135deg, var(--fms-primary), var(--fms-secondary));
    color: white;
    padding: 2px var(--space-xs);
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.user-type.admin {
    background: linear-gradient(135deg, #dc3545, #c82333);
}

.user-type.manager {
    background: linear-gradient(135deg, #ffc107, #ff8c00);
}

.user-type.doer {
    background: linear-gradient(135deg, #28a745, #20c997);
}

/* Sharing Controls Horizontal Layout */
.sharing-controls-row {
    display: flex;
    gap: var(--space-lg);
    align-items: flex-end;
    margin-bottom: var(--space-lg);
    flex-wrap: wrap;
    overflow: visible !important;
    position: relative;
}

.sharing-control-item {
    flex: 1;
    min-width: 200px;
    display: flex;
    flex-direction: column;
    overflow: visible !important;
    position: relative;
}

/* Dark theme for form labels in sharing section */
body .sharing-section .form-label {
    color: var(--dark-text-primary) !important;
}

body .sharing-section select.form-control {
    background: rgba(17, 24, 39, 0.6) !important;
    border: 1px solid var(--glass-border) !important;
    color: var(--dark-text-primary) !important;
}

body .sharing-section select.form-control option {
    background: #0b1220 !important;
    color: var(--dark-text-primary) !important;
}

body .sharing-section select.form-control:focus {
    border-color: rgba(34, 211, 238, 0.6) !important;
    background: rgba(17, 24, 39, 0.8) !important;
}

.sharing-control-item:last-child {
    flex: 0 0 auto;
    min-width: auto;
}

.sharing-actions {
    display: flex;
    gap: var(--space-sm);
    align-items: center;
}

.sharing-actions .btn {
    white-space: nowrap;
}

/* Shared URLs Section */
.shared-urls-section {
    margin-top: var(--space-2xl);
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: var(--space-xl);
    box-shadow: var(--glass-shadow);
    border-left: 4px solid var(--info);
}

.shared-urls-header {
    text-align: center;
    margin-bottom: var(--space-2xl);
    padding-bottom: var(--space-lg);
    border-bottom: 2px solid var(--glass-border);
}

.shared-urls-header h2 {
    color: #ffffff;
    font-size: var(--text-3xl);
    font-weight: 700;
    margin: 0 0 var(--space-sm) 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-sm);
}

.shared-urls-header h2 i {
    color: var(--info);
}

.shared-urls-subtitle {
    color: var(--fms-accent);
    font-size: var(--text-lg);
    font-weight: 500;
    margin: 0;
}

.shared-url-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: var(--space-md);
    margin-bottom: var(--space-md);
    box-shadow: var(--glass-shadow);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    cursor: pointer;
    overflow: hidden;
    border-left: 3px solid var(--info);
}

.shared-url-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(23, 162, 184, 0.1);
    border-color: var(--info);
    background: rgba(255, 255, 255, 0.15);
}

.shared-url-owner {
    background: linear-gradient(135deg, var(--info), #17a2b8);
    color: white;
    padding: 2px var(--space-xs);
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    box-shadow: 0 1px 4px rgba(23, 162, 184, 0.2);
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin-bottom: var(--space-sm);
}

.shared-url-permission {
    background: linear-gradient(135deg, var(--warning), #ff8c00);
    color: white;
    padding: 2px var(--space-xs);
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    box-shadow: 0 1px 4px rgba(255, 193, 7, 0.2);
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin-left: var(--space-xs);
}

.shared-urls-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-md);
}

.shared-url-meta {
    margin-bottom: var(--space-sm);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-xs);
}

.sharing-list {
    max-height: 200px;
    overflow-y: auto;
    margin-top: var(--space-lg);
    position: relative;
    z-index: 1;
}

@media (max-width: 768px) {
    .urls-container {
        padding: var(--space-md);
    }
    
    .urls-header {
        flex-direction: column;
        align-items: stretch;
        gap: var(--space-md);
        padding: var(--space-lg);
    }
    
    .urls-header h2 {
        font-size: var(--text-xl);
        text-align: center;
    }
    
    .urls-filters {
        justify-content: center;
        flex-wrap: wrap;
        gap: var(--space-sm);
    }
    
    .search-box {
        min-width: 150px;
        max-width: 100%;
        flex: 1 1 auto;
    }
    
    .filter-select {
        min-width: 140px;
    }
    
    .btn-add-url {
        font-size: 12px;
        padding: 8px 12px;
    }
    
    .urls-grid {
        grid-template-columns: 1fr;
        gap: var(--space-sm);
    }
    
    .url-card {
        padding: var(--space-sm);
    }
    
    .url-actions {
        opacity: 1;
        transform: translateX(0);
    }
    
    .tabs {
        flex-direction: column;
    }
    
    .form-container {
        padding: var(--space-md);
    }
    
    .form-actions {
        flex-direction: column;
        gap: var(--space-md);
    }
    
    .btn-primary,
    .btn-secondary,
    .btn-danger {
        width: 100%;
        justify-content: center;
    }
    
    .sharing-controls-row {
        flex-direction: column;
        gap: var(--space-md);
    }
    
    .sharing-control-item {
        min-width: 100%;
    }
    
    .sharing-actions {
        justify-content: center;
        width: 100%;
    }
    
    .sharing-actions .btn {
        flex: 1;
        justify-content: center;
    }
}
</style>

<div class="urls-container">
    <div class="urls-header">
        <h2><i class="fas fa-link"></i> Useful URLs</h2>
        <div class="urls-filters">
            <div class="search-box">
                <input type="text" id="searchUrls" placeholder="Search URLs...">
                <i class="fas fa-search"></i>
            </div>
            <select class="filter-select" id="filterCategory">
                <option value="">All Categories</option>
                <option value="Work">Work</option>
                <option value="Tools">Tools</option>
                <option value="Resources">Resources</option>
                <option value="Documentation">Documentation</option>
                <option value="Other">Other</option>
            </select>
            <?php 
            $user_type = $_SESSION['user_type'] ?? '';
            // Show Personal URL button for Manager, Doer, and Admin
            if (in_array($user_type, ['manager', 'doer', 'admin'])): ?>
            <button class="btn-add-url" onclick="openUrlModal('personal')">
                <i class="fas fa-plus"></i> Add Personal URL
            </button>
            <?php endif; ?>
            
            <?php if (isAdmin()): ?>
            <button class="btn-add-url" onclick="openUrlModal('admin')" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
                <i class="fas fa-plus"></i> Add Admin URL
            </button>
            <?php endif; ?>
            <button class="btn-add-url" id="toggleSharedUrls" style="background: linear-gradient(135deg, var(--info) 0%, #17a2b8 100%); box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);">
                <i class="fas fa-eye"></i> Show Shared URLs
            </button>
        </div>
    </div>

    <!-- URL Form Section -->
    <div class="url-form-section" id="urlFormSection" style="display: none;">
        <div class="form-container">
            <div class="form-header">
                <h3 id="urlFormTitle">Add New URL</h3>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveUrl()">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm ml-2" onclick="resetUrlForm()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm ml-2" onclick="closeUrlForm()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                </div>
            </div>
            <form id="urlForm">
                <input type="hidden" id="urlId" name="url_id">
                <input type="hidden" id="urlType" name="url_type">
                
                <div class="row align-items-end">
                    <div class="col-md-3">
                <div class="form-group">
                    <label class="form-label" for="urlTitle">Title *</label>
                            <input type="text" class="form-control" id="urlTitle" name="title" required autocomplete="off">
                </div>
                    </div>
                    <div class="col-md-3">
                <div class="form-group">
                    <label class="form-label" for="urlLink">URL *</label>
                            <input type="text" class="form-control" id="urlLink" name="url" required placeholder="example.com or https://example.com" autocomplete="off">
                    <div class="url-validation" id="urlValidation"></div>
                </div>
                </div>
                    <div class="col-md-3.5">
                <div class="form-group">
                    <label class="form-label" for="urlCategory">Category</label>
                    <select class="form-control" id="urlCategory" name="category">
                        <option value="">Select Category</option>
                        <option value="Work">Work</option>
                        <option value="Tools">Tools</option>
                        <option value="Resources">Resources</option>
                        <option value="Documentation">Documentation</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                    </div>
                    <div class="col-md-2" id="visibilityGroup" style="display: none;">
                        <div class="form-group">
                    <label class="form-label" for="urlVisibility">Visible For</label>
                    <select class="form-control" id="urlVisibility" name="visible_for">
                        <option value="all">All Users</option>
                        <option value="admin">Admins Only</option>
                        <option value="manager">Managers & Admins</option>
                        <option value="doer">Doers & Above</option>
                    </select>
                </div>
                    </div>
                    <div class="col-md-2" id="deleteButtonGroup" style="display: none;">
                        <div class="form-group">
                            <button type="button" class="btn btn-danger btn-block" id="deleteUrlBtn" onclick="deleteUrl()">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label" for="urlDescription">Description</label>
                            <textarea class="form-control" id="urlDescription" name="description" rows="2" placeholder="Brief description of this URL..."></textarea>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Sharing Section -->
            <div class="sharing-section" id="sharingSection" style="display: none;">
                <h6><i class="fas fa-share-alt"></i> Share URL</h6>
                
                <!-- Horizontal Layout for Sharing Controls -->
                <div class="sharing-controls-row">
                    <!-- User Selection -->
                    <div class="sharing-control-item">
                        <label class="form-label">Select Users to Share With:</label>
                        <div class="dropdown-checkbox-container">
                            <button type="button" class="dropdown-checkbox-toggle" id="usersDropdownToggle">
                                <span id="selectedUsersText">Select users...</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="dropdown-checkbox-menu" id="usersDropdownMenu">
                                <div class="loading-users">
                                    <i class="fas fa-spinner fa-spin"></i> Loading users...
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Permission Selection -->
                    <div class="sharing-control-item">
                        <label class="form-label">Permission Level:</label>
                        <select class="form-control" id="sharePermission">
                            <option value="view">View Only</option>
                            <option value="comment">View & Comment</option>
                            <option value="edit">View, Comment & Edit</option>
                        </select>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="sharing-control-item">
                        <label class="form-label">&nbsp;</label>
                        <div class="sharing-actions">
                            <button type="button" class="btn btn-primary btn-sm" onclick="shareUrl()">
                                <i class="fas fa-share"></i> Share
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="refreshSharingList()" title="Refresh sharing list">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Shared Users List -->
                <div class="sharing-list" id="sharingList">
                    <!-- Shared users will be listed here -->
                </div>
            </div>
        </div>
        </div>
        
    <div class="tabs-container">
        <div class="tabs">
            <div class="tab active" onclick="switchTab('all')">
                <i class="fas fa-globe"></i> All URLs
            </div>
            <div class="tab" onclick="switchTab('personal')">
                <i class="fas fa-user"></i> Personal URLs
            </div>
            <div class="tab" onclick="switchTab('admin')">
                <i class="fas fa-shield-alt"></i> Admin URLs
            </div>
        </div>
        
        <div class="tab-content active" id="allUrls">
            <div id="allUrlsContainer">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading URLs...</p>
                </div>
            </div>
        </div>
        
        <div class="tab-content" id="personalUrls">
            <div id="personalUrlsContainer">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading personal URLs...</p>
                </div>
            </div>
        </div>
        
        <div class="tab-content" id="adminUrls">
            <div id="adminUrlsContainer">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading admin URLs...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Shared URLs Section -->
    <div class="shared-urls-section" id="sharedUrlsSection" style="display: none;">
        <div class="shared-urls-header">
            <h2><i class="fas fa-share-alt"></i> Shared URLs</h2>
            <p class="shared-urls-subtitle">URLs shared with you by other users</p>
        </div>
        <div id="sharedUrlsContainer">
            <div class="loading">
                <i class="fas fa-spinner"></i>
                <p>Loading shared URLs...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
let currentUrlId = null;
let currentUrlType = 'personal';
let sortableInstance = null;
let allUsers = [];

// Optimized useful URLs functionality
if (!window.usefulUrlsInitialized) {
    window.usefulUrlsInitialized = true;
    
    $(document).ready(function() {
        loadAllUrls();
        initializeDragAndDrop();
        loadUsers();
        loadSharedUrls();
    
    // Search functionality
    $('#searchUrls').on('input', function() {
        loadCurrentTab();
    });
    
    // Category filter
    $('#filterCategory').on('change', function() {
        loadCurrentTab();
    });
    
    // URL validation
    $('#urlLink').on('input', function() {
        validateUrl();
    });
    
    // Toggle shared URLs section
    $('#toggleSharedUrls').on('click', function() {
        const section = $('#sharedUrlsSection');
        if (section.is(':visible')) {
            section.slideUp();
            $(this).html('<i class="fas fa-eye"></i> Show Shared URLs');
        } else {
            section.slideDown();
            $(this).html('<i class="fas fa-eye-slash"></i> Hide Shared URLs');
            loadSharedUrls(); // Refresh shared URLs when showing
        }
    });
    
    // Dropdown checkbox toggle - Keep attached to field
    $('#usersDropdownToggle').on('click', function(e) {
        e.stopPropagation();
        const menu = $('#usersDropdownMenu');
        const searchInput = $('#userSearchInput');
        const toggle = $(this);
        const container = toggle.closest('.dropdown-checkbox-container');
        
        // Clear search when opening dropdown
        if (!menu.hasClass('show')) {
            if (searchInput.length) {
                searchInput.val('');
                filterUsers('');
            }
        }
        
        if (menu.hasClass('show')) {
            // Close dropdown
            menu.removeClass('show');
            toggle.removeClass('active');
            container.removeClass('open');
            
            // Clear search when closing dropdown
            if (searchInput.length) {
                searchInput.val('');
                filterUsers('');
            }
        } else {
            // Open dropdown - keep it attached to the container
            menu.addClass('show');
            toggle.addClass('active');
            container.addClass('open');
            
            // Ensure menu stays within container
            menu.css({
                position: 'absolute',
                top: '100%',
                left: '0',
                right: '0',
                width: '100%',
                minWidth: '100%',
                zIndex: '2147483647',
                marginTop: '2px'
            });
        }
    });
    
    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        const menu = $('#usersDropdownMenu');
        const container = $('.dropdown-checkbox-container');
        const searchInput = $('#userSearchInput');
        
        if (!$(e.target).closest('.dropdown-checkbox-container').length && 
            !$(e.target).closest('#usersDropdownMenu').length) {
            menu.removeClass('show');
            $('#usersDropdownToggle').removeClass('active');
            container.removeClass('open');
            
            // Clear search when closing dropdown
            if (searchInput.length) {
                searchInput.val('');
                filterUsers('');
            }
        }
    });
    });
}

function switchTab(tabName) {
    // Update tab appearance
    $('.tab').removeClass('active');
    $(`.tab[onclick="switchTab('${tabName}')"]`).addClass('active');
    
    // Update tab content
    $('.tab-content').removeClass('active');
    $(`#${tabName}Urls`).addClass('active');
    
    // Load appropriate content
    loadCurrentTab();
}

function loadCurrentTab() {
    const activeTab = $('.tab.active').attr('onclick').match(/switchTab\('(.+)'\)/)[1];
    
    switch(activeTab) {
        case 'all':
            loadAllUrls();
            break;
        case 'personal':
            loadPersonalUrls();
            break;
        case 'admin':
            loadAdminUrls();
            break;
    }
}

function initializeDragAndDrop() {
    const personalGrid = document.getElementById('personalUrlsGrid');
    const adminGrid = document.getElementById('adminUrlsGrid');
    
    if (personalGrid && typeof Sortable !== 'undefined') {
        new Sortable(personalGrid, {
            animation: 300,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onStart: function(evt) {
                evt.item.classList.add('dragging');
            },
            onEnd: function(evt) {
                evt.item.classList.remove('dragging');
                updateUrlOrder('personal');
            }
        });
    }
    
    if (adminGrid && typeof Sortable !== 'undefined') {
        new Sortable(adminGrid, {
            animation: 300,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onStart: function(evt) {
                evt.item.classList.add('dragging');
            },
            onEnd: function(evt) {
                evt.item.classList.remove('dragging');
                updateUrlOrder('admin');
            }
        });
    }
}

function updateUrlOrder(type) {
    const urlIds = [];
    const gridId = type === 'personal' ? '#personalUrlsGrid' : '#adminUrlsGrid';
    
    $(gridId + ' .url-card').each(function() {
        const urlId = $(this).data('url-id');
        if (urlId) {
            urlIds.push(urlId);
        }
    });
    
    if (urlIds.length > 0) {
        $.ajax({
            url: '../ajax/urls_handler.php',
            type: 'POST',
            data: {
                action: 'update_order',
                url_type: type,
                url_ids: urlIds
            },
            success: function(response) {
                if (response.success) {
                    showNotification('URLs reordered successfully!', 'success');
                }
            },
            error: function() {
                showNotification('Failed to update URL order', 'error');
            }
        });
    }
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    $('.notification').remove();
    
    const notification = $(`
        <div class="notification ${type}">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
    `);
    
    $('body').append(notification);
    
    // Show notification
    setTimeout(() => {
        notification.addClass('show');
    }, 100);
    
    // Hide notification after 3 seconds
    setTimeout(() => {
        notification.removeClass('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

function getNotificationIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function loadAllUrls() {
    const search = $('#searchUrls').val();
    const category = $('#filterCategory').val();
    
    $.ajax({
        url: '../ajax/urls_handler.php',
        method: 'POST',
        data: {
            action: 'get_all_urls',
            search: search,
            category: category,
            type: 'all'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayAllUrls(response.personal_urls, response.admin_urls);
            } else {
                showAlert('Error loading URLs: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading URLs. Please try again.', 'danger');
        }
    });
}

function loadPersonalUrls() {
    const search = $('#searchUrls').val();
    const category = $('#filterCategory').val();
    
    $.ajax({
        url: '../ajax/urls_handler.php',
        method: 'POST',
        data: {
            action: 'get_personal_urls',
            search: search,
            category: category
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayUrls(response.urls, 'personalUrlsContainer');
            } else {
                showAlert('Error loading personal URLs: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading personal URLs. Please try again.', 'danger');
        }
    });
}

function loadAdminUrls() {
    const search = $('#searchUrls').val();
    const category = $('#filterCategory').val();
    
    $.ajax({
        url: '../ajax/urls_handler.php',
        method: 'POST',
        data: {
            action: 'get_admin_urls',
            search: search,
            category: category
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayUrls(response.urls, 'adminUrlsContainer');
            } else {
                showAlert('Error loading admin URLs: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading admin URLs. Please try again.', 'danger');
        }
    });
}

function displayAllUrls(personalUrls, adminUrls) {
    const container = $('#allUrlsContainer');
    
    if (personalUrls.length === 0 && adminUrls.length === 0) {
        container.html(`
            <div class="no-urls">
                <i class="fas fa-link"></i>
                <h3>No URLs found</h3>
                <p>Add your first URL to get started!</p>
            </div>
        `);
        return;
    }
    
    let html = '<div class="urls-grid">';
    
    // Display personal URLs
    personalUrls.forEach(url => {
        html += createUrlCard(url, 'personal');
    });
    
    // Display admin URLs
    adminUrls.forEach(url => {
        html += createUrlCard(url, 'admin');
    });
    
    html += '</div>';
    container.html(html);
}

function displayUrls(urls, containerId) {
    const container = $(`#${containerId}`);
    
    if (urls.length === 0) {
        container.html(`
            <div class="no-urls">
                <i class="fas fa-link"></i>
                <h3>No URLs found</h3>
                <p>Add your first URL to get started!</p>
            </div>
        `);
        return;
    }
    
    let html = '<div class="urls-grid">';
    
    urls.forEach(url => {
        const urlType = containerId.includes('personal') ? 'personal' : 'admin';
        html += createUrlCard(url, urlType);
    });
    
    html += '</div>';
    container.html(html);
}

function createUrlCard(url, type) {
    const createdDate = new Date(url.created_at).toLocaleDateString();
    const isAdminUser = <?php echo isAdmin() ? 'true' : 'false'; ?>;
    const canEdit = type === 'personal' || (type === 'admin' && isAdminUser);
    const canShare = type === 'personal' || (type === 'admin' && isAdminUser);
    
    return `
        <div class="url-card ${type}" data-url="${escapeHtml(url.url)}" onclick="openUrl('${escapeHtml(url.url)}')">
            <div class="url-header">
                <h4 class="url-title">${escapeHtml(url.title)}</h4>
                <div class="url-actions" onclick="event.stopPropagation()">
                    ${canEdit ? `
                        <button class="url-action-btn edit" onclick="editUrl(${url.id}, '${type}')" title="Edit URL">
                            <i class="fas fa-edit"></i>
                        </button>
                    ` : ''}
                    ${canShare ? `
                        <button class="url-action-btn share" onclick="shareUrlFromCard(${url.id}, '${type}')" title="Share URL">
                            <i class="fas fa-share-alt"></i>
                        </button>
                    ` : ''}
                    ${canEdit ? `
                        <button class="url-action-btn delete" onclick="deleteUrl(${url.id}, '${type}')" title="Delete URL">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
            <div class="url-link">
                <i class="fas fa-external-link-alt"></i> ${escapeHtml(url.url)}
            </div>
            ${url.description ? `<div class="url-description">${escapeHtml(url.description)}</div>` : ''}
            <div class="url-meta">
                <div class="url-date">
                    <i class="fas fa-calendar"></i>
                    ${createdDate}
                </div>
                <div>
                    ${url.category ? `<span class="url-category">${escapeHtml(url.category)}</span>` : ''}
                    <span class="url-type ${type}">${type === 'personal' ? 'Personal' : 'Admin'}</span>
                </div>
            </div>
        </div>
    `;
}

function openUrlModal(type, urlId = null) {
    currentUrlId = urlId;
    currentUrlType = type;
    
    if (urlId) {
        // Edit mode
        $('#urlFormTitle').text(`Edit ${type === 'personal' ? 'Personal' : 'Admin'} URL`);
        $('#deleteUrlBtn').show();
        $('#deleteButtonGroup').show();
        loadUrl(urlId, type);
        // Show sharing section for existing URLs
        $('#sharingSection').show();
        loadSharingList(urlId, type);
    } else {
        // Create mode
        $('#urlFormTitle').text(`Add New ${type === 'personal' ? 'Personal' : 'Admin'} URL`);
        $('#deleteUrlBtn').hide();
        $('#deleteButtonGroup').hide();
        $('#urlForm')[0].reset();
        $('#urlType').val(type);
        
        if (type === 'admin') {
            $('#visibilityGroup').show();
            // Adjust column sizes for admin form
            $('.col-md-3').removeClass('col-md-3').addClass('col-md-2');
        } else {
            $('#visibilityGroup').hide();
            // Reset column sizes for personal form
            $('.col-md-2').first().removeClass('col-md-2').addClass('col-md-3');
        }
        
        // Hide sharing section for new URLs
        $('#sharingSection').hide();
    }
    
    $('#urlFormSection').show();
    
    // Scroll to top of page to show the form
    $('html, body').animate({
        scrollTop: 0
    }, 500);
}

function closeUrlForm() {
    $('#urlFormSection').hide();
    $('#urlForm')[0].reset();
    $('#visibilityGroup').hide();
    $('#sharingSection').hide();
    currentUrlId = null;
    
    // Reset column sizes
    $('.col-md-2').first().removeClass('col-md-2').addClass('col-md-3');
    
    // Clear user selections
    $('input[name="selected_users"]:checked').prop('checked', false);
    updateSelectedUsersText();
    
    // Close dropdown if open
    $('#usersDropdownMenu').removeClass('show');
    $('#usersDropdownToggle').removeClass('active');
    $('#usersDropdownToggle').closest('.dropdown-checkbox-container').removeClass('open');
}

function resetUrlForm() {
    $('#urlForm')[0].reset();
    $('#urlId').val('');
    $('#urlType').val(currentUrlType);
    $('#deleteButtonGroup').hide();
    
    if (currentUrlType === 'admin') {
        $('#visibilityGroup').show();
    } else {
        $('#visibilityGroup').hide();
    }
}

function loadUrl(urlId, type) {
    $.ajax({
        url: '../ajax/urls_handler.php',
        method: 'POST',
        data: {
            action: `get_${type}_url`,
            url_id: urlId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const url = response.url;
                $('#urlId').val(url.id);
                $('#urlTitle').val(url.title);
                $('#urlLink').val(url.url);
                $('#urlDescription').val(url.description);
                $('#urlCategory').val(url.category);
                
                if (type === 'admin') {
                    $('#urlVisibility').val(url.visible_for);
                }
                
                $('#deleteButtonGroup').show();
                
                // Load sharing list
                loadSharingList(urlId, type);
            } else {
                showAlert('Error loading URL: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading URL. Please try again.', 'danger');
        }
    });
}

function saveUrl() {
    let urlValue = $('#urlLink').val().trim();
    
    // Normalize URL - add http:// if no protocol
    if (urlValue && !urlValue.match(/^https?:\/\//i)) {
        urlValue = 'http://' + urlValue;
    }
    
    const formData = {
        action: currentUrlId ? `update_${currentUrlType}_url` : `create_${currentUrlType}_url`,
        title: $('#urlTitle').val(),
        url: urlValue,
        description: $('#urlDescription').val(),
        category: $('#urlCategory').val()
    };
    
    if (currentUrlId) {
        formData.url_id = currentUrlId;
    }
    
    if (currentUrlType === 'admin') {
        formData.visible_for = $('#urlVisibility').val();
    }
    
    if (!formData.title.trim() || !formData.url.trim()) {
        showAlert('Please fill in all required fields.', 'warning');
        return;
    }
    
    if (!isValidUrl(formData.url)) {
        showAlert('Please enter a valid URL.', 'warning');
        return;
    }
    
    $.ajax({
        url: '../ajax/urls_handler.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                $('#urlFormSection').hide();
                loadCurrentTab();
            } else {
                showAlert('Error saving URL: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error saving URL. Please try again.', 'danger');
        }
    });
}

function editUrl(urlId, type) {
    openUrlModal(type, urlId);
}

function shareUrlFromCard(urlId, type) {
    // Open the URL form in edit mode and show sharing section
    openUrlModal(type, urlId);
    
    // Wait a bit for the form to load, then scroll to sharing section
    setTimeout(function() {
        $('#sharingSection').show();
        loadSharingList(urlId, type);
        
        // Scroll to sharing section
        $('html, body').animate({
            scrollTop: $('#sharingSection').offset().top - 100
        }, 500);
    }, 300);
}

function deleteUrl(urlId, type = null) {
    if (!urlId) return;
    
    const actualType = type || currentUrlType;
    
    if (confirm('Are you sure you want to delete this URL? This action cannot be undone.')) {
        $.ajax({
            url: '../ajax/urls_handler.php',
            method: 'POST',
            data: {
                action: `delete_${actualType}_url`,
                url_id: urlId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    if (type) {
                        loadCurrentTab();
                    } else {
                        $('#urlFormSection').hide();
                        loadCurrentTab();
                    }
                } else {
                    showAlert('Error deleting URL: ' + response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error deleting URL. Please try again.', 'danger');
            }
        });
    }
}

function validateUrl() {
    const url = $('#urlLink').val();
    const validation = $('#urlValidation');
    
    if (!url) {
        validation.removeClass('valid invalid').text('');
        return;
    }
    
    if (isValidUrl(url)) {
        validation.removeClass('invalid').addClass('valid').text('✓ Valid URL');
    } else {
        validation.removeClass('valid').addClass('invalid').text('✗ Invalid URL format');
    }
}

function isValidUrl(string) {
    if (!string || string.trim() === '') return false;
    
    // Remove https validation - allow URLs with or without protocol
    try {
        // Try to create URL - if it fails, try adding http:// prefix
    try {
        new URL(string);
        return true;
        } catch (e) {
            // If URL creation fails, try adding http:// prefix
            if (!string.match(/^https?:\/\//i)) {
                new URL('http://' + string);
                return true;
            }
            return false;
        }
    } catch (_) {
        return false;
    }
}

function openUrl(url) {
    // Open URL in new tab
    window.open(url, '_blank');
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function showAlert(message, type) {
    // Create and show alert
    const alert = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `);
    
    $('.urls-container').prepend(alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alert.alert('close');
    }, 5000);
}

// Sharing Functions
function loadUsers() {
    $.ajax({
        url: '../ajax/urls_handler.php',
        method: 'POST',
        data: {
            action: 'get_all_users'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                allUsers = response.users;
                displayUserCheckboxes();
            } else {
                $('#usersDropdownMenu').html('<p class="text-muted">Error loading users.</p>');
            }
        },
        error: function() {
            $('#usersDropdownMenu').html('<p class="text-muted">Error loading users.</p>');
        }
    });
}

function displayUserCheckboxes() {
    const container = $('#usersDropdownMenu');
    
    if (allUsers.length === 0) {
        container.html('<p class="text-muted">No users available to share with.</p>');
        return;
    }
    
    // Build search box and user list
    let html = `
        <div class="user-search-container">
            <input type="text" class="user-search-input" id="userSearchInput" placeholder="Search users by name or type...">
            <i class="fas fa-search user-search-icon"></i>
        </div>
        <div class="user-checkbox-list" id="userCheckboxList">
    `;
    
    allUsers.forEach(user => {
        const userTypeClass = user.user_type.toLowerCase();
        html += `
            <div class="user-checkbox-item" data-user-name="${escapeHtml(user.name).toLowerCase()}" data-user-type="${escapeHtml(user.user_type).toLowerCase()}">
                <input type="checkbox" id="user_${user.id}" value="${user.id}" name="selected_users">
                <label for="user_${user.id}">
                    <span class="user-name">${escapeHtml(user.name)}</span>
                    <span class="user-type ${userTypeClass}">${user.user_type}</span>
                </label>
            </div>
        `;
    });
    
    html += '</div>';
    
    container.html(html);
    
    // Add event listener for search input
    $('#userSearchInput').on('input', function() {
        filterUsers($(this).val().toLowerCase());
    });
    
    // Add event listeners to checkboxes
    $('input[name="selected_users"]').on('change', function() {
        updateSelectedUsersText();
    });
}

function filterUsers(searchTerm) {
    const userItems = $('.user-checkbox-item');
    
    if (!searchTerm || searchTerm.trim() === '') {
        // Show all users if search is empty
        userItems.show();
    } else {
        // Filter users based on search term
        userItems.each(function() {
            const userName = $(this).data('user-name') || '';
            const userType = $(this).data('user-type') || '';
            const matches = userName.includes(searchTerm) || userType.includes(searchTerm);
            
            if (matches) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        // Show message if no users match
        const visibleItems = userItems.filter(':visible');
        const checkboxList = $('#userCheckboxList');
        if (visibleItems.length === 0) {
            if (checkboxList.find('.no-results-message').length === 0) {
                checkboxList.append('<p class="text-muted no-results-message" style="padding: 1rem; text-align: center;">No users found matching your search.</p>');
            }
        } else {
            checkboxList.find('.no-results-message').remove();
        }
    }
}

function updateSelectedUsersText() {
    const selectedUsers = [];
    $('input[name="selected_users"]:checked').each(function() {
        const userId = $(this).val();
        const userName = $(this).siblings('label').find('.user-name').text();
        selectedUsers.push(userName);
    });
    
    const textElement = $('#selectedUsersText');
    if (selectedUsers.length === 0) {
        textElement.text('Select users...');
    } else if (selectedUsers.length === 1) {
        textElement.text(selectedUsers[0]);
    } else if (selectedUsers.length <= 3) {
        textElement.text(selectedUsers.join(', '));
    } else {
        textElement.text(`${selectedUsers.length} users selected`);
    }
}

function shareUrl() {
    const urlId = currentUrlId;
    const urlType = currentUrlType;
    const permission = $('#sharePermission').val();
    
    if (!urlId) {
        showAlert('No URL selected for sharing.', 'warning');
        return;
    }
    
    // Get selected users
    const selectedUsers = [];
    $('input[name="selected_users"]:checked').each(function() {
        selectedUsers.push($(this).val());
    });
    
    if (selectedUsers.length === 0) {
        showAlert('Please select at least one user to share with.', 'warning');
        return;
    }
    
    // Share with each selected user
    let successCount = 0;
    let errorCount = 0;
    
    selectedUsers.forEach(userId => {
        $.ajax({
            url: '../ajax/urls_handler.php',
            method: 'POST',
            data: {
                action: 'share_url',
                url_id: urlId,
                url_type: urlType,
                shared_with_user_id: userId,
                permission: permission
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    successCount++;
                } else {
                    errorCount++;
                    console.error('Share URL error:', response ? response.message : 'Unknown error');
                }
                
                // Check if all requests are complete
                if (successCount + errorCount === selectedUsers.length) {
                    if (errorCount === 0) {
                        showAlert(`URL shared successfully with ${successCount} user(s)!`, 'success');
                        loadSharingList(urlId, urlType);
                        // Clear selections
                        $('input[name="selected_users"]:checked').prop('checked', false);
                        updateSelectedUsersText();
                        // Close dropdown
                        $('#usersDropdownMenu').removeClass('show');
                        $('#usersDropdownToggle').removeClass('active');
                        $('#usersDropdownToggle').closest('.dropdown-checkbox-container').removeClass('open');
                    } else if (successCount === 0) {
                        const errorMsg = response && response.message ? response.message : 'Failed to share URL with any users.';
                        showAlert(errorMsg, 'danger');
                    } else {
                        showAlert(`URL shared with ${successCount} user(s), failed with ${errorCount} user(s).`, 'warning');
                        loadSharingList(urlId, urlType);
                        // Clear selections
                        $('input[name="selected_users"]:checked').prop('checked', false);
                        updateSelectedUsersText();
                        // Close dropdown
                        $('#usersDropdownMenu').removeClass('show');
                        $('#usersDropdownToggle').removeClass('active');
                        $('#usersDropdownToggle').closest('.dropdown-checkbox-container').removeClass('open');
                    }
                }
            },
            error: function(xhr, status, error) {
                errorCount++;
                console.error('AJAX error sharing URL:', status, error, xhr.responseText);
                if (successCount + errorCount === selectedUsers.length) {
                    if (successCount === 0) {
                        let errorMsg = 'Failed to share URL with any users.';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.message) {
                                errorMsg = response.message;
                            }
                        } catch (e) {
                            // Use default error message
                        }
                        showAlert(errorMsg, 'danger');
                    } else {
                        showAlert(`URL shared with ${successCount} user(s), failed with ${errorCount} user(s).`, 'warning');
                        loadSharingList(urlId, urlType);
                    }
                    // Clear selections
                    $('input[name="selected_users"]:checked').prop('checked', false);
                    updateSelectedUsersText();
                    // Close dropdown
                    $('#usersDropdownMenu').removeClass('show');
                    $('#usersDropdownToggle').removeClass('active');
                    $('#usersDropdownToggle').closest('.dropdown-checkbox-container').removeClass('open');
                }
            }
        });
    });
}

function loadSharingList(urlId, urlType) {
    if (!urlId) return;
    
    $.ajax({
        url: '../ajax/urls_handler.php',
        method: 'POST',
        data: {
            action: 'get_sharing_list',
            url_id: urlId,
            url_type: urlType
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displaySharingList(response.shared_users);
            } else {
                $('#sharingList').html('<p class="text-muted">No users shared with yet.</p>');
            }
        },
        error: function() {
            $('#sharingList').html('<p class="text-muted">Error loading sharing list.</p>');
        }
    });
}

function displaySharingList(sharedUsers) {
    const container = $('#sharingList');
    
    if (!sharedUsers || sharedUsers.length === 0) {
        container.html('<p class="text-muted">No users shared with yet.</p>');
        return;
    }
    
    let html = '<div class="sharing-list-header"><h6><i class="fas fa-users"></i> Shared With</h6></div>';
    html += '<div class="sharing-items">';
    
    sharedUsers.forEach(user => {
        const permissionBadge = getPermissionBadge(user.permission);
        html += `
            <div class="sharing-item">
                <div class="sharing-user-info">
                    <div class="sharing-user-name">${escapeHtml(user.user_name)}</div>
                    <div class="sharing-user-permission">${permissionBadge}</div>
                </div>
                <div class="sharing-user-actions">
                    <button class="sharing-action-btn" onclick="removeSharing(${user.id})" title="Remove sharing">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.html(html);
}

function getPermissionBadge(permission) {
    const badges = {
        'view': '<span class="permission-badge view">View Only</span>',
        'comment': '<span class="permission-badge comment">View & Comment</span>',
        'edit': '<span class="permission-badge edit">Full Access</span>'
    };
    return badges[permission] || badges['view'];
}

function removeSharing(sharingId) {
    if (confirm('Are you sure you want to remove this user\'s access to the URL?')) {
        $.ajax({
            url: '../ajax/urls_handler.php',
            method: 'POST',
            data: {
                action: 'remove_sharing',
                sharing_id: sharingId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    loadSharingList(currentUrlId, currentUrlType);
                } else {
                    showAlert('Error removing sharing: ' + response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error removing sharing. Please try again.', 'danger');
            }
        });
    }
}

function refreshSharingList() {
    if (currentUrlId) {
        loadSharingList(currentUrlId, currentUrlType);
    }
}

function loadSharedUrls() {
    $.ajax({
        url: '../ajax/urls_handler.php',
        method: 'POST',
        data: {
            action: 'get_shared_urls'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displaySharedUrls(response.shared_urls);
            } else {
                $('#sharedUrlsContainer').html('<p class="text-muted">Error loading shared URLs.</p>');
            }
        },
        error: function() {
            $('#sharedUrlsContainer').html('<p class="text-muted">Error loading shared URLs.</p>');
        }
    });
}

function displaySharedUrls(sharedUrls) {
    const container = $('#sharedUrlsContainer');
    
    if (sharedUrls.length === 0) {
        container.html(`
            <div class="no-urls">
                <i class="fas fa-share-alt"></i>
                <h3>No shared URLs</h3>
                <p>No URLs have been shared with you yet.</p>
            </div>
        `);
        return;
    }
    
    let html = '<div class="shared-urls-grid">';
    
    sharedUrls.forEach(url => {
        const permissionText = getPermissionText(url.permission);
        const createdDate = new Date(url.created_at).toLocaleDateString();
        
        html += `
            <div class="shared-url-card" data-url="${escapeHtml(url.url)}" onclick="openUrl('${escapeHtml(url.url)}')">
                <div class="url-header">
                    <h4 class="url-title">${escapeHtml(url.title)}</h4>
                </div>
                <div class="shared-url-meta">
                    <span class="shared-url-owner">
                        <i class="fas fa-user"></i> ${escapeHtml(url.owner_name)}
                    </span>
                    <span class="shared-url-permission">
                        <i class="fas fa-key"></i> ${permissionText}
                    </span>
                </div>
                <div class="url-link">
                    <i class="fas fa-external-link-alt"></i> ${escapeHtml(url.url)}
                </div>
                ${url.description ? `<div class="url-description">${escapeHtml(url.description)}</div>` : ''}
                <div class="url-meta">
                    <div class="url-date">
                        <i class="fas fa-calendar"></i>
                        ${createdDate}
                    </div>
                    ${url.category ? `<span class="url-category">${escapeHtml(url.category)}</span>` : ''}
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.html(html);
}

function getPermissionText(permission) {
    const permissions = {
        'view': 'View Only',
        'comment': 'View & Comment',
        'edit': 'Full Access'
    };
    return permissions[permission] || 'View Only';
}
</script>

<?php require_once "../includes/footer.php"; ?>
