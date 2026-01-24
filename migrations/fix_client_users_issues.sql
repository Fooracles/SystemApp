-- ==============================================
-- Fix Client Users and Deleted Accounts Issues
-- ==============================================
-- This script fixes issues where:
-- 1. Client users appear in "Manage Team" table instead of "Manage Client Users"
-- 2. Deleted client accounts can still log in
-- ==============================================

-- Step 1: Diagnostic Queries (Run these first to see current state)
-- ==============================================

-- Check for users with NULL or empty user_type
SELECT 'Users with NULL or empty user_type:' as diagnostic;
SELECT id, username, name, user_type, password, manager_id, Status
FROM users 
WHERE user_type IS NULL OR user_type = '';

-- Check for client users that might appear in team table (wrong user_type)
SELECT 'Client users with incorrect user_type:' as diagnostic;
SELECT u.id, u.username, u.name, u.user_type, u.password, u.manager_id, u.Status,
       c.id as client_account_id, c.username as client_account_username
FROM users u
LEFT JOIN users c ON u.manager_id = c.id AND c.user_type = 'client' AND (c.password IS NULL OR c.password = '')
WHERE u.password IS NOT NULL 
AND u.password != ''
AND u.manager_id IN (SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = ''))
AND (u.user_type IS NULL OR u.user_type = '' OR u.user_type != 'client');

-- Check for deleted client accounts that still exist
SELECT 'Deleted client accounts (Inactive status):' as diagnostic;
SELECT id, username, name, user_type, password, Status, created_at
FROM users 
WHERE user_type = 'client' 
AND (password IS NULL OR password = '')
AND (Status = 'Inactive' OR Status IS NULL);

-- Check for client accounts that should not be able to log in but have passwords
SELECT 'Client accounts with passwords (should not exist):' as diagnostic;
SELECT id, username, name, user_type, password, Status
FROM users 
WHERE user_type = 'client' 
AND (password IS NULL OR password = '')
AND password IS NOT NULL
AND password != '';

-- ==============================================
-- Step 2: Fix Data Issues
-- ==============================================

-- Fix 1: Update client users that have NULL or empty user_type
-- These are users under client accounts (have manager_id pointing to client account)
UPDATE users u
SET u.user_type = 'client'
WHERE u.manager_id IN (
    SELECT id FROM (
        SELECT id FROM users 
        WHERE user_type = 'client' 
        AND (password IS NULL OR password = '')
    ) AS client_accounts
)
AND u.password IS NOT NULL 
AND u.password != ''
AND (u.user_type IS NULL OR u.user_type = '' OR u.user_type != 'client');

-- Fix 2: Ensure user_type column cannot be NULL (if not already enforced)
-- First check if column allows NULL
-- If it does, we'll need to update the schema, but for now we'll fix the data

-- Fix 3: Mark or delete inactive client accounts that should not be accessible
-- Option A: Physically delete inactive client accounts (USE WITH CAUTION)
-- Uncomment the line below if you want to delete them:
-- DELETE FROM users WHERE user_type = 'client' AND (password IS NULL OR password = '') AND Status = 'Inactive';

-- Option B: Ensure inactive client accounts have empty password (safer)
UPDATE users 
SET password = '' 
WHERE user_type = 'client' 
AND (password IS NULL OR password = '')
AND Status = 'Inactive'
AND (password IS NOT NULL AND password != '');

-- Fix 4: Remove any passwords from client accounts (they should not have passwords)
UPDATE users 
SET password = '' 
WHERE user_type = 'client' 
AND (password IS NULL OR password = '')
AND password IS NOT NULL
AND password != '';

-- ==============================================
-- Step 3: Verify Fixes
-- ==============================================

-- Verify: Check that all client users have user_type = 'client'
SELECT 'Verification: Client users with correct user_type:' as verification;
SELECT COUNT(*) as total_client_users
FROM users 
WHERE user_type = 'client' 
AND password IS NOT NULL 
AND password != ''
AND manager_id IN (SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = ''));

-- Verify: Check that no client accounts have passwords
SELECT 'Verification: Client accounts without passwords:' as verification;
SELECT COUNT(*) as client_accounts_without_passwords
FROM users 
WHERE user_type = 'client' 
AND (password IS NULL OR password = '');

-- Verify: Check team users (should not include any client users)
SELECT 'Verification: Team users (should not include clients):' as verification;
SELECT user_type, COUNT(*) as count
FROM users 
WHERE user_type IN ('admin', 'manager', 'doer')
GROUP BY user_type;

-- Verify: Check for any remaining NULL user_type
SELECT 'Verification: Users with NULL user_type (should be 0):' as verification;
SELECT COUNT(*) as users_with_null_type
FROM users 
WHERE user_type IS NULL OR user_type = '';

-- ==============================================
-- Step 4: Add Constraints (Optional but Recommended)
-- ==============================================

-- Ensure Status column exists (if not already present)
-- This is usually added by the application, but we'll check
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'Status';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT "Status column already exists" AS result;',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN Status VARCHAR(20) DEFAULT "Active";')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ==============================================
-- Step 5: Final Diagnostic Report
-- ==============================================

SELECT '=== FINAL DIAGNOSTIC REPORT ===' as report;

SELECT 'Total Users by Type:' as category, user_type, COUNT(*) as count
FROM users
GROUP BY user_type
ORDER BY user_type;

SELECT 'Client Accounts (no password):' as category, COUNT(*) as count
FROM users 
WHERE user_type = 'client' 
AND (password IS NULL OR password = '');

SELECT 'Client Users (with password):' as category, COUNT(*) as count
FROM users 
WHERE user_type = 'client' 
AND password IS NOT NULL 
AND password != ''
AND manager_id IN (SELECT id FROM users WHERE user_type = 'client' AND (password IS NULL OR password = ''));

SELECT 'Team Users (admin/manager/doer):' as category, COUNT(*) as count
FROM users 
WHERE user_type IN ('admin', 'manager', 'doer');

SELECT 'Users with NULL user_type:' as category, COUNT(*) as count
FROM users 
WHERE user_type IS NULL OR user_type = '';

SELECT '=== END OF REPORT ===' as report;
