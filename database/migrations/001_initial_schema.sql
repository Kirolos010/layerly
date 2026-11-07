-- Migration 001: Initial Schema (Projects and Rate Limits)
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `owner_token` VARCHAR(64) NOT NULL,
    `name` VARCHAR(255) NOT NULL DEFAULT 'Untitled Design',
    `json_data` LONGTEXT NOT NULL,
    `thumbnail` MEDIUMTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_owner_token` (`owner_token`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `ip` VARCHAR(45) NOT NULL,
    `window_type` VARCHAR(10) NOT NULL DEFAULT 'minute',
    `window_start` INT UNSIGNED NOT NULL,
    `count` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`ip`, `window_type`, `window_start`),
    INDEX `idx_window_cleanup` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

