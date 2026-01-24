-- Migration: Fix password_reset_requests.reset_code to allow NULL
-- Date: 2025-01-27
-- Description: reset_code should be nullable since it's only set when admin approves the request

ALTER TABLE password_reset_requests 
MODIFY COLUMN reset_code VARCHAR(255) NULL;

