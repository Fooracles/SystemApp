# Manage Tasks Page - Complete Documentation

## 📋 Table of Contents
1. [Page Overview](#page-overview)
2. [Architecture & Structure](#architecture--structure)
3. [Authentication & Authorization](#authentication--authorization)
4. [Database Structure](#database-structure)
5. [Backend Logic](#backend-logic)
6. [Frontend Components](#frontend-components)
7. [JavaScript Functionality](#javascript-functionality)
8. [Styling & Theme](#styling--theme)
9. [API Endpoints](#api-endpoints)
10. [Related Files](#related-files)
11. [Features & Functionality](#features--functionality)
12. [Performance Considerations](#performance-considerations)
13. [Error Handling](#error-handling)
14. [Future Improvements](#future-improvements)

---

## 📄 Page Overview

**File**: `pages/manage_tasks.php`

**Purpose**: Centralized task management interface for viewing, filtering, sorting, and updating tasks across multiple task types (Delegation, Checklist, FMS).

**Target Users**: Administrators and Managers

**Key Features**:
- Unified view of all task types (Delegation, Checklist, FMS)
- Advanced filtering and sorting capabilities
- Real-time status updates via AJAX
- Summary statistics dashboard
- Pagination for large datasets
- Dark theme UI with responsive design

---

## 🏗️ Architecture & Structure

### File Structure
```
pages/manage_tasks.php
├── PHP Backend (Lines 1-516)
│   ├── Session & Authentication (1-22)
│   ├── Message Handling (24-36)
│   ├── Pagination Setup (41-44)
│   ├── Filter Parameters (46-54)
│   ├── Sorting Parameters (56-67)
│   ├── Helper Functions (70-107)
│   ├── Task Fetching Logic (109-297)
│   │   ├── Delegation Tasks (113-167)
│   │   ├── Checklist Tasks (169-203)
│   │   └── FMS Tasks (205-297)
│   ├── Filtering Logic (304-409)
│   ├── Sorting Logic (411-456)
│   └── Statistics Calculation (482-516)
├── CSS Styling (Lines 521-1218)
│   ├── Page Layout
│   ├── Dark Theme Variables
│   ├── Component Styles
│   └── Responsive Design
└── JavaScript (Lines 1721-1988)
    ├── Status Update AJAX
    ├── Filter Toggle
    ├── Tooltip Management
    └── Auto-refresh
```

### Component Hierarchy
```
manage-tasks-page
├── Container Fluid
│   ├── Page Header Card
│   ├── Summary Statistics Cards (4 cards)
│   ├── Alert Messages Section
│   ├── Debug Information (conditional)
│   ├── Filter Section (collapsible)
│   └── Tasks Table Section
│       ├── Table Headers (sortable)
│       ├── Table Body (dynamic rows)
│       ├── Pagination Controls
│       └── Results Info
```

---

## 🔐 Authentication & Authorization

### Access Control

**Required Roles**: `admin` OR `manager`

**Access Logic**:
```php
// Lines 7-22
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

if (!isAdmin() && !isManager()) {
    if (isDoer()) {
        header("Location: doer_dashboard.php");
    } else {
        header("Location: ../index.php");
    }
    exit();
}
```

### Manager Filtering

**Delegation Tasks** (Lines 118-129):
- Managers see tasks where:
  - `manager_id = current_manager_id`
  - `created_by = current_manager_id`
  - `doer_manager_id = current_manager_id`
  - OR doer reports to manager (via subquery)

**FMS Tasks** (Lines 210-217):
- Managers see tasks where:
  - `doer_name` matches users reporting to manager
  - Uses subquery: `SELECT u.username FROM users u WHERE u.manager_id = ?`

**Checklist Tasks**:
- No manager filtering (all managers see all checklist tasks)

---

## 🗄️ Database Structure

### Tables Used

#### 1. `tasks` (Delegation Tasks)
```sql
SELECT 
    t.id, t.unique_id, t.description, 
    t.planned_date, t.planned_time, 
    t.actual_date, t.actual_time, 
    t.status, t.is_delayed, t.delay_duration, 
    t.duration, t.shifted_count, t.assigned_by,
    COALESCE(t.doer_name, u.username, 'N/A') as doer_name, 
    d.name as department_name, 
    m.name as manager_name,
    COALESCE(a.name, a.username, 'N/A') as assigned_by_name
FROM tasks t 
LEFT JOIN users u ON t.doer_id = u.id
LEFT JOIN departments d ON t.department_id = d.id
LEFT JOIN users m ON t.manager_id = m.id
LEFT JOIN users a ON t.assigned_by = a.id
```

#### 2. `checklist_subtasks`
```sql
SELECT 
    cs.id as task_id, 
    cs.task_code as unique_id, 
    cs.task_description as description,
    cs.task_date as planned_date, 
    CONCAT(cs.task_date, ' 23:59:59') as planned_time,
    cs.actual_date, cs.actual_time, 
    COALESCE(cs.status, 'pending') as status,
    cs.is_delayed, cs.delay_duration, cs.duration, 
    cs.assignee as doer_name,
    cs.department as department_name, 
    cs.assigned_by, 
    cs.frequency, 
    'checklist' as task_type
FROM checklist_subtasks cs
WHERE (
    (cs.frequency = 'Daily' AND cs.task_date = ?)
    OR
    (cs.frequency != 'Daily' AND cs.task_date >= ? AND cs.task_date <= ?)
)
```

#### 3. `fms_tasks`
```sql
SELECT 
    id, unique_key, step_name, 
    planned, actual, status, duration, 
    doer_name, department, task_link, 
    sheet_label, step_code, 
    is_delayed, delay_duration, client_name
FROM fms_tasks
```

### Data Normalization

All task types are normalized into a unified array structure:
```php
[
    'id' => integer,
    'unique_id' => string,
    'description' => string,
    'planned_date' => 'Y-m-d',
    'planned_time' => 'H:i:s',
    'actual_date' => 'Y-m-d',
    'actual_time' => 'H:i:s',
    'status' => string,
    'is_delayed' => 0|1,
    'delay_duration' => string,
    'duration' => string,
    'doer_name' => string,
    'department_name' => string,
    'assigned_by' => string,
    'task_type' => 'delegation'|'checklist'|'fms'
]
```

---

## ⚙️ Backend Logic

### Task Fetching Strategy

#### 1. Delegation Tasks (Lines 113-167)

**Frequency**: All tasks (no date filtering)

**Manager Filtering**:
```php
WHERE (t.manager_id = ? 
    OR t.created_by = ? 
    OR t.doer_manager_id = ? 
    OR COALESCE(t.doer_name, u.username, 'N/A') 
        IN (SELECT username FROM users WHERE manager = ?))
```

**Order**: `ORDER BY t.planned_date DESC, t.id DESC`

#### 2. Checklist Tasks (Lines 169-203)

**Frequency-Based Filtering**:
- **Daily**: Show only if `task_date = today`
- **Other frequencies**: Show if `task_date` is within current week

**Date Range**:
```php
$today = date('Y-m-d');
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));
```

**Order**: `ORDER BY cs.task_date DESC, cs.id DESC`

#### 3. FMS Tasks (Lines 205-297)

**Special Processing**:
- Date parsing: `parseFMSDateTimeString_doer()` handles format `"Sep 05, 2025 04:30 PM"`
- Delay calculation:
  - **Completed tasks**: Compare `actual` vs `planned`
  - **Pending tasks**: Compare `current_time` vs `planned`
- Uses `formatSecondsToHHMMSS()` for delay formatting

**Manager Filtering**:
```php
WHERE fms_tasks.doer_name IN (
    SELECT u.username FROM users u WHERE u.manager_id = ?
)
```

### Filtering Logic (Lines 304-409)

All filters use PHP `array_filter()` with closure functions:

**Status Filter**:
- Special handling for FMS tasks (maps `done` → `completed`)
- Considers actual data presence for FMS tasks
- Normalizes status values (case-insensitive)

**Doer Filter**:
- Uses `stripos()` for case-insensitive partial matching
- Excludes tasks without doer names

**Date Filters**:
- `date_from`: `task['planned_date'] >= filter_date_from`
- `date_to`: `task['planned_date'] <= filter_date_to`
- Excludes tasks without planned dates

**Type Filter**:
- Direct comparison: `task['task_type'] === filter_type`

### Sorting Logic (Lines 411-456)

**Custom Sort Function**:
```php
function customSort($a, $b, $column, $direction) {
    // Handles null values
    // Converts dates to timestamps
    // Converts delay strings to seconds
    // Returns comparison result
}
```

**Supported Columns**:
- `unique_id`, `description`, `assigned_by`
- `planned_date`, `actual_date` (converted to timestamps)
- `status`, `delay_duration` (converted to seconds)
- `duration`, `doer_name`

**Delay String Parsing**:
```php
function convertDelayToSeconds($delay_str) {
    // Parses "2 days, 3 hours, 15 minutes"
    // Returns total seconds for comparison
}
```

### Delay Calculation

**FMS Tasks** (Lines 250-273):
```php
if ($is_task_completed) {
    // Compare actual vs planned
    if ($actual_timestamp > $planned_timestamp) {
        $delay_seconds = $actual_timestamp - $planned_timestamp;
        $delay_duration = formatSecondsToHHMMSS($delay_seconds);
    }
} else {
    // Compare current time vs planned
    if ($current_time > $planned_timestamp) {
        $delay_seconds = $current_time - $planned_timestamp;
        $delay_duration = formatSecondsToHHMMSS($delay_seconds);
    }
}
```

**Global Update** (Line 300):
- Calls `updateAllTasksDelayStatus($conn)` after fetching all tasks
- Updates delay status in database

### Statistics Calculation (Lines 482-496)

```php
$summary_stats = [
    'total_tasks' => count($filtered_tasks),
    'completed_tasks' => count(array_filter($filtered_tasks, function($task) {
        return in_array(strtolower($task['status']), ['completed', 'done']);
    })),
    'delayed_tasks' => count(array_filter($filtered_tasks, function($task) {
        return $task['is_delayed'] == 1 || !empty($task['delay_duration']);
    })),
    'completion_rate' => round(($completed_tasks / $total_tasks) * 100, 2)
];
```

### Pagination (Lines 458-461)

```php
$tasks_per_page = 15;
$current_page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page_num - 1) * $tasks_per_page;
$total_pages = ceil($total_filtered_tasks / $tasks_per_page);
$paginated_tasks = array_slice($filtered_tasks, $offset, $tasks_per_page);
```

---

## 🎨 Frontend Components

### Summary Statistics Cards (Lines 1244-1289)

Four metric cards displaying:
1. **Total Tasks in View** (`$summary_stats['total_tasks']`)
2. **Completed Tasks** (`$summary_stats['completed_tasks']`)
3. **Total Delayed Tasks** (`$summary_stats['delayed_tasks']`)
4. **Completion Rate** (`$summary_stats['completion_rate']`%)

**Styling Classes**:
- `.stats-card.total-tasks`
- `.stats-card.completed-tasks`
- `.stats-card.delayed-tasks`
- `.stats-card.completion-rate`

### Filter Section (Lines 1333-1423)

**Collapsible Design**:
- Header with toggle button
- Content section with `collapsed` class for hiding
- Uses CSS `max-height: 0` transition

**Filter Fields**:
1. **Task ID**: Text input (`name="task_id"`)
2. **Task Name**: Text input (`name="task_name"`)
3. **Doer**: Select dropdown (populated from `$doers` array)
4. **Status**: Select dropdown (Pending, Completed, Not Done, Can't Be Done, Shifted)
5. **Type**: Select dropdown (Delegation, FMS, Checklist)
6. **Date From**: Date input
7. **Date To**: Date input
8. **Filter/Reset Buttons**

**Form Action**: `GET` method (query string parameters)

### Tasks Table (Lines 1425-1676)

**Table Structure**:
```html
<table id="manage-tasks-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Description</th>
            <th>ASSIGNER</th>
            <th>Planned</th>
            <th>Actual</th>
            <th>Status</th>
            <th>Delay</th>
            <th>Duration</th>
            <th>Doer</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <!-- Dynamic rows -->
    </tbody>
</table>
```

**Sortable Headers**:
- All headers link to same page with `sort` and `dir` parameters
- Preserves existing filter parameters via `http_build_query($_GET)`

**Row Data Attributes**:
- `data-task-id`: Task ID
- `data-task-type`: Task type (delegation/checklist/fms)

**Cell Content**:

1. **ID Column**:
   - Task unique ID
   - Badge showing task type

2. **Description Column**:
   - Truncated to 50 characters
   - Full text in `data-full-description` attribute
   - Hover tooltip shows full description

3. **Assigner Column**:
   - Delegation: `assigned_by_name`
   - FMS: `client_name`
   - Checklist: `assigned_by`

4. **Planned Date Column**:
   - Checklist: `d M Y` format
   - Others: `M d, Y` with time `H:i`

5. **Actual Date Column**:
   - `M d, Y` with time `H:i`
   - Shows `-` if empty

6. **Status Column**:
   - Uses `get_status_column_cell()` helper function
   - FMS tasks: Custom logic (✅/⏳ based on actual data)
   - Icons: ⏳ (Pending), ✅ (Completed), ❌ (Not Done), ⛔ (Can't Be Done), 🔁 (Shifted)

7. **Delay Column**:
   - If delayed: Truncated delay with hover tooltip
   - If completed on time: "On Time" (green)
   - If pending/no delay: "N/A" (muted)

8. **Duration Column**:
   - Supports two formats:
     - **New**: Minutes stored as integer (> 100) → `formatMinutesToHHMMSS()`
     - **Old**: Decimal hours (≤ 100) → `formatDecimalDurationToHHMMSS()`
   - Display: `HH:MM:00` format

9. **Doer Column**:
   - `doer_name` value

10. **Action Column**:
    - FMS tasks: External link button (opens `task_link`)
    - Other tasks: Status dropdown select
    - Dropdown options: Pending, Completed, Not Done, Can't Be Done

### Pagination Controls (Lines 1678-1707)

**Features**:
- Shows previous/next buttons
- Displays 5 page numbers around current page (current ± 2)
- Preserves filter parameters in URLs
- Only shows if `$total_pages > 1`

**Format**:
```
[<] [1] [2] [3] [4] [5] [>]
```

### Results Info (Lines 1709-1712)

Displays: `"Showing X of Y tasks"`

---

## 💻 JavaScript Functionality

### Status Update AJAX (Lines 1743-1805)

**Flow**:
1. User changes status dropdown
2. Confirmation dialog
3. Loading state (disabled dropdown, "Updating..." text)
4. POST request to `action_update_task_status.php`
5. Response handling:
   - Success: Update row, show success alert
   - Error: Revert dropdown, show error alert

**Request Format**:
```javascript
fetch('action_update_task_status.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `task_id=${taskId}&task_type=${taskType}&status=${newStatus}&action=update_status`
})
```

**Response Format**:
```json
{
    "status": "success|error",
    "message": "Status update message",
    "new_status_icon": "<span>...</span>",
    "new_status_text": "Completed",
    "updated_actual_display": "Jan 15, 2024 14:30",
    "updated_delay_display_html": "<span>...</span>"
}
```

**Row Update Function** (`updateTaskRow`, Lines 1819-1883):
- Updates status icon HTML
- Updates actual date/time cell
- Updates delay duration cell

### Filter Toggle (Lines 1905-1923)

**Functionality**:
- Toggles `.collapsed` class on `#filterContent`
- Changes chevron icon (up/down)
- Changes button text ("Show Filters" / "Hide Filters")

**CSS Transition**:
```css
.filter-content.collapsed {
    max-height: 0;
    padding: 0;
    margin: 0;
}
```

### Tooltip Management (Lines 1950-1987)

**Delay Tooltips**:
- Reads `data-full-delay` attribute
- Converts format via `convertDelayToFullFormat()`
- Sets `title` attribute for browser tooltip

**Description Tooltips**:
- Reads `data-full-description` attribute
- Converts newlines to `<br>` for tooltip
- Sets `title` attribute

**Conversion Functions**:
```javascript
function convertDelayToFullFormat(delay) {
    return delay
        .replace(/(\d+)\s*D\b/g, '$1 days')
        .replace(/(\d+)\s*d\b/g, '$1 days')
        .replace(/(\d+)\s*h\b/g, '$1 hours')
        .replace(/(\d+)\s*m\b/g, '$1 minutes');
}
```

### Auto-Refresh (Lines 1926-1933)

**Conditional Refresh**:
- Runs every 30 seconds
- Only refreshes if no filters are applied
- Checks: `search` input empty AND `status` select empty

### Date Picker Enhancement (Lines 1935-1948)

**Features**:
- Makes date inputs clickable
- Calls `showPicker()` on click/focus
- Applied to `.date-picker-clickable` class

### Alert Auto-Dismiss (Lines 1807-1816)

**Behavior**:
- Alerts fade out after 5 seconds
- Uses CSS `fade` class
- Removes from DOM after animation

---

## 🎨 Styling & Theme

### CSS Variables (Referenced from Global Styles)

```css
--dark-bg-primary
--dark-bg-secondary
--dark-bg-tertiary
--dark-bg-card
--dark-bg-glass
--dark-bg-glass-hover
--dark-text-primary
--dark-text-secondary
--dark-text-muted
--glass-border
--glass-shadow
--glass-blur
--brand-primary
--brand-success
--brand-danger
--brand-warning
--brand-accent
--gradient-primary
--gradient-secondary
--gradient-accent
--radius-sm
--radius-md
--radius-lg
--transition-normal
```

### Component Styles

#### Summary Cards (Lines 529-596)
- Glass-morphism effect with backdrop-filter
- Gradient backgrounds
- Hover animations (translateY, shadow)
- Shimmer effect on hover (::before pseudo-element)

#### Filter Section (Lines 691-837)
- Dark card background
- Collapsible content with smooth transition
- Form controls with dark theme
- Consistent button styling

#### Table (Lines 614-649)
- Dark theme headers and rows
- Hover effects on rows
- Sortable header links
- Responsive design

#### Tooltips (Lines 1001-1103)
- Custom CSS tooltips using `::after` and `::before`
- Dark gradient background
- Arrow pointer
- Fade-in animation

#### Responsive Design (Lines 1130-1152)
- Mobile breakpoint: `@media (max-width: 768px)`
- Adjusted font sizes
- Reduced padding/margins
- Stacked layout

---

## 🔌 API Endpoints

### Status Update Endpoint

**File**: `pages/action_update_task_status.php`

**Method**: POST

**Parameters**:
- `task_id` (int): Task ID
- `task_type` (string): 'delegation' | 'checklist' | 'fms'
- `status` (string): New status value
- `action` (string): 'update_status'

**Response**:
```json
{
    "status": "success|error",
    "message": "Status updated successfully",
    "new_status_icon": "<span class='status-icon'>✅</span>",
    "new_status_text": "Completed",
    "updated_actual_display": "Jan 15, 2024 14:30",
    "updated_delay_display_html": "<span class='text-success'>On Time</span>"
}
```

**Error Response**:
```json
{
    "status": "error",
    "message": "Error description"
}
```

---

## 📁 Related Files

### Core Includes

1. **`includes/header.php`**
   - Navigation, sidebar
   - Global CSS/JS includes
   - Session initialization

2. **`includes/footer.php`**
   - Page footer
   - Closing HTML tags

3. **`includes/config.php`**
   - Database connection (`$conn`)
   - Configuration constants

4. **`includes/functions.php`**
   - `isLoggedIn()`, `isAdmin()`, `isManager()`, `isDoer()`
   - `formatDelayForDisplay()`
   - `formatSecondsToHHMMSS()`
   - `formatMinutesToHHMMSS()`
   - `formatDecimalDurationToHHMMSS()`
   - `updateAllTasksDelayStatus()`
   - `parseFMSDateTimeString_doer()`

5. **`includes/status_column_helpers.php`**
   - `get_status_column_cell()`: Returns status icon HTML
   - `get_status_icon()`: Returns status icon span
   - `get_status_display_text()`: Returns status text

### AJAX Handler

**`pages/action_update_task_status.php`**
- Processes status update requests
- Validates user permissions
- Updates database
- Calculates new delay/actual dates
- Returns JSON response

### Database Tables

1. **`tasks`**: Delegation tasks
2. **`checklist_subtasks`**: Checklist tasks
3. **`fms_tasks`**: FMS tasks (imported from Google Sheets)
4. **`users`**: User information and roles
5. **`departments`**: Department information

### CSS Files

1. **`assets/css/style.css`**: Global dark theme styles
2. **Inline Styles** (Lines 521-1218): Page-specific styles

### JavaScript Libraries

1. **jQuery**: DOM manipulation, event handling
2. **Bootstrap**: UI components, alerts, modals
3. **Font Awesome**: Icons

---

## ✨ Features & Functionality

### Task Types

#### 1. Delegation Tasks
- Created manually via `add_task.php`
- Full CRUD operations
- Status management
- Delay tracking

#### 2. Checklist Tasks
- Recurring tasks with frequencies (Daily, Weekly, Monthly, etc.)
- Frequency-based date filtering
- Status management
- Delay tracking

#### 3. FMS Tasks
- Imported from Google Sheets
- Link to original Google Sheet
- Custom status icons (✅/⏳)
- Delay calculation based on actual data presence

### Filtering Features

1. **Multi-Criteria Filtering**:
   - Task ID, Name, Doer, Status, Type, Date Range
   - Filters can be combined
   - Case-insensitive text matching

2. **Filter Persistence**:
   - All filters preserved in URL query string
   - Maintained across pagination
   - Maintained across sorting

3. **Collapsible Filter UI**:
   - Saves screen space
   - Smooth expand/collapse animation

### Sorting Features

1. **Multi-Column Sorting**:
   - All data columns sortable
   - Ascending/Descending toggle
   - URL-based sorting (shareable links)

2. **Smart Sorting**:
   - Dates converted to timestamps
   - Delays converted to seconds
   - Null value handling

### Status Management

1. **Real-Time Updates**:
   - AJAX-based status changes
   - No page refresh required
   - Instant visual feedback

2. **Status Types**:
   - ⏳ Pending
   - ✅ Completed
   - ❌ Not Done
   - ⛔ Can't Be Done
   - 🔁 Shifted

3. **Auto-Calculations**:
   - Actual date/time on completion
   - Delay recalculation on status change
   - "On Time" display for completed tasks

### Summary Statistics

1. **Real-Time Metrics**:
   - Total tasks count
   - Completed tasks count
   - Delayed tasks count
   - Completion rate percentage

2. **Dynamic Updates**:
   - Stats reflect current filters
   - Updates on filter/pagination changes

### User Experience Features

1. **Tooltips**:
   - Delay duration full text
   - Description full text
   - Status explanations

2. **Responsive Design**:
   - Mobile-friendly layout
   - Touch-friendly controls
   - Adaptive table display

3. **Visual Feedback**:
   - Loading states
   - Success/error alerts
   - Hover effects
   - Active states

---

## ⚡ Performance Considerations

### Optimization Strategies

1. **Database Queries**:
   - Uses prepared statements for security
   - Separate queries per task type (allows independent optimization)
   - Indexed columns used in WHERE clauses

2. **Memory Management**:
   - Tasks fetched into array, then filtered in PHP
   - Pagination reduces rendered rows (15 per page)
   - Array filtering uses closures (efficient)

3. **Caching Opportunities**:
   - Summary statistics could be cached
   - Filter dropdown values recalculated each request
   - No current caching implementation

### Performance Bottlenecks

1. **Large Datasets**:
   - All tasks loaded into memory before filtering
   - Consider database-level filtering for very large datasets

2. **Multiple Queries**:
   - Three separate queries (one per task type)
   - Could be optimized with UNION queries

3. **Client-Side Filtering**:
   - Filtering done in PHP after fetching
   - Could use database WHERE clauses for better performance

### Recommendations

1. **Database Indexing**:
   - Index `planned_date`, `status`, `doer_name`, `manager_id`
   - Composite indexes for common filter combinations

2. **Query Optimization**:
   - Use UNION for combined task fetching
   - Add LIMIT clauses with OFFSET for pagination
   - Consider stored procedures for complex filters

3. **Caching**:
   - Cache summary statistics (refresh every 5 minutes)
   - Cache filter dropdown values (refresh daily)
   - Use Redis/Memcached for session-based caching

---

## 🛡️ Error Handling

### Try-Catch Block (Lines 112-516)

```php
try {
    // Task fetching and processing
} catch (Exception $e) {
    $error_message = $e->getMessage();
    // Set default empty values
    $total_tasks = 0;
    $total_pages = 0;
    $tasks = [];
    // ... other defaults
}
```

### Error Display

**Success Messages**:
- Displayed via `$_SESSION['manage_tasks_success_msg']`
- Green alert box with dismiss button

**Error Messages**:
- Displayed via `$_SESSION['manage_tasks_error_msg']`
- Red alert box with dismiss button
- Also displayed for database errors in catch block

### AJAX Error Handling

**Client-Side** (Lines 1788-1793):
```javascript
.catch(error => {
    console.error('Error:', error);
    showAlert('Error updating task status. Please try again.', 'danger');
    this.value = originalValue; // Revert dropdown
});
```

**Server-Side** (`action_update_task_status.php`):
- Output buffering to prevent JSON corruption
- Error logging to file
- JSON error responses

### Validation

1. **Sort Parameters** (Lines 60-67):
   - Whitelist allowed columns
   - Whitelist allowed directions
   - Defaults to safe values if invalid

2. **Pagination** (Line 43):
   - Cast to integer
   - Prevents SQL injection

3. **SQL Injection Prevention**:
   - Prepared statements for all queries
   - Parameter binding
   - Input sanitization via `htmlspecialchars()`

---

## 🔮 Future Improvements

### Suggested Enhancements

1. **Advanced Filtering**:
   - Date range presets (Today, This Week, This Month)
   - Saved filter presets
   - Multi-select for status/type filters

2. **Export Functionality**:
   - Export filtered results to CSV/Excel
   - PDF report generation
   - Email reports

3. **Bulk Operations**:
   - Bulk status updates
   - Bulk assignment changes
   - Bulk deletion (with confirmation)

4. **Enhanced Search**:
   - Full-text search across all fields
   - Search history
   - Saved searches

5. **Analytics Dashboard**:
   - Task completion trends
   - Delay analysis charts
   - Performance metrics over time

6. **Real-Time Updates**:
   - WebSocket integration for live updates
   - Browser notifications for task changes
   - Auto-refresh indicators

7. **Accessibility**:
   - ARIA labels for screen readers
   - Keyboard navigation support
   - High contrast mode

8. **Internationalization**:
   - Multi-language support
   - Date/time format localization
   - Currency/unit localization

---

## 📝 Code Examples

### Adding a New Filter

```php
// In backend (around line 54)
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : '';

// In filtering logic (around line 409)
if (!empty($filter_priority)) {
    $filtered_tasks = array_filter($filtered_tasks, function($task) use ($filter_priority) {
        return ($task['priority'] ?? '') === $filter_priority;
    });
}

// In HTML form (around line 1410)
<div class="col-md-3">
    <label class="form-label small text-muted">Priority</label>
    <select class="form-control form-control-sm" name="priority">
        <option value="">All Priorities</option>
        <option value="high" <?php echo ($filter_priority === 'high') ? 'selected' : ''; ?>>High</option>
        <option value="medium" <?php echo ($filter_priority === 'medium') ? 'selected' : ''; ?>>Medium</option>
        <option value="low" <?php echo ($filter_priority === 'low') ? 'selected' : ''; ?>>Low</option>
    </select>
</div>
```

### Adding a New Task Type

```php
// In task fetching section (after line 297)
// 4. Fetch Custom Tasks
$custom_query = "SELECT id, unique_id, description, ... FROM custom_tasks";
$custom_result = mysqli_query($conn, $custom_query);
if ($custom_result) {
    while ($row = mysqli_fetch_assoc($custom_result)) {
        $custom_task = [
            'id' => $row['id'],
            'unique_id' => $row['unique_id'],
            'description' => $row['description'],
            // ... other fields
            'task_type' => 'custom'
        ];
        $all_tasks[] = $custom_task;
    }
}

// In type filter dropdown (around line 1393)
<option value="custom" <?php echo (($filter_type ?? '') === 'custom') ? 'selected' : ''; ?>>Custom</option>
```

---

## 🎯 Summary

The `manage_tasks.php` page is a comprehensive task management interface that:

- **Unifies** three different task types (Delegation, Checklist, FMS) into a single view
- **Provides** advanced filtering, sorting, and pagination capabilities
- **Implements** role-based access control with manager filtering
- **Offers** real-time status updates via AJAX
- **Displays** summary statistics and detailed task information
- **Uses** a modern dark theme with responsive design
- **Handles** errors gracefully with user-friendly messages

The page is well-structured, maintainable, and follows PHP best practices for security and performance. It serves as the central hub for task management in the FMS application.

---

**Document Version**: 1.0  
**Last Updated**: 2024  
**Author**: AI Documentation Generator  
**File Path**: `pages/manage_tasks.php`

