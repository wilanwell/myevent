-- =====================================================================
-- MyEvent - Database schema (structure only)
-- Target: MariaDB 10.4+ / MySQL 8.0+, utf8mb4
--
-- Fresh install:   mysql -u root -e "CREATE DATABASE myevent CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
--                  mysql -u root myevent < database/schema.sql
--                  mysql -u root myevent < database/seed.sql      (optional demo data)
--
-- Existing installs are upgraded with the files in database/migrations/.
-- This file always reflects the schema after the latest migration.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


CREATE TABLE IF NOT EXISTS `audit_log` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) DEFAULT NULL,
  `actor_name` varchar(255) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `target_type` varchar(32) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_actor` (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `event_category` (
  `category_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `categoryName` varchar(100) NOT NULL,
  `categoryDesc` varchar(100) NOT NULL,
  `createDate` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `venue` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `mode` enum('Physical','Online','Hybrid') NOT NULL DEFAULT 'Physical',
  `remarks` text DEFAULT NULL,
  `visibility` enum('Public','Private') NOT NULL DEFAULT 'Public',
  `poster_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reg_close_date` date DEFAULT NULL,
  `max_participants` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`event_id`),
  KEY `category_id` (`category_id`),
  KEY `fk_events_createdby` (`created_by`),
  KEY `idx_events_date` (`event_date`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `event_category` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_events_createdby` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_events_fee` CHECK (`fee` >= 0),
  CONSTRAINT `chk_events_capacity` CHECK (`max_participants` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Post-event ratings (1-5) and private comments, one per participant per event.
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_feedback_user_event` (`user_id`,`event_id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_feedback_rating` CHECK (`rating` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `idx_email_time` (`email`,`attempted_at`),
  KEY `idx_ip_time` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Outbox delivered by tools/send_mail.php (cron every 5 minutes).
CREATE TABLE IF NOT EXISTS `mail_queue` (
  `mail_id` int(11) NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_text` mediumtext NOT NULL,
  `body_html` mediumtext DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `last_error` varchar(500) DEFAULT NULL,
  `next_attempt_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`mail_id`),
  KEY `idx_mail_queue_due` (`status`,`next_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `notif_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `message_data` text DEFAULT NULL COMMENT 'JSON {text, vars, icon}: translated when shown',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notif_id`),
  KEY `idx_notifications_unread` (`user_id`,`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `uniq_token_hash` (`token_hash`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  CONSTRAINT `password_resets_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `registrations` (
  `reg_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `approval_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `attendance_status` enum('Absent','Present') NOT NULL DEFAULT 'Absent',
  `checked_in_at` datetime DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reg_id`),
  UNIQUE KEY `uniq_user_event` (`user_id`,`event_id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `category` enum('Staff','Student','Public') NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `organization` varchar(255) NOT NULL,
  `recommend_events` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','User') NOT NULL DEFAULT 'User',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bio` text DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `email_participant` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Emails about my own registrations and events I joined',
  `email_organiser` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Emails when people register for events I organise',
  `locale` varchar(5) DEFAULT NULL COMMENT 'Interface and email language: en or ms (NULL = not chosen yet)',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- First-come-first-served queue for full events (see include/waitlist.php).
CREATE TABLE IF NOT EXISTS `waitlist` (
  `waitlist_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`waitlist_id`),
  UNIQUE KEY `uniq_waitlist_user_event` (`user_id`,`event_id`),
  KEY `idx_waitlist_event_order` (`event_id`,`created_at`,`waitlist_id`),
  CONSTRAINT `waitlist_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  CONSTRAINT `waitlist_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
