-- -----------------------------------------------------------------------
-- Enterprise Inventory Agent — Database Schema
-- MySQL 5.7+ / MariaDB 10.4+
-- -----------------------------------------------------------------------

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------------------------
-- Core devices table
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devices` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `hostname`         VARCHAR(255)  NOT NULL DEFAULT '',
    `serial`           VARCHAR(100)  NOT NULL DEFAULT '',
    `model`            VARCHAR(255)           DEFAULT NULL,
    `manufacturer`     VARCHAR(255)           DEFAULT NULL,
    `chassis_type`     VARCHAR(100)           DEFAULT NULL,
    `os_name`          VARCHAR(255)           DEFAULT NULL,
    `os_build`         VARCHAR(100)           DEFAULT NULL,
    `os_ubr`           VARCHAR(50)            DEFAULT NULL,
    `location`         VARCHAR(255)           DEFAULT NULL,
    `location_agent`   VARCHAR(255)           DEFAULT NULL,
    `status`           ENUM('Active','In-Store','Scrapped') NOT NULL DEFAULT 'Active',
    `last_seen`        DATE                   DEFAULT NULL,
    `uptime_seconds`   BIGINT UNSIGNED        DEFAULT 0,
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_serial` (`serial`),
    KEY `idx_hostname`  (`hostname`),
    KEY `idx_status`    (`status`),
    KEY `idx_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Asset tag mapping (desktop hardware)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `asset_tag_map` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `asset_tag`    VARCHAR(100)           DEFAULT NULL,
    `serial_number` VARCHAR(100)  NOT NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_asset_tag`    (`asset_tag`),
    KEY `idx_serial_number` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Device hardware-change audit log
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `device_change_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `device_id`   INT UNSIGNED NOT NULL,
    `change_type` VARCHAR(100) NOT NULL,
    `old_value`   TEXT         DEFAULT NULL,
    `new_value`   TEXT         DEFAULT NULL,
    `is_sent`     TINYINT(1)   NOT NULL DEFAULT 0,
    `logged_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_device_id` (`device_id`),
    KEY `idx_is_sent`   (`is_sent`),
    CONSTRAINT `fk_dcl_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Device history snapshots (used by compare_new.php)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `device_history` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `device_id`     INT UNSIGNED NOT NULL,
    `snapshot_time` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `raw_json`      LONGTEXT     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_dh_device_time` (`device_id`, `snapshot_time`),
    CONSTRAINT `fk_dh_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Patch / Windows Update status
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `patch_status` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `device_id`    INT UNSIGNED NOT NULL,
    `kb_number`    VARCHAR(20)  NOT NULL,
    `install_date` DATE         DEFAULT NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ps_device_date` (`device_id`, `install_date`),
    CONSTRAINT `fk_ps_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Installed software inventory
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `installed_software` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `device_id`     INT UNSIGNED NOT NULL,
    `software_name` VARCHAR(500) NOT NULL,
    `version`       VARCHAR(100)           DEFAULT NULL,
    `install_date`  DATE                   DEFAULT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_is_device`    (`device_id`),
    KEY `idx_is_sw_name`   (`software_name`(191)),
    CONSTRAINT `fk_is_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Stock / peripheral inventory
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_inventory` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category`         VARCHAR(100) NOT NULL,
    `model_name`       VARCHAR(255) NOT NULL,
    `current_stock`    INT          NOT NULL DEFAULT 0,
    `min_alert_level`  INT          NOT NULL DEFAULT 5,
    `modified_by`      VARCHAR(100)           DEFAULT NULL,
    `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_model_name` (`model_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Stock transaction audit log
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id`     INT UNSIGNED NOT NULL,
    `action_type` ENUM('Receive','Issue') NOT NULL,
    `quantity`    INT          NOT NULL,
    `admin_user`  VARCHAR(100) NOT NULL,
    `timestamp`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sl_item` (`item_id`),
    CONSTRAINT `fk_sl_item` FOREIGN KEY (`item_id`) REFERENCES `stock_inventory` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
