<?php
$page_title = "Client Dashboard";
require_once "../includes/header.php";

// Check if the user is logged in and is a client
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Redirect if not a client
if(!isClient()) {
    // Redirect to appropriate dashboard based on user type
    if (isAdmin()) {
        header("location: admin_dashboard.php");
    } elseif (isManager()) {
        header("location: manager_dashboard.php");
    } elseif (isDoer()) {
        header("location: doer_dashboard.php");
    } else {
        header("location: ../login.php");
    }
    exit;
}

// Get user data
$username = htmlspecialchars($_SESSION["username"]);
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
?>

<style>
    /* Hide scrollbar on client dashboard while keeping scroll functionality */
    .client-dashboard-container {
        padding: 2rem;
        max-width: 1400px;
        margin: 0 auto;
        /* Enable GPU acceleration for smooth scrolling */
        transform: translateZ(0);
        will-change: scroll-position;
    }
    
    /* Hide scrollbar for webkit browsers (Chrome, Safari, Edge) */
    .client-dashboard-container::-webkit-scrollbar,
    body.two-frame-layout .app-frame .main-content::-webkit-scrollbar,
    html body.two-frame-layout .app-frame .main-content::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }
    
    /* Hide scrollbar for Firefox */
    .client-dashboard-container,
    body.two-frame-layout .app-frame .main-content,
    html body.two-frame-layout .app-frame .main-content {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }
    
    /* Ensure scroll functionality is maintained with hardware acceleration */
    .client-dashboard-container,
    body.two-frame-layout .app-frame .main-content,
    html body.two-frame-layout .app-frame .main-content {
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        /* Force GPU acceleration */
        transform: translateZ(0);
        -webkit-transform: translateZ(0);
        will-change: scroll-position;
    }

    .dashboard-header {
        margin-bottom: 2rem;
    }

    .dashboard-header h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark-text-primary);
        margin-bottom: 0.5rem;
    }

    .dashboard-header p {
        color: var(--dark-text-secondary);
        font-size: 1rem;
    }

    /* Stats Section */
    .stats-section {
        margin-top: 2rem;
        position: relative;
        overflow: visible;
    }

    .stats-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
        position: relative;
        overflow: visible;
    }

    .stats-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--dark-text-primary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }

    .stats-title i {
        color: #8b5cf6;
    }

    /* Date Range Display */
    .date-range-display {
        font-size: 0.875rem;
        color: var(--dark-text-secondary);
        margin-top: 0.375rem;
        font-weight: 400;
    }

    /* Date Range Selector */
    .date-range-selector {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        position: relative;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        padding: 0.25rem;
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        box-shadow: var(--glass-shadow);
        overflow: visible !important;
        /* GPU acceleration */
        transform: translateZ(0);
        will-change: transform;
    }

    .date-range-btn {
        background: transparent;
        border: none;
        color: var(--dark-text-secondary);
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition-normal);
        white-space: nowrap;
        flex-shrink: 0;
    }

    .date-range-btn:hover {
        background: var(--dark-bg-glass-hover);
        color: var(--dark-text-primary);
    }

    .date-range-btn.active {
        background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }

    .date-range-btn.active:hover {
        background: linear-gradient(135deg, #7c3aed 0%, #9333ea 100%);
        box-shadow: 0 6px 16px rgba(139, 92, 246, 0.5);
    }

    .date-range-btn.custom-date-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .date-range-btn.custom-date-btn i {
        font-size: 0.75rem;
    }

    /* Custom Date Modal */
    .custom-date-modal {
        position: absolute;
        margin-top: 0.5rem;
        bottom: calc(100% + 0.875rem);
        right: 0;
        z-index: 99999;
        display: none;
        overflow: visible;
    }

    .custom-date-modal.show {
        display: block;
    }

    .custom-date-modal-content {
        background: var(--dark-bg-card);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        padding: 0.5rem 0.625rem;
        padding-top: 0.625rem;
        min-width: 340px;
        box-shadow: var(--glass-shadow);
        backdrop-filter: var(--glass-blur);
    }

    .custom-date-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.375rem;
        margin-top: 0;
    }

    .custom-date-modal-header h3 {
        color: var(--dark-text-primary);
        font-size: 0.75rem;
        margin: 0;
        font-weight: 600;
        line-height: 1.2;
    }

    .close-custom-modal {
        background: transparent;
        border: none;
        color: var(--dark-text-secondary);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: var(--radius-sm);
        transition: var(--transition-normal);
        font-size: 0.75rem;
        line-height: 1;
    }

    .close-custom-modal:hover {
        background: var(--dark-bg-glass-hover);
        color: var(--dark-text-primary);
    }

    .custom-date-modal-body {
        display: flex;
        flex-direction: row;
        gap: 0.375rem;
        margin-bottom: 0.375rem;
    }

    .date-input-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        flex: 1;
    }

    .date-input-group label {
        color: var(--dark-text-secondary);
        font-size: 0.625rem;
        font-weight: 500;
        line-height: 1.2;
    }

    .date-input {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        color: var(--dark-text-primary);
        padding: 0.3125rem 0.4375rem;
        padding-right: 1.75rem;
        border-radius: var(--radius-sm);
        font-size: 0.6875rem;
        transition: var(--transition-normal);
        height: 28px;
        box-sizing: border-box;
        cursor: pointer;
        width: 100%;
        position: relative;
    }

    .date-input::-webkit-calendar-picker-indicator {
        filter: invert(1);
        cursor: pointer;
        opacity: 0.8;
        position: absolute;
        right: 0.4375rem;
        width: 14px;
        height: 14px;
    }

    .date-input::-webkit-calendar-picker-indicator:hover {
        opacity: 1;
    }

    .date-input:focus {
        outline: none;
        border-color: #8b5cf6;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }

    .custom-date-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.3125rem;
        margin-top: 0.375rem;
    }

    .btn-cancel,
    .btn-apply {
        padding: 0.3125rem 0.75rem;
        border-radius: var(--radius-sm);
        font-size: 0.6875rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition-normal);
        border: none;
        height: 26px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-clear {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        color: var(--dark-text-secondary);
    }

    .btn-clear:hover {
        background: var(--dark-bg-glass-hover);
        color: var(--dark-text-primary);
    }

    .btn-apply {
        background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%);
        color: #ffffff;
    }

    .btn-apply:hover {
        background: linear-gradient(135deg, #7c3aed 0%, #9333ea 100%);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }


    /* Stat Cards Grid */
    .stat-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        padding-top: 10px;
        /* Limit repaints to this container */
        contain: layout style paint;
    }

    /* Stat Card */
    .stat-card {
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-xl);
        padding: var(--space-lg);
        box-shadow: var(--glass-shadow);
        transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
        position: relative;
        overflow: visible;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: var(--space-md);
        width: 100%;
        /* Enable GPU acceleration */
        transform: translateZ(0);
        will-change: transform;
        /* Limit repaints */
        contain: layout style paint;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border-radius: var(--radius-xl);
        opacity: 0;
        transition: var(--transition-normal);
        z-index: -1;
    }

    .stat-card:hover {
        transform: translateY(-5px) translateZ(0);
        /* Reduce shadow blur for better performance */
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .stat-card:hover::before {
        opacity: 0.1;
    }

    /* Permanent glow effect behind cards */
    .stat-card::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        transform: translate(-50%, -50%) translateZ(0);
        z-index: -2;
        pointer-events: none;
        /* Reduce blur from 30px to 20px for better performance */
        filter: blur(20px);
        opacity: 0.6;
        /* Isolate this layer */
        will-change: transform;
    }

    /* Assigned - Slate Gray */
    .stat-card.assigned::before {
        background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    }

    .stat-card.assigned::after {
        background: radial-gradient(circle, rgba(100, 116, 139, 0.5) 0%, transparent 70%);
    }

    .stat-card.assigned .stat-icon {
        background: rgba(100, 116, 139, 0.2);
        border-color: rgba(100, 116, 139, 0.4);
    }

    .stat-card.assigned .stat-icon i {
        color: #cbd5e1;
    }

    /* Working - Blue */
    .stat-card.working::before {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }

    .stat-card.working::after {
        background: radial-gradient(circle, rgba(59, 130, 246, 0.5) 0%, transparent 70%);
    }

    .stat-card.working .stat-icon {
        background: rgba(59, 130, 246, 0.2);
        border-color: rgba(59, 130, 246, 0.4);
    }

    .stat-card.working .stat-icon i {
        color: #93c5fd;
    }

    /* Review - Indigo */
    .stat-card.review::before {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    }

    .stat-card.review::after {
        background: radial-gradient(circle, rgba(99, 102, 241, 0.5) 0%, transparent 70%);
    }

    .stat-card.review .stat-icon {
        background: rgba(99, 102, 241, 0.2);
        border-color: rgba(99, 102, 241, 0.4);
    }

    .stat-card.review .stat-icon i {
        color: #a5b4fc;
    }

    /* Revise - Amber */
    .stat-card.revise::before {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .stat-card.revise::after {
        background: radial-gradient(circle, rgba(245, 158, 11, 0.5) 0%, transparent 70%);
    }

    .stat-card.revise .stat-icon {
        background: rgba(245, 158, 11, 0.2);
        border-color: rgba(245, 158, 11, 0.4);
    }

    .stat-card.revise .stat-icon i {
        color: #fcd34d;
    }

    /* Approved - Green */
    .stat-card.approved::before {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .stat-card.approved::after {
        background: radial-gradient(circle, rgba(16, 185, 129, 0.5) 0%, transparent 70%);
    }

    .stat-card.approved .stat-icon {
        background: rgba(16, 185, 129, 0.2);
        border-color: rgba(16, 185, 129, 0.4);
    }

    .stat-card.approved .stat-icon i {
        color: #6ee7b7;
    }

    /* Complete - Emerald */
    .stat-card.complete::before {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }

    .stat-card.complete::after {
        background: radial-gradient(circle, rgba(16, 185, 129, 0.6) 0%, transparent 70%);
    }

    .stat-card.complete .stat-icon {
        background: rgba(16, 185, 129, 0.25);
        border-color: rgba(16, 185, 129, 0.45);
    }

    .stat-card.complete .stat-icon i {
        color: #34d399;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--dark-text-primary);
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        border: 1px solid var(--glass-border);
        box-shadow: var(--neu-shadow-light), var(--neu-shadow-dark);
        flex-shrink: 0;
        transition: var(--transition-normal);
    }

    .stat-card:hover .stat-icon {
        transform: scale(1.1) translateZ(0);
    }

    /* Drag and Drop Styles */
    .stat-card {
        cursor: grab;
        user-select: none;
    }

    .stat-card:active {
        cursor: grabbing;
    }

    .stat-card.dragging {
        opacity: 0.5;
        transform: scale(0.95) translateZ(0);
    }

    .stat-card.drag-over {
        border-color: rgba(139, 92, 246, 0.6);
        box-shadow: 0 0 20px rgba(139, 92, 246, 0.4);
        transform: scale(1.02) translateZ(0);
    }

    .stat-icon i {
        font-size: 1.5rem !important;
        width: 1.5rem;
        height: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .stat-content {
        flex: 1;
        min-width: 0;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--dark-text-primary);
        margin: 0;
        line-height: 1;
    }

    .stat-label {
        font-size: 0.9rem;
        color: var(--dark-text-secondary);
        margin: var(--space-xs) 0 0 0;
        font-weight: 500;
    }

    /* Ticket Overview Section - Distinct Style */
    .ticket-stats-section {
        margin-top: 3rem;
        position: relative;
    }

    .ticket-cards-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        contain: layout style paint;
    }

    /* Ticket Card - Ticket-Style Shape with Inverse Radius Curves */
    .ticket-card {
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        border-top: 1px solid var(--glass-border);
        border-bottom: 1px solid var(--glass-border);
        border-left: none;
        border-right: none;
        border-radius: 1rem;
        padding: var(--space-lg);
        box-shadow: var(--glass-shadow);
        transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
        position: relative;
        overflow: visible;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: var(--space-md);
        width: 100%;
        opacity: 0;
        transform: translateY(20px) translateZ(0);
        animation: ticketCardFadeIn 0.6s ease-out forwards;
        /* Enable GPU acceleration */
        will-change: transform, opacity;
        contain: layout style paint;
    }
    
    /* Inverse radius curves on left and right sides (aligned with icon center) */
    .ticket-card::before {
        content: '';
        position: absolute;
        top: 50%;
        left: -20px;
        width: 40px;
        height: 40px;
        background-color: var(--dark-bg-primary);
        background-image: 
            radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 40% 40%, rgba(6, 182, 212, 0.05) 0%, transparent 50%);
        border-radius: 50%;
        transform: translateY(-50%);
        z-index: 2;
        pointer-events: none;
    }
    
    .ticket-card::after {
        content: '';
        position: absolute;
        top: 50%;
        right: -20px;
        width: 40px;
        height: 40px;
        background-color: var(--dark-bg-primary);
        background-image: 
            radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 40% 40%, rgba(6, 182, 212, 0.05) 0%, transparent 50%);
        border-radius: 50%;
        transform: translateY(-50%);
        z-index: 2;
        pointer-events: none;
    }

    .ticket-card-wrapper:nth-child(1) .ticket-card {
        animation-delay: 0.1s;
    }

    .ticket-card-wrapper:nth-child(2) .ticket-card {
        animation-delay: 0.2s;
    }

    .ticket-card-wrapper:nth-child(3) .ticket-card {
        animation-delay: 0.3s;
    }

    @keyframes ticketCardFadeIn {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Dashed perforated inner border on top and bottom only */
    .ticket-card-inner-border {
        position: absolute;
        top: 12px;
        left: 12px;
        right: 12px;
        bottom: 12px;
        border-top: 1px dashed rgba(255, 255, 255, 0.25);
        border-bottom: 1px dashed rgba(255, 255, 255, 0.25);
        border-left: none;
        border-right: none;
        border-radius: 0.75rem;
        z-index: 0;
        pointer-events: none;
    }
    
    /* Ensure content is above the inner border */
    .ticket-icon,
    .ticket-content {
        position: relative;
        z-index: 1;
    }
    
    /* Ticket gradient overlay for hover effect - using wrapper */
    .ticket-card-wrapper::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border-radius: 1rem;
        opacity: 0;
        transition: var(--transition-normal);
        z-index: -1;
    }

    .ticket-card:hover {
        transform: translateY(-5px) translateZ(0);
        /* Reduce shadow blur */
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        border-top-color: rgba(255, 255, 255, 0.3);
        border-bottom-color: rgba(255, 255, 255, 0.3);
    }

    .ticket-card-wrapper:hover::before {
        opacity: 0.1;
    }

    /* Ticket Icon - Matching Task Stat Icon */
    .ticket-icon {
        width: 60px;
        height: 60px;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--dark-text-primary);
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        border: 1px solid var(--glass-border);
        box-shadow: var(--neu-shadow-light), var(--neu-shadow-dark);
        flex-shrink: 0;
        transition: var(--transition-normal);
    }

    .ticket-icon i {
        font-size: 1.5rem !important;
        width: 1.5rem;
        height: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .ticket-card:hover .ticket-icon {
        transform: scale(1.1) translateZ(0);
    }

    /* Ticket Content - Matching Task Stat Content */
    .ticket-content {
        flex: 1;
        min-width: 0;
    }

    .ticket-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--dark-text-primary);
        margin: 0;
        line-height: 1;
    }

    .ticket-label {
        font-size: 0.9rem;
        color: var(--dark-text-secondary);
        margin: var(--space-xs) 0 0 0;
        font-weight: 500;
    }

    /* Permanent glow effect behind ticket cards - using wrapper */
    .ticket-card-wrapper {
        position: relative;
    }
    
    .ticket-card-wrapper::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        transform: translate(-50%, -50%) translateZ(0);
        z-index: -1;
        pointer-events: none;
        /* Reduce blur from 30px to 20px for better performance */
        filter: blur(20px);
        opacity: 0.6;
        will-change: transform;
    }

    /* Raised Ticket - Orange/Red */
    .ticket-card.raised {
        background: linear-gradient(135deg, rgba(249, 115, 22, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%);
        border-top-color: rgba(249, 115, 22, 0.3);
        border-bottom-color: rgba(249, 115, 22, 0.3);
    }
    
    /* Inverse radius curves - use page background to create cutout effect */
    .ticket-card.raised::before,
    .ticket-card.raised::after {
        background-color: var(--dark-bg-primary);
        background-image: 
            radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 40% 40%, rgba(6, 182, 212, 0.05) 0%, transparent 50%);
    }

    .ticket-card-wrapper.raised-wrapper::after {
        background: radial-gradient(circle, rgba(249, 115, 22, 0.5) 0%, transparent 70%);
    }

    .ticket-card.raised .ticket-icon {
        background: rgba(249, 115, 22, 0.2);
        border-color: rgba(249, 115, 22, 0.4);
    }

    .ticket-card.raised .ticket-icon i {
        color: #fb923c;
    }

    /* In Progress Ticket - Blue/Cyan */
    .ticket-card.in-progress {
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.15) 0%, rgba(59, 130, 246, 0.15) 100%);
        border-top-color: rgba(6, 182, 212, 0.3);
        border-bottom-color: rgba(6, 182, 212, 0.3);
    }
    
    /* Inverse radius curves - use page background to create cutout effect */
    .ticket-card.in-progress::before,
    .ticket-card.in-progress::after {
        background-color: var(--dark-bg-primary);
        background-image: 
            radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 40% 40%, rgba(6, 182, 212, 0.05) 0%, transparent 50%);
    }

    .ticket-card-wrapper.in-progress-wrapper::after {
        background: radial-gradient(circle, rgba(6, 182, 212, 0.5) 0%, transparent 70%);
    }

    .ticket-card.in-progress .ticket-icon {
        background: rgba(6, 182, 212, 0.2);
        border-color: rgba(6, 182, 212, 0.4);
    }

    .ticket-card.in-progress .ticket-icon i {
        color: #22d3ee;
    }

    /* Resolved Ticket - Green */
    .ticket-card.resolved {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.15) 100%);
        border-top-color: rgba(16, 185, 129, 0.3);
        border-bottom-color: rgba(16, 185, 129, 0.3);
    }
    
    /* Inverse radius curves - use page background to create cutout effect */
    .ticket-card.resolved::before,
    .ticket-card.resolved::after {
        background-color: var(--dark-bg-primary);
        background-image: 
            radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 40% 40%, rgba(6, 182, 212, 0.05) 0%, transparent 50%);
    }

    .ticket-card-wrapper.resolved-wrapper::after {
        background: radial-gradient(circle, rgba(16, 185, 129, 0.5) 0%, transparent 70%);
    }

    .ticket-card.resolved .ticket-icon {
        background: rgba(16, 185, 129, 0.2);
        border-color: rgba(16, 185, 129, 0.4);
    }

    .ticket-card.resolved .ticket-icon i {
        color: #34d399;
    }

    /* Optimize transitions for better scroll performance */
    @media (prefers-reduced-motion: no-preference) {
        .stat-card,
        .ticket-card {
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
        }
    }

    /* Reduce motion for users who prefer it */
    @media (prefers-reduced-motion: reduce) {
        .stat-card,
        .ticket-card {
            transition: none;
        }
        
        .stat-card:hover,
        .ticket-card:hover {
            transform: none;
        }
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .stat-cards-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .ticket-cards-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 768px) {
        .client-dashboard-container {
            padding: 1rem;
        }

        .stat-cards-grid {
            grid-template-columns: 1fr;
        }

        .ticket-cards-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-header h1 {
            font-size: 1.5rem;
        }

        .ticket-icon {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }

        .ticket-value {
            font-size: 1.8rem;
        }
    }
</style>

<div class="client-dashboard-container">
    <div class="dashboard-header">
        <h1>Welcome Back, <?php echo $username; ?>!</h1>
        <p>Track your tasks and tickets across different stages</p>
    </div>

    <!-- Stats Section with Date Range Toggle -->
    <div class="stats-section">
        <div class="stats-header">
            <div>
                <h6 class="stats-title">
                    <i class="fas fa-chart-bar"></i>
                    <span id="overviewTitle">Tasks Overview</span>
                </h6>
                <div id="dateRangeDisplay" class="date-range-display"></div>
            </div>
            <div class="date-range-selector">
                <button class="date-range-btn active" data-range="last_7d" title="Last 1 Week">1W</button>
                <button class="date-range-btn" data-range="2w" title="Last 2 Weeks">2W</button>
                <button class="date-range-btn" data-range="4w" title="Last 4 Weeks">4W</button>
                <button class="date-range-btn custom-date-btn" id="customDateBtn" title="Custom Date Range">
                    <i class="fas fa-calendar"></i>
                </button>
            </div>
        </div>
        <!-- Custom Date Picker Modal -->
        <div id="customDateModal" class="custom-date-modal">
            <div class="custom-date-modal-content">
                <div class="custom-date-modal-header">
                    <h3>Select Date Range</h3>
                    <button class="close-custom-modal" id="closeCustomModal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="custom-date-modal-body">
                    <div class="date-input-group">
                        <label>From Date:</label>
                        <input type="date" id="customDateFrom" class="date-input">
                    </div>
                    <div class="date-input-group">
                        <label>To Date:</label>
                        <input type="date" id="customDateTo" class="date-input">
                    </div>
                </div>
                <div class="custom-date-modal-footer">
                    <button class="btn-clear" id="clearCustomDate">Clear</button>
                    <button class="btn-apply" id="applyCustomDate">Apply</button>
                </div>
            </div>
        </div>
        </div>
        <div class="stat-cards-grid">
        <!-- Assigned Card -->
        <div class="stat-card assigned" draggable="true" data-card-id="assigned">
            <div class="stat-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="assigned-count">0</div>
                <div class="stat-label">Assigned</div>
            </div>
        </div>

        <!-- Working Card -->
        <div class="stat-card working" draggable="true" data-card-id="working">
            <div class="stat-icon">
                <i class="fas fa-cog"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="working-count">0</div>
                <div class="stat-label">Working</div>
            </div>
        </div>

        <!-- Review Card -->
        <div class="stat-card review" draggable="true" data-card-id="review">
            <div class="stat-icon">
                <i class="fas fa-eye"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="review-count">0</div>
                <div class="stat-label">Review</div>
            </div>
        </div>

        <!-- Revise Card -->
        <div class="stat-card revise" draggable="true" data-card-id="revise">
            <div class="stat-icon">
                <i class="fas fa-edit"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="revise-count">0</div>
                <div class="stat-label">Revise</div>
            </div>
        </div>

        <!-- Approved Card -->
        <div class="stat-card approved" draggable="true" data-card-id="approved">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="approved-count">0</div>
                <div class="stat-label">Approved</div>
            </div>
        </div>

        <!-- Complete Card -->
        <div class="stat-card complete" draggable="true" data-card-id="complete">
            <div class="stat-icon">
                <i class="fas fa-flag-checkered"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="complete-count">0</div>
                <div class="stat-label">Complete</div>
            </div>
        </div>
    </div>

    <!-- Ticket Overview Section -->
    <div class="ticket-stats-section">
        <div class="stats-header">
            <h6 class="stats-title">
                <i class="fas fa-ticket-alt"></i>
                <span>Ticket Overview</span>
            </h6>
        </div>
        <div class="ticket-cards-grid">
            <!-- Raised Ticket Card -->
            <div class="ticket-card-wrapper raised-wrapper">
                <div class="ticket-card raised">
                    <div class="ticket-card-inner-border"></div>
                    <div class="ticket-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="ticket-content">
                        <div class="ticket-value" id="raised-count">0</div>
                        <div class="ticket-label">Raised</div>
                    </div>
                </div>
            </div>

            <!-- In Progress Ticket Card -->
            <div class="ticket-card-wrapper in-progress-wrapper">
                <div class="ticket-card in-progress">
                    <div class="ticket-card-inner-border"></div>
                    <div class="ticket-icon">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="ticket-content">
                        <div class="ticket-value" id="in-progress-count">0</div>
                        <div class="ticket-label">In Progress</div>
                    </div>
                </div>
            </div>

            <!-- Resolved Ticket Card -->
            <div class="ticket-card-wrapper resolved-wrapper">
                <div class="ticket-card resolved">
                    <div class="ticket-card-inner-border"></div>
                    <div class="ticket-icon">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="ticket-content">
                        <div class="ticket-value" id="resolved-count">0</div>
                        <div class="ticket-label">Resolved</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Global variable to store current date range
    let currentDateRange = {
        type: 'last_7d',
        fromDate: null,
        toDate: null
    };

    // Helper function to get Monday of a given date (week starts Monday)
    function getMonday(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
        return new Date(d.setDate(diff));
    }

    // Helper function to get Sunday of a given week (week ends Sunday)
    function getSunday(monday) {
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        return sunday;
    }

    // Calculate date range based on options
    function calculateDateRange(rangeType) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        let fromDate, toDate;

        switch(rangeType) {
            case 'last_7d':
                // Last 1 week: 7 days ago to today (including today)
                fromDate = new Date(today);
                fromDate.setDate(today.getDate() - 6); // Include today, so -6 days
                toDate = new Date(today);
                break;
            case '2w':
                // Last 2 weeks: Current week + last week = Monday of last week to today
                const currentMonday = getMonday(today);
                const lastWeekMonday = new Date(currentMonday);
                lastWeekMonday.setDate(currentMonday.getDate() - 7); // Monday of last week
                fromDate = lastWeekMonday;
                toDate = new Date(today); // Include current week up to today
                break;
            case '4w':
                // Last 4 weeks: Current week + last 3 weeks = Monday of 3 weeks ago to today
                const currentMonday4W = getMonday(today);
                const threeWeeksAgoMonday = new Date(currentMonday4W);
                threeWeeksAgoMonday.setDate(currentMonday4W.getDate() - 21); // Monday of 3 weeks ago (4 weeks total including current)
                fromDate = threeWeeksAgoMonday;
                toDate = new Date(today); // Include current week up to today
                break;
            case 'custom':
                // Custom dates from inputs
                const fromInput = document.getElementById('customDateFrom');
                const toInput = document.getElementById('customDateTo');
                if (fromInput && toInput && fromInput.value && toInput.value) {
                    fromDate = new Date(fromInput.value);
                    toDate = new Date(toInput.value);
                } else {
                    // Default to last 1 week if custom not set
                    fromDate = new Date(today);
                    fromDate.setDate(today.getDate() - 6);
                    toDate = new Date(today);
                }
                break;
            default:
                // Default to last 1 week
                fromDate = new Date(today);
                fromDate.setDate(today.getDate() - 6);
                toDate = new Date(today);
        }

        // For last_7d, 2w, and 4w, add 1 day to toDate to ensure today is included in backend queries
        let toDateString = toDate.toISOString().split('T')[0];
        if (rangeType === 'last_7d' || rangeType === '2w' || rangeType === '4w') {
            const toDatePlusOne = new Date(toDate);
            toDatePlusOne.setDate(toDate.getDate() + 1);
            toDateString = toDatePlusOne.toISOString().split('T')[0];
        }
        
        return {
            fromDate: fromDate.toISOString().split('T')[0],
            toDate: toDateString
        };
    }

    // Function to animate counter
    function animateCounter(element, target, isPercentage = false) {
        const el = document.querySelector(element);
        if (!el) return;

        const duration = 1500;
        const start = 0;
        const increment = target / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            el.textContent = isPercentage ? Math.round(current) + '%' : Math.round(current);
        }, 16);
    }

    // Update overview title based on date range
    function updateOverviewTitle(range) {
        const titleElement = document.getElementById('overviewTitle');
        if (!titleElement) return;
        
        let title = '';
        switch(range) {
            case 'last_7d':
                title = '1 Week Tasks Overview';
                break;
            case '2w':
                title = 'Last 2 Weeks Tasks Overview';
                break;
            case '4w':
                title = 'Last 4 Weeks Tasks Overview';
                break;
            case 'custom':
                const fromInput = document.getElementById('customDateFrom');
                const toInput = document.getElementById('customDateTo');
                if (fromInput && toInput && fromInput.value && toInput.value) {
                    const from = new Date(fromInput.value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    const to = new Date(toInput.value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    title = `Custom Range Tasks: ${from} - ${to}`;
                } else {
                    title = 'Custom Range Tasks Overview';
                }
                break;
            default:
                title = '1 Week Tasks Overview';
        }
        
        titleElement.textContent = title;
    }

    // Update date range display below the title
    function updateDateRangeDisplay() {
        const dateRangeDisplay = document.getElementById('dateRangeDisplay');
        if (!dateRangeDisplay) return;

        // Use stored date range values (they already have +1 day for filters that need it)
        if (!currentDateRange.fromDate || !currentDateRange.toDate) {
            const dateRange = calculateDateRange(currentDateRange.type);
            currentDateRange.fromDate = dateRange.fromDate;
            currentDateRange.toDate = dateRange.toDate;
        }
        
        // Parse the dates (they come as YYYY-MM-DD strings)
        const fromDate = new Date(currentDateRange.fromDate);
        const toDate = new Date(currentDateRange.toDate);
        
        // For filters that add 1 day to toDate for backend queries, subtract it back for display
        let displayToDate = toDate;
        if (currentDateRange.type === 'last_7d' || currentDateRange.type === '2w' || currentDateRange.type === '4w') {
            displayToDate = new Date(toDate);
            displayToDate.setDate(toDate.getDate() - 1); // Subtract the day we added for backend query
        }
        
        // Format dates nicely
        const fromFormatted = fromDate.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
        const toFormatted = displayToDate.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
        
        // Display the date range
        dateRangeDisplay.textContent = `${fromFormatted} - ${toFormatted}`;
    }

    // Load dashboard data based on date range
    async function loadDashboardData() {
        try {
            // Calculate date range
            const dateRange = calculateDateRange(currentDateRange.type);
            currentDateRange.fromDate = dateRange.fromDate;
            currentDateRange.toDate = dateRange.toDate;

            // Update date range display
            updateDateRangeDisplay();

            // Fetch task and ticket data from server
            const response = await fetch(`../ajax/client_dashboard_data.php?date_from=${dateRange.fromDate}&date_to=${dateRange.toDate}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            console.log('Dashboard data received:', data);

            if (data.success) {
                // Update task counts (Task Overview section only)
                if (data.tasks) {
                    animateCounter('#assigned-count', data.tasks.assigned || 0);
                    animateCounter('#working-count', data.tasks.working || 0);
                    animateCounter('#review-count', data.tasks.review || 0);
                    animateCounter('#revise-count', data.tasks.revise || 0);
                    animateCounter('#approved-count', data.tasks.approved || 0);
                    animateCounter('#complete-count', data.tasks.complete || 0);
                }

                // Update ticket counts (Ticket Overview section only)
                if (data.tickets) {
                    animateCounter('#raised-count', data.tickets.raised || 0);
                    animateCounter('#in-progress-count', data.tickets.in_progress || 0);
                    animateCounter('#resolved-count', data.tickets.resolved || 0);
                }
            } else {
                console.error('Error loading dashboard data:', data.error);
                // Fallback to zeros on error
                animateCounter('#assigned-count', 0);
                animateCounter('#working-count', 0);
                animateCounter('#review-count', 0);
                animateCounter('#revise-count', 0);
                animateCounter('#approved-count', 0);
                animateCounter('#complete-count', 0);
                animateCounter('#raised-count', 0);
                animateCounter('#in-progress-count', 0);
                animateCounter('#resolved-count', 0);
            }
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            // Fallback to zeros on error
            animateCounter('#assigned-count', 0);
            animateCounter('#working-count', 0);
            animateCounter('#review-count', 0);
            animateCounter('#revise-count', 0);
            animateCounter('#approved-count', 0);
            animateCounter('#complete-count', 0);
            animateCounter('#raised-count', 0);
            animateCounter('#in-progress-count', 0);
            animateCounter('#resolved-count', 0);
        }
    }

    // Custom date picker functionality
    function initializeCustomDatePicker() {
        const customBtn = document.getElementById('customDateBtn');
        const customModal = document.getElementById('customDateModal');
        const closeModal = document.getElementById('closeCustomModal');
        const clearBtn = document.getElementById('clearCustomDate');
        const applyBtn = document.getElementById('applyCustomDate');
        const fromInput = document.getElementById('customDateFrom');
        const toInput = document.getElementById('customDateTo');

        if (!customBtn || !customModal) return;

        // Open modal
        customBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            // Set default dates (last 1 week)
            const today = new Date();
            const sevenDaysAgo = new Date(today);
            sevenDaysAgo.setDate(today.getDate() - 6);
            
            if (fromInput) fromInput.value = sevenDaysAgo.toISOString().split('T')[0];
            if (toInput) toInput.value = today.toISOString().split('T')[0];
            
            customModal.classList.add('show');
        });

        // Close modal
        function closeModalFunc() {
            customModal.classList.remove('show');
        }

        if (closeModal) closeModal.addEventListener('click', closeModalFunc);
        
        // Clear button functionality
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                // Clear the date inputs
                if (fromInput) fromInput.value = '';
                if (toInput) toInput.value = '';
                
                // Remove active from custom button
                customBtn.classList.remove('active');
                
                // Reset to default (1W)
                const defaultBtn = document.querySelector('.date-range-btn[data-range="last_7d"]');
                if (defaultBtn) {
                    document.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                        b.classList.remove('active');
                    });
                    defaultBtn.classList.add('active');
                    currentDateRange.type = 'last_7d';
                    const dateRange = calculateDateRange('last_7d');
                    currentDateRange.fromDate = dateRange.fromDate;
                    currentDateRange.toDate = dateRange.toDate;
                    updateOverviewTitle('last_7d');
                    loadDashboardData();
                }
                
                // Close modal
                closeModalFunc();
            });
        }

        // Apply custom date
        if (applyBtn) {
            applyBtn.addEventListener('click', function() {
                if (fromInput && toInput && fromInput.value && toInput.value) {
                    const from = new Date(fromInput.value);
                    const to = new Date(toInput.value);
                    
                    if (from > to) {
                        alert('From date must be before To date');
                        return;
                    }

                    // Remove active from all buttons
                    document.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                        b.classList.remove('active');
                    });

                    // Set custom as active
                    customBtn.classList.add('active');
                    
                    // Update current date range
                    currentDateRange.type = 'custom';
                    currentDateRange.fromDate = fromInput.value;
                    currentDateRange.toDate = toInput.value;
                    
                    // Update overview title
                    updateOverviewTitle('custom');
                    
                    // Reload dashboard data
                    loadDashboardData();
                    
                    // Close modal
                    closeModalFunc();
                } else {
                    alert('Please select both From and To dates');
                }
            });
        }

        // Make date inputs fully clickable to open calendar
        if (fromInput) {
            fromInput.addEventListener('click', function(e) {
                // Prevent event from bubbling up
                e.stopPropagation();
                // Open the native date picker
                if (this.showPicker) {
                    this.showPicker();
                } else {
                    // Fallback for older browsers
                    this.focus();
                    this.click();
                }
            });
        }

        if (toInput) {
            toInput.addEventListener('click', function(e) {
                // Prevent event from bubbling up
                e.stopPropagation();
                // Open the native date picker
                if (this.showPicker) {
                    this.showPicker();
                } else {
                    // Fallback for older browsers
                    this.focus();
                    this.click();
                }
            });
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (customModal.classList.contains('show') && 
                !customModal.contains(e.target) && 
                !customBtn.contains(e.target)) {
                closeModalFunc();
            }
        });
    }

    // Initialize date range selector
    function initializeDateRangeSelector() {
        // Handle all date range buttons
        document.querySelectorAll('.date-range-btn[data-range]').forEach(btn => {
            btn.addEventListener('click', function() {
                const range = this.getAttribute('data-range');
                
                // Remove active class from all buttons
                document.querySelectorAll('.date-range-btn[data-range]').forEach(b => {
                    b.classList.remove('active');
                });
                
                // Remove active from custom button if selecting a preset
                const customBtn = document.getElementById('customDateBtn');
                if (customBtn) {
                    customBtn.classList.remove('active');
                }
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Update current date range
                currentDateRange.type = range;
                
                // Update overview title
                updateOverviewTitle(range);
                
                // Reload dashboard data
                loadDashboardData();
            });
        });
    }

    // Drag and Drop functionality for stat cards
    let draggedElement = null;
    let isDragging = false;

    function initializeDragAndDrop() {
        const grid = document.querySelector('.stat-cards-grid');
        if (!grid) return;

        const cards = grid.querySelectorAll('.stat-card');
        
        cards.forEach((card) => {
            // Drag start
            card.addEventListener('dragstart', function(e) {
                draggedElement = this;
                isDragging = true;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.outerHTML);
            });

            // Drag end
            card.addEventListener('dragend', function(e) {
                this.classList.remove('dragging');
                isDragging = false;
                // Remove drag-over class from all cards
                cards.forEach(c => c.classList.remove('drag-over'));
                draggedElement = null;
            });

            // Drag over
            card.addEventListener('dragover', function(e) {
                if (e.preventDefault) {
                    e.preventDefault();
                }
                e.dataTransfer.dropEffect = 'move';
                
                // Remove drag-over from all cards
                cards.forEach(c => c.classList.remove('drag-over'));
                
                // Add drag-over to current card if it's not the dragged element
                if (this !== draggedElement) {
                    this.classList.add('drag-over');
                }
                
                return false;
            });

            // Drag enter
            card.addEventListener('dragenter', function(e) {
                if (this !== draggedElement) {
                    this.classList.add('drag-over');
                }
            });

            // Drag leave
            card.addEventListener('dragleave', function(e) {
                this.classList.remove('drag-over');
            });

            // Drop
            card.addEventListener('drop', function(e) {
                if (e.stopPropagation) {
                    e.stopPropagation();
                }

                if (draggedElement !== this) {
                    const allCards = Array.from(grid.querySelectorAll('.stat-card'));
                    const draggedIndex = allCards.indexOf(draggedElement);
                    const targetIndex = allCards.indexOf(this);

                    if (draggedIndex < targetIndex) {
                        grid.insertBefore(draggedElement, this.nextSibling);
                    } else {
                        grid.insertBefore(draggedElement, this);
                    }

                    // Save new order
                    saveCardOrder();
                }

                this.classList.remove('drag-over');
                return false;
            });

            // Click handler - navigate only if not dragging
            card.addEventListener('click', function(e) {
                if (!isDragging) {
                    window.location.href = 'task_ticket.php';
                }
            });
        });
    }

    // Save card order to localStorage
    function saveCardOrder() {
        const grid = document.querySelector('.stat-cards-grid');
        if (!grid) return;

        const cards = Array.from(grid.querySelectorAll('.stat-card'));
        const order = cards.map(card => card.getAttribute('data-card-id'));
        localStorage.setItem('clientDashboardCardOrder', JSON.stringify(order));
    }

    // Load card order from localStorage
    function loadCardOrder() {
        const grid = document.querySelector('.stat-cards-grid');
        if (!grid) return;

        const savedOrder = localStorage.getItem('clientDashboardCardOrder');
        if (!savedOrder) return;

        try {
            const order = JSON.parse(savedOrder);
            const cards = Array.from(grid.querySelectorAll('.stat-card'));
            const cardMap = new Map();
            
            cards.forEach(card => {
                cardMap.set(card.getAttribute('data-card-id'), card);
            });

            // Reorder cards based on saved order
            order.forEach(cardId => {
                const card = cardMap.get(cardId);
                if (card) {
                    grid.appendChild(card);
                }
            });
        } catch (e) {
            console.error('Error loading card order:', e);
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Load saved card order first
        loadCardOrder();
        
        // Initialize drag and drop
        initializeDragAndDrop();
        
        // Initialize date range selector
        initializeDateRangeSelector();
        
        // Initialize custom date picker
        initializeCustomDatePicker();
        
        // Set initial date range
        const dateRange = calculateDateRange('last_7d');
        currentDateRange.fromDate = dateRange.fromDate;
        currentDateRange.toDate = dateRange.toDate;
        
        // Update initial title and date range display
        updateOverviewTitle('last_7d');
        updateDateRangeDisplay();
        
        // Load initial data
        loadDashboardData();
    });
</script>

<?php require_once "../includes/footer.php"; ?>
