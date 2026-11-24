# MyEvent

Campus event management system: event announcement, registration, organiser
approval, attendance tracking, notifications and participant exports.

**Stack:** PHP 8.1+ (mysqli), MariaDB 10.4+ / MySQL 8.0+, Apache 2.4 (`mod_rewrite`/`.htaccess` enabled).

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

Demo accounts created by `seed.sql` are listed at the top of that file.
Delete or change them before going live.

## Upgrading an existing database

Apply the files in `database/migrations/` that have not been run yet, in filename order:
```
mysql -u root myevent < database/migrations/<file>.sql
```
`database/schema.sql` always reflects the schema after the latest migration.
Back up first (`mysqldump -u root myevent > backup.sql`).

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
| `storage/logs` | PHP error log (not web-accessible) |

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
