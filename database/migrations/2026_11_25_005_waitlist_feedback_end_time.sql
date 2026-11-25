-- Phase 5: waitlist, post-event feedback rules, optional event end time.

-- 1) Waitlist: people waiting for a place in a full event, served first come first served.
CREATE TABLE IF NOT EXISTS `waitlist` (
  `waitlist_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`waitlist_id`),
  UNIQUE KEY `uniq_waitlist_user_event` (`user_id`, `event_id`),
  KEY `idx_waitlist_event_order` (`event_id`, `created_at`, `waitlist_id`),
  CONSTRAINT `waitlist_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  CONSTRAINT `waitlist_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Feedback: one per participant per event, rating 1-5, editable.
ALTER TABLE `feedback`
  ADD UNIQUE KEY `uniq_feedback_user_event` (`user_id`, `event_id`),
  ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() AFTER `created_at`,
  ADD CONSTRAINT `chk_feedback_rating` CHECK (`rating` BETWEEN 1 AND 5);

-- 3) Optional end time (calendar exports default to two hours when empty).
ALTER TABLE `events` ADD COLUMN `end_time` time DEFAULT NULL AFTER `event_time`;
