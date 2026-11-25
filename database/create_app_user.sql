-- =====================================================================
-- MyEvent - least-privilege database account for the web application.
--
-- Run ONCE as an administrator (root), after replacing the password:
--   mysql -u root -p < database/create_app_user.sql
-- Then put the same values in .env:
--   DB_USERNAME=myevent_app
--   DB_PASSWORD=<the password you chose>
--
-- The application only needs to read and write rows. It cannot change the
-- schema, drop tables or touch other databases, so a bug or a leaked .env
-- cannot destroy the structure. Keep using root (or another admin account)
-- for migrations in database/migrations/.
--
-- On shared hosting (cPanel) create the user in "MySQL Databases" instead and
-- tick only the privileges listed below.
-- =====================================================================

-- 1) Choose a long random password, e.g.:  php -r "echo bin2hex(random_bytes(20));"
CREATE USER IF NOT EXISTS 'myevent_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_TO_A_LONG_RANDOM_PASSWORD';

-- 2) Row access for the app, plus what tools/backup.php (mysqldump) needs.
GRANT SELECT, INSERT, UPDATE, DELETE, LOCK TABLES, SHOW VIEW, TRIGGER
    ON `myevent`.* TO 'myevent_app'@'localhost';

FLUSH PRIVILEGES;
