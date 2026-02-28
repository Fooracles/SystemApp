# Project Cleanup Report — app-v8.1

**Generated:** 2026-02-20  
**Scope:** Full recursive audit of `C:\xampp\htdocs\app-v8.1`  
**Status:** READ-ONLY REPORT — No files have been deleted or modified.

---

## Legend

| Column | Meaning |
|--------|---------|
| **Usage Found** | Whether the file is referenced by any `include`, `require`, AJAX call, `href`, `src`, `.htaccess` rule, or active code path. References only from other cleanup-candidate files or `.md` docs are noted as "Docs only". |
| **Risk Level** | **Low** = safe to remove, no code depends on it. **Medium** = referenced only by docs or other removable files. **High** = referenced by production code; removal would break something. |
| **Recommendation** | **Delete** = remove entirely. **Archive** = move to a `_archive/` folder outside webroot. **Isolate** = move out of webroot but keep accessible. **Keep** = do not remove. **Review** = needs manual decision. |

---

## Section 1 — Test Pages & Test Reports

These are developer/QA test files that should never be publicly accessible in production.

| File Path | Estimated Purpose | Usage Found | Risk Level | Recommendation |
|-----------|-------------------|:-----------:|:----------:|----------------|
| `test/test_admin_dashboard.php` | Test admin dashboard rendering | No | Low | Delete |
| `test/test_admin_dashboard_pending_tasks.php` | Test pending tasks logic | No | Low | Delete |
| `test/test_admin_dashboard_stats.php` | Test admin stats calculations | No | Low | Delete |
| `test/test_checklist_overdue.php` | Test overdue checklist logic | Docs only (linked from test_notifications) | Low | Delete |
| `test/test_cron_task_delay_notifications.php` | Test cron delay notifications | No | Low | Delete |
| `test/test_doer_dashboard_date_ranges.php` | Test date range filtering | No | Low | Delete |
| `test/test_doer_dashboard_stats.php` | Test doer stats calculations | No | Low | Delete |
| `test/test_leave_data_visibility.php` | Test leave data visibility | No | Low | Delete |
| `test/test_leave_filtering.php` | Test leave filtering logic | No | Low | Delete |
| `test/test_leave_notifications.php` | Test leave notification triggers | No | Low | Delete |
| `test/test_leave_sorting.php` | Test leave sorting behavior | No | Low | Delete |
| `test/test_manager_dashboard.php` | Test manager dashboard rendering | No | Low | Delete |
| `test/test_manager_dashboard_stats.php` | Test manager stats calculations | No | Low | Delete |
| `test/test_metrics_comparison.php` | Test metrics comparison UI | Yes (fetches test_metrics_comparison_api.php) | Low | Delete |
| `test/test_metrics_comparison_api.php` | Test metrics comparison API | Yes (called by test_metrics_comparison.php) | Low | Delete |
| `test/test_notification_actions.php` | Test notification action buttons | No | Low | Delete |
| `test/test_notification_redirect_button.php` | Test notification redirect UI | No | Low | Delete |
| `test/test_notification_redirects.php` | Test notification redirect logic | No | Low | Delete |
| `test/test_notifications.php` | Test notification system | Yes (linked from setup_notifications pages) | Low | Delete |
| `test/test_pending_delayed_consistency.php` | Test pending/delayed consistency | No | Low | Delete |
| `test/test_pending_visibility.php` | Test pending task visibility | No | Low | Delete |
| `test/test_performance_page_cards.php` | Test performance page cards | No | Low | Delete |
| `test/test_post_fix_validation.php` | Post-fix validation checks | No | Low | Delete |
| `test/test_raise_ticket.php` | Test raise ticket flow | No | Low | Delete |
| `test/test_stats_comparison.php` | Test stats comparison logic | No | Low | Delete |
| `test/test_task_delay_notifications.php` | Test task delay notifications | No | Low | Delete |
| `test/test_task_delay_with_dummy_tasks.php` | Test delays with dummy data | No | Low | Delete |
| `test/test_task_ticket.php` | Test task ticket flow | No | Low | Delete |
| `test/test_wnd_glow.php` | Test WND glow UI effect | No | Low | Delete |
| `test/comprehensive_test.php` | Full test suite runner | Docs only (.md reports) | Low | Delete |
| `test/cron_rqc_sync.php` | Test version of RQC cron sync | Docs only (.md reports) | Low | Delete |
| `test/fix_leave_status_enum.php` | Fix leave status enum values | Yes (called from test_leave_data_visibility) | Low | Delete |
| `test/README_DATE_RANGE_TEST.md` | Test documentation | No | Low | Delete |
| `test_requirement_modal.html` | Test requirement modal UI | No | Low | Delete |
| `migration_and_test.php` | Migration runner + test suite | No | Low | Delete |
| `migration_test_report_2026-01-27_211730.html` | Generated migration test report | No | Low | Delete |
| `migration_test_report_2026-01-27_212815.html` | Generated migration test report | No | Low | Delete |
| `migration_test_report_2026-01-28_135551.html` | Generated migration test report | No | Low | Delete |
| `migration_test_report_2026-01-28_231042.html` | Generated migration test report | No | Low | Delete |

> **Verdict:** The entire `test/` directory and all root-level test/migration-report files can be safely deleted. No production code depends on them. The cross-references between test files are internal to the test suite.

---

## Section 2 — Setup & Migration Scripts

One-time setup scripts that have already been executed and are no longer needed at runtime.

| File Path | Estimated Purpose | Usage Found | Risk Level | Recommendation |
|-----------|-------------------|:-----------:|:----------:|----------------|
| `setup_session_logging.php` | One-time session logging setup | No | Low | Archive |
| `setup_sessions_table.php` | One-time sessions table creation | No | Low | Archive |
| `pages/setup_notifications_table.php` | One-time notifications table setup | Yes (error_log message in functions.php) | Medium | Archive ¹ |
| `pages/setup_notifications_database.php` | One-time notifications DB setup | Yes (self-link + test link) | Low | Archive |
| `create_notifications_table_simple.php` | Simplified notifications table creator | No (self-reference only) | Low | Archive |
| `create_notifications_table_standalone.php` | Standalone notifications table creator | No (self-reference only) | Low | Archive |
| `create_url_sharing_tables.php` | One-time URL sharing tables setup | No | Low | Archive |
| `scripts/create_notifications_table.php` | Notifications table creator | No | Low | Archive |
| `scripts/setup_cron_job.php` | Cron job setup helper | Docs only | Low | Archive |
| `scripts/setup_windows_scheduler.bat` | Windows task scheduler setup | Docs only | Low | Archive |
| `scripts/cleanup_duplicate_notifications.php` | One-time duplicate notification cleanup | No | Low | Archive |
| `scripts/update_notification_types.php` | One-time notification type updater | No | Low | Archive |
| `scripts/update_leave_requests.php` | Legacy leave request updater | Docs only | Low | Archive |
| `scripts/update_leave_requests_minimal.php` | Minimal leave request updater | Docs only | Low | Archive |
| `scripts/update_leave_requests_simple.php` | Simple leave request updater | Docs only | Low | Archive |

> ¹ `pages/setup_notifications_table.php` is mentioned in `error_log()` messages in `includes/functions.php` as a helpful hint. This is not a runtime dependency — the error message tells admins to run it if the table is missing. Safe to archive, but update the error message text if desired.

---

## Section 3 — Fix & Diagnostic Scripts

One-off repair scripts that were created to fix specific issues. Already executed.

| File Path | Estimated Purpose | Usage Found | Risk Level | Recommendation |
|-----------|-------------------|:-----------:|:----------:|----------------|
| `fix_collation.php` | Fix database collation issues | No (self-reference only) | Low | Archive |
| `fix_db_connections.php` | Fix database connection configs | No (self-reference only) | Low | Archive |
| `fix_manage_users_clients_db.php` | Fix manage users/clients DB | No (self-reference only) | Low | Archive |
| `fix_new_client_accounts.php` | Fix new client account creation | No | Low | Archive |
| `diagnose_client_account_visibility.php` | Diagnose client account visibility | No | Low | Archive |
| `deploy_error_handler.php` | Deploy error handler to includes | No (self-reference only) | Low | Archive |
| `deploy_file_uploader.php` | Deploy file uploader to includes | Docs only (.htaccess comment) | Low | Archive |
| `scripts/diagnose_client_users_issues.php` | Diagnose client user issues | Yes (linked from fix script + docs) | Low | Archive |
| `scripts/fix_client_users_issues.php` | Fix client user issues | Yes (linked from diagnose script + docs) | Low | Archive |
| `scripts/fix_task_ticket_assigned_to.php` | Fix task ticket assigned_to field | No (self-link only) | Low | Archive |

---

## Section 4 — Archived Scripts (scripts_archive/)

Already archived once. Can be fully removed or moved out of webroot.

| File Path | Estimated Purpose | Usage Found | Risk Level | Recommendation |
|-----------|-------------------|:-----------:|:----------:|----------------|
| `scripts_archive/complete_leave_sync_setup.php` | Complete leave sync setup | No | Low | Delete |
| `scripts_archive/create_leave_database_structure.php` | Create leave DB structure | No | Low | Delete |
| `scripts_archive/setup_leave_sync_config.php` | Leave sync config setup | No | Low | Delete |
| `scripts_archive/database.sql` | Full database schema dump | No | Low | Delete |
| `scripts_archive/database_schema_notes_urls.sql` | Notes/URLs schema | Docs only | Low | Delete |
| `scripts_archive/leave_sheet_sync.sql` | Leave sheet sync schema | Docs only | Low | Delete |
| `scripts_archive/update_leave_status.sql` | Update leave status migration | Docs only | Low | Delete |

---

## Section 5 — SQL & Schema Files

| File Path | Estimated Purpose | Usage Found | Risk Level | Recommendation |
|-----------|-------------------|:-----------:|:----------:|----------------|
| `complete_database_schema.sql` | Full database schema reference | Docs only | Low | Archive |
| `sql/create_client_taskflow_table.sql` | Client taskflow table DDL | No (table created inline in code) | Low | Archive |
| `migrations/001_fix_password_reset_code_nullable.sql` | Password reset column fix | Docs only | Low | Archive |
| `migrations/fix_client_users_issues.sql` | Client users fix migration | Docs only (scripts + docs) | Low | Archive |
| `migrations/README_CLIENT_USERS_FIX.md` | Migration documentation | Docs only | Low | Archive |

---

## Section 6 — Documentation Files (Public Webroot Exposure)

These `.md` files are accessible via browser and may expose internal architecture details.

| File Path | Estimated Purpose | Usage Found | Risk Level | Recommendation |
|-----------|-------------------|:-----------:|:----------:|----------------|
| `ANALYSIS_REPORT.md` | System analysis report | Docs only (cross-referenced by other .md) | Low | Archive |
| `BACKEND_IMPLEMENTATION_GUIDE.md` | Backend implementation guide | No | Low | Archive |
| `CLEANUP_REPORT_2025.md` | Previous cleanup report | No | Low | Archive |
| `CLEANUP_REPORT_PHASE2.md` | Phase 2 cleanup report | No | Low | Archive |
| `COMPLETE_DOCUMENTATION.md` | Complete system documentation | Docs only | Low | Archive |
| `COMPLETE_FINAL_REPORT.md` | Final implementation report | Docs only | Low | Archive |
| `DATE_FIX_SOLUTION.md` | Date fix solution notes | Docs only | Low | Archive |
| `DOER_DASHBOARD_README.md` | Doer dashboard documentation | No | Low | Archive |
| `FINAL_REPORT.md` | Final report | Docs only | Low | Archive |
| `FIX_SUMMARY.md` | Fix summary notes | No | Low | Archive |
| `LEAVE_SYNC_IMPLEMENTATION_README.md` | Leave sync implementation guide | No | Low | Archive |
| `MEETING_PAGE_ISSUES_REPORT.md` | Meeting page issues report | No | Low | Archive |
| `NEW_FEATURES_README.md` | New features documentation | Docs only | Low | Archive |
| `NOTIFICATION_SETUP_README.md` | Notification setup guide | No | Low | Archive |
| `PROJECT_CLEANUP_REPORT.md` | Previous cleanup report | No | Low | Archive |
| `QUICK_FIX_GUIDE.md` | Quick fix guide | No | Low | Archive |
| `REMAINING_ISSUES_REPORT.md` | Remaining issues tracker | Docs only | Low | Archive |
| `TASK_TICKET_DOWNLOAD_FIX.md` | Task ticket download fix notes | No | Low | Archive |
| `manage_tasks_documentation.md` | Manage tasks documentation | No | Low | Archive |
| `assets/css/THEME_GUIDE.md` | CSS theme guide | No | Low | Archive |
| `docs_archive/AUTOMATIC_SYNC_SETUP.md` | Sync setup guide | Docs only | Low | Delete |
| `docs_archive/LEAVE_SYSTEM_COMPLETE_FILE_DOCUMENTATION.md` | Leave system file docs | Docs only | Low | Delete |
| `docs_archive/LEAVE_SYSTEM_DEPLOYMENT_FILES.md` | Leave deployment file list | Docs only | Low | Delete |
| `docs_archive/LEAVE_SYSTEM_IMPLEMENTATION_GUIDE.txt` | Leave implementation guide | Docs only | Low | Delete |
| `docs_archive/LIVE_SERVER_DEPLOYMENT_GUIDE.md` | Live server deployment guide | Docs only | Low | Delete |
| `docs_archive/SMART_SYNC_IMPLEMENTATION.md` | Smart sync implementation docs | Docs only | Low | Delete |
| `scripts/README_FIX_SCRIPTS.md` | Fix scripts documentation | No | Low | Delete |
| `fms-flow-builder/README.md` | FMS flow builder readme | No | Low | Keep (dev tooling) |

---

## Section 7 — Leftover Artifacts & Miscellaneous

| File Path | Estimated Purpose | Usage Found | Risk Level | Recommendation |
|-----------|-------------------|:-----------:|:----------:|----------------|
| `log.txt` | Application debug log | **Yes** — written by `includes/functions.php`, `ajax/ajax_fetch_sheet_data.php`, `ajax/fms_sheet_handler.php` | **High** | Keep (active log) |
| `logs/ajax_errors.log` | AJAX error log | Active log target | Medium | Keep (but rotate) |
| `logs/db_operations.log` | DB operations log | Active log target | Medium | Keep (but rotate) |
| `logs/leave_sync.log` | Leave sync log | Active log target | Medium | Keep (but rotate) |
| `logs/rqc_cron.log` | RQC cron log | Active log target | Medium | Keep (but rotate) |
| `assets/css/style_clean.css` | Cleaned/alt version of stylesheet | **No** — not referenced anywhere | Low | Delete |
| `assets/version.txt` | Version tracking file | **No** — not referenced anywhere | Low | Review |
| `cacert.pem` | SSL CA certificate bundle | **No** — not referenced in PHP code | Low | Review ² |
| `cron_leave_sync.php` (root) | Root-level leave sync cron | Docs only (duplicate of `scripts/cron_leave_sync.php`) | Low | Delete |
| `cron_rqc_sync.php` (root) | Root-level RQC sync cron | Docs only | Medium | Review ³ |

> ² `cacert.pem` may be referenced in `php.ini` (`curl.cainfo` or `openssl.cafile`) rather than in PHP code. Check your XAMPP `php.ini` before removing.  
> ³ `cron_rqc_sync.php` in root may be the production cron target if your task scheduler points to it. Verify your scheduled tasks before removing.

---

## Section 8 — Files Confirmed SAFE (Keep)

These files were reviewed and are actively used by the application:

| File Path | Reason to Keep |
|-----------|---------------|
| `includes/config.php` | Core configuration — required everywhere |
| `includes/db_schema.php` | Database schema — required by `config.php` and `db_functions.php` |
| `includes/db_functions.php` | Database functions — required by multiple pages |
| `includes/functions.php` | Core utility functions |
| `includes/header.php` | Page header template |
| `includes/footer.php` | Page footer template |
| `includes/sidebar.php` | Navigation sidebar |
| `includes/google_sheets_client.php` | Google Sheets API client |
| `includes/error_handler.php` | Error handler |
| `includes/file_uploader.php` | File upload handler |
| `includes/notification_triggers.php` | Notification trigger logic |
| `includes/dashboard_components.php` | Dashboard UI components |
| `includes/sorting_helpers.php` | Sorting helper functions |
| `includes/status_column_helpers.php` | Status column helpers |
| `credentials.json` | Google API credentials — used by `config.php` and `google_sheets_client.php` |
| `sw.js` | Service worker — production asset |
| `index.php` | Application entry point |
| `login.php` | Login page |
| `logout.php` | Logout handler |
| All `pages/*.php` (except setup_*) | Application pages |
| All `ajax/*.php` | AJAX endpoints |
| All `cron/*.php` | Active cron jobs |
| All `assets/css/*.css` (except style_clean.css) | Active stylesheets |
| All `assets/js/*.js` | Active JavaScript |
| `.htaccess` (all locations) | Server configuration |
| `composer.json` / `composer.lock` | Dependency management |

---

## Summary Statistics

| Category | File Count | Recommendation Breakdown |
|----------|:----------:|--------------------------|
| Test pages & reports | **39** | All Delete |
| Setup & migration scripts | **15** | All Archive |
| Fix & diagnostic scripts | **10** | All Archive |
| Archived scripts (scripts_archive/) | **7** | All Delete |
| SQL & schema files | **5** | All Archive |
| Documentation (.md) | **28** | 20 Archive, 7 Delete, 1 Keep |
| Leftover artifacts | **7** | 2 Delete, 2 Review, 3 Keep (rotate logs) |
| **Total cleanup candidates** | **111** | **48 Delete, 55 Archive, 5 Keep, 3 Review** |

---

## Recommended Execution Plan

### Phase 1 — Zero-Risk Deletions (48 files)
Delete the entire `test/` directory, all `migration_test_report_*.html` files, `test_requirement_modal.html`, `migration_and_test.php`, all `scripts_archive/` contents, `docs_archive/` contents, orphan CSS/root cron duplicate, and README files inside deleted directories.

### Phase 2 — Archive to `_archive/` Outside Webroot (55 files)
Create `C:\xampp\htdocs\app-v8.1\_archive\` (blocked by `.htaccess`), and move all setup scripts, fix scripts, SQL files, and documentation `.md` files there. This preserves them for reference without public exposure.

### Phase 3 — Manual Review (3 files)
- `cacert.pem` — Check `php.ini` for `curl.cainfo` / `openssl.cafile` references before removing.
- `cron_rqc_sync.php` (root) — Verify Windows Task Scheduler target before removing.
- `assets/version.txt` — Determine if any external system reads this.

### Phase 4 — Log Rotation
Implement log rotation for `logs/*.log` and `log.txt` to prevent unbounded growth.

---

> **⚠️ REMINDER:** This is an audit report only. No files have been modified or deleted. Review each section and confirm before executing any cleanup phase.
