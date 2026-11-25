-- Phase 7: email notifications.

-- 1) Outbox: emails are queued here and delivered by tools/send_mail.php (cron),
--    so requests never wait for SMTP and failed sends are retried.
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
  KEY `idx_mail_queue_due` (`status`, `next_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Per-user email preferences (on by default).
ALTER TABLE `users`
  ADD COLUMN `email_participant` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Emails about my own registrations and events I joined' AFTER `profile_pic`,
  ADD COLUMN `email_organiser`   tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Emails when people register for events I organise' AFTER `email_participant`;

-- 3) Day-before reminders are sent once per registration.
ALTER TABLE `registrations` ADD COLUMN `reminder_sent_at` datetime DEFAULT NULL AFTER `checked_in_at`;
