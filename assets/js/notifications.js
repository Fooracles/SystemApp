/**
 * All Notifications System
 * Handles loading, displaying, and managing all types of notifications
 */

window.NotificationsManager = {
    audioEnabled: true,
    checkInterval: null,
    lastCheckTime: null,
    lastNotificationIds: [], // Track last seen notification IDs
    alertedNotificationIds: [], // Track notifications that have already triggered alerts
    isMenuOpen: false,
    isInitialLoad: true, // Track if this is the first load
    
    init: function() {
        if (typeof $ === 'undefined') {
            console.warn('jQuery not available for NotificationsManager');
            return;
        }
        
        // Initialize notification bell click handler
        $('#notificationsBell').on('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleNotificationsMenu();
        });
        
        // Mark all as read button
        $('#markAllReadBtn').on('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.markAllAsRead();
        });
        
        // Setup audio notification first
        this.setupAudioNotification();
        
        // Load notifications on page load (silent - no alerts)
        this.loadNotifications(true); // Silent load on init
        
        // Mark initial load as complete after a short delay
        setTimeout(() => {
            this.isInitialLoad = false;
        }, 2000);
        
        // Check for new notifications every 10 seconds
        this.startPolling();
        
        // Load alerted IDs from sessionStorage to persist across page loads
        this.loadAlertedNotificationIds();
        
        // Unlock audio on first user interaction (required by browser autoplay policies)
        this.unlockAudio();
    },
    
    toggleNotificationsMenu: function() {
        const $menu = $('#notificationsMenu');
        const $bell = $('#notificationsBell');
        const isOpen = $menu.hasClass('show');
        
        // Close all other dropdowns first (Forms, My Account, etc.)
        $('.dropdown-menu').not($menu).removeClass('show');
        $('.form-dropdown, .profile-dropdown, .day-special-dropdown').not($bell.closest('.day-special-dropdown')).removeClass('active');
        
        if (!isOpen) {
            $menu.addClass('show');
            $bell.closest('.day-special-dropdown').addClass('active');
            this.isMenuOpen = true;
            this.loadNotifications();
            
            // Position dropdown to prevent overflow
            this.positionDropdown($menu, $bell);
        } else {
            $menu.removeClass('show');
            $bell.closest('.day-special-dropdown').removeClass('active');
            this.isMenuOpen = false;
        }
    },
    
    positionDropdown: function($menu, $bell) {
        // Get bell position
        const bellOffset = $bell.offset();
        const bellWidth = $bell.outerWidth();
        const menuWidth = $menu.outerWidth();
        const viewportWidth = $(window).width();
        
        // Calculate right edge position
        const rightEdge = bellOffset.left + bellWidth;
        const spaceOnRight = viewportWidth - rightEdge;
        
        // If menu would overflow on right, adjust position
        if (spaceOnRight < menuWidth) {
            const overflow = menuWidth - spaceOnRight;
            const newLeft = Math.max(10, bellOffset.left - overflow);
            $menu.css({
                'position': 'fixed',
                'right': '10px',
                'left': 'auto',
                'transform': 'translateX(0)'
            });
        } else {
            // Reset to default positioning
            $menu.css({
                'position': 'absolute',
                'right': '0',
                'left': 'auto',
                'transform': 'translateX(0)'
            });
        }
    },
    
    loadNotifications: function(silent = false) {
        const $content = $('#notificationsContent');
        
        // Only show loading if not silent update
        if (!silent) {
            $content.html('<div class="notification-loading"><i class="fas fa-spinner fa-spin"></i> Loading notifications...</div>');
        }
        
        $.ajax({
            url: '../ajax/notifications_handler.php?action=get_notifications',
            method: 'GET',
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    const previousIds = [...this.lastNotificationIds];
                    const currentIds = response.notifications.map(n => n.id);
                    
                    // Check if there are new notifications (not seen before)
                    const newNotifications = response.notifications.filter(n => !previousIds.includes(n.id));
                    
                    // Filter to only new AND unread notifications that haven't been alerted yet
                    const newUnreadNotifications = newNotifications.filter(n => {
                        return !n.is_read && 
                               !this.alertedNotificationIds.includes(n.id) &&
                               !this.isInitialLoad; // Don't alert on initial page load
                    });
                    
                    // Update last seen IDs
                    this.lastNotificationIds = currentIds;
                    
                    // Cleanup old alerted IDs that are no longer in the list
                    this.cleanupOldAlertedIds(currentIds);
                    
                    // Mark new unread notifications as alerted
                    if (newUnreadNotifications.length > 0) {
                        newUnreadNotifications.forEach(n => {
                            if (!this.alertedNotificationIds.includes(n.id)) {
                                this.alertedNotificationIds.push(n.id);
                            }
                        });
                        // Save to sessionStorage
                        this.saveAlertedNotificationIds();
                    }
                    
                    // Display notifications with smooth animation
                    this.displayNotifications(response.notifications, newUnreadNotifications);
                    this.updateBadge(response.unread_count);
                    this.updateFooter(response.notifications.length, response.unread_count);
                    
                    // Play sound and show toast ONLY for new, unread notifications
                    if (newUnreadNotifications.length > 0 && !silent && !this.isInitialLoad) {
                        if (this.audioEnabled) {
                            this.playNotificationSound();
                        }
                        this.showNewNotificationToast(newUnreadNotifications);
                    }
                } else {
                    if (!silent) {
                        $content.html('<div class="notification-empty">Error loading notifications</div>');
                    }
                }
            },
            error: (xhr, status, error) => {
                console.error('Error loading notifications:', error);
                if (!silent) {
                    $content.html('<div class="notification-empty">Error loading notifications</div>');
                }
            }
        });
    },
    
    displayNotifications: function(notifications, newNotifications = []) {
        const $content = $('#notificationsContent');
        
        if (!notifications || notifications.length === 0) {
            $content.html('<div class="notification-empty"><i class="fas fa-bell-slash"></i> No notifications</div>');
            return;
        }
        
        // Track which notifications are new
        const newNotificationIds = new Set(newNotifications.map(n => n.id));
        
        let html = '';
        notifications.forEach((notif, index) => {
            const isNew = newNotificationIds.has(notif.id);
            const icon = this.getNotificationIcon(notif.type);
            const timestamp = this.formatTimestamp(notif.created_at);
            const isUnread = !notif.is_read;
            const actionButtons = this.getActionButtons(notif);
            
            // Get redirect URL for this notification
            const redirectUrl = this.getNotificationRedirectUrl(notif);
            const hasRedirect = redirectUrl && redirectUrl !== null && redirectUrl !== '' && redirectUrl !== 'null' && redirectUrl !== 'undefined';
            const clickableClass = hasRedirect ? 'notification-clickable' : '';
            
            // Debug: Log if notification should have redirect but doesn't
            if (notif.type && notif.type !== 'day_special' && !hasRedirect) {
                console.warn('Notification type should have redirect but doesn\'t:', {
                    type: notif.type,
                    redirectUrl: redirectUrl,
                    notification: notif
                });
            }
            
            // Special handling for day_special notifications
            const isDaySpecial = notif.type === 'day_special';
            const daySpecialClass = isDaySpecial ? 'notification-day-special' : '';
            const celebrationEmojis = isDaySpecial ? this.getCelebrationEmojis(notif.message) : '';
            
            // Add 'new' class for new notifications to trigger animation
            const newClass = isNew ? 'notification-new' : '';
            
            // Build title and message with anchor tags if redirectUrl exists
            const titleContent = (isDaySpecial ? celebrationEmojis + ' ' : '') + this.escapeHtml(notif.title);
            const messageContent = this.escapeHtml(notif.message);
            
            // Wrap title and message in anchor tags if redirectUrl exists
            const titleHtml = hasRedirect 
                ? `<a href="${this.escapeHtml(redirectUrl)}" class="notification-link" onclick="NotificationsManager.handleNotificationClick(event, ${notif.id}, ${isUnread ? 'true' : 'false'});">${titleContent}</a>`
                : `<div>${titleContent}</div>`;
            
            const messageHtml = hasRedirect 
                ? `<a href="${this.escapeHtml(redirectUrl)}" class="notification-link" onclick="NotificationsManager.handleNotificationClick(event, ${notif.id}, ${isUnread ? 'true' : 'false'});">${messageContent}</a>`
                : `<div>${messageContent}</div>`;
            
            // Determine which icon/button to show
            let iconHtml = '';
            if (hasRedirect) {
                // Show redirect button for notifications with redirect URLs
                iconHtml = `<button class="btn-notification-redirect notification-icon-btn" onclick="NotificationsManager.handleRedirectClick(event, ${notif.id}, ${isUnread ? 'true' : 'false'}, '${this.escapeHtml(redirectUrl)}');" title="Go to page"><i class="fas fa-external-link-alt"></i></button>`;
            } else {
                // Show bell icon for notifications without redirect URLs (like day_special)
                iconHtml = `<div class="notification-icon ${isDaySpecial ? 'notification-icon-celebration' : ''}">${isDaySpecial ? celebrationEmojis : `<i class="${icon}"></i>`}</div>`;
            }
            
            html += `
                <div class="notification-item ${isUnread ? 'unread' : ''} ${clickableClass} ${daySpecialClass} ${newClass}" 
                     data-id="${notif.id}" 
                     data-type="${notif.type}"
                     data-related-id="${notif.related_id || ''}"
                     data-related-type="${notif.related_type || ''}"
                     ${hasRedirect ? `data-redirect-url="${this.escapeHtml(redirectUrl)}"` : ''}>
                    ${iconHtml}
                    <div class="notification-content">
                        <div class="notification-title ${isDaySpecial ? 'notification-title-celebration' : ''}">
                            ${titleHtml}
                        </div>
                        <div class="notification-message ${isDaySpecial ? 'notification-message-celebration' : ''}">
                            ${messageHtml}
                        </div>
                        <div class="notification-footer-row">
                            <div class="notification-time">${timestamp}</div>
                            <div class="notification-actions-wrapper">
                                ${actionButtons}
                                ${isUnread ? '<button class="btn-mark-read" onclick="NotificationsManager.markAsRead(' + notif.id + '); event.stopPropagation();" title="Mark as read"><i class="fas fa-check"></i></button>' : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        // Store previous content for smooth transition
        const previousHtml = $content.html();
        $content.html(html);
        
        // Animate new notifications
        if (newNotifications.length > 0) {
            $content.find('.notification-new').each(function(index) {
                const $item = $(this);
                // Add slide-in animation
                $item.css({
                    'opacity': '0',
                    'transform': 'translateY(-10px)',
                    'transition': 'all 0.3s ease-out'
                });
                
                setTimeout(() => {
                    $item.css({
                        'opacity': '1',
                        'transform': 'translateY(0)'
                    });
                    
                    // Remove 'new' class after animation
                    setTimeout(() => {
                        $item.removeClass('notification-new');
                    }, 300);
                }, index * 50); // Stagger animations
            });
        }
        
        // Bind action button handlers
        this.bindActionHandlers();
    },
    
    getNotificationIcon: function(type) {
        const icons = {
            'task_delay': 'fas fa-exclamation-triangle text-warning',
            'meeting_request': 'fas fa-calendar-plus text-info',
            'meeting_approved': 'fas fa-calendar-check text-success',
            'meeting_rescheduled': 'fas fa-calendar-alt text-primary',
            'day_special': 'fas fa-birthday-cake text-danger',
            'notes_reminder': 'fas fa-sticky-note text-warning',
            'leave_request': 'fas fa-calendar-times text-info',
            'leave_approved': 'fas fa-check-circle text-success',
            'leave_rejected': 'fas fa-times-circle text-danger'
        };
        return icons[type] || 'fas fa-bell';
    },
    
    getCelebrationEmojis: function(message) {
        // Extract emojis from message or provide default celebration emojis
        // Check if message already contains emojis (including escaped HTML entities)
        const emojiRegex = /[\u{1F300}-\u{1F9FF}]|&#[0-9]+;|&[a-zA-Z]+;/gu;
        let existingEmojis = message.match(/[\u{1F300}-\u{1F9FF}]/gu);
        
        if (existingEmojis && existingEmojis.length > 0) {
            // Use emojis from message, but add some celebration ones
            return existingEmojis.slice(0, 3).join('') + ' 🎉';
        }
        
        // Default celebration emojis based on message content
        const lowerMessage = message.toLowerCase();
        if (lowerMessage.includes('birthday') || lowerMessage.includes('birth day')) {
            return '🎂🎉🎈';
        } else if (lowerMessage.includes('anniversary')) {
            return '💐🎊🎉';
        } else if (lowerMessage.includes('holiday') || lowerMessage.includes('festival')) {
            return '🎊🎉🎈';
        } else if (lowerMessage.includes('achievement') || lowerMessage.includes('milestone')) {
            return '🏆🎉✨';
        } else if (lowerMessage.includes('new year')) {
            return '🎊🎉🎆';
        } else if (lowerMessage.includes('christmas')) {
            return '🎄🎅🎁';
        } else if (lowerMessage.includes('eid') || lowerMessage.includes('ramadan')) {
            return '🌙✨🕌';
        } else if (lowerMessage.includes('diwali') || lowerMessage.includes('deepavali')) {
            return '🪔✨🎆';
        } else {
            // Generic celebration
            return '🎉🎊✨';
        }
    },
    
    getActionButtons: function(notif) {
        // Don't show action buttons if:
        // 1. action_required is false/0
        // 2. action_data is null/empty
        // 3. notification is already read (action was already performed)
        if (!notif.action_required || !notif.action_data || notif.is_read) {
            return '';
        }
        
        const actions = notif.action_data.actions || [];
        let buttons = '<div class="notification-actions">';
        
        actions.forEach((action) => {
            const icon = action.icon || 'fas fa-check';
            const color = action.color || 'primary';
            const tooltip = action.tooltip || action.label;
            
            // Properly escape related_id for onclick handler
            // related_id can be string (leave) or number (meeting), so we need to handle both
            let relatedIdParam;
            if (notif.related_id === null || notif.related_id === undefined) {
                relatedIdParam = 'null';
            } else if (typeof notif.related_id === 'string') {
                // Escape single quotes and backslashes in the string and wrap in quotes
                const escapedId = String(notif.related_id).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                relatedIdParam = `'${escapedId}'`;
            } else {
                // It's a number
                relatedIdParam = notif.related_id;
            }
            
            // Use data attributes instead of inline onclick for better security and reliability
            buttons += `
                <button class="btn-action btn-${color}" 
                        data-action-type="${this.escapeHtml(action.type)}"
                        data-notification-id="${notif.id}"
                        data-related-id="${notif.related_id !== null && notif.related_id !== undefined ? this.escapeHtml(String(notif.related_id)) : ''}"
                        title="${this.escapeHtml(tooltip)}">
                    <i class="${icon}"></i>
                </button>
            `;
        });
        
        buttons += '</div>';
        return buttons;
    },
    
    handleAction: function(actionType, notificationId, relatedId, buttonElement) {
        console.log('handleAction called:', { actionType, notificationId, relatedId });
        
        switch (actionType) {
            case 'approve_meeting':
                this.performAction(actionType, notificationId, relatedId, buttonElement);
                break;
            case 'approve_leave':
            case 'reject_leave':
                // Open confirmation modal (same as table action buttons)
                this.openLeaveActionModal(actionType, notificationId, relatedId);
                break;
            case 'reschedule_meeting':
                // Open reschedule modal instead of inline picker
                if (typeof window.openMeetingRescheduleModal === 'function') {
                    window.openMeetingRescheduleModal(relatedId, notificationId);
                } else {
                    // Fallback to inline picker if modal function not available
                    this.showReschedulePicker(notificationId, relatedId, buttonElement);
                }
                break;
            default:
                console.warn('Unknown action type:', actionType);
                alert('Unknown action type: ' + actionType);
        }
    },
    
    openLeaveActionModal: function(actionType, notificationId, relatedId) {
        console.log('openLeaveActionModal called:', { actionType, notificationId, relatedId });
        console.log('Current leaveManager state:', {
            exists: !!window.leaveManager,
            hasShowActionModal: !!(window.leaveManager && typeof window.leaveManager.showActionModal === 'function')
        });
        
        // Ensure leaveManager is available (initialize minimal version if needed)
        this.ensureLeaveManager();
        
        // Check if LeaveRequestManager is available
        const leaveManager = window.leaveManager || window.leaveRequestManager;
        
        console.log('After ensureLeaveManager:', {
            exists: !!leaveManager,
            hasShowActionModal: !!(leaveManager && typeof leaveManager.showActionModal === 'function'),
            type: typeof leaveManager
        });
        
        if (leaveManager && typeof leaveManager.showActionModal === 'function') {
            // Convert action type to modal action format
            const modalAction = actionType === 'approve_leave' ? 'Approve' : 'Reject';
            
            // Store notification context for later use
            leaveManager.currentNotificationId = notificationId;
            
            console.log('Opening modal with:', { relatedId, modalAction, notificationId });
            
            // Open modal with leave ID and action
            try {
                leaveManager.showActionModal(relatedId, modalAction, notificationId);
            } catch (error) {
                console.error('Error opening modal:', error);
                this.showInlineError('Error opening confirmation modal: ' + error.message);
            }
        } else {
            console.error('LeaveRequestManager not found or showActionModal not available', {
                leaveManager: leaveManager,
                hasShowActionModal: leaveManager ? typeof leaveManager.showActionModal : 'N/A'
            });
            this.showInlineError('Unable to open confirmation modal. Please refresh the page and try again.');
        }
    },
    
    ensureLeaveManager: function() {
        // If leaveManager doesn't exist, create minimal version
        if (!window.leaveManager && !window.leaveRequestManager) {
            console.log('Creating minimal LeaveRequestManager for notification actions');
            console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
            console.log('Modal element exists:', !!document.getElementById('confirmationModal'));
            
            // Create minimal LeaveRequestManager
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
                        alert('Confirmation modal not found. Please refresh the page.');
                        return;
                    }
                    
                    if (message) {
                        message.textContent = `Are you sure you want to ${action.toLowerCase()} this leave request?`;
                    }
                    if (noteInput) {
                        noteInput.value = '';
                    }
                    
                    // Use event delegation (same as full LeaveRequestManager)
                    // Store reference to this for use in event handlers
                    const self = this;
                    
                    // Remove old handlers and add new ones using event delegation on document
                    // This ensures it works even if buttons are recreated
                    const handleConfirmClick = function(e) {
                        if (e.target && (e.target.id === 'confirmAction' || e.target.closest('#confirmAction'))) {
                            e.preventDefault();
                            e.stopPropagation();
                            console.log('Confirm button clicked in minimal manager');
                            self.executeAction();
                            // Remove listener after use to prevent duplicates
                            document.removeEventListener('click', handleConfirmClick, true);
                        }
                    };
                    
                    const handleCancelClick = function(e) {
                        if (e.target && (e.target.id === 'cancelAction' || e.target.closest('#cancelAction'))) {
                            e.preventDefault();
                            e.stopPropagation();
                            console.log('Cancel button clicked in minimal manager');
                            self.closeModal();
                            // Remove listener after use to prevent duplicates
                            document.removeEventListener('click', handleCancelClick, true);
                        }
                    };
                    
                    // Add event listeners using capture phase
                    document.addEventListener('click', handleConfirmClick, true);
                    document.addEventListener('click', handleCancelClick, true);
                    
                    // Also try direct binding as backup
                    setTimeout(() => {
                        const confirmBtn = document.getElementById('confirmAction');
                        const cancelBtn = document.getElementById('cancelAction');
                        
                        if (confirmBtn && !confirmBtn.hasAttribute('data-bound-minimal')) {
                            confirmBtn.setAttribute('data-bound-minimal', 'true');
                            confirmBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                self.executeAction();
                            });
                        }
                        
                        if (cancelBtn && !cancelBtn.hasAttribute('data-bound-minimal')) {
                            cancelBtn.setAttribute('data-bound-minimal', 'true');
                            cancelBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                self.closeModal();
                            });
                        }
                    }, 100);
                    
                    // Show modal - Use jQuery Bootstrap 4 (project uses Bootstrap 4)
                    if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(modalElement).modal('show');
                    } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        // Fallback to Bootstrap 5 if available
                        const modal = new bootstrap.Modal(modalElement, {
                            backdrop: true,
                            keyboard: true,
                            focus: true
                        });
                        modal.show();
                    } else {
                        console.error('Bootstrap not available!');
                        alert('Bootstrap library not loaded. Please refresh the page.');
                        return;
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
                                // Use jQuery Bootstrap 4 (project uses Bootstrap 4)
                                if (typeof $ !== 'undefined' && $.fn.modal) {
                                    $(modalElement).modal('hide');
                                } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal && bootstrap.Modal.getInstance) {
                                    // Fallback to Bootstrap 5 if available
                                    try {
                                        const modal = bootstrap.Modal.getInstance(modalElement);
                                        if (modal) {
                                            modal.hide();
                                        }
                                    } catch (error) {
                                        console.log('Bootstrap 5 modal error, using manual fallback:', error);
                                        // Manual fallback
                                        modalElement.classList.remove('show');
                                        modalElement.style.display = 'none';
                                        const backdrop = document.querySelector('.modal-backdrop');
                                        if (backdrop) backdrop.remove();
                                        document.body.classList.remove('modal-open');
                                    }
                                }
                            }
                            
                            // Show success message
                            if (typeof window.NotificationManager !== 'undefined' && window.NotificationManager.showInlineSuccess) {
                                window.NotificationManager.showInlineSuccess(data.message || 'Action completed successfully!');
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
                            if (confirmBtn) {
                                confirmBtn.disabled = false;
                                confirmBtn.innerHTML = 'Save';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to save action. Please try again.');
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
                    console.log('closeModal called in minimal manager');
                    const modalElement = document.getElementById('confirmationModal');
                    if (modalElement) {
                        // Try Bootstrap 5 method first (if available)
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal && bootstrap.Modal.getInstance) {
                            try {
                                const modal = bootstrap.Modal.getInstance(modalElement);
                                if (modal) {
                                    modal.hide();
                                } else {
                                    // Create new modal instance and hide it
                                    const newModal = new bootstrap.Modal(modalElement);
                                    newModal.hide();
                                }
                            } catch (error) {
                                console.log('Bootstrap 5 modal error, using jQuery fallback:', error);
                                // Fallback to jQuery Bootstrap 4
                                if (typeof $ !== 'undefined' && $.fn.modal) {
                                    $(modalElement).modal('hide');
                                } else {
                                    // Manual fallback
                                    this.hideModalManually(modalElement);
                                }
                            }
                        } else if (typeof $ !== 'undefined' && $.fn.modal) {
                            // Use jQuery Bootstrap 4 (project uses Bootstrap 4)
                            $(modalElement).modal('hide');
                        } else {
                            // Manual fallback
                            this.hideModalManually(modalElement);
                        }
                    }
                    this.currentAction = null;
                    this.currentServiceNo = null;
                    this.currentNotificationId = null;
                },
                
                hideModalManually: function(modalElement) {
                    // Manual fallback: hide modal without Bootstrap
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
            };
            
            console.log('Minimal LeaveRequestManager created successfully');
        } else {
            console.log('LeaveRequestManager already exists, using existing instance');
        }
    },
    
    performAction: function(actionType, notificationId, relatedId, buttonElement) {
        console.log('Performing action:', actionType, 'for notification:', notificationId, 'related_id:', relatedId);
        
        // Show loading state on the button
        const $button = buttonElement ? $(buttonElement) : $(`.btn-action[data-notification-id="${notificationId}"][data-action-type="${actionType}"]`);
        let originalHTML = null;
        
        if ($button.length > 0) {
            originalHTML = $button.html();
            $button.prop('disabled', true);
            $button.html('<i class="fas fa-spinner fa-spin"></i>');
            $button.css('opacity', '0.6');
        }
        
        $.ajax({
            url: '../ajax/notification_actions.php',
            method: 'POST',
            data: {
                action: actionType,
                notification_id: notificationId,
                related_id: relatedId
            },
            dataType: 'json',
            success: (response) => {
                console.log('Action response:', response);
                
                if (response.success) {
                    this.playNotificationSound();
                    this.showInlineSuccess(response.message || 'Action completed successfully!');
                    
                    // Immediately update notification UI
                    this.updateNotificationAfterAction(notificationId, actionType, response);
                    
                    // Reload notifications to reflect changes (with slight delay to show immediate UI update)
                    setTimeout(() => {
                        this.loadNotifications();
                        this.updateUnreadCount();
                    }, 500);
                } else {
                    const errorMsg = response.error || 'Unknown error occurred';
                    console.error('Action failed:', errorMsg);
                    this.showInlineError(errorMsg);
                    
                    // Restore button
                    if ($button.length > 0 && originalHTML) {
                        $button.prop('disabled', false);
                        $button.html(originalHTML);
                        $button.css('opacity', '1');
                    }
                }
            },
            error: (xhr, status, error) => {
                console.error('AJAX Error performing action:', error);
                console.error('Response:', xhr.responseText);
                
                let errorMsg = 'Error performing action. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch (e) {
                    // Use default error message
                }
                
                this.showInlineError(errorMsg);
                
                // Restore button
                if ($button.length > 0 && originalHTML) {
                    $button.prop('disabled', false);
                    $button.html(originalHTML);
                    $button.css('opacity', '1');
                }
            }
        });
    },
    
    bindActionHandlers: function() {
        // Bind action button handlers using event delegation
        // Use .notifications-content as the container for better performance
        const self = this;
        $('.notifications-content').off('click', '.btn-action').on('click', '.btn-action', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const actionType = $btn.data('action-type');
            const notificationId = $btn.data('notification-id');
            let relatedId = $btn.data('related-id');
            
            // Handle empty string as null
            if (relatedId === '' || relatedId === undefined) {
                relatedId = null;
            }
            
            if (actionType && notificationId) {
                console.log('Action button clicked:', { actionType, notificationId, relatedId });
                self.handleAction(actionType, notificationId, relatedId, this);
            } else {
                console.error('Missing action data:', { actionType, notificationId, relatedId });
                self.showInlineError('Error: Missing action data. Please refresh and try again.');
            }
        });
        
        // Note: Redirect is now handled by anchor tags in the notification title and message
        // The handleNotificationClick function handles marking as read before redirect
        
        // Handle reschedule form submission
        $(document).off('submit', '.reschedule-form-inline').on('submit', '.reschedule-form-inline', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $form = $(this);
            const notificationId = $form.data('notification-id');
            const meetingId = $form.data('meeting-id');
            const dateTime = $form.find('input[type="datetime-local"]').val();
            
            if (!dateTime) {
                self.showInlineError('Please select a date and time');
                return;
            }
            
            self.performReschedule(notificationId, meetingId, dateTime, $form);
        });
        
        // Handle reschedule cancel
        $(document).off('click', '.cancel-reschedule').on('click', '.cancel-reschedule', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $picker = $(this).closest('.reschedule-picker-inline');
            $picker.slideUp(200, function() {
                $(this).remove();
            });
        });
        
        // Handle click on entire notification item (if it has redirect URL)
        $('.notifications-content').off('click', '.notification-item.notification-clickable').on('click', '.notification-item.notification-clickable', function(e) {
            // Don't trigger if clicking on action buttons, mark-read button, or input fields
            if ($(e.target).closest('.btn-action, .btn-mark-read, .notification-actions-wrapper, .reschedule-picker-inline, input, select, textarea').length > 0) {
                return;
            }
            
            // Don't trigger if clicking directly on the redirect button (it has its own handler)
            if ($(e.target).closest('.btn-notification-redirect').length > 0) {
                return;
            }
            
            const $item = $(this);
            const redirectUrl = $item.data('redirect-url');
            const notificationId = $item.data('id');
            const isUnread = $item.hasClass('unread');
            
            if (redirectUrl) {
                // Use the existing handleRedirectClick logic
                NotificationsManager.handleRedirectClick(e, notificationId, isUnread, redirectUrl);
            }
        });
    },
    
    showReschedulePicker: function(notificationId, meetingId, buttonElement) {
        // Hide the reschedule button
        const $button = $(buttonElement);
        $button.hide();
        
        // Get the notification item
        const $notificationItem = $button.closest('.notification-item');
        
        // Check if picker already exists
        if ($notificationItem.find('.reschedule-picker-inline').length > 0) {
            return;
        }
        
        // Create inline date-time picker
        const pickerHtml = `
            <div class="reschedule-picker-inline" style="margin-top: 0.5rem; padding: 0.75rem; background: rgba(99, 102, 241, 0.1); border-radius: 6px; border: 1px solid rgba(99, 102, 241, 0.3);">
                <form class="reschedule-form-inline" data-notification-id="${notificationId}" data-meeting-id="${meetingId}">
                    <div style="margin-bottom: 0.5rem;">
                        <label style="color: var(--dark-text-primary); font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Select New Date & Time:</label>
                        <input type="datetime-local" 
                               class="form-control reschedule-datetime" 
                               required 
                               style="background-color: #2a2a2a; color: #fff; border-color: #444; font-size: 0.85rem; padding: 0.5rem;">
                    </div>
                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                        <button type="button" class="cancel-reschedule" style="background: transparent; border: 1px solid var(--glass-border); color: var(--dark-text-secondary); padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                            Cancel
                        </button>
                        <button type="submit" class="btn-reschedule-submit" style="background: var(--brand-primary); border: none; color: white; padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                            Reschedule
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        // Insert after notification content
        $notificationItem.find('.notification-content').after(pickerHtml);
        
        // Set minimum date to now
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const $datetimeInput = $notificationItem.find('.reschedule-datetime');
        $datetimeInput.attr('min', `${year}-${month}-${day}T${hours}:${minutes}`);
        
        // Focus the input
        setTimeout(() => {
            $datetimeInput[0].focus();
            if ($datetimeInput[0].showPicker) {
                $datetimeInput[0].showPicker();
            }
        }, 100);
    },
    
    performReschedule: function(notificationId, meetingId, dateTime, $form) {
        const $submitBtn = $form.find('.btn-reschedule-submit');
        const originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Rescheduling...');
        
        // Convert datetime-local format to MySQL format
        const mysqlDateTime = dateTime.replace('T', ' ') + ':00';
        
        $.ajax({
            url: '../ajax/meeting_handler.php',
            method: 'POST',
            data: {
                action: 'schedule',
                meeting_id: meetingId,
                scheduled_date: mysqlDateTime,
                schedule_comment: 'Rescheduled from notification'
            },
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    this.playNotificationSound();
                    this.showInlineSuccess('Meeting rescheduled successfully!');
                    
                    // Remove the picker
                    $form.closest('.reschedule-picker-inline').slideUp(200, function() {
                        $(this).remove();
                    });
                    
                    // Immediately update notification UI
                    // Use response notification message if available, otherwise format the dateTime
                    const rescheduleMessage = response.notification || null;
                    this.updateNotificationAfterAction(notificationId, 'reschedule_meeting', {
                        ...response,
                        message: rescheduleMessage || response.message
                    }, dateTime);
                    
                    // Reload notifications (with slight delay to show immediate UI update)
                    setTimeout(() => {
                        this.loadNotifications();
                        this.updateUnreadCount();
                    }, 500);
                } else {
                    this.showInlineError(response.error || 'Failed to reschedule meeting');
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: (xhr, status, error) => {
                console.error('Error rescheduling meeting:', error);
                let errorMsg = 'Error rescheduling meeting. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch (e) {
                    // Use default error message
                }
                this.showInlineError(errorMsg);
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    },
    
    showInlineSuccess: function(message) {
        this.showInlineMessage(message, 'success');
    },
    
    showInlineError: function(message) {
        this.showInlineMessage(message, 'error');
    },
    
    showInlineMessage: function(message, type) {
        // Remove existing messages
        $('.notification-inline-message').remove();
        
        const bgColor = type === 'success' ? 'rgba(34, 197, 94, 0.2)' : 'rgba(239, 68, 68, 0.2)';
        const borderColor = type === 'success' ? '#22c55e' : '#ef4444';
        const textColor = type === 'success' ? '#22c55e' : '#ef4444';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        const messageHtml = `
            <div class="notification-inline-message" style="margin-top: 0.5rem; padding: 0.5rem 0.75rem; background: ${bgColor}; border: 1px solid ${borderColor}; border-radius: 4px; color: ${textColor}; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas ${icon}"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
        `;
        
        // Insert at the top of notifications content
        const $content = $('#notificationsContent');
        $content.prepend(messageHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            $('.notification-inline-message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    },
    
    markAsRead: function(notificationId) {
        $.ajax({
            url: '../ajax/notifications_handler.php',
            method: 'POST',
            data: {
                action: 'mark_read',
                notification_id: notificationId
            },
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    $(`.notification-item[data-id="${notificationId}"]`).removeClass('unread');
                    this.updateUnreadCount();
                }
            },
            error: (xhr, status, error) => {
                console.error('Error marking notification as read:', error);
            }
        });
    },
    
    markAllAsRead: function() {
        $.ajax({
            url: '../ajax/notifications_handler.php',
            method: 'POST',
            data: {
                action: 'mark_all_read'
            },
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    $('.notification-item').removeClass('unread');
                    this.updateUnreadCount();
                    if (typeof showToast === 'function') {
                        showToast('success', 'All notifications marked as read');
                    }
                }
            },
            error: (xhr, status, error) => {
                console.error('Error marking all as read:', error);
            }
        });
    },
    
    updateBadge: function(count) {
        const $badge = $('#notificationBadge');
        const previousCount = parseInt($badge.text()) || 0;
        
        if (count > 0) {
            const displayCount = count > 99 ? '99+' : count;
            
            // Animate badge update if count increased
            if (count > previousCount && previousCount > 0) {
                $badge.addClass('badge-pulse');
                setTimeout(() => {
                    $badge.removeClass('badge-pulse');
                }, 600);
            }
            
            $badge.text(displayCount).fadeIn(200);
            $('#notificationsBell').addClass('has-notifications');
        } else {
            $badge.fadeOut(200);
            $('#notificationsBell').removeClass('has-notifications');
        }
    },
    
    showNewNotificationToast: function(newNotifications) {
        // Show a subtle toast notification for new notifications
        if (newNotifications.length === 0) return;
        
        const notification = newNotifications[0]; // Show first new notification
        const message = notification.title || 'New notification';
        
        // Create a toast element
        const toast = $(`
            <div class="notification-toast" style="
                position: fixed;
                top: 80px;
                right: 20px;
                background: var(--fms-primary, #2f3c7e);
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                max-width: 350px;
                animation: slideInRight 0.3s ease-out;
                cursor: pointer;
            ">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-bell" style="font-size: 18px;"></i>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">${this.escapeHtml(message)}</div>
                        <div style="font-size: 12px; opacity: 0.9;">${newNotifications.length > 1 ? `+${newNotifications.length - 1} more` : 'Tap to view'}</div>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(toast);
        
        // Auto-dismiss after 4 seconds
        setTimeout(() => {
            toast.css('animation', 'slideOutRight 0.3s ease-out');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 4000);
        
        // Click to open notifications
        toast.on('click', () => {
            if (!$('#notificationsMenu').hasClass('show')) {
                this.toggleNotificationsMenu();
            }
            toast.remove();
        });
    },
    
    updateUnreadCount: function() {
        $.ajax({
            url: '../ajax/notifications_handler.php?action=get_unread_count',
            method: 'GET',
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    this.updateBadge(response.unread_count);
                }
            }
        });
    },
    
    updateFooter: function(total, unread) {
        const $footer = $('#notificationsFooter');
        if (total === 0) {
            $footer.text('No notifications');
        } else if (unread > 0) {
            $footer.text(`${unread} unread notification${unread > 1 ? 's' : ''}`);
        } else {
            $footer.text('All caught up!');
        }
    },
    
    formatTimestamp: function(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) {
            return 'Just now';
        } else if (diffMins < 60) {
            return `${diffMins} min${diffMins > 1 ? 's' : ''} ago`;
        } else if (diffHours < 24) {
            return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        } else if (diffDays === 1) {
            return 'Yesterday';
        } else if (diffDays < 7) {
            return `${diffDays} days ago`;
        } else {
            return date.toLocaleDateString();
        }
    },
    
    startPolling: function() {
        // Check for new notifications every 10 seconds for real-time feel
        this.checkInterval = setInterval(() => {
            this.checkForNewNotifications();
        }, 10000); // Reduced from 30 seconds to 10 seconds
        
        // Initial check after 3 seconds
        setTimeout(() => {
            this.checkForNewNotifications();
        }, 3000);
    },
    
    checkForNewNotifications: function() {
        const previousCount = parseInt($('#notificationBadge').text()) || 0;
        
        // Get full notification list to check for new unread ones
        $.ajax({
            url: '../ajax/notifications_handler.php?action=get_notifications',
            method: 'GET',
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    const newCount = response.unread_count;
                    const previousIds = [...this.lastNotificationIds];
                    
                    // Find notifications that are:
                    // 1. New (not in lastNotificationIds)
                    // 2. Unread (is_read = 0)
                    // 3. Not already alerted
                    const newUnreadNotifications = response.notifications.filter(n => {
                        return !previousIds.includes(n.id) && 
                               !n.is_read && 
                               !this.alertedNotificationIds.includes(n.id);
                    });
                    
                    // Update last seen IDs
                    const currentIds = response.notifications.map(n => n.id);
                    this.lastNotificationIds = currentIds;
                    
                    // Cleanup old alerted IDs that are no longer in the list
                    this.cleanupOldAlertedIds(currentIds);
                    
                    // Update badge with smooth animation
                    this.updateBadge(newCount);
                    
                    // If new unread notifications arrived
                    if (newUnreadNotifications.length > 0 && !this.isInitialLoad) {
                        // Mark as alerted
                        newUnreadNotifications.forEach(n => {
                            if (!this.alertedNotificationIds.includes(n.id)) {
                                this.alertedNotificationIds.push(n.id);
                            }
                        });
                        // Save to sessionStorage
                        this.saveAlertedNotificationIds();
                        
                        // Play sound
                        if (this.audioEnabled) {
                            this.playNotificationSound();
                        }
                        
                        // Show toast
                        this.showNewNotificationToast(newUnreadNotifications);
                        
                        // If menu is open, refresh the notification list silently
                        if (this.isMenuOpen) {
                            this.loadNotifications(true); // Silent refresh
                        }
                    } else if (this.isMenuOpen && newCount !== previousCount) {
                        // Just refresh the list if count changed but no new alerts needed
                        this.loadNotifications(true); // Silent refresh
                    }
                }
            },
            error: (xhr, status, error) => {
                // Silently fail - don't disrupt user experience
                console.log('Notification check failed:', error);
            }
        });
    },
    
    saveAlertedNotificationIds: function() {
        // Save to sessionStorage to persist across page reloads
        try {
            sessionStorage.setItem('notifications_alerted_ids', JSON.stringify(this.alertedNotificationIds));
        } catch (e) {
            console.log('Failed to save alerted notification IDs:', e);
        }
    },
    
    loadAlertedNotificationIds: function() {
        // Load from sessionStorage
        try {
            const saved = sessionStorage.getItem('notifications_alerted_ids');
            if (saved) {
                this.alertedNotificationIds = JSON.parse(saved);
                // Clean up old IDs (keep only last 100 to prevent memory issues)
                if (this.alertedNotificationIds.length > 100) {
                    this.alertedNotificationIds = this.alertedNotificationIds.slice(-100);
                    this.saveAlertedNotificationIds();
                }
            }
        } catch (e) {
            console.log('Failed to load alerted notification IDs:', e);
            this.alertedNotificationIds = [];
        }
    },
    
    cleanupOldAlertedIds: function(currentNotificationIds) {
        // Remove alerted IDs that are no longer in the current notification list
        // This prevents memory buildup from old notifications
        this.alertedNotificationIds = this.alertedNotificationIds.filter(id => 
            currentNotificationIds.includes(id)
        );
        this.saveAlertedNotificationIds();
    },
    
    clearAlertedNotificationIds: function() {
        // Clear alerted IDs (useful for testing or reset)
        this.alertedNotificationIds = [];
        try {
            sessionStorage.removeItem('notifications_alerted_ids');
        } catch (e) {
            console.log('Failed to clear alerted notification IDs:', e);
        }
    },
    
    setupAudioNotification: function() {
        // Determine the correct path based on current page location
        const currentPath = window.location.pathname;
        let audioPath;
        
        // Check if we're in pages/ directory
        if (currentPath.includes('/pages/')) {
            audioPath = '../assets/audio/notification.mp3';
        } else {
            // We're at root level
            audioPath = 'assets/audio/notification.mp3';
        }
        
        console.log('Setting up audio notification with path:', audioPath);
        
        // Create audio element
        this.audioElement = new Audio(audioPath);
        this.audioElement.preload = 'auto';
        this.audioElement.volume = 0.5;
        
        // Handle successful load
        this.audioElement.addEventListener('loadeddata', () => {
            console.log('✓ Notification audio file loaded successfully');
        });
        
        this.audioElement.addEventListener('canplaythrough', () => {
            console.log('✓ Notification audio ready to play');
        });
        
        // Handle loading errors
        this.audioElement.addEventListener('error', (e) => {
            console.error('✗ Failed to load audio from:', audioPath);
            console.error('Error details:', e);
            
            // Try alternative path
            const altPath = currentPath.includes('/pages/') 
                ? 'assets/audio/notification.mp3' 
                : '../assets/audio/notification.mp3';
            
            console.log('Trying alternative path:', altPath);
            const altAudio = new Audio(altPath);
            altAudio.preload = 'auto';
            altAudio.volume = 0.5;
            
            altAudio.addEventListener('loadeddata', () => {
                console.log('✓ Notification audio loaded from alternative path:', altPath);
                this.audioElement = altAudio;
            });
            
            altAudio.addEventListener('error', () => {
                console.warn('✗ Both audio paths failed, will use fallback beep sound');
                this.audioElement = null;
            });
        });
        
        // Try to load the audio
        this.audioElement.load();
    },
    
    unlockAudio: function() {
        // Unlock audio on first user interaction (required by browser autoplay policies)
        const unlock = () => {
            if (this.audioElement) {
                // Try to play and immediately pause to unlock audio
                const playPromise = this.audioElement.play();
                if (playPromise !== undefined) {
                    playPromise
                        .then(() => {
                            this.audioElement.pause();
                            this.audioElement.currentTime = 0;
                            console.log('Audio unlocked successfully');
                        })
                        .catch(() => {
                            // Audio will be unlocked on next user interaction
                        });
                }
            }
        };
        
        // Unlock on various user interactions
        $(document).one('click touchstart keydown', unlock);
        
        // Also try to unlock when bell is clicked
        $('#notificationsBell').one('click', unlock);
    },
    
    playNotificationSound: function() {
        if (!this.audioEnabled) {
            console.log('Audio notifications are disabled');
            return;
        }
        
        console.log('Attempting to play notification sound...');
        
        try {
            // Try to play the audio element
            if (this.audioElement) {
                // Clone the audio element to allow multiple simultaneous plays
                const audioClone = this.audioElement.cloneNode();
                audioClone.volume = 0.5;
                
                // Reset to beginning and play
                audioClone.currentTime = 0;
                
                const playPromise = audioClone.play();
                
                // Handle play promise rejection (browser autoplay policies)
                if (playPromise !== undefined) {
                    playPromise
                        .then(() => {
                            console.log('Notification sound played successfully');
                            // Clean up after audio finishes
                            audioClone.addEventListener('ended', () => {
                                audioClone.remove();
                            });
                        })
                        .catch(error => {
                            console.warn('Audio autoplay prevented:', error);
                            console.log('Trying fallback beep sound');
                            this.playFallbackBeep();
                        });
                } else {
                    // For older browsers
                    console.log('Notification sound triggered (legacy browser)');
                }
            } else {
                // Fallback: Use Web Audio API to create a beep
                console.log('No audio element available, using fallback beep');
                this.playFallbackBeep();
            }
        } catch (e) {
            console.error('Notification sound error:', e);
            // Try fallback on error
            this.playFallbackBeep();
        }
    },
    
    playFallbackBeep: function() {
        try {
            // Fallback: Use Web Audio API to create a subtle beep
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) {
                console.warn('Web Audio API not supported');
                return;
            }
            
            const audioContext = new AudioContext();
            
            // Resume audio context if suspended (required by some browsers)
            if (audioContext.state === 'suspended') {
                audioContext.resume().then(() => {
                    console.log('Audio context resumed');
                });
            }
            
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.2, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
            
            console.log('Fallback beep sound played');
        } catch (e) {
            console.error('Fallback beep also failed:', e);
        }
    },
    
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    updateNotificationAfterAction: function(notificationId, actionType, response, additionalData = null) {
        const $notificationItem = $(`.notification-item[data-id="${notificationId}"]`);
        
        if ($notificationItem.length === 0) {
            return;
        }
        
        // Remove all action buttons immediately
        $notificationItem.find('.notification-actions-wrapper').fadeOut(200, function() {
            $(this).remove();
        });
        
        // Mark notification as read (remove unread class and mark-read button)
        $notificationItem.removeClass('unread');
        $notificationItem.find('.btn-mark-read').fadeOut(200, function() {
            $(this).remove();
        });
        
        // Update notification message based on action type
        const $message = $notificationItem.find('.notification-message');
        if ($message.length > 0) {
            let updatedMessage = '';
            
            switch (actionType) {
                case 'approve_meeting':
                    updatedMessage = response.message || 'Meeting has been approved';
                    break;
                
                case 'approve_leave':
                    updatedMessage = response.message || 'Leave has been approved';
                    break;
                
                case 'reject_leave':
                    updatedMessage = response.message || 'Leave has been rejected';
                    break;
                
                case 'reschedule_meeting':
                    // Use response notification message if available (contains formatted date/time)
                    if (response.notification) {
                        updatedMessage = response.notification;
                    } else if (additionalData) {
                        // Fallback: Format the rescheduled date/time from additionalData
                        const dateObj = new Date(additionalData);
                        const formattedDate = dateObj.toLocaleDateString('en-GB', { 
                            day: '2-digit', 
                            month: '2-digit', 
                            year: 'numeric' 
                        });
                        const formattedTime = dateObj.toLocaleTimeString('en-US', { 
                            hour: '2-digit', 
                            minute: '2-digit',
                            hour12: true 
                        });
                        updatedMessage = `Meeting rescheduled to ${formattedDate}, ${formattedTime}`;
                    } else {
                        updatedMessage = response.message || 'Meeting has been rescheduled';
                    }
                    break;
                
                default:
                    updatedMessage = response.message || 'Action completed';
            }
            
            if (updatedMessage) {
                $message.fadeOut(150, function() {
                    $(this).text(updatedMessage).fadeIn(150);
                });
            }
        }
        
        // Update notification title if needed (for status changes)
        if (response.status_title) {
            const $title = $notificationItem.find('.notification-title');
            if ($title.length > 0) {
                $title.fadeOut(150, function() {
                    $(this).text(response.status_title).fadeIn(150);
                });
            }
        }
    },
    
    handleNotificationClick: function(event, notificationId, isUnread) {
        // Mark as read if unread before redirecting (fire and forget - don't wait)
        // Don't prevent default - let the anchor tag handle navigation naturally
        if (isUnread && notificationId) {
            // Mark as read asynchronously (don't block redirect)
            $.ajax({
                url: '../ajax/notifications_handler.php',
                method: 'POST',
                data: {
                    action: 'mark_read',
                    notification_id: notificationId
                },
                dataType: 'json',
                async: true
            }).done(function() {
                // Optionally update the UI to remove unread styling
                const $item = $('.notification-item[data-id="' + notificationId + '"]');
                if ($item.length) {
                    $item.removeClass('unread');
                }
            });
        }
        // Let the anchor tag handle the redirect naturally - don't prevent default
    },
    
    handleRedirectClick: function(event, notificationId, isUnread, redirectUrl) {
        event.preventDefault();
        event.stopPropagation();
        
        // Mark as read if unread before redirecting (fire and forget - don't wait)
        if (isUnread && notificationId) {
            // Mark as read asynchronously (don't block redirect)
            $.ajax({
                url: '../ajax/notifications_handler.php',
                method: 'POST',
                data: {
                    action: 'mark_read',
                    notification_id: notificationId
                },
                dataType: 'json',
                async: true
            });
        }
        
        // Redirect immediately
        if (redirectUrl) {
            window.location.href = redirectUrl;
        }
    },
    
    getNotificationRedirectUrl: function(notif) {
        // Debug: Log notification type
        if (!notif) {
            console.warn('Notification missing:', notif);
            return null;
        }
        
        // Handle empty or missing type - use related_type as fallback
        let notificationType = notif.type;
        if (!notificationType || notificationType === '' || notificationType === null || notificationType === undefined) {
            // Try to infer type from related_type and action_data
            if (notif.related_type === 'meeting') {
                // Check if it has action buttons to determine if it's a request
                if (notif.action_required && notif.action_data && notif.action_data.actions) {
                    notificationType = 'meeting_request';
                } else {
                    // Could be approved or rescheduled, default to meeting_request
                    notificationType = 'meeting_request';
                }
            } else if (notif.related_type === 'leave') {
                // Check title/message to determine leave status
                const title = (notif.title || '').toLowerCase();
                const message = (notif.message || '').toLowerCase();
                if (title.includes('approved') || message.includes('approved')) {
                    notificationType = 'leave_approved';
                } else if (title.includes('rejected') || message.includes('rejected')) {
                    notificationType = 'leave_rejected';
                } else {
                    notificationType = 'leave_request';
                }
            } else if (notif.related_type === 'task') {
                notificationType = 'task_delay';
            } else if (notif.related_type === 'note') {
                notificationType = 'notes_reminder';
            } else if (notif.related_type === 'user') {
                // Day special notifications (birthdays, anniversaries) - no redirect needed
                notificationType = 'day_special';
            } else {
                // Can't determine type, silently return null (don't log warning for unknown types)
                // This prevents console spam for notifications that legitimately don't need redirects
                return null;
            }
        }
        
        // Get user type from session (we'll need to pass this or get it from a global variable)
        const userType = window.currentUserType || 'doer'; // Default to doer if not set
        
        // Determine base path - if we're in pages/ directory, use relative path, otherwise use ../pages/
        const currentPath = window.location.pathname;
        const isInPages = currentPath.includes('/pages/');
        const basePath = isInPages ? '' : '../pages/';
        
        let redirectUrl = null;
        
        switch (notificationType) {
            case 'meeting_request':
            case 'meeting_approved':
            case 'meeting_rescheduled':
                // All users go to my meetings page
                redirectUrl = basePath + 'admin_my_meetings.php';
                break;
            
            case 'leave_request':
            case 'leave_approved':
            case 'leave_rejected':
                redirectUrl = basePath + 'leave_request.php';
                break;
            
            case 'task_delay':
                // All users go to my_task page
                redirectUrl = basePath + 'my_task.php';
                break;
            
            case 'notes_reminder':
                redirectUrl = basePath + 'my_notes.php';
                break;
            
            case 'day_special':
                // Day special notifications don't need redirect - they are static celebrations
                redirectUrl = null;
                break;
            
            default:
                redirectUrl = null;
        }
        
        // Debug logging (can be removed later)
        if (redirectUrl) {
            console.log('Notification redirect URL generated:', {
                originalType: notif.type,
                inferredType: notificationType,
                related_type: notif.related_type,
                redirectUrl: redirectUrl,
                basePath: basePath
            });
        }
        
        return redirectUrl;
    }
};

// Initialize on document ready
$(document).ready(function() {
    if (typeof NotificationsManager !== 'undefined') {
        NotificationsManager.init();
    }
});

