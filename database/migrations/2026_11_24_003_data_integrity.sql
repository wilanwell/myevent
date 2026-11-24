-- Phase 2: data integrity fixes.
-- 1) The original admin account has user_id = 0, which AUTO_INCREMENT treats as
--    "generate a new id" and breaks re-imports. Move it to id 1 (unused).
SET FOREIGN_KEY_CHECKS = 0;
UPDATE users         SET user_id    = 1 WHERE user_id    = 0;
UPDATE events        SET created_by = 1 WHERE created_by = 0;
UPDATE registrations SET user_id    = 1 WHERE user_id    = 0;
UPDATE notifications SET user_id    = 1 WHERE user_id    = 0;
UPDATE feedback      SET user_id    = 1 WHERE user_id    = 0;
UPDATE user_event_recommend SET user_id = 1 WHERE user_id = 0;
SET FOREIGN_KEY_CHECKS = 1;

-- 2) Fee is money: store as DECIMAL, never free text.
UPDATE events SET fee = '0' WHERE fee IS NULL OR fee = '' OR LOWER(fee) IN ('free', 'percuma');
ALTER TABLE events MODIFY fee DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- 3) Required event fields and sane defaults.
ALTER TABLE events
  MODIFY event_date DATE NOT NULL,
  MODIFY venue VARCHAR(255) NOT NULL,
  MODIFY mode ENUM('Physical','Online','Hybrid') NOT NULL DEFAULT 'Physical',
  MODIFY visibility ENUM('Public','Private') NOT NULL DEFAULT 'Public',
  MODIFY max_participants INT(11) NOT NULL DEFAULT 0,
  MODIFY poster_path VARCHAR(255) DEFAULT NULL,
  ADD CONSTRAINT chk_events_fee CHECK (fee >= 0),
  ADD CONSTRAINT chk_events_capacity CHECK (max_participants >= 0),
  ADD KEY idx_events_date (event_date);

-- 4) Profile pictures: NULL means "no picture" (the old default file never existed).
UPDATE users SET profile_pic = NULL WHERE profile_pic = 'default_avatar.png' OR profile_pic = '';
ALTER TABLE users
  MODIFY profile_pic VARCHAR(255) DEFAULT NULL,
  MODIFY role ENUM('Admin','User') NOT NULL DEFAULT 'User';

-- 5) Registration statuses are never NULL.
ALTER TABLE registrations
  MODIFY approval_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  MODIFY attendance_status ENUM('Absent','Present') NOT NULL DEFAULT 'Absent';

-- 6) Unread-notification badge query runs on every page.
ALTER TABLE notifications
  MODIFY is_read TINYINT(1) NOT NULL DEFAULT 0,
  ADD KEY idx_notifications_unread (user_id, is_read);

-- 7) Poster files referenced by these events no longer exist; clear so a placeholder shows.
--    (Handled in application code via poster_url(); kept here as documentation.)
