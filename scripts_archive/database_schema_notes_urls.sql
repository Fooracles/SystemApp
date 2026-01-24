-- ========================================
-- DATABASE SCHEMA FOR MY NOTES & USEFUL URLS
-- ========================================

-- Notes table for personal notes
CREATE TABLE IF NOT EXISTS `notes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `content` longtext,
    `reminder_date` datetime NULL,
    `is_important` tinyint(1) DEFAULT 0,
    `is_completed` tinyint(1) DEFAULT 0,
    `sort_order` int(11) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `is_important` (`is_important`),
    KEY `is_completed` (`is_completed`),
    KEY `sort_order` (`sort_order`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note sharing table for sharing notes with other users
CREATE TABLE IF NOT EXISTS `note_shares` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `note_id` int(11) NOT NULL,
    `shared_with_user_id` int(11) NOT NULL,
    `permission` enum('view','comment','edit') DEFAULT 'view',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `note_id` (`note_id`),
    KEY `shared_with_user_id` (`shared_with_user_id`),
    UNIQUE KEY `unique_share` (`note_id`, `shared_with_user_id`),
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shared_with_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note comments table for comments on shared notes
CREATE TABLE IF NOT EXISTS `note_comments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `note_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `comment` text NOT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `note_id` (`note_id`),
    KEY `user_id` (`user_id`),
    KEY `created_at` (`created_at`),
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personal URLs table for user's personal bookmarks
CREATE TABLE IF NOT EXISTS `personal_urls` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `url` varchar(500) NOT NULL,
    `description` text,
    `category` varchar(100) DEFAULT NULL,
    `sort_order` int(11) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `category` (`category`),
    KEY `sort_order` (`sort_order`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin URLs table for system-wide bookmarks
CREATE TABLE IF NOT EXISTS `admin_urls` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `url` varchar(500) NOT NULL,
    `description` text,
    `category` varchar(100) DEFAULT NULL,
    `visible_for` enum('all','admin','manager','doer') DEFAULT 'all',
    `sort_order` int(11) DEFAULT 0,
    `created_by` int(11) NOT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `category` (`category`),
    KEY `visible_for` (`visible_for`),
    KEY `sort_order` (`sort_order`),
    KEY `created_by` (`created_by`),
    KEY `created_at` (`created_at`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SAMPLE DATA (Optional)
-- ========================================

-- Sample personal URL for testing
INSERT IGNORE INTO `personal_urls` (`user_id`, `title`, `url`, `description`, `category`, `sort_order`) VALUES
(1, 'Google', 'https://www.google.com', 'Search engine', 'Tools', 1),
(1, 'GitHub', 'https://github.com', 'Code repository hosting', 'Tools', 2);

-- Sample admin URL for testing (only if you have admin users)
-- INSERT IGNORE INTO `admin_urls` (`title`, `url`, `description`, `category`, `visible_for`, `sort_order`, `created_by`) VALUES
-- ('Company Portal', 'https://portal.company.com', 'Internal company portal', 'Work', 'all', 1, 1),
-- ('Admin Dashboard', 'https://admin.company.com', 'Administrative dashboard', 'Work', 'admin', 2, 1);

-- ========================================
-- INDEXES FOR PERFORMANCE
-- ========================================

-- Additional indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_notes_user_important` ON `notes` (`user_id`, `is_important`);
CREATE INDEX IF NOT EXISTS `idx_notes_user_completed` ON `notes` (`user_id`, `is_completed`);
CREATE INDEX IF NOT EXISTS `idx_notes_user_sort` ON `notes` (`user_id`, `sort_order`);

CREATE INDEX IF NOT EXISTS `idx_personal_urls_user_category` ON `personal_urls` (`user_id`, `category`);
CREATE INDEX IF NOT EXISTS `idx_personal_urls_user_sort` ON `personal_urls` (`user_id`, `sort_order`);

CREATE INDEX IF NOT EXISTS `idx_admin_urls_visible` ON `admin_urls` (`visible_for`, `sort_order`);
CREATE INDEX IF NOT EXISTS `idx_admin_urls_category` ON `admin_urls` (`category`, `sort_order`);

-- ========================================
-- VIEWS FOR EASIER QUERYING
-- ========================================

-- View for notes with sharing information
CREATE OR REPLACE VIEW `notes_with_shares` AS
SELECT 
    n.*,
    COUNT(DISTINCT ns.id) as shared_count,
    GROUP_CONCAT(DISTINCT u.username) as shared_with_users
FROM `notes` n
LEFT JOIN `note_shares` ns ON n.id = ns.note_id
LEFT JOIN `users` u ON ns.shared_with_user_id = u.id
GROUP BY n.id;

-- View for URLs with user information
CREATE OR REPLACE VIEW `personal_urls_with_user` AS
SELECT 
    pu.*,
    u.username as created_by_username
FROM `personal_urls` pu
JOIN `users` u ON pu.user_id = u.id;

-- View for admin URLs with creator information
CREATE OR REPLACE VIEW `admin_urls_with_creator` AS
SELECT 
    au.*,
    u.username as created_by_username
FROM `admin_urls` au
JOIN `users` u ON au.created_by = u.id;

