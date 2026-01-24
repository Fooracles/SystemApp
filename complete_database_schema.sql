-- ==============================================
-- FMS-4.11-App Complete Database Schema
-- ==============================================
-- 
-- ⚠️  WARNING: THIS FILE IS DEPRECATED ⚠️
-- 
-- This SQL file is kept for historical reference only.
-- The actual database schema is defined in: includes/db_schema.php
-- 
-- DO NOT use this file to create a new database.
-- Use includes/db_schema.php or run the application which will auto-create tables.
-- ==============================================

-- 1. DEPARTMENTS TABLE
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USERS TABLE
-- NOTE: This schema file is DEPRECATED. Use includes/db_schema.php for the current schema.
-- This file is kept for reference only and may not match the actual database structure.
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `user_type` ENUM('admin', 'manager', 'doer') NOT NULL,
    `department_id` INT,
    `manager` VARCHAR(255) NULL,
    `manager_id` INT NULL,
    `joining_date` DATE NULL,
    `date_of_birth` DATE NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. USER_DEPARTMENTS TABLE (Many-to-Many relationship)
CREATE TABLE IF NOT EXISTS `user_departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `department_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_department` (`user_id`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TASKS TABLE
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `assignee_id` INT,
    `created_by` INT NOT NULL,
    `department_id` INT,
    `due_date` DATE,
    `planned_date` DATE,
    `planned_time` TIME,
    `actual_date` DATE,
    `actual_time` TIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`assignee_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. FMS_SHEETS TABLE
CREATE TABLE IF NOT EXISTS `fms_sheets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sheet_id` VARCHAR(255) NOT NULL,
    `tab_name` VARCHAR(255) NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_sheet_tab` (`sheet_id`, `tab_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. FMS_SHEET_METADATA TABLE
CREATE TABLE IF NOT EXISTS `fms_sheet_metadata` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sheet_id` VARCHAR(255) NOT NULL,
    `tab_name` VARCHAR(255) NOT NULL,
    `metadata` JSON,
    `last_sync` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_sheet_tab_meta` (`sheet_id`, `tab_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. FMS_TASKS TABLE
CREATE TABLE IF NOT EXISTS `fms_tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sheet_id` VARCHAR(255) NOT NULL,
    `tab_name` VARCHAR(255) NOT NULL,
    `row_number` INT NOT NULL,
    `task_name` VARCHAR(500),
    `assignee` VARCHAR(255),
    `status` VARCHAR(100),
    `priority` VARCHAR(50),
    `due_date` DATE,
    `planned_date` DATE,
    `planned_time` TIME,
    `actual_date` DATE,
    `actual_time` TIME,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_sheet_tab_row` (`sheet_id`, `tab_name`, `row_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. FMS_SHEET_SYNC TABLE
CREATE TABLE IF NOT EXISTS `fms_sheet_sync` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sheet_id` VARCHAR(255) NOT NULL,
    `tab_name` VARCHAR(255) NOT NULL,
    `last_sync_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `sync_status` ENUM('success', 'failed', 'in_progress') DEFAULT 'success',
    `error_message` TEXT,
    UNIQUE KEY `unique_sheet_tab_sync` (`sheet_id`, `tab_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. HOLIDAYS TABLE
CREATE TABLE IF NOT EXISTS `holidays` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `date` DATE NOT NULL,
    `type` ENUM('national', 'regional', 'company') DEFAULT 'national',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_holiday_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. CHECKLIST_SUBTASKS TABLE
CREATE TABLE IF NOT EXISTS `checklist_subtasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT NOT NULL,
    `subtask_name` VARCHAR(500) NOT NULL,
    `assignee` VARCHAR(255),
    `status` ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
    `due_date` DATE,
    `planned_date` DATE,
    `planned_time` TIME,
    `actual_date` DATE,
    `actual_time` TIME,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. PASSWORD_RESET_REQUESTS TABLE
CREATE TABLE IF NOT EXISTS `password_reset_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token` VARCHAR(255) NOT NULL UNIQUE,
    `expires_at` TIMESTAMP NOT NULL,
    `used` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. ACTIVITY_LOGS TABLE
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(50),
    `record_id` INT,
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================
-- INDEXES FOR PERFORMANCE
-- ==============================================

-- Indexes for users table
CREATE INDEX `idx_users_email` ON `users` (`email`);
CREATE INDEX `idx_users_username` ON `users` (`username`);
CREATE INDEX `idx_users_role` ON `users` (`role`);
CREATE INDEX `idx_users_active` ON `users` (`is_active`);

-- Indexes for tasks table
CREATE INDEX `idx_tasks_assignee` ON `tasks` (`assignee_id`);
CREATE INDEX `idx_tasks_created_by` ON `tasks` (`created_by`);
CREATE INDEX `idx_tasks_department` ON `tasks` (`department_id`);
CREATE INDEX `idx_tasks_status` ON `tasks` (`status`);
CREATE INDEX `idx_tasks_priority` ON `tasks` (`priority`);
CREATE INDEX `idx_tasks_due_date` ON `tasks` (`due_date`);
CREATE INDEX `idx_tasks_planned_date` ON `tasks` (`planned_date`);

-- Indexes for fms_tasks table
CREATE INDEX `idx_fms_tasks_sheet` ON `fms_tasks` (`sheet_id`);
CREATE INDEX `idx_fms_tasks_tab` ON `fms_tasks` (`tab_name`);
CREATE INDEX `idx_fms_tasks_assignee` ON `fms_tasks` (`assignee`);
CREATE INDEX `idx_fms_tasks_status` ON `fms_tasks` (`status`);
CREATE INDEX `idx_fms_tasks_due_date` ON `fms_tasks` (`due_date`);
CREATE INDEX `idx_fms_tasks_planned_date` ON `fms_tasks` (`planned_date`);

-- Indexes for checklist_subtasks table
CREATE INDEX `idx_checklist_task` ON `checklist_subtasks` (`task_id`);
CREATE INDEX `idx_checklist_assignee` ON `checklist_subtasks` (`assignee`);
CREATE INDEX `idx_checklist_status` ON `checklist_subtasks` (`status`);
CREATE INDEX `idx_checklist_due_date` ON `checklist_subtasks` (`due_date`);
CREATE INDEX `idx_checklist_planned_date` ON `checklist_subtasks` (`planned_date`);

-- Indexes for activity_logs table
CREATE INDEX `idx_activity_user` ON `activity_logs` (`user_id`);
CREATE INDEX `idx_activity_action` ON `activity_logs` (`action`);
CREATE INDEX `idx_activity_table` ON `activity_logs` (`table_name`);
CREATE INDEX `idx_activity_created` ON `activity_logs` (`created_at`);

-- Indexes for password_reset_requests table
CREATE INDEX `idx_password_reset_user` ON `password_reset_requests` (`user_id`);
CREATE INDEX `idx_password_reset_token` ON `password_reset_requests` (`token`);
CREATE INDEX `idx_password_reset_expires` ON `password_reset_requests` (`expires_at`);

-- Indexes for holidays table
CREATE INDEX `idx_holidays_date` ON `holidays` (`date`);
CREATE INDEX `idx_holidays_type` ON `holidays` (`type`);
CREATE INDEX `idx_holidays_active` ON `holidays` (`is_active`);

-- ==============================================
-- SAMPLE DATA (Optional)
-- ==============================================

-- Insert sample departments
INSERT IGNORE INTO `departments` (`name`) VALUES 
('IT Department'),
('Human Resources'),
('Finance'),
('Operations'),
('Marketing');

-- Insert sample admin user (password: admin123)
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `role`) VALUES 
('admin', 'admin@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin');

-- Insert sample holidays
INSERT IGNORE INTO `holidays` (`name`, `date`, `type`) VALUES 
('New Year', '2024-01-01', 'national'),
('Independence Day', '2024-07-04', 'national'),
('Christmas Day', '2024-12-25', 'national');

-- ==============================================
-- END OF SCHEMA
-- ==============================================





