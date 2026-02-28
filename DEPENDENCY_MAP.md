# Dependency & Risk-Impact Map

> **Project:** app-v8.1  
> **Generated:** February 21, 2026  
> **Total Files:** 14 includes · 41 pages · 41 ajax handlers

---

## Table of Contents

1. [Core File Dependency Tree](#1-core-file-dependency-tree)
2. [Include File Summaries](#2-include-file-summaries)
3. [Risk-Impact Matrix](#3-risk-impact-matrix)
4. [Dependency Fan-Out](#4-dependency-fan-out-who-depends-on-what)
5. [Page Dependencies](#5-page-dependencies)
6. [Ajax Handler Dependencies](#6-ajax-handler-dependencies)
7. [External CDN Dependencies](#7-external-cdn-dependency-risk)
8. [Critical Dependency Chains](#8-critical-dependency-chains-cascading-failure-paths)
9. [Anomalies & Architectural Risks](#9-anomalies--architectural-risks)
10. [Recommended Priority Actions](#10-recommended-priority-actions)

---

## 1. Core File Dependency Tree

```
config.php (107 lines)
├── error_handler.php
├── file_uploader.php
├── db_functions.php
└── db_schema.php

functions.php (1,392 lines)
└── (standalone — no internal requires)

header.php (987 lines)
├── config.php  ──► (error_handler, file_uploader, db_functions, db_schema)
├── functions.php
└── sidebar.php

footer.php (159 lines)
└── (standalone — closes layout opened by header.php)
```

Every page that does `require header.php` transitively loads **6 files**:  
`config → error_handler → file_uploader → db_functions → db_schema`, plus `functions` and `sidebar`.

---

## 2. Include File Summaries

### config.php (~107 lines)
Bootstrap file: loads error handler and file uploader, sets timezone and session path, detects environment (localhost vs production), defines DB credentials and constants (Google Sheets, leave module), connects to MySQL, registers shutdown to close DB, and ensures DB tables exist via `db_schema`/`db_functions`.

### functions.php (~1,392 lines)
Core application helpers: session management, authentication (`isLoggedIn`, `isAdmin`, `isManager`, `isDoer`, `isClient`), redirects, access control, UI helpers, task/delay/date logic, checklist/FMS helpers, notifications, session logging, week/pending/delayed logic, and CSRF protection. Contains **60+ exported functions**.

### header.php (~987 lines)
Page shell for authenticated users: requires config + functions, enforces login redirect, runs 8 PM auto-logout and inactive-user check, loads today's special events and notification triggers, then outputs HTML head, CSRF meta, CSS, and the main layout (sidebar, sticky header with notifications/forms/profile, content area, Raise Ticket + Meeting modals and their scripts).

### footer.php (~159 lines)
Closes the main content wrapper and app-frame divs for logged-in users, outputs the global confirmation modal, and runs session/auto-logout and toast scripts.

### sidebar.php (~257 lines)
Renders the collapsible sidebar nav: logo, dashboard link by role, and role-based links (My Tasks, Task Panel, Client Portal, Tools, Records, FMS Builder, My Meetings, Leave, Holiday List).

### db_functions.php (~489 lines)
DB utilities and migrations: table/column/index/foreign-key checks and non-destructive schema updates. Key functions: `tableExists`, `columnExists`, `indexExists`, `ensureFmsTasksColumns`, `ensureUsersColumns`, `ensureTasksColumns`, `checkAndCreateTables`, `ensureDatabaseTables`.

### db_schema.php (~479 lines)
Central schema definitions: `$DB_TABLES` with CREATE statements for all tables (departments, users, tasks, forms, FMS, holidays, checklist, leave, notes, URLs, meetings, notifications, user_sessions, reports, updates, flow builder, file_upload_audit) and `$TABLE_CREATION_ORDER`.

### notification_triggers.php (~756 lines)
Event-driven notification creation: meeting request/reschedule, day-special (birthdays/anniversaries), notes reminder, leave request, task-delay warnings, overdue checklist, and client update/requirement/report/ticket/task notifications.

### dashboard_components.php (~1,048 lines)
Shared dashboard logic: task status/date helpers, personal and global task stats, performance snapshots, leaderboard, team stats, RQC score, performance rate, and team availability.

### error_handler.php (~283 lines)
Central error handling: env-based display (dev vs production), safe JSON error/success and DB/exception/connection handlers, global exception/error/shutdown handlers. Defines `IS_PRODUCTION`.

### file_uploader.php (~131 lines)
Secure upload pipeline: MIME/ext whitelist, blocked extensions, double-extension and PHP-code checks, size limits per context, safe filenames, and audit logging. Key functions: `secureUpload`, `secureBase64Upload`.

### status_column_helpers.php (~166 lines)
Status column UI and validation: icon + tooltip, display text, "can't be done" variants, table header/cell HTML. Key functions: `get_status_icon`, `get_status_column_cell`, `get_available_statuses`, `is_valid_status`.

### sorting_helpers.php (~331 lines)
Table sorting: status/task-type priority, date parsing, SQL ORDER BY builder, sort direction/URL/icon, validation, and array sorting. Key functions: `buildSmartOrderBy`, `sortTasksArray`, `getSortIcon`, `buildSortUrl`.

### google_sheets_client.php (~234 lines)
Google Sheets API via service account: read, append, update, metadata, and access check. Class `GoogleSheetsClient` with global wrappers: `gs_list`, `gs_append`, `gs_update`, `gs_get_sheet_info`, `gs_check_access`.

---

## 3. Risk-Impact Matrix

| Risk Level | File | Lines | Depended On By | Impact If Broken |
|:---:|---|:---:|:---:|---|
| **CRITICAL** | `config.php` | 107 | All 76 pages + ajax | DB connection, constants, sessions — entire app goes down |
| **CRITICAL** | `functions.php` | 1,392 | All 76 pages + ajax | Auth, CSRF, session, redirects, helpers — entire app breaks |
| **CRITICAL** | `header.php` | 987 | 35 pages | Layout, nav, CSS/JS loading — all page views break |
| **HIGH** | `db_schema.php` | 479 | Via config.php | Tables won't create/validate — new installs and migrations fail |
| **HIGH** | `db_functions.php` | 489 | Via config.php | Column migrations fail — schema drift between environments |
| **HIGH** | `error_handler.php` | 283 | Via config.php | Unhandled exceptions leak to users in production |
| **HIGH** | `dashboard_components.php` | 1,048 | 10 files | All dashboards (admin, manager, doer), team perf, leave, leaderboard break |
| **MEDIUM** | `notification_triggers.php` | 756 | 4 ajax files | Notifications stop firing for meetings, tasks, tickets, leave |
| **MEDIUM** | `status_column_helpers.php` | 166 | 8 pages | Status columns render incorrectly across task/checklist pages |
| **MEDIUM** | `sorting_helpers.php` | 331 | 7 pages + 1 ajax | Table sorting breaks on manage_tasks, my_task, checklist, FMS, meetings |
| **MEDIUM** | `sidebar.php` | 257 | Via header.php | Navigation disappears on all pages |
| **MEDIUM** | `footer.php` | 159 | 30+ pages | Layout breaks (unclosed divs), session toast scripts missing |
| **LOW** | `file_uploader.php` | 131 | Via config.php | File uploads fail (task_ticket, updates attachments) |
| **LOW** | `google_sheets_client.php` | 234 | 1 ajax file | Leave sync to Google Sheets fails |

---

## 4. Dependency Fan-Out (Who Depends on What)

Most depended-on files (highest risk at top):

```
config.php                  ← 76 files (ALL pages + ajax)
functions.php               ← 76 files (ALL pages + ajax)
header.php                  ← 35 page files
footer.php                  ← 30+ page files
dashboard_components.php    ← 10 files (6 ajax + 4 pages)
status_column_helpers.php   ← 8 page files
sorting_helpers.php         ← 7 pages + 1 ajax
notification_triggers.php   ← 4 ajax files
db_functions.php            ← 2 direct + all via config
google_sheets_client.php    ← 1 file (leave_status_action.php)
```

---

## 5. Page Dependencies

| Page | Depends On |
|---|---|
| `admin_dashboard.php` | header, status_column_helpers, dashboard_components |
| `manager_dashboard.php` | header, status_column_helpers, dashboard_components |
| `doer_dashboard.php` | header, status_column_helpers, dashboard_components, footer |
| `client_dashboard.php` | header, footer |
| `manage_tasks.php` | header, status_column_helpers, db_functions, sorting_helpers, footer |
| `my_task.php` | header, status_column_helpers, sorting_helpers, notification_triggers, db_functions, footer |
| `task_ticket.php` | header, footer |
| `checklist_task.php` | config, functions, header, sorting_helpers, status_column_helpers |
| `fms_task.php` | header, status_column_helpers, sorting_helpers, footer |
| `team_performance.php` | config, functions, dashboard_components, header, status_column_helpers, footer |
| `team_performance_overview.php` | config, functions, header, status_column_helpers, dashboard_components, footer |
| `leave_request.php` | header, dashboard_components, sorting_helpers, footer |
| `report.php` | header, footer |
| `manage_forms.php` | header, footer |
| `manage_apis.php` | config, functions, header, footer |
| `manage_users.php` | header, footer |
| `manage_client_users.php` | config, functions, header, footer |
| `manage_sheets.php` | header, footer |
| `add_task.php` | config, functions, status_column_helpers, sorting_helpers, header |
| `add_sheet.php` | header, footer |
| `admin_my_meetings.php` | header, sorting_helpers, footer |
| `login.php` | config, functions |
| `logged_in.php` | header, footer |
| `profile.php` | header, footer |
| `updates.php` | header, footer |
| `my_notes.php` | header, footer |
| `useful_urls.php` | header, footer |
| `holiday_list.php` | header |
| `api_documentation.php` | header, footer |
| `api_analytics.php` | header, footer |
| `view_sheet_data.php` | header, footer |
| `action_update_task_status.php` | status_column_helpers, config, functions |
| `action_update_checklist_status.php` | config, functions, status_column_helpers |
| `action_update_task_edit.php` | config, functions |
| `action_update_checklist_edit.php` | config, functions |
| `action_update_user_status.php` | config, functions |
| `action_toggle_priority.php` | config, functions, db_functions |
| `action_shift_task.php` | config, functions |
| `action_doer_complete_task.php` | config, functions |
| `fms_builder.php` | _(no PHP includes)_ |
| `shift_task_modal.php` | _(no PHP includes)_ |

---

## 6. Ajax Handler Dependencies

| Ajax Handler | Depends On |
|---|---|
| `admin_dashboard_data.php` | config, functions, dashboard_components |
| `manager_dashboard_data.php` | config, functions, dashboard_components |
| `doer_dashboard_data.php` | config, functions, dashboard_components |
| `client_dashboard_data.php` | config, functions |
| `team_performance_data.php` | config, functions, dashboard_components |
| `team_performance_overview_data.php` | config, functions, dashboard_components |
| `get_performance_users.php` | config, functions, dashboard_components |
| `get_team_performance_members.php` | config, functions, dashboard_components |
| `leave_fetch_pending.php` | config, functions, dashboard_components |
| `task_ticket_handler.php` | config, functions, notification_triggers |
| `updates_handler.php` | config, functions, db_functions, notification_triggers |
| `notification_actions.php` | config, functions, notification_triggers |
| `meeting_handler.php` | config, functions, sorting_helpers, notification_triggers |
| `leave_status_action.php` | config, google_sheets_client, functions |
| `reports_handler.php` | config, functions, notification_triggers |
| `fms_sheet_handler.php` | config, functions, pages/fms_task.php |
| `notifications_handler.php` | config, functions |
| `get_notifications.php` | config, functions |
| `clear_all_notifications.php` | config, functions |
| `notes_handler.php` | config, functions |
| `urls_handler.php` | config, functions |
| `holiday_handler.php` | config, functions, vendor/autoload.php |
| `sessions_handler.php` | config, functions |
| `get_doers.php` | config, functions |
| `get_new_delegation_tasks.php` | config, functions |
| `delegation_tasks_sse.php` | config, functions |
| `approve_password_reset.php` | config, functions |
| `reject_password_reset.php` | config, functions |
| `get_user_motivation.php` | config, functions |
| `update_user_motivation.php` | config, functions |
| `update_delays.php` | config, functions |
| `ajax_fetch_sheet_data.php` | config, functions |
| `leave_fetch_employee_names.php` | config, functions |
| `leave_fetch_totals.php` | config, functions |
| `leave_metrics.php` | config |
| `freeze_weekly_performance.php` | _(no includes)_ |
| `rqc_auto_sync.php` | _(no includes)_ |
| `leave_auto_sync.php` | _(no includes)_ |
| `clear_leave_data.php` | _(no includes)_ |
| `fms_flow_handler.php` | _(no includes)_ |
| `get_last_sync_time.php` | _(no includes)_ |

---

## 7. External CDN Dependency Risk

### CDN Scripts

| CDN / Provider | Files Using It | Risk |
|---|---|---|
| **jQuery 3.6.0** (code.jquery.com) | header.php + 3 pages | **HIGH** — Bootstrap JS, Select2, custom JS all depend on it |
| **Bootstrap 4.5.2 JS** (stackpath) | header.php + 4 pages | **HIGH** — modals, dropdowns, tooltips break without it |
| **Bootstrap 4.5.2 CSS** (stackpath) | header.php + 4 pages | **HIGH** — entire responsive layout depends on it |
| **Font Awesome 6.0.0** (cdnjs) | header.php | **MEDIUM** — icons disappear but app functions |
| **Font Awesome 5.15.4** (cdnjs) | admin_dashboard, login, checklist_task, holiday_list | **MEDIUM** — icons disappear on those pages |
| **TailwindCSS CDN** (cdn.tailwindcss.com) | task_ticket.php only | **MEDIUM** — runtime compiler; fragile; conflicts with Bootstrap |
| **Alpine.js 3.13.3** (jsdelivr) | task_ticket.php, logged_in.php | **MEDIUM** — Action Center won't render without it |
| **Select2 4.1.0** (jsdelivr) | header.php, checklist_task, leave_request | **LOW** — dropdowns lose search; still functional |
| **Popper.js** (jsdelivr) | header.php + 3 pages | **LOW** — tooltip/dropdown positioning breaks |
| **Chart.js** (jsdelivr) | team_performance, api_analytics, team_performance_overview | **LOW** — charts break but pages still load |
| **Sortable.js 1.15.0** (jsdelivr) | my_notes, useful_urls | **LOW** — drag-and-drop reordering breaks |
| **Vanilla Tilt 1.8.0** (jsdelivr) | updates.php | **LOW** — visual effect only |
| **Quill Editor 1.3.6** (quilljs) | my_notes.php | **LOW** — rich text editing breaks on notes only |
| **Lucide Icons** (unpkg) | task_ticket.php, logged_in.php | **LOW** — icons vanish; functionality unaffected |
| **React 18 + Babel** (unpkg) | holiday_list.php | **LOW** — holiday list UI breaks |
| **XLSX 0.18.5** (cdnjs) | holiday_list.php | **LOW** — Excel export fails |
| **Bootstrap Datepicker** (cdnjs) | checklist_task, holiday_list | **LOW** — date pickers fall back to native |

### External API Calls (Server-side)

| File | Service | Risk |
|---|---|---|
| `google_sheets_client.php` | Google Sheets/Drive API | **LOW** — leave sync fails; app still works |
| `manager_dashboard.php` | External CSV via cURL | **LOW** — legacy function; dashboard works from DB |
| `fms_builder.php` | localhost:5173 (Vite dev server) | **LOW** — dev-only check |

---

## 8. Critical Dependency Chains (Cascading Failure Paths)

```
Chain 1 — TOTAL OUTAGE:
  config.php breaks
  └── ALL 76 files fail → entire app down

Chain 2 — TOTAL OUTAGE:
  functions.php breaks
  └── auth/CSRF/session fail → all pages inaccessible

Chain 3 — ALL PAGE VIEWS:
  header.php breaks
  └── 35 pages lose layout/nav/CSS/JS → all page views broken

Chain 4 — ALL DASHBOARDS:
  dashboard_components.php breaks
  └── admin + manager + doer dashboards
  └── team performance + leave + leaderboard
  └── 10 files total affected

Chain 5 — FRESH INSTALL:
  db_schema.php breaks
  └── tables not created on first run → entire app fails

Chain 6 — EXTERNAL:
  stackpath CDN down
  └── Bootstrap CSS/JS unavailable
  └── all pages lose responsive layout + modals + dropdowns

Chain 7 — NOTIFICATIONS:
  notification_triggers.php breaks
  └── task_ticket_handler, updates_handler, notification_actions, meeting_handler
  └── all event notifications stop silently

Chain 8 — TASK PAGES:
  status_column_helpers.php breaks
  └── 8 pages show broken status columns
  └── status validation fails on task updates
```

---

## 9. Anomalies & Architectural Risks

| Severity | Issue | Location | Detail |
|:---:|---|---|---|
| **HIGH** | Cross-layer dependency | `ajax/fms_sheet_handler.php` | Requires `pages/fms_task.php` — ajax should never depend on a page file |
| ~~RESOLVED~~ | ~~Missing file~~ | ~~`ajax/reports_handler.php`~~ | ~~Was incorrectly flagged — `security.php` is NOT referenced by reports_handler~~ |
| **MEDIUM** | God file | `functions.php` | 1,392 lines with 60+ functions — single change risks breaking many callers |
| **MEDIUM** | God file | `dashboard_components.php` | 1,048 lines — tightly coupled to 10 consumers |
| **MEDIUM** | Framework conflict | `task_ticket.php` | Loads both TailwindCSS CDN and Bootstrap — CSS utility conflicts |
| **MEDIUM** | Unpinned CDN version | `task_ticket.php`, `logged_in.php` | `lucide@latest` on unpkg — breaking changes on any release |
| **LOW** | Duplicate CDN loads | Multiple pages | Bootstrap/jQuery/Font Awesome loaded in header.php AND individually in admin_dashboard, login, checklist_task, holiday_list |
| **LOW** | Mixed icon versions | header.php vs other pages | Font Awesome 6.0.0 in header vs 5.15.4 in admin_dashboard, login, checklist_task, holiday_list — class conflicts possible |
| **LOW** | Orphan ajax handlers | 6 ajax files | `freeze_weekly_performance`, `rqc_auto_sync`, `leave_auto_sync`, `clear_leave_data`, `fms_flow_handler`, `get_last_sync_time` have no PHP includes — may handle auth/DB independently or be incomplete |

---

## 10. Recommended Priority Actions

### Priority 1 — Critical (Fix immediately)

- **Guard `config.php` and `functions.php`** — any change to these files affects the entire application. These should have the most careful review process.
- **Investigate missing `includes/security.php`** — `ajax/reports_handler.php` requires this file but it doesn't exist in the includes directory. This will cause a fatal error when reports are accessed.

### Priority 2 — High (Fix soon)

- **Fix the cross-layer dependency** — `ajax/fms_sheet_handler.php` requiring `pages/fms_task.php` should be refactored to extract shared logic into an include file.
- **Pin all CDN versions** — `lucide@latest` should be locked to a specific version (e.g., `lucide@0.263.1`) to prevent surprise breakages from upstream releases.
- **Consolidate duplicate CDN loads** — Bootstrap, jQuery, and Font Awesome should only be loaded once from `header.php`. Pages that load their own copies (admin_dashboard, login, checklist_task, holiday_list) risk version conflicts and double-loading.

### Priority 3 — Medium (Plan for)

- **Consider splitting `functions.php`** — at 1,392 lines with 60+ functions, it's a monolith. Auth, date/time, task logic, and CSRF could be separate modules (e.g., `auth.php`, `date_helpers.php`, `task_helpers.php`, `csrf.php`).
- **Standardize Font Awesome version** — use either 5.x or 6.x consistently across all pages. Currently header uses 6.0.0 while several pages use 5.15.4.
- **Audit orphan ajax handlers** — 6 ajax files have no includes. Verify they handle authentication and DB connections properly.

### Priority 4 — Low (Nice to have)

- **Consider self-hosting critical CDN assets** — Bootstrap CSS/JS and jQuery could be served locally to eliminate external CDN failure as a risk. Other CDNs (Chart.js, Quill, etc.) are lower risk.
- **Remove TailwindCSS CDN dependency** — the Play CDN is a development-only tool. For the single page that uses it (task_ticket.php), consider pre-compiling Tailwind utilities into a static CSS file.
