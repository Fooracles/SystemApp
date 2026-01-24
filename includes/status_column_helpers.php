<?php
/**
 * Status Column Helper Functions
 * 
 * This file provides easy-to-use functions for implementing the status column system
 * throughout the FMS project. Include this file in your PHP pages to use the functions.
 */

/**
 * Get status icon HTML with tooltip (PHP Backend)
 */
function get_status_icon($status) {
    $status_val = strtolower($status ?? 'pending');
    $status_text = get_status_display_text($status);
    
    $icon_map = [
        'pending' => '⏳',
        'completed' => '✅',
        'not_done' => '❌',
        'not done' => '❌',
        'cant_be_done' => '⛔',
        'can not be done' => '⛔',
        'shifted' => '🔁',
        'priority' => '⭐'
    ];
    
    $icon = $icon_map[$status_val] ?? '⏳';
    $css_class = 'status-' . str_replace(['_', ' '], '-', $status_val);
    
    return '<span class="status-icon ' . $css_class . '" title="' . htmlspecialchars($status_text) . '">' . $icon . '</span>';
}

/**
 * Get status display text (PHP Backend)
 */
function get_status_display_text($status) {
    $status_val = strtolower($status ?? 'pending');
    $status_text = ucfirst(str_replace('_', ' ', $status_val));
    
    if ($status_val === 'not_done' || $status_val === 'not done') $status_text = 'Not Done';
    if ($status_val === 'cant_be_done' || $status_val === 'can not be done') $status_text = "Can't be done";
    
    return $status_text;
}

/**
 * Generate HTML table header for status column
 */
function get_status_column_header($sortable = true) {
    if ($sortable) {
        return '<th class="status-column sortable-header" data-column="status">
                    Status 
                    <span class="sort-icons">
                        <span class="triangle-up">▲</span>
                        <span class="triangle-down">▼</span>
                    </span>
                </th>';
    } else {
        return '<th class="status-column">Status</th>';
    }
}

/**
 * Generate HTML table cell for status column
 */
function get_status_column_cell($status, $additional_classes = '') {
    $status_icon = get_status_icon($status);
    $classes = 'status-column ' . $additional_classes;
    
    return '<td class="' . $classes . '" data-status="' . htmlspecialchars($status) . '">' . $status_icon . '</td>';
}

/**
 * Convert old badge-based status display to new icon system
 * This function helps migrate existing status displays
 */
function convert_status_to_icon($status, $old_badge_class = '') {
    // If it's already an icon, return as is
    if (strpos($status, 'status-icon') !== false) {
        return $status;
    }
    
    // Convert old status values to new format
    $status_mapping = [
        'Pending' => 'pending',
        'pending' => 'pending',
        'Completed' => 'completed',
        'completed' => 'completed',
        'Done' => 'completed',
        'done' => 'completed',
        'Not Done' => 'not_done',
        'not done' => 'not_done',
        'not_done' => 'not_done',
        'Can not be done' => 'cant_be_done',
        'can not be done' => 'cant_be_done',
        'cant_be_done' => 'cant_be_done',
        'Cancelled' => 'not_done',
        'cancelled' => 'not_done',
        'In Progress' => 'pending',
        'in progress' => 'pending',
        'Shifted' => 'shifted',
        'shifted' => 'shifted',
        'Priority' => 'priority',
        'priority' => 'priority'
    ];
    
    $normalized_status = $status_mapping[$status] ?? $status;
    return get_status_icon($normalized_status);
}

/**
 * Get all available status values
 */
function get_available_statuses() {
    return [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'not_done' => 'Not Done',
        'cant_be_done' => "Can't be done",
        'shifted' => 'Shifted',
        'priority' => 'Priority'
    ];
}

/**
 * Check if a status is valid
 */
function is_valid_status($status) {
    $valid_statuses = array_keys(get_available_statuses());
    return in_array(strtolower($status), $valid_statuses);
}
?>
