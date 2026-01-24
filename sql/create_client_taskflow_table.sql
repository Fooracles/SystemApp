-- Create client_taskflow table for Task & Ticket collaboration
-- This table stores Tasks, Tickets, and Required items from the collaboration page

CREATE TABLE IF NOT EXISTS `client_taskflow` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `unique_id` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Format: TAS001, TKT001, REQ001',
    `type` ENUM('Task', 'Ticket', 'Required') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Assigned',
    `created_by` INT NOT NULL COMMENT 'User ID who created the item',
    `created_by_type` ENUM('Client', 'Manager') NOT NULL,
    `assigned_to` INT NULL COMMENT 'User ID assigned to (for Required items)',
    `attachments` JSON NULL COMMENT 'Array of attachment objects with name, size, type, path',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX (`type`),
    INDEX (`status`),
    INDEX (`created_by`),
    INDEX (`created_at`),
    INDEX (`unique_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

