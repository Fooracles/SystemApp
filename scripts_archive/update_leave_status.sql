-- Update Leave Status to Match FMS-5.7 Format
-- Run this SQL to update your database with the correct status values

-- First, let's see what we have
SELECT status, COUNT(*) as count FROM Leave_request GROUP BY status ORDER BY count DESC;

-- Update 702 records to 'Approve' (exact match for FMS-5.7)
-- You can modify the WHERE clause based on your criteria
UPDATE Leave_request 
SET status = 'Approve' 
WHERE (status = '' OR status IS NULL)
LIMIT 702;

-- Update 42 records to 'Reject' (exact match for FMS-5.7)  
-- You can modify the WHERE clause based on your criteria@
UPDATE Leave_request 
SET status = 'Reject' 
WHERE (status = '' OR status IS NULL)
LIMIT 42;

-- Check the results
SELECT status, COUNT(*) as count FROM Leave_request GROUP BY status ORDER BY count DESC;

-- Alternative: If you want to update based on specific criteria, use these examples:

-- Example 1: Update based on employee names
-- UPDATE Leave_request SET status = 'Approve' WHERE employee_name IN ('Employee1', 'Employee2') AND (status = '' OR status IS NULL);

-- Example 2: Update based on leave type
-- UPDATE Leave_request SET status = 'Approve' WHERE leave_type = 'Sick leave' AND (status = '' OR status IS NULL);
-- UPDATE Leave_request SET status = 'Reject' WHERE leave_type = 'Casual leave' AND (status = '' OR status IS NULL);

-- Example 3: Update based on date range
-- UPDATE Leave_request SET status = 'Approve' WHERE start_date < '2025-01-01' AND (status = '' OR status IS NULL);
-- UPDATE Leave_request SET status = 'Reject' WHERE start_date >= '2025-01-01' AND (status = '' OR status IS NULL);
