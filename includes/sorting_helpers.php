<?php
/**
 * Advanced Smart Column Sorting Helper Functions
 * Provides reusable sorting functionality for all tables across the system
 */

/**
 * Get status priority for sorting
 * Priority order: Completed (1) > Pending (2) > Shifted (3) > Delayed (4) > Not Done (5)
 */
function getStatusPriority($status) {
    $status = strtolower(trim($status ?? ''));
    $priorities = [
        'completed' => 1,
        'done' => 1,
        'pending' => 2,
        'shifted' => 3,
        'delayed' => 4,
        'not_done' => 5,
        'can_not_be_done' => 6,
        'can\'t be done' => 6,
        'cant be done' => 6,
    ];
    return $priorities[$status] ?? 99; // Unknown status gets lowest priority
}

/**
 * Get task type priority for sorting
 * Order: FMS (1) > Delegation (2) > Checklist (3)
 */
function getTaskTypePriority($task_type) {
    $task_type = strtolower(trim($task_type ?? ''));
    $priorities = [
        'fms' => 1,
        'delegation' => 2,
        'checklist' => 3,
    ];
    return $priorities[$task_type] ?? 99;
}

/**
 * Parse date string to timestamp for accurate sorting
 * Handles various date formats including "31 Oct 2025 01:00 PM"
 */
function parseDateForSorting($date_str, $time_str = null) {
    if (empty($date_str)) {
        return 0; // Null dates sort last
    }
    
    // If time is provided separately, combine them
    if (!empty($time_str)) {
        $date_str = trim($date_str) . ' ' . trim($time_str);
    }
    
    // Try common date formats
    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'd M Y H:i A',  // "31 Oct 2025 01:00 PM"
        'd M Y h:i A',  // "31 Oct 2025 1:00 PM"
        'M d, Y H:i A', // "Oct 31, 2025 01:00 PM"
        'M d, Y h:i A', // "Oct 31, 2025 1:00 PM"
        'd/m/Y H:i',
        'd/m/Y',
        'm/d/Y H:i',
        'm/d/Y',
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, trim($date_str));
        if ($date !== false) {
            return $date->getTimestamp();
        }
    }
    
    // Fallback to strtotime
    $timestamp = strtotime($date_str);
    return $timestamp !== false ? $timestamp : 0;
}

/**
 * Build SQL ORDER BY clause with smart sorting
 * 
 * @param string $sort_column Column to sort by
 * @param string $sort_direction 'asc', 'desc', or 'default'
 * @param string $table_prefix Table alias prefix (e.g., 't.' for tasks table)
 * @param array $default_sort Default sorting when sort_direction is 'default'
 * @return string SQL ORDER BY clause
 */
function buildSmartOrderBy($sort_column, $sort_direction, $table_prefix = '', $default_sort = []) {
    // If direction is 'default', return default sort
    if ($sort_direction === 'default' || empty($sort_direction)) {
        if (!empty($default_sort)) {
            $order_parts = [];
            foreach ($default_sort as $col => $dir) {
                $order_parts[] = $table_prefix . $col . ' ' . strtoupper($dir);
            }
            return !empty($order_parts) ? 'ORDER BY ' . implode(', ', $order_parts) : '';
        }
        return '';
    }
    
    $direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';
    $prefix = !empty($table_prefix) ? $table_prefix : '';
    
    // Handle special sorting cases
    switch ($sort_column) {
        case 'status':
            // Use CASE statement for status priority sorting
            return "ORDER BY CASE LOWER(COALESCE({$prefix}status, ''))
                WHEN 'completed' THEN 1
                WHEN 'done' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'shifted' THEN 3
                WHEN 'delayed' THEN 4
                WHEN 'not_done' THEN 5
                WHEN 'can_not_be_done' THEN 6
                WHEN 'can\'t be done' THEN 6
                ELSE 99
            END {$direction}";
            
        case 'task_type':
            // Use CASE statement for task type priority
            return "ORDER BY CASE LOWER(COALESCE({$prefix}task_type, ''))
                WHEN 'fms' THEN 1
                WHEN 'delegation' THEN 2
                WHEN 'checklist' THEN 3
                ELSE 99
            END {$direction}";
            
        case 'planned':
        case 'actual':
            // For FMS date strings (VARCHAR format like "DD/MM/YY HH:mm am/pm"), use direct sorting
            // Note: For proper date sorting, these should be converted to timestamps, but for now use direct sort
            return "ORDER BY {$prefix}{$sort_column} {$direction}";
            
        case 'planned_date':
        case 'actual_date':
        case 'task_date':
            // For dates, use COALESCE to handle NULLs (put them last)
            return "ORDER BY COALESCE({$prefix}{$sort_column}, '9999-12-31') {$direction}";
            
        case 'planned_datetime':
        case 'actual_datetime':
            // For datetime fields, combine date and time
            $date_col = str_replace('_datetime', '_date', $sort_column);
            $time_col = str_replace('_datetime', '_time', $sort_column);
            return "ORDER BY COALESCE(CONCAT({$prefix}{$date_col}, ' ', COALESCE({$prefix}{$time_col}, '00:00:00')), '9999-12-31 23:59:59') {$direction}";
            
        case 'description':
        case 'doer_name':
        case 'assigned_by':
        case 'assigner':
        case 'name':
        case 'username':
        case 'email':
        case 'step_name':
        case 'unique_key':
        case 'sheet_label':
        case 'step_code':
            // Text fields - case-insensitive sorting
            return "ORDER BY LOWER(COALESCE({$prefix}{$sort_column}, '')) {$direction}";
            
        case 'unique_id':
        case 'id':
        case 'task_id':
        case 'duration':
        case 'delay_duration':
        case 'shifted_count':
        case 'task_code':
            // Numeric fields
            return "ORDER BY CAST(COALESCE({$prefix}{$sort_column}, 0) AS UNSIGNED) {$direction}";
            
        default:
            // Default: direct column sorting
            return "ORDER BY {$prefix}{$sort_column} {$direction}";
    }
}

/**
 * Get next sort direction for two-state sorting
 * asc → desc → asc (cycle, no default state)
 */
function getNextSortDirection($current_direction) {
    $current = strtolower($current_direction ?? 'asc');
    switch ($current) {
        case 'asc':
            return 'desc';
        case 'desc':
            return 'asc';
        default:
            return 'asc';
    }
}

/**
 * Get sort icon HTML for column header
 * Shows only one icon (chevron-up or chevron-down) per column
 * Active column: full opacity, Inactive columns: faded
 * 
 * @param string $column Current sort column
 * @param string $sort_column Active sort column
 * @param string $sort_direction Current sort direction ('asc' or 'desc')
 * @return string HTML for sort icon
 */
function getSortIcon($column, $sort_column, $sort_direction) {
    $is_active = ($column === $sort_column);
    $direction = strtolower($sort_direction ?? 'asc');
    
    // Determine which icon to show
    // For active column, show the current sort direction
    // For inactive columns, show a default faded icon (chevron-up by default)
    if ($is_active) {
        if ($direction === 'asc') {
            return '<i class="fas fa-chevron-up sort-icon sort-icon-active" title="Sorted Ascending"></i>';
        } else {
            return '<i class="fas fa-chevron-down sort-icon sort-icon-active" title="Sorted Descending"></i>';
        }
    }
    
    // Inactive column: show faded chevron-up icon
    return '<i class="fas fa-chevron-up sort-icon sort-icon-inactive" title="Click to sort"></i>';
}

/**
 * Build sort URL with two-state sorting (asc/desc only)
 * 
 * @param string $column Column to sort by
 * @param string $current_sort_column Currently sorted column
 * @param string $current_sort_direction Current sort direction
 * @param array $preserve_params Parameters to preserve in URL
 * @return string URL with sort parameters
 */
function buildSortUrl($column, $current_sort_column, $current_sort_direction, $preserve_params = []) {
    // Get next sort direction
    if ($current_sort_column === $column) {
        $next_direction = getNextSortDirection($current_sort_direction);
    } else {
        $next_direction = 'asc'; // Start with ascending for new column
    }
    
    // Build query parameters
    $params = array_merge($preserve_params, [
        'sort' => $column,
        'dir' => $next_direction
    ]);
    
    $query_string = http_build_query($params);
    return '?' . $query_string;
}

/**
 * Validate and sanitize sort column
 * 
 * @param string $column Column name to validate
 * @param array $allowed_columns List of allowed columns
 * @param string $default Default column if invalid
 * @return string Validated column name
 */
function validateSortColumn($column, $allowed_columns, $default = '') {
    if (in_array($column, $allowed_columns)) {
        return $column;
    }
    return $default;
}

/**
 * Validate and sanitize sort direction
 * 
 * @param string $direction Direction to validate
 * @return string Validated direction ('asc' or 'desc')
 */
function validateSortDirection($direction) {
    $direction = strtolower($direction ?? '');
    if (in_array($direction, ['asc', 'desc'])) {
        return $direction;
    }
    return 'asc'; // Default to ascending
}

/**
 * Apply smart sorting to array of tasks (for client-side sorting when needed)
 * 
 * @param array $tasks Array of task data
 * @param string $sort_column Column to sort by
 * @param string $sort_direction Sort direction
 * @return array Sorted tasks array
 */
function sortTasksArray($tasks, $sort_column, $sort_direction = 'asc') {
    if (empty($tasks) || empty($sort_column)) {
        return $tasks;
    }
    
    $direction = strtolower($sort_direction) === 'desc' ? -1 : 1;
    
    usort($tasks, function($a, $b) use ($sort_column, $direction) {
        $val_a = $a[$sort_column] ?? null;
        $val_b = $b[$sort_column] ?? null;
        
        // Handle status sorting
        if ($sort_column === 'status') {
            $priority_a = getStatusPriority($val_a);
            $priority_b = getStatusPriority($val_b);
            return ($priority_a <=> $priority_b) * $direction;
        }
        
        // Handle task type sorting
        if ($sort_column === 'task_type') {
            $priority_a = getTaskTypePriority($val_a);
            $priority_b = getTaskTypePriority($val_b);
            return ($priority_a <=> $priority_b) * $direction;
        }
        
        // Handle date sorting
        if (in_array($sort_column, ['planned_date', 'actual_date', 'task_date'])) {
            $timestamp_a = parseDateForSorting($val_a, $a['planned_time'] ?? $a['actual_time'] ?? null);
            $timestamp_b = parseDateForSorting($val_b, $b['planned_time'] ?? $b['actual_time'] ?? null);
            return ($timestamp_a <=> $timestamp_b) * $direction;
        }
        
        // Handle numeric sorting
        if (in_array($sort_column, ['id', 'task_id', 'duration', 'delay_duration', 'shifted_count'])) {
            $num_a = (int)($val_a ?? 0);
            $num_b = (int)($val_b ?? 0);
            return ($num_a <=> $num_b) * $direction;
        }
        
        // Default: string sorting (case-insensitive)
        $str_a = strtolower($val_a ?? '');
        $str_b = strtolower($val_b ?? '');
        return strcmp($str_a, $str_b) * $direction;
    });
    
    return $tasks;
}

