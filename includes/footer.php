<?php
// Footer component for the FMS application
if (!isLoggedIn()) {
    echo '</div>'; // Close container for non-logged in users
    return;
}
?>

                </div> <!-- End content-area -->
            </div> <!-- End main-content -->
        </div> <!-- End main-wrapper -->
    </div> <!-- End app-frame -->

    <!-- Global Confirmation Modal - At body level to escape stacking contexts -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Leave Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <p id="confirmationMessage">Are you sure you want to proceed with this action?</p>
                    </div>
                    <div class="mb-3">
                        <label for="actionNote" class="form-label">Comments (Optional):</label>
                        <textarea class="form-control" id="actionNote" rows="3" placeholder="Enter any additional comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelAction">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmAction">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Session Management Script -->
    <script>
    (function() {
        <?php if (isLoggedIn()): ?>
        // Auto-logout at 8:00 PM (20:00) daily
        const logoutHour = 20; // 8:00 PM
        const logoutMinute = 0;
        const warningMinute = 55; // 7:55 PM (5 minutes before)
        
        function checkSessionExpiry() {
            const now = new Date();
            const currentHour = now.getHours();
            const currentMinute = now.getMinutes();
            const currentSecond = now.getSeconds();
            
            // Check if current time is exactly 8:00 PM (20:00)
            if (currentHour === logoutHour && currentMinute === logoutMinute) {
                showToast('It is 8:00 PM. You will be logged out automatically.', 'warning');
                setTimeout(function() {
                    window.location.href = '../logout.php?expired=1';
                }, 2000);
                return;
            }
            
            // Show warning at 7:55 PM (5 minutes before 8:00 PM)
            if (currentHour === (logoutHour - 1) && currentMinute === warningMinute) {
                // Only show once per day
                const today = now.toDateString();
                const warningKey = 'autoLogoutWarning_' + today;
                if (!sessionStorage.getItem(warningKey)) {
                    showToast('⚠️ You will be logged out at 8:00 PM. Please save your work.', 'warning');
                    sessionStorage.setItem(warningKey, 'true');
                }
            }
        }
        
        // Check every 30 seconds for more accurate timing
        setInterval(checkSessionExpiry, 30000);
        checkSessionExpiry(); // Check immediately
        
        // Show new device warning if applicable
        <?php if (isset($_SESSION['new_device_warning']) && $_SESSION['new_device_warning']): ?>
        $(document).ready(function() {
            showToast('⚠️ You logged in from a new device/browser.', 'warning');
            <?php unset($_SESSION['new_device_warning']); ?>
        });
        <?php endif; ?>
        
        // Show expired session message if redirected
        <?php if (isset($_GET['expired'])): ?>
        $(document).ready(function() {
            showToast('You were logged out at 8:00 PM. Please login again.', 'warning');
        });
        <?php endif; ?>
        <?php endif; ?>
        
        // Toast notification function
        function showToast(message, type = 'info') {
            // Remove existing toasts
            $('.session-toast').remove();
            
            const toast = $('<div class="session-toast"></div>');
            const bgColor = type === 'warning' ? '#f59e0b' : type === 'error' ? '#ef4444' : '#3b82f6';
            
            toast.css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                background: bgColor,
                color: 'white',
                padding: '1rem 1.5rem',
                borderRadius: '0.5rem',
                boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
                zIndex: '10000',
                maxWidth: '400px',
                animation: 'slideInRight 0.3s ease-out'
            });
            
            toast.html('<i class="fas fa-' + (type === 'warning' ? 'exclamation-triangle' : 'info-circle') + '"></i> ' + message);
            $('body').append(toast);
            
            setTimeout(function() {
                toast.css('animation', 'slideOutRight 0.3s ease-out');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }, type === 'warning' ? 8000 : 5000);
        }
        
        // Add CSS animations if not already present
        if (!$('#session-toast-styles').length) {
            $('head').append(`
                <style id="session-toast-styles">
                    @keyframes slideInRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    @keyframes slideOutRight {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                    }
                </style>
            `);
        }
    })();
    </script>
</body>
</html>