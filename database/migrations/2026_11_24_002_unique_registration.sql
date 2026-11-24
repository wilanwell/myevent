-- One registration per user per event (prevents double-submit races).
ALTER TABLE `registrations`
  ADD UNIQUE KEY `uniq_user_event` (`user_id`, `event_id`);
