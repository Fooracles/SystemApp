<?php
$page_title = "Task & Ticket";
require_once "../includes/header.php";

// Check if the user is logged in and is a client
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Redirect if not a client, manager, or admin
if(!isClient() && !isManager() && !isAdmin()) {
    // Redirect to appropriate dashboard based on user type
    if (isDoer()) {
        header("location: doer_dashboard.php");
    } else {
        header("location: ../login.php");
    }
    exit;
}

// Get user data
$username = htmlspecialchars($_SESSION["username"]);
$is_manager = isManager();
$is_client = isClient();
$is_admin = isAdmin();
?>

<!-- Preconnect to CDNs for faster loading -->
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://unpkg.com" crossorigin>
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
<link rel="dns-prefetch" href="https://unpkg.com">

<!-- Tailwind CSS v3 Play CDN - loaded synchronously before Alpine.js -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        corePlugins: {
            preflight: false
        }
    };
</script>

<!-- Alpine.js - Defer to not block rendering -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

<!-- Lucide Icons - Load asynchronously, only when needed -->
<script>
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
    /* Hide content until Alpine is ready */
    [x-cloak] { 
        display: none !important; 
    }
    
    /* Reset browser default button styling (needed since Tailwind Preflight is disabled to avoid Bootstrap conflicts) */
    .task-ticket-container button {
        background: transparent;
        border: none;
        padding: 0;
        margin: 0;
        font: inherit;
        color: inherit;
        cursor: pointer;
        outline: none;
    }
    
    .task-ticket-container button:focus {
        outline: none;
    }
    
    .task-ticket-container select {
        background: transparent;
        border: none;
        padding: 0;
        margin: 0;
        font: inherit;
        color: inherit;
        cursor: pointer;
    }
    
    .task-ticket-container input {
        background: transparent;
        border: none;
        padding: 0;
        margin: 0;
        font: inherit;
        color: inherit;
    }
    
    .task-ticket-container textarea {
        background: transparent;
        border: none;
        padding: 0;
        margin: 0;
        font: inherit;
        color: inherit;
    }
    
    .task-ticket-container *,
    .task-ticket-container *::before,
    .task-ticket-container *::after {
        box-sizing: border-box;
    }
    
    .task-ticket-container img,
    .task-ticket-container svg {
        display: block;
        max-width: 100%;
    }
    
    /* Also reset for modals that are outside the container (fixed positioned) */
    .requirement-modal button,
    .modal-enter button,
    [x-show="isModalOpen"] button,
    [x-show="isDetailModalOpen"] button,
    [x-show="isRequirementModalOpen"] button {
        background: transparent;
        border: none;
        padding: 0;
        margin: 0;
        font: inherit;
        color: inherit;
        cursor: pointer;
    }
    
    .requirement-modal select,
    .requirement-modal input,
    .requirement-modal textarea {
        background: transparent;
        border: none;
        padding: 0;
        margin: 0;
        font: inherit;
        color: inherit;
    }
    
    /* Critical CSS - Prevent layout flicker with immediate styles */
    .task-ticket-container {
        min-height: 100vh;
        background: transparent;
        padding: 1rem;
        opacity: 1;
        visibility: visible;
        position: relative;
    }
    
    @media (min-width: 768px) {
        .task-ticket-container {
            padding: 2rem;
        }
    }
    
    .task-ticket-wrapper {
        max-width: 1280px;
        margin: 0 auto;
        width: 100%;
    }
    
    .task-ticket-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    
    .task-ticket-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 0.5rem;
        line-height: 1.2;
    }
    
    .task-ticket-subtitle {
        color: #94a3b8;
        font-size: 1rem;
        line-height: 1.5;
    }
    
    /* Date input styling for dark theme - hide calendar icon but keep functionality */
    input[type="date"] {
        cursor: pointer;
        position: relative;
    }
    
    input[type="date"]::-webkit-calendar-picker-indicator {
        position: absolute;
        right: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        z-index: 1;
    }
    
    input[type="date"]::-webkit-inner-spin-button,
    input[type="date"]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    /* Hide calendar icon on Firefox but keep functionality */
    input[type="date"]::-moz-calendar-picker-indicator {
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
        position: absolute;
        right: 0;
    }
    
    .task-ticket-table-container {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.75rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        overflow: visible;
        min-height: 400px;
        position: relative;
    }
    
    .task-ticket-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }
    
    /* Prevent horizontal scrollbar, no vertical scroll */
    .task-ticket-table-container > div {
        overflow-x: hidden !important;
        overflow-y: visible !important;
        width: 100%;
        position: relative;
    }
    
    .task-ticket-table thead {
        background: rgba(30, 41, 59, 0.5);
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .task-ticket-table th {
        padding: 0.75rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 600;
        color: #cbd5e1;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .task-ticket-table td {
        padding: 0.75rem;
        color: #e2e8f0;
        font-size: 0.875rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    .task-ticket-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }
    
    /* Ensure dropdowns can overflow table rows */
    .task-ticket-table tbody tr {
        position: relative;
    }
    
    /* Ensure dropdown containers can overflow */
    .task-ticket-table tbody td {
        position: relative;
        overflow: visible;
    }
    
    /* Ensure dropdowns appear above all table elements */
    .task-ticket-table-container [style*="z-index: 9999"],
    .task-ticket-table-container [style*="z-index: 10000"] {
        position: absolute !important;
        z-index: 10000 !important;
    }
    
    /* Fix dropdown z-index and positioning to appear above table rows */
    .task-ticket-table tbody tr {
        position: relative;
        z-index: 1;
    }
    
    .task-ticket-table tbody tr:hover {
        z-index: 2;
    }
    
    /* Dropdown menu container - ensure it's above all rows */
    .task-ticket-table tbody td > div.relative {
        position: relative;
        z-index: 10;
    }
    
    /* Dropdown menu itself - highest z-index, ensure it's above everything including hovered rows */
    .task-ticket-table tbody td .absolute {
        position: absolute !important;
        z-index: 100000 !important;
    }
    
    /* Ensure Action column dropdowns are always on top */
    .task-ticket-table tbody td[style*="width: 100px"] .absolute,
    .task-ticket-table tbody td[style*="min-width: 100px"] .absolute {
        z-index: 100001 !important;
    }
    
    /* Ensure Status column dropdowns are always on top */
    .task-ticket-table tbody td .relative.inline-block .absolute {
        z-index: 100001 !important;
    }
    
    /* Ensure the row containing an open dropdown has highest z-index */
    .task-ticket-table tbody tr.dropdown-open-row {
        z-index: 10000 !important;
        position: relative;
    }
    
    /* Ensure the table cell containing dropdown has proper stacking context */
    .task-ticket-table tbody tr.dropdown-open-row td {
        z-index: 10000 !important;
        position: relative;
    }
    
    /* Ensure table container allows overflow for dropdowns (no vertical scroll) */
    .task-ticket-table-container {
        overflow: visible !important;
    }
    
    .task-ticket-table-container > div {
        overflow-x: hidden !important;
        overflow-y: visible !important;
        max-height: none !important;
    }
    
    /* Action column select dropdown styling */
    .task-ticket-table select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234ade80' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.5rem center;
        background-size: 0.75rem;
        padding-right: 1.75rem;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }
    
    .task-ticket-table select option {
        background-color: #1e293b;
        color: #cbd5e1;
        padding: 0.5rem;
    }
    
    .task-ticket-table select:focus {
        outline: none;
        ring: 2px;
        ring-color: rgba(74, 222, 128, 0.5);
    }
    
    /* Loading skeleton to prevent layout shift - shown initially */
    .task-ticket-loading {
        display: block;
        min-height: 500px;
        background: rgba(30, 41, 59, 0.3);
        border-radius: 0.75rem;
        position: relative;
        overflow: hidden;
        margin: 1rem;
    }
    
    @media (min-width: 768px) {
        .task-ticket-loading {
            margin: 2rem auto;
            max-width: 1280px;
        }
    }
    
    /* Hide main content initially, show when ready */
    #taskTicketApp {
        display: none;
    }
    
    body.alpine-ready #taskTicketApp {
        display: block;
        animation: fadeIn 0.2s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Hide loading skeleton when Alpine is ready */
    body.alpine-ready .task-ticket-loading {
        display: none !important;
    }
    
    .task-ticket-loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
        animation: loading-shimmer 1.5s infinite;
    }
    
    @keyframes loading-shimmer {
        0% { left: -100%; }
        100% { left: 100%; }
    }
    
    /* Hide loading skeleton when Alpine is ready */
    body.alpine-ready .task-ticket-loading {
        display: none;
    }
    
    /* Inline critical CSS for instant rendering */
    .glass-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .gradient-button {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transition: all 0.3s ease;
    }
    .gradient-button:hover {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }
    .tab-underline {
        position: relative;
    }
    .tab-underline::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }
    .tab-underline.active::after {
        transform: scaleX(1);
    }
    .floating-label {
        position: relative;
    }
    .floating-label input:focus ~ label,
    .floating-label input:not(:placeholder-shown) ~ label,
    .floating-label textarea:focus ~ label,
    .floating-label textarea:not(:placeholder-shown) ~ label {
        transform: translateY(-24px) scale(0.85);
        color: #667eea;
    }
    .floating-label label {
        position: absolute;
        left: 12px;
        top: 12px;
        pointer-events: none;
        transition: all 0.3s ease;
        color: #9ca3af;
    }
    .drop-zone {
        border: 2px dashed #6b7280;
        transition: all 0.3s ease;
    }
    .drop-zone.drag-over {
        border-color: #667eea;
        background: rgba(102, 126, 234, 0.1);
    }
    @keyframes ai-processing {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    .ai-processing {
        animation: ai-processing 1s ease-in-out infinite;
    }
    .table-row-hover {
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .table-row-hover:hover {
        background: rgba(102, 126, 234, 0.05);
    }
    .modal-enter {
        animation: modalEnter 0.3s ease-out;
    }
    .modal-exit {
        animation: modalExit 0.2s ease-in;
    }
    @keyframes modalEnter {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    @keyframes modalExit {
        from {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
        to {
            opacity: 0;
            transform: scale(0.95) translateY(-20px);
        }
    }
    
    /* Status icon animations */
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    [data-lucide="loader-2"] {
        animation: spin 1s linear infinite;
    }
    
    /* Tooltip styles */
    .group:hover .group-hover\:opacity-100 {
        opacity: 1;
    }
    
    /* Sortable header styles */
    th[class*="cursor-pointer"] {
        user-select: none;
    }
    
    th[class*="cursor-pointer"]:hover {
        background: rgba(51, 65, 85, 0.5) !important;
    }
    
    th[class*="cursor-pointer"]:active {
        transform: scale(0.98);
    }
    
    /* Ensure all sorting icons are the same size */
    th[class*="cursor-pointer"] i[data-lucide] {
        width: 1rem !important;
        height: 1rem !important;
        min-width: 1rem;
        min-height: 1rem;
        flex-shrink: 0;
    }
    
    th[class*="cursor-pointer"] i[data-lucide] svg {
        width: 100% !important;
        height: 100% !important;
        stroke-width: 2;
    }
    
    /* Filter section styles */
    .filter-section {
        transition: all 0.3s ease;
    }
    
    /* Select dropdown arrow - positioned to not overlap with icon */
    select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 0.75rem;
        padding-right: 2.5rem !important;
    }
    
    /* Ensure icons don't overlap with text in filter inputs only */
    .filter-section input[type="text"],
    .filter-section input[type="date"],
    .filter-section select {
        padding-left: 2.5rem !important;
    }

    /* Keep filter control text vertically centered and prevent clipping */
    .filter-section .relative.h-\[34px\] {
        min-height: 34px;
    }

    .filter-section .relative.h-\[34px\] > input[type="text"],
    .filter-section .relative.h-\[34px\] > input[type="date"],
    .filter-section .relative.h-\[34px\] > select {
        height: 34px !important;
        min-height: 34px;
        box-sizing: border-box;
        line-height: 32px;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        font-size: 0.75rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Date input specific styling to prevent icon overlap */
    input[type="date"]::-webkit-calendar-picker-indicator {
        position: absolute;
        right: 0.75rem;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
    }
    
    /* Ensure placeholder text doesn't overlap with icons */
    input::placeholder {
        padding-left: 0;
    }
    
    /* Custom scrollbar for detail modal */
    .detail-modal-content::-webkit-scrollbar {
        width: 6px;
    }
    
    .detail-modal-content::-webkit-scrollbar-track {
        background: rgba(30, 41, 59, 0.5);
        border-radius: 3px;
    }
    
    .detail-modal-content::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.5);
        border-radius: 3px;
    }
    
    .detail-modal-content::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 163, 184, 0.7);
    }
    
    /* Hide horizontal scrollbar */
    .detail-modal-content {
        overflow-x: hidden !important;
    }
    
    /* Attachment grid scrollbar */
    .attachment-grid::-webkit-scrollbar {
        width: 4px;
    }
    
    .attachment-grid::-webkit-scrollbar-track {
        background: rgba(30, 41, 59, 0.3);
        border-radius: 2px;
    }
    
    .attachment-grid::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.4);
        border-radius: 2px;
    }
    
    .attachment-grid::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 163, 184, 0.6);
    }
    
    /* Status Timeline Styles - Modern Horizontal Design */
    .status-timeline {
        position: relative;
        display: flex;
        align-items: flex-start;
        padding: 1.5rem 0 0.75rem;
        overflow-x: hidden;
        overflow-y: hidden;
        width: 100%;
        justify-content: space-between;
    }
    
    .status-timeline::before {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        top: 1rem;
        height: 2px;
        background: linear-gradient(90deg, 
            rgba(148, 163, 184, 0.15) 0%, 
            rgba(148, 163, 184, 0.25) 50%, 
            rgba(148, 163, 184, 0.15) 100%);
        border-radius: 1px;
        z-index: 1;
    }
    
    .status-timeline-item {
        position: relative;
        flex: 1;
        min-width: 0;
        padding: 0 0.25rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        z-index: 2;
        transition: transform 0.2s ease;
    }
    
    .status-timeline-item:hover {
        transform: translateY(-1px);
    }
    
    .status-timeline-item:first-child {
        padding-left: 0;
    }
    
    .status-timeline-item:last-child {
        padding-right: 0;
    }
    
    .status-timeline-item::before {
        content: '';
        position: absolute;
        top: 0.75rem;
        left: 50%;
        transform: translateX(-50%);
        width: 0.75rem;
        height: 0.75rem;
        border-radius: 50%;
        background: rgba(30, 41, 59, 0.9);
        border: 2px solid rgba(148, 163, 184, 0.5);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 3;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }
    
    .status-timeline-item.completed::before {
        background: #10b981;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2), 
                    0 2px 8px rgba(16, 185, 129, 0.4);
        width: 0.875rem;
        height: 0.875rem;
        top: 0.6875rem;
    }
    
    .status-timeline-item.completed::after {
        content: '✓';
        position: absolute;
        top: 0.625rem;
        left: 50%;
        transform: translateX(-50%);
        width: 0.875rem;
        height: 0.875rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.5rem;
        font-weight: 700;
        z-index: 4;
    }
    
    .status-timeline-item.current::before {
        background: #667eea;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25), 
                    0 2px 10px rgba(102, 126, 234, 0.5);
        width: 1rem;
        height: 1rem;
        top: 0.625rem;
    }
    
    .status-timeline-item.upcoming {
        opacity: 0.5;
    }
    
    .status-timeline-item.upcoming::before {
        background: rgba(30, 41, 59, 0.7);
        border-color: rgba(148, 163, 184, 0.3);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }
    
    .status-timeline-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
        margin-top: 1.25rem;
        text-align: center;
        width: 100%;
        padding: 0.5rem 0.25rem;
        background: rgba(30, 41, 59, 0.4);
        border-radius: 0.375rem;
        border: 1px solid rgba(148, 163, 184, 0.15);
        transition: all 0.2s ease;
    }
    
    .status-timeline-item:hover .status-timeline-content {
        background: rgba(30, 41, 59, 0.6);
        border-color: rgba(148, 163, 184, 0.25);
    }
    
    .status-timeline-item.current .status-timeline-content {
        background: rgba(102, 126, 234, 0.2);
        border-color: rgba(102, 126, 234, 0.4);
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.25);
    }
    
    .status-timeline-item.completed .status-timeline-content {
        background: rgba(16, 185, 129, 0.15);
        border-color: rgba(16, 185, 129, 0.3);
    }
    
    .status-timeline-status {
        font-size: 0.7rem;
        font-weight: 600;
        color: #e2e8f0;
        word-break: break-word;
        line-height: 1.2;
        letter-spacing: 0.01em;
    }
    
    .status-timeline-item.completed .status-timeline-status {
        color: #94a3b8;
        text-decoration: line-through;
        text-decoration-color: rgba(148, 163, 184, 0.6);
    }
    
    .status-timeline-item.current .status-timeline-status {
        color: #c7d2fe;
        font-weight: 700;
    }
    
    .status-timeline-item.upcoming .status-timeline-status {
        color: #64748b;
    }
    
    .status-timeline-date {
        font-size: 0.65rem;
        color: #cbd5e1;
        line-height: 1.3;
        font-weight: 400;
    }
    
    .status-timeline-item.completed .status-timeline-date {
        color: #94a3b8;
    }
    
    .status-timeline-item.current .status-timeline-date {
        color: #e2e8f0;
        font-weight: 500;
    }
    
    .status-timeline-item.upcoming .status-timeline-date {
        color: #64748b;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .status-timeline {
            padding: 1.25rem 0 0.5rem;
        }
        
        .status-timeline-item {
            padding: 0 0.15rem;
        }
        
        .status-timeline-content {
            padding: 0.4rem 0.2rem;
            margin-top: 1rem;
        }
        
        .status-timeline-status {
            font-size: 0.65rem;
        }
        
        .status-timeline-date {
            font-size: 0.6rem;
        }
    }
</style>

<!-- Loading Skeleton (shown before Alpine loads) -->
<div class="task-ticket-loading" id="taskTicketLoading"></div>

<div x-data="ticketApp()" class="task-ticket-container" x-cloak id="taskTicketApp" style="display: none;">
    <div class="task-ticket-wrapper">
        <!-- Header with Action Button -->
        <div class="task-ticket-header">
            <div>
                <h1 class="task-ticket-title">Action Center</h1>
                <p class="task-ticket-subtitle">Manage Your Project Performance</p>
            </div>
            <button 
                @click="openModal()"
                class="gradient-button text-white px-4 py-2 rounded-xl font-medium shadow-lg flex items-center gap-2 text-sm"
            >
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span x-text="(isAdmin || isManager) ? 'Requirements' : 'Add Task'"></span>
            </button>
        </div>

        <!-- Filters Toggle Button -->
        <div class="mb-3 flex items-center justify-end gap-2">
            <button 
                @click="clearFilters()"
                x-show="hasActiveFilters()"
                class="flex items-center justify-center w-10 h-10 text-slate-400 hover:text-white transition-colors"
                title="Clear All Filters"
                x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            <button 
                @click="filtersExpanded = !filtersExpanded"
                class="relative flex items-center justify-center w-8 h-8 bg-slate-800/50 hover:bg-slate-700/50 border border-slate-700/50 rounded-lg text-slate-300 hover:text-white transition-all focus:outline-none focus:ring-0"
                :title="filtersExpanded ? 'Hide Filters' : 'Show Filters'"
                x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)"
            >
                <i data-lucide="filter" class="w-4 h-4"></i>
                <span 
                    x-show="hasActiveFilters()"
                    class="absolute -top-1 -right-1 flex items-center justify-center w-4 h-4 bg-purple-500/20 text-purple-300 rounded-full text-xs font-medium"
                    x-text="getActiveFiltersCount()"
                ></span>
            </button>
        </div>

        <!-- Filters Section (Collapsible) -->
        <div 
            x-show="filtersExpanded"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform -translate-y-2"
            class="mb-4 bg-slate-800/50 backdrop-blur-sm border border-slate-700/50 rounded-xl p-4 filter-section"
            x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)"
        >
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-2.5">
                <!-- Search by ID -->
                <div class="flex flex-col">
                    <label class="text-xs font-medium text-slate-300 mb-0.5">Search by ID</label>
                    <div class="relative h-[34px]">
                        <i data-lucide="search" class="absolute left-1.5 top-1/2 transform -translate-y-1/2 w-2.5 h-2.5 text-slate-400 pointer-events-none z-10"></i>
                        <input
                            type="text"
                            x-model="filters.searchId"
                            @input.debounce.300ms="applyFilters()"
                            placeholder="e.g., TAS001, TKT001"
                            class="w-full h-full pl-7 pr-2 bg-slate-700/50 border border-slate-600/50 rounded-lg text-white placeholder-slate-500 text-xs focus:outline-none transition-all"
                        />
                    </div>
                </div>

                <!-- Item Type -->
                <div class="flex flex-col">
                    <label class="text-xs font-medium text-slate-300 mb-0.5">Item Type</label>
                    <div class="relative h-[34px]">
                        <i data-lucide="tag" class="absolute left-1.5 top-1/2 transform -translate-y-1/2 w-2.5 h-2.5 text-slate-400 pointer-events-none z-10"></i>
                        <select
                            x-model="filters.itemType"
                            @change="applyFilters()"
                            class="w-full h-full pl-7 pr-6 bg-slate-700/50 border border-slate-600/50 rounded-lg text-white text-xs focus:outline-none transition-all appearance-none cursor-pointer"
                        >
                            <option value="">Types</option>
                            <option value="Task">Task</option>
                            <option value="Ticket">Ticket</option>
                            <option value="Required">Required</option>
                        </select>
                    </div>
                </div>

                <!-- Status -->
                <div class="flex flex-col">
                    <label class="text-xs font-medium text-slate-300 mb-0.5">Status</label>
                    <div class="relative h-[34px]">
                        <i data-lucide="check-circle" class="absolute left-1.5 top-1/2 transform -translate-y-1/2 w-2.5 h-2.5 text-slate-400 pointer-events-none z-10"></i>
                        <select
                            x-model="filters.status"
                            @change="applyFilters()"
                            class="w-full h-full pl-7 pr-6 bg-slate-700/50 border border-slate-600/50 rounded-lg text-white text-xs focus:outline-none transition-all appearance-none cursor-pointer"
                        >
                            <option value="">Status</option>
                            <template x-for="status in getAllStatuses()" :key="status">
                                <option :value="status" x-text="status"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Last Updated Date -->
                <div class="flex flex-col">
                    <label class="text-xs font-medium text-slate-300 mb-0.5">Last Updated</label>
                    <div class="relative h-[34px]">
                        <i data-lucide="calendar" class="absolute left-1.5 top-1/2 transform -translate-y-1/2 w-2.5 h-2.5 text-slate-400 pointer-events-none z-10"></i>
                        <input
                            type="text"
                            :value="formatDateForFilter(filters.lastUpdated)"
                            @click.stop="openDatePicker($event)"
                            @input="handleDateInput($event)"
                            @focus="openDatePicker($event)"
                            placeholder="DD/MM/YYYY"
                            pattern="[0-9]{2}/[0-9]{2}/[0-9]{4}"
                            class="w-full h-full pl-7 pr-2 bg-slate-700/50 border border-slate-600/50 rounded-lg text-white placeholder-slate-500 text-xs focus:outline-none transition-all cursor-pointer"
                            readonly
                        />
                        <input
                            type="date"
                            x-ref="hiddenDateInput"
                            @change="handleDateChange($event)"
                            class="absolute inset-0 opacity-0 cursor-pointer z-20"
                            style="width: 100%; height: 100%;"
                        />
                    </div>
                </div>

                <!-- Assigner -->
                <div class="flex flex-col">
                    <label class="text-xs font-medium text-slate-300 mb-0.5">Assigner</label>
                    <div class="relative h-[34px]">
                        <i data-lucide="user" class="absolute left-1.5 top-1/2 transform -translate-y-1/2 w-2.5 h-2.5 text-slate-400 pointer-events-none z-10"></i>
                        <select
                            x-model="filters.assigner"
                            @change="applyFilters()"
                            class="w-full h-full pl-7 pr-6 bg-slate-700/50 border border-slate-600/50 rounded-lg text-white text-xs focus:outline-none transition-all appearance-none cursor-pointer"
                        >
                            <option value="">All Assigners</option>
                            <template x-for="option in assignerFilterOptions" :key="option.id">
                                <option :value="option.id" x-text="option.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Assigned To -->
                <div class="flex flex-col">
                    <label class="text-xs font-medium text-slate-300 mb-0.5">Assigned To</label>
                    <div class="relative h-[34px]">
                        <i data-lucide="user-check" class="absolute left-1.5 top-1/2 transform -translate-y-1/2 w-2.5 h-2.5 text-slate-400 pointer-events-none z-10"></i>
                        <select
                            x-model="filters.assignedTo"
                            @change="applyFilters()"
                            class="w-full h-full pl-7 pr-6 bg-slate-700/50 border border-slate-600/50 rounded-lg text-white text-xs focus:outline-none transition-all appearance-none cursor-pointer"
                        >
                            <option value="">All Assigned To</option>
                            <template x-for="option in assignedToFilterOptions" :key="option.id">
                                <option :value="String(option.id)" x-text="option.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Clear Button -->
                <div class="flex flex-col">
                    <label class="text-xs font-medium text-slate-300 mb-0.5 invisible">Clear</label>
                    <div class="relative h-[34px] flex items-end">
                        <button 
                            @click="clearFilters()"
                            class="py-1 px-3 bg-purple-600/50 hover:bg-purple-600 border-0 rounded-lg text-white text-xs font-medium transition-all focus:outline-none"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="task-ticket-table-container">
            <div style="overflow-x: hidden;">
                <table class="task-ticket-table">
                    <thead>
                        <tr>
                            <th 
                                @click="sortBy('type')"
                                class="px-3 py-3 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider cursor-pointer hover:bg-slate-700/50 transition-colors select-none"
                                style="width: 110px; min-width: 110px;"
                            >
                                <div class="flex items-center gap-1.5">
                                    <span>Type</span>
                                    <i :data-lucide="getSortIcon('type')" class="w-4 h-4 flex-shrink-0" :class="sortColumn === 'type' ? 'text-purple-400' : 'text-slate-500'"></i>
                                </div>
                            </th>
                            <th 
                                @click="sortBy('title')"
                                class="px-3 py-3 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider cursor-pointer hover:bg-slate-700/50 transition-colors select-none"
                            >
                                <div class="flex items-center gap-1.5">
                                    <span>Title</span>
                                    <i :data-lucide="getSortIcon('title')" class="w-4 h-4 flex-shrink-0" :class="sortColumn === 'title' ? 'text-purple-400' : 'text-slate-500'"></i>
                                </div>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider w-24">
                                <span>Details</span>
                            </th>
                            <th 
                                @click="sortBy('status')"
                                class="px-3 py-3 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider w-32 cursor-pointer hover:bg-slate-700/50 transition-colors select-none"
                            >
                                <div class="flex items-center gap-1.5">
                                    <span>Status</span>
                                    <i :data-lucide="getSortIcon('status')" class="w-4 h-4 flex-shrink-0" :class="sortColumn === 'status' ? 'text-purple-400' : 'text-slate-500'"></i>
                                </div>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider" style="width: 150px; min-width: 150px;">
                                <span>Assigned To</span>
                            </th>
                            <th 
                                @click="sortBy('lastUpdated')"
                                class="px-3 py-3 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider w-28 cursor-pointer hover:bg-slate-700/50 transition-colors select-none"
                            >
                                <div class="flex items-center gap-1.5">
                                    <span>Last Updated</span>
                                    <i :data-lucide="getSortIcon('lastUpdated')" class="w-4 h-4 flex-shrink-0" :class="sortColumn === 'lastUpdated' ? 'text-purple-400' : 'text-slate-500'"></i>
                                </div>
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider" style="width: 150px; min-width: 150px;">
                                <span>Assigner</span>
                            </th>
                            <th class="px-3 py-3" style="width: 100px; min-width: 100px;">
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <template x-if="isLoading">
                            <tr>
                                <td :colspan="8" class="px-3 py-8 text-center text-slate-400">
                                    <i data-lucide="loader-2" class="w-6 h-6 mx-auto mb-2"></i>
                                    <p>Loading items...</p>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!isLoading && sortedItems.length === 0 && !hasActiveFilters()">
                            <tr>
                                <td :colspan="8" class="px-3 py-8 text-center text-slate-400">
                                    <p>No items found. Create your first item using the "Add Task" button.</p>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!isLoading && sortedItems.length === 0 && hasActiveFilters()">
                            <tr>
                                <td :colspan="8" class="px-3 py-8 text-center text-slate-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <i data-lucide="filter-x" class="w-8 h-8 text-slate-500"></i>
                                        <p>No items match your filters.</p>
                                        <button 
                                            @click="clearFilters()"
                                            class="text-sm text-purple-400 hover:text-purple-300 transition-colors underline"
                                        >
                                            Clear filters
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-for="(item, index) in paginatedItems" :key="item.id || index">
                            <tr 
                                class="table-row-hover" 
                                :class="[
                                    isDropped(item) ? 'opacity-50 cursor-not-allowed' : '',
                                    (statusDropdownOpen === index || actionDropdownOpen === index) ? 'dropdown-open-row' : ''
                                ]"
                                x-init="initDetailIcons()" 
                                @click="isDropped(item) ? null : viewItem(item)"
                            >
                                <td class="px-3 py-3">
                                    <div class="flex flex-col gap-1">
                                    <span 
                                            class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap w-fit"
                                            :class="item.type === 'Ticket' ? 'bg-blue-500/20 text-blue-300' : item.type === 'Required' ? 'bg-orange-500/20 text-orange-300' : 'bg-purple-500/20 text-purple-300'"
                                    >
                                            <i :data-lucide="item.type === 'Ticket' ? 'ticket' : item.type === 'Required' ? 'alert-circle' : 'check-square'" class="w-3 h-3 inline mr-1"></i>
                                        <span x-text="item.type"></span>
                                    </span>
                                        <div class="text-xs font-mono font-semibold text-slate-400" x-text="item.id || 'N/A'"></div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="text-sm font-medium text-white truncate" x-text="item.title" :title="item.title"></div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <span 
                                            x-show="item.description && item.description.trim()"
                                            class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-500/20 text-blue-300"
                                            title="Has Description"
                                        >
                                            <i data-lucide="file-text" class="w-3 h-3"></i>
                                        </span>
                                        <span 
                                            x-show="item.attachments && item.attachments.length > 0"
                                            class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-purple-500/20 text-purple-300"
                                            :title="item.attachments.length + ' attachment(s)'"
                                        >
                                            <i data-lucide="paperclip" class="w-3 h-3"></i>
                                        </span>
                                        <span 
                                            x-show="(!item.description || !item.description.trim()) && (!item.attachments || item.attachments.length === 0)"
                                            class="text-slate-500 text-xs"
                                        >
                                            -
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="relative inline-block" @click.stop>
                                        <!-- Status Badge (Clickable if not dropped and has permission) -->
                                    <span 
                                            @click.stop="toggleStatusDropdown(item, index)"
                                            class="px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap cursor-pointer hover:opacity-80 transition-opacity"
                                            :class="[
                                                getStatusClass(item.status, item.type),
                                                isDropped(item) || !canChangeStatus(item) ? 'cursor-default hover:opacity-100' : ''
                                            ]"
                                            :title="isDropped(item) ? 'Dropped items cannot be changed' : (canChangeStatus(item) ? 'Click to change status' : 'Read-only')"
                                    >
                                        <span x-text="item.status"></span>
                                    </span>
                                        
                                        <!-- Status Dropdown -->
                                        <div 
                                            x-show="statusDropdownOpen === index && !isDropped(item) && canChangeStatus(item)"
                                            x-transition
                                            @click.away="statusDropdownOpen = null"
                                            class="absolute left-0 mt-1 w-40 bg-slate-800 border border-slate-700 rounded-lg shadow-xl"
                                            style="display: none; z-index: 10000;"
                                        >
                                            <div class="py-1">
                                                <template x-for="statusOption in getStatusOptionsForItem(item)" :key="statusOption">
                                                    <button 
                                                        @click="updateStatus(item, index, statusOption); statusDropdownOpen = null"
                                                        class="w-full text-left px-3 py-2 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors"
                                                        :class="statusOption === item.status ? 'bg-slate-700/30' : ''"
                                                    >
                                                        <span x-text="statusOption"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3" style="width: 150px; min-width: 150px;">
                                    <!-- Check if assigned to current user (for Required items) - Highest priority -->
                                    <template x-if="item.type === 'Required' && item.assigned_to != null && currentUserId != null && String(item.assigned_to) === String(currentUserId)">
                                        <div class="text-sm font-medium text-purple-300">You</div>
                                    </template>
                                    <!-- For Required items: Admin/Manager view - Show Account Name (Bold) and Client Username (Italic) -->
                                    <template x-if="item.type === 'Required' && !(item.assigned_to != null && currentUserId != null && String(item.assigned_to) === String(currentUserId)) && item.assigner_account_name && item.assigner_user_name && (isAdmin || isManager)">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="text-sm font-bold text-white" x-text="item.assigner_account_name"></span>
                                            <span class="text-xs italic text-slate-400" x-text="item.assigner_user_name"></span>
                                        </div>
                                    </template>
                                    <!-- For Required items: Client view - Show only Client Username (no account name) -->
                                    <template x-if="item.type === 'Required' && !(item.assigned_to != null && currentUserId != null && String(item.assigned_to) === String(currentUserId)) && item.assigner_user_name && !isAdmin && !isManager">
                                        <div class="text-sm text-white" x-text="item.assigner_user_name"></div>
                                    </template>
                                    <!-- For Required items: Fallback if no account/user info -->
                                    <template x-if="!(item.assigned_to != null && currentUserId != null && String(item.assigned_to) === String(currentUserId)) && item.type === 'Required' && (!item.assigner_account_name || !item.assigner_user_name)">
                                        <div class="text-sm text-slate-500">-</div>
                                    </template>
                                    <!-- For Task/Ticket: Admin view - Show assigned manager (check admin first) -->
                                    <template x-if="(item.type === 'Task' || item.type === 'Ticket') && isAdmin && item.assigned_to_name">
                                        <div class="text-sm text-white" x-text="item.assigned_to_name"></div>
                                    </template>
                                    <!-- For Task/Ticket: Admin view - No assigned manager -->
                                    <template x-if="(item.type === 'Task' || item.type === 'Ticket') && isAdmin && !item.assigned_to_name">
                                        <div class="text-sm text-slate-500">-</div>
                                    </template>
                                    <!-- For Task/Ticket: Manager view - Show "You" when assigned to current manager, else assigned_to_name (e.g. when client created task/ticket) -->
                                    <template x-if="(item.type === 'Task' || item.type === 'Ticket') && isManager && !isAdmin && item.assigned_to != null && currentUserId != null && String(item.assigned_to) === String(currentUserId)">
                                        <div class="text-sm font-medium text-purple-300">You</div>
                                    </template>
                                    <template x-if="(item.type === 'Task' || item.type === 'Ticket') && isManager && !isAdmin && (item.assigned_to == null || currentUserId == null || String(item.assigned_to) !== String(currentUserId)) && item.assigned_to_name">
                                        <div class="text-sm text-white" x-text="item.assigned_to_name"></div>
                                    </template>
                                    <template x-if="(item.type === 'Task' || item.type === 'Ticket') && isManager && !isAdmin && !item.assigned_to_name">
                                        <div class="text-sm text-slate-500">-</div>
                                    </template>
                                    <!-- For Task/Ticket: Client view - Show assigned manager name -->
                                    <template x-if="(item.type === 'Task' || item.type === 'Ticket') && !isAdmin && !isManager && item.assigned_to_name">
                                        <div class="text-sm text-white" x-text="item.assigned_to_name"></div>
                                    </template>
                                    <!-- For Task/Ticket: Client view - No assigned manager -->
                                    <template x-if="(item.type === 'Task' || item.type === 'Ticket') && !isAdmin && !isManager && !item.assigned_to_name">
                                        <div class="text-sm text-slate-500">-</div>
                                    </template>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="text-xs text-slate-400 whitespace-nowrap">
                                        <div x-text="formatDate(item.lastUpdated)"></div>
                                        <div x-text="formatTime(item.lastUpdated)" class="text-slate-500"></div>
                                    </div>
                                </td>
                                <td class="px-3 py-3" style="width: 150px; min-width: 150px;">
                                    <!-- Check if item was created by current user -->
                                    <template x-if="item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)">
                                        <div class="text-sm font-medium text-purple-300">You</div>
                                    </template>
                                    <!-- For Required items: Show creator info -->
                                    <!-- Admin/Manager view: Show manager/admin name or client account + user -->
                                    <template x-if="!(item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)) && item.type === 'Required' && item.assigner_info && (isAdmin || isManager)">
                                        <!-- Required item created by manager/admin - show creator name -->
                                        <div class="text-sm text-white" x-text="item.assigner_info"></div>
                                    </template>
                                    <template x-if="!(item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)) && item.type === 'Required' && !item.assigner_info && item.assigner_account_name && item.assigner_user_name && item.created_by_manager_id && (isAdmin || isManager)">
                                        <!-- Required item created by client user - show client account and user (from created_by) -->
                                        <div class="flex flex-col gap-0.5">
                                            <span class="text-sm font-bold text-white" x-text="item.assigner_account_name"></span>
                                            <span class="text-xs italic text-slate-400" x-text="item.assigner_user_name"></span>
                                        </div>
                                    </template>
                                    <!-- Client view: Show only user name (no account name) for Required items -->
                                    <template x-if="!(item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)) && item.type === 'Required' && item.assigner_info && !isAdmin && !isManager">
                                        <!-- Required item created by manager/admin - show creator name -->
                                        <div class="text-sm text-white" x-text="item.assigner_info"></div>
                                    </template>
                                    <template x-if="!(item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)) && item.type === 'Required' && !item.assigner_info && item.assigner_user_name && item.created_by_manager_id && !isAdmin && !isManager">
                                        <!-- Required item created by client user - show only user name (no account name) -->
                                        <div class="text-sm text-white" x-text="item.assigner_user_name"></div>
                                    </template>
                                    <!-- Fallback for Required items -->
                                    <template x-if="!(item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)) && item.type === 'Required' && !item.assigner_info && !item.assigner_account_name">
                                        <div class="text-sm text-slate-500">-</div>
                                    </template>
                                    <!-- Manager view: Show Account Name (Bold) and Client User Name (Italic) for client-created items (non-Required) -->
                                    <template x-if="!(item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)) && isManager && item.type !== 'Required' && item.assigner_account_name && item.assigner_user_name">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="text-sm font-bold text-white" x-text="item.assigner_account_name"></span>
                                            <span class="text-xs italic text-slate-400" x-text="item.assigner_user_name"></span>
                                        </div>
                                    </template>
                                    <!-- Manager view: Show manager name for manager-created items (non-Required) -->
                                    <template x-if="!(item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)) && isManager && item.type !== 'Required' && !item.assigner_account_name && item.assigner_info">
                                        <div class="text-sm text-white" x-text="item.assigner_info"></div>
                                    </template>
                                    <!-- Client view: Show manager name for manager-created items (non-Required) -->
                                    <template x-if="!(item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)) && !isManager && item.type !== 'Required' && item.assigner_info">
                                        <div class="text-sm text-white" x-text="item.assigner_info"></div>
                                    </template>
                                    <!-- Client view: Show only user name (no account name) for client-created items (non-Required) -->
                                    <template x-if="!(item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)) && !isManager && item.type !== 'Required' && item.assigner_user_name">
                                        <div class="text-sm text-white" x-text="item.assigner_user_name"></div>
                                    </template>
                                    <!-- Fallback: Show dash if no assigner info -->
                                    <template x-if="!(item.created_by && currentUserId && parseInt(item.created_by) === parseInt(currentUserId)) && !item.assigner_info && !item.assigner_account_name && !item.assigner_user_name">
                                        <div class="text-sm text-slate-500">-</div>
                                    </template>
                                </td>
                                <td class="px-3 py-3" style="width: 100px; min-width: 100px;">
                                    <div class="flex items-center justify-end" @click.stop>
                                        <?php if (isAdmin()): ?>
                                            <!-- Admin Actions: Full CRUD access for all item types -->
                                            <template x-if="item.type === 'Required'">
                                                <!-- Required: Upload icon, Edit, Drop -->
                                                <div class="flex items-center gap-1" x-show="!isDropped(item)" x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)">
                                                    <button 
                                                        @click.stop="openRequirementModal(item, index)"
                                                        class="text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors p-1.5 rounded-lg flex-shrink-0"
                                                        title="Provide Requirement"
                                                    >
                                                        <i data-lucide="upload" class="w-4 h-4"></i>
                                                    </button>
                                                    <div class="relative">
                                                        <button 
                                                            @click.stop="statusDropdownOpen = null; actionDropdownOpen = actionDropdownOpen === index ? null : index"
                                                            @click.away="actionDropdownOpen = null"
                                                            class="text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors p-1.5 rounded-lg flex-shrink-0"
                                                            title="Actions"
                                                        >
                                                            <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                                        </button>
                                                        <div 
                                                            x-show="actionDropdownOpen === index"
                                                            x-transition
                                                            @click.away="actionDropdownOpen = null"
                                                            class="absolute right-0 mt-1 w-40 bg-slate-800 border border-slate-700 rounded-lg shadow-xl"
                                                            style="display: none; z-index: 10000;"
                                                        >
                                                            <div class="py-1">
                                                                <button 
                                                                    @click="editItem(item, index); actionDropdownOpen = null"
                                                                    class="w-full text-left px-3 py-2 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors"
                                                                >Edit</button>
                                                                <div class="border-t border-slate-700 my-1"></div>
                                                                <button 
                                                                    @click="dropItem(item, index); actionDropdownOpen = null"
                                                                    class="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-red-500/20 transition-colors"
                                                                >
                                                                    <span x-text="'Drop ' + (item.type === 'Required' ? 'Requirement' : item.type)"></span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="item.type !== 'Required' && !isDropped(item)">
                                                <div class="relative" x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)">
                                                    <button 
                                                        @click.stop="statusDropdownOpen = null; actionDropdownOpen = actionDropdownOpen === index ? null : index"
                                                        @click.away="actionDropdownOpen = null"
                                                        class="text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors p-1.5 rounded-lg flex-shrink-0"
                                                        title="Actions"
                                                    >
                                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                                    </button>
                                                    <div 
                                                        x-show="actionDropdownOpen === index"
                                                        x-transition
                                                        @click.away="actionDropdownOpen = null"
                                                        class="absolute right-0 mt-1 w-40 bg-slate-800 border border-slate-700 rounded-lg shadow-xl"
                                                        style="display: none; z-index: 10000;"
                                                    >
                                                        <div class="py-1">
                                                            <button 
                                                                @click="editItem(item, index); actionDropdownOpen = null"
                                                                class="w-full text-left px-3 py-2 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors"
                                                            >Edit</button>
                                                            <div class="border-t border-slate-700 my-1"></div>
                                                            <button 
                                                                @click="dropItem(item, index); actionDropdownOpen = null"
                                                                class="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-red-500/20 transition-colors"
                                                            >
                                                                <span x-text="'Drop ' + (item.type === 'Required' ? 'Requirement' : item.type)"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <!-- Disabled indicator for dropped items -->
                                            <span x-show="isDropped(item)" class="text-slate-500 text-xs italic">Dropped</span>
                                        <?php elseif (isManager()): ?>
                                            <!-- Manager Actions -->
                                            <template x-if="item.type === 'Task'">
                                                <!-- Task: Drop only -->
                                                <div class="relative" x-show="!isDropped(item)" x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)">
                                                    <button 
                                                        @click.stop="statusDropdownOpen = null; actionDropdownOpen = actionDropdownOpen === index ? null : index"
                                                        @click.away="actionDropdownOpen = null"
                                                        class="text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors p-1.5 rounded-lg flex-shrink-0"
                                                        title="Actions"
                                                    >
                                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                                    </button>
                                                    <div 
                                                        x-show="actionDropdownOpen === index"
                                                        x-transition
                                                        @click.away="actionDropdownOpen = null"
                                                        class="absolute right-0 mt-1 w-40 bg-slate-800 border border-slate-700 rounded-lg shadow-xl"
                                                        style="display: none; z-index: 10000;"
                                                    >
                                                        <div class="py-1">
                                                            <button 
                                                                @click="dropItem(item, index); actionDropdownOpen = null"
                                                                class="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-red-500/20 transition-colors"
                                                            >
                                                                <span x-text="'Drop ' + (item.type === 'Required' ? 'Requirement' : item.type)"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="item.type === 'Ticket'">
                                                <!-- Ticket: Drop only (Status handled in Status column) -->
                                                <div class="relative" x-show="!isDropped(item)" x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)">
                                                    <button 
                                                        @click.stop="statusDropdownOpen = null; actionDropdownOpen = actionDropdownOpen === index ? null : index"
                                                        @click.away="actionDropdownOpen = null"
                                                        class="text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors p-1.5 rounded-lg flex-shrink-0"
                                                        title="Actions"
                                                    >
                                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                                    </button>
                                                    <div 
                                                        x-show="actionDropdownOpen === index"
                                                        x-transition
                                                        @click.away="actionDropdownOpen = null"
                                                        class="absolute right-0 mt-1 w-40 bg-slate-800 border border-slate-700 rounded-lg shadow-xl"
                                                        style="display: none; z-index: 10000;"
                                                    >
                                                        <div class="py-1">
                                                            <button 
                                                                @click="dropItem(item, index); actionDropdownOpen = null"
                                                                class="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-red-500/20 transition-colors"
                                                            >
                                                                <span x-text="'Drop ' + (item.type === 'Required' ? 'Requirement' : item.type)"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="item.type === 'Required'">
                                                <!-- Required: Upload icon, Edit, Drop -->
                                                <div class="flex items-center gap-1" x-show="!isDropped(item)" x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)">
                                                    <button 
                                                        @click.stop="openRequirementModal(item, index)"
                                                        class="text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors p-1.5 rounded-lg flex-shrink-0"
                                                        title="Provide Requirement"
                                                    >
                                                        <i data-lucide="upload" class="w-4 h-4"></i>
                                                    </button>
                                                    <div class="relative">
                                                        <button 
                                                            @click.stop="statusDropdownOpen = null; actionDropdownOpen = actionDropdownOpen === index ? null : index"
                                                            @click.away="actionDropdownOpen = null"
                                                            class="text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors p-1.5 rounded-lg flex-shrink-0"
                                                            title="Actions"
                                                        >
                                                            <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                                        </button>
                                                        <div 
                                                            x-show="actionDropdownOpen === index"
                                                            x-transition
                                                            @click.away="actionDropdownOpen = null"
                                                            class="absolute right-0 mt-1 w-40 bg-slate-800 border border-slate-700 rounded-lg shadow-xl"
                                                            style="display: none; z-index: 10000;"
                                                        >
                                                            <div class="py-1">
                                                                <button 
                                                                    @click="editItem(item, index); actionDropdownOpen = null"
                                                                    class="w-full text-left px-3 py-2 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors"
                                                                >Edit</button>
                                                                <div class="border-t border-slate-700 my-1"></div>
                                                                <button 
                                                                    @click="dropItem(item, index); actionDropdownOpen = null"
                                                                    class="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-red-500/20 transition-colors"
                                                                >
                                                                    <span x-text="'Drop ' + (item.type === 'Required' ? 'Requirement' : item.type)"></span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <!-- Disabled indicator for dropped items -->
                                            <span x-show="isDropped(item)" class="text-slate-500 text-xs italic">Dropped</span>
                                        <?php elseif (isClient()): ?>
                                            <!-- Client Actions -->
                                            <template x-if="item.type === 'Task' || item.type === 'Ticket'">
                                                <!-- Task/Ticket: Edit, Drop -->
                                                <div class="relative" x-show="!isDropped(item)" x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)">
                                                    <button 
                                                        @click.stop="statusDropdownOpen = null; actionDropdownOpen = actionDropdownOpen === index ? null : index"
                                                        @click.away="actionDropdownOpen = null"
                                                        class="text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors p-1.5 rounded-lg flex-shrink-0"
                                                        title="Actions"
                                                    >
                                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                                    </button>
                                                    <div 
                                                        x-show="actionDropdownOpen === index"
                                                        x-transition
                                                        @click.away="actionDropdownOpen = null"
                                                        class="absolute right-0 mt-1 w-40 bg-slate-800 border border-slate-700 rounded-lg shadow-xl"
                                                        style="display: none; z-index: 10000;"
                                                    >
                                                        <div class="py-1">
                                                            <button 
                                                                @click="editItem(item, index); actionDropdownOpen = null"
                                                                class="w-full text-left px-3 py-2 text-sm text-slate-300 hover:bg-slate-700/50 transition-colors"
                                                            >Edit</button>
                                                            <div class="border-t border-slate-700 my-1"></div>
                                                            <button 
                                                                @click="dropItem(item, index); actionDropdownOpen = null"
                                                                class="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-red-500/20 transition-colors"
                                                            >
                                                                <span x-text="'Drop ' + (item.type === 'Required' ? 'Requirement' : item.type)"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <!-- Required: Upload icon -->
                                            <template x-if="item.type === 'Required'">
                                                <div class="flex items-center" x-show="!isDropped(item)" x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)">
                                                    <button 
                                                        @click.stop="openRequirementModal(item, index)"
                                                        class="text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors p-1.5 rounded-lg flex-shrink-0"
                                                        title="Provide Requirement"
                                                    >
                                                        <i data-lucide="upload" class="w-4 h-4"></i>
                                                    </button>
                                                </div>
                                            </template>
                                            <!-- Disabled indicator for dropped items -->
                                            <span x-show="isDropped(item)" class="text-slate-500 text-xs italic">Dropped</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div 
                x-show="!isLoading && sortedItems.length > 0" 
                class="px-4 py-3 border-t border-slate-700/50 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-800/30"
                x-init="setTimeout(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); }, 50)"
            >
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <span>Showing</span>
                        <span class="text-white font-medium" x-text="Math.min((currentPage - 1) * itemsPerPage + 1, totalFilteredItems)"></span>
                        <span>to</span>
                        <span class="text-white font-medium" x-text="Math.min(currentPage * itemsPerPage, totalFilteredItems)"></span>
                        <span>of</span>
                        <span class="text-white font-medium" x-text="totalFilteredItems"></span>
                        <span>items</span>
        </div>
                    <!-- Items Per Page Selector -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-slate-400">Rows per page:</label>
                        <select
                            x-model="itemsPerPage"
                            @change="handleItemsPerPageChange()"
                            class="px-2 py-1.5 text-sm text-white bg-slate-700/50 border border-slate-600/50 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500/50 transition-all appearance-none cursor-pointer"
                            style="background-image: url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 12 12\'%3E%3Cpath fill=\'%2394a3b8\' d=\'M6 9L1 4h10z\'/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 0.75rem; padding-right: 2rem;"
                        >
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Previous Button -->
                    <button
                        @click="currentPage = Math.max(1, currentPage - 1); $nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); })"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-700/50'"
                        class="px-3 py-1.5 text-sm text-slate-300 bg-slate-700/30 border border-slate-600/50 rounded-lg transition-colors flex items-center gap-1"
                        title="Previous"
                    >
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                    
                    <!-- Page Numbers -->
                    <div class="flex items-center gap-1">
                        <template x-for="page in getPageNumbers()" :key="page">
                            <button
                                x-show="page !== '...'"
                                @click="goToPage(page); $nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); })"
                                :class="currentPage === page ? 'bg-purple-600/50 text-white border-purple-500/50' : 'text-slate-300 bg-slate-700/30 border-slate-600/50 hover:bg-slate-700/50'"
                                class="px-3 py-1.5 text-sm font-medium border rounded-lg transition-colors min-w-[2.5rem]"
                                x-text="page"
                            ></button>
                            <span x-show="page === '...'" class="px-2 text-slate-500">...</span>
                        </template>
                    </div>
                    
                    <!-- Next Button -->
                    <button
                        @click="currentPage = Math.min(totalPages, currentPage + 1); $nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); })"
                        :disabled="currentPage === totalPages"
                        :class="currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-700/50'"
                        class="px-3 py-1.5 text-sm text-slate-300 bg-slate-700/30 border border-slate-600/50 rounded-lg transition-colors flex items-center gap-1"
                        title="Next"
                    >
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div 
        x-show="isModalOpen"
        x-transition:enter="modal-enter"
        x-transition:leave="modal-exit"
        @click.away="closeModal()"
        @keydown.escape.window="closeModal()"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        <!-- Modal Content -->
        <div 
            @click.stop
            class="relative bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col"
        >
            <!-- Modal Header -->
            <div class="bg-slate-800 border-b border-slate-700 px-4 py-3 flex justify-between items-center rounded-t-2xl flex-shrink-0">
                <h2 class="text-lg font-bold text-white" x-text="editingItem ? 'Edit Item' : ((isAdmin || isManager) ? 'Requirements' : 'Add Task')"></h2>
                <button 
                    @click="closeModal()"
                    class="text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-slate-700"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Form Content -->
            <div class="p-4 overflow-y-auto flex-1">
                <form @submit.prevent="handleSubmit()">
                    <!-- Client Account and Users Selection (Only for Managers/Admins creating Required items) -->
                    <template x-if="(isAdmin || isManager) && !editingItem">
                        <div class="mb-4 space-y-3">
                            <!-- Client Account Selection -->
                            <div>
                                <label class="block text-slate-300 font-semibold mb-1.5 text-sm">Client Account <span class="text-red-400">*</span></label>
                                <select
                                    x-model="formData.selectedClientAccount"
                                    @change="onClientAccountChange()"
                                    required
                                    class="w-full px-3 py-2 bg-slate-700/50 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all appearance-none cursor-pointer"
                                >
                                    <option value="">Select Client Account</option>
                                    <template x-for="account in clientAccounts" :key="account.id">
                                        <option :value="account.id" x-text="account.name"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Client Users Selection (Checkboxes) -->
                            <div x-show="formData.selectedClientAccount && clientUsers.length > 0">
                                <label class="block text-slate-300 font-semibold mb-1.5 text-sm">Client Users <span class="text-red-400">*</span></label>
                                <div class="max-h-48 overflow-y-auto bg-slate-700/30 rounded-lg p-3 space-y-2 border border-slate-600/50">
                                    <template x-for="user in clientUsers" :key="user.id">
                                        <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-700/50 p-2 rounded transition-colors">
                                            <input
                                                type="checkbox"
                                                :value="user.id"
                                                x-model="formData.selectedClientUsers"
                                                class="w-4 h-4 text-purple-600 bg-slate-700 border-slate-500 rounded focus:ring-purple-500 focus:ring-2"
                                            />
                                            <span class="text-sm text-white" x-text="user.name"></span>
                                        </label>
                                    </template>
                                </div>
                                <p class="text-xs text-slate-400 mt-1.5" x-show="formData.selectedClientUsers.length === 0">
                                    Please select at least one client user
                                </p>
                            </div>
                            <div x-show="formData.selectedClientAccount && clientUsers.length === 0" class="text-sm text-slate-400 italic">
                                No users found for this account
                            </div>
                        </div>
                    </template>

                    <!-- Title Field -->
                    <div class="mb-4">
                        <label class="block text-slate-300 font-semibold mb-1.5 text-sm">Title <span class="text-red-400">*</span></label>
                        <input
                            type="text"
                            x-model="formData.title"
                            placeholder="Enter title..."
                            required
                            class="w-full px-3 py-2 bg-slate-700/50 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all text-sm"
                        />
                    </div>

                    <!-- Description Field -->
                    <div class="mb-4">
                        <label class="block text-slate-300 font-semibold mb-1.5 text-sm">Description <span class="text-red-400">*</span></label>
                        <textarea
                            x-model="formData.description"
                            placeholder="Enter description..."
                            rows="4"
                            required
                            class="w-full px-3 py-2 bg-slate-700/50 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all resize-vertical text-sm font-family-inherit"
                        ></textarea>
                        <!-- Temporarily commented out - Rephrase with AI button -->
                        <!--
                        <div class="mt-1.5 flex justify-end">
                            <button
                                type="button"
                                @click="rephraseWithAI()"
                                :disabled="isRephrasing"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 rounded-lg text-xs font-medium transition-colors disabled:opacity-50"
                                :class="isRephrasing ? 'ai-processing' : ''"
                            >
                                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                                <span x-text="isRephrasing ? 'Rephrasing...' : 'Rephrase with AI'"></span>
                            </button>
                        </div>
                        -->
                    </div>

                    <!-- Attach Media Field -->
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Attach Media (Optional)</label>
                        <div
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleFileDrop($event)"
                            @click="$refs.fileInput.click()"
                            class="drop-zone rounded-lg p-4 text-center cursor-pointer transition-all"
                            :class="isDragging ? 'drag-over' : 'bg-slate-700/30'"
                        >
                            <input
                                type="file"
                                x-ref="fileInput"
                                @change="handleFileSelect($event)"
                                multiple
                                accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt"
                                class="hidden"
                            />
                            <i data-lucide="upload" class="w-8 h-8 mx-auto mb-2 text-slate-400"></i>
                            <p class="text-slate-300 mb-0.5 text-xs">Drag & drop or click to browse</p>
                            <p class="text-xs text-slate-500">Docs, Images, Video, Audio</p>
                            <p class="text-xs text-slate-500 mt-1">
                                <i class="fas fa-info-circle"></i> Max file size: 50 MB
                            </p>
                        </div>
                        
                        <!-- File Previews -->
                        <div x-show="selectedFiles.length > 0" class="mt-2 space-y-1.5">
                            <template x-for="(file, index) in selectedFiles" :key="index">
                                <div class="flex items-center justify-between bg-slate-700/50 rounded-lg p-2">
                                    <div class="flex items-center gap-2 flex-1 min-w-0">
                                        <i :data-lucide="getFileIcon(file.type)" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs text-white truncate" x-text="file.name"></p>
                                            <p class="text-xs text-slate-400" x-text="formatFileSize(file.size)"></p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeFile(index)"
                                        class="text-slate-400 hover:text-red-400 transition-colors p-1 flex-shrink-0"
                                    >
                                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-700 flex-shrink-0">
                        <button
                            type="button"
                            @click="closeModal()"
                            class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-medium transition-colors text-sm"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="gradient-button text-white px-4 py-2 rounded-lg font-medium shadow-lg text-sm"
                        >
                            <span x-text="editingItem ? 'Update' : ((isAdmin || isManager) ? 'Requirements' : 'Add Task')"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div 
        x-show="isDetailModalOpen"
        x-transition:enter="modal-enter"
        x-transition:leave="modal-exit"
        @click.away="closeDetailModal()"
        @keydown.escape.window="closeDetailModal()"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        <!-- Modal Content -->
        <div 
            @click.stop
            class="relative bg-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col"
            style="overflow-x: hidden;"
        >
            <!-- Modal Header -->
            <div class="bg-slate-800 border-b border-slate-700 px-6 py-4 flex justify-between items-center rounded-t-2xl flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <!-- View Mode Title -->
                        <div x-show="!isEditingTitle" class="flex items-center gap-2 flex-1 min-w-0">
                            <h2 class="text-xl font-bold text-white truncate" x-text="selectedItem ? selectedItem.title : ''"></h2>
                            <template x-if="selectedItem && selectedItem.title_edited_at">
                                <span class="text-xs font-normal text-slate-400 flex-shrink-0">
                                    (Edited <span x-text="formatEditTimestamp(selectedItem.title_edited_at)"></span>)
                                </span>
                            </template>
                            <!-- Edit button for Client (Task/Ticket items) -->
                            <template x-if="isClient && selectedItem && (selectedItem.type === 'Task' || selectedItem.type === 'Ticket')">
                                <button
                                    @click="startEditTitle()"
                                    class="text-slate-400 hover:text-blue-400 transition-colors p-1 rounded-lg hover:bg-slate-700/50 flex-shrink-0"
                                    title="Edit Title"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            </template>
                        </div>
                        <!-- Edit Mode Title -->
                        <div x-show="isEditingTitle" class="flex items-center gap-2 flex-1 min-w-0">
                            <input
                                type="text"
                                x-model="editingTitle"
                                class="flex-1 bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-1.5 text-lg font-bold text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter title..."
                                @keydown.enter="saveEditTitle()"
                                @keydown.escape="cancelEditTitle()"
                            />
                            <div class="flex gap-2 flex-shrink-0">
                                <button
                                    @click="cancelEditTitle()"
                                    class="px-2 py-1 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-xs font-medium transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="saveEditTitle()"
                                    class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition-colors"
                                >
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <!-- Toggle badges for Required items -->
                        <template x-if="selectedItem && selectedItem.type === 'Required' && selectedItem.status === 'Provided' && (selectedItem.provided_description || (selectedItem.provided_attachments && selectedItem.provided_attachments.length > 0))">
                            <div class="flex items-center gap-2">
                                <button
                                    @click="detailModalActiveTab = 'required'"
                                    class="px-2 py-0.5 rounded-full text-xs font-medium transition-all"
                                    :class="detailModalActiveTab === 'required' ? 'bg-orange-500/30 text-orange-300 ring-2 ring-orange-500/50' : 'bg-orange-500/20 text-orange-400 hover:bg-orange-500/25'"
                                >
                                    Required
                                </button>
                                <button
                                    @click="detailModalActiveTab = 'provided'"
                                    class="px-2 py-0.5 rounded-full text-xs font-medium transition-all"
                                    :class="detailModalActiveTab === 'provided' ? 'bg-green-500/30 text-green-300 ring-2 ring-green-500/50' : 'bg-green-500/20 text-green-400 hover:bg-green-500/25'"
                                >
                                    Provided
                                </button>
                            </div>
                        </template>
                        <!-- Regular badges for non-Required items or Required items without Provided data -->
                        <template x-if="!selectedItem || selectedItem.type !== 'Required' || selectedItem.status !== 'Provided' || (!selectedItem.provided_description && (!selectedItem.provided_attachments || selectedItem.provided_attachments.length === 0))">
                            <div class="flex items-center gap-2">
                                <span 
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="selectedItem && selectedItem.type === 'Ticket' ? 'bg-blue-500/20 text-blue-300' : selectedItem && selectedItem.type === 'Required' ? 'bg-orange-500/20 text-orange-300' : 'bg-purple-500/20 text-purple-300'"
                                    x-text="selectedItem ? selectedItem.type : ''"
                                ></span>
                                <span 
                                    class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                    :class="selectedItem ? getStatusClass(selectedItem.status, selectedItem.type) : ''"
                                    x-text="selectedItem ? selectedItem.status : ''"
                                ></span>
                            </div>
                        </template>
                    </div>
                </div>
                <button 
                    @click="closeDetailModal()"
                    class="text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-slate-700"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="p-6 overflow-y-auto overflow-x-hidden flex-1 detail-modal-content">
                <template x-if="selectedItem">
                    <div class="space-y-6">
                        <!-- Required Section (shown when detailModalActiveTab === 'required' or for non-Required items) -->
                        <div x-show="(selectedItem.type === 'Required' && selectedItem.status === 'Provided' && (selectedItem.provided_description || (selectedItem.provided_attachments && selectedItem.provided_attachments.length > 0)) ? detailModalActiveTab === 'required' : true)">
                            <!-- Description Section -->
                            <div x-show="selectedItem.description && selectedItem.description.trim()">
                                <h3 class="text-sm font-semibold text-slate-300 mb-2 flex items-center gap-2">
                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                    Description
                                    <template x-if="selectedItem.description_edited_at">
                                        <span class="text-xs font-normal text-slate-400 ml-1">
                                            (Edited <span x-text="formatEditTimestamp(selectedItem.description_edited_at)"></span>)
                                        </span>
                                    </template>
                                    <!-- Edit button for Manager (Required items) and Client (Task/Ticket items) -->
                                    <template x-if="(isManager && selectedItem.type === 'Required') || (isClient && (selectedItem.type === 'Task' || selectedItem.type === 'Ticket'))">
                                        <button
                                            @click="startEditRequired()"
                                            class="ml-auto text-slate-400 hover:text-blue-400 transition-colors p-1 rounded-lg hover:bg-slate-700/50"
                                            :title="selectedItem.type === 'Required' ? 'Edit Required Description' : 'Edit Description'"
                                        >
                                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </template>
                                </h3>
                                <!-- View Mode -->
                                <div x-show="!isEditingRequired" class="bg-slate-700/30 rounded-lg p-4 text-sm text-slate-200 whitespace-pre-wrap break-words" x-text="selectedItem.description"></div>
                                <!-- Edit Mode -->
                                <div x-show="isEditingRequired" class="space-y-2">
                                    <textarea
                                        x-model="editingRequiredDescription"
                                        rows="4"
                                        class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                        placeholder="Enter description..."
                                    ></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button
                                            @click="cancelEditRequired()"
                                            class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-xs font-medium transition-colors"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            @click="saveEditRequired()"
                                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition-colors"
                                        >
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Attachments Section -->
                            <div x-show="(selectedItem.attachments && selectedItem.attachments.length > 0) || isEditingRequiredAttachments">
                                <h3 class="text-sm font-semibold text-slate-300 mb-3 flex items-center gap-2">
                                    <i data-lucide="paperclip" class="w-4 h-4"></i>
                                    Attachments (<span x-text="isEditingRequiredAttachments ? editingRequiredAttachmentsList.length : selectedItem.attachments.length"></span>)
                                    <template x-if="selectedItem.attachments_edited_at">
                                        <span class="text-xs font-normal text-slate-400 ml-1">
                                            (Edited <span x-text="formatEditTimestamp(selectedItem.attachments_edited_at)"></span>)
                                        </span>
                                    </template>
                                    <!-- Edit button for Manager (Required items) and Client (Task/Ticket items) -->
                                    <template x-if="(isManager && selectedItem.type === 'Required') || (isClient && (selectedItem.type === 'Task' || selectedItem.type === 'Ticket'))">
                                        <template x-if="!isEditingRequiredAttachments">
                                            <button
                                                @click="startEditRequiredAttachments()"
                                                class="ml-auto text-slate-400 hover:text-blue-400 transition-colors p-1 rounded-lg hover:bg-slate-700/50"
                                                :title="selectedItem.type === 'Required' ? 'Edit Required Attachments' : 'Edit Attachments'"
                                            >
                                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </template>
                                        <template x-if="isEditingRequiredAttachments">
                                            <div class="ml-auto flex gap-2">
                                                <button
                                                    @click="cancelEditRequiredAttachments()"
                                                    class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-xs font-medium transition-colors"
                                                >
                                                    Cancel
                                                </button>
                                                <button
                                                    @click="saveEditRequiredAttachments()"
                                                    class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition-colors"
                                                >
                                                    Save
                                                </button>
                                            </div>
                                        </template>
                                    </template>
                                </h3>
                                
                                <!-- View Mode -->
                                <div x-show="!isEditingRequiredAttachments" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[300px] overflow-y-auto overflow-x-hidden pr-1 attachment-grid">
                                    <template x-for="(attachment, index) in selectedItem.attachments" :key="index">
                                        <div class="bg-slate-700/30 rounded-lg p-2.5 hover:bg-slate-700/50 transition-colors flex flex-col min-w-0">
                                            <div class="flex items-start gap-2 mb-2">
                                                <i :data-lucide="getFileIcon(attachment.type)" class="w-4 h-4 text-slate-400 flex-shrink-0 mt-0.5"></i>
                                                <div class="min-w-0 flex-1 overflow-hidden">
                                                    <p class="text-xs text-white truncate font-medium" x-text="attachment.name" :title="attachment.name"></p>
                                                    <p class="text-xs text-slate-400 mt-0.5" x-text="formatFileSize(attachment.size)"></p>
                                                </div>
                                            </div>
                                            <div class="mt-auto flex gap-1.5">
                                            <button 
                                                    class="flex-1 text-slate-400 hover:text-blue-400 hover:bg-slate-600/50 transition-colors p-1.5 rounded-lg flex items-center justify-center"
                                                    title="Preview"
                                                    x-on:click.stop="previewAttachment(Object.assign({}, attachment, {item_id: selectedItem.id || selectedItem.db_id, index: index}), index)"
                                                >
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                                </button>
                                                <button 
                                                    class="flex-1 text-slate-400 hover:text-purple-400 hover:bg-slate-600/50 transition-colors p-1.5 rounded-lg flex items-center justify-center"
                                                title="Download"
                                                    x-on:click.stop="downloadAttachment(Object.assign({}, attachment, {item_id: selectedItem.id || selectedItem.db_id, index: index}), index)"
                                            >
                                                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                            </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                
                                <!-- Edit Mode -->
                                <div x-show="isEditingRequiredAttachments" class="space-y-3">
                                    <!-- Existing Attachments with Remove Button -->
                                    <div x-show="editingRequiredAttachmentsList.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[200px] overflow-y-auto overflow-x-hidden pr-1">
                                        <template x-for="(attachment, index) in editingRequiredAttachmentsList" :key="index">
                                            <div class="bg-slate-700/30 rounded-lg p-2.5 hover:bg-slate-700/50 transition-colors flex items-center gap-2 min-w-0">
                                                <i :data-lucide="getFileIcon(attachment.type)" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                                                <div class="min-w-0 flex-1 overflow-hidden">
                                                    <p class="text-xs text-white truncate font-medium" x-text="attachment.name" :title="attachment.name"></p>
                                                    <p class="text-xs text-slate-400 mt-0.5" x-text="formatFileSize(attachment.size)"></p>
                                                </div>
                                                <button
                                                    @click="removeRequiredAttachment(index)"
                                                    class="text-slate-400 hover:text-red-400 transition-colors p-1 flex-shrink-0"
                                                    title="Remove"
                                                >
                                                    <i data-lucide="x" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    
                                    <!-- Upload New Attachments -->
                                    <div class="border-t border-slate-700 pt-3">
                                        <label class="block text-xs font-medium text-slate-300 mb-2">Add New Attachments</label>
                                        <div
                                            @dragover.prevent="isDragging = true"
                                            @dragleave.prevent="isDragging = false"
                                            @drop.prevent="handleRequiredAttachmentDrop($event)"
                                            @click="$refs.requiredAttachmentInput.click()"
                                            class="drop-zone rounded-lg p-3 text-center cursor-pointer transition-all"
                                            :class="isDragging ? 'drag-over bg-blue-500/20' : 'bg-slate-700/30'"
                                        >
                                            <input
                                                type="file"
                                                x-ref="requiredAttachmentInput"
                                                @change="handleRequiredAttachmentSelect($event)"
                                                multiple
                                                accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt"
                                                class="hidden"
                                            />
                                            <i data-lucide="upload" class="w-6 h-6 mx-auto mb-1 text-slate-400"></i>
                                            <p class="text-slate-300 mb-0.5 text-xs">Drag & drop or click to add files</p>
                                            <p class="text-xs text-slate-500">Max file size: 50 MB</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Save/Cancel Buttons at Bottom -->
                                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-700">
                                        <button
                                            @click="cancelEditRequiredAttachments()"
                                            class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            @click="saveEditRequiredAttachments()"
                                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors"
                                        >
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Provided Section (shown when detailModalActiveTab === 'provided') -->
                        <div x-show="selectedItem.type === 'Required' && selectedItem.status === 'Provided' && (selectedItem.provided_description || (selectedItem.provided_attachments && selectedItem.provided_attachments.length > 0)) && detailModalActiveTab === 'provided'">
                            <h3 class="text-sm font-semibold text-slate-300 mb-3 flex items-center gap-2">
                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                Provided
                                <template x-if="selectedItem.provided_edited_at">
                                    <span class="text-xs font-normal text-slate-400 ml-1">
                                        (Edited <span x-text="formatEditTimestamp(selectedItem.provided_edited_at)"></span>)
                                    </span>
                                </template>
                                <!-- Edit button for Manager and Client -->
                                <template x-if="(isManager || isClient)">
                                    <button
                                        @click="startEditProvided()"
                                        class="ml-auto text-slate-400 hover:text-blue-400 transition-colors p-1 rounded-lg hover:bg-slate-700/50"
                                        title="Edit Provided Description"
                                    >
                                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                    </button>
                                </template>
                            </h3>
                            
                            <!-- Provided Description -->
                            <div x-show="selectedItem.provided_description && selectedItem.provided_description.trim()" class="mb-4">
                                <!-- View Mode -->
                                <div x-show="!isEditingProvided" class="bg-slate-700/30 rounded-lg p-4 text-sm text-slate-200 whitespace-pre-wrap break-words" x-text="selectedItem.provided_description"></div>
                                <!-- Edit Mode -->
                                <div x-show="isEditingProvided" class="space-y-2">
                                    <textarea
                                        x-model="editingProvidedDescription"
                                        rows="4"
                                        class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                        placeholder="Enter description..."
                                    ></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button
                                            @click="cancelEditProvided()"
                                            class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-xs font-medium transition-colors"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            @click="saveEditProvided()"
                                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition-colors"
                                        >
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Provided Attachments -->
                            <div x-show="(selectedItem.provided_attachments && selectedItem.provided_attachments.length > 0) || isEditingProvidedAttachments">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs text-slate-400">Attachments (<span x-text="isEditingProvidedAttachments ? editingProvidedAttachmentsList.length : selectedItem.provided_attachments.length"></span>)</span>
                                    <!-- Edit button for Manager and Client -->
                                    <template x-if="(isManager || isClient)">
                                        <template x-if="!isEditingProvidedAttachments">
                                            <button
                                                @click="startEditProvidedAttachments()"
                                                class="text-slate-400 hover:text-blue-400 transition-colors p-1 rounded-lg hover:bg-slate-700/50"
                                                title="Edit Provided Attachments"
                                            >
                                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </template>
                                        <template x-if="isEditingProvidedAttachments">
                                            <div class="flex gap-2">
                                                <button
                                                    @click="cancelEditProvidedAttachments()"
                                                    class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-xs font-medium transition-colors"
                                                >
                                                    Cancel
                                                </button>
                                                <button
                                                    @click="saveEditProvidedAttachments()"
                                                    class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition-colors"
                                                >
                                                    Save
                                                </button>
                                            </div>
                                        </template>
                                    </template>
                                </div>
                                
                                <!-- View Mode -->
                                <div x-show="!isEditingProvidedAttachments" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[300px] overflow-y-auto overflow-x-hidden pr-1 attachment-grid">
                                    <template x-for="(attachment, index) in selectedItem.provided_attachments" :key="index">
                                        <div class="bg-slate-700/30 rounded-lg p-2.5 hover:bg-slate-700/50 transition-colors flex flex-col min-w-0">
                                            <div class="flex items-start gap-2 mb-2">
                                                <i :data-lucide="getFileIcon(attachment.type)" class="w-4 h-4 text-slate-400 flex-shrink-0 mt-0.5"></i>
                                                <div class="min-w-0 flex-1 overflow-hidden">
                                                    <p class="text-xs text-white truncate font-medium" x-text="attachment.name" :title="attachment.name"></p>
                                                    <p class="text-xs text-slate-400 mt-0.5" x-text="formatFileSize(attachment.size)"></p>
                                                </div>
                                            </div>
                                            <div class="mt-auto flex gap-1.5">
                                                <button 
                                                    class="flex-1 text-slate-400 hover:text-blue-400 hover:bg-slate-600/50 transition-colors p-1.5 rounded-lg flex items-center justify-center"
                                                    title="Preview"
                                                    x-on:click.stop="previewAttachment(Object.assign({}, attachment, {item_id: selectedItem.id || selectedItem.db_id, index: index, provided: true}), index)"
                                                >
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                                </button>
                                                <button 
                                                    class="flex-1 text-slate-400 hover:text-purple-400 hover:bg-slate-600/50 transition-colors p-1.5 rounded-lg flex items-center justify-center"
                                                    title="Download"
                                                    x-on:click.stop="downloadAttachment(Object.assign({}, attachment, {item_id: selectedItem.id || selectedItem.db_id, index: index, provided: true}), index)"
                                                >
                                                    <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                
                                <!-- Edit Mode -->
                                <div x-show="isEditingProvidedAttachments" class="space-y-3">
                                    <!-- Existing Attachments with Remove Button -->
                                    <div x-show="editingProvidedAttachmentsList.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[200px] overflow-y-auto overflow-x-hidden pr-1">
                                        <template x-for="(attachment, index) in editingProvidedAttachmentsList" :key="index">
                                            <div class="bg-slate-700/30 rounded-lg p-2.5 hover:bg-slate-700/50 transition-colors flex items-center gap-2 min-w-0">
                                                <i :data-lucide="getFileIcon(attachment.type)" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                                                <div class="min-w-0 flex-1 overflow-hidden">
                                                    <p class="text-xs text-white truncate font-medium" x-text="attachment.name" :title="attachment.name"></p>
                                                    <p class="text-xs text-slate-400 mt-0.5" x-text="formatFileSize(attachment.size)"></p>
                                                </div>
                                                <button
                                                    @click="removeProvidedAttachment(index)"
                                                    class="text-slate-400 hover:text-red-400 transition-colors p-1 flex-shrink-0"
                                                    title="Remove"
                                                >
                                                    <i data-lucide="x" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    
                                    <!-- Upload New Attachments -->
                                    <div class="border-t border-slate-700 pt-3">
                                        <label class="block text-xs font-medium text-slate-300 mb-2">Add New Attachments</label>
                                        <div
                                            @dragover.prevent="isDragging = true"
                                            @dragleave.prevent="isDragging = false"
                                            @drop.prevent="handleProvidedAttachmentDrop($event)"
                                            @click="$refs.providedAttachmentInput.click()"
                                            class="drop-zone rounded-lg p-3 text-center cursor-pointer transition-all"
                                            :class="isDragging ? 'drag-over bg-blue-500/20' : 'bg-slate-700/30'"
                                        >
                                            <input
                                                type="file"
                                                x-ref="providedAttachmentInput"
                                                @change="handleProvidedAttachmentSelect($event)"
                                                multiple
                                                accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt"
                                                class="hidden"
                                            />
                                            <i data-lucide="upload" class="w-6 h-6 mx-auto mb-1 text-slate-400"></i>
                                            <p class="text-slate-300 mb-0.5 text-xs">Drag & drop or click to add files</p>
                                            <p class="text-xs text-slate-500">Max file size: 50 MB</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Save/Cancel Buttons at Bottom -->
                                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-700">
                                        <button
                                            @click="cancelEditProvidedAttachments()"
                                            class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            @click="saveEditProvidedAttachments()"
                                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors"
                                        >
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- No Description or Attachments Message -->
                        <div 
                            x-show="(detailModalActiveTab === 'required' && (!selectedItem.description || !selectedItem.description.trim()) && (!selectedItem.attachments || selectedItem.attachments.length === 0)) || (detailModalActiveTab === 'provided' && (!selectedItem.provided_description || !selectedItem.provided_description.trim()) && (!selectedItem.provided_attachments || selectedItem.provided_attachments.length === 0))"
                            class="text-center py-8 text-slate-400"
                        >
                            <i data-lucide="info" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            <p class="text-sm" x-text="detailModalActiveTab === 'provided' ? 'No provided description or attachments available' : 'No description or attachments available'"></p>
                        </div>

                        <!-- Status Tracking Timeline -->
                        <div x-show="selectedItem">
                            <h3 class="text-sm font-semibold text-slate-300 mb-3 flex items-center gap-2">
                                <i data-lucide="clock" class="w-4 h-4"></i>
                                Status Timeline
                            </h3>
                            <div class="bg-slate-700/30 rounded-lg p-4">
                                <div class="status-timeline">
                                    <template x-for="(status, index) in getAllStatusesForType(selectedItem.type, selectedItem.status)" :key="index">
                                        <div 
                                            class="status-timeline-item"
                                            :class="getTimelineState(status, selectedItem.status, getAllStatusesForType(selectedItem.type, selectedItem.status), selectedItem.type)"
                                        >
                                            <div class="status-timeline-content">
                                                <div class="status-timeline-status" x-text="status"></div>
                                                <div class="status-timeline-date">
                                                    <template x-if="getStatusDate(status, selectedItem, getAllStatusesForType(selectedItem.type, selectedItem.status), selectedItem.type)">
                                                        <div class="flex flex-col">
                                                            <span x-text="formatTimelineDate(getStatusDate(status, selectedItem, getAllStatusesForType(selectedItem.type, selectedItem.status), selectedItem.type))"></span>
                                                            <span x-text="formatTime(getStatusDate(status, selectedItem, getAllStatusesForType(selectedItem.type, selectedItem.status), selectedItem.type))" class="text-xs mt-0.5"></span>
                                    </div>
                                                    </template>
                                                    <template x-if="!getStatusDate(status, selectedItem, getAllStatusesForType(selectedItem.type, selectedItem.status), selectedItem.type) && getTimelineState(status, selectedItem.status, getAllStatusesForType(selectedItem.type, selectedItem.status), selectedItem.type) === 'completed'">
                                                        <span class="text-slate-500 italic">Date not available</span>
                                                    </template>
                                                    <template x-if="getTimelineState(status, selectedItem.status, getAllStatusesForType(selectedItem.type, selectedItem.status), selectedItem.type) === 'upcoming'">
                                                        <span class="text-slate-500 italic">Not reached</span>
                                                    </template>
                                </div>
                                    </div>
                                </div>
                                    </template>
                            </div>
                        </div>
                        </div>

                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="border-t border-slate-700 px-6 py-4 flex justify-end flex-shrink-0">
                <button
                    @click="closeDetailModal()"
                    class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-medium transition-colors text-sm"
                >
                    Close
                </button>
            </div>
        </div>
      </div>
  </div>
  
  <!-- Preview Modal -->
  <div 
      x-show="isPreviewModalOpen"
      @click.away="closePreviewModal()"
      @keydown.escape.window="closePreviewModal()"
      class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      style="display: none;"
      x-cloak
  >
      <div 
          @click.stop
          class="relative bg-slate-800 rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col"
      >
          <!-- Preview Modal Header -->
          <div class="bg-slate-800 border-b border-slate-700 px-6 py-4 flex justify-between items-center rounded-t-2xl flex-shrink-0">
              <div class="flex items-center gap-3 min-w-0">
                  <i :data-lucide="previewFile ? getFileIcon(previewFile.type) : 'file'" class="w-5 h-5 text-slate-400 flex-shrink-0"></i>
                  <h2 class="text-lg font-bold text-white truncate" x-text="previewFile ? previewFile.name : 'Preview'"></h2>
              </div>
              <button 
                  @click="closePreviewModal()"
                  class="text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-slate-700 flex-shrink-0"
              >
                  <i data-lucide="x" class="w-5 h-5"></i>
              </button>
          </div>

          <!-- Preview Content -->
          <div class="p-6 overflow-y-auto flex-1 flex items-center justify-center bg-slate-900/50">
              <div x-show="previewLoading" class="text-center">
                  <i data-lucide="loader-2" class="w-8 h-8 text-purple-400 mx-auto mb-2"></i>
                  <p class="text-slate-400 text-sm">Loading preview...</p>
              </div>
              
              <!-- Image Preview -->
              <div x-show="previewType === 'image' && !previewLoading" class="w-full">
                  <img :src="previewUrl" :alt="previewFile ? previewFile.name : ''" class="max-w-full max-h-[70vh] mx-auto rounded-lg shadow-lg" />
              </div>
              
              <!-- Video Preview -->
              <div x-show="previewType === 'video' && !previewLoading" class="w-full">
                  <video :src="previewUrl" controls class="max-w-full max-h-[70vh] mx-auto rounded-lg shadow-lg">
                      Your browser does not support the video tag.
                  </video>
              </div>
              
              <!-- Audio Preview -->
              <div x-show="previewType === 'audio' && !previewLoading" class="w-full max-w-md mx-auto">
                  <div class="bg-slate-700/50 rounded-lg p-6 text-center">
                      <i data-lucide="music" class="w-12 h-12 text-purple-400 mx-auto mb-4"></i>
                      <p class="text-white font-medium mb-4" x-text="previewFile ? previewFile.name : ''"></p>
                      <audio :src="previewUrl" controls class="w-full">
                          Your browser does not support the audio tag.
                      </audio>
                  </div>
              </div>
              
              <!-- PDF Preview -->
              <div x-show="previewType === 'pdf' && !previewLoading" class="w-full h-full min-h-[500px]">
                  <iframe :src="previewUrl" class="w-full h-full min-h-[500px] rounded-lg border border-slate-700" frameborder="0"></iframe>
              </div>
              
              <!-- Unsupported Preview -->
              <div x-show="previewType === 'unsupported' && !previewLoading" class="text-center py-12">
                  <i data-lucide="file-x" class="w-16 h-16 text-slate-500 mx-auto mb-4"></i>
                  <p class="text-slate-400 text-lg mb-2">Preview not available</p>
                  <p class="text-slate-500 text-sm mb-4">This file type cannot be previewed in the browser.</p>
                  <button 
                      @click="downloadAttachment(previewFile, 0)"
                      class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors text-sm"
                  >
                      <i data-lucide="download" class="w-4 h-4"></i>
                      <span>Download to view</span>
                </button>
        </div>
    </div>
  </div>

    <!-- Requirement Modal -->
    <div 
        x-show="isRequirementModalOpen"
        x-transition:enter="modal-enter"
        x-transition:leave="modal-exit"
        @click.away="closeRequirementModal()"
        @keydown.escape.window="closeRequirementModal()"
        class="fixed inset-0 flex items-start justify-center p-4 pt-8 bg-black/50 backdrop-blur-sm requirement-modal"
        id="requirementModal"
        style="z-index: 999999 !important;"
        @click="if ($event.target.id === 'requirementModal') closeRequirementModal()"
    >
        <!-- Modal Content -->
        <div 
            @click.stop
            class="relative bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col"
            style="z-index: 1000000 !important;"
        >
            <!-- Modal Header -->
            <div class="bg-slate-800 border-b border-slate-700 px-4 py-3 flex justify-between items-center rounded-t-2xl flex-shrink-0">
                <h2 class="text-lg font-bold text-white">Requirement</h2>
                <button 
                    @click="closeRequirementModal()"
                    class="text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-slate-700 requirement-close-btn"
                    id="requirementCloseBtn"
                    type="button"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="p-4 overflow-y-auto flex-1">
                <div class="mb-4">
                    <p class="text-sm text-slate-300 mb-2">
                        <strong class="text-white" x-text="requirementItem ? requirementItem.title : ''"></strong>
                    </p>
                    <p class="text-xs text-slate-400 mb-4">
                        Provide description and attachments. Status will be updated to "Provided" when you submit.
                    </p>
                </div>

                <!-- Description Field -->
                <div class="mb-4">
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Description</label>
                    <textarea
                        x-model="requirementDescription"
                        rows="4"
                        class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        placeholder="Enter description..."
                    ></textarea>
                </div>

                <!-- Attach Media Field -->
                <div class="mb-3">
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Attachments</label>
                    <div
                        class="drop-zone rounded-lg p-4 text-center cursor-pointer transition-all bg-slate-700/30"
                        id="requirementFileDropZone"
                    >
                        <input
                            type="file"
                            x-ref="requirementFileInput"
                            multiple
                            accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt"
                            class="hidden"
                            id="requirementFileInput"
                        />
                        <i data-lucide="upload" class="w-8 h-8 mx-auto mb-2 text-slate-400"></i>
                        <p class="text-slate-300 mb-0.5 text-xs">Drag & drop or click to browse</p>
                        <p class="text-xs text-slate-500">Docs, Images, Video, Audio</p>
                        <p class="text-xs text-slate-500 mt-1">
                            <i class="fas fa-info-circle"></i> Max file size: 50 MB
                        </p>
                    </div>
                    
                    <!-- File Previews -->
                    <div class="mt-2 space-y-1.5" id="requirementFilePreviews" style="display: none;">
                        <template x-for="(file, index) in requirementFiles" :key="index">
                            <div class="flex items-center justify-between bg-slate-700/50 rounded-lg p-2">
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <i :data-lucide="getFileIcon(file.type)" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-white truncate" x-text="file.name"></p>
                                        <p class="text-xs text-slate-400" x-text="formatFileSize(file.size)"></p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="removeRequirementFile(index)"
                                    class="text-slate-400 hover:text-red-400 transition-colors p-1 flex-shrink-0"
                                >
                                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-700 flex-shrink-0">
                    <button
                        type="button"
                        @click="closeRequirementModal()"
                        class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-medium transition-colors text-sm requirement-cancel-btn"
                        id="requirementCancelBtn"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="gradient-button text-white px-4 py-2 rounded-lg font-medium shadow-lg text-sm"
                        id="requirementSubmitBtn"
                    >
                        Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

<script>
    function ticketApp() {
        return {
            isModalOpen: false,
            activeTab: 'task',
            isRephrasing: false,
            isDragging: false,
            selectedFiles: [],
            formData: {
                title: '',
                description: '',
                selectedClientAccount: '',
                selectedClientUsers: []
            },
            clientAccounts: [],
            clientUsers: [],
            sortColumn: '',
            sortDirection: 'asc',
            selectedItem: null,
            isDetailModalOpen: false,
            editingIndex: null,
            editingItem: null,
            isPreviewModalOpen: false,
            previewFile: null,
            previewUrl: null,
            previewType: null,
            previewLoading: false,
            isRequirementModalOpen: false,
            requirementItem: null,
            requirementIndex: null,
            requirementDescription: '',
            requirementFiles: [],
            isRequirementDragging: false,
            _isSubmitting: false, // Flag to prevent duplicate submissions
            isManager: <?php echo $is_manager ? 'true' : 'false'; ?>,
            isClient: <?php echo $is_client ? 'true' : 'false'; ?>,
            isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>,
            isEditingTitle: false,
            editingTitle: '',
            isEditingRequired: false,
            isEditingProvided: false,
            isEditingRequiredAttachments: false,
            isEditingProvidedAttachments: false,
            editingRequiredDescription: '',
            editingRequiredFiles: [],
            editingProvidedDescription: '',
            editingProvidedFiles: [],
            editingRequiredAttachmentsList: [],
            editingProvidedAttachmentsList: [],
            currentUserId: null, // Will be set from API response
            items: [],
            isLoading: true,
            statusDropdownOpen: null, // Track which status dropdown is open (by index)
            actionDropdownOpen: null, // Track which action dropdown is open (by index)
            filtersExpanded: false, // Filter section collapsed by default
            detailModalActiveTab: 'required', // Track active tab in detail modal: 'required' or 'provided'
            filters: {
                searchId: '',
                itemType: '',
                status: '',
                lastUpdated: '',
                assigner: '',
                assignedTo: ''
            },
            assignerFilterOptions: [],
            assignedToFilterOptions: [],
            currentPage: 1,
            itemsPerPage: 10,
            
            get sortedItems() {
                let filtered = [...this.items];
                
                // Apply filters
                if (this.filters.searchId) {
                    const searchId = this.filters.searchId.toLowerCase().trim();
                    filtered = filtered.filter(item => {
                        const itemId = (item.id || '').toLowerCase();
                        return itemId.includes(searchId);
                    });
                }
                
                if (this.filters.itemType) {
                    filtered = filtered.filter(item => item.type === this.filters.itemType);
                }
                
                if (this.filters.status) {
                    filtered = filtered.filter(item => item.status === this.filters.status);
                }
                
                if (this.filters.lastUpdated) {
                    // Parse DD/MM/YYYY format
                    const dateParts = this.filters.lastUpdated.split('/');
                    let filterDate;
                    if (dateParts.length === 3) {
                        // DD/MM/YYYY format
                        const day = parseInt(dateParts[0], 10);
                        const month = parseInt(dateParts[1], 10) - 1; // Month is 0-indexed
                        const year = parseInt(dateParts[2], 10);
                        filterDate = new Date(year, month, day);
                    } else {
                        // Fallback to standard date parsing
                        filterDate = new Date(this.filters.lastUpdated);
                    }
                    filterDate.setHours(0, 0, 0, 0);
                    filtered = filtered.filter(item => {
                        const itemDate = new Date(item.lastUpdated);
                        itemDate.setHours(0, 0, 0, 0);
                        return itemDate.getTime() === filterDate.getTime();
                    });
                }
                
                if (this.filters.assigner && this.filters.assigner !== '') {
                    const assignerId = parseInt(this.filters.assigner);
                    if (!isNaN(assignerId)) {
                        filtered = filtered.filter(item => {
                            // Check if created_by matches the selected assigner ID
                            const itemCreatedBy = item.created_by ? parseInt(item.created_by) : null;
                            if (itemCreatedBy === assignerId) {
                                return true;
                            }
                            // For Required items created by managers/admins, assigner info comes from assigned_to
                            // So also check if assigned_to matches (for Required items)
                            if (item.type === 'Required' && item.assigned_to) {
                                const itemAssignedTo = parseInt(item.assigned_to);
                                if (itemAssignedTo === assignerId) {
                                    return true;
                                }
                            }
                            return false;
                        });
                    }
                }
                
                if (this.filters.assignedTo && this.filters.assignedTo !== '') {
                    const assignedToId = parseInt(this.filters.assignedTo);
                    if (!isNaN(assignedToId)) {
                        filtered = filtered.filter(item => {
                            // Check if assigned_to matches the selected ID
                            if (item.assigned_to) {
                                const itemAssignedTo = parseInt(item.assigned_to);
                                if (itemAssignedTo === assignedToId) {
                                    return true;
                                }
                            }
                            return false;
                        });
                    }
                }
                
                // Apply sorting
                if (this.sortColumn) {
                    filtered = filtered.sort((a, b) => {
                    let aVal = a.sortValue[this.sortColumn];
                    let bVal = b.sortValue[this.sortColumn];
                    
                    // Handle string comparison
                    if (typeof aVal === 'string') {
                        aVal = aVal.toLowerCase();
                        bVal = bVal.toLowerCase();
                    }
                    
                    if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
                    if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
                    return 0;
                });
                }
                
                return filtered;
            },
            
            get paginatedItems() {
                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                return this.sortedItems.slice(start, end);
            },
            
            get totalPages() {
                return Math.ceil(this.sortedItems.length / this.itemsPerPage);
            },
            
            get totalFilteredItems() {
                return this.sortedItems.length;
            },
            
            formatDate(date) {
                if (!date) return '';
                const d = new Date(date);
                const day = String(d.getDate()).padStart(2, '0');
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const year = d.getFullYear();
                return `${day}/${month}/${year}`;
            },
            
            formatDateForFilter(dateValue) {
                if (!dateValue) return '';
                // If it's already in DD/MM/YYYY format, return as is
                if (typeof dateValue === 'string' && dateValue.includes('/')) {
                    return dateValue;
                }
                // If it's in YYYY-MM-DD format (from date input), convert to DD/MM/YYYY
                if (typeof dateValue === 'string' && dateValue.includes('-')) {
                    const parts = dateValue.split('-');
                    if (parts.length === 3) {
                        return `${parts[2]}/${parts[1]}/${parts[0]}`;
                    }
                }
                // If it's a Date object or ISO string, format it
                const d = new Date(dateValue);
                if (isNaN(d.getTime())) return '';
                const day = String(d.getDate()).padStart(2, '0');
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const year = d.getFullYear();
                return `${day}/${month}/${year}`;
            },
            
            openDatePicker(event) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                this.$nextTick(() => {
                    const hiddenInput = this.$refs.hiddenDateInput;
                    if (hiddenInput) {
                        // If we have a current value in DD/MM/YYYY, convert it to YYYY-MM-DD for the date input
                        if (this.filters.lastUpdated && this.filters.lastUpdated.includes('/')) {
                            const parts = this.filters.lastUpdated.split('/');
                            if (parts.length === 3) {
                                const day = parts[0].padStart(2, '0');
                                const month = parts[1].padStart(2, '0');
                                const year = parts[2];
                                hiddenInput.value = `${year}-${month}-${day}`;
                            }
                        } else if (!this.filters.lastUpdated) {
                            // Clear the date input if no value
                            hiddenInput.value = '';
                        }
                        
                        // Focus and trigger the date picker
                        hiddenInput.focus();
                        
                        // Try showPicker() first (modern browsers), fallback to click()
                        if (hiddenInput.showPicker) {
                            hiddenInput.showPicker().catch((err) => {
                                hiddenInput.click();
                            });
                        } else {
                            hiddenInput.click();
                        }
                    }
                });
            },
            
            handleDateChange(event) {
                const dateValue = event.target.value; // YYYY-MM-DD format
                if (dateValue) {
                    // Convert YYYY-MM-DD to DD/MM/YYYY
                    const parts = dateValue.split('-');
                    if (parts.length === 3) {
                        this.filters.lastUpdated = `${parts[2]}/${parts[1]}/${parts[0]}`;
                        this.applyFilters();
                    }
                } else {
                    this.filters.lastUpdated = '';
                    this.applyFilters();
                }
            },
            
            handleDateInput(event) {
                const value = event.target.value;
                // Allow manual input in DD/MM/YYYY format
                // Validate format
                const datePattern = /^(\d{2})\/(\d{2})\/(\d{4})$/;
                if (value === '') {
                    this.filters.lastUpdated = '';
                    this.applyFilters();
                } else if (datePattern.test(value)) {
                    this.filters.lastUpdated = value;
                    this.applyFilters();
                }
            },
            
            formatTimelineDate(date) {
                if (!date) return '';
                const d = new Date(date);
                const day = String(d.getDate()).padStart(2, '0');
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const month = months[d.getMonth()];
                const year = d.getFullYear();
                return `${day}-${month}-${year}`;
            },
            
            formatEditTimestamp(date) {
                if (!date) return '';
                const d = new Date(date);
                const day = String(d.getDate()).padStart(2, '0');
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const month = months[d.getMonth()];
                const year = String(d.getFullYear()).slice(-2);
                let hours = d.getHours();
                const minutes = String(d.getMinutes()).padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12; // the hour '0' should be '12'
                const hoursStr = String(hours).padStart(2, '0');
                return `${day}/${month}/${year} ${hoursStr}:${minutes} ${ampm}`;
            },
            
            formatTime(date) {
                if (!date) return '';
                const d = new Date(date);
                let hours = d.getHours();
                const minutes = String(d.getMinutes()).padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12; // the hour '0' should be '12'
                const hoursStr = String(hours).padStart(2, '0');
                return `${hoursStr}:${minutes} ${ampm}`;
            },
            
            sortBy(column) {
                if (this.sortColumn === column) {
                    // Toggle direction if same column
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    // New column, default to ascending
                    this.sortColumn = column;
                    this.sortDirection = 'asc';
                }
                // Reset to first page when sorting changes
                this.resetPagination();
                // Re-initialize icons after sort
                this.reinitIcons();
            },
            
            getSortIcon(column) {
                if (this.sortColumn !== column) {
                    return 'chevrons-up-down'; // Neutral icon
                }
                return this.sortDirection === 'asc' ? 'chevron-up' : 'chevron-down';
            },
            
            async openModal() {
                this.isModalOpen = true;
                document.body.style.overflow = 'hidden';
                
                // Fetch client accounts if manager/admin
                if (this.isAdmin || this.isManager) {
                    await this.fetchClientAccounts();
                }
                
                // Initialize icons when modal opens
                this.$nextTick(() => {
                    if (typeof window.loadLucideIcons === 'function') {
                        window.loadLucideIcons();
                    }
                });
            },
            
            closeModal() {
                this.isModalOpen = false;
                document.body.style.overflow = '';
                this.resetForm();
            },
            
            resetForm() {
                this.formData = { 
                    title: '', 
                    description: '',
                    selectedClientAccount: '',
                    selectedClientUsers: []
                };
                this.selectedFiles = [];
                this.activeTab = 'task';
                this.isRephrasing = false;
                this.editingIndex = null;
                this.editingItem = null;
                this.clientUsers = [];
            },
            
            async fetchClientAccounts() {
                if (!this.isAdmin && !this.isManager) {
                    return;
                }
                
                try {
                    const response = await fetch('../ajax/updates_handler.php?action=get_client_accounts');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.clientAccounts = result.client_accounts || [];
                    } else {
                        this.clientAccounts = [];
                    }
                } catch (error) {
                    this.clientAccounts = [];
                }
            },
            
            async fetchClientUsers(clientAccountId) {
                if (!clientAccountId) {
                    this.clientUsers = [];
                    return;
                }
                
                try {
                    const response = await fetch(`../ajax/updates_handler.php?action=get_client_users&client_account_id=${clientAccountId}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.clientUsers = result.client_users || [];
                        // Reset selected users when account changes
                        this.formData.selectedClientUsers = [];
                    } else {
                        this.clientUsers = [];
                    }
                } catch (error) {
                    this.clientUsers = [];
                }
            },
            
            async onClientAccountChange() {
                if (this.formData.selectedClientAccount) {
                    await this.fetchClientUsers(this.formData.selectedClientAccount);
                } else {
                    this.clientUsers = [];
                    this.formData.selectedClientUsers = [];
                }
            },
            
            async handleSubmit() {
                // Collaboration workspace logic
                const isManager = this.isManager;
                const isClient = this.isClient;
                const isAdmin = this.isAdmin;
                
                // Determine item type based on role
                // Admin and Manager can create: Required (Requirements/Inputs Required)
                // Client can create: Task (only Task, not Tickets)
                let itemType;
                if (isAdmin || isManager) {
                    itemType = 'Required'; // Admin and Manager create Required items
                } else if (isClient) {
                    itemType = 'Task'; // Client can only create Task items
                } else {
                    itemType = 'Task'; // Fallback
                }
                
                const defaultStatus = 'Assigned';
                
                try {
                    if (this.editingItem && this.editingIndex !== null) {
                        // Update existing item
                        const formData = new FormData();
                        formData.append('action', 'update_item');
                        formData.append('item_id', this.editingItem.id || this.editingItem.db_id);
                        formData.append('title', this.formData.title);
                        formData.append('description', this.formData.description);
                        
                        // Separate existing attachments from new files
                        const existingAttachments = [];
                        const newFilesWithFileObject = [];
                        const newFilesWithBase64Only = [];
                        
                        this.selectedFiles.forEach(file => {
                            if (file.path) {
                                // Existing attachment - send in attachments_json only
                                existingAttachments.push({
                                    name: file.name,
                                    size: file.size,
                                    type: file.type,
                                    path: file.path
                                });
                            } else if (file.file && file.file instanceof File) {
                                // New file with File object - send via $_FILES only
                                newFilesWithFileObject.push(file);
                            } else if (file.fileData) {
                                // New file with only base64 - send in attachments_json only
                                newFilesWithBase64Only.push({
                                    name: file.name,
                                    size: file.size,
                                    type: file.type,
                                    fileData: file.fileData
                                });
                            }
                        });
                        
                        // Combine existing + new base64 files for attachments_json
                        const allAttachmentsForJson = [...existingAttachments, ...newFilesWithBase64Only];
                        if (allAttachmentsForJson.length > 0) {
                            formData.append('attachments_json', JSON.stringify(allAttachmentsForJson));
                        }
                        
                        // Add new File objects as uploads
                        newFilesWithFileObject.forEach((file) => {
                            formData.append('attachments[]', file.file);
                        });
                        
                        const response = await fetch('../ajax/task_ticket_handler.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('Item updated successfully!');
                            await this.loadItems(); // Reload items from database
                this.closeModal();
                        } else {
                            alert('Error: ' + (result.message || 'Failed to update item'));
                        }
                    } else {
                        // Create new item
                        // Validate client account and users selection for Required items
                        if (itemType === 'Required' && (this.isAdmin || this.isManager)) {
                            if (!this.formData.selectedClientAccount) {
                                alert('Please select a client account');
                                return;
                            }
                            if (!this.formData.selectedClientUsers || this.formData.selectedClientUsers.length === 0) {
                                alert('Please select at least one client user');
                                return;
                            }
                        }
                        
                        const formData = new FormData();
                        formData.append('action', 'create_item');
                        formData.append('type', itemType);
                        formData.append('title', this.formData.title);
                        formData.append('description', this.formData.description);
                        
                        // Add client account and users for Required items
                        if (itemType === 'Required' && (this.isAdmin || this.isManager)) {
                            formData.append('client_account_id', this.formData.selectedClientAccount);
                            formData.append('client_user_ids', JSON.stringify(this.formData.selectedClientUsers));
                        }
                        
                        // Separate files: those with File objects vs those with only base64
                        const filesWithFileObject = [];
                        const filesWithBase64Only = [];
                        
                        this.selectedFiles.forEach(file => {
                            if (file.file && file.file instanceof File) {
                                // Has File object - send via $_FILES only
                                filesWithFileObject.push(file);
                            } else if (file.fileData) {
                                // Only has base64 - send via attachments_json only
                                filesWithBase64Only.push({
                                    name: file.name,
                                    size: file.size,
                                    type: file.type,
                                    fileData: file.fileData
                                });
                            }
                        });
                        
                        // Add base64-only files as JSON
                        if (filesWithBase64Only.length > 0) {
                            formData.append('attachments_json', JSON.stringify(filesWithBase64Only));
                        }
                        
                        // Add File objects as uploads
                        filesWithFileObject.forEach((file) => {
                            formData.append('attachments[]', file.file);
                        });
                        
                        const response = await fetch('../ajax/task_ticket_handler.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        // Check if response is OK
                        if (!response.ok) {
                            const text = await response.text();
                            alert('Server error: ' + response.status + '. Please check console for details.');
                            return;
                        }
                        
                        // Check if response is JSON
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            const text = await response.text();
                            alert('Server returned invalid response. Please check console for details.');
                            return;
                        }
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert(`${itemType} (${result.item.id}) submitted successfully!`);
                            await this.loadItems(); // Reload items from database
                this.closeModal();
                        } else {
                            alert('Error: ' + (result.message || 'Failed to create item'));
                        }
                    }
                } catch (error) {
                    // Try to get response text for debugging
                    try {
                        const response = await fetch('../ajax/task_ticket_handler.php?action=create_item', {
                            method: 'POST',
                            body: new FormData()
                        });
                        const text = await response.text();
                    } catch (e) {
                    }
                    alert('An error occurred: ' + error.message);
                }
            },
            
            async fetchFilterOptions() {
                try {
                    const response = await fetch('../ajax/task_ticket_handler.php?action=get_filter_options');
                    if (!response.ok) {
                        throw new Error('Failed to fetch filter options');
                    }
                    const result = await response.json();
                    if (result.success) {
                        this.assignerFilterOptions = result.assigner_options || [];
                        this.assignedToFilterOptions = result.assigned_to_options || [];
                    }
                } catch (error) {
                    this.assignerFilterOptions = [];
                    this.assignedToFilterOptions = [];
                }
            },
            
            async loadItems() {
                try {
                    this.isLoading = true;
                    const response = await fetch('../ajax/task_ticket_handler.php?action=get_items');
                    
                    // Check if response is OK
                    if (!response.ok) {
                        const text = await response.text();
                        this.items = [];
                        this.isLoading = false;
                        return;
                    }
                    
                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        this.items = [];
                        this.isLoading = false;
                        return;
                    }
                    
                    const result = await response.json();
                    
                    
                    if (result.success && result.items) {
                        // Store current user ID from response
                        if (result.current_user_id) {
                            this.currentUserId = result.current_user_id;
                        }
                        
                        // Convert database items to frontend format
                        this.items = result.items.map(item => {
                            // Deduplicate attachments for each item
                            let attachments = item.attachments || [];
                            if (Array.isArray(attachments) && attachments.length > 0) {
                                const seen = new Set();
                                attachments = attachments.filter(attachment => {
                                    // Create a unique key for each attachment
                                    const key = attachment.path || `${attachment.name}_${attachment.size || 0}`;
                                    if (seen.has(key)) {
                                        return false; // Duplicate, filter it out
                                    }
                                    seen.add(key);
                                    return true; // Keep this attachment
                                });
                            }
                            
                            // Process provided_attachments
                            let provided_attachments = item.provided_attachments || [];
                            if (!Array.isArray(provided_attachments) && typeof provided_attachments === 'string') {
                                try {
                                    provided_attachments = JSON.parse(provided_attachments);
                                } catch (e) {
                                    provided_attachments = [];
                                }
                            }
                            
                            return {
                                id: item.id,
                                db_id: item.db_id,
                                type: item.type,
                                title: item.title,
                                description: item.description || '',
                                status: item.status,
                                createdBy: item.createdBy,
                                created_by: item.created_by || null,
                                created_by_manager_id: item.created_by_manager_id || null,
                                assigned_to: item.assigned_to || null,
                                assigned_to_name: item.assigned_to_name || null,
                                assigner_info: item.assigner_info || null,
                                assigner_account_name: item.assigner_account_name || null,
                                assigner_user_name: item.assigner_user_name || null,
                                attachments: attachments,
                                provided_description: item.provided_description || null,
                                provided_attachments: provided_attachments,
                                provided_edited_at: item.provided_edited_at ? new Date(item.provided_edited_at) : null,
                                lastUpdated: new Date(item.lastUpdated),
                                created_at: item.created_at ? new Date(item.created_at) : new Date(item.lastUpdated),
                                status_updated_at: item.status_updated_at ? new Date(item.status_updated_at) : null,
                                title_edited_at: item.title_edited_at ? new Date(item.title_edited_at) : null,
                                description_edited_at: item.description_edited_at ? new Date(item.description_edited_at) : null,
                                attachments_edited_at: item.attachments_edited_at ? new Date(item.attachments_edited_at) : null,
                                sortValue: {
                                    type: item.type,
                                    title: item.title,
                                    status: item.status,
                                    lastUpdated: new Date(item.lastUpdated).getTime()
                                }
                            };
                        });
                        
                        // Log ticket count for debugging
                        const ticketCount = this.items.filter(item => item.type === 'Ticket').length;
                    } else {
                        this.items = [];
                    }
                } catch (error) {
                    // Try to get response text for debugging
                    try {
                        const response = await fetch('../ajax/task_ticket_handler.php?action=get_items');
                        const text = await response.text();
                    } catch (e) {
                    }
                    this.items = [];
                } finally {
                    this.isLoading = false;
                }
            },
            
            rephraseWithAI() {
                if (!this.formData.description.trim()) {
                    alert('Please enter a description first');
                    return;
                }
                
                this.isRephrasing = true;
                
                // Simulate AI processing
                setTimeout(() => {
                    // Dummy rephrased text (in real app, this would come from API)
                    const rephrased = this.formData.description + ' [AI Enhanced]';
                    this.formData.description = rephrased;
                    this.isRephrasing = false;
                }, 2000);
            },
            
            handleFileSelect(event) {
                const files = Array.from(event.target.files);
                this.addFiles(files);
            },
            
            handleFileDrop(event) {
                this.isDragging = false;
                const files = Array.from(event.dataTransfer.files);
                this.addFiles(files);
            },
            
            addFiles(files) {
                const maxSize = 50 * 1024 * 1024; // 50MB
                files.forEach(file => {
                    // Check file size before adding
                    if (file.size > maxSize) {
                        alert(`File "${file.name}" exceeds 50MB limit. Please select a smaller file.`);
                        return;
                    }
                    
                    if (!this.selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                        // Store file with base64 data for download capability
                        const fileObj = {
                            name: file.name,
                            size: file.size,
                            type: file.type,
                            file: file // Keep original file object
                        };
                        
                        // Read file as base64 for localStorage storage
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const base64Data = e.target.result.split(',')[1]; // Remove data:type;base64, prefix
                            const fileIndex = this.selectedFiles.findIndex(f => f.name === file.name && f.size === file.size);
                            if (fileIndex !== -1) {
                                this.selectedFiles[fileIndex].fileData = base64Data;
                            }
                        };
                        reader.readAsDataURL(file);
                        
                        this.selectedFiles.push(fileObj);
                    }
                });
            },
            
            removeFile(index) {
                this.selectedFiles.splice(index, 1);
            },
            
            getFileIcon(fileType) {
                if (fileType.startsWith('image/')) return 'image';
                if (fileType.startsWith('video/')) return 'video';
                if (fileType.startsWith('audio/')) return 'music';
                return 'file';
            },
            
            formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
            },
            
            downloadAttachment(attachment, index) {
                
                // Method 1: Check if attachment has file data (base64) stored in localStorage
                if (attachment.fileData) {
                    try {
                        // Convert base64 to blob
                        const byteCharacters = atob(attachment.fileData);
                        const byteNumbers = new Array(byteCharacters.length);
                        for (let i = 0; i < byteCharacters.length; i++) {
                            byteNumbers[i] = byteCharacters.charCodeAt(i);
                        }
                        const byteArray = new Uint8Array(byteNumbers);
                        const blob = new Blob([byteArray], { type: attachment.type || 'application/octet-stream' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = attachment.name;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                        return;
                    } catch (e) {
                        alert('Error processing file data. Please try again.');
                    }
                }
                
                // Method 2: Try to download from server if attachment has path
                if (attachment.path) {
                    const downloadUrl = `../ajax/task_ticket_handler.php?action=download_attachment&id=${encodeURIComponent(attachment.path)}`;
                    window.location.href = downloadUrl;
                    return;
                }
                
                // Method 2b: Download from database using item ID and attachment index
                if (attachment.item_id && attachment.index !== undefined) {
                    const providedParam = attachment.provided ? '&provided=1' : '';
                    const downloadUrl = `../ajax/task_ticket_handler.php?action=download_attachment&id=${encodeURIComponent(attachment.item_id)}&index=${attachment.index}${providedParam}`;
                    window.location.href = downloadUrl;
                    return;
                }
                
                // Method 3: Check if original file object is available (for newly added files)
                if (attachment.file && attachment.file instanceof File) {
                    const url = URL.createObjectURL(attachment.file);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = attachment.name;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    return;
                }
                
                // Method 4: For default items without fileData, show informative message
                alert('This attachment is a demo item and doesn\'t contain actual file data. Real attachments uploaded through the form will be downloadable.');
            },
            
            previewAttachment(attachment, index) {
                
                // Validate attachment has required properties
                if (!attachment) {
                    alert('Cannot preview: Attachment data is missing');
                    return;
                }
                
                // Check if we have at least one way to access the file
                const hasPath = attachment.path && attachment.path.trim() !== '';
                const hasFileData = attachment.fileData && attachment.fileData.trim() !== '';
                const hasFile = attachment.file && attachment.file instanceof File;
                const hasItemId = attachment.item_id && attachment.index !== undefined;
                
                if (!hasPath && !hasFileData && !hasFile && !hasItemId) {
                    alert('Cannot preview: Attachment data is missing');
                    return;
                }
                
                let previewUrl = null;
                
                // Method 1: From base64 fileData - create data URL
                if (hasFileData) {
                    try {
                        const mimeType = attachment.type || 'application/octet-stream';
                        previewUrl = `data:${mimeType};base64,${attachment.fileData}`;
                        // Open in new tab immediately for base64
                        window.open(previewUrl, '_blank');
                        return;
                    } catch (e) {
                    }
                }
                
                // Method 2: From File object - create blob URL
                if (!previewUrl && hasFile) {
                    previewUrl = URL.createObjectURL(attachment.file);
                    // Open in new tab immediately for File object
                    window.open(previewUrl, '_blank');
                    return;
                }
                
                // Method 3: From server path - use download handler with preview parameter
                if (!previewUrl && hasPath) {
                    previewUrl = `../ajax/task_ticket_handler.php?action=download_attachment&id=${encodeURIComponent(attachment.path)}&preview=1`;
                    // Open in new tab (same as report.php view button)
                    window.open(previewUrl, '_blank');
                    return;
                }
                
                // Method 4: From database using item ID and attachment index
                if (!previewUrl && hasItemId) {
                    const providedParam = attachment.provided ? '&provided=1' : '';
                    previewUrl = `../ajax/task_ticket_handler.php?action=download_attachment&id=${encodeURIComponent(attachment.item_id)}&index=${attachment.index}&preview=1${providedParam}`;
                    // Open in new tab (same as report.php view button)
                    window.open(previewUrl, '_blank');
                    return;
                }
                
                // Fallback: Cannot preview
                alert('Cannot preview this attachment. Please try downloading it instead.');
            },
            
            closePreviewModal() {
                this.isPreviewModalOpen = false;
                this.previewFile = null;
                this.previewUrl = null;
                this.previewType = null;
                this.previewLoading = false;
                document.body.style.overflow = '';
                
                // Revoke object URL if it was created from File object
                if (this.previewUrl && this.previewUrl.startsWith('blob:')) {
                    URL.revokeObjectURL(this.previewUrl);
                }
            },
            
            getStatusClass(status, type) {
                // Dropped status (applies to all types)
                if (status === 'Dropped') {
                    return 'bg-slate-600/30 text-slate-500 line-through';
                }
                
                // Task statuses
                if (type === 'Task') {
                    const taskClasses = {
                        'Assigned': 'bg-slate-500/20 text-slate-300',
                        'Working': 'bg-blue-500/20 text-blue-300',
                        'Review': 'bg-indigo-500/20 text-indigo-300',
                        'Revise': 'bg-amber-500/20 text-amber-300',
                        'Approved': 'bg-green-500/20 text-green-300',
                        'Completed': 'bg-emerald-500/20 text-emerald-300'
                    };
                    return taskClasses[status] || 'bg-slate-500/20 text-slate-300';
                }
                
                // Required statuses
                if (type === 'Required') {
                    const requiredClasses = {
                        'Requested': 'bg-yellow-500/20 text-yellow-300',
                        'Provided': 'bg-green-500/20 text-green-300',
                        'Assigned': 'bg-slate-500/20 text-slate-300',
                        'Working': 'bg-blue-500/20 text-blue-300',
                        'Review': 'bg-indigo-500/20 text-indigo-300',
                        'Revise': 'bg-amber-500/20 text-amber-300',
                        'Approved': 'bg-green-500/20 text-green-300',
                        'Completed': 'bg-emerald-500/20 text-emerald-300'
                    };
                    return requiredClasses[status] || 'bg-slate-500/20 text-slate-300';
                }
                
                // Ticket statuses
                if (type === 'Ticket') {
                    const ticketClasses = {
                        'Raised': 'bg-orange-500/20 text-orange-300',
                        'In Progress': 'bg-cyan-500/20 text-cyan-300',
                        'Resolved': 'bg-green-500/20 text-green-300'
                    };
                    return ticketClasses[status] || 'bg-slate-500/20 text-slate-300';
                }
                
                return 'bg-slate-500/20 text-slate-300';
            },
            
            viewItem(item) {
                // Prevent opening detail modal for dropped items
                if (this.isDropped(item)) {
                    return;
                }
                
                // Create a copy of the item and deduplicate attachments
                const itemCopy = { ...item };
                
                // Ensure provided fields are included
                itemCopy.provided_description = item.provided_description || null;
                itemCopy.provided_attachments = item.provided_attachments || [];
                itemCopy.provided_edited_at = item.provided_edited_at || null;
                
                if (itemCopy.attachments && Array.isArray(itemCopy.attachments)) {
                    // Remove duplicate attachments based on path or name+size combination
                    const seen = new Set();
                    itemCopy.attachments = itemCopy.attachments.filter(attachment => {
                        // Ensure attachment is an object
                        if (!attachment || typeof attachment !== 'object') {
                            return false;
                        }
                        
                        // Create a unique key for each attachment
                        const key = attachment.path || `${attachment.name}_${attachment.size || 0}`;
                        if (seen.has(key)) {
                            return false; // Duplicate, filter it out
                        }
                        seen.add(key);
                        
                        // Ensure attachment has all required properties
                        if (!attachment.type && attachment.name) {
                            // Try to infer type from filename
                            const ext = attachment.name.split('.').pop().toLowerCase();
                            const typeMap = {
                                'pdf': 'application/pdf',
                                'jpg': 'image/jpeg',
                                'jpeg': 'image/jpeg',
                                'png': 'image/png',
                                'gif': 'image/gif',
                                'mp4': 'video/mp4',
                                'mp3': 'audio/mpeg',
                                'wav': 'audio/wav',
                                'doc': 'application/msword',
                                'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'xls': 'application/vnd.ms-excel',
                                'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'txt': 'text/plain'
                            };
                            attachment.type = typeMap[ext] || 'application/octet-stream';
                        }
                        
                        return true; // Keep this attachment
                    });
                }
                
                // Process provided_attachments similarly
                if (itemCopy.provided_attachments && Array.isArray(itemCopy.provided_attachments)) {
                    itemCopy.provided_attachments = itemCopy.provided_attachments.map(attachment => {
                        if (!attachment.type && attachment.name) {
                            const ext = attachment.name.split('.').pop().toLowerCase();
                            const typeMap = {
                                'pdf': 'application/pdf',
                                'jpg': 'image/jpeg',
                                'jpeg': 'image/jpeg',
                                'png': 'image/png',
                                'gif': 'image/gif',
                                'mp4': 'video/mp4',
                                'mp3': 'audio/mpeg',
                                'wav': 'audio/wav',
                                'doc': 'application/msword',
                                'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'xls': 'application/vnd.ms-excel',
                                'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'txt': 'text/plain'
                            };
                            attachment.type = typeMap[ext] || 'application/octet-stream';
                        }
                        return attachment;
                    });
                }
                
                this.selectedItem = itemCopy;
                // Always default to 'required' tab when opening modal
                this.detailModalActiveTab = 'required';
                this.isDetailModalOpen = true;
                document.body.style.overflow = 'hidden';
                
                // Initialize icons for edit buttons
                this.initDetailModalIcons();
                this.$nextTick(() => {
                    setTimeout(() => {
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }, 100);
                });
            },
            
            // Initialize icons when detail modal opens
            initDetailModalIcons() {
                this.$nextTick(() => {
                    setTimeout(() => {
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }, 100);
                });
            },
            
            closeDetailModal() {
                this.isDetailModalOpen = false;
                this.selectedItem = null;
                this.isEditingRequired = false;
                this.isEditingProvided = false;
                this.isEditingRequiredAttachments = false;
                this.isEditingProvidedAttachments = false;
                this.isEditingTitle = false;
                this.editingRequiredDescription = '';
                this.editingRequiredFiles = [];
                this.editingProvidedDescription = '';
                this.editingProvidedFiles = [];
                this.editingRequiredAttachmentsList = [];
                this.editingProvidedAttachmentsList = [];
                this.editingTitle = '';
                document.body.style.overflow = '';
            },
            
            startEditTitle() {
                this.isEditingTitle = true;
                this.editingTitle = this.selectedItem.title || '';
            },
            
            cancelEditTitle() {
                this.isEditingTitle = false;
                this.editingTitle = '';
            },
            
            async saveEditTitle() {
                if (!this.selectedItem) return;
                
                if (!this.editingTitle || !this.editingTitle.trim()) {
                    alert('Title cannot be empty');
                    return;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'update_item');
                    formData.append('item_id', this.selectedItem.id || this.selectedItem.db_id);
                    formData.append('title', this.editingTitle.trim());
                    formData.append('description', this.selectedItem.description || ''); // Include description (required by backend)
                    
                    const response = await fetch('../ajax/task_ticket_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.selectedItem.title = this.editingTitle.trim();
                        this.isEditingTitle = false;
                        await this.loadItems();
                        // Refresh selectedItem from items array
                        const updatedItem = this.items.find(i => 
                            (i.id === this.selectedItem.id || i.id === this.selectedItem.db_id) ||
                            (i.db_id === this.selectedItem.id || i.db_id === this.selectedItem.db_id)
                        );
                        if (updatedItem) {
                            this.selectedItem = { ...this.selectedItem, ...updatedItem };
                        }
                    } else {
                        alert(result.message || 'Failed to update title');
                    }
                } catch (error) {
                    alert('An error occurred. Please try again.');
                }
            },
            
            startEditRequired() {
                this.isEditingRequired = true;
                this.editingRequiredDescription = this.selectedItem.description || '';
            },
            
            cancelEditRequired() {
                this.isEditingRequired = false;
                this.editingRequiredDescription = '';
            },
            
            async saveEditRequired() {
                if (!this.selectedItem) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'update_item');
                    formData.append('item_id', this.selectedItem.id || this.selectedItem.db_id);
                    formData.append('title', this.selectedItem.title || ''); // Include title (required by backend)
                    formData.append('description', this.editingRequiredDescription);
                    
                    const response = await fetch('../ajax/task_ticket_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.selectedItem.description = this.editingRequiredDescription;
                        this.isEditingRequired = false;
                        await this.loadItems();
                        // Refresh selectedItem from items array
                        const updatedItem = this.items.find(i => 
                            (i.id === this.selectedItem.id || i.id === this.selectedItem.db_id) ||
                            (i.db_id === this.selectedItem.id || i.db_id === this.selectedItem.db_id)
                        );
                        if (updatedItem) {
                            this.selectedItem = { ...this.selectedItem, ...updatedItem };
                        }
                    } else {
                        alert(result.message || 'Failed to update description');
                    }
                } catch (error) {
                    alert('An error occurred. Please try again.');
                }
            },
            
            startEditRequiredAttachments() {
                this.isEditingRequiredAttachments = true;
                // Create a copy of attachments for editing - ensure it's an array
                const attachments = this.selectedItem.attachments || [];
                this.editingRequiredAttachmentsList = Array.isArray(attachments) 
                    ? JSON.parse(JSON.stringify(attachments))
                    : [];
                this.editingRequiredFiles = [];
                
                // Force Alpine.js reactivity
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            },
            
            cancelEditRequiredAttachments() {
                this.isEditingRequiredAttachments = false;
                this.editingRequiredAttachmentsList = [];
                this.editingRequiredFiles = [];
            },
            
            removeRequiredAttachment(index) {
                this.editingRequiredAttachmentsList.splice(index, 1);
            },
            
            handleRequiredAttachmentSelect(event) {
                const files = Array.from(event.target.files || []);
                this.addRequiredAttachments(files);
                event.target.value = '';
            },
            
            handleRequiredAttachmentDrop(event) {
                event.preventDefault();
                this.isDragging = false;
                const files = Array.from(event.dataTransfer.files || []);
                this.addRequiredAttachments(files);
            },
            
            addRequiredAttachments(files) {
                const maxSize = 50 * 1024 * 1024; // 50MB
                const existingNames = new Set(this.editingRequiredAttachmentsList.map(a => a.name));
                
                for (let file of files) {
                    if (file.size > maxSize) {
                        alert(`File "${file.name}" exceeds 50MB limit.`);
                        continue;
                    }
                    
                    // Add as new file object (will be uploaded)
                    this.editingRequiredFiles.push(file);
                    
                    // Also add to display list
                    this.editingRequiredAttachmentsList.push({
                        name: file.name,
                        size: file.size,
                        type: file.type,
                        file: file, // Keep reference for upload
                        isNew: true
                    });
                }
                
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            },
            
            async saveEditRequiredAttachments() {
                if (!this.selectedItem) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'update_item');
                    formData.append('item_id', this.selectedItem.id || this.selectedItem.db_id);
                    formData.append('title', this.selectedItem.title || ''); // Include title (required by backend)
                    formData.append('description', this.selectedItem.description || ''); // Include description (required by backend)
                    
                    // Convert existing attachments (without file property) to JSON
                    const existingAttachments = this.editingRequiredAttachmentsList
                        .filter(a => !a.isNew)
                        .map(a => ({
                            name: a.name,
                            size: a.size,
                            type: a.type,
                            path: a.path
                        }));
                    
                    // Add new files
                    for (let file of this.editingRequiredFiles) {
                        formData.append('attachments[]', file);
                    }
                    
                    // Add existing attachments as JSON
                    if (existingAttachments.length > 0) {
                        formData.append('attachments_json', JSON.stringify(existingAttachments));
                    }
                    
                    const response = await fetch('../ajax/task_ticket_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.selectedItem.attachments = this.editingRequiredAttachmentsList.map(a => ({
                            name: a.name,
                            size: a.size,
                            type: a.type,
                            path: a.path
                        }));
                        this.isEditingRequiredAttachments = false;
                        this.editingRequiredAttachmentsList = [];
                        this.editingRequiredFiles = [];
                        await this.loadItems();
                        // Refresh selectedItem
                        const updatedItem = this.items.find(i => 
                            (i.id === this.selectedItem.id || i.id === this.selectedItem.db_id) ||
                            (i.db_id === this.selectedItem.id || i.db_id === this.selectedItem.db_id)
                        );
                        if (updatedItem) {
                            this.selectedItem = { ...this.selectedItem, ...updatedItem };
                        }
                    } else {
                        alert(result.message || 'Failed to update attachments');
                    }
                } catch (error) {
                    alert('An error occurred. Please try again.');
                }
            },
            
            startEditProvided() {
                this.isEditingProvided = true;
                this.editingProvidedDescription = this.selectedItem.provided_description || '';
            },
            
            cancelEditProvided() {
                this.isEditingProvided = false;
                this.editingProvidedDescription = '';
            },
            
            async saveEditProvided() {
                if (!this.selectedItem) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'update_provided');
                    formData.append('item_id', this.selectedItem.id || this.selectedItem.db_id);
                    formData.append('description', this.editingProvidedDescription);
                    
                    const response = await fetch('../ajax/task_ticket_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.selectedItem.provided_description = this.editingProvidedDescription;
                        this.selectedItem.provided_edited_at = result.provided_edited_at;
                        this.isEditingProvided = false;
                        await this.loadItems();
                        // Refresh selectedItem from items array
                        const updatedItem = this.items.find(i => 
                            (i.id === this.selectedItem.id || i.id === this.selectedItem.db_id) ||
                            (i.db_id === this.selectedItem.id || i.db_id === this.selectedItem.db_id)
                        );
                        if (updatedItem) {
                            this.selectedItem = { ...this.selectedItem, ...updatedItem };
                        }
                    } else {
                        alert(result.message || 'Failed to update provided description');
                    }
                } catch (error) {
                    alert('An error occurred. Please try again.');
                }
            },
            
            startEditProvidedAttachments() {
                this.isEditingProvidedAttachments = true;
                // Create a copy of provided attachments for editing - ensure it's an array
                const attachments = this.selectedItem.provided_attachments || [];
                this.editingProvidedAttachmentsList = Array.isArray(attachments)
                    ? JSON.parse(JSON.stringify(attachments))
                    : [];
                this.editingProvidedFiles = [];
                
                // Force Alpine.js reactivity
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            },
            
            cancelEditProvidedAttachments() {
                this.isEditingProvidedAttachments = false;
                this.editingProvidedAttachmentsList = [];
                this.editingProvidedFiles = [];
            },
            
            removeProvidedAttachment(index) {
                this.editingProvidedAttachmentsList.splice(index, 1);
            },
            
            handleProvidedAttachmentSelect(event) {
                const files = Array.from(event.target.files || []);
                this.addProvidedAttachments(files);
                event.target.value = '';
            },
            
            handleProvidedAttachmentDrop(event) {
                event.preventDefault();
                this.isDragging = false;
                const files = Array.from(event.dataTransfer.files || []);
                this.addProvidedAttachments(files);
            },
            
            addProvidedAttachments(files) {
                const maxSize = 50 * 1024 * 1024; // 50MB
                const existingNames = new Set(this.editingProvidedAttachmentsList.map(a => a.name));
                
                for (let file of files) {
                    if (file.size > maxSize) {
                        alert(`File "${file.name}" exceeds 50MB limit.`);
                        continue;
                    }
                    
                    // Add as new file object (will be uploaded)
                    this.editingProvidedFiles.push(file);
                    
                    // Also add to display list
                    this.editingProvidedAttachmentsList.push({
                        name: file.name,
                        size: file.size,
                        type: file.type,
                        file: file, // Keep reference for upload
                        isNew: true
                    });
                }
                
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            },
            
            async saveEditProvidedAttachments() {
                if (!this.selectedItem) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'update_provided');
                    formData.append('item_id', this.selectedItem.id || this.selectedItem.db_id);
                    formData.append('description', this.selectedItem.provided_description || '');
                    
                    // Convert existing attachments (without file property) to JSON
                    const existingAttachments = this.editingProvidedAttachmentsList
                        .filter(a => !a.isNew)
                        .map(a => ({
                            name: a.name,
                            size: a.size,
                            type: a.type,
                            path: a.path
                        }));
                    
                    // Add new files
                    for (let file of this.editingProvidedFiles) {
                        formData.append('attachments[]', file);
                    }
                    
                    // Add existing attachments as JSON
                    if (existingAttachments.length > 0) {
                        formData.append('attachments_json', JSON.stringify(existingAttachments));
                    }
                    
                    const response = await fetch('../ajax/task_ticket_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.selectedItem.provided_attachments = this.editingProvidedAttachmentsList.map(a => ({
                            name: a.name,
                            size: a.size,
                            type: a.type,
                            path: a.path
                        }));
                        this.isEditingProvidedAttachments = false;
                        this.editingProvidedAttachmentsList = [];
                        this.editingProvidedFiles = [];
                        await this.loadItems();
                        // Refresh selectedItem
                        const updatedItem = this.items.find(i => 
                            (i.id === this.selectedItem.id || i.id === this.selectedItem.db_id) ||
                            (i.db_id === this.selectedItem.id || i.db_id === this.selectedItem.db_id)
                        );
                        if (updatedItem) {
                            this.selectedItem = { ...this.selectedItem, ...updatedItem };
                        }
                    } else {
                        alert(result.message || 'Failed to update provided attachments');
                    }
                } catch (error) {
                    alert('An error occurred. Please try again.');
                }
            },
            
            editItem(item, index) {
                // Set form data to item values
                this.formData = {
                    title: item.title,
                    description: item.description || ''
                };
                this.selectedFiles = item.attachments || [];
                this.editingIndex = index;
                this.editingItem = item;
                this.openModal();
            },
            
            isDropped(item) {
                return item.status === 'Dropped';
            },
            
            async dropItem(item, index) {
                if (confirm(`Are you sure you want to drop "${item.title}"? This item will remain visible but will be disabled.`)) {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_status');
                        formData.append('item_id', item.id || item.db_id);
                        formData.append('status', 'Dropped');
                        
                        const response = await fetch('../ajax/task_ticket_handler.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (!response.ok) {
                            const text = await response.text();
                            alert('Server error: ' + response.status);
                            return;
                        }
                        
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            const text = await response.text();
                            alert('Server returned invalid response');
                            return;
                        }
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Reload items from database to ensure sync
                            await this.loadItems();
                            alert('Item dropped successfully!');
                        } else {
                            alert('Error: ' + (result.message || 'Failed to drop item'));
                        }
                    } catch (error) {
                        alert('An error occurred. Please try again.');
                    }
                }
            },
            
            async approveRequired(item, index) {
                if (item.type !== 'Required') {
                    alert('This action is only available for Required items.');
                    return;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'update_status');
                    formData.append('item_id', item.id || item.db_id);
                    formData.append('status', 'Approved');
                    
                    const response = await fetch('../ajax/task_ticket_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        const text = await response.text();
                        alert('Server error: ' + response.status);
                        return;
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        alert('Server returned invalid response');
                        return;
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        item.status = 'Approved';
                        item.sortValue.status = 'Approved';
                        alert('Item approved successfully!');
                    } else {
                        alert('Error: ' + (result.message || 'Failed to approve item'));
                    }
                } catch (error) {
                    alert('An error occurred. Please try again.');
                }
            },
            
            commentOnRequired(item, index) {
                if (item.type !== 'Required') {
                    alert('This action is only available for Required items.');
                    return;
                }
                
                // Open edit modal for client to add comments/attachments
                this.editItem(item, index);
            },
            
            // Get status options for a specific item based on role
            getStatusOptionsForItem(item) {
                if (this.isDropped(item)) {
                    return [];
                }
                
                // ADMIN: Full access to all statuses for all item types
                if (this.isAdmin) {
                    if (item.type === 'Task') {
                        return ['Assigned', 'Working', 'Review', 'Revise', 'Approved', 'Completed'];
                    } else if (item.type === 'Ticket') {
                        return ['Raised', 'In Progress', 'Resolved'];
                    } else if (item.type === 'Required') {
                        return ['Requested', 'Provided'];
                    }
                    return [];
                }
                
                // TASK
                if (item.type === 'Task') {
                    if (this.isClient) {
                        // Client: Assigned, Approve, Revise
                        return ['Assigned', 'Approved', 'Revise'];
                    } else if (this.isManager) {
                        // Manager: Working, Review, Revise, Approve, Complete
                        return ['Working', 'Review', 'Revise', 'Approved', 'Completed'];
                    }
                }
                
                // TICKET
                if (item.type === 'Ticket') {
                    // Client: Read-only (no dropdown)
                    if (this.isClient) {
                        return [];
                    }
                    // Manager: Status options in Status column
                    if (this.isManager) {
                        return ['Raised', 'In Progress', 'Resolved'];
                    }
                    return [];
                }
                
                // REQUIRED
                if (item.type === 'Required') {
                    // Both Client and Manager: Requested, Provided
                    return ['Requested', 'Provided'];
                }
                
                return [];
            },
            
            // Check if user can change status for this item
            canChangeStatus(item) {
                if (this.isDropped(item)) {
                    return false;
                }
                
                // ADMIN: Can change status for all item types
                if (this.isAdmin) {
                    return true;
                }
                
                // TASK
                if (item.type === 'Task') {
                    // Client can change status on their own tasks
                    if (this.isClient && item.createdBy === 'Client') {
                        return true;
                    }
                    // Manager can always change task status
                    if (this.isManager) {
                        return true;
                    }
                }
                
                // TICKET
                if (item.type === 'Ticket') {
                    // Client: Read-only
                    if (this.isClient) {
                        return false;
                    }
                    // Manager: Can change status in Status column
                    if (this.isManager) {
                        return true;
                    }
                }
                
                // REQUIRED
                if (item.type === 'Required') {
                    // Both can change status
                    return true;
                }
                
                return false;
            },
            
            // Get status options for Ticket in Action column (Manager only)
            getTicketStatusOptions() {
                return ['Raised', 'In Progress', 'Resolved'];
            },
            
            toggleStatusDropdown(item, index) {
                if (this.isDropped(item) || !this.canChangeStatus(item)) {
                    return;
                }
                // Close action dropdown if open
                this.actionDropdownOpen = null;
                // Toggle status dropdown
                this.statusDropdownOpen = this.statusDropdownOpen === index ? null : index;
            },
            
            async updateStatus(item, index, newStatus) {
                // If newStatus is not provided, get it from event
                if (!newStatus && event && event.target) {
                    newStatus = event.target.value;
                }
                
                // Don't update if status hasn't changed
                if (newStatus === item.status) {
                    return;
                }
                
                // Check permissions
                if (this.isClient && !this.canClientUpdateStatus(item, newStatus)) {
                    alert('You do not have permission to update the status of this item.');
                    return;
                }
                
                // Cannot update dropped items
                if (this.isDropped(item)) {
                    alert('Cannot update status of dropped items.');
                    return;
                }
                
                // For all status changes, proceed normally
                await this.executeStatusUpdate(item, index, newStatus, null);
            },
            
            async executeStatusUpdate(item, index, newStatus, attachmentsJson) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'update_status');
                    formData.append('item_id', item.id || item.db_id);
                    formData.append('status', newStatus);
                    
                    // Add attachments if provided
                    if (attachmentsJson) {
                        formData.append('attachments_json', attachmentsJson);
                    }
                    
                    const response = await fetch('../ajax/task_ticket_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    // Check if response is OK
                    if (!response.ok) {
                        const text = await response.text();
                        alert('Server error: ' + response.status);
                        return;
                    }
                    
                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        alert('Server returned invalid response');
                        return;
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Update the item status locally for immediate feedback
                        item.status = newStatus;
                        item.sortValue.status = newStatus;
                        // Reload items to get updated attachments
                        await this.loadItems();
                    } else {
                        alert('Error: ' + (result.message || 'Failed to update status'));
                    }
                } catch (error) {
                    alert('An error occurred. Please try again.');
                }
            },
            
            openRequirementModal(item, index) {
                try {
                    if (!item) {
                        return;
                    }
                    if (this.isDropped(item)) {
                        return;
                    }
                    
                    // Close any other open modals first
                    this.isPreviewModalOpen = false;
                    this.isDetailModalOpen = false;
                    this.isModalOpen = false;
                    
                    // Set state
                    this.requirementItem = item;
                    this.requirementIndex = index;
                    this.requirementDescription = item.provided_description || '';
                    this.requirementFiles = [];
                    this.isRequirementModalOpen = true;
                    
                    // Force modal to show immediately
                    setTimeout(() => {
                        // Find modal by ID first
                        let modal = document.getElementById('requirementModal');
                        if (!modal) {
                            modal = document.querySelector('[x-show*="isRequirementModalOpen"]');
                        }
                        if (!modal) {
                            modal = document.querySelector('.requirement-modal');
                        }
                        
                        if (modal) {
                            // Close preview modal explicitly
                            const previewModal = document.querySelector('[x-show*="isPreviewModalOpen"]');
                            if (previewModal) {
                                previewModal.style.setProperty('display', 'none', 'important');
                            }
                            
                            // Force all display properties FIRST before removing x-show
                            // This ensures elements are accessible
                            modal.style.cssText = `
                                display: flex !important;
                                visibility: visible !important;
                                opacity: 1 !important;
                                z-index: 999999 !important;
                                position: fixed !important;
                                top: 0 !important;
                                left: 0 !important;
                                right: 0 !important;
                                bottom: 0 !important;
                                width: 100% !important;
                                height: 100% !important;
                                pointer-events: auto !important;
                            `;
                            
                            // Force modal content to be visible
                            const modalContent = modal.querySelector('.bg-slate-800.rounded-2xl');
                            if (modalContent) {
                                modalContent.style.cssText = `
                                    display: flex !important;
                                    visibility: visible !important;
                                    opacity: 1 !important;
                                    z-index: 1000000 !important;
                                    pointer-events: auto !important;
                                `;
                            }
                            
                            // NOW remove Alpine.js x-show attribute after forcing display
                            modal.removeAttribute('x-show');
                            modal.removeAttribute('x-cloak');
                            
                            // Move modal directly to body to avoid parent container issues
                            if (modal.parentElement !== document.body) {
                                document.body.appendChild(modal);
                            }
                            
                            // Ensure all child elements are visible
                            const allChildren = modal.querySelectorAll('*');
                            allChildren.forEach(child => {
                                if (child.style.display === 'none') {
                                    child.style.display = '';
                                }
                            });
                            
                            // Create a reference to this for use in event handlers
                            const self = this;
                            
                            // Add native event listeners for closing
                            const closeModal = () => {
                                self.closeRequirementModal();
                            };
                            
                            // Remove old listeners if any
                            if (modal._closeHandler) {
                                modal.removeEventListener('click', modal._closeHandler);
                            }
                            if (modal._escHandler) {
                                document.removeEventListener('keydown', modal._escHandler);
                            }
                            
                            // Add click-away handler (click on backdrop)
                            // BUT exclude clicks on file input, drop zone, and their children
                            modal._closeHandler = (e) => {
                                // Don't close if clicking on file input, drop zone, or their children
                                const isFileInput = e.target.id === 'requirementFileInput' || e.target.closest('#requirementFileInput');
                                const isDropZone = e.target.id === 'requirementFileDropZone' || e.target.closest('#requirementFileDropZone');
                                const isModalContent = e.target.closest('.bg-slate-800.rounded-2xl');
                                
                                if (isFileInput || isDropZone || isModalContent) {
                                    return; // Don't close modal
                                }
                                
                                if (e.target === modal || e.target.id === 'requirementModal') {
                                    e.stopPropagation();
                                    closeModal();
                                }
                            };
                            modal.addEventListener('click', modal._closeHandler, false);
                            
                            // Add ESC key handler
                            modal._escHandler = (e) => {
                                if (e.key === 'Escape' && self.isRequirementModalOpen) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    closeModal();
                                }
                            };
                            document.addEventListener('keydown', modal._escHandler, true);
                            
                            // Update close buttons to use native handlers
                            const closeBtn = modal.querySelector('#requirementCloseBtn');
                            const cancelBtn = modal.querySelector('#requirementCancelBtn');
                            
                            // Add click handlers to close buttons
                            if (closeBtn) {
                                closeBtn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    closeModal();
                                }, true);
                            }
                            
                            if (cancelBtn) {
                                cancelBtn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    closeModal();
                                }, true);
                            }
                            
                            // Also find by class as fallback
                            const closeBtnByClass = modal.querySelector('.requirement-close-btn');
                            const cancelBtnByClass = modal.querySelector('.requirement-cancel-btn');
                            
                            if (closeBtnByClass && closeBtnByClass !== closeBtn) {
                                closeBtnByClass.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    closeModal();
                                }, true);
                            }
                            
                            if (cancelBtnByClass && cancelBtnByClass !== cancelBtn) {
                                cancelBtnByClass.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    closeModal();
                                }, true);
                            }
                            
                            // Wire up file upload handlers - wait for DOM to be fully ready
                            // Use requestAnimationFrame to ensure DOM is painted
                            requestAnimationFrame(() => {
                                requestAnimationFrame(() => {
                                    const fileInput = modal.querySelector('#requirementFileInput') || modal.querySelector('input[type="file"]');
                                    const fileDropZone = modal.querySelector('#requirementFileDropZone') || modal.querySelector('.drop-zone');
                                    
                                    
                                    if (!fileInput) {
                                    }
                                    if (!fileDropZone) {
                                    }
                                
                                // Find submit button by text content
                                const allButtons = modal.querySelectorAll('button');
                                let submitBtn = null;
                                allButtons.forEach(btn => {
                                    if (btn.textContent.trim() === 'Submit' && btn.classList.contains('gradient-button')) {
                                        submitBtn = btn;
                                    }
                                });
                                
                                    // File input change handler
                                    if (fileInput) {
                                        // Ensure file input is accessible but hidden
                                        fileInput.style.cssText = `
                                            position: absolute !important;
                                            width: 0 !important;
                                            height: 0 !important;
                                            opacity: 0 !important;
                                            pointer-events: auto !important;
                                            overflow: hidden !important;
                                        `;
                                        
                                        if (fileInput._changeHandler) {
                                            fileInput.removeEventListener('change', fileInput._changeHandler);
                                        }
                                        fileInput._changeHandler = (e) => {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            
                                            if (e.target && e.target.files && e.target.files.length > 0) {
                                                self.handleRequirementFileSelect(e);
                                            } else if (fileInput.files && fileInput.files.length > 0) {
                                                // Fallback: use fileInput directly
                                                const syntheticEvent = {
                                                    target: fileInput,
                                                    preventDefault: () => {},
                                                    stopPropagation: () => {}
                                                };
                                                self.handleRequirementFileSelect(syntheticEvent);
                                            } else {
                                            }
                                        };
                                        fileInput.addEventListener('change', fileInput._changeHandler, false);
                                        
                                        // Also add a direct click handler as backup
                                        fileInput.addEventListener('click', (e) => {
                                            e.stopPropagation();
                                        }, true);
                                        
                                    } else {
                                    }
                                
                                // Drag and drop handlers
                                if (fileDropZone) {
                                // Remove old handlers if they exist
                                if (fileDropZone._dragOverHandler) {
                                    fileDropZone.removeEventListener('dragover', fileDropZone._dragOverHandler);
                                }
                                if (fileDropZone._dragLeaveHandler) {
                                    fileDropZone.removeEventListener('dragleave', fileDropZone._dragLeaveHandler);
                                }
                                if (fileDropZone._dropHandler) {
                                    fileDropZone.removeEventListener('drop', fileDropZone._dropHandler);
                                }
                                if (fileDropZone._clickHandler) {
                                    fileDropZone.removeEventListener('click', fileDropZone._clickHandler);
                                }
                                
                                // Drag over
                                fileDropZone._dragOverHandler = (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    self.isRequirementDragging = true;
                                    fileDropZone.classList.add('drag-over');
                                    fileDropZone.style.backgroundColor = 'rgba(59, 130, 246, 0.1)'; // Blue tint
                                };
                                fileDropZone.addEventListener('dragover', fileDropZone._dragOverHandler, false);
                                
                                // Drag leave
                                fileDropZone._dragLeaveHandler = (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    self.isRequirementDragging = false;
                                    fileDropZone.classList.remove('drag-over');
                                    fileDropZone.style.backgroundColor = '';
                                };
                                fileDropZone.addEventListener('dragleave', fileDropZone._dragLeaveHandler, false);
                                
                                // Drop
                                fileDropZone._dropHandler = (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    e.stopImmediatePropagation(); // Prevent other handlers
                                    self.isRequirementDragging = false;
                                    fileDropZone.classList.remove('drag-over');
                                    fileDropZone.style.backgroundColor = '';
                                    self.handleRequirementFileDrop(e);
                                };
                                fileDropZone.addEventListener('drop', fileDropZone._dropHandler, false);
                                
                                // Click to browse - MUST stop propagation to prevent modal close
                                fileDropZone._clickHandler = (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    e.stopImmediatePropagation();
                                    if (fileInput) {
                                        // Ensure file input is accessible
                                        fileInput.style.pointerEvents = 'auto';
                                        fileInput.style.position = 'absolute';
                                        fileInput.style.width = '1px';
                                        fileInput.style.height = '1px';
                                        fileInput.style.opacity = '0';
                                        
                                        // Directly trigger the file input
                                        try {
                                            // Use setTimeout to ensure the style changes take effect
                                            setTimeout(() => {
                                                fileInput.click();
                                            }, 10);
                                        } catch (err) {
                                            // Fallback: create a synthetic click
                                            const clickEvent = new MouseEvent('click', {
                                                bubbles: false,
                                                cancelable: true,
                                                view: window
                                            });
                                            fileInput.dispatchEvent(clickEvent);
                                        }
                                    } else {
                                    }
                                };
                                fileDropZone.addEventListener('click', fileDropZone._clickHandler, false);
                            } else {
                            }
                                
                                // Submit button handler
                                if (submitBtn) {
                                    // Remove old handler if exists
                                    if (submitBtn._submitHandler) {
                                        submitBtn.removeEventListener('click', submitBtn._submitHandler);
                                        delete submitBtn._submitHandler;
                                    }
                                    
                                    // Create new handler with duplicate prevention
                                    submitBtn._submitHandler = (e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        e.stopImmediatePropagation();
                                        
                                        // Prevent multiple clicks
                                        if (self._isSubmitting) {
                                            return;
                                        }
                                        
                                        self.submitRequirement();
                                    };
                                    
                                    // Use capture phase and only attach once
                                    submitBtn.addEventListener('click', submitBtn._submitHandler, true);
                                } else {
                                }
                                });
                            });
                            
                            // Wire up remove file buttons (will be added dynamically when files are added)
                            const setupRemoveFileHandlers = () => {
                                const filePreviews = modal.querySelectorAll('.bg-slate-700\\/50.rounded-lg');
                                filePreviews.forEach((preview, index) => {
                                    const removeBtn = preview.querySelector('button');
                                    if (removeBtn && removeBtn.querySelector('[data-lucide="x"]') && !removeBtn.classList.contains('requirement-close-btn')) {
                                        if (removeBtn._removeHandler) {
                                            removeBtn.removeEventListener('click', removeBtn._removeHandler);
                                        }
                                        removeBtn._removeHandler = (e) => {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            self.removeRequirementFile(index);
                                            // Re-setup handlers after removal
                                            setTimeout(setupRemoveFileHandlers, 50);
                                        };
                                        removeBtn.addEventListener('click', removeBtn._removeHandler, true);
                                    }
                                });
                            };
                            
                            // Setup remove handlers initially and watch for changes
                            setupRemoveFileHandlers();
                            if (modal._fileObserver) {
                                modal._fileObserver.disconnect();
                            }
                            const observer = new MutationObserver(() => {
                                setupRemoveFileHandlers();
                            });
                            observer.observe(modal, { childList: true, subtree: true });
                            modal._fileObserver = observer;
                            
                            // Wire up description textarea to update Alpine data
                            const descriptionTextarea = modal.querySelector('textarea[placeholder*="description" i]');
                            if (descriptionTextarea) {
                                if (descriptionTextarea._inputHandler) {
                                    descriptionTextarea.removeEventListener('input', descriptionTextarea._inputHandler);
                                }
                                descriptionTextarea._inputHandler = (e) => {
                                    self.requirementDescription = e.target.value;
                                };
                                descriptionTextarea.addEventListener('input', descriptionTextarea._inputHandler);
                                
                                // Set initial value
                                if (self.requirementDescription) {
                                    descriptionTextarea.value = self.requirementDescription;
                                }
                            }
                        }
                    }, 100);
                    
                    // Initialize icons
                    setTimeout(() => {
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }, 200);
                } catch (error) {
                }
            },
            
            async submitRequirement() {
                if (!this.requirementItem) {
                    return;
                }
                
                // Prevent multiple submissions
                if (this._isSubmitting) {
                    return;
                }
                this._isSubmitting = true;
                
                // Determine if this is an edit (item already has provided data) or new submission
                const isEdit = this.requirementItem.provided_description || 
                              (this.requirementItem.provided_attachments && this.requirementItem.provided_attachments.length > 0);
                
                try {
                    
                    const formData = new FormData();
                    // Use update_provided if editing, otherwise use provide_requirement
                    const action = isEdit ? 'update_provided' : 'provide_requirement';
                    formData.append('action', action);
                    formData.append('item_id', this.requirementItem.id || this.requirementItem.db_id);
                    formData.append('description', this.requirementDescription || '');
                    if (!isEdit) {
                        formData.append('status', 'Provided');
                    }
                    
                    // Append files for FormData upload (preferred method - more efficient)
                    // Only use base64 as fallback if FormData doesn't work
                    if (this.requirementFiles.length > 0) {
                        // Use FormData for file upload (more efficient)
                        for (let file of this.requirementFiles) {
                            formData.append('attachments[]', file);
                        }
                    }
                    
                    // Note: We're NOT sending base64 JSON to avoid duplicate processing
                    // The backend will handle FormData files directly
                    
                    const response = await fetch('../ajax/task_ticket_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Reload items to get updated data with provided_description and provided_attachments
                        await this.loadItems();
                        
                        // Update the current item in the items array if it exists
                        const itemIndex = this.items.findIndex(i => 
                            (i.id === this.requirementItem.id || i.id === this.requirementItem.db_id) ||
                            (i.db_id === this.requirementItem.id || i.db_id === this.requirementItem.db_id)
                        );
                        
                        if (itemIndex !== -1) {
                            // Refresh the item data
                            const updatedItem = this.items[itemIndex];
                            if (updatedItem) {
                                // Ensure provided fields are set
                                updatedItem.provided_description = this.requirementDescription;
                                updatedItem.status = 'Provided';
                                // provided_attachments will be in the reloaded data
                            }
                        }
                        
                        this.closeRequirementModal();
                    } else {
                        alert(result.message || 'Failed to submit requirement');
                    }
                } catch (error) {
                    alert('An error occurred. Please try again.');
                } finally {
                    // Reset submission flag
                    this._isSubmitting = false;
                }
            },
            
            fileToBase64(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => {
                        const base64 = reader.result.split(',')[1];
                        resolve(base64);
                    };
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });
            },
            
            handleRequirementFileSelect(event) {
                
                // Get files from event target or directly from the file input
                let files = [];
                const fileInput = document.getElementById('requirementFileInput') || 
                                 (event && event.target) || 
                                 document.querySelector('#requirementFileInput');
                
                // Try multiple ways to get files
                if (event && event.target && event.target.files && event.target.files.length > 0) {
                    files = Array.from(event.target.files);
                } else if (fileInput && fileInput.files && fileInput.files.length > 0) {
                    files = Array.from(fileInput.files);
                } else if (event && event.files) {
                    files = Array.from(event.files);
                } else {
                    alert('No files selected. Please try again.');
                    return;
                }
                
                if (files.length > 0) {
                    this.addRequirementFiles(files);
                } else {
                }
                
                // Clear the input so same file can be selected again
                if (fileInput) {
                    fileInput.value = '';
                } else if (event && event.target) {
                    event.target.value = '';
                }
            },
            
            handleRequirementFileDrop(event) {
                event.preventDefault();
                event.stopPropagation();
                this.isRequirementDragging = false;
                
                const files = Array.from(event.dataTransfer ? event.dataTransfer.files : []);
                
                if (files.length > 0) {
                    this.addRequirementFiles(files);
                } else {
                }
            },
            
            addRequirementFiles(files) {
                const maxSize = 50 * 1024 * 1024; // 50MB
                const filesToAdd = [];
                
                // Check for duplicates by comparing name and size
                const existingFiles = new Set(
                    this.requirementFiles.map(f => `${f.name}_${f.size}_${f.lastModified || f.type}`)
                );
                
                for (let file of files) {
                    if (file.size > maxSize) {
                        alert(`File "${file.name}" exceeds 50MB limit. Please select a smaller file.`);
                        continue;
                    }
                    
                    // Check if file already exists
                    const fileKey = `${file.name}_${file.size}_${file.lastModified || file.type}`;
                    if (existingFiles.has(fileKey)) {
                        continue;
                    }
                    
                    filesToAdd.push(file);
                    existingFiles.add(fileKey);
                }
                
                // Add all files at once
                if (filesToAdd.length > 0) {
                    // Use spread operator to add files - this ensures Alpine.js reactivity
                    this.requirementFiles = [...this.requirementFiles, ...filesToAdd];
                    
                    // Force Alpine.js to update and show file previews
                    this.$nextTick(() => {
                        setTimeout(() => {
                            const modal = document.getElementById('requirementModal');
                            if (modal) {
                                const filePreviewsContainer = modal.querySelector('#requirementFilePreviews');
                                if (filePreviewsContainer) {
                                    // Always show the container when files exist
                                    if (this.requirementFiles.length > 0) {
                                        filePreviewsContainer.style.setProperty('display', 'block', 'important');
                                        filePreviewsContainer.style.setProperty('visibility', 'visible', 'important');
                                        filePreviewsContainer.style.setProperty('opacity', '1', 'important');
                                        
                                        // Check if Alpine rendered the files
                                        const existingPreviews = filePreviewsContainer.querySelectorAll('.bg-slate-700\\/50.rounded-lg');
                                        
                                        // Get unique file count
                                        const uniqueFileCount = new Set(
                                            this.requirementFiles.map(f => `${f.name}_${f.size}_${f.lastModified || f.type}`)
                                        ).size;
                                        
                                        
                                        // Always manually render to ensure files show up
                                        if (existingPreviews.length !== uniqueFileCount || existingPreviews.length === 0) {
                                            this.renderFilePreviews(filePreviewsContainer);
                                        } else {
                                            // Alpine rendered correctly, just re-initialize icons
                                            if (typeof lucide !== 'undefined') {
                                                lucide.createIcons();
                                            }
                                        }
                                    }
                                } else {
                                }
                            } else {
                            }
                        }, 150);
                    });
                } else {
                }
            },
            
            renderFilePreviews(container) {
                
                // Clear existing content first
                container.innerHTML = '';
                
                // Get unique files only (in case duplicates somehow got through)
                const uniqueFiles = [];
                const seen = new Set();
                
                this.requirementFiles.forEach((file, originalIndex) => {
                    const fileKey = `${file.name}_${file.size}_${file.lastModified || file.type}`;
                    if (!seen.has(fileKey)) {
                        seen.add(fileKey);
                        uniqueFiles.push({ file, originalIndex });
                    }
                });
                
                // Update requirementFiles array to remove duplicates if needed
                if (uniqueFiles.length !== this.requirementFiles.length) {
                    this.requirementFiles = uniqueFiles.map(({ file }) => file);
                }
                
                // Manually render each unique file
                uniqueFiles.forEach(({ file, originalIndex }, displayIndex) => {
                    const fileDiv = document.createElement('div');
                    fileDiv.className = 'flex items-center justify-between bg-slate-700/50 rounded-lg p-2';
                    
                    const icon = this.getFileIcon(file.type || 'file');
                    const size = this.formatFileSize(file.size);
                    
                    // Escape HTML in file name
                    const fileName = file.name.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    
                    fileDiv.innerHTML = `
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <i data-lucide="${icon}" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-white truncate" title="${fileName}">${fileName}</p>
                                <p class="text-xs text-slate-400">${size}</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="text-slate-400 hover:text-red-400 transition-colors p-1 flex-shrink-0 requirement-remove-file"
                            data-index="${displayIndex}"
                            title="Remove file"
                        >
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </button>
                    `;
                    
                    // Add remove handler
                    const removeBtn = fileDiv.querySelector('button');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            // Find the actual index in requirementFiles array
                            const actualIndex = this.requirementFiles.findIndex(f => 
                                f.name === file.name && 
                                f.size === file.size && 
                                (f.lastModified || f.type) === (file.lastModified || file.type)
                            );
                            if (actualIndex !== -1) {
                                this.removeRequirementFile(actualIndex);
                                // Re-render after removal
                                setTimeout(() => {
                                    this.renderFilePreviews(container);
                                }, 50);
                            }
                        });
                    }
                    
                    container.appendChild(fileDiv);
                });
                
                // Re-initialize icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                
            },
            
            removeRequirementFile(index) {
                this.requirementFiles.splice(index, 1);
            },
            
            closeRequirementModal() {
                this.isRequirementModalOpen = false;
                this.requirementItem = null;
                this.requirementIndex = null;
                this.requirementDescription = '';
                this.requirementFiles = [];
                this._isSubmitting = false; // Reset submission flag
                
                // Force hide the modal element
                const modal = document.getElementById('requirementModal');
                if (modal) {
                    modal.style.setProperty('display', 'none', 'important');
                    modal.style.setProperty('visibility', 'hidden', 'important');
                    modal.style.setProperty('opacity', '0', 'important');
                    
                    // Remove event listeners
                    if (modal._closeHandler) {
                        modal.removeEventListener('click', modal._closeHandler);
                        delete modal._closeHandler;
                    }
                    if (modal._escHandler) {
                        document.removeEventListener('keydown', modal._escHandler);
                        delete modal._escHandler;
                    }
                    
                    // Clean up file observer
                    if (modal._fileObserver) {
                        modal._fileObserver.disconnect();
                        delete modal._fileObserver;
                    }
                    
                    // Clean up file input handlers
                    const fileInput = modal.querySelector('input[type="file"]');
                    if (fileInput && fileInput._changeHandler) {
                        fileInput.removeEventListener('change', fileInput._changeHandler);
                        delete fileInput._changeHandler;
                    }
                    
                    // Clean up drop zone handlers
                    const fileDropZone = modal.querySelector('.drop-zone');
                    if (fileDropZone) {
                        if (fileDropZone._dragOverHandler) {
                            fileDropZone.removeEventListener('dragover', fileDropZone._dragOverHandler);
                            delete fileDropZone._dragOverHandler;
                        }
                        if (fileDropZone._dragLeaveHandler) {
                            fileDropZone.removeEventListener('dragleave', fileDropZone._dragLeaveHandler);
                            delete fileDropZone._dragLeaveHandler;
                        }
                        if (fileDropZone._dropHandler) {
                            fileDropZone.removeEventListener('drop', fileDropZone._dropHandler);
                            delete fileDropZone._dropHandler;
                        }
                        if (fileDropZone._clickHandler) {
                            fileDropZone.removeEventListener('click', fileDropZone._clickHandler);
                            delete fileDropZone._clickHandler;
                        }
                    }
                    
                    // Clean up submit button handler
                    const submitBtn = Array.from(modal.querySelectorAll('button')).find(btn => btn.textContent.trim() === 'Submit');
                    if (submitBtn && submitBtn._submitHandler) {
                        submitBtn.removeEventListener('click', submitBtn._submitHandler);
                        delete submitBtn._submitHandler;
                    }
                    
                    // Clean up description textarea handler
                    const descriptionTextarea = modal.querySelector('textarea[placeholder*="description" i]');
                    if (descriptionTextarea && descriptionTextarea._inputHandler) {
                        descriptionTextarea.removeEventListener('input', descriptionTextarea._inputHandler);
                        delete descriptionTextarea._inputHandler;
                    }
                }
            },
            
            canEditItem(item) {
                // Cannot edit dropped items
                if (this.isDropped(item)) {
                    return false;
                }
                
                // ADMIN: Can edit all item types
                if (this.isAdmin) {
                    return true;
                }
                
                // Clients can edit Tasks and Tickets only (not Required)
                if (this.isClient) {
                    // Client can edit their own Tasks and Tickets (created by Client)
                    if (item.createdBy === 'Client') {
                        return item.type === 'Task' || item.type === 'Ticket';
                    }
                }
                
                // Managers can edit Required items only
                if (this.isManager) {
                    return item.type === 'Required';
                }
                
                return false;
            },
            
            canClientUpdateStatus(item, newStatus) {
                // Admin has full access
                if (this.isAdmin) {
                    return true;
                }
                // Clients can only update status for specific actions on their own items
                if (!this.isClient) {
                    return false;
                }
                
                // Client cannot update status if item is dropped
                if (this.isDropped(item)) {
                    return false;
                }
                
                // Allowed status updates for clients:
                // - Task: Assigned, Approved, Revise (only on their own tasks)
                if (item.type === 'Task') {
                    if (item.createdBy !== 'Client') {
                        return false;
                    }
                    return newStatus === 'Assigned' || newStatus === 'Approved' || newStatus === 'Revise';
                }
                
                // Clients cannot change Ticket status (read-only)
                if (item.type === 'Ticket') {
                    return false;
                }
                
                // Required: Requested, Provided
                if (item.type === 'Required') {
                    return newStatus === 'Requested' || newStatus === 'Provided';
                }
                
                return false;
            },
            
            canDeleteItem(item) {
                // ADMIN: Can delete all items
                if (this.isAdmin) {
                    return true;
                }
                // Clients can only delete Required items they created
                if (this.isClient) {
                    return item.type === 'Required' && item.createdBy === 'Client';
                }
                // Managers can delete all items
                return this.isManager;
            },
            
            // Get action needed indicator
            getActionNeeded(item) {
                if (!item) return '';
                
                const isManager = this.isManager;
                const isClient = this.isClient;
                
                // Manager action needed: Task or Ticket that needs manager attention
                if (isClient && (item.type === 'Task' || item.type === 'Ticket')) {
                    // Client sees Task/Ticket as needing Manager action
                    if (item.status !== 'Completed' && item.status !== 'Resolved') {
                        return 'Manager Action';
                    }
                }
                
                // Client action needed: Required items
                if (isClient && item.type === 'Required') {
                    // Client needs to respond to Required items
                    if (item.status !== 'Completed') {
                        return 'Your Action';
                    }
                }
                
                // Manager sees Required items as needing client action
                if (isManager && item.type === 'Required') {
                    if (item.status !== 'Completed') {
                        return 'Your Action'; // Manager's action is to wait for client
                    }
                }
                
                // Manager sees Task/Ticket as their responsibility
                if (isManager && (item.type === 'Task' || item.type === 'Ticket')) {
                    if (item.status !== 'Completed' && item.status !== 'Resolved') {
                        return 'Your Action';
                    }
                }
                
                return '';
            },
            
            // Check if Client can act on this item (only Required items)
            canClientActOnItem(item) {
                if (!item) return false;
                // Client can ONLY act on Required items
                return item.type === 'Required';
            },
            
            // Client submits response to Required item
            addResponseToRequired(item, index) {
                if (item.type !== 'Required') {
                    alert('This action is only available for Required items.');
                    return;
                }
                
                // Open edit modal for client to add response/attachments
                this.editItem(item, index);
            },
            
            // Re-initialize icons after sorting
            reinitIcons() {
                this.$nextTick(() => {
                    setTimeout(() => {
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }, 50);
                });
            },
            
            // Initialize detail column icons
            initDetailIcons() {
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            },
            
            // Map database status to timeline status
            mapStatusForTimeline(dbStatus, type) {
                // Handle status name differences between database and timeline
                if (type === 'Task' && dbStatus === 'Approved') {
                    return 'Approve';
                }
                return dbStatus;
            },
            
            // Get all statuses in order for timeline
            getAllStatusesForType(type, currentStatus) {
                // If item is dropped, show only Dropped status
                if (currentStatus === 'Dropped') {
                    return ['Dropped'];
                }
                
                if (type === 'Task') {
                    return ['Assigned', 'Working', 'Review', 'Revise', 'Approve', 'Completed'];
                } else if (type === 'Required') {
                    return ['Requested', 'Provided'];
                } else if (type === 'Ticket') {
                    return ['Raised', 'In Progress', 'Resolved'];
                }
                return [];
            },
            
            // Get timeline state for a status
            getTimelineState(status, currentStatus, allStatuses, type) {
                // Handle Dropped status
                if (currentStatus === 'Dropped' || status === 'Dropped') {
                    return status === currentStatus ? 'current' : 'upcoming';
                }
                
                // Map current status to timeline format
                const mappedCurrentStatus = this.mapStatusForTimeline(currentStatus, type);
                const currentIndex = allStatuses.indexOf(mappedCurrentStatus);
                const statusIndex = allStatuses.indexOf(status);
                
                if (statusIndex === -1) return 'upcoming';
                if (statusIndex < currentIndex) return 'completed';
                if (statusIndex === currentIndex) return 'current';
                return 'upcoming';
            },
            
            // Get status date (using created_at for initial status, status_updated_at for current)
            getStatusDate(status, item, allStatuses, type) {
                // Map current status to timeline format
                const mappedCurrentStatus = this.mapStatusForTimeline(item.status, type);
                const currentIndex = allStatuses.indexOf(mappedCurrentStatus);
                const statusIndex = allStatuses.indexOf(status);
                
                // If this is the current status, use status_updated_at if available, otherwise lastUpdated
                if (statusIndex === currentIndex) {
                    return item.status_updated_at || item.lastUpdated;
                }
                
                // For completed statuses, we don't have exact timestamps
                // So we'll use created_at for the first status, and estimate for others
                if (statusIndex === 0) {
                    return item.created_at || item.lastUpdated;
                }
                
                // For other completed statuses, we can't know exact date without history
                // Return null to indicate unknown date
                return null;
            },
            
            // Get all unique statuses for filter dropdown
            getAllStatuses() {
                // Return all possible statuses for all item types
                const allPossibleStatuses = [
                    // Task statuses
                    'Assigned', 'Working', 'Review', 'Revise', 'Approved', 'Completed',
                    // Ticket statuses
                    'Raised', 'In Progress', 'Resolved',
                    // Required statuses
                    'Requested', 'Provided',
                    // Common status
                    'Dropped'
                ];
                return allPossibleStatuses.sort();
            },
            
            // Apply filters (called when filter values change)
            applyFilters() {
                // Reset to first page when filters change
                this.resetPagination();
                // Filters are applied automatically via sortedItems computed property
                // This function is here to reset pagination and update icons
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            },
            
            // Clear all filters
            clearFilters() {
                this.filters = {
                    searchId: '',
                    itemType: '',
                    status: '',
                    lastUpdated: '',
                    assigner: '',
                    assignedTo: ''
                };
                this.resetPagination();
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            },
            
            // Check if any filters are active
            hasActiveFilters() {
                return !!(
                    this.filters.searchId ||
                    this.filters.itemType ||
                    this.filters.status ||
                    this.filters.lastUpdated ||
                    this.filters.assigner ||
                    this.filters.assignedTo
                );
            },
            
            // Get count of active filters
            getActiveFiltersCount() {
                let count = 0;
                if (this.filters.searchId) count++;
                if (this.filters.itemType) count++;
                if (this.filters.status) count++;
                if (this.filters.lastUpdated) count++;
                if (this.filters.assigner) count++;
                if (this.filters.assignedTo) count++;
                return count;
            },
            
            // Get page numbers for pagination (show max 5 pages)
            getPageNumbers() {
                const total = this.totalPages;
                const current = this.currentPage;
                const pages = [];
                
                if (total <= 5) {
                    // Show all pages if 5 or fewer
                    for (let i = 1; i <= total; i++) {
                        pages.push(i);
                    }
                } else {
                    // Show smart pagination
                    if (current <= 3) {
                        // Show first 3, ellipsis, last
                        for (let i = 1; i <= 3; i++) {
                            pages.push(i);
                        }
                        pages.push('...');
                        pages.push(total);
                    } else if (current >= total - 2) {
                        // Show first, ellipsis, last 3
                        pages.push(1);
                        pages.push('...');
                        for (let i = total - 2; i <= total; i++) {
                            pages.push(i);
                        }
                    } else {
                        // Show first, ellipsis, current-1, current, current+1, ellipsis, last
                        pages.push(1);
                        pages.push('...');
                        pages.push(current - 1);
                        pages.push(current);
                        pages.push(current + 1);
                        pages.push('...');
                        pages.push(total);
                    }
                }
                
                return pages;
            },
            
            // Go to specific page
            goToPage(page) {
                if (page >= 1 && page <= this.totalPages && page !== '...') {
                    this.currentPage = page;
                }
            },
            
            // Reset to first page when filters change
            resetPagination() {
                this.currentPage = 1;
            },
            
            // Handle items per page change
            handleItemsPerPageChange() {
                // Reset to first page when items per page changes
                this.resetPagination();
                // Recalculate total pages
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            }
        }
    }
    
    // Optimized icon initialization - only when needed
    let iconsInitialized = false;
    function initIcons() {
        if (typeof lucide !== 'undefined' && !iconsInitialized) {
            try {
                lucide.createIcons();
                iconsInitialized = true;
            } catch(e) {
            }
        }
    }
    
    // Lazy load icons - only initialize when modal opens or after page is interactive
    function lazyInitIcons() {
        if (document.readyState === 'complete') {
            setTimeout(initIcons, 50);
        } else {
            window.addEventListener('load', () => setTimeout(initIcons, 50));
        }
    }
    
    // Start lazy initialization
    lazyInitIcons();
    
    // Hide loading skeleton and show content when Alpine is ready
    function showContent() {
        const loadingEl = document.getElementById('taskTicketLoading');
        const appEl = document.getElementById('taskTicketApp');
        
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
        
        if (appEl) {
            appEl.style.display = 'block';
        }
        
        document.body.classList.add('alpine-ready');
    }
    
    // Function to load items from database
    function loadItemsFromDB() {
        setTimeout(() => {
            const app = document.getElementById('taskTicketApp');
            if (app && app._x_dataStack && app._x_dataStack[0]) {
                const appData = app._x_dataStack[0];
                if (typeof appData.fetchFilterOptions === 'function') {
                    appData.fetchFilterOptions();
                }
                if (typeof appData.loadItems === 'function') {
                    appData.loadItems();
                }
            }
        }, 300);
    }
    
    // Re-initialize icons when Alpine is ready
    document.addEventListener('alpine:init', () => {
        setTimeout(() => {
            initIcons();
            showContent();
            loadItemsFromDB();
        }, 50);
    });
    
    // Also mark as ready if Alpine loads before this script
    if (typeof Alpine !== 'undefined') {
        setTimeout(() => {
            showContent();
            loadItemsFromDB();
        }, 50);
    }
    
    // Fallback: mark as ready after a short delay and ensure content is visible
    setTimeout(() => {
        showContent();
        loadItemsFromDB();
        // Ensure icons are initialized
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }, 200);
    
    // Immediate fallback for very fast loads
    if (document.readyState === 'complete') {
        setTimeout(() => {
            showContent();
            loadItemsFromDB();
        }, 100);
    } else {
        window.addEventListener('load', () => {
            setTimeout(() => {
                showContent();
                loadItemsFromDB();
            }, 100);
        });
    }
</script>

<?php require_once "../includes/footer.php";
?>
