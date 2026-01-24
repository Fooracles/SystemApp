<?php
$page_title = "My Notes";
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
    --glass-bg: rgba(255, 255, 255, 0.06);
    --glass-border: rgba(255, 255, 255, 0.12);
    --glass-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    --glass-blur: blur(8px);
    
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

.notes-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: var(--space-xl);
    background: linear-gradient(135deg, var(--primary-50) 0%, var(--secondary-50) 100%);
    min-height: 100vh;
}

.notes-header {
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

.notes-header h2 {
    color: var(--fms-primary);
    font-size: var(--text-3xl);
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.notes-header h2 i {
    color: var(--fms-secondary);
}

.notes-filters {
    display: flex;
    gap: var(--space-lg);
    align-items: center;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    min-width: 280px;
}

.search-box input {
    width: 100%;
    padding: var(--space-md) 50px var(--space-md) var(--space-lg);
    border: 2px solid var(--primary-200);
    border-radius: 50px;
    font-size: var(--text-base);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: var(--glass-blur);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: var(--fms-dark);
}

.search-box input:focus {
    outline: none;
    border-color: var(--fms-primary);
    box-shadow: 0 0 0 4px var(--primary-100);
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-1px);
}

.search-box input::placeholder {
    color: var(--fms-accent);
    font-weight: 500;
}

.search-box i {
    position: absolute;
    right: var(--space-lg);
    top: 50%;
    transform: translateY(-50%);
    color: var(--fms-primary);
    font-size: var(--text-lg);
}

.filter-select {
    padding: var(--space-md) var(--space-lg);
    border: 2px solid var(--primary-200);
    border-radius: 50px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: var(--glass-blur);
    font-size: var(--text-base);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: var(--fms-dark);
}

.filter-select:focus {
    outline: none;
    border-color: var(--fms-primary);
    box-shadow: 0 0 0 4px var(--primary-100);
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-1px);
}

.btn-add-note {
    background: linear-gradient(135deg, var(--fms-primary) 0%, var(--fms-secondary) 100%);
    color: white;
    border: none;
    padding: var(--space-md) var(--space-xl);
    border-radius: 50px;
    font-weight: 600;
    font-size: var(--text-base);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 25px rgba(47, 60, 126, 0.3);
    position: relative;
    overflow: hidden;
}

.btn-add-note::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn-add-note:hover::before {
    left: 100%;
}

.btn-add-note:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(47, 60, 126, 0.4);
}

.btn-add-note:active {
    transform: translateY(-1px);
}

.notes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-md);
    margin-bottom: var(--space-2xl);
}

.note-card {
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

.note-card::after {
    content: '👁';
    position: absolute;
    top: var(--space-sm);
    right: var(--space-sm);
    color: var(--fms-accent);
    font-size: var(--text-sm);
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.note-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(47, 60, 126, 0.1);
    border-color: var(--primary-300);
    background: rgba(255, 255, 255, 0.15);
}

.note-card:hover::after {
    opacity: 1;
}

.note-card:active {
    transform: translateY(0);
    box-shadow: 0 4px 15px rgba(47, 60, 126, 0.1);
}

.note-card.important {
    border-color: var(--secondary-300);
    background: linear-gradient(135deg, rgba(184, 80, 66, 0.1) 0%, var(--glass-bg) 100%);
}

.note-card.important::before {
    background: linear-gradient(90deg, var(--fms-secondary), #ff6b6b);
    opacity: 1;
}

.note-card.completed {
    opacity: 0.8;
    background: linear-gradient(135deg, rgba(137, 141, 145, 0.1) 0%, var(--glass-bg) 100%);
}

.note-card.completed .note-title {
    text-decoration: line-through;
    color: var(--fms-accent);
}

.note-card.completed::before {
    background: linear-gradient(90deg, var(--fms-accent), #6c757d);
    opacity: 1;
}

.note-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-sm);
    gap: var(--space-sm);
}

.note-title {
    font-size: var(--text-base);
    font-weight: 600;
    color: var(--fms-dark);
    margin: 0;
    line-height: 1.4;
    flex: 1;
    transition: color 0.3s ease;
}

.note-card:hover .note-title {
    color: var(--fms-primary);
}

.note-actions {
    display: flex;
    gap: var(--space-xs);
    align-items: center;
    opacity: 0;
    transform: translateX(5px);
    transition: all 0.3s ease;
}

.note-card:hover .note-actions {
    opacity: 1;
    transform: translateX(0);
}

.note-action-btn {
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

.note-action-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    color: var(--fms-primary);
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(47, 60, 126, 0.2);
}

.note-action-btn.important {
    color: var(--fms-secondary);
}

.note-action-btn.important:hover {
    color: #ff6b6b;
    background: rgba(184, 80, 66, 0.1);
}

.note-action-btn.completed {
    color: var(--success);
}

.note-action-btn.completed:hover {
    color: #20c997;
    background: rgba(40, 167, 69, 0.1);
}

.note-content {
    color: var(--fms-dark);
    line-height: 1.5;
    margin-bottom: var(--space-sm);
    max-height: 80px;
    overflow: hidden;
    position: relative;
    font-size: var(--text-xs);
    font-weight: 400;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
}

.note-content.expanded {
    max-height: none;
    -webkit-line-clamp: unset;
}

.note-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: var(--text-xs);
    color: var(--fms-accent);
    margin-top: var(--space-sm);
    padding-top: var(--space-sm);
    border-top: 1px solid var(--glass-border);
    font-weight: 500;
}

.note-date {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    color: var(--fms-accent);
}

.note-date i {
    color: var(--fms-primary);
}

.note-reminder {
    background: linear-gradient(135deg, var(--fms-secondary), #ff6b6b);
    color: white;
    padding: 2px var(--space-xs);
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    box-shadow: 0 1px 4px rgba(184, 80, 66, 0.2);
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.note-shared {
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

/* Note Form Section */
.note-form-section {
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

.note-form-section::before {
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
    color: var(--fms-primary);
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

/* Note Detail Section */
.note-detail-section {
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: var(--space-xl);
    margin-bottom: var(--space-2xl);
    box-shadow: var(--glass-shadow);
    position: relative;
    z-index: 1;
    border-left: 4px solid var(--fms-primary);
    overflow: visible !important;
}

.note-detail-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--fms-primary), var(--fms-secondary));
    border-radius: 20px 20px 0 0;
}

.detail-container {
    max-width: 800px;
    margin: 0 auto;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-xl);
    padding-bottom: var(--space-md);
    border-bottom: 2px solid var(--glass-border);
}

.detail-header h3 {
    color: var(--fms-primary);
    margin: 0;
    font-weight: 700;
    font-size: var(--text-2xl);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.detail-header h3 i {
    color: var(--fms-secondary);
}

.note-detail-info {
    margin-bottom: var(--space-2xl);
}

.note-detail-info h4 {
    color: var(--fms-dark);
    margin-bottom: var(--space-lg);
    font-size: var(--text-3xl);
    font-weight: 700;
    line-height: 1.3;
}

.note-detail-meta {
    display: flex;
    gap: var(--space-lg);
    margin-bottom: var(--space-xl);
    font-size: var(--text-sm);
    color: var(--fms-accent);
    flex-wrap: wrap;
}

.note-detail-content {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: var(--glass-blur);
    padding: var(--space-xl);
    border-radius: 15px;
    line-height: 1.7;
    color: var(--fms-dark);
    border: 1px solid var(--glass-border);
    min-height: 100px;
    font-size: var(--text-base);
}

.note-detail-content h1,
.note-detail-content h2,
.note-detail-content h3,
.note-detail-content h4,
.note-detail-content h5,
.note-detail-content h6 {
    color: var(--fms-primary);
    margin-top: var(--space-lg);
    margin-bottom: var(--space-sm);
}

.note-detail-content h1:first-child,
.note-detail-content h2:first-child,
.note-detail-content h3:first-child,
.note-detail-content h4:first-child,
.note-detail-content h5:first-child,
.note-detail-content h6:first-child {
    margin-top: 0;
}

.note-detail-content p {
    margin-bottom: var(--space-md);
}

.note-detail-content ul,
.note-detail-content ol {
    margin-bottom: var(--space-md);
    padding-left: var(--space-xl);
}

.note-detail-content li {
    margin-bottom: var(--space-xs);
}

.note-detail-content blockquote {
    border-left: 4px solid var(--fms-secondary);
    padding-left: var(--space-lg);
    margin: var(--space-lg) 0;
    font-style: italic;
    color: var(--fms-accent);
}

.note-detail-content code {
    background: rgba(47, 60, 126, 0.1);
    padding: 2px var(--space-xs);
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: var(--text-sm);
}

.note-detail-content pre {
    background: rgba(47, 60, 126, 0.1);
    padding: var(--space-md);
    border-radius: 8px;
    overflow-x: auto;
    margin: var(--space-md) 0;
}

.note-detail-content pre code {
    background: none;
    padding: 0;
}

/* Overdue reminder styling */
.note-reminder.overdue {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 600;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

/* Text muted styling for empty states */
.text-muted {
    color: var(--fms-accent) !important;
    font-style: italic;
}

/* Improved note detail header with icon */
.detail-header h3::before {
    content: '\f15c';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    margin-right: var(--space-sm);
    color: var(--fms-secondary);
}

/* Close button styling */
.detail-header .btn-outline-danger {
    border-color: #dc3545;
    color: #dc3545;
    background: transparent;
    transition: all 0.3s ease;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
}

.detail-header .btn-outline-danger:hover {
    background: #dc3545;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.detail-header .btn-outline-danger:active {
    transform: translateY(0);
    box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
}

/* Loading state for note detail */
.note-detail-content .loading {
    text-align: center;
    padding: var(--space-2xl);
    color: var(--fms-accent);
}

.note-detail-content .loading i {
    font-size: var(--text-2xl);
    margin-bottom: var(--space-md);
    color: var(--fms-primary);
}

.note-detail-content .loading p {
    margin: 0;
    font-size: var(--text-base);
    font-weight: 500;
}

/* Responsive design for note detail */
@media (max-width: 768px) {
    .note-detail-section {
        padding: var(--space-lg);
        margin-bottom: var(--space-lg);
    }
    
    .detail-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-md);
    }
    
    .detail-header h3 {
        font-size: var(--text-xl);
    }
    
    .note-detail-info h4 {
        font-size: var(--text-2xl);
    }
    
    .note-detail-meta {
        flex-direction: column;
        gap: var(--space-sm);
    }
    
    .note-detail-content {
        padding: var(--space-lg);
        font-size: var(--text-sm);
    }
}

.form-group {
    margin-bottom: 0;
}

.form-label {
    font-weight: 600;
    color: var(--fms-dark);
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
    color: var(--fms-dark);
    font-weight: 500;
}

.form-control:focus {
    outline: none;
    border-color: var(--fms-primary);
    box-shadow: 0 0 0 3px var(--primary-100);
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-1px);
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

/* Quill Editor Styling */
.ql-editor {
    font-size: var(--text-sm);
    line-height: 1.5;
    color: var(--fms-dark);
    min-height: 150px;
}

.ql-toolbar {
    border-top: 2px solid var(--primary-200);
    border-left: 2px solid var(--primary-200);
    border-right: 2px solid var(--primary-200);
    border-bottom: none;
    border-radius: 10px 10px 0 0;
    background: rgba(255, 255, 255, 0.9);
}

.ql-container {
    border-bottom: 2px solid var(--primary-200);
    border-left: 2px solid var(--primary-200);
    border-right: 2px solid var(--primary-200);
    border-top: none;
    border-radius: 0 0 10px 10px;
    background: rgba(255, 255, 255, 0.9);
    position: relative;
    z-index: 1;
}

/* Ensure the editor container allows dropdowns to appear above */
#noteContentEditor {
    position: relative;
    z-index: 1;
}

/* Make sure toolbar has proper z-index */
.ql-toolbar {
    position: relative;
    z-index: 2;
}

.ql-toolbar .ql-stroke {
    stroke: var(--fms-primary);
}

.ql-toolbar .ql-fill {
    fill: var(--fms-primary);
}

.ql-toolbar button:hover {
    color: var(--fms-primary);
}

.ql-toolbar button.ql-active {
    color: var(--fms-primary);
    background-color: var(--primary-100);
}

.ql-toolbar .ql-picker-label {
    color: var(--fms-dark);
}

.ql-toolbar .ql-picker-options {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid var(--primary-200);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(47, 60, 126, 0.1);
    z-index: 9999 !important;
}

.ql-toolbar .ql-picker {
    z-index: 9999 !important;
}

.ql-toolbar .ql-picker-label {
    z-index: 9999 !important;
}

.ql-toolbar .ql-color-picker,
.ql-toolbar .ql-background {
    z-index: 9999 !important;
}

.ql-toolbar .ql-color-picker .ql-picker-options,
.ql-toolbar .ql-background .ql-picker-options {
    z-index: 9999 !important;
}

/* Ensure all Quill dropdowns have high z-index */
.ql-toolbar .ql-picker-options,
.ql-toolbar .ql-color-picker .ql-picker-options,
.ql-toolbar .ql-background .ql-picker-options,
.ql-toolbar .ql-font .ql-picker-options,
.ql-toolbar .ql-size .ql-picker-options,
.ql-toolbar .ql-header .ql-picker-options,
.ql-toolbar .ql-align .ql-picker-options {
    z-index: 9999 !important;
    position: absolute !important;
}

/* Additional Quill dropdown elements */
.ql-toolbar .ql-picker-item,
.ql-toolbar .ql-picker-item:hover,
.ql-toolbar .ql-picker-item.ql-selected {
    z-index: 9999 !important;
}

/* Color picker specific styling */
.ql-toolbar .ql-color-picker .ql-picker-options,
.ql-toolbar .ql-background .ql-picker-options {
    z-index: 9999 !important;
    position: absolute !important;
    top: 100% !important;
    left: 0 !important;
    margin-top: 4px !important;
}

/* Font picker specific styling */
.ql-toolbar .ql-font .ql-picker-options {
    z-index: 9999 !important;
    position: absolute !important;
    top: 100% !important;
    left: 0 !important;
    margin-top: 4px !important;
}

/* Header picker specific styling */
.ql-toolbar .ql-header .ql-picker-options {
    z-index: 9999 !important;
    position: absolute !important;
    top: 100% !important;
    left: 0 !important;
    margin-top: 4px !important;
}

/* Date/Time Picker Styling */
input[type="datetime-local"] {
    cursor: pointer;
    position: relative;
}

input[type="datetime-local"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: auto;
    height: auto;
    color: transparent;
    background: transparent;
    margin: 0;
    padding: 0;
    border: none;
    outline: none;
}

input[type="datetime-local"]::-webkit-datetime-edit {
    cursor: pointer;
}

input[type="datetime-local"]::-webkit-datetime-edit-fields-wrapper {
    cursor: pointer;
}

input[type="datetime-local"]::-webkit-datetime-edit-text {
    cursor: pointer;
}

input[type="datetime-local"]::-webkit-datetime-edit-month-field,
input[type="datetime-local"]::-webkit-datetime-edit-day-field,
input[type="datetime-local"]::-webkit-datetime-edit-year-field,
input[type="datetime-local"]::-webkit-datetime-edit-hour-field,
input[type="datetime-local"]::-webkit-datetime-edit-minute-field {
    cursor: pointer;
}

/* Firefox support */
input[type="datetime-local"]::-moz-placeholder {
    cursor: pointer;
}

/* Ensure the entire input area is clickable */
.form-control[type="datetime-local"] {
    cursor: pointer;
    user-select: none;
}

/* Responsive form layout */
@media (max-width: 768px) {
    .row.align-items-end .col-md-6,
    .row.align-items-end .col-md-3 {
        margin-bottom: 15px;
    }
    
    .btn-block {
        width: 100%;
    }
    
    .ql-toolbar {
        padding: 8px;
    }
    
    .ql-toolbar .ql-formats {
        margin-right: 8px;
    }
}

.form-check {
    display: flex;
    align-items: center;
    gap: var(--space-md);
    margin-bottom: var(--space-lg);
    padding: var(--space-md);
    background: rgba(255, 255, 255, 0.5);
    border-radius: 15px;
    border: 1px solid var(--glass-border);
    transition: all 0.3s ease;
}

.form-check:hover {
    background: rgba(255, 255, 255, 0.7);
    transform: translateY(-1px);
}

.form-check-input {
    width: 20px;
    height: 20px;
    margin: 0;
    accent-color: var(--fms-primary);
}

.form-check-label {
    font-weight: 600;
    color: var(--fms-dark);
    margin: 0;
    font-size: var(--text-base);
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(135deg, var(--fms-primary) 0%, var(--fms-secondary) 100%);
    border: none;
    padding: var(--space-md) var(--space-xl);
    border-radius: 50px;
    font-weight: 600;
    font-size: var(--text-base);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 25px rgba(47, 60, 126, 0.3);
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
    box-shadow: 0 12px 35px rgba(47, 60, 126, 0.4);
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

.sharing-section {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: var(--glass-blur);
    padding: var(--space-xl);
    border-radius: 15px;
    margin-top: var(--space-xl);
    border: 1px solid var(--glass-border);
    position: relative;
    /* z-index managed by Z-INDEX MANAGEMENT SYSTEM above */
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
    color: var(--fms-primary);
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
    color: var(--fms-dark);
    font-size: var(--text-sm);
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

/* ========================================
   Z-INDEX MANAGEMENT SYSTEM
   ======================================== */

/* ULTRA-HIGH Z-INDEX: Dropdown System (Highest Priority) */
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
    position: fixed !important;
}

.user-checkbox-item {
    z-index: 2147483647 !important;
    position: relative !important;
}

/* HIGH Z-INDEX: Modal and Overlay Elements */
.modal, .overlay, .popup {
    z-index: 10000;
}

/* MEDIUM Z-INDEX: Page Sections - Lower to allow dropdown above */
.sharing-section {
    z-index: 1;
    overflow: visible !important;
    position: relative;
}

.comments-section {
    z-index: 1;
    overflow: visible !important;
    position: relative;
}

/* LOW Z-INDEX: Content Elements */
.sharing-list,
.sharing-list-header,
.sharing-items,
.sharing-item,
.note-card,
.note-form-section {
    z-index: 1;
}

/* Ensure sharing controls don't clip dropdown */
.sharing-controls-row,
.sharing-control-item {
    overflow: visible !important;
    position: relative;
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
    /* z-index managed by Z-INDEX MANAGEMENT SYSTEM above */
    max-height: 200px;
    overflow-y: auto;
    display: none;
    min-width: 100%;
}

/* Ensure dropdown doesn't break layout in side-by-side view */
/* Note: z-index is already set above as 99998 */

/* Make sure the dropdown menu stays within bounds */
@media (min-width: 769px) {
    .dropdown-checkbox-menu {
        min-width: 300px;
    }
}

.dropdown-checkbox-menu.show {
    display: block !important;
    animation: slideDown 0.3s ease;
    position: absolute !important;
    /* z-index managed by Z-INDEX MANAGEMENT SYSTEM above */
}

/* Additional z-index fixes for dropdown items */
.user-checkbox-item {
    position: relative;
    /* z-index managed by Z-INDEX MANAGEMENT SYSTEM above */
}

/* Ensure dropdown appears above all other elements */
/* Note: z-index managed by Z-INDEX MANAGEMENT SYSTEM above */

/* Ultra-high z-index for dropdown when open */
.dropdown-checkbox-container.open .dropdown-checkbox-menu {
    /* z-index managed by Z-INDEX MANAGEMENT SYSTEM above */
    position: fixed !important;
    top: auto !important;
    left: auto !important;
    right: auto !important;
    width: auto !important;
    min-width: 300px !important;
}

/* Ensure dropdown items are clickable */
.dropdown-checkbox-menu .user-checkbox-item {
    position: relative;
    /* z-index managed by Z-INDEX MANAGEMENT SYSTEM above */
    pointer-events: auto;
}

/* Force dropdown above all other content */
/* Note: z-index managed by Z-INDEX MANAGEMENT SYSTEM above */

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
}

.user-checkbox-item:hover {
    background: rgba(47, 60, 126, 0.1);
    transform: translateX(2px);
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
}

.sharing-control-item {
    flex: 1;
    min-width: 200px;
    display: flex;
    flex-direction: column;
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

/* Responsive adjustments */
@media (max-width: 768px) {
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

/* Shared Notes Section */
.shared-notes-section {
    margin-top: var(--space-2xl);
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: var(--space-xl);
    box-shadow: var(--glass-shadow);
    border-left: 4px solid var(--info);
}

.shared-notes-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--info), #17a2b8);
    border-radius: 20px 20px 0 0;
}

.shared-notes-header {
    text-align: center;
    margin-bottom: var(--space-2xl);
    padding-bottom: var(--space-lg);
    border-bottom: 2px solid var(--glass-border);
}

.shared-notes-header h2 {
    color: var(--fms-primary);
    font-size: var(--text-3xl);
    font-weight: 700;
    margin: 0 0 var(--space-sm) 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-sm);
}

.shared-notes-header h2 i {
    color: var(--info);
}

.shared-notes-subtitle {
    color: var(--fms-accent);
    font-size: var(--text-lg);
    font-weight: 500;
    margin: 0;
}

.shared-note-card {
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

.shared-note-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(23, 162, 184, 0.1);
    border-color: var(--info);
    background: rgba(255, 255, 255, 0.15);
}

.shared-note-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-sm);
    gap: var(--space-sm);
}

.shared-note-title {
    font-size: var(--text-base);
    font-weight: 600;
    color: var(--fms-dark);
    margin: 0;
    line-height: 1.4;
    flex: 1;
    transition: color 0.3s ease;
}

.shared-note-card:hover .shared-note-title {
    color: var(--info);
}

.shared-note-actions {
    display: flex;
    gap: var(--space-xs);
    align-items: center;
    opacity: 0;
    transform: translateX(5px);
    transition: all 0.3s ease;
}

.shared-note-card:hover .shared-note-actions {
    opacity: 1;
    transform: translateX(0);
}

.shared-note-action-btn {
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

.shared-note-action-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    color: var(--info);
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(23, 162, 184, 0.2);
}

.shared-note-owner {
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

.shared-note-permission {
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

.shared-notes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-md);
}

.shared-note-meta {
    margin-bottom: var(--space-sm);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-xs);
}

.sharing-section h6 {
    color: var(--fms-primary);
    font-weight: 700;
    margin-bottom: var(--space-lg);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.sharing-section h6 i {
    color: var(--fms-secondary);
}

.sharing-list {
    max-height: 200px;
    overflow-y: auto;
    margin-top: var(--space-lg);
    position: relative;
    z-index: 1;
}

/* Ensure all sharing list elements have lower z-index than dropdown */
.sharing-list .sharing-item,
.sharing-list .sharing-user-info,
.sharing-list .sharing-user-name,
.sharing-list .sharing-user-permission,
.sharing-list .sharing-user-actions,
.sharing-list .sharing-action-btn,
.sharing-list .permission-badge {
    position: relative;
    z-index: 1 !important;
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

.comments-section {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: var(--glass-blur);
    padding: var(--space-xl);
    border-radius: 15px;
    margin-top: var(--space-xl);
    border: 1px solid var(--glass-border);
    position: relative;
    /* z-index managed by Z-INDEX MANAGEMENT SYSTEM above */
}

.comments-section h6 {
    color: var(--fms-primary);
    font-weight: 700;
    margin-bottom: var(--space-lg);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.comments-section h6 i {
    color: var(--fms-secondary);
}

.comment {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: var(--glass-blur);
    padding: var(--space-lg);
    border-radius: 12px;
    margin-bottom: var(--space-lg);
    border: 1px solid var(--glass-border);
    transition: all 0.3s ease;
}

.comment:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-sm);
}

.comment-author {
    font-weight: 700;
    color: var(--fms-dark);
    font-size: var(--text-sm);
}

.comment-date {
    font-size: var(--text-xs);
    color: var(--fms-accent);
    font-weight: 500;
}

.comment-content {
    color: var(--fms-dark);
    line-height: 1.6;
    font-size: var(--text-sm);
}

.no-notes {
    text-align: center;
    padding: var(--space-2xl) var(--space-xl);
    color: var(--fms-accent);
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    margin: var(--space-2xl) 0;
}

.no-notes i {
    font-size: 4rem;
    color: var(--primary-200);
    margin-bottom: var(--space-xl);
    display: block;
}

.no-notes h3 {
    color: var(--fms-primary);
    margin-bottom: var(--space-sm);
    font-size: var(--text-2xl);
    font-weight: 700;
}

.no-notes p {
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

.note-card.dragging {
    opacity: 0.8;
    transform: scale(1.05) rotate(2deg);
    z-index: 1000;
    box-shadow: 0 20px 50px rgba(47, 60, 126, 0.4);
}

.notes-grid.drag-over {
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

@media (max-width: 768px) {
    .notes-container {
        padding: var(--space-md);
    }
    
    .notes-header {
        flex-direction: column;
        align-items: stretch;
        gap: var(--space-md);
        padding: var(--space-lg);
    }
    
    .notes-header h2 {
        font-size: var(--text-2xl);
        text-align: center;
    }
    
    .notes-filters {
        justify-content: center;
        flex-direction: column;
        gap: var(--space-md);
    }
    
    .search-box {
        min-width: 100%;
    }
    
    .notes-grid {
        grid-template-columns: 1fr;
        gap: var(--space-sm);
    }
    
    .note-card {
        padding: var(--space-sm);
    }
    
    .note-actions {
        opacity: 1;
        transform: translateX(0);
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
}
</style>

<style id="notes-dark-overrides">
/* Dark theme overrides to match app-wide styling */
body .notes-container { background: transparent; color: var(--dark-text-primary); }

/* Header */
body .notes-header { background: rgba(17, 24, 39, 0.6); border: 1px solid var(--glass-border); box-shadow: var(--glass-shadow); }
body .notes-header h2 { color: var(--dark-text-primary); }
body .notes-header h2 i { color: var(--brand-secondary, #22d3ee); }

/* Inputs */
body .search-box input,
body .filter-select,
body .form-control { background: rgba(17, 24, 39, 0.6); border: 1px solid var(--glass-border); color: var(--dark-text-primary); }
body .search-box input::placeholder,
body .form-control::placeholder { color: rgba(255,255,255,0.5); }
body .search-box input:focus,
body .filter-select:focus,
body .form-control:focus { border-color: rgba(34,211,238,0.6); box-shadow: 0 0 0 3px rgba(34,211,238,0.15); background: rgba(17,24,39,0.8); }
body .search-box i { color: #93c5fd; }

/* Buttons */
body .btn-add-note,
body .btn-primary { background: linear-gradient(135deg, #0ea5e9, #22d3ee); border: 1px solid rgba(34,211,238,0.35); color: #0b1220; box-shadow: 0 0 18px rgba(34,211,238,0.35), 0 2px 6px rgba(0,0,0,0.4); }
body .btn-add-note:hover,
body .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 0 22px rgba(34,211,238,0.5), 0 4px 10px rgba(0,0,0,0.5); }
body .btn-secondary { background: rgba(255,255,255,0.08); border: 1px solid var(--glass-border); color: var(--dark-text-primary); box-shadow: 0 2px 8px rgba(0,0,0,0.35); }
body .btn-secondary:hover { background: rgba(255,255,255,0.12); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.45); }

/* Cards */
body .note-card,
body .note-form-section,
body .note-detail-section,
body .sharing-section,
body .comments-section { background: rgba(17, 24, 39, 0.6); border: 1px solid var(--glass-border); color: var(--dark-text-primary); }
body .note-card { border-left-color: rgba(34,211,238,0.7); }
body .note-card:hover { background: rgba(17,24,39,0.75); box-shadow: 0 10px 28px rgba(0,0,0,0.45); border-color: rgba(34,211,238,0.7); }
body .note-title { color: var(--dark-text-primary); }
body .note-card:hover .note-title { color: #93c5fd; }
body .note-content { color: rgba(255,255,255,0.78); }

/* Headings and labels for consistent readable contrast */
body .notes-container h1,
body .notes-container h2,
body .notes-container h3,
body .notes-container h4,
body .notes-container h5,
body .notes-container h6 { color: var(--dark-text-primary); }
body .form-label,
body label,
body .sharing-list-header h6,
body .comments-section h6,
body .shared-notes-header h2,
body .detail-header h3,
body .note-detail-info h4 { color: var(--dark-text-primary); }
body .note-meta,
body .note-detail-content p,
body .note-detail-content li,
body .shared-note-meta,
body .sharing-user-name,
body .comment-content { color: rgba(255,255,255,0.8); }
body .note-detail-content h1,
body .note-detail-content h2,
body .note-detail-content h3,
body .note-detail-content h4,
body .note-detail-content h5,
body .note-detail-content h6 { color: #93c5fd; }
body .notes-header i,
body .detail-header i,
body .sharing-list-header i,
body .comments-section h6 i,
body .shared-notes-header h2 i { color: #93c5fd; }
body .note-card::after { color: rgba(255,255,255,0.6); }

/* Action buttons */
body .note-action-btn { background: rgba(255,255,255,0.06); border: 1px solid var(--glass-border); color: rgba(255,255,255,0.85); }
body .note-action-btn:hover { background: rgba(34,211,238,0.15); color: #7dd3fc; box-shadow: 0 2px 8px rgba(0,0,0,0.35); }

/* Meta and muted */
body .note-meta,
body .note-date { color: rgba(255,255,255,0.65); }
body .note-date i { color: #93c5fd; }
body .text-muted { color: rgba(255,255,255,0.65) !important; }

/* Dropdown checkbox - Force dark theme with !important and maximum z-index */
body .dropdown-checkbox-container { 
    overflow: visible !important; 
    z-index: 2147483647 !important;
}
body .dropdown-checkbox-toggle { 
    background: rgba(17,24,39,0.6) !important; 
    border: 1px solid var(--glass-border) !important; 
    color: var(--dark-text-primary) !important; 
}
body .dropdown-checkbox-toggle:hover { 
    background: rgba(17,24,39,0.8) !important; 
    border-color: rgba(34,211,238,0.6) !important; 
}
body .dropdown-checkbox-toggle.active { 
    background: rgba(17,24,39,0.8) !important; 
    border-color: rgba(34,211,238,0.6) !important; 
    box-shadow: 0 0 0 3px rgba(34,211,238,0.15) !important;
}
body .dropdown-checkbox-toggle i { 
    color: rgba(255,255,255,0.7) !important; 
}
body .dropdown-checkbox-toggle.active i { 
    color: #93c5fd !important; 
}
body #selectedUsersText { 
    color: var(--dark-text-primary) !important; 
}

/* Dropdown menu container - Maximum z-index to appear above everything */
body .dropdown-checkbox-menu { 
    background: rgba(6,8,14,0.98) !important; 
    border: 1px solid var(--glass-border) !important; 
    box-shadow: 0 12px 35px rgba(0,0,0,0.45) !important; 
    z-index: 2147483647 !important;
}
body .dropdown-checkbox-menu.show {
    z-index: 2147483647 !important;
}
body .dropdown-checkbox-container.open .dropdown-checkbox-menu {
    z-index: 2147483647 !important;
}

/* Dropdown menu content - user items */
body .dropdown-checkbox-menu .user-search-container {
    background: rgba(17,24,39,0.8) !important;
    border-bottom-color: rgba(34,211,238,0.3) !important;
}

body .dropdown-checkbox-menu .user-search-input {
    background: rgba(6,8,14,0.9) !important;
    border-color: rgba(34,211,238,0.3) !important;
    color: var(--dark-text-primary) !important;
}

body .dropdown-checkbox-menu .user-search-input:focus {
    border-color: rgba(34,211,238,0.6) !important;
    background: rgba(6,8,14,1) !important;
}

body .dropdown-checkbox-menu .user-search-input::placeholder {
    color: rgba(255,255,255,0.5) !important;
}

body .dropdown-checkbox-menu .user-search-icon {
    color: rgba(34,211,238,0.7) !important;
}

body .dropdown-checkbox-menu .user-checkbox-item { 
    background: transparent !important; 
    color: var(--dark-text-primary) !important; 
}
body .dropdown-checkbox-menu .user-checkbox-item:hover { 
    background: rgba(34,211,238,0.12) !important; 
}
body .dropdown-checkbox-menu .user-name { 
    color: var(--dark-text-primary) !important; 
}
body .dropdown-checkbox-menu .user-type { 
    color: white !important; 
}
body .dropdown-checkbox-menu .loading-users { 
    color: rgba(255,255,255,0.7) !important; 
}
body .dropdown-checkbox-menu p.text-muted,
body .dropdown-checkbox-menu .text-muted { 
    color: rgba(255,255,255,0.7) !important; 
}
body .dropdown-checkbox-menu input[type="checkbox"] { 
    accent-color: #22d3ee !important; 
}

/* Sharing items */
body .sharing-item { background: rgba(255,255,255,0.06); }
body .sharing-item:hover { background: rgba(34,211,238,0.12); }

/* Notifications */
body .notification { box-shadow: 0 8px 28px rgba(0,0,0,0.45); border: 1px solid var(--glass-border); }

/* Distinct hover/active highlights */
body .note-card:active { transform: translateY(0); box-shadow: 0 4px 15px rgba(0,0,0,0.4); }

/* Focus rings for accessibility */
body .btn:focus,
body .btn:focus-visible,
body .form-control:focus,
body .filter-select:focus,
body .dropdown-checkbox-toggle:focus { outline: none; box-shadow: 0 0 0 3px rgba(34,211,238,0.2); }

/* Ensure select/option contrast */
body select.filter-select option { background: #0b1220; color: var(--dark-text-primary); }
body select.form-control,
body #sharePermission { background: rgba(17,24,39,0.6) !important; border: 1px solid var(--glass-border) !important; color: var(--dark-text-primary) !important; }
body select.form-control option,
body #sharePermission option { background: #0b1220 !important; color: var(--dark-text-primary) !important; }

/* Comment textarea */
body #newComment { background: rgba(17,24,39,0.6) !important; border: 1px solid var(--glass-border) !important; color: var(--dark-text-primary) !important; }
body #newComment::placeholder { color: rgba(255,255,255,0.5) !important; }
body #newComment:focus { background: rgba(17,24,39,0.8) !important; border-color: rgba(34,211,238,0.6) !important; }

/* Quill editor dark theme */
body .ql-container { background: rgba(17,24,39,0.6) !important; border-color: var(--glass-border) !important; color: var(--dark-text-primary) !important; }
body .ql-toolbar { background: rgba(17,24,39,0.7) !important; border-color: var(--glass-border) !important; }
body .ql-toolbar .ql-stroke { stroke: rgba(255,255,255,0.8) !important; }
body .ql-toolbar .ql-fill { fill: rgba(255,255,255,0.8) !important; }
body .ql-toolbar button:hover { color: #93c5fd !important; }
body .ql-toolbar button.ql-active { color: #93c5fd !important; background-color: rgba(34,211,238,0.15) !important; }
body .ql-toolbar .ql-picker-label { color: var(--dark-text-primary) !important; }
body .ql-toolbar .ql-picker-options { background: rgba(6,8,14,0.98) !important; border-color: var(--glass-border) !important; color: var(--dark-text-primary) !important; }
body .ql-editor { color: var(--dark-text-primary) !important; }
</style>

<div class="notes-container">
    <div class="notes-header">
        <h2><i class="fas fa-sticky-note"></i> My Notes</h2>
        <div class="notes-filters">
            <div class="search-box">
                <input type="text" id="searchNotes" placeholder="Search notes...">
                <i class="fas fa-search"></i>
            </div>
            <select class="filter-select" id="filterNotes">
                <option value="all">All Notes</option>
                <option value="important">Important</option>
                <option value="completed">Completed</option>
                <option value="pending">Pending</option>
            </select>
            <button class="btn-add-note" onclick="openNoteModal()">
                <i class="fas fa-plus"></i> Add Note
            </button>
            <button class="btn btn-secondary" id="toggleSharedNotes">
                <i class="fas fa-eye"></i> Show Shared Notes
            </button>
        </div>
    </div>
    
    <!-- Note Form Section -->
    <div class="note-form-section" id="noteFormSection" style="display: none;">
        <div class="form-container">
            <div class="form-header">
                <h3 id="noteFormTitle">Add New Note</h3>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNote()">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm ml-2" onclick="resetNoteForm()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm ml-2" onclick="closeNoteForm()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                </div>
            </div>
            <form id="noteForm">
                <input type="hidden" id="noteId" name="note_id">
                
                <div class="row align-items-end">
                    <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label" for="noteTitle">Title *</label>
                            <input type="text" class="form-control" id="noteTitle" name="title" required autocomplete="off">
                </div>
                </div>
                    <div class="col-md-3">
                <div class="form-group">
                    <label class="form-label" for="reminderDate">Reminder (Optional)</label>
                    <input type="datetime-local" class="form-control" id="reminderDate" name="reminder_date" min="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>
                </div>
                    <div class="col-md-3" id="deleteButtonGroup" style="display: none;">
                        <div class="form-group">
                            <button type="button" class="btn btn-danger btn-block" id="deleteNoteBtn" onclick="deleteNote()">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label" for="noteContent">Content</label>
                            <div id="noteContentEditor" style="min-height: 200px; border: 2px solid var(--primary-200); border-radius: 10px; background: rgba(255, 255, 255, 0.9);"></div>
                            <textarea id="noteContent" name="content" style="display: none;"></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Note Detail Section -->
    <div class="note-detail-section" id="noteDetailSection" style="display: none;">
        <div class="detail-container">
            <div class="detail-header">
                <h3 id="noteDetailTitle">Note Details</h3>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="closeNoteDetail()" title="Close note detail">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div class="detail-content">
                <div class="note-detail-info">
                    <h4 id="detailNoteTitle"></h4>
                    <div class="note-detail-meta">
                        <span id="detailNoteDate"></span>
                        <span id="detailNoteStatus"></span>
                    </div>
                    <div class="note-detail-content" id="detailNoteContent"></div>
                </div>
                
                <div class="sharing-section" id="sharingSection">
                    <h6><i class="fas fa-share-alt"></i> Share Note</h6>
                    
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
                    <button type="button" class="btn btn-primary btn-sm" onclick="shareNote()">
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
                
                <div class="comments-section" id="commentsSection">
                    <h6><i class="fas fa-comments"></i> Comments</h6>
                    <div id="commentsList">
                        <!-- Comments will be loaded here -->
                    </div>
                    <div class="form-group">
                        <textarea class="form-control" id="newComment" placeholder="Add a comment..." rows="3"></textarea>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addComment()">
                        <i class="fas fa-comment"></i> Add Comment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="notesContainer">
        <div class="loading">
            <i class="fas fa-spinner"></i>
            <p>Loading notes...</p>
        </div>
    </div>
    
    <!-- Shared Notes Section -->
    <div class="shared-notes-section" id="sharedNotesSection" style="display: none;">
        <div class="shared-notes-header">
            <h2><i class="fas fa-share-alt"></i> Shared Notes</h2>
            <p class="shared-notes-subtitle">Notes shared with you by other users</p>
        </div>
        <div id="sharedNotesContainer">
            <div class="loading">
                <i class="fas fa-spinner"></i>
                <p>Loading shared notes...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<!-- Quill.js Rich Text Editor -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
let currentNoteId = null;
let allUsers = [];
let sortableInstance = null;
let quillEditor = null;

// Optimized my notes functionality
if (!window.myNotesInitialized) {
    window.myNotesInitialized = true;
    
    $(document).ready(function() {
        loadNotes();
        // Check for notes reminders automatically on page load
        checkNotesReminders();
        loadUsers();
        loadSharedNotes();
        initializeDragAndDrop();
        initializeQuillEditor();
    
    // Search functionality
    $('#searchNotes').on('input', function() {
        loadNotes();
    });
    
    // Filter functionality
    $('#filterNotes').on('change', function() {
        loadNotes();
    });
    
    // Toggle shared notes section
    $('#toggleSharedNotes').on('click', function() {
        const section = $('#sharedNotesSection');
        if (section.is(':visible')) {
            section.slideUp();
            $(this).html('<i class="fas fa-eye"></i> Show Shared Notes');
        } else {
            section.slideDown();
            $(this).html('<i class="fas fa-eye-slash"></i> Hide Shared Notes');
            loadSharedNotes(); // Refresh shared notes when showing
        }
    });
    
    // Dropdown checkbox toggle - Append to body when open to escape stacking context
    $('#usersDropdownToggle').on('click', function(e) {
        e.stopPropagation();
        const menu = $('#usersDropdownMenu');
        const searchInput = $('#userSearchInput');
        
        // Clear search when opening dropdown
        if (!menu.hasClass('show')) {
            if (searchInput.length) {
                searchInput.val('');
                filterUsers('');
            }
        }
        const toggle = $(this);
        const container = $('.dropdown-checkbox-container');
        
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
            
            // If menu was moved to body, move it back
            if (menu.data('appended-to-body')) {
                container.append(menu);
                menu.css({ position: '', top: '', left: '', right: '', width: '', minWidth: '' });
                menu.data('appended-to-body', false);
            }
        } else {
            // Open dropdown
            menu.addClass('show');
            toggle.addClass('active');
            container.addClass('open');
            
            // Get toggle position to position dropdown correctly
            const toggleOffset = toggle.offset();
            const toggleHeight = toggle.outerHeight();
            const toggleWidth = toggle.outerWidth();
            
            // Append menu to body to escape any stacking context
            $('body').append(menu);
            menu.css({
                position: 'fixed',
                top: (toggleOffset.top + toggleHeight) + 'px',
                left: toggleOffset.left + 'px',
                width: toggleWidth + 'px',
                minWidth: '300px',
                zIndex: '2147483647'
            });
            menu.data('appended-to-body', true);
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
            
            // If menu was moved to body, move it back
            if (menu.data('appended-to-body')) {
                container.append(menu);
                menu.css({ position: '', top: '', left: '', right: '', width: '', minWidth: '', zIndex: '' });
                menu.data('appended-to-body', false);
            }
        }
    });
    
    // Reposition dropdown on window resize or scroll if open
    function repositionDropdownMenu() {
        const menu = $('#usersDropdownMenu');
        const toggle = $('#usersDropdownToggle');
        
        if (menu.hasClass('show') && menu.data('appended-to-body')) {
            const toggleOffset = toggle.offset();
            const toggleHeight = toggle.outerHeight();
            const toggleWidth = toggle.outerWidth();
            
            menu.css({
                top: (toggleOffset.top + toggleHeight) + 'px',
                left: toggleOffset.left + 'px',
                width: toggleWidth + 'px'
            });
        }
    }
    
    $(window).on('resize scroll', repositionDropdownMenu);
    
    // Set min attribute for reminder date to disable past dates
    function updateReminderDateMin() {
        var now = new Date();
        var year = now.getFullYear();
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var day = String(now.getDate()).padStart(2, '0');
        var hours = String(now.getHours()).padStart(2, '0');
        var minutes = String(now.getMinutes()).padStart(2, '0');
        var minDateTime = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
        $('#reminderDate').attr('min', minDateTime);
    }
    
    // Update min attribute on page load and when form is reset
    updateReminderDateMin();
    $('#noteForm').on('reset', function() {
        updateReminderDateMin();
    });
    
    // Make datetime-local input clickable anywhere
    $('#reminderDate').on('click', function() {
        updateReminderDateMin(); // Ensure min is current when clicked
        if (this.showPicker) {
            this.showPicker();
        } else {
            // Fallback for browsers that don't support showPicker()
            this.focus();
            this.click();
        }
    });
    
    // Also trigger on focus
    $('#reminderDate').on('focus', function() {
        updateReminderDateMin(); // Ensure min is current when focused
        if (this.showPicker) {
            this.showPicker();
        }
    });
    
    // Additional fallback - trigger on any interaction
    $('#reminderDate').on('mousedown', function() {
        if (this.showPicker) {
            this.showPicker();
        }
    });
    });
}

function initializeQuillEditor() {
    // Quill editor configuration
    const toolbarOptions = [
        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        [{ 'indent': '-1'}, { 'indent': '+1' }],
        [{ 'align': [] }],
        ['link', 'image'],
        ['clean']
    ];

    quillEditor = new Quill('#noteContentEditor', {
        theme: 'snow',
        modules: {
            toolbar: toolbarOptions
        },
        placeholder: 'Write your note here...'
    });

    // Update hidden textarea when editor content changes
    quillEditor.on('text-change', function() {
        $('#noteContent').val(quillEditor.root.innerHTML);
    });
}

function initializeDragAndDrop() {
    const notesGrid = document.getElementById('notesGrid');
    if (notesGrid && typeof Sortable !== 'undefined') {
        sortableInstance = new Sortable(notesGrid, {
            animation: 300,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onStart: function(evt) {
                evt.item.classList.add('dragging');
            },
            onEnd: function(evt) {
                evt.item.classList.remove('dragging');
                updateNoteOrder();
            }
        });
    }
}

function updateNoteOrder() {
    const noteIds = [];
    $('#notesGrid .note-card').each(function() {
        const noteId = $(this).data('note-id');
        if (noteId) {
            noteIds.push(noteId);
        }
    });
    
    if (noteIds.length > 0) {
        $.ajax({
            url: '../ajax/notes_handler.php',
            type: 'POST',
            data: {
                action: 'update_order',
                note_ids: noteIds
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Notes reordered successfully!', 'success');
                }
            },
            error: function() {
                showNotification('Failed to update note order', 'error');
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

function loadNotes() {
    const search = $('#searchNotes').val();
    const filter = $('#filterNotes').val();
    
    $.ajax({
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: {
            action: 'get_notes',
            search: search,
            filter: filter
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayNotes(response.notes);
            } else {
                showAlert('Error loading notes: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading notes. Please try again.', 'danger');
        }
    });
}

function displayNotes(notes) {
    const container = $('#notesContainer');
    
    if (notes.length === 0) {
        container.html(`
            <div class="no-notes">
                <i class="fas fa-sticky-note"></i>
                <h3>No notes found</h3>
                <p>Create your first note to get started!</p>
            </div>
        `);
        return;
    }
    
    let html = '<div class="notes-grid">';
    
    notes.forEach(note => {
        const isImportant = note.is_important == 1;
        const isCompleted = note.is_completed == 1;
        const hasReminder = note.reminder_date && new Date(note.reminder_date) > new Date();
        const isShared = note.shared_count > 0;
        
        let cardClass = 'note-card';
        if (isImportant) cardClass += ' important';
        if (isCompleted) cardClass += ' completed';
        
        const createdDate = new Date(note.created_at).toLocaleDateString();
        const reminderDate = note.reminder_date ? new Date(note.reminder_date).toLocaleString() : null;
        
        html += `
            <div class="${cardClass}" data-note-id="${note.id}" onclick="openNoteDetail(${note.id})">
                <div class="note-header">
                    <h4 class="note-title">${escapeHtml(note.title)}</h4>
                    <div class="note-actions" onclick="event.stopPropagation()">
                        <button class="note-action-btn ${isImportant ? 'important' : ''}" 
                                onclick="toggleImportant(${note.id})" 
                                title="${isImportant ? 'Remove from important' : 'Mark as important'}">
                            <i class="fas fa-star"></i>
                        </button>
                        <button class="note-action-btn ${isCompleted ? 'completed' : ''}" 
                                onclick="toggleCompleted(${note.id})" 
                                title="${isCompleted ? 'Mark as pending' : 'Mark as completed'}">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="note-action-btn" 
                                onclick="openNoteModal(${note.id})" 
                                title="Edit note">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="note-action-btn" 
                                onclick="deleteNote(${note.id})" 
                                title="Delete note">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="note-content">
                    ${stripHtml(note.content).substring(0, 200)}${stripHtml(note.content).length > 200 ? '...' : ''}
                </div>
                <div class="note-meta">
                    <div class="note-date">
                        <i class="fas fa-calendar"></i>
                        ${createdDate}
                    </div>
                    <div>
                        ${hasReminder ? `<span class="note-reminder"><i class="fas fa-bell"></i> ${reminderDate}</span>` : ''}
                        ${isShared ? '<span class="note-shared"><i class="fas fa-share"></i> Shared</span>' : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.html(html);
}

function openNoteModal(noteId = null) {
    currentNoteId = noteId;
    
    if (noteId) {
        // Edit mode
        $('#noteFormTitle').text('Edit Note');
        $('#deleteButtonGroup').show();
        loadNote(noteId);
        $('#noteFormSection').show();
        $('#noteDetailSection').hide();
    } else {
        // Create mode
        $('#noteFormTitle').text('Add New Note');
        $('#deleteButtonGroup').hide();
        $('#noteForm')[0].reset();
        $('#noteFormSection').show();
        $('#noteDetailSection').hide();
    }
    
    // Scroll to form
    $('html, body').animate({
        scrollTop: $('#noteFormSection').offset().top - 100
    }, 500);
}

function closeNoteForm() {
    $('#noteFormSection').hide();
    $('#noteForm')[0].reset();
    currentNoteId = null;
}

function resetNoteForm() {
    $('#noteForm')[0].reset();
    $('#noteId').val('');
    $('#deleteButtonGroup').hide();
    
    // Clear Quill editor
    quillEditor.setContents([]);
}

function openNoteDetail(noteId) {
    currentNoteId = noteId;
    
    // Show loading state
    $('#noteDetailSection').show();
    $('#noteFormSection').hide();
    
    // Show loading in detail content
    $('#detailNoteTitle').text('Loading...');
    $('#detailNoteContent').html('<div class="loading"><i class="fas fa-spinner fa-spin"></i><p>Loading note details...</p></div>');
    $('#detailNoteDate').html('<i class="fas fa-spinner fa-spin"></i> Loading...');
    $('#detailNoteStatus').html('<i class="fas fa-spinner fa-spin"></i> Loading...');
    
    // Load note detail
    loadNoteDetail(noteId);
    
    // Scroll to detail with smooth animation
    $('html, body').animate({
        scrollTop: $('#noteDetailSection').offset().top - 100
    }, 500);
    
    // Add a subtle animation to the detail section
    $('#noteDetailSection').css('opacity', '0').animate({ opacity: 1 }, 300);
}

function closeNoteDetail() {
    // Add closing animation
    $('#noteDetailSection').animate({ opacity: 0 }, 200, function() {
        $(this).hide();
    });
    currentNoteId = null;
    
    // Show success message
    showNotification('Note detail closed', 'info');
}

function loadNote(noteId) {
    $.ajax({
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: {
            action: 'get_note',
            note_id: noteId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const note = response.note;
                $('#noteId').val(note.id);
                $('#noteTitle').val(note.title);
                
                // Set content in Quill editor
                quillEditor.root.innerHTML = note.content || '';
                
                if (note.reminder_date) {
                    const reminderDate = new Date(note.reminder_date);
                    const localDateTime = new Date(reminderDate.getTime() - reminderDate.getTimezoneOffset() * 60000);
                    $('#reminderDate').val(localDateTime.toISOString().slice(0, 16));
                }
                
                // Show sharing and comments sections for existing notes
                $('#sharingSection').show();
                $('#commentsSection').show();
                loadSharingList(noteId);
                loadComments(noteId);
            } else {
                showAlert('Error loading note: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading note. Please try again.', 'danger');
        }
    });
}

function saveNote() {
    // Get content from Quill editor
    const editorContent = quillEditor.root.innerHTML;
    
    const formData = {
        action: currentNoteId ? 'update_note' : 'create_note',
        title: $('#noteTitle').val(),
        content: editorContent,
        reminder_date: $('#reminderDate').val()
    };
    
    if (currentNoteId) {
        formData.note_id = currentNoteId;
    }
    
    if (!formData.title.trim()) {
        showAlert('Please enter a title for the note.', 'warning');
        return;
    }
    
    $.ajax({
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                $('#noteFormSection').hide();
                loadNotes();
            } else {
                showAlert('Error saving note: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error saving note. Please try again.', 'danger');
        }
    });
}

function loadNoteDetail(noteId) {
    $.ajax({
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: {
            action: 'get_note',
            note_id: noteId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const note = response.note;
                
                // Set note title
                $('#detailNoteTitle').text(note.title);
                
                // Set note content with proper HTML handling
                const content = note.content || '';
                if (content.trim() === '') {
                    $('#detailNoteContent').html('<p class="text-muted"><em>No content available</em></p>');
                } else {
                    $('#detailNoteContent').html(content);
                }
                
                // Set creation date with better formatting
                const createdDate = new Date(note.created_at);
                const formattedDate = createdDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                $('#detailNoteDate').html(`<i class="fas fa-calendar-plus"></i> Created: ${formattedDate}`);
                
                // Set status with improved styling
                let statusHtml = '';
                if (note.is_important == 1) {
                    statusHtml += '<span class="note-reminder"><i class="fas fa-star"></i> Important</span> ';
                }
                if (note.is_completed == 1) {
                    statusHtml += '<span class="note-shared"><i class="fas fa-check-circle"></i> Completed</span> ';
                }
                if (note.reminder_date) {
                    const reminderDate = new Date(note.reminder_date);
                    const now = new Date();
                    const isOverdue = reminderDate < now;
                    const reminderClass = isOverdue ? 'note-reminder overdue' : 'note-reminder';
                    const reminderText = isOverdue ? 'Overdue' : 'Reminder';
                    statusHtml += `<span class="${reminderClass}"><i class="fas fa-bell"></i> ${reminderText}: ${reminderDate.toLocaleString()}</span>`;
                }
                
                if (statusHtml.trim() === '') {
                    statusHtml = '<span class="text-muted"><i class="fas fa-info-circle"></i> No special status</span>';
                }
                $('#detailNoteStatus').html(statusHtml);
                
                // Load sharing and comments
                loadSharingList(noteId);
                loadComments(noteId);
                
                // Show success message
                showNotification('Note details loaded successfully', 'success');
            } else {
                showAlert('Error loading note: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading note. Please try again.', 'danger');
        }
    });
}

function deleteNote(noteId = null) {
    const idToDelete = noteId || currentNoteId;
    if (!idToDelete) return;
    
    if (confirm('Are you sure you want to delete this note? This action cannot be undone.')) {
        $.ajax({
            url: '../ajax/notes_handler.php',
            method: 'POST',
            data: {
                action: 'delete_note',
                note_id: idToDelete
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#noteFormSection').hide();
                    $('#noteDetailSection').hide();
                    loadNotes();
                } else {
                    showAlert('Error deleting note: ' + response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error deleting note. Please try again.', 'danger');
            }
        });
    }
}

function toggleImportant(noteId) {
    $.ajax({
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: {
            action: 'toggle_important',
            note_id: noteId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadNotes();
            } else {
                showAlert('Error updating note: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating note. Please try again.', 'danger');
        }
    });
}

function toggleCompleted(noteId) {
    $.ajax({
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: {
            action: 'toggle_completed',
            note_id: noteId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadNotes();
            } else {
                showAlert('Error updating note: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating note. Please try again.', 'danger');
        }
    });
}

function loadUsers() {
    $.ajax({
        url: '../ajax/notes_handler.php',
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
                $('#usersSelection').html('<p class="text-muted">Error loading users.</p>');
            }
        },
        error: function() {
            $('#usersSelection').html('<p class="text-muted">Error loading users.</p>');
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

function shareNote() {
    const noteId = currentNoteId;
    const permission = $('#sharePermission').val();
    
    if (!noteId) {
        showAlert('No note selected for sharing.', 'warning');
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
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: {
            action: 'share_note',
            note_id: noteId,
            shared_with_user_id: userId,
            permission: permission
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                    successCount++;
                } else {
                    errorCount++;
                }
                
                // Check if all requests are complete
                if (successCount + errorCount === selectedUsers.length) {
                    if (errorCount === 0) {
                        showAlert(`Note shared successfully with ${successCount} user(s)!`, 'success');
                loadSharingList(noteId);
                        // Clear selections
                        $('input[name="selected_users"]:checked').prop('checked', false);
                        updateSelectedUsersText();
                        // Close dropdown
                        $('#usersDropdownMenu').removeClass('show');
                        $('#usersDropdownToggle').removeClass('active');
                        $('.dropdown-checkbox-container').removeClass('open');
                    } else if (successCount === 0) {
                        showAlert('Failed to share note with any users.', 'danger');
            } else {
                        showAlert(`Note shared with ${successCount} user(s), failed with ${errorCount} user(s).`, 'warning');
                        loadSharingList(noteId);
                        // Clear selections
                        $('input[name="selected_users"]:checked').prop('checked', false);
                        updateSelectedUsersText();
                        // Close dropdown
                        $('#usersDropdownMenu').removeClass('show');
                        $('#usersDropdownToggle').removeClass('active');
                        $('.dropdown-checkbox-container').removeClass('open');
                    }
            }
        },
        error: function() {
                errorCount++;
                if (successCount + errorCount === selectedUsers.length) {
                    if (successCount === 0) {
                        showAlert('Failed to share note with any users.', 'danger');
                    } else {
                        showAlert(`Note shared with ${successCount} user(s), failed with ${errorCount} user(s).`, 'warning');
                        loadSharingList(noteId);
                    }
                    // Clear selections
                    $('input[name="selected_users"]:checked').prop('checked', false);
                    updateSelectedUsersText();
                    // Close dropdown
                    $('#usersDropdownMenu').removeClass('show');
                    $('#usersDropdownToggle').removeClass('active');
                }
            }
        });
    });
}

function loadSharingList(noteId) {
    if (!noteId) return;
    
    $.ajax({
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: {
            action: 'get_sharing_list',
            note_id: noteId
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
    if (confirm('Are you sure you want to remove this user\'s access to the note?')) {
        $.ajax({
            url: '../ajax/notes_handler.php',
            method: 'POST',
            data: {
                action: 'remove_sharing',
                sharing_id: sharingId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    loadSharingList(currentNoteId);
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
    if (currentNoteId) {
        loadSharingList(currentNoteId);
    }
}

function loadSharedNotes() {
    $.ajax({
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: {
            action: 'get_shared_notes'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displaySharedNotes(response.shared_notes);
            } else {
                $('#sharedNotesContainer').html('<p class="text-muted">Error loading shared notes.</p>');
            }
        },
        error: function() {
            $('#sharedNotesContainer').html('<p class="text-muted">Error loading shared notes.</p>');
        }
    });
}

function displaySharedNotes(sharedNotes) {
    const container = $('#sharedNotesContainer');
    
    if (sharedNotes.length === 0) {
        container.html(`
            <div class="no-notes">
                <i class="fas fa-share-alt"></i>
                <h3>No shared notes</h3>
                <p>No notes have been shared with you yet.</p>
            </div>
        `);
        return;
    }
    
    let html = '<div class="shared-notes-grid">';
    
    sharedNotes.forEach(note => {
        const isImportant = note.is_important == 1;
        const isCompleted = note.is_completed == 1;
        const hasReminder = note.reminder_date && new Date(note.reminder_date) > new Date();
        
        let cardClass = 'shared-note-card';
        if (isImportant) cardClass += ' important';
        if (isCompleted) cardClass += ' completed';
        
        const createdDate = new Date(note.created_at).toLocaleDateString();
        const reminderDate = note.reminder_date ? new Date(note.reminder_date).toLocaleString() : null;
        const permissionText = getPermissionText(note.permission);
        
        html += `
            <div class="${cardClass}" data-note-id="${note.id}" onclick="openSharedNoteDetail(${note.id})">
                <div class="shared-note-header">
                    <h4 class="shared-note-title">${escapeHtml(note.title)}</h4>
                    <div class="shared-note-actions" onclick="event.stopPropagation()">
                        ${note.permission === 'edit' ? `
                            <button class="shared-note-action-btn" 
                                    onclick="editSharedNote(${note.id})" 
                                    title="Edit note">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        <button class="shared-note-action-btn" 
                                onclick="viewSharedNote(${note.id})" 
                                title="View note">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="shared-note-meta">
                    <span class="shared-note-owner">
                        <i class="fas fa-user"></i> ${escapeHtml(note.owner_name)}
                    </span>
                    <span class="shared-note-permission">
                        <i class="fas fa-key"></i> ${permissionText}
                    </span>
                </div>
                <div class="note-content">
                    ${stripHtml(note.content).substring(0, 200)}${stripHtml(note.content).length > 200 ? '...' : ''}
                </div>
                <div class="note-meta">
                    <div class="note-date">
                        <i class="fas fa-calendar"></i>
                        ${createdDate}
                    </div>
                    <div>
                        ${hasReminder ? `<span class="note-reminder"><i class="fas fa-bell"></i> ${reminderDate}</span>` : ''}
                    </div>
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

function openSharedNoteDetail(noteId) {
    // This would open a detail view for shared notes
    // Similar to openNoteDetail but with permission restrictions
    console.log('Opening shared note detail:', noteId);
}

function editSharedNote(noteId) {
    // This would open the note for editing if user has edit permission
    console.log('Editing shared note:', noteId);
}

function viewSharedNote(noteId) {
    // This would open the note in read-only mode
    console.log('Viewing shared note:', noteId);
}

function addComment() {
    const noteId = currentNoteId;
    const comment = $('#newComment').val();
    
    if (!noteId || !comment.trim()) {
        showAlert('Please enter a comment.', 'warning');
        return;
    }
    
    $.ajax({
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: {
            action: 'add_comment',
            note_id: noteId,
            comment: comment
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                $('#newComment').val('');
                loadComments(noteId);
            } else {
                showAlert('Error adding comment: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error adding comment. Please try again.', 'danger');
        }
    });
}

function loadComments(noteId) {
    $.ajax({
        url: '../ajax/notes_handler.php',
        method: 'POST',
        data: {
            action: 'get_comments',
            note_id: noteId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayComments(response.comments);
            } else {
                $('#commentsList').html('<p class="text-muted">Error loading comments.</p>');
            }
        },
        error: function() {
            $('#commentsList').html('<p class="text-muted">Error loading comments.</p>');
        }
    });
}

function displayComments(comments) {
    const container = $('#commentsList');
    
    if (comments.length === 0) {
        container.html('<p class="text-muted">No comments yet.</p>');
        return;
    }
    
    let html = '';
    comments.forEach(comment => {
        const date = new Date(comment.created_at).toLocaleString();
        html += `
            <div class="comment">
                <div class="comment-header">
                    <span class="comment-author">${escapeHtml(comment.user_name)}</span>
                    <span class="comment-date">${date}</span>
                </div>
                <div class="comment-content">${escapeHtml(comment.comment)}</div>
            </div>
        `;
    });
    
    container.html(html);
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

function stripHtml(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

function checkNotesReminders() {
    // Automatically check for notes reminders when page loads
    $.ajax({
        url: '../ajax/notes_handler.php',
        type: 'POST',
        data: {
            action: 'check_reminders'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.notifications_created > 0) {
                // Refresh notification count if notifications were created
                if (typeof NotificationsManager !== 'undefined' && typeof NotificationsManager.updateBadge === 'function') {
                    NotificationsManager.updateBadge();
                }
            }
        },
        error: function(xhr, status, error) {
            // Silently fail - don't show error to user
            console.log('Notes reminder check failed:', error);
        }
    });
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
    
    $('.notes-container').prepend(alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alert.alert('close');
    }, 5000);
}
</script>

<?php require_once "../includes/footer.php"; ?>
