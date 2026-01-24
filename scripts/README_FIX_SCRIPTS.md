# Client Users Fix Scripts - Usage Guide

## Available Scripts

### 1. `diagnose_client_users_issues.php` - Diagnostic Tool
**Purpose:** Check and identify database issues without making changes

**How to Use:**
1. Upload to your server: `scripts/diagnose_client_users_issues.php`
2. Access via browser (must be logged in as admin)
3. Review the diagnostic report
4. See what issues exist before fixing

**What it shows:**
- Users with NULL/empty user_type
- Client users with incorrect user_type
- Deleted/inactive client accounts
- Client accounts with passwords
- Summary statistics

### 2. `fix_client_users_issues.php` - Automatic Fix Script ⭐
**Purpose:** Automatically fix all database issues

**How to Use:**
1. **Backup your database first!** ⚠️
2. Upload to your server: `scripts/fix_client_users_issues.php`
3. Access via browser (must be logged in as admin)
4. Review what the script will do
5. Click "Run Fix Script" button
6. Review the results

**What it fixes:**
- ✅ Adds Status column if missing
- ✅ Fixes client users with NULL/incorrect user_type
- ✅ Removes passwords from client accounts
- ✅ Ensures deleted client accounts can't log in
- ✅ Verifies all fixes were applied correctly

**Safety Features:**
- Only accessible by admin users
- Shows detailed results for each fix
- Can be run multiple times safely
- Does not delete accounts by default (only marks them inactive)

## Recommended Workflow

### Step 1: Run Diagnostic
```
1. Upload diagnose_client_users_issues.php
2. Access it in browser
3. Review issues found
4. Take note of any critical issues
```

### Step 2: Backup Database
```
- Use phpMyAdmin export
- Or use mysqldump command
- Or use hosting provider's backup tool
```

### Step 3: Run Fix Script
```
1. Upload fix_client_users_issues.php
2. Access it in browser
3. Click "Run Fix Script"
4. Review results
```

### Step 4: Verify Fixes
```
1. Run diagnose_client_users_issues.php again
2. Check that issues are resolved
3. Test the application:
   - Create new client user
   - Check Manage Team table
   - Try logging into deleted account
```

## Security

Both scripts require:
- User must be logged in
- User must be admin
- Session must be active

If you see "Access denied", make sure you're logged in as admin.

## Troubleshooting

### "Access denied" error
- **Solution:** Log in as admin user first

### Script shows errors
- **Solution:** Check PHP error logs
- Verify database connection is working
- Check that you have proper database permissions

### Some fixes didn't apply
- **Solution:** 
  - Check the error messages in the results
  - Verify database permissions
  - Some fixes may require manual SQL if constraints exist

### Want to delete inactive accounts
- **Solution:** 
  - The script marks them inactive by default (safer)
  - To physically delete, uncomment the deletion code in the script
  - Or run manual SQL: `DELETE FROM users WHERE user_type = 'client' AND Status = 'Inactive'`

## Files Location

```
your-project/
├── scripts/
│   ├── diagnose_client_users_issues.php  (Diagnostic)
│   ├── fix_client_users_issues.php        (Auto Fix) ⭐
│   └── README_FIX_SCRIPTS.md              (This file)
├── pages/
│   └── manage_users.php                   (Updated)
├── login.php                               (Updated)
└── migrations/
    └── fix_client_users_issues.sql        (SQL version - optional)
```

## Comparison: PHP Script vs SQL Script

| Feature | PHP Script | SQL Script |
|---------|-----------|------------|
| Ease of Use | ⭐⭐⭐⭐⭐ Very Easy | ⭐⭐⭐ Medium |
| Browser Access | ✅ Yes | ❌ No |
| Safety Checks | ✅ Built-in | ⚠️ Manual |
| Results Display | ✅ Detailed | ⚠️ Basic |
| Error Handling | ✅ Automatic | ⚠️ Manual |
| Recommended | ✅ **Yes** | ⚠️ Advanced users |

## Support

If you encounter issues:
1. Check PHP error logs
2. Verify database connection
3. Ensure admin access
4. Review diagnostic script output first
5. Check that all required files are uploaded
