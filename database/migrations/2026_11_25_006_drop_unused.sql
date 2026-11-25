-- Phase 6: remove schema left over from the original prototype.
-- Both were verified empty and unused by the application before removal.
DROP TABLE IF EXISTS `user_event_recommend`;   -- interests live in users.recommend_events
ALTER TABLE `events` DROP COLUMN `type`;       -- always NULL; categories use category_id
