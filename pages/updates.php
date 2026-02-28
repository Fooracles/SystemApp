<?php
$page_title = "Updates";
require_once "../includes/header.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: ../login.php");
    exit;
}

// Check if user is Admin, Manager, or Client
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

<!-- Vanilla Tilt.js for parallax effect -->
<script src="https://cdn.jsdelivr.net/npm/vanilla-tilt@1.8.0/dist/vanilla-tilt.min.js"></script>

<style>
/* Updates Page Styles */
.updates-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 0.5rem;
}

.updates-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
    gap: 1rem;
    position: relative;
}

.updates-header h1 {
    color: var(--dark-text-primary, #e2e8f0);
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    margin-top: 0;
}

.updates-header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.updates-filter-toggles {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    padding: 0.2rem;
    backdrop-filter: blur(8px);
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
}

@media (max-width: 1024px) {
    .updates-filter-toggles {
        position: static;
        transform: none;
        order: 2;
        width: 100%;
        justify-content: center;
        margin: 0.5rem 0;
    }
    
    .updates-search-container {
        order: 3;
    }
}

.updates-filter-toggle {
    padding: 0.3rem 0.7rem;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    border-radius: 0.375rem;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.updates-filter-toggle:hover {
    color: rgba(255, 255, 255, 0.8);
    background: rgba(255, 255, 255, 0.05);
}

.updates-filter-toggle.active {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
    box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
}

.updates-search-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.updates-search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.updates-search-icon-btn {
    width: 2.25rem;
    height: 2.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(8px);
    z-index: 2;
    position: relative;
}

.updates-search-icon-btn:hover {
    background: rgba(30, 41, 59, 0.95);
    border-color: rgba(139, 92, 246, 0.3);
    color: #8b5cf6;
}

.updates-search-icon-btn i {
    transition: transform 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.updates-search-wrapper:hover .updates-search-icon-btn i {
    transform: rotate(360deg);
}

.updates-search-input {
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    padding: 0.5rem 0.875rem;
    color: var(--dark-text-primary, #e2e8f0);
    font-size: 0.8rem;
    width: 0;
    opacity: 0;
    overflow: hidden;
    transition: width 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) 0.3s, 
                opacity 0.3s ease 0.4s,
                border-color 0.3s ease,
                box-shadow 0.3s ease;
    backdrop-filter: blur(8px);
    position: absolute;
    right: 0;
    top: 0;
    height: 2.25rem;
}

.updates-search-wrapper:hover .updates-search-input,
.updates-search-input:focus,
.updates-search-input.has-content {
    width: 240px;
    opacity: 1;
}

.updates-search-input:focus {
    outline: none;
    border-color: rgba(139, 92, 246, 0.5);
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.updates-search-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.updates-client-filter {
    margin: 0;
    display: inline-flex;
    align-items: center;
    position: relative;
}

.updates-client-select {
    padding: 0.5rem 2.5rem 0.5rem 0.75rem;
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    color: transparent;
    font-size: 0.875rem;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    min-width: 160px;
    max-width: 220px;
    height: 2.25rem;
    position: relative;
    z-index: 1;
    width: 100%;
}

.updates-client-filter:hover .updates-client-select {
    background-color: rgba(30, 41, 59, 0.95);
}

.updates-client-filter:hover .fa-chevron-down {
    color: #a78bfa;
}

.updates-client-select:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.updates-client-select option {
    padding: 0.5rem;
    color: rgba(255, 255, 255, 0.9);
    background: rgba(2, 8, 20, 0.98);
}





.updates-header h1 i {
    color: #8b5cf6;
    font-size: 1.25rem;
}

.btn-add-update {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border: 1px solid rgba(139, 92, 246, 0.35);
    color: #fff;
    padding: 0.45rem 0.875rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.8125rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    margin: 0;
    white-space: nowrap;
}

.btn-add-update:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.5);
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    color: #fff;
}

.btn-add-update i {
    font-size: 0.8rem;
}

/* Updates Grid */
.updates-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 0;
    width: 100%;
    align-items: flex-start;
}

@media (max-width: 1200px) {
    .update-card {
        max-width: 100%;
    }
}

@media (max-width: 768px) {
    .update-card {
        max-width: 100%;
    }
    
    .update-message-bubble {
        max-width: 100%;
    }
}

/* Update Card */
.update-card {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
    position: relative;
    cursor: pointer;
    transition: opacity 0.2s ease;
    width: 100%;
    max-width: 100%;
}

.update-card-left {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.375rem;
    flex-shrink: 0;
}

.update-card:hover {
    opacity: 0.9;
}

/* Message Bubble */
.update-message-bubble {
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    padding: 0.25rem 0.75rem 0.375rem 0.75rem;
    min-width: 200px;
    max-width: 600px;
    position: relative;
    backdrop-filter: blur(8px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.update-card:hover .update-message-bubble {
    border-color: rgba(139, 92, 246, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Update Styling - All updates use same style */
.update-message-bubble {
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(255, 255, 255, 0.1);
}

.update-icon {
    background: rgba(139, 92, 246, 0.15);
    border-color: rgba(139, 92, 246, 0.2);
}

.update-username {
    color: rgba(255, 255, 255, 0.8);
}

/* Profile Picture */
.update-icon {
    width: 3rem;
    height: 3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(139, 92, 246, 0.15);
    color: #8b5cf6;
    font-size: 1.25rem;
    flex-shrink: 0;
    overflow: hidden;
    border: 1.5px solid rgba(139, 92, 246, 0.2);
}

.update-icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.update-icon .avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
    font-weight: 600;
    font-size: 0.6875rem;
}

.update-username {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.6875rem;
    font-weight: 500;
    text-align: center;
    max-width: 3rem;
    word-wrap: break-word;
    line-height: 1.2;
}

/* Message Header - Title at top */
.update-card-title {
    margin-bottom: 0.25rem;
    margin-top: 0;
    padding-right: 4.5rem;
    position: relative;
}

.update-card-title h3 {
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.8125rem;
    font-weight: 600;
    margin: 0;
    line-height: 1.3;
    word-wrap: break-word;
    overflow-wrap: break-word;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    max-width: 100%;
}

.update-card-title .update-type-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
    margin-top: 0.5rem;
    text-transform: uppercase;
}

/* Card Body */
.update-card-body {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
}

.update-content-wrapper {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    width: 100%;
    line-height: 1.4;
    gap: 0.25rem;
}

.update-content {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.75rem;
    line-height: 1.4;
    word-wrap: break-word;
    margin: 0;
    flex: 1;
    min-width: 0;
}

.update-content-text {
    display: block;
}

.update-content-text.truncated {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-wrap: break-word;
}

.read-more-link {
    color: #14b8a6;
    font-size: 0.75rem;
    text-decoration: none;
    cursor: pointer;
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
    display: inline-block;
}

.read-more-link:hover {
    text-decoration: underline;
    color: #2dd4bf;
}

.update-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.6875rem;
    margin: 0;
    margin-left: auto;
    white-space: nowrap;
}

.update-meta-item:not(:last-child)::after {
    content: '•';
    margin: 0 0.375rem;
    color: rgba(255, 255, 255, 0.4);
}

.update-meta-item i {
    color: #a78bfa;
    width: 1rem;
    font-size: 0.875rem;
}

.update-meta-item strong {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

/* Card Footer - Action buttons */
.update-card-footer {
    display: flex;
    gap: 0.375rem;
    align-items: center;
    flex-shrink: 0;
    opacity: 0;
    transition: opacity 0.3s ease;
    position: absolute;
    top: 0.375rem;
    right: 0.5rem;
}

.update-card:hover .update-card-footer {
    opacity: 1;
}

.btn-update-action {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-weight: 600;
    font-size: 0.6875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    white-space: nowrap;
}

.btn-view-full {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
    border: 1px solid rgba(139, 92, 246, 0.3);
}

.btn-view-full:hover {
    background: rgba(139, 92, 246, 0.25);
    color: #c4b5fd;
    border-color: rgba(139, 92, 246, 0.5);
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
.updates-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: rgba(255, 255, 255, 0.6);
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    align-self: center;
}

.updates-empty i {
    font-size: 4rem;
    color: rgba(255, 255, 255, 0.3);
    margin-bottom: 1rem;
    display: block;
}

.updates-empty h3 {
    color: var(--dark-text-primary, #e2e8f0);
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.updates-empty p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
}

/* Add/Edit Update Modal */
.update-modal {
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

.update-modal.active {
    display: flex;
}

.update-modal-content {
    background: rgba(17, 24, 39, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    padding: 1.25rem;
    width: 100%;
    max-width: 600px;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.update-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.update-modal-header h2 {
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

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.btn-attachment-pin {
    position: absolute;
    bottom: 0.625rem;
    left: 0.625rem;
    background: rgba(139, 92, 246, 0.2);
    border: 1px solid rgba(139, 92, 246, 0.3);
    color: #a78bfa;
    padding: 0.375rem;
    border-radius: 0.375rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    z-index: 10;
}

.btn-attachment-pin:hover {
    background: rgba(139, 92, 246, 0.3);
    color: #c4b5fd;
    border-color: rgba(139, 92, 246, 0.5);
    transform: scale(1.1);
}

.btn-attachment-pin i {
    font-size: 0.7rem;
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
    padding: 4rem 2rem;
    color: rgba(255, 255, 255, 0.6);
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    align-self: center;
}

.loading p {
    margin: 0;
    font-size: 1rem;
}


/* View Update Modal */
.view-update-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(6px);
    z-index: 1001;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.view-update-modal.active {
    display: flex;
}

.view-update-modal-content {
    background: rgba(17, 24, 39, 0.98);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 0.875rem;
    padding: 0;
    width: 100%;
    max-width: 560px;
    max-height: calc(100vh - 4rem);
    overflow: hidden;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.6);
    display: flex;
    flex-direction: column;
    position: relative;
    transition: top 0.2s ease, left 0.2s ease;
}

.view-update-header {
    padding: 1.25rem 1.5rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    flex-shrink: 0;
}

.view-update-header h2 {
    color: var(--dark-text-primary, #e2e8f0);
    font-size: 1.125rem;
    font-weight: 700;
    margin: 0 0 0.75rem 0;
    line-height: 1.4;
    padding-right: 2.25rem;
    word-wrap: break-word;
}

.view-update-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
    color: rgba(255, 255, 255, 0.65);
    font-size: 0.75rem;
}

.view-update-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.view-update-meta i {
    color: #8b5cf6;
    font-size: 0.6875rem;
    width: 0.8125rem;
}

.view-update-content {
    padding: 1.25rem 1.5rem 0.5rem;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.875rem;
    line-height: 1.65;
    white-space: pre-wrap;
    word-wrap: break-word;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}

/* Custom scrollbar for view update modal */
.view-update-content::-webkit-scrollbar {
    width: 6px;
}

.view-update-content::-webkit-scrollbar-track {
    background: rgba(30, 41, 59, 0.5);
    border-radius: 3px;
}

.view-update-content::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.5);
    border-radius: 3px;
}

.view-update-content::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.7);
}

.view-update-content > div {
    margin: 0;
    padding: 0;
}

.view-update-attachment {
    margin-top: 0.375rem;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.view-update-attachment h4 {
    color: var(--dark-text-primary, #e2e8f0);
    font-size: 0.8125rem;
    font-weight: 600;
    margin: 4px 0 0.375rem 0;
    display: flex;
    align-items: center;
    gap: 0.4375rem;
}

.view-update-attachment h4 i {
    color: #8b5cf6;
    font-size: 0.8125rem;
}

.view-update-attachment a {
    display: inline-flex;
    align-items: center;
    gap: 0.4375rem;
    padding: 0.5rem 0.875rem;
    background: rgba(139, 92, 246, 0.12);
    color: #a78bfa;
    border: 1px solid rgba(139, 92, 246, 0.25);
    border-radius: 0.4375rem;
    text-decoration: none;
    font-size: 0.8125rem;
    font-weight: 500;
    transition: all 0.2s ease;
    max-width: 100%;
    word-break: break-word;
}

.view-update-attachment a:hover {
    background: rgba(139, 92, 246, 0.2);
    border-color: rgba(139, 92, 246, 0.4);
    color: #c4b5fd;
    transform: translateY(-1px);
}

.view-update-attachment a i {
    font-size: 0.75rem;
    flex-shrink: 0;
}

.view-update-header .btn-close-modal {
    position: absolute;
    top: 1rem;
    right: 1.25rem;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.125rem;
    cursor: pointer;
    padding: 0.3125rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
    width: 1.875rem;
    height: 1.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.view-update-header .btn-close-modal:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
}

/* Responsive */
@media (max-width: 768px) {
    .updates-container {
        padding: 1rem;
    }
    
    .updates-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .updates-filter-toggles {
        width: 100%;
        justify-content: center;
        margin: 0.5rem 0;
    }
    
    .updates-filter-toggle {
        flex: 1;
        font-size: 0.7rem;
        padding: 0.3rem 0.4rem;
    }
    
    .updates-grid {
        gap: 1rem;
    }
    
    .update-card {
        max-width: 100%;
    }
    
    
    .updates-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .updates-header-actions {
        width: 100%;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .updates-search-container {
        width: 100%;
    }
    
    .updates-search-input {
        width: 100%;
    }
    
    .btn-add-update {
        width: 100%;
        justify-content: center;
    }
    
    .update-card-left {
        flex-direction: row;
        align-items: center;
        gap: 0.5rem;
    }
    
    .update-icon {
        width: 3rem;
        height: 3rem;
    }
    
    .update-username {
        max-width: none;
        text-align: left;
    }
    
    .update-card-footer {
        position: relative;
        top: auto;
        right: auto;
        width: 100%;
        justify-content: flex-end;
        margin-top: 0.5rem;
    }
    
    .update-card-title {
        padding-right: 0;
    }
    
    .update-modal-content {
        padding: 1.5rem;
    }
    
    .view-update-modal {
        padding: 1.5rem 0.75rem;
    }
    
    .view-update-modal-content {
        max-width: 100%;
        max-height: calc(100vh - 3rem);
        border-radius: 0.75rem;
    }
    
    .view-update-header {
        padding: 1rem 1.25rem 0.875rem;
    }
    
    .view-update-header h2 {
        font-size: 1rem;
        padding-right: 2rem;
        margin-bottom: 0.625rem;
    }
    
    .view-update-content {
        padding: 1rem 1.25rem 0.5rem;
        font-size: 0.8125rem;
        line-height: 1.6;
    }
    
    .view-update-meta {
        gap: 0.875rem;
        font-size: 0.6875rem;
    }
    
    .view-update-attachment {
        margin-top: 0.375rem;
        padding-top: 0.5rem;
    }
    
    .view-update-attachment h4 {
        font-size: 0.75rem;
        margin: 4px 0 0.375rem 0;
    }
    
    .view-update-attachment a {
        padding: 0.4375rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .view-update-header .btn-close-modal {
        top: 0.875rem;
        right: 1rem;
        width: 1.75rem;
        height: 1.75rem;
        font-size: 1rem;
    }
}

/* Pagination Styles */
.updates-pagination {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 2rem;
    padding: 0.625rem 1rem;
    background: rgba(30, 41, 59, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    backdrop-filter: blur(8px);
}

.updates-pagination-info {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
    flex: 1;
    pointer-events: none;
}

.updates-pagination-text {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
}

.pagination-number {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

.updates-pagination-rows {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
    justify-content: flex-end;
    pointer-events: auto;
    position: relative;
    z-index: 1;
}

.pagination-label {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
}

.pagination-select {
    padding: 0.375rem 2rem 0.375rem 0.5rem;
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.875rem;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 0.75rem;
    transition: all 0.3s ease;
}

.pagination-select:focus {
    outline: none;
    border-color: rgba(139, 92, 246, 0.5);
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.updates-pagination-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex: 1;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    pointer-events: auto;
}

.pagination-btn {
    padding: 0.375rem 0.75rem;
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.25rem;
    position: relative;
    z-index: 11;
    pointer-events: auto;
}

.pagination-btn:hover:not(:disabled) {
    background: rgba(30, 41, 59, 0.95);
    border-color: rgba(139, 92, 246, 0.3);
    color: #8b5cf6;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-numbers {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pagination-page-btn {
    padding: 0.375rem 0.75rem;
    min-width: 2.5rem;
    height: 2.25rem;
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    z-index: 11;
    pointer-events: auto;
}

.pagination-page-btn:hover {
    background: rgba(30, 41, 59, 0.95);
    border-color: rgba(139, 92, 246, 0.3);
    color: #8b5cf6;
}

.pagination-page-btn.active {
    background: rgba(139, 92, 246, 0.5);
    border-color: rgba(139, 92, 246, 0.5);
    color: #fff;
}

.pagination-ellipsis {
    padding: 0 0.5rem;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .updates-pagination {
        flex-direction: column;
        padding: 0.75rem;
        gap: 0.75rem;
    }
    
    .updates-pagination-info {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .updates-pagination-controls {
        position: static;
        transform: none;
        width: 100%;
        justify-content: center;
        flex: none;
    }
    
    .updates-pagination-rows {
        justify-content: flex-start;
        flex: none;
    }
}
</style>

<div class="updates-container">
    <div class="updates-header">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1>Updates</h1>
                <?php if ($is_admin || $is_manager): ?>
                <div class="updates-client-filter">
                    <select id="clientFilterSelect" class="updates-client-select" onchange="handleClientFilterChange()">
                        <option value="" style="display: none;">All Updates</option>
                    </select>
                    <i class="fas fa-chevron-down" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); color: #8b5cf6; pointer-events: none; font-size: 0.875rem; z-index: 2;"></i>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="updates-filter-toggles">
            <button type="button" class="updates-filter-toggle active" data-filter="all" onclick="setUpdateFilter('all')">
                All
            </button>
            <button type="button" class="updates-filter-toggle" data-filter="my" onclick="setUpdateFilter('my')">
                My
            </button>
            <?php if ($is_admin || $is_manager): ?>
                <button type="button" class="updates-filter-toggle" data-filter="client" onclick="setUpdateFilter('client')">
                    Client
                </button>
            <?php else: ?>
                <button type="button" class="updates-filter-toggle" data-filter="team" onclick="setUpdateFilter('team')">
                    Team
                </button>
            <?php endif; ?>
        </div>
        <div class="updates-header-actions">
        <div class="updates-search-container">
            <div class="updates-search-wrapper">
                <button type="button" class="updates-search-icon-btn" title="Search updates">
                    <i class="fas fa-search"></i>
                </button>
                <input 
                    type="text" 
                    id="updatesSearchInput" 
                    class="updates-search-input" 
                    placeholder="Search updates..."
                    autocomplete="off"
                >
            </div>
            </div>
            <?php if ($is_admin || $is_manager || $is_client): ?>
            <button class="btn-add-update" id="writeUpdateBtn" onclick="openAddUpdateModal()">
                <i class="fas fa-pen"></i>
                <span>Add Update</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="updatesGrid" class="updates-grid"></div>

    <!-- Pagination -->
    <div id="updatesPagination" class="updates-pagination" style="display: none; position: relative;">
        <div class="updates-pagination-controls">
            <button id="prevPageBtn" class="pagination-btn" onclick="goToPreviousPage()" disabled>
                <i class="fas fa-chevron-left"></i>
            </button>
            <div id="pageNumbers" class="pagination-numbers"></div>
            <button id="nextPageBtn" class="pagination-btn" onclick="goToNextPage()" disabled>
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        <div class="updates-pagination-info">
            <div class="updates-pagination-text">
                <span>Showing</span>
                <span id="paginationStart" class="pagination-number">0</span>
                <span>to</span>
                <span id="paginationEnd" class="pagination-number">0</span>
                <span>of</span>
                <span id="paginationTotal" class="pagination-number">0</span>
                <span>items</span>
            </div>
        </div>
        <div class="updates-pagination-rows">
            <label class="pagination-label">Rows per page:</label>
            <select id="itemsPerPageSelect" class="pagination-select">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>
</div>


<!-- Add/Edit Update Modal (Admin, Manager & Client) -->
<?php if ($is_admin || $is_manager || $is_client): ?>
<div id="updateModal" class="update-modal" onclick="closeModalOnBackdrop(event)">
    <div class="update-modal-content" onclick="event.stopPropagation()">
        <div class="update-modal-header">
            <h2 id="modalTitle">Write Update</h2>
            <button class="btn-close-modal" onclick="closeUpdateModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="updateForm">
            <input type="hidden" id="updateId" name="update_id">
            
            <?php if ($is_admin || $is_manager): ?>
            <div class="form-group">
                <label class="form-label" for="targetClientAccount">
                    Client Account
                </label>
                <select class="form-control" id="targetClientAccount" name="target_client_account" onchange="handleClientAccountChange()">
                    <option value="">Select Client Account (Optional)</option>
                </select>
            </div>
            
            <div class="form-group" id="targetClientUserGroup" style="display: none;">
                <label class="form-label" id="targetClientUsersLabel">
                    Client Users <span class="required">*</span>
                </label>
                <div id="targetClientUsersContainer" style="max-height: 200px; overflow-y: auto; background: rgba(6, 8, 14, 0.5); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 0.5rem; padding: 0.75rem;">
                    <!-- Client users checkboxes will be populated here -->
                </div>
                <p id="targetClientUsersMessage" style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.5); margin-top: 0.5rem; display: none;">
                    Please select at least one client user
                </p>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label" for="updateTitle">
                    Title <span class="required">*</span>
                </label>
                <input type="text" class="form-control" id="updateTitle" name="title" required placeholder="Enter update title...">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="updateContent">
                    Description <span class="required">*</span>
                </label>
                <div style="position: relative;">
                    <textarea class="form-control" id="updateContent" name="content" required placeholder="Enter update details..." style="padding-left: 3rem; padding-bottom: 3rem;"></textarea>
                    <button type="button" class="btn-attachment-pin" id="attachmentPinBtn" onclick="document.getElementById('updateAttachment').click()" title="Attach Documents & Media">
                        <i class="fas fa-paperclip"></i>
                    </button>
                </div>
                <div id="attachmentInfo" style="margin-top: 0.5rem; display: none; padding: 0.5rem; background: rgba(139, 92, 246, 0.1); border-radius: 0.5rem; border: 1px solid rgba(139, 92, 246, 0.3);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #a78bfa;">
                            <i class="fas fa-file"></i>
                            <span id="attachmentFileName"></span>
                            <span style="font-size: 0.75rem; color: rgba(255,255,255,0.6);" id="attachmentFileSize"></span>
                        </div>
                        <button type="button" onclick="removeAttachment()" style="background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 0.375rem; padding: 0.25rem 0.5rem; cursor: pointer; font-size: 0.75rem;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <input type="file" id="updateAttachment" name="attachment" accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar,.jpg,.jpeg,.png,.gif,.bmp,.webp,.mp4,.avi,.mov,.wmv,.flv,.mp3,.wav,.m4a,.aac" style="display: none;">
                <small class="text-muted" style="display: block; margin-top: 0.5rem; font-size: 0.75rem; color: rgba(255,255,255,0.6);">
                    <i class="fas fa-info-circle"></i> Max file size: 50 MB
                </small>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="clearUpdateForm()">Clear</button>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- View Update Modal -->
<div id="viewUpdateModal" class="view-update-modal" onclick="closeViewModalOnBackdrop(event)">
    <div class="view-update-modal-content" onclick="event.stopPropagation()">
        <div class="view-update-header">
            <h2 id="viewUpdateTitle"></h2>
            <div class="view-update-meta">
                <span id="viewUpdateAuthor"></span>
                <span id="viewUpdateDate"></span>
            </div>
            <button class="btn-close-modal" onclick="closeViewUpdateModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="view-update-content" id="viewUpdateContent"></div>
    </div>
</div>

<script>
// Initialize Vanilla Tilt on update cards
function initTilt() {
    const cards = document.querySelectorAll('.update-card');
    cards.forEach(card => {
        if (!card.hasAttribute('data-tilt-initialized')) {
            VanillaTilt.init(card, {
                max: 15,
                speed: 800,
                glare: true,
                'max-glare': 0.3,
                scale: 1.05,
                perspective: 1000,
                transition: true,
                'mouse-event-element': null,
                reset: true,
                easing: 'cubic-bezier(.03,.98,.52,.99)'
            });
            card.setAttribute('data-tilt-initialized', 'true');
        }
    });
}

// Load updates
// Store all updates globally for search functionality
let allUpdates = [];
let currentFilter = 'all'; // 'all', 'my', 'team'
let filteredUpdates = []; // Store filtered updates for pagination
let currentPage = 1;
let itemsPerPage = 10;
let selectedClientAccountId = '';
let selectedClientUserId = '';

function loadUpdates() {
    const grid = document.getElementById('updatesGrid');
    grid.innerHTML = '<div class="loading"><p>Loading updates...</p></div>';
    
    // Build URL with optional filters
    let url = '../ajax/updates_handler.php?action=get_updates';
    if (selectedClientUserId) {
        url += `&client_user_id=${selectedClientUserId}`;
    } else if (selectedClientAccountId) {
        url += `&client_account_id=${selectedClientAccountId}`;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allUpdates = data.updates; // Store all updates
                applyFilters(); // This will call displayPaginatedUpdates
            } else {
                allUpdates = [];
                filteredUpdates = [];
                grid.innerHTML = `<div class="updates-empty"><i class="fas fa-bullhorn"></i><h3>No updates available</h3><p>${data.message || 'There are no updates to display at this time.'}</p></div>`;
                document.getElementById('updatesPagination').style.display = 'none';
            }
        })
        .catch(error => {
            allUpdates = [];
            filteredUpdates = [];
            grid.innerHTML = '<div class="updates-empty"><i class="fas fa-exclamation-triangle"></i><h3>Error</h3><p>Failed to load updates. Please try again.</p></div>';
            document.getElementById('updatesPagination').style.display = 'none';
        });
}

// Load client accounts and users for dropdown
function loadClientFilter() {
    const clientFilterSelect = document.getElementById('clientFilterSelect');
    if (!clientFilterSelect) return;
    
    fetch('../ajax/updates_handler.php?action=get_client_accounts')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                clientFilterSelect.innerHTML = '<option value="">All Updates</option>';
                
                // Load all accounts and their users
                const accountPromises = data.client_accounts.map(account => {
                    return fetch(`../ajax/updates_handler.php?action=get_client_users&client_account_id=${account.id}`)
                        .then(response => response.json())
                        .then(userData => {
                            return { account, users: userData.success ? userData.client_users : [] };
                        })
                        .catch(error => {
                            return { account, users: [] };
                        });
                });
                
                Promise.all(accountPromises).then(results => {
                    results.forEach(({ account, users }) => {
                        // Add account option
                        const accountOption = document.createElement('option');
                        accountOption.value = `account_${account.id}`;
                        accountOption.textContent = account.name || account.username;
                        clientFilterSelect.appendChild(accountOption);
                        
                        // Add user options under this account (indented)
                        users.forEach(user => {
                            const userOption = document.createElement('option');
                            userOption.value = `user_${user.id}`;
                            userOption.textContent = `  └─ ${user.name || user.username}`;
                            clientFilterSelect.appendChild(userOption);
                        });
                    });
                });
            }
        })
        .catch(error => {
        });
}

// Handle client filter change
function handleClientFilterChange() {
    const clientFilterSelect = document.getElementById('clientFilterSelect');
    if (!clientFilterSelect) return;
    
    const selectedValue = clientFilterSelect.value;
    
    // Reset filters
    selectedClientAccountId = '';
    selectedClientUserId = '';
    
    if (selectedValue) {
        if (selectedValue.startsWith('account_')) {
            // Client account selected
            selectedClientAccountId = selectedValue.replace('account_', '');
        } else if (selectedValue.startsWith('user_')) {
            // Client user selected
            selectedClientUserId = selectedValue.replace('user_', '');
        }
    }
    
    // Reload updates with new filter
    loadUpdates();
}

// Set update filter
function setUpdateFilter(filter) {
    currentFilter = filter;
    
    // Update toggle buttons
    document.querySelectorAll('.updates-filter-toggle').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.updates-filter-toggle[data-filter="${filter}"]`).classList.add('active');
    
    // Apply filters
    applyFilters();
}

// Apply both filter and search
function applyFilters() {
    const searchInput = document.getElementById('updatesSearchInput');
    const searchQuery = searchInput ? searchInput.value.trim() : '';
    
    let filtered = allUpdates;
    
    // Apply filter (all, my, team, client)
    const currentUserId = <?php echo $user_id ?? 'null'; ?>;
    const isAdminOrManager = <?php echo ($is_admin || $is_manager) ? 'true' : 'false'; ?>;
    
    if (currentFilter === 'my' && currentUserId) {
        filtered = filtered.filter(update => 
            update.created_by && parseInt(update.created_by) === parseInt(currentUserId)
        );
    } else if (currentFilter === 'team' && currentUserId) {
        // For clients: show updates from other users (team members)
        filtered = filtered.filter(update => 
            update.created_by && parseInt(update.created_by) !== parseInt(currentUserId)
        );
    } else if (currentFilter === 'client' && isAdminOrManager) {
        // For admin/manager: show only updates created by clients
        filtered = filtered.filter(update => {
            return update.created_by_user_type === 'client';
        });
    }
    
    // Apply search query
    if (searchQuery) {
        const searchTerm = searchQuery.toLowerCase();
        filtered = filtered.filter(update => {
            const title = (update.title || '').toLowerCase();
            const content = (update.content || '').toLowerCase();
            const authorName = (update.created_by_name || '').toLowerCase();
            
            return title.includes(searchTerm) || 
                   content.includes(searchTerm) || 
                   authorName.includes(searchTerm);
        });
    }
    
    // Store filtered updates and reset to page 1
    filteredUpdates = filtered;
    currentPage = 1;
    
    // Display paginated updates
    displayPaginatedUpdates();
}

// Search updates
function searchUpdates(query) {
    applyFilters();
}

// Initialize search functionality
function initSearch() {
    const searchInput = document.getElementById('updatesSearchInput');
    const searchIconBtn = document.querySelector('.updates-search-icon-btn');
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value;
            
            // Maintain expanded state
            if (query.trim() !== '') {
                searchInput.classList.add('has-content');
            } else {
                searchInput.classList.remove('has-content');
            }
            
            // Apply filters (which includes search)
            applyFilters();
        });
        
        // Handle Enter key
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        
        // Focus input when icon button is clicked
        if (searchIconBtn) {
            searchIconBtn.addEventListener('click', function() {
                searchInput.focus();
            });
        }
        
        // Keep input expanded when focused
        searchInput.addEventListener('focus', function() {
            searchInput.classList.add('has-content');
        });
    }
}

// Display paginated updates
function displayPaginatedUpdates() {
    const totalItems = filteredUpdates.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    
    // Calculate pagination
    const start = (currentPage - 1) * itemsPerPage;
    const end = Math.min(start + itemsPerPage, totalItems);
    const paginatedItems = filteredUpdates.slice(start, end);
    
    // Update pagination UI
    updatePaginationUI(totalItems, start, end, totalPages);

// Display updates
    displayUpdates(paginatedItems, totalItems === 0);
}

// Display updates
function displayUpdates(updates, isEmpty = false) {
    const grid = document.getElementById('updatesGrid');
    
    if (isEmpty || !updates || updates.length === 0) {
        grid.innerHTML = '<div class="updates-empty"><i class="fas fa-bullhorn"></i><h3>No updates available</h3><p>There are no updates to display at this time.</p></div>';
        document.getElementById('updatesPagination').style.display = 'none';
        return;
    }
    
    // Show pagination if there are items
    if (filteredUpdates.length > 0) {
        document.getElementById('updatesPagination').style.display = 'flex';
    }
    
    const currentUserId = <?php echo $user_id ?? 'null'; ?>;
    const canEdit = <?php echo ($is_admin || $is_manager || $is_client) ? 'true' : 'false'; ?>;
    
    grid.innerHTML = updates.map(update => {
        const updateTimestamp = formatTimestamp(update.created_at);
        const fullContent = update.content || '';
        const needsReadMore = fullContent.length > 150; // Approximate 2 lines
        const hasAttachment = update.attachment_path && update.attachment_path.trim() !== '';
        const isOwnUpdate = currentUserId && update.created_by && parseInt(update.created_by) === parseInt(currentUserId);
        // Strict ownership: Only the creator can edit/delete their own updates (applies to all roles)
        const canEditThisUpdate = canEdit && isOwnUpdate;
        
        // Get profile photo path
        const fullUserName = update.created_by_name || 'Unknown';
        const userName = fullUserName.split(' ')[0]; // Get only first name
        const userInitial = userName.charAt(0).toUpperCase();
        let profilePhotoHtml = '';
        
        // Try to get profile photo
        if (update.created_by_photo && update.created_by_photo.trim() !== '') {
            const photoPath = `../assets/uploads/profile_photos/${escapeHtml(update.created_by_photo)}`;
            profilePhotoHtml = `<img src="${photoPath}" alt="${escapeHtml(fullUserName)}" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">`;
        } else if (update.created_by) {
            // Check legacy path
            const legacyPath = `../assets/uploads/profile_photos/user_${update.created_by}.png`;
            profilePhotoHtml = `<img src="${legacyPath}" alt="${escapeHtml(fullUserName)}" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">`;
        }
        
        // Fallback avatar placeholder (hidden if image exists)
        const avatarPlaceholder = `<div class="avatar-placeholder" style="display: ${profilePhotoHtml ? 'none' : 'flex'};">${userInitial}</div>`;
        
        const updateTitle = update.title || '';
        
        return `
            <div class="update-card" data-update-id="${update.id}" onclick="viewUpdate(${update.id}, event)">
                <div class="update-card-left">
                    <div class="update-icon">
                        ${profilePhotoHtml}
                        ${avatarPlaceholder}
                    </div>
                    <div class="update-username">${escapeHtml(userName)}</div>
                </div>
                <div class="update-message-bubble">
                    <div class="update-card-title">
                        <h3>${updateTitle ? escapeHtml(updateTitle) : 'Update'} ${hasAttachment ? '<i class="fas fa-paperclip" style="color: #a78bfa; font-size: 0.625rem;"></i>' : ''}</h3>
                    </div>
                    <div class="update-card-body">
                        <div class="update-content-wrapper">
                            <div class="update-content">
                                <span class="update-content-text ${needsReadMore ? 'truncated' : ''}">${escapeHtml(fullContent)}</span>
                                ${needsReadMore ? '<span class="read-more-link" onclick="event.stopPropagation(); viewUpdate(' + update.id + ', event)">read more</span>' : ''}
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.125rem;">
                            <div style="flex: 1;"></div>
                            <div class="update-meta-item">
                                <span>${updateTimestamp}</span>
                            </div>
                        </div>
                    </div>
                    <div class="update-card-footer" onclick="event.stopPropagation()">
                        ${canEditThisUpdate ? `
                            <button class="btn-update-action btn-edit" onclick="editUpdate(${update.id})" title="Edit Update">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-update-action btn-delete" onclick="deleteUpdate(${update.id})" title="Delete Update">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    // Tilt effect disabled for compact rectangles
}

// Update pagination UI
function updatePaginationUI(totalItems, start, end, totalPages) {
    // Update info text
    document.getElementById('paginationStart').textContent = totalItems > 0 ? start + 1 : 0;
    document.getElementById('paginationEnd').textContent = end;
    document.getElementById('paginationTotal').textContent = totalItems;
    
    // Update buttons
    document.getElementById('prevPageBtn').disabled = currentPage === 1;
    document.getElementById('nextPageBtn').disabled = currentPage === totalPages || totalPages === 0;
    
    // Update page numbers
    const pageNumbersContainer = document.getElementById('pageNumbers');
    pageNumbersContainer.innerHTML = '';
    
    if (totalPages === 0) return;
    
    const pages = getPageNumbers(totalPages, currentPage);
    pages.forEach(page => {
        if (page === '...') {
            const span = document.createElement('span');
            span.className = 'pagination-ellipsis';
            span.textContent = '...';
            pageNumbersContainer.appendChild(span);
        } else {
            const button = document.createElement('button');
            button.className = 'pagination-page-btn';
            if (currentPage === page) {
                button.classList.add('active');
            }
            button.textContent = page;
            button.onclick = () => goToPage(page);
            pageNumbersContainer.appendChild(button);
        }
    });
}

// Get page numbers with ellipsis
function getPageNumbers(total, current) {
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
}

// Pagination functions
function goToPage(page) {
    const totalPages = Math.ceil(filteredUpdates.length / itemsPerPage);
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        displayPaginatedUpdates();
        // Scroll to top of updates
        document.getElementById('updatesGrid').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function goToPreviousPage() {
    if (currentPage > 1) {
        goToPage(currentPage - 1);
    }
}

function goToNextPage() {
    const totalPages = Math.ceil(filteredUpdates.length / itemsPerPage);
    if (currentPage < totalPages) {
        goToPage(currentPage + 1);
    }
}

function handleItemsPerPageChange() {
    itemsPerPage = parseInt(document.getElementById('itemsPerPageSelect').value);
    currentPage = 1; // Reset to first page
    displayPaginatedUpdates();
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
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

// Format relative time (timestamp)
function formatTimestamp(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) {
        return 'just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes}m ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours}h ago`;
    } else if (diffInSeconds < 604800) {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days}d ago`;
    } else if (diffInSeconds < 2592000) {
        const weeks = Math.floor(diffInSeconds / 604800);
        return `${weeks}w ago`;
    } else if (diffInSeconds < 31536000) {
        const months = Math.floor(diffInSeconds / 2592000);
        return `${months}mo ago`;
    } else {
        const years = Math.floor(diffInSeconds / 31536000);
        return `${years}y ago`;
    }
}

// View update
function viewUpdate(updateId, event) {
    fetch(`../ajax/updates_handler.php?action=get_update&id=${updateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const update = data.update;
                const updateTitle = update.title || 'Update';
                document.getElementById('viewUpdateTitle').textContent = updateTitle;
                document.getElementById('viewUpdateAuthor').innerHTML = `<i class="fas fa-user"></i> ${escapeHtml(update.created_by_name || 'Unknown')}`;
                document.getElementById('viewUpdateDate').innerHTML = `<i class="fas fa-calendar"></i> ${formatDate(update.created_at)}`;
                
                let contentHtml = `<div style="margin-bottom: 0; padding-bottom: 0;">${escapeHtml(update.content)}</div>`;
                
                // Add attachment if exists
                if (update.attachment_path && update.attachment_path.trim() !== '') {
                    const attachmentName = update.attachment_name || update.attachment_path.split('/').pop() || 'Attachment';
                    contentHtml += `<div class="view-update-attachment"><h4><i class="fas fa-paperclip"></i> Attachment</h4><a href="../ajax/updates_handler.php?action=download_attachment&id=${update.id}"><i class="fas fa-download"></i> ${escapeHtml(attachmentName)}</a></div>`;
                }
                
                document.getElementById('viewUpdateContent').innerHTML = contentHtml;
                
                // Position modal near the clicked update bubble
                const modal = document.getElementById('viewUpdateModal');
                const modalContent = modal.querySelector('.view-update-modal-content');
                
                // Get the clicked update card position
                let targetElement = null;
                if (event && event.target) {
                    // Find the update card element
                    targetElement = event.target.closest('.update-card');
                }
                
                // First, make modal visible (but off-screen) to get its dimensions
                modalContent.style.visibility = 'hidden';
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                if (targetElement) {
                    const cardRect = targetElement.getBoundingClientRect();
                    const viewportHeight = window.innerHeight;
                    const viewportWidth = window.innerWidth;
                    const spacing = 15; // Space between card and modal
                    
                    // Get actual modal dimensions
                    const modalRect = modalContent.getBoundingClientRect();
                    const modalHeight = modalRect.height || 400;
                    const modalWidth = Math.min(modalRect.width || 560, viewportWidth - 40);
                    
                    // Calculate vertical position (try to place below the card first)
                    let topPosition = cardRect.bottom + spacing;
                    
                    // If modal would go below viewport, try placing it above the card
                    if (topPosition + modalHeight > viewportHeight - 20) {
                        const spaceAbove = cardRect.top;
                        const spaceBelow = viewportHeight - cardRect.bottom;
                        
                        // If more space above, place it above
                        if (spaceAbove > spaceBelow && spaceAbove >= modalHeight + spacing) {
                            topPosition = cardRect.top - modalHeight - spacing;
                        } else {
                            // Not enough space either way, center vertically but keep near the card
                            topPosition = Math.max(20, Math.min(cardRect.bottom - modalHeight / 2, viewportHeight - modalHeight - 20));
                        }
                    }
                    
                    // Ensure minimum top position
                    if (topPosition < 20) {
                        topPosition = 20;
                    }
                    
                    // Ensure maximum bottom position
                    if (topPosition + modalHeight > viewportHeight - 20) {
                        topPosition = viewportHeight - modalHeight - 20;
                    }
                    
                    // Center horizontally in the viewport (content area)
                    const leftPosition = (viewportWidth - modalWidth) / 2;
                    
                    // Set modal position
                    modalContent.style.position = 'absolute';
                    modalContent.style.top = topPosition + 'px';
                    modalContent.style.left = leftPosition + 'px';
                    modalContent.style.margin = '0';
                    modalContent.style.transform = 'none';
                    modalContent.style.maxWidth = modalWidth + 'px';
                    modalContent.style.visibility = 'visible';
                } else {
                    // Fallback: center the modal if we can't find the card
                    modalContent.style.position = '';
                    modalContent.style.top = '';
                    modalContent.style.left = '';
                    modalContent.style.margin = '';
                    modalContent.style.transform = '';
                    modalContent.style.maxWidth = '';
                    modalContent.style.visibility = 'visible';
                }
                
                // Final check to ensure modal is visible in viewport
                requestAnimationFrame(() => {
                    const finalRect = modalContent.getBoundingClientRect();
                    const viewportHeight = window.innerHeight;
                    if (finalRect.top < 0 || finalRect.bottom > viewportHeight) {
                        // Adjust if modal went out of bounds
                        const adjustedTop = Math.max(20, Math.min(finalRect.top, viewportHeight - finalRect.height - 20));
                        modalContent.style.top = adjustedTop + 'px';
                    }
                });
            } else {
                alert('Error loading update: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error loading update. Please try again.');
        });
}

// Remove attachment
function removeAttachment() {
    document.getElementById('updateAttachment').value = '';
    document.getElementById('attachmentInfo').style.display = 'none';
    document.getElementById('attachmentFileName').textContent = '';
    document.getElementById('attachmentFileSize').textContent = '';
}

// Edit update
function editUpdate(updateId) {
    const currentUserId = <?php echo $user_id ?? 'null'; ?>;
    
    fetch(`../ajax/updates_handler.php?action=get_update&id=${updateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const update = data.update;
                
                // Verify ownership before allowing edit
                if (currentUserId && update.created_by && parseInt(update.created_by) !== parseInt(currentUserId)) {
                    alert('You can only edit your own updates');
                    return;
                }
                
                document.getElementById('updateId').value = update.id;
                document.getElementById('updateTitle').value = update.title || '';
                document.getElementById('updateContent').value = update.content;
                document.getElementById('modalTitle').textContent = 'Edit Update';
                
                // Reset attachment
                document.getElementById('updateAttachment').value = '';
                document.getElementById('attachmentInfo').style.display = 'none';
                document.getElementById('attachmentFileName').textContent = '';
                document.getElementById('attachmentFileSize').textContent = '';
                
                // Populate client dropdowns if admin/manager and update has target_client_id
                <?php if ($is_admin || $is_manager): ?>
                populateClientDropdownsForEdit(update.target_client_id);
                <?php endif; ?>
                
                openAddUpdateModal();
            } else {
                alert('Error loading update: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error loading update. Please try again.');
        });
}

// Delete update
function deleteUpdate(updateId) {
    const currentUserId = <?php echo $user_id ?? 'null'; ?>;
    
    // First verify ownership
    fetch(`../ajax/updates_handler.php?action=get_update&id=${updateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const update = data.update;
                
                // Verify ownership before allowing delete
                if (currentUserId && update.created_by && parseInt(update.created_by) !== parseInt(currentUserId)) {
                    alert('You can only delete your own updates');
                    return;
                }
                
                // Ownership confirmed, proceed with delete
    if (!confirm('Are you sure you want to delete this update?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_update');
    formData.append('update_id', updateId);
    
                return fetch('../ajax/updates_handler.php', {
        method: 'POST',
        body: formData
                });
            } else {
                throw new Error(data.message || 'Failed to load update');
            }
    })
        .then(response => {
            if (response) {
                return response.json();
            }
        })
    .then(data => {
            if (data && data.success) {
            alert(data.message || 'Update deleted successfully!');
            loadUpdates();
            } else if (data) {
            alert('Error: ' + (data.message || 'Failed to delete update'));
        }
    })
    .catch(error => {
        alert('Error deleting update. Please try again.');
    });
}

// Attachment file handling
document.getElementById('updateAttachment')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const maxSize = 50 * 1024 * 1024; // 50MB
        if (file.size > maxSize) {
            alert(`File "${file.name}" exceeds 50MB limit. Please select a smaller file.`);
            this.value = ''; // Clear the input
            return;
        }
        
        const info = document.getElementById('attachmentInfo');
        const fileName = document.getElementById('attachmentFileName');
        const fileSize = document.getElementById('attachmentFileSize');
        
        info.style.display = 'block';
        fileName.textContent = escapeHtml(file.name);
        fileSize.textContent = `(${formatFileSize(file.size)})`;
    }
});


// Modal functions
function openAddUpdateModal() {
    document.getElementById('updateModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Load client accounts if not already loaded (Admin/Manager only)
    <?php if ($is_admin || $is_manager): ?>
    const accountSelect = document.getElementById('targetClientAccount');
    if (accountSelect && accountSelect.options.length <= 1) {
        loadClientAccountsForModal();
    }
    <?php endif; ?>
}

function clearUpdateForm() {
    document.getElementById('updateForm').reset();
    document.getElementById('updateId').value = '';
    document.getElementById('updateTitle').value = '';
    document.getElementById('updateContent').value = '';
    document.getElementById('attachmentInfo').style.display = 'none';
    document.getElementById('attachmentFileName').textContent = '';
    document.getElementById('attachmentFileSize').textContent = '';
    document.getElementById('updateAttachment').value = '';
    
    // Reset client dropdowns (Admin/Manager only)
    const accountSelect = document.getElementById('targetClientAccount');
    const usersContainer = document.getElementById('targetClientUsersContainer');
    const usersMessage = document.getElementById('targetClientUsersMessage');
    const userGroup = document.getElementById('targetClientUserGroup');
    
    if (accountSelect) {
        accountSelect.value = '';
    }
    if (usersContainer) {
        usersContainer.innerHTML = '';
    }
    if (usersMessage) {
        usersMessage.style.display = 'none';
    }
    if (userGroup) {
        userGroup.style.display = 'none';
    }
}

function closeUpdateModal() {
    document.getElementById('updateModal').classList.remove('active');
    clearUpdateForm();
    document.getElementById('modalTitle').textContent = 'Write Update';
    document.body.style.overflow = '';
}

function closeModalOnBackdrop(event) {
    if (event.target.id === 'updateModal') {
        closeUpdateModal();
    }
}

function closeViewUpdateModal() {
    const modal = document.getElementById('viewUpdateModal');
    const modalContent = modal.querySelector('.view-update-modal-content');
    
    // Reset modal position and styles
    modalContent.style.position = '';
    modalContent.style.top = '';
    modalContent.style.left = '';
    modalContent.style.margin = '';
    modalContent.style.transform = '';
    modalContent.style.maxWidth = '';
    modalContent.style.visibility = '';
    
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

function closeViewModalOnBackdrop(event) {
    if (event.target.id === 'viewUpdateModal') {
        closeViewUpdateModal();
    }
}

// Load client accounts for dropdown (Admin/Manager only)
function loadClientAccountsForModal() {
    const accountSelect = document.getElementById('targetClientAccount');
    
    if (!accountSelect) return;
    
    fetch('../ajax/updates_handler.php?action=get_client_accounts')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                accountSelect.innerHTML = '<option value="">Select Client Account (Optional)</option>';
                
                data.client_accounts.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = account.name || account.username;
                    accountSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
        });
}

// Populate client dropdowns when editing an update
function populateClientDropdownsForEdit(targetClientId) {
    const targetClientAccount = document.getElementById('targetClientAccount');
    const targetClientUsersContainer = document.getElementById('targetClientUsersContainer');
    const targetClientUserGroup = document.getElementById('targetClientUserGroup');
    
    if (!targetClientAccount) return;
    
    if (targetClientId) {
        // Find which client account this user belongs to
        fetch(`../ajax/updates_handler.php?action=get_client_accounts`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.client_accounts) {
                    // Load all accounts and their users to find the target
                    const accountPromises = data.client_accounts.map(account => {
                        return fetch(`../ajax/updates_handler.php?action=get_client_users&client_account_id=${account.id}`)
                            .then(response => response.json())
                            .then(userData => {
                                return { 
                                    account, 
                                    users: userData.success && userData.client_users ? userData.client_users : [] 
                                };
                            })
                            .catch(() => ({ account, users: [] }));
                    });
                    
                    Promise.all(accountPromises).then(results => {
                        let foundAccount = null;
                        let foundUser = null;
                        
                        // Search for the target client user
                        for (const { account, users } of results) {
                            // Check if target is the account itself
                            if (parseInt(account.id) === parseInt(targetClientId)) {
                                foundAccount = account;
                                break;
                            }
                            
                            // Check if target is a user under this account
                            const user = users.find(u => parseInt(u.id) === parseInt(targetClientId));
                            if (user) {
                                foundAccount = account;
                                foundUser = user;
                                break;
                            }
                        }
                        
                        // Populate dropdowns
                        if (foundAccount) {
                            targetClientAccount.value = foundAccount.id;
                            
                            if (foundUser) {
                                // Load users for this account first
                                handleClientAccountChange();
                                // Wait for checkboxes to populate, then check the target user
                                setTimeout(() => {
                                    const checkbox = document.getElementById(`target_client_user_${foundUser.id}`);
                                    if (checkbox) {
                                        checkbox.checked = true;
                                    }
                                }, 500);
                            } else {
                                // Target is the account itself, hide user dropdown
                                if (targetClientUserGroup) {
                                    targetClientUserGroup.style.display = 'none';
                                }
                                if (targetClientUsersContainer) {
                                    targetClientUsersContainer.innerHTML = '';
                                }
                            }
                        } else {
                            // Target not found, clear dropdowns
                            if (targetClientAccount) targetClientAccount.value = '';
                            if (targetClientUsersContainer) {
                                targetClientUsersContainer.innerHTML = '';
                            }
                            if (targetClientUserGroup) targetClientUserGroup.style.display = 'none';
                        }
                    })
                    .catch(error => {
                    });
                }
            })
            .catch(error => {
            });
    } else {
        // No target, clear dropdowns
        if (targetClientAccount) targetClientAccount.value = '';
        if (targetClientUsersContainer) {
            targetClientUsersContainer.innerHTML = '';
        }
        if (targetClientUserGroup) targetClientUserGroup.style.display = 'none';
    }
}

// Handle client account change - load users as checkboxes
function handleClientAccountChange() {
    const accountSelect = document.getElementById('targetClientAccount');
    const usersContainer = document.getElementById('targetClientUsersContainer');
    const usersMessage = document.getElementById('targetClientUsersMessage');
    const userGroup = document.getElementById('targetClientUserGroup');
    
    if (!accountSelect || !usersContainer || !userGroup) return;
    
    const accountId = accountSelect.value;
    
    if (accountId) {
        // Update label to show required
        const usersLabel = document.getElementById('targetClientUsersLabel');
        if (usersLabel) {
            usersLabel.innerHTML = 'Client Users <span class="required">*</span>';
        }
        
        fetch(`../ajax/updates_handler.php?action=get_client_users&client_account_id=${accountId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.client_users && data.client_users.length > 0) {
                    usersContainer.innerHTML = '';
                    
                    data.client_users.forEach(user => {
                        const label = document.createElement('label');
                        label.style.cssText = 'display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 0.375rem; transition: background 0.2s;';
                        label.onmouseover = function() { this.style.background = 'rgba(255, 255, 255, 0.05)'; };
                        label.onmouseout = function() { this.style.background = 'transparent'; };
                        
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.name = 'target_client_user_ids[]';
                        checkbox.value = user.id;
                        checkbox.id = `target_client_user_${user.id}`;
                        checkbox.style.cssText = 'width: 1rem; height: 1rem; cursor: pointer; accent-color: #8b5cf6;';
                        
                        const span = document.createElement('span');
                        span.textContent = user.name || user.username;
                        span.style.cssText = 'color: rgba(255, 255, 255, 0.9); font-size: 0.875rem;';
                        
                        label.appendChild(checkbox);
                        label.appendChild(span);
                        usersContainer.appendChild(label);
                    });
                    
                    userGroup.style.display = 'block';
                    usersMessage.style.display = 'none';
                    
                    // Add event listeners to checkboxes to hide validation message when checked
                    const checkboxes = usersContainer.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            const checkedCount = usersContainer.querySelectorAll('input[type="checkbox"]:checked').length;
                            if (checkedCount > 0 && usersMessage) {
                                usersMessage.style.display = 'none';
                            }
                        });
                    });
                } else {
                    usersContainer.innerHTML = '<p style="color: rgba(255, 255, 255, 0.5); font-size: 0.875rem; font-style: italic; padding: 0.5rem;">No users found for this account</p>';
                    userGroup.style.display = 'block';
                    usersMessage.style.display = 'none';
                }
            })
            .catch(error => {
                usersContainer.innerHTML = '<p style="color: rgba(255, 255, 255, 0.5); font-size: 0.875rem; font-style: italic; padding: 0.5rem;">Error loading users</p>';
                userGroup.style.display = 'block';
                usersMessage.style.display = 'none';
            });
    } else {
        // Update label to show optional
        const usersLabel = document.getElementById('targetClientUsersLabel');
        if (usersLabel) {
            usersLabel.innerHTML = 'Client Users <span style="color: rgba(255, 255, 255, 0.5); font-size: 0.75rem;">(Optional)</span>';
        }
        usersContainer.innerHTML = '';
        userGroup.style.display = 'none';
        usersMessage.style.display = 'none';
    }
}

// Form submission
document.getElementById('updateForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', document.getElementById('updateId').value ? 'update_update' : 'create_update');
    
    // Add target_client_id if client users are selected (Admin/Manager only)
    // Get all checked client user checkboxes
    const accountSelect = document.getElementById('targetClientAccount');
    const checkedUsers = document.querySelectorAll('input[name="target_client_user_ids[]"]:checked');
    const usersMessage = document.getElementById('targetClientUsersMessage');
    
    // Validate: if client account is selected, at least one user must be selected
    if (accountSelect && accountSelect.value && (!checkedUsers || checkedUsers.length === 0)) {
        if (usersMessage) {
            usersMessage.style.display = 'block';
        }
        e.preventDefault();
        alert('Please select at least one client user when a client account is selected.');
        return;
    }
    
    if (checkedUsers && checkedUsers.length > 0) {
        // For now, use the first selected user (backend currently supports single target_client_id)
        // If backend is updated to support multiple, we can send all IDs
        formData.append('target_client_id', checkedUsers[0].value);
    }
    
    // Hide validation message if users are selected
    if (usersMessage && checkedUsers && checkedUsers.length > 0) {
        usersMessage.style.display = 'none';
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Saving...';
    
    fetch('../ajax/updates_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Update saved successfully!');
            closeUpdateModal();
            loadUpdates();
        } else {
            alert('Error: ' + (data.message || 'Failed to save update'));
        }
    })
    .catch(error => {
        alert('Error saving update. Please try again.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUpdateModal();
        closeViewUpdateModal();
    }
});

// Load updates on page load
document.addEventListener('DOMContentLoaded', function() {
    loadUpdates();
    
    // Initialize search functionality
    initSearch();
    
    // Initialize items per page selector
    const itemsPerPageSelect = document.getElementById('itemsPerPageSelect');
    if (itemsPerPageSelect) {
        itemsPerPageSelect.addEventListener('change', handleItemsPerPageChange);
    }
    
    // Load client filter for managers/admins
    <?php if ($is_admin || $is_manager): ?>
    loadClientFilter();
    // Load client accounts for modal dropdown
    loadClientAccountsForModal();
    <?php endif; ?>
});

</script>

<?php require_once "../includes/footer.php";
?>
