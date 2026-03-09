-- Info-Graph database schema
-- Run once to set up the database.

CREATE TABLE IF NOT EXISTS `items` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_name`   VARCHAR(255) NOT NULL,
    `author_name` VARCHAR(255) NOT NULL DEFAULT '',
    `link`        VARCHAR(2048) DEFAULT NULL,
    `notes`       TEXT,
    `rating`      TINYINT UNSIGNED DEFAULT NULL,
    `flag`        VARCHAR(50) DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
    `id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    UNIQUE KEY `uq_tag_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `item_tags` (
    `item_id` INT UNSIGNED NOT NULL,
    `tag_id`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`item_id`, `tag_id`),
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tag_id`)  REFERENCES `tags`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
