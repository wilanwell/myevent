-- =====================================================================
-- MyEvent - Demo seed data (for local development / demos ONLY)
-- Load AFTER schema.sql into an EMPTY database.
--
-- Demo accounts (change or delete these before going live):
--   Admin     admin@myevent.test      Admin-Demo-2026
--   Organiser organiser@myevent.test  User-Demo-2026
--   Student   student@myevent.test    User-Demo-2026
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO `event_category` (`category_id`, `categoryName`, `categoryDesc`) VALUES
(1, 'Workshop',    'F2F/Online course or hands-on as workshop'),
(2, 'Seminar',     'F2F/Online seminar, presentation, etc.'),
(3, 'Competition', 'Competition event'),
(4, 'Festival',    'All festival event'),
(5, 'Sport',       'All types of sport events'),
(6, 'Course',      'All types of educational course events');

INSERT INTO `users` (`user_id`, `category`, `name`, `email`, `phone`, `organization`, `recommend_events`, `password`, `role`) VALUES
(1, 'Staff',   'MyEvent Admin',  'admin@myevent.test',     '0123456789', 'Event Management Unit', 'workshop, seminar', '$2y$10$y9obHVnwaPlFggosGFnQQ.v82a1kzWWAjXvBXAzvcyo0M.IyWiox2', 'Admin'),
(2, 'Staff',   'Demo Organiser', 'organiser@myevent.test', '0129876543', 'Faculty of Computing',  'workshop, competition', '$2y$10$BUioOQlCjEXxtjAxRjE8iul3jVXJZQ6N.76r6qDuI/DBHz5xEGOGa', 'User'),
(3, 'Student', 'Demo Student',   'student@myevent.test',   '0131234567', 'Faculty of Science',    'sport, festival', '$2y$10$BUioOQlCjEXxtjAxRjE8iul3jVXJZQ6N.76r6qDuI/DBHz5xEGOGa', 'User');

-- Dates are relative to the install day so the demo always has open events.
INSERT INTO `events` (`event_id`, `event_name`, `description`, `category_id`, `contact_no`, `venue`, `event_date`, `event_time`, `mode`, `reg_close_date`, `max_participants`, `fee`, `remarks`, `visibility`, `poster_path`, `created_by`) VALUES
(1, 'Art & Sketching Workshop', 'Hands-on drawing techniques for beginners.', 1, '0129876543', 'Faculty of Arts, Studio 2', CURDATE() + INTERVAL 21 DAY, '09:00:00', 'Physical', CURDATE() + INTERVAL 14 DAY, 30, 0.00,  'Bring your own sketchbook.', 'Public', 'art.jpg',    2),
(2, 'Campus Photography Walk',  'Learn composition while exploring campus.', 1, '0129876543', 'Main Library Entrance',     CURDATE() + INTERVAL 28 DAY, '08:00:00', 'Physical', CURDATE() + INTERVAL 21 DAY, 20, 10.00, 'Camera or phone required.', 'Public', 'kamera.jpg', 2),
(3, 'Beach Cleaning Day',       'Community clean-up in conjunction with Ocean Day.', 4, '0123456789', 'Tanjung Aru Beach', CURDATE() + INTERVAL 35 DAY, '07:30:00', 'Physical', CURDATE() + INTERVAL 30 DAY, 0, 0.00, 'Gloves and bags provided.', 'Public', 'beach.jpg', 1),
(4, 'Inter-Faculty Chess Open', 'Rapid chess tournament, 7 rounds Swiss.',   3, '0129876543', 'Student Centre Hall',       CURDATE() + INTERVAL 42 DAY, '10:00:00', 'Physical', CURDATE() + INTERVAL 35 DAY, 64, 5.00,  'Bring your own clock if possible.', 'Public', 'chess.jpg', 2),
(5, 'Sunrise Hike',             'Guided hike to the campus peak.',           5, '0123456789', 'Sports Complex Gate',       CURDATE() + INTERVAL 14 DAY, '05:30:00', 'Physical', CURDATE() + INTERVAL 10 DAY, 40, 0.00,  'Sport attire and water bottle.', 'Public', 'hiking.jpg', 1),
(6, 'Fun Run 5K',               'Annual charity run around campus.',         5, '0123456789', 'Stadium',                   CURDATE() + INTERVAL 49 DAY, '06:30:00', 'Physical', CURDATE() + INTERVAL 40 DAY, 200, 15.00, 'Race kit included.', 'Public', 'run.jpg', 1);

INSERT INTO `registrations` (`user_id`, `event_id`, `approval_status`) VALUES
(3, 1, 'Approved'),
(3, 4, 'Pending');

INSERT INTO `notifications` (`user_id`, `message`, `is_read`) VALUES
(3, '✅ Your registration for <strong>Art & Sketching Workshop</strong> has been Approved!', 0),
(2, '📩 <strong>Demo Student</strong> has registered for: <strong>Inter-Faculty Chess Open</strong>.', 0);
