<?php
/**
 * Shift Task Modal Popup Script
 * 
 * This script provides a complete modal popup system for shifting tasks
 * with date and time confirmation. It includes:
 * 1. Modal HTML structure with proper Z-index
 * 2. JavaScript functionality for modal interactions
 * 3. AJAX handling for task shifting
 * 4. Date/time picker integration
 * 5. Form validation and error handling
 * 
 * Usage: Include this file in your task management pages
 * 
 * NOTE: Confirmation modal replaced with browser confirm() dialog
 */

// This file only contains the modal functions, not the AJAX handler
// The AJAX request will be handled by the existing action_shift_task.php

/**
 * Generate the modal HTML structure
 */
function generateShiftTaskModal() {
    return '
    <!-- Date Time Picker Modal (Confirmation Modal Removed - Using Browser Confirm) -->
    <div class="modal fade" id="dateTimePickerModal" tabindex="-1" role="dialog" aria-labelledby="dateTimePickerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dateTimePickerModalLabel">
                        <i class="fas fa-calendar-plus mr-2"></i>Select New Date and Time
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="shiftTaskForm">
                        <input type="hidden" id="shift_task_id" name="task_id" value="">
                        <input type="hidden" id="shift_task_type" name="task_type" value="">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="new_planned_date">
                                        <i class="fas fa-calendar mr-1"></i>New Date
                                    </label>
                                    <input type="date" class="form-control" id="new_planned_date" name="new_planned_date" required>
                                    <small class="form-text text-muted">Select a date from today onwards</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="new_planned_time">
                                        <i class="fas fa-clock mr-1"></i>New Time
                                    </label>
                                    <select class="form-control" id="new_planned_time" name="new_planned_time" required>
                                        <option value="">Select Time</option>
                                        ' . generateTimeOptions() . '
                                    </select>
                                    <small class="form-text text-muted">Select a time slot</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Note:</strong> Shifting to a different week will create a new task and mark the current one as completed.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="submitShiftBtn">
                        <i class="fas fa-calendar-check mr-1"></i>Shift Task
                    </button>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Generate time options for the time picker
 */
function generateTimeOptions() {
    $options = '';
    $start = strtotime("10:00");
    $end = strtotime("23:30");
    $interval = 30 * 60; // 30 minutes
    
    for ($time = $start; $time <= $end; $time += $interval) {
        $value = date("H:i", $time);
        $display = date("h:i A", $time);
        $options .= "<option value=\"{$value}\">{$display}</option>";
    }
    
    // Add 11:59 PM as a special option
    $value_1159 = '23:59';
    $display_1159 = '11:59 PM';
    $options .= "<option value=\"{$value_1159}\">{$display_1159}</option>";
    
    return $options;
}

/**
 * Generate the CSS styles for the modals
 */
function generateModalStyles() {
    return '
    <style>
        /* ========================================
           MODAL Z-INDEX SYSTEM - SIMPLIFIED
           ======================================== */
        
        /* Modal backdrop - ULTRA HIGH Z-INDEX */
        body .modal-backdrop {
            z-index: 9999998 !important;
        }
        
        body .modal-backdrop.show {
            z-index: 9999998 !important;
        }
        
        /* Modal container - ULTRA HIGH Z-INDEX */
        body .modal {
            z-index: 9999999 !important;
        }
        
        body .modal.show {
            z-index: 9999999 !important;
        }
        
        /* Date time picker modal - HIGHEST PRIORITY */
        body .modal#dateTimePickerModal {
            z-index: 10000000 !important;
        }
        
        body .modal#dateTimePickerModal.show {
            z-index: 10000000 !important;
        }
        
        /* Modal dialog - ULTRA HIGH Z-INDEX */
        body .modal .modal-dialog {
            z-index: 10000001 !important;
        }
        
        body .modal#dateTimePickerModal .modal-dialog {
            z-index: 10000001 !important;
        }
        
        /* Modal content - ULTRA HIGH Z-INDEX */
        body .modal .modal-content {
            z-index: 10000002 !important;
        }
        
        body .modal#dateTimePickerModal .modal-content {
            z-index: 10000002 !important;
        }
        
        /* Force modal visibility - ULTRA HIGH Z-INDEX */
        body .modal#dateTimePickerModal.modal.fade.show {
            display: block !important;
            z-index: 10000000 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* Modal interactive elements */
        body .modal .form-control,
        body .modal .btn,
        body .modal .dropdown-menu,
        body .modal .alert {
            position: relative !important;
            z-index: 100030 !important;
        }
        
        /* Modal backdrop styling */
        body .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5) !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            z-index: 99998 !important;
            pointer-events: auto !important;
        }
        
        /* Modal container positioning */
        body .modal {
            padding-right: 0 !important;
        }
        
        /* Modal dialog centering and sizing */
        body .modal .modal-dialog {
            margin: 1.75rem auto !important;
            max-width: 500px !important;
        }
        
        body .modal#dateTimePickerModal .modal-dialog {
            max-width: 600px !important;
        }
        
        /* Modal content styling */
        body .modal .modal-content {
            border: none !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2) !important;
        }
        
        /* Modal header styling */
        body .modal .modal-header {
            border-bottom: 1px solid #dee2e6 !important;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
            color: white !important;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        
        body .modal .modal-header .close {
            color: white !important;
            opacity: 0.8 !important;
        }
        
        body .modal .modal-header .close:hover {
            opacity: 1 !important;
        }
        
        /* Modal body styling */
        body .modal .modal-body {
            padding: 1.5rem !important;
        }
        
        /* Modal footer styling */
        body .modal .modal-footer {
            border-top: 1px solid #dee2e6 !important;
            background-color: #f8f9fa !important;
            border-radius: 0 0 0.5rem 0.5rem !important;
        }
        
        /* Form styling */
        .form-group label {
            font-weight: 600;
            color: #495057;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        /* Button styling */
        .btn i {
            margin-right: 0.25rem;
        }
        
        /* Alert styling */
        .alert {
            border-radius: 0.5rem;
        }
        
        /* Native HTML5 date input styling */
        input[type="date"] {
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            color: #495057;
        }
        
        input[type="date"]:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: 0;
        }
        
        input[type="date"]::-webkit-calendar-picker-indicator {
            background: transparent;
            bottom: 0;
            color: transparent;
            cursor: pointer;
            height: auto;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            width: auto;
        }
        
        /* Ensure date input has proper spacing */
        .form-group input[type="date"] {
            width: 100%;
        }
        
        /* Ensure modal is completely interactive */
        body .modal#dateTimePickerModal {
            pointer-events: auto !important;
            user-select: auto !important;
        }
        
        /* Force all modal elements to be interactive */
        body .modal#dateTimePickerModal * {
            pointer-events: auto !important;
        }
        
        /* Ensure buttons are clickable */
        body .modal#dateTimePickerModal .btn {
            pointer-events: auto !important;
            cursor: pointer !important;
            z-index: 10000003 !important;
        }
        
        /* Ensure form controls are interactive */
        body .modal#dateTimePickerModal .form-control {
            pointer-events: auto !important;
            cursor: text !important;
            z-index: 10000003 !important;
        }
        
        /* ULTIMATE Z-INDEX OVERRIDE - FORCE ABOVE EVERYTHING */
        html body .modal#dateTimePickerModal,
        html body .modal#dateTimePickerModal.modal,
        html body .modal#dateTimePickerModal.modal.show,
        html body .modal#dateTimePickerModal.modal.fade.show {
            z-index: 10000000 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* Force modal dialog to be above everything */
        html body .modal#dateTimePickerModal .modal-dialog {
            z-index: 10000001 !important;
            position: relative !important;
        }
        
        /* Force modal content to be above everything */
        html body .modal#dateTimePickerModal .modal-content {
            z-index: 10000002 !important;
            position: relative !important;
        }
        
        /* Override any potential interference from sidebar/header */
        body .sidebar,
        body .header,
        body .navbar,
        body .main-header {
            z-index: 999 !important;
        }
        
        /* DEBUG: Make modal very visible for testing */
        body .modal#dateTimePickerModal {
            border: 3px solid #ff0000 !important;
            background-color: rgba(255, 0, 0, 0.1) !important;
        }
        
        body .modal#dateTimePickerModal .modal-content {
            border: 3px solid #00ff00 !important;
            background-color: #ffffff !important;
        }
    </style>';
}

/**
 * Generate the JavaScript functionality
 */
function generateModalJavaScript() {
    return '
    <script>
    $(document).ready(function() {
        let currentTaskId = null;
        let currentTaskType = null;
        
        // Initialize native HTML5 date input constraints
        function initializeDateInput() {
            var today = new Date();
            var maxDate = new Date();
            maxDate.setFullYear(maxDate.getFullYear() + 2);
            
            // Format dates for HTML5 date input
            var todayStr = today.getFullYear() + "-" + 
                          (today.getMonth() + 1 < 10 ? "0" : "") + (today.getMonth() + 1) + "-" + 
                          (today.getDate() < 10 ? "0" : "") + today.getDate();
            
            var maxDateStr = maxDate.getFullYear() + "-" + 
                            (maxDate.getMonth() + 1 < 10 ? "0" : "") + (maxDate.getMonth() + 1) + "-" + 
                            (maxDate.getDate() < 10 ? "0" : "") + maxDate.getDate();
            
            // Set constraints and default value
            $("#new_planned_date").attr("min", todayStr);
            $("#new_planned_date").attr("max", maxDateStr);
            $("#new_planned_date").val(todayStr);
        }
        
        // Handle shift submission
        $("#submitShiftBtn").on("click", function() {
            var newPlannedDate = $("#new_planned_date").val();
            var newPlannedTime = $("#new_planned_time").val();
            
            // Validate inputs
            if (!newPlannedDate || !newPlannedTime) {
                showAlert("Please select both date and time to continue.", "warning");
                return;
            }
            
            // Validate date is not in the past
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            var selectedDate = new Date(newPlannedDate + "T00:00:00");
            if (isNaN(selectedDate.getTime())) {
                showAlert("Invalid date selected. Please choose a valid date to continue.", "warning");
                return;
            }
            
            if (selectedDate < today) {
                showAlert("Cannot shift task to a past date. Please select today or a future date.", "warning");
                return;
            }
            
            // Show loading state
            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop("disabled", true).html("<i class=\"fas fa-spinner fa-spin mr-1\"></i>Shifting...");
            
            // Submit the shift request
            $.ajax({
                url: "action_shift_task.php",
                type: "POST",
                data: {
                    task_id: currentTaskId,
                    new_planned_date: newPlannedDate,
                    new_planned_time: newPlannedTime
                },
                dataType: "json",
                success: function(response) {
                    // Hide the date time picker modal
                    $("#dateTimePickerModal").modal("hide");
                    
                    // Validate response structure
                    if (typeof response !== "object" || response === null) {
                        showAlert("Invalid response from server. Please try again.", "danger");
                        return;
                    }
                    
                    if (response.status === "success") {
                        var message = response.message || "Task shifted successfully!";
                        showAlert(message, "success");
                        // Reload page after successful shift
                        setTimeout(function() { 
                            location.reload(); 
                        }, 2000);
                    } else {
                        var errorMessage = response.message || "An unknown error occurred.";
                        showAlert("Error: " + errorMessage, "danger");
                    }
                },
                error: function(xhr, status, error) {
                    // Hide the date time picker modal
                    $("#dateTimePickerModal").modal("hide");
                    
                    // AJAX Error occurred
                    showAlert("AJAX Error: Could not shift task. " + error, "danger");
                },
                complete: function() {
                    // Reset button state
                    $btn.prop("disabled", false).html(originalText);
                }
            });
        });
        
        // Show alert function
        function showAlert(message, type) {
            var alertClass = "alert-" + type;
            var alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === "success" ? "check-circle" : type === "warning" ? "exclamation-triangle" : "times-circle"} mr-2"></i>
                    ${message}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `;
            
            // Remove existing alerts
            $(".alert").remove();
            
            // Add new alert at the top of the page
            $("body").prepend(alertHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $(".alert").fadeOut();
            }, 5000);
        }
        
        // Handle cancel button clicks
        $("#dateTimePickerModal .btn-secondary, #dateTimePickerModal .close").on("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            $("#dateTimePickerModal").modal("hide");
        });
        
        // Handle modal close events
        $("#dateTimePickerModal").on("hidden.bs.modal", function() {
            // Reset form
            $("#shiftTaskForm")[0].reset();
            // Reset the shift modal called flag
            window.shiftModalCalled = false;
        });
        
        // Handle backdrop clicks to close modal
        $("#dateTimePickerModal").on("click", function(e) {
            if (e.target === this) {
                $(this).modal("hide");
            }
        });
        
        // Handle ESC key to close modal
        $(document).on("keydown", function(e) {
            if (e.keyCode === 27) { // ESC key
                if ($("#dateTimePickerModal").hasClass("show")) {
                    $("#dateTimePickerModal").modal("hide");
                }
            }
        });
    });
    
    // Make functions globally available immediately (outside document.ready)
    window.showShiftConfirmationModal = function(taskId, taskType, taskData) {
        // Wait for DOM to be ready, then execute
        $(document).ready(function() {
            // Check if function is already defined to prevent duplicates
            if (typeof window.showShiftConfirmationModalInternal === "function") {
                window.showShiftConfirmationModalInternal(taskId, taskType, taskData);
            } else {
                // If not ready yet, wait a bit more
                setTimeout(function() {
                    if (typeof window.showShiftConfirmationModalInternal === "function") {
                        window.showShiftConfirmationModalInternal(taskId, taskType, taskData);
                    }
                }, 100);
            }
        });
    };
    
    // Internal function that does the actual work - make it global
    window.showShiftConfirmationModalInternal = function(taskId, taskType, taskData) {
        // Set task data
        currentTaskId = taskId;
        currentTaskType = taskType;
        
        // Set task ID and type in form
        $("#shift_task_id").val(currentTaskId);
        $("#shift_task_type").val(currentTaskType);
        
        // Initialize native HTML5 date input
        var today = new Date();
        var maxDate = new Date();
        maxDate.setFullYear(maxDate.getFullYear() + 2);
        
        // Format dates for HTML5 date input
        var todayStr = today.getFullYear() + "-" + 
                      (today.getMonth() + 1 < 10 ? "0" : "") + (today.getMonth() + 1) + "-" + 
                      (today.getDate() < 10 ? "0" : "") + today.getDate();
        
        var maxDateStr = maxDate.getFullYear() + "-" + 
                        (maxDate.getMonth() + 1 < 10 ? "0" : "") + (maxDate.getMonth() + 1) + "-" + 
                        (maxDate.getDate() < 10 ? "0" : "") + maxDate.getDate();
        
        // Set constraints and default value
        $("#new_planned_date").attr("min", todayStr);
        $("#new_planned_date").attr("max", maxDateStr);
        $("#new_planned_date").val(todayStr);
        
        // Show date time picker modal directly
        $("#dateTimePickerModal").modal({
                backdrop: true,
                keyboard: true,
                show: true
            });
            
        // Ensure proper z-index for datetime picker modal - ULTRA HIGH Z-INDEX
            setTimeout(function() {
            $("#dateTimePickerModal").css({
                "z-index": "10000000",
                    "position": "fixed",
                    "top": "0",
                    "left": "0",
                    "width": "100%",
                    "height": "100%",
                    "display": "block",
                    "opacity": "1",
                    "visibility": "visible"
                });
            $("#dateTimePickerModal .modal-dialog").css("z-index", "10000001");
            $("#dateTimePickerModal .modal-content").css("z-index", "10000002");
            }, 100);
    };
    </script>';
}

// If this file is included directly, output the modal components
if (basename($_SERVER['PHP_SELF']) === 'shift_task_modal.php') {
    // Output CSS
    echo generateModalStyles();
    
    // Output HTML
    echo generateShiftTaskModal();
    
    // Output JavaScript
    echo generateModalJavaScript();
}
?>