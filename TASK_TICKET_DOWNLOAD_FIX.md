# Task & Ticket Download Fix - Database Structure

## SQL Queries to Add Attachment Support to Tasks Table

If your `tasks` table doesn't have attachment columns, run these SQL queries:

```sql
-- Add attachment_path column to tasks table
ALTER TABLE tasks 
ADD COLUMN attachment_path VARCHAR(500) NULL 
COMMENT 'Path to uploaded attachment file' 
AFTER status;

-- Add attachment_name column to tasks table
ALTER TABLE tasks 
ADD COLUMN attachment_name VARCHAR(255) NULL 
COMMENT 'Original name of the attachment' 
AFTER attachment_path;
```

## SQL Queries to Add Attachment Support to Tickets Table

If you have a separate `tickets` table, run these SQL queries:

```sql
-- Create tickets table if it doesn't exist (with attachment support)
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('Raised', 'In Progress', 'Resolved') DEFAULT 'Raised',
    attachment_path VARCHAR(500) NULL COMMENT 'Path to uploaded attachment file',
    attachment_name VARCHAR(255) NULL COMMENT 'Original name of the attachment',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (created_by),
    INDEX (status),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Or if tickets table exists, add attachment columns:
ALTER TABLE tickets 
ADD COLUMN attachment_path VARCHAR(500) NULL 
COMMENT 'Path to uploaded attachment file' 
AFTER description;

ALTER TABLE tickets 
ADD COLUMN attachment_name VARCHAR(255) NULL 
COMMENT 'Original name of the attachment' 
AFTER attachment_path;
```

## File Storage Structure

Files should be stored in:
- `uploads/tasks/` - for task attachments
- `uploads/tickets/` - for ticket attachments

Ensure these directories exist and have proper permissions (755 for directories, 644 for files).

## Verification Queries

Check if columns exist:
```sql
-- Check tasks table
SHOW COLUMNS FROM tasks LIKE 'attachment%';

-- Check tickets table (if exists)
SHOW COLUMNS FROM tickets LIKE 'attachment%';
```

