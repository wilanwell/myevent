# Deploying MyEvent

This guide takes MyEvent from a laptop (XAMPP) to a real server. It covers the two most
common hosts: **shared hosting with cPanel** and a **VPS** (Ubuntu + Apache). The app works in
the web root or any sub-folder (`https://example.edu.my/` or `https://example.edu.my/events/`).

> Run `php tools/check_production.php` on the server at the end. It must say
> **"Ready for production"** (or only warnings you have consciously accepted) before you announce the site.

---

## 1. Requirements

| | Minimum |
|---|---|
| PHP | 8.1+ with `mysqli`, `mbstring`, `openssl`, `fileinfo`, `zlib` (and `phar` for upload backups) |
| Database | MariaDB 10.4+ or MySQL 8.0+ |
| Web server | Apache 2.4 with `.htaccess` enabled (`AllowOverride All`) and `mod_rewrite` |
| HTTPS | A certificate (free: cPanel AutoSSL or Let's Encrypt) |
| Email | An SMTP account (Gmail/Google Workspace app password, Outlook, Mailtrap, Amazon SES ...) |

Recommended `php.ini` (cPanel: *Select PHP Version → Options* / *MultiPHP INI Editor*):

```ini
display_errors = Off
log_errors = On
upload_max_filesize = 6M
post_max_size = 8M
session.cookie_httponly = 1
expose_php = Off
```

---

## 2. Upload the code

Upload everything **except** local data:

- do **not** upload `.env`, `storage/logs/*`, `storage/backups/*`, `storage/mail/*`, `public/uploads/events/*`, `public/uploads/profiles/*`
- `vendor/`, `tests/` and `.github/` are not needed on the server (they are never served anyway)
- **do** upload the `.htaccess` files (they are hidden on some systems; enable "show hidden files")

With Git on the server: `git clone https://github.com/wilanwell/myevent.git myevent` — `.gitignore` already excludes the local data above.

**cPanel:** use *File Manager* (upload a zip and *Extract*) or *Git Version Control*.
**VPS:** for example `/var/www/myevent`, owned by your deploy user, with the web server group able to read it.

### The web root is `public/`

Only `public/` should be reachable over the web. Choose one of these, best first:

1. **Point the (sub)domain at `public/`** (cleanest). *cPanel:* upload the project to `/home/cpaneluser/myevent`,
   outside `public_html`, then under *Domains* set the domain's or subdomain's **Document Root** to
   `/home/cpaneluser/myevent/public`. *VPS:* `DocumentRoot /var/www/myevent/public` (section 6).
2. **Main domain whose root must stay `public_html`**: upload the project to `/home/cpaneluser/myevent` and make
   `public_html` a link to its `public/` folder (cPanel *Terminal*:
   `mv ~/public_html ~/public_html.old && ln -s ~/myevent/public ~/public_html`). Ask the host if there is no terminal.
3. **Last resort, a sub-folder such as `public_html/events`**: upload the whole project there. The root `.htaccess`
   routes every request into `public/` and refuses everything else, so `.env` and the code stay unreachable as long
   as the host honours `.htaccess` (`php tools/check_production.php` tests this).

Set `APP_URL` to the address people type (for example `https://events.example.edu.my`), never with `/public` in it.

---

## 3. Database

1. Create an empty database (`utf8mb4_general_ci`).
   - cPanel: *MySQL Databases → Create New Database*.
   - VPS: `mysql -u root -p -e "CREATE DATABASE myevent CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"`
2. Import the structure **only** — do *not* import `seed.sql` on a live site (it creates demo accounts):
   - cPanel: *phpMyAdmin → Import → database/schema.sql*
   - VPS: `mysql -u root -p myevent < database/schema.sql`
3. Create a **least-privilege** account for the app (it can read and write rows, but not change or drop tables):
   - cPanel: *MySQL Databases → Add New User*, then *Add User To Database* and tick only
     **SELECT, INSERT, UPDATE, DELETE, LOCK TABLES, SHOW VIEW, TRIGGER**.
   - VPS: edit the password in `database/create_app_user.sql`, then `mysql -u root -p < database/create_app_user.sql`.

**Moving existing data from your laptop instead?** Export with `php tools/backup.php --db-only`, then import
that `.sql.gz` (phpMyAdmin accepts `.gz`) instead of `schema.sql`, and copy `public/uploads/events` and `public/uploads/profiles`
across. Remove or re-password any `@myevent.test` demo accounts afterwards.

---

## 4. Configure `.env`

Copy `.env.example` to `.env` in the app folder and fill it in:

```ini
APP_NAME=MyEvent
APP_URL=https://events.example.edu.my      # exact public address, no trailing slash
APP_DEBUG=false
APP_TIMEZONE=Asia/Kuala_Lumpur
APP_LOCALE=en                               # or ms: language for visitors whose browser asks for neither
APP_KEY=<php -r "echo bin2hex(random_bytes(32));">

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpaneluser_myevent              # cPanel prefixes names with your account
DB_USERNAME=cpaneluser_myeventapp
DB_PASSWORD=<the app user's password>

MAIL_DRIVER=smtp
MAIL_FROM=no-reply@events.example.edu.my
MAIL_FROM_NAME=MyEvent
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=you@example.edu.my
SMTP_PASSWORD=<app password>

MYSQLDUMP_PATH=mysqldump
BACKUP_KEEP=14
```

- **`APP_KEY` signs ticket QR codes.** Generate a new one for the server and never change it afterwards,
  or every ticket already issued stops scanning.
- Behind Cloudflare or a load balancer that terminates HTTPS, also set `APP_TRUST_PROXY=true`.
- Permissions: `.env` readable by PHP only (`chmod 640 .env` on a VPS; `600` on most cPanel hosts).

---

## 5. Folder permissions

PHP must be able to write to `storage/logs`, `storage/backups`, `storage/mail`, `public/uploads/events`, `public/uploads/profiles`.

- cPanel: usually already true (folders `755`, files `644`).
- VPS: `sudo chown -R deploy:www-data /var/www/myevent && sudo chmod -R 775 /var/www/myevent/storage /var/www/myevent/public/uploads`

Uploaded files can never run as code: `public/uploads/.htaccess` blocks scripts.

---

## 6. HTTPS

- cPanel: *SSL/TLS Status → Run AutoSSL* (free certificate, renews itself).
- VPS: `sudo apt install certbot python3-certbot-apache && sudo certbot --apache -d events.example.edu.my`

Once `APP_URL` starts with `https://`, MyEvent redirects plain HTTP to HTTPS, marks the session cookie
*Secure* and sends an HSTS header. A Content-Security-Policy is sent on every page.

### VPS: Apache virtual host

```apache
<VirtualHost *:443>
    ServerName events.example.edu.my
    DocumentRoot /var/www/myevent/public
    <Directory /var/www/myevent/public>
        AllowOverride All
        Require all granted
    </Directory>
    # SSLEngine/SSLCertificate* lines are added by certbot.
</VirtualHost>
```

Enable the useful modules: `sudo a2enmod rewrite headers deflate expires ssl && sudo systemctl reload apache2`.

---

## 7. First administrator

Register normally on the live site with your own email, then promote that account once:

```sql
UPDATE users SET role = 'Admin' WHERE email = 'you@example.edu.my';
```

(cPanel: phpMyAdmin → SQL.) Further admins can be promoted from **Admin → Users → Edit**.

---

## 8. Scheduled jobs

| Job | Command | When |
|---|---|---|
| **Email + reminders** | `php /path/to/myevent/tools/send_mail.php` | **every 5 minutes** |
| Backup (database + uploads) | `php /path/to/myevent/tools/backup.php` | daily, e.g. 02:00 |
| Housekeeping | `php /path/to/myevent/tools/maintenance.php` | daily, e.g. 03:00 |

- cPanel: *Cron Jobs* → e.g. `*/5 * * * * /usr/local/bin/php /home/cpaneluser/myevent/tools/send_mail.php >/dev/null 2>&1`
  and `0 2 * * * /usr/local/bin/php /home/cpaneluser/myevent/tools/backup.php >/dev/null 2>&1`
  (the PHP path is shown on that page).
- VPS: `crontab -e` with the same lines (use `which php` for the path).
- Windows server / XAMPP: *Task Scheduler* → action `C:\xampp\php\php.exe` with argument `C:\xampp\htdocs\myevent\tools\backup.php`.

**Public demo only** (`APP_DEMO=true`): restore the demo data every night, e.g.
`30 4 * * * /usr/local/bin/php /home/cpaneluser/myevent/tools/demo_reset.php --yes >/dev/null 2>&1`.
It erases every row and the uploads they used, so it refuses to run unless `APP_DEMO=true`. See *Public demo* below.

Backups stay on the same server, so also **copy them somewhere else** regularly
(download from `storage/backups` or sync with `rclone`). Test a restore now and then:

```bash
gzip -dc storage/backups/myevent_YYYYMMDD_HHMMSS.sql.gz | mysql -u root -p myevent_restore_test
```

---

### How email works

With `MAIL_DRIVER=smtp`, notification emails are written to the `mail_queue` table and delivered by
`tools/send_mail.php`, so pages never wait for the mail server and failed sends are retried
(after 5, 15, 45 and 135 minutes, then marked *failed*). Password-reset emails are sent immediately.
**Without the 5-minute cron job no notification emails go out**; `check_production.php` fails if
emails wait longer than 30 minutes. People choose which emails they receive in *My profile → Email notifications*.

---

## 9. Final check

```bash
php tools/check_production.php
```

Fix every **FAIL**. Then point an uptime monitor at `https://events.example.edu.my/health.php`
(returns `ok` with status 200 when PHP and the database respond).

---

## 10. Updating later

0. Make sure the new version passes its checks: CI is green, or `bash tests/run.sh` passes locally (see README, Tests).
1. `php tools/backup.php` (always back up first).
2. Upload the new code (or `git pull`). Keep your `.env` and `public/uploads/`.
3. Apply any new files in `database/migrations/` in name order, using an admin database account.
4. `php tools/check_production.php`.

**Rolling back:** restore the last backup (`gzip -dc ... | mysql ...`), extract the matching
`_uploads.tar.gz` into `public/uploads/`, and redeploy the previous code version.

---

## Public demo (portfolio / recruiters)

A showcase copy where anyone can click around without an account. Follow sections 1 to 6 as usual
(a free host with PHP 8.2+ and MySQL is enough), with these differences:

1. **Database:** import `database/schema.sql` only, then run `php tools/demo_reset.php --yes` once
   (it loads `seed.sql` and `demo.sql`). Without SSH, import `schema.sql`, `seed.sql` and `demo.sql`
   in that order in phpMyAdmin instead.
2. **`.env`:** `APP_DEMO=true`, `MAIL_DRIVER=log` (nothing is ever emailed), `APP_DEBUG=false`.
3. **Cron:** `demo_reset.php --yes` nightly (section 8); `send_mail.php` every 5 minutes still writes
   reminders to the in-app notifications. Without cron the demo simply never resets.
4. `php tools/check_production.php` reports `APP_DEMO is true` as a warning: expected here.

Visitors use **Try the demo** on the login page. Never turn demo mode on for a site with real users:
anyone could sign in as the admin.

---
## Troubleshooting

| Symptom | Likely cause |
|---|---|
| "Unexpected error" on every page | Wrong DB settings in `.env`; details are in `storage/logs/php-error.log` |
| CSS missing / 404 on every link | `APP_URL` does not match the real address (check http vs https and the sub-folder) |
| `.env` or `include/` reachable | The document root is not `public/` and `.htaccess` is ignored: point the root at `public/` (section 2) |
| Every page is 404, or URLs contain `/public/` | Document root and `APP_URL` disagree: `APP_URL` is the public address, without `/public` |
| Password-reset emails never arrive | `MAIL_DRIVER` still `log`, wrong SMTP password, or the provider blocks the From address |
| Times or deadlines an hour or more off | `APP_TIMEZONE` not set to your local zone |
| Old tickets no longer scan | `APP_KEY` was changed after tickets were issued |
