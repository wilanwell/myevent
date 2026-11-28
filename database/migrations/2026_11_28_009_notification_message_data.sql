-- Phase 11: notifications follow the reader's current language.
-- message_data keeps the English text and its values (JSON); the bell translates it when shown.
-- message stays as the text in the recipient's language when it was sent (older rows only have this).
ALTER TABLE notifications
  ADD COLUMN message_data TEXT DEFAULT NULL COMMENT 'JSON {text, vars, icon}: translated when shown' AFTER message;

-- The sample notifications from seed.sql and demo.sql, so they switch language too.
UPDATE notifications SET message_data = '{"text":"Your registration for {event} has been approved. Your ticket is ready in My registrations.","vars":{"event":{"b":"Art & Sketching Workshop"}},"icon":"✅"}'
 WHERE message_data IS NULL AND message = '✅ Your registration for <strong>Art & Sketching Workshop</strong> has been Approved!';
UPDATE notifications SET message_data = '{"text":"{name} has registered for: {event}.","vars":{"name":{"b":"Demo Student"},"event":{"b":"Inter-Faculty Chess Open"}},"icon":"📩"}'
 WHERE message_data IS NULL AND message = '📩 <strong>Demo Student</strong> has registered for: <strong>Inter-Faculty Chess Open</strong>.';
UPDATE notifications SET message_data = '{"text":"{name} has registered for: {event}.","vars":{"name":{"b":"Kevin Lim"},"event":{"b":"Art & Sketching Workshop"}},"icon":"📩"}'
 WHERE message_data IS NULL AND message = '📩 <strong>Kevin Lim</strong> has registered for: <strong>Art & Sketching Workshop</strong>.';
UPDATE notifications SET message_data = '{"text":"{name} has registered for: {event}.","vars":{"name":{"b":"Amirul Hakim"},"event":{"b":"Campus Photography Walk"}},"icon":"📩"}'
 WHERE message_data IS NULL AND message = '📩 <strong>Amirul Hakim</strong> has registered for: <strong>Campus Photography Walk</strong>.';
UPDATE notifications SET message_data = '{"text":"The {what} of {event} changed. Check the new details.","vars":{"what":[{"t":"venue"}],"event":{"b":"Art & Sketching Workshop"}},"icon":"📅"}',
       message = '📅 The venue of <strong>Art & Sketching Workshop</strong> changed. Check the new details.'
 WHERE message_data IS NULL AND message = '⭐ How was <strong>Mini Futsal League</strong>? Rate it from My registrations.';
