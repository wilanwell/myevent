<?php
/**
 * MyEvent - Application helpers (media URLs, formatting).
 * Loaded by config.php after the security helpers.
 */

/** Upload folders, relative to ROOT_PATH. All user uploads live under uploads/. */
const POSTER_DIR = 'uploads/events';
const AVATAR_DIR = 'uploads/profiles';

/**
 * Public URL for an event poster, with a placeholder when the file is missing.
 * Bundled demo posters in assets/img/ are also accepted.
 */
function poster_url(?string $file): string
{
    if ($file && basename($file) === $file) {
        foreach ([POSTER_DIR, 'assets/img'] as $dir) {
            if (is_file(ROOT_PATH . '/' . $dir . '/' . $file)) {
                return BASE_URL . $dir . '/' . rawurlencode($file);
            }
        }
    }
    return BASE_URL . 'assets/img/event-placeholder.svg';
}

/** Public URL for a profile picture, or null when the user has none. */
function avatar_url(?string $file): ?string
{
    if ($file && basename($file) === $file && is_file(ROOT_PATH . '/' . AVATAR_DIR . '/' . $file)) {
        return BASE_URL . AVATAR_DIR . '/' . rawurlencode($file);
    }
    return null;
}

/** Format a fee for display: "Free" or "RM 10.00". */
function format_fee($fee): string
{
    $amount = (float) $fee;
    return $amount > 0 ? 'RM ' . number_format($amount, 2) : __('Free');
}

/** Parse a fee typed by a user ("Free", "RM10", "10.50") into a non-negative number. */
function parse_fee($input): float
{
    $clean = preg_replace('/[^0-9.]/', '', (string) $input);
    return max(0, round((float) $clean, 2));
}

/** Validate a Y-m-d date string. */
function valid_date(?string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $date);
    return $d && $d->format('Y-m-d') === $date;
}

/** Validate an H:i or H:i:s time string. */
function valid_time(?string $time): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $time);
}

/* ------------------------------------------------------------------ */
/* UI helpers                                                          */
/* ------------------------------------------------------------------ */

/** Ticket-stub date block (day + month). */
function datestub(?string $date): string
{
    if (!$date || !($ts = strtotime($date))) {
        return '<span class="datestub is-past" aria-label="' . e(__('No date')) . '"><b>–</b><span>' . e(__('TBA')) . '</span></span>';
    }
    $past = date('Y-m-d', $ts) < date('Y-m-d') ? ' is-past' : '';
    return '<span class="datestub' . $past . '" aria-label="' . e(ldate('j F Y', $ts)) . '"><b>'
        . date('j', $ts) . '</b><span>' . e(ldate('M', $ts)) . '</span></span>';
}

/** Coloured status pill for approval, attendance, visibility and roles (the label is translated). */
function status_badge(?string $status): string
{
    $map = [
        'Approved' => 'badge-success', 'Present' => 'badge-success', 'Public' => 'badge-info',
        'Pending'  => 'badge-warning', 'Private' => 'badge-warning',
        'Rejected' => 'badge-danger',
        'Absent'   => '',
        'Admin'    => 'badge-brand', 'User' => '',
    ];
    $status = $status ?: 'Unknown';
    return '<span class="badge ' . ($map[$status] ?? '') . '">' . e(status_label($status)) . '</span>';
}

/** Display name of a stored status, role, mode or category value. */
function status_label(string $value): string
{
    $labels = [
        'Approved' => __('Approved'), 'Pending' => __('Pending'), 'Rejected' => __('Rejected'),
        'Present' => __('Present'), 'Absent' => __('Absent'),
        'Public' => __('Public'), 'Private' => __('Private'),
        'Admin' => __('Admin'), 'User' => __('User'),
        'Student' => __('Student'), 'Staff' => __('Staff'),
        'Physical' => __('Physical'), 'Online' => __('Online'), 'Hybrid' => __('Hybrid'),
        'Unknown' => __('Unknown'),
    ];
    return $labels[$value] ?? $value;
}

/** Initials for an avatar placeholder. */
function initials(?string $name): string
{
    $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
    $letters = '';
    foreach (array_slice(array_filter($parts), 0, 2) as $p) {
        $letters .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $letters ?: '?';
}

/* ------------------------------------------------------------------ */
/* Pagination                                                          */
/* ------------------------------------------------------------------ */

/**
 * Work out paging for a result set.
 * @return array{page:int, per_page:int, total:int, pages:int, offset:int}
 */
function paginate(int $total, int $perPage = 10, string $param = 'page'): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min($pages, max(1, (int) ($_GET[$param] ?? 1)));
    return ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

/** Current URL with some query parameters replaced (null removes them). */
function url_with(array $changes): string
{
    $query = array_merge($_GET, $changes);
    $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
    $path  = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    return $path . ($query ? '?' . http_build_query($query) : '');
}

/**
 * Pagination bar ("Showing 11–20 of 42 events" + page links).
 * $noun is one of: results, events, entries, users, participants, registrations, notifications.
 */
function render_pagination(array $p, string $noun = 'results', string $param = 'page'): string
{
    if ($p['total'] === 0) {
        return '';
    }
    $nouns = [
        'results' => __('Showing {from}–{to} of {total} results'),
        'events' => __('Showing {from}–{to} of {total} events'),
        'entries' => __('Showing {from}–{to} of {total} entries'),
        'users' => __('Showing {from}–{to} of {total} users'),
        'participants' => __('Showing {from}–{to} of {total} participants'),
        'registrations' => __('Showing {from}–{to} of {total} registrations'),
        'notifications' => __('Showing {from}–{to} of {total} notifications'),
    ];
    $from = $p['offset'] + 1;
    $to   = min($p['total'], $p['offset'] + $p['per_page']);
    $html = '<nav class="pagination" aria-label="' . e(__('Pagination')) . '"><span>'
          . e(strtr($nouns[$noun] ?? $nouns['results'], ['{from}' => $from, '{to}' => $to, '{total}' => $p['total']])) . '</span>';
    if ($p['pages'] > 1) {
        $html .= '<div class="pagination-links">';
        $prev = $p['page'] - 1;
        $html .= '<a class="page-link' . ($prev < 1 ? ' is-disabled' : '') . '" href="' . e(url_with([$param => $prev])) . '" aria-label="' . e(__('Previous page')) . '"><i class="fa-solid fa-chevron-left"></i></a>';
        $start = max(1, $p['page'] - 2);
        $end   = min($p['pages'], $start + 4);
        $start = max(1, $end - 4);
        for ($i = $start; $i <= $end; $i++) {
            $current = $i === $p['page'];
            $html .= '<a class="page-link' . ($current ? ' is-current' : '') . '" href="' . e(url_with([$param => $i])) . '"'
                   . ($current ? ' aria-current="page"' : '') . '>' . $i . '</a>';
        }
        $next = $p['page'] + 1;
        $html .= '<a class="page-link' . ($next > $p['pages'] ? ' is-disabled' : '') . '" href="' . e(url_with([$param => $next])) . '" aria-label="' . e(__('Next page')) . '"><i class="fa-solid fa-chevron-right"></i></a>';
        $html .= '</div>';
    }
    return $html . '</nav>';
}

/** Run a prepared statement and return all rows. */
function db_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Run a prepared statement and return the first column of the first row. */
function db_value(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $row[0] ?? null;
}

/* ------------------------------------------------------------------ */
/* Audit trail                                                         */
/* ------------------------------------------------------------------ */

/**
 * Record a sensitive action (deletions, role changes, approvals, check-ins, password resets).
 * Never throws: auditing must not break the action itself.
 */
function audit(mysqli $conn, string $action, ?string $targetType = null, ?int $targetId = null, string $details = ''): void
{
    try {
        $actor = current_user_id();
        $name  = $_SESSION['name'] ?? null;
        $ip    = client_ip();
        $details = mb_substr($details, 0, 500);
        $stmt = $conn->prepare('INSERT INTO audit_log (actor_id, actor_name, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssiss', $actor, $name, $action, $targetType, $targetId, $details, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $ex) {
        error_log('[audit] ' . $ex->getMessage());
    }
}

/* ------------------------------------------------------------------ */
/* Signed tickets                                                      */
/* ------------------------------------------------------------------ */

/** Secret for signing tickets. */
function app_key(): string
{
    $key = (string) env('APP_KEY', '');
    if (strlen($key) < 32) {
        throw new RuntimeException('APP_KEY is missing or too short. Set it in .env (see .env.example).');
    }
    return $key;
}

/** Human-readable ticket code, e.g. ME-0044-00012-7F3A9C. The suffix is an HMAC so codes cannot be guessed. */
function ticket_code(int $eventId, int $regId): string
{
    $sig = strtoupper(substr(hash_hmac('sha256', "ticket:$eventId:$regId", app_key()), 0, 6));
    return sprintf('ME-%04d-%05d-%s', $eventId, $regId, $sig);
}

/**
 * Parse and verify a ticket code (case/spacing tolerant).
 * @return array{event_id:int, reg_id:int}|null
 */
function verify_ticket_code(string $code): ?array
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $code));
    if (!preg_match('/^ME-(\d{1,8})-(\d{1,10})-([0-9A-F]{6})$/', $code, $m)) {
        return null;
    }
    $expected = ticket_code((int) $m[1], (int) $m[2]);
    return hash_equals($expected, sprintf('ME-%04d-%05d-%s', (int) $m[1], (int) $m[2], $m[3]))
        ? ['event_id' => (int) $m[1], 'reg_id' => (int) $m[2]]
        : null;
}

/** URL encoded in the ticket QR: opening it lets the organiser check the participant in. */
function ticket_checkin_url(string $code): string
{
    return BASE_URL . 'users/checkin.php?code=' . rawurlencode($code);
}

/* ------------------------------------------------------------------ */
/* Ratings                                                             */
/* ------------------------------------------------------------------ */

/** Five-star display for a value from 0 to 5 (rounded to the nearest half). */
function rating_stars(float $value): string
{
    $half = round($value * 2) / 2;
    $html = '<span class="stars" role="img" aria-label="' . e(__('{n} out of 5 stars', ['n' => number_format($value, 1)])) . '">';
    for ($i = 1; $i <= 5; $i++) {
        if ($half >= $i) {
            $html .= '<i class="fa-solid fa-star" aria-hidden="true"></i>';
        } elseif ($half >= $i - 0.5) {
            $html .= '<i class="fa-solid fa-star-half-stroke" aria-hidden="true"></i>';
        } else {
            $html .= '<i class="fa-regular fa-star is-empty" aria-hidden="true"></i>';
        }
    }
    return $html . '</span>';
}

/** Average rating and count for an event: ['avg' => float, 'count' => int]. */
function event_rating(mysqli $conn, int $eventId): array
{
    $row = db_all($conn, 'SELECT AVG(rating) AS avg, COUNT(*) AS count FROM feedback WHERE event_id = ?', 'i', [$eventId])[0];
    return ['avg' => (float) $row['avg'], 'count' => (int) $row['count']];
}

/* ------------------------------------------------------------------ */
/* Calendar export                                                     */
/* ------------------------------------------------------------------ */

const DEFAULT_EVENT_HOURS = 2;

/** Start and end as DateTimeImmutable in the app time zone (end defaults to start + 2 hours). */
function event_period(array $event): array
{
    $tz = new DateTimeZone(date_default_timezone_get());
    $start = new DateTimeImmutable($event['event_date'] . ' ' . $event['event_time'], $tz);
    $end = !empty($event['end_time'])
        ? new DateTimeImmutable($event['event_date'] . ' ' . $event['end_time'], $tz)
        : $start->modify('+' . DEFAULT_EVENT_HOURS . ' hours');
    if ($end <= $start) {
        $end = $start->modify('+' . DEFAULT_EVENT_HOURS . ' hours');
    }
    return [$start, $end];
}

/** Link that opens Google Calendar with the event pre-filled. */
function google_calendar_url(array $event): string
{
    [$start, $end] = event_period($event);
    $utc = new DateTimeZone('UTC');
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action'   => 'TEMPLATE',
        'text'     => $event['event_name'],
        'dates'    => $start->setTimezone($utc)->format('Ymd\THis\Z') . '/' . $end->setTimezone($utc)->format('Ymd\THis\Z'),
        'details'  => trim(mb_substr((string) ($event['description'] ?? ''), 0, 800) . "\n\n" . BASE_URL . 'event_detail.php?id=' . (int) $event['event_id']),
        'location' => $event['venue'] ?? '',
    ]);
}

/** Escape text for an iCalendar property value (RFC 5545 3.3.11). */
function ics_escape(string $text): string
{
    // Order matters: escape backslashes first, then separators, then line breaks.
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace([';', ','], ['\\;', '\\,'], $text);
    return str_replace(["\r\n", "\n", "\r"], ['\\n', '\\n', ''], $text);
}

/** Fold long lines to 75 octets as RFC 5545 requires. */
function ics_fold(string $line): string
{
    $out = '';
    $limit = 75; // continuation lines start with a space, which counts towards the 75
    while (strlen($line) > $limit) {
        $cut = $limit;
        // do not split a multi-byte UTF-8 character
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
            $cut--;
        }
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
        $limit = 74;
    }
    return $out . $line;
}

/** A complete .ics document for one event. */
function event_ics(array $event): string
{
    [$start, $end] = event_period($event);
    $utc = new DateTimeZone('UTC');
    $host = parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost';
    $url = BASE_URL . 'event_detail.php?id=' . (int) $event['event_id'];
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//' . APP_NAME . '//Campus events//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:event-' . (int) $event['event_id'] . '@' . $host,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z'),
        'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z'),
        'SUMMARY:' . ics_escape($event['event_name']),
        'LOCATION:' . ics_escape((string) ($event['venue'] ?? '')),
        'DESCRIPTION:' . ics_escape(trim((string) ($event['description'] ?? '') . "\n\n" . $url)),
        'URL:' . $url,
        'BEGIN:VALARM',
        'ACTION:DISPLAY',
        'DESCRIPTION:' . ics_escape($event['event_name']),
        'TRIGGER:-PT1H',
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    ];
    return implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
}
