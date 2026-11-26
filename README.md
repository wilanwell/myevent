# MyEvent

Campus event management system: event announcement, registration, organiser
approval, attendance tracking, notifications and participant exports.

**Stack:** PHP 8.1+ (mysqli), MariaDB 10.4+ / MySQL 8.0+, Apache 2.4 (`mod_rewrite`/`.htaccess` enabled).

> Deploying to a real server? Follow **[DEPLOY.md](DEPLOY.md)** and finish with `php tools/check_production.php`.

## Installation (XAMPP / local)

1. Put the project in `htdocs/myevent`.
2. Copy the environment file and adjust it:
   ```
   cp .env.example .env
   ```
   For local development set `APP_DEBUG=true`. **Never enable debug on a public server.**
3. Create the database and load the schema:
   ```
   mysql -u root -e "CREATE DATABASE myevent CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
   mysql -u root myevent < database/schema.sql
   mysql -u root myevent < database/seed.sql   # optional demo data
   ```
4. Open http://localhost/myevent.

Generate `APP_KEY` (signs ticket QR codes) and put it in `.env`:
```
php -r "echo bin2hex(random_bytes(32));"
```

Demo accounts created by `seed.sql` are listed at the top of that file.
Delete or change them before going live.

## Upgrading an existing database

Apply the files in `database/migrations/` that have not been run yet, in filename order:
```
mysql -u root myevent < database/migrations/<file>.sql
```
`database/schema.sql` always reflects the schema after the latest migration.
Back up first (`mysqldump -u root myevent > backup.sql`).

## Participant features

- **Waitlist.** When an event is full and registration is still open, people can join a
  first-come-first-served waitlist. When a place frees up (a cancellation, a rejection, or a
  higher capacity), the first person in line becomes a *Pending* registration automatically and
  is notified; the organiser approves as usual. Logic lives in `include/waitlist.php`.
- **Feedback.** Participants with an approved registration can rate an event (1 to 5 stars, with an
  optional comment) once it has started, and edit it later. The average rating is public on the
  event page; comments are visible only to the organiser and admins.
- **Add to calendar.** Event pages and tickets link to Google Calendar and to a downloadable
  `.ics` file (`event_calendar.php`) for Outlook and Apple Calendar, with a reminder one hour before.
  Organisers can set an optional end time; without one, calendars assume two hours.

## Reports

**Admin → Reports** covers every event (optionally one organiser); **Organise → Reports** shows an organiser
their own events only. Both offer a period filter (last 30 / 90 days, 12 months, all time or custom dates,
by event date) and show:

- headline numbers: events, registrations and unique people, attendance rate, average rating
- registrations per month (with a table view), registrations by participant category, rating distribution
- a per-event table: registrations against capacity, attendance, rating and waitlist

**Export CSV** downloads the per-event table (every event, not just the 50 shown) and **Print or save PDF**
prints a clean copy (sidebar and filters hidden), which the browser can save as PDF. Admin exports are written
to the audit log. Definitions: *registrations* are pending + approved places; *attendance* is checked-in ÷ approved,
only for events that have taken place. Queries live in `include/reports.php`; charts are plain HTML/CSS
(no chart library), with hover and keyboard focus showing exact values.

## Email notifications

Every notification appears under the bell icon and, unless the person switched it off in
*My profile → Email notifications*, also as a branded HTML email (with a plain-text version):

| Who | When |
|---|---|
| Participant | Registration approved (with ticket link) or not approved; moved off the waitlist; reminder ~24 hours before the event; the event's date, time or venue changed; the event was cancelled |
| Organiser | Someone registered; someone moved up from the waitlist |

Calls go through `notify_user()` in `include/notify.php`. In development (`MAIL_DRIVER=log`) emails are
written to `storage/mail/` (`.eml`, `.txt` and `.html` copies) straight away. In production they are queued and
sent by `php tools/send_mail.php` every 5 minutes (see DEPLOY.md), which also queues the reminders.

## Time zone

`APP_TIMEZONE` (default `Asia/Kuala_Lumpur`) is applied to both PHP and the MySQL session, so
"today", registration deadlines and timestamps agree everywhere.

## Email

`MAIL_DRIVER=log` (development) saves every email to `storage/mail/` instead of sending it.
With `APP_DEBUG=true` the password-reset screen also shows the link. For a real server set:

```
MAIL_DRIVER=smtp
SMTP_HOST=smtp.gmail.com      # or your provider
SMTP_PORT=587
SMTP_ENCRYPTION=tls           # tls = STARTTLS on 587, ssl = port 465
SMTP_USERNAME=you@example.com
SMTP_PASSWORD=app-password
MAIL_FROM=no-reply@example.com
```

## Tickets and check-in

Approved participants get a ticket with a QR code and a code such as `ME-0044-00012-7F3A9C`.
The last part is an HMAC signature made with `APP_KEY`, so codes cannot be guessed or edited.
The QR opens `users/checkin.php`; the organiser (or an admin) confirms the check-in there.
Scanning with any phone camera works. The phone must be able to reach `APP_URL`, so for
testing on a phone over Wi-Fi set `APP_URL` to the computer's LAN address, e.g. `http://192.168.1.20/myevent`.

## Tests

Three layers, all run by CI (`.github/workflows/ci.yml`) on every push and pull request:

| Layer | What it covers | Needs |
|---|---|---|
| **Lint** `php tools/lint.php` | Every PHP file parses | PHP only |
| **PHPUnit** `vendor/bin/phpunit` | 106 tests: escaping, CSRF, signed tickets, calendar files, email building, waitlist promotion, mail queue retries, notification preferences, reminders, login throttling, audit log, report figures and charts | MySQL/MariaDB |
| **End-to-end** `bash tests/e2e.sh` | 77 HTTP checks against the running app: CSRF, uploads, access control, approvals, check-in, password reset, sessions, brute force | Apache + MySQL |

### First time

Install [Composer](https://getcomposer.org/download/), then in the project folder:
```
composer install
```
(XAMPP without Composer on PATH: `C:\xampp\php\php.exe -d extension=zip composer.phar install`.)
`vendor/` holds test tools only; the app itself does not need it, so a production server can skip this step.

### Running

```
bash tests/run.sh          # lint + PHPUnit + end-to-end, like CI
bash tests/run.sh --fast   # lint + PHPUnit only (no web server needed)
vendor/bin/phpunit --testsuite unit          # just the fast unit tests
vendor/bin/phpunit --filter Waitlist         # one area
```
On Windows prefix the PHP commands with `C:\xampp\php\php.exe` (for example `C:\xampp\php\php.exe vendor\bin\phpunit`).

**Your data is safe.** PHPUnit uses a separate database, `myevent_test`, which it drops and rebuilds from
`database/schema.sql` at the start of every run (it refuses any name that does not end in `_test`). The host
and credentials come from `.env`, or from `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD` in the environment.
Test emails go to a temporary folder, never to `storage/mail`.
The end-to-end suite runs against the real app and database: it creates throwaway `qa_*@example.test`
accounts and removes them afterwards. Override `BASE`, `MYSQL`, `MYSQL_OPTS`, `DB` or `PHP` if your setup differs.

### Writing a test

- Pure functions go in `tests/Unit` (extend `PHPUnit\Framework\TestCase`).
- Anything that reads or writes the database goes in `tests/Integration` and extends
  `MyEvent\Tests\Support\DatabaseTestCase`: every test starts with empty tables; create rows with
  `makeUser()`, `makeEvent()`, `register()` and `joinWaitlist()`.
- Assert on emails with `MyEvent\Tests\Support\MailSpy::messages()` / `recipients()`.
- New table or column? Update `database/schema.sql` (the tests build from it) as well as adding a migration.

## Project layout

| Path | Purpose |
|---|---|
| `config.php` | Bootstrap: `.env`, error handling, hardened session, DB connection, CSRF check for every POST |
| `include/security.php` | `e()`, CSRF, `require_login()` / `require_admin()`, safe image upload, login throttling |
| `include/helpers.php` | Media URLs, fee/date helpers, `datestub()`, `status_badge()`, pagination, `db_all()` / `db_value()` |
| `include/layout/` | `app_start` / `app_end` (signed-in pages with sidebar) and `public_start` / `public_end` (public pages) |
| `include/partials/` | Reusable fragments: event card, profile form |
| `include/event_editor.php` | Create/edit event screen shared by organisers and admins |
| `include/participants_manager.php` | Review, check-in and CSV export screen shared by organisers and admins |
| `assets/css/app.css` | Design system: tokens, layout shell and components |
| `assets/css/public.css` | Public pages: hero, event cards, event detail, sign-in |
| `assets/js/app.js` | Mobile menu, confirm dialogs, image preview, auto-submit filters |
| `auth/`, `users/`, `admin/` | Pages. Each page sets `$page_title` / `$nav_active` and includes a layout |
| `uploads/events`, `uploads/profiles` | User uploads (script execution blocked by `uploads/.htaccess`) |
| `database/` | `schema.sql`, `seed.sql`, `migrations/` |
| `include/mailer.php` | `send_mail()` with log and SMTP drivers |
| `admin/audit.php` | Audit log of deletions, role changes, approvals, check-ins and password resets |
| `tests/Unit`, `tests/Integration` | PHPUnit tests (`phpunit.xml`; helpers in `tests/Support`) |
| `tests/e2e.sh`, `tests/run.sh` | End-to-end suite; runner for every check |
| `.github/workflows/ci.yml` | CI: lint, PHPUnit on PHP 8.2 and 8.3, end-to-end under Apache |
| `tools/lint.php` | Syntax check of every PHP file |
| `include/reports.php`, `include/partials/report_view.php` | Report queries, HTML charts and the shared reports screen |
| `tools/check_production.php` | Pre-launch checklist (exit code 1 when something must be fixed) |
| `tools/backup.php` | Database + uploads backup with rotation (schedule daily) |
| `tools/send_mail.php` | Delivers queued emails and day-before reminders (cron every 5 minutes) |
| `tools/maintenance.php` | Daily housekeeping: expired tokens, log rotation, orphaned uploads report |
| `health.php` | Uptime-monitor endpoint: `ok` / 200 when PHP and the database respond |
| `storage/logs`, `storage/mail` | PHP error log and development mailbox (not web-accessible) |

### Adding a page

```php
<?php
require_once __DIR__ . '/../config.php';
require_login();                       // or require_admin()

$page_title = 'My page';
$nav_active = 'dashboard';             // a key from include/layout/app_start.php
include ROOT_PATH . '/include/layout/app_start.php';
?>
<div class="page-header"><div><h1>My page</h1></div></div>
<section class="card"><div class="card-body">...</div></section>
<?php include ROOT_PATH . '/include/layout/app_end.php'; ?>
```

Use the components in `app.css` (`card`, `table`, `btn`, `badge`, `field`, `empty-state`, `pagination`) instead of inline styles.

## Security conventions

- Every page includes `config.php` first. Every POST form must contain `<?= csrf_field() ?>`.
- State-changing actions use POST only. Escape all output with `e()`.
- Use prepared statements for every query that includes user input.
- Use `upload_image()` for uploads; never trust the original file name or extension.
- A Content-Security-Policy is sent on every page. Inline `<script>` blocks need `nonce="<?= e(CSP_NONCE) ?>"`,
  and inline event handlers (`onclick="..."`) are not allowed: use `assets/js/app.js` or a nonce'd script.
