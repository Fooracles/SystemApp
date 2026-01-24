# Client Users Issues Fix - Migration Guide

This guide helps you fix issues where:
1. Client users appear in "Manage Team" table instead of "Manage Client Users"
2. Deleted client accounts can still log in

## Files Created

1. **`migrations/fix_client_users_issues.sql`** - SQL migration script to fix database issues
2. **`scripts/diagnose_client_users_issues.php`** - Diagnostic script to check database state
3. **Updated `pages/manage_users.php`** - Fixed query to properly filter client users
4. **Updated `login.php`** - Enhanced login check for deleted client accounts

## Step-by-Step Instructions

### Step 1: Run Diagnostic Script (Optional but Recommended)

1. Upload `scripts/diagnose_client_users_issues.php` to your live server
2. Access it via browser: `https://yourdomain.com/scripts/diagnose_client_users_issues.php`
3. Review the diagnostic report to see what issues exist
4. Take note of any critical issues found

### Step 2: Backup Your Database

**IMPORTANT:** Always backup your database before running migration scripts!

```bash
# Using mysqldump
mysqldump -u your_username -p your_database_name > backup_before_fix.sql

# Or use your hosting provider's backup tool
```

### Step 3: Review the SQL Migration Script

1. Open `migrations/fix_client_users_issues.sql`
2. Review the queries, especially the DELETE statements
3. The script includes diagnostic queries at the top - you can run those first
4. The DELETE statement for inactive client accounts is commented out by default - uncomment only if you want to physically delete them

### Step 4: Run the SQL Migration Script

**Option A: Using phpMyAdmin**
1. Log into phpMyAdmin
2. Select your database
3. Click on "SQL" tab
4. Copy and paste the contents of `migrations/fix_client_users_issues.sql`
5. Review the queries
6. Click "Go" to execute

**Option B: Using MySQL Command Line**
```bash
mysql -u your_username -p your_database_name < migrations/fix_client_users_issues.sql
```

**Option C: Run Queries Section by Section**
1. Run the diagnostic queries first (Step 1)
2. Review the results
3. Run the fix queries (Step 2)
4. Run the verification queries (Step 3)
5. Review the final diagnostic report (Step 5)

### Step 5: Upload Updated PHP Files

Upload the updated files to your live server:
- `pages/manage_users.php` (updated query)
- `login.php` (enhanced login check)

### Step 6: Verify the Fixes

1. Run the diagnostic script again: `scripts/diagnose_client_users_issues.php`
2. Check that all issues are resolved
3. Test the application:
   - Create a new client user and verify it appears in "Manage Client Users" table
   - Verify "Manage Team" table only shows admin/manager/doer users
   - Try logging into a deleted client account (should fail)

## What the Fixes Do

### Database Fixes (SQL Script)

1. **Fix NULL/Empty user_type**: Updates client users that have NULL or empty `user_type` to `'client'`
2. **Remove passwords from client accounts**: Ensures client accounts (parent entities) don't have passwords
3. **Handle deleted client accounts**: Either deletes them or ensures they can't log in
4. **Add Status column if missing**: Ensures the Status column exists for account management

### Code Fixes (PHP Files)

1. **manage_users.php**: Changed query from `WHERE u.user_type != 'client'` to `WHERE u.user_type IN ('admin', 'manager', 'doer')` to explicitly include only team users
2. **login.php**: Enhanced login check to:
   - Include Status in the query
   - Check if client account is inactive/deleted
   - Provide better error messages

## Troubleshooting

### Issue: "Access denied" when running diagnostic script
- **Solution**: Make sure you're logged in as an admin user

### Issue: SQL errors when running migration
- **Solution**: 
  - Check MySQL version compatibility
  - Ensure you have proper permissions
  - Run queries section by section to identify the problematic query

### Issue: Client users still appearing in team table
- **Solution**:
  1. Check if `user_type` column exists and has correct values
  2. Run the diagnostic script to see which users have issues
  3. Manually update problematic records:
     ```sql
     UPDATE users SET user_type = 'client' WHERE id = [user_id];
     ```

### Issue: Deleted account can still log in
- **Solution**:
  1. Check if the account was actually deleted from database
  2. Verify the account has Status = 'Inactive' or was physically deleted
  3. Check if login.php was properly uploaded
  4. Clear any cached sessions

## Manual SQL Queries (If Needed)

### Check for problematic users:
```sql
-- Users with NULL user_type
SELECT * FROM users WHERE user_type IS NULL OR user_type = '';

-- Client users that should have user_type = 'client'
SELECT u.* FROM users u
WHERE u.password IS NOT NULL 
AND u.password != ''
AND u.manager_id IN (SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = ''))
AND (u.user_type IS NULL OR u.user_type = '' OR u.user_type != 'client');
```

### Fix specific user:
```sql
-- Set user_type for a specific user
UPDATE users SET user_type = 'client' WHERE id = [user_id];

-- Delete a specific client account
DELETE FROM users WHERE id = [client_account_id] AND user_type = 'client' AND (password IS NULL OR password = '');
```

## Support

If you encounter any issues:
1. Check the diagnostic script output
2. Review the SQL migration script comments
3. Verify database schema matches expected structure
4. Check PHP error logs for any code-related issues

## Notes

- The migration script is designed to be safe and includes verification steps
- DELETE statements are commented out by default - only uncomment if you're sure you want to delete records
- Always test on a staging environment first if possible
- Keep backups of your database before making changes
