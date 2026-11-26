-- =====================================================================
-- MyEvent - Extra showcase data for the public demo (APP_DEMO=true).
-- Loaded by tools/demo_reset.php AFTER seed.sql, so reports, ratings,
-- waitlists and approvals have something to show. Never load it in production.
--
-- The extra participants cannot sign in: their password is not a valid hash.
-- Dates are relative to the reset day, so the demo never goes stale.
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO `users` (`user_id`, `category`, `name`, `email`, `phone`, `organization`, `recommend_events`, `password`, `role`, `created_at`) VALUES
(4,  'Student', 'Aisyah Rahman',   'aisyah@myevent.test',  '0131110004', 'Faculty of Computing',    'workshop, competition', '!', 'User', NOW() - INTERVAL 150 DAY),
(5,  'Student', 'Daniel Wong',     'daniel@myevent.test',  '0131110005', 'Faculty of Engineering',  'competition, sport',    '!', 'User', NOW() - INTERVAL 148 DAY),
(6,  'Student', 'Nurul Huda',      'nurul@myevent.test',   '0131110006', 'Faculty of Science',      'seminar, workshop',     '!', 'User', NOW() - INTERVAL 140 DAY),
(7,  'Student', 'Arjun Kumar',     'arjun@myevent.test',   '0131110007', 'Faculty of Computing',    'competition, workshop', '!', 'User', NOW() - INTERVAL 138 DAY),
(8,  'Staff',   'Siti Mariam',     'siti@myevent.test',    '0131110008', 'Library',                 'seminar, course',       '!', 'User', NOW() - INTERVAL 130 DAY),
(9,  'Student', 'Hafiz Ismail',    'hafiz@myevent.test',   '0131110009', 'Faculty of Engineering',  'sport, competition',    '!', 'User', NOW() - INTERVAL 128 DAY),
(10, 'Student', 'Tan Mei Ling',    'meiling@myevent.test', '0131110010', 'Faculty of Arts',         'workshop, festival',    '!', 'User', NOW() - INTERVAL 126 DAY),
(11, 'Student', 'Farah Nadia',     'farah@myevent.test',   '0131110011', 'Faculty of Business',     'seminar, course',       '!', 'User', NOW() - INTERVAL 100 DAY),
(12, 'Public',  'Kevin Lim',       'kevin@myevent.test',   '0131110012', 'Alumni',                  'sport, festival',       '!', 'User', NOW() - INTERVAL 95 DAY),
(13, 'Student', 'Amirul Hakim',    'amirul@myevent.test',  '0131110013', 'Faculty of Computing',    'competition, sport',    '!', 'User', NOW() - INTERVAL 90 DAY),
(14, 'Student', 'Priya Devi',      'priya@myevent.test',   '0131110014', 'Faculty of Science',      'workshop, seminar',     '!', 'User', NOW() - INTERVAL 70 DAY),
(15, 'Staff',   'Zulkifli Omar',   'zulkifli@myevent.test','0131110015', 'Faculty of Business',     'seminar, sport',        '!', 'User', NOW() - INTERVAL 60 DAY);

-- Past events (reports, attendance, ratings) and one small upcoming event that is already full.
INSERT INTO `events` (`event_id`, `event_name`, `description`, `category_id`, `contact_no`, `venue`, `event_date`, `event_time`, `end_time`, `mode`, `reg_close_date`, `max_participants`, `fee`, `remarks`, `visibility`, `poster_path`, `created_by`, `created_at`) VALUES
(7,  '24-Hour Campus Hackathon', 'Teams of three build a working prototype for a campus problem in 24 hours.', 3, '0129876543', 'Faculty of Computing, Lab 3', CURDATE() - INTERVAL 110 DAY, '09:00:00', NULL, 'Physical', CURDATE() - INTERVAL 117 DAY, 30, 0.00, 'Meals provided.', 'Public', NULL, 2, NOW() - INTERVAL 135 DAY),
(8,  'Career Talk: Life in Tech', 'Graduates share what their first year in industry was really like.', 2, '0123456789', 'Online (Google Meet)', CURDATE() - INTERVAL 75 DAY, '20:00:00', '21:30:00', 'Online', CURDATE() - INTERVAL 76 DAY, 0, 0.00, 'The link is sent to approved participants.', 'Public', NULL, 1, NOW() - INTERVAL 95 DAY),
(9,  'Intro to Python Workshop', 'From variables to a small data project, for complete beginners.', 1, '0129876543', 'Faculty of Computing, Lab 1', CURDATE() - INTERVAL 45 DAY, '14:00:00', '17:00:00', 'Physical', CURDATE() - INTERVAL 50 DAY, 25, 5.00, 'Bring a laptop.', 'Public', NULL, 2, NOW() - INTERVAL 65 DAY),
(10, 'Mini Futsal League', 'Five-a-side league between faculties, finals night.', 5, '0129876543', 'Indoor Sports Hall', CURDATE() - INTERVAL 10 DAY, '19:00:00', '22:00:00', 'Physical', CURDATE() - INTERVAL 14 DAY, 40, 0.00, 'Futsal shoes only.', 'Public', NULL, 2, NOW() - INTERVAL 30 DAY),
(11, 'UI Design Bootcamp', 'A small, hands-on morning on wireframes and prototypes. Limited to five seats.', 1, '0129876543', 'Design Studio, Level 2', CURDATE() + INTERVAL 12 DAY, '09:00:00', '12:30:00', 'Physical', CURDATE() + INTERVAL 7 DAY, 5, 0.00, 'Full? Join the waitlist: a seat is offered as soon as one frees up.', 'Public', NULL, 2, NOW() - INTERVAL 8 DAY);

INSERT INTO `registrations` (`user_id`, `event_id`, `approval_status`, `attendance_status`, `checked_in_at`, `registration_date`) VALUES
-- Hackathon (110 days ago)
(4,  7, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 110 DAY, '08:50:00'), NOW() - INTERVAL 125 DAY),
(5,  7, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 110 DAY, '08:52:00'), NOW() - INTERVAL 124 DAY),
(7,  7, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 110 DAY, '08:55:00'), NOW() - INTERVAL 123 DAY),
(9,  7, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 110 DAY, '09:01:00'), NOW() - INTERVAL 121 DAY),
(10, 7, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 110 DAY, '09:04:00'), NOW() - INTERVAL 120 DAY),
(13, 7, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 110 DAY, '09:10:00'), NOW() - INTERVAL 119 DAY),
(14, 7, 'Approved', 'Absent',  NULL, NOW() - INTERVAL 118 DAY),
(12, 7, 'Rejected', 'Absent',  NULL, NOW() - INTERVAL 118 DAY),
-- Career talk (75 days ago)
(3,  8, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 75 DAY, '19:58:00'), NOW() - INTERVAL 90 DAY),
(4,  8, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 75 DAY, '19:59:00'), NOW() - INTERVAL 89 DAY),
(5,  8, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 75 DAY, '20:00:00'), NOW() - INTERVAL 88 DAY),
(6,  8, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 75 DAY, '20:01:00'), NOW() - INTERVAL 86 DAY),
(8,  8, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 75 DAY, '20:02:00'), NOW() - INTERVAL 85 DAY),
(10, 8, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 75 DAY, '20:03:00'), NOW() - INTERVAL 84 DAY),
(11, 8, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 75 DAY, '20:05:00'), NOW() - INTERVAL 83 DAY),
(12, 8, 'Approved', 'Absent',  NULL, NOW() - INTERVAL 82 DAY),
(15, 8, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 75 DAY, '20:06:00'), NOW() - INTERVAL 80 DAY),
-- Python workshop (45 days ago)
(4,  9, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '13:50:00'), NOW() - INTERVAL 60 DAY),
(6,  9, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '13:52:00'), NOW() - INTERVAL 59 DAY),
(7,  9, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '13:55:00'), NOW() - INTERVAL 57 DAY),
(9,  9, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '13:58:00'), NOW() - INTERVAL 55 DAY),
(11, 9, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '14:00:00'), NOW() - INTERVAL 54 DAY),
(13, 9, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '14:03:00'), NOW() - INTERVAL 52 DAY),
(14, 9, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '14:05:00'), NOW() - INTERVAL 51 DAY),
(15, 9, 'Approved', 'Absent',  NULL, NOW() - INTERVAL 50 DAY),
-- Futsal finals (10 days ago): the demo participant attended and has not rated it yet
(3,  10, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '18:50:00'), NOW() - INTERVAL 25 DAY),
(5,  10, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '18:51:00'), NOW() - INTERVAL 24 DAY),
(7,  10, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '18:53:00'), NOW() - INTERVAL 22 DAY),
(9,  10, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '18:55:00'), NOW() - INTERVAL 20 DAY),
(13, 10, 'Approved', 'Present', TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '18:58:00'), NOW() - INTERVAL 18 DAY),
-- UI bootcamp (upcoming, full)
(4,  11, 'Approved', 'Absent', NULL, NOW() - INTERVAL 6 DAY),
(6,  11, 'Approved', 'Absent', NULL, NOW() - INTERVAL 6 DAY),
(10, 11, 'Approved', 'Absent', NULL, NOW() - INTERVAL 5 DAY),
(11, 11, 'Approved', 'Absent', NULL, NOW() - INTERVAL 4 DAY),
(14, 11, 'Approved', 'Absent', NULL, NOW() - INTERVAL 3 DAY),
-- Upcoming seed events: a mix of approved and pending (the organiser has requests to review)
(4,  1, 'Approved', 'Absent', NULL, NOW() - INTERVAL 12 DAY),
(6,  1, 'Approved', 'Absent', NULL, NOW() - INTERVAL 10 DAY),
(10, 1, 'Approved', 'Absent', NULL, NOW() - INTERVAL 9 DAY),
(11, 1, 'Pending',  'Absent', NULL, NOW() - INTERVAL 2 DAY),
(12, 1, 'Pending',  'Absent', NULL, NOW() - INTERVAL 1 DAY),
(5,  2, 'Approved', 'Absent', NULL, NOW() - INTERVAL 8 DAY),
(7,  2, 'Approved', 'Absent', NULL, NOW() - INTERVAL 7 DAY),
(13, 2, 'Pending',  'Absent', NULL, NOW() - INTERVAL 1 DAY),
(7,  4, 'Approved', 'Absent', NULL, NOW() - INTERVAL 6 DAY),
(9,  4, 'Approved', 'Absent', NULL, NOW() - INTERVAL 5 DAY),
(4,  5, 'Approved', 'Absent', NULL, NOW() - INTERVAL 9 DAY),
(5,  5, 'Approved', 'Absent', NULL, NOW() - INTERVAL 8 DAY),
(8,  5, 'Approved', 'Absent', NULL, NOW() - INTERVAL 4 DAY),
(10, 6, 'Approved', 'Absent', NULL, NOW() - INTERVAL 11 DAY),
(11, 6, 'Approved', 'Absent', NULL, NOW() - INTERVAL 10 DAY),
(12, 6, 'Approved', 'Absent', NULL, NOW() - INTERVAL 7 DAY),
(13, 6, 'Approved', 'Absent', NULL, NOW() - INTERVAL 5 DAY),
(14, 6, 'Approved', 'Absent', NULL, NOW() - INTERVAL 3 DAY),
(15, 6, 'Approved', 'Absent', NULL, NOW() - INTERVAL 2 DAY);

INSERT INTO `waitlist` (`event_id`, `user_id`, `created_at`) VALUES
(11, 5, NOW() - INTERVAL 2 DAY),
(11, 9, NOW() - INTERVAL 1 DAY);

INSERT INTO `feedback` (`event_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(7, 4,  5, 'Best weekend of the semester. Mentors were great.', NOW() - INTERVAL 108 DAY),
(7, 5,  4, 'Well run, but the Wi-Fi struggled at night.',       NOW() - INTERVAL 108 DAY),
(7, 7,  5, NULL,                                                NOW() - INTERVAL 107 DAY),
(7, 9,  4, 'Would love a longer judging session.',              NOW() - INTERVAL 107 DAY),
(7, 10, 3, 'Fun, but the theme was announced too late.',        NOW() - INTERVAL 106 DAY),
(7, 13, 5, NULL,                                                NOW() - INTERVAL 105 DAY),
(8, 3,  4, 'Very useful tips on internships.',                  NOW() - INTERVAL 74 DAY),
(8, 4,  5, NULL,                                                NOW() - INTERVAL 74 DAY),
(8, 5,  4, 'Good speakers. Please record it next time.',        NOW() - INTERVAL 74 DAY),
(8, 6,  4, NULL,                                                NOW() - INTERVAL 73 DAY),
(8, 8,  5, 'Honest and practical.',                             NOW() - INTERVAL 73 DAY),
(8, 10, 5, NULL,                                                NOW() - INTERVAL 72 DAY),
(8, 11, 3, 'A bit too short for the Q&A.',                      NOW() - INTERVAL 72 DAY),
(8, 15, 4, NULL,                                                NOW() - INTERVAL 70 DAY),
(9, 4,  5, 'Clear pace for beginners.',                         NOW() - INTERVAL 44 DAY),
(9, 6,  4, NULL,                                                NOW() - INTERVAL 44 DAY),
(9, 7,  4, 'More exercises please.',                            NOW() - INTERVAL 43 DAY),
(9, 9,  5, NULL,                                                NOW() - INTERVAL 43 DAY),
(9, 11, 4, NULL,                                                NOW() - INTERVAL 42 DAY),
(9, 13, 3, 'The lab PCs were slow.',                            NOW() - INTERVAL 42 DAY),
(9, 14, 5, 'I finally understand loops!',                       NOW() - INTERVAL 41 DAY),
(10, 5, 5, 'Great atmosphere on finals night.',                 NOW() - INTERVAL 9 DAY),
(10, 7, 4, NULL,                                                NOW() - INTERVAL 9 DAY),
(10, 9, 4, NULL,                                                NOW() - INTERVAL 8 DAY),
(10, 13, 5, 'Well refereed.',                                   NOW() - INTERVAL 8 DAY);

INSERT INTO `notifications` (`user_id`, `message`, `is_read`, `created_at`) VALUES
(2, '📩 <strong>Kevin Lim</strong> has registered for: <strong>Art & Sketching Workshop</strong>.', 0, NOW() - INTERVAL 1 DAY),
(2, '📩 <strong>Amirul Hakim</strong> has registered for: <strong>Campus Photography Walk</strong>.', 0, NOW() - INTERVAL 1 DAY),
(3, '⭐ How was <strong>Mini Futsal League</strong>? Rate it from My registrations.', 0, NOW() - INTERVAL 9 DAY);
