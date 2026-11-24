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
    return $amount > 0 ? 'RM ' . number_format($amount, 2) : 'Free';
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
        return '<span class="datestub is-past" aria-label="No date"><b>–</b><span>TBA</span></span>';
    }
    $past = date('Y-m-d', $ts) < date('Y-m-d') ? ' is-past' : '';
    return '<span class="datestub' . $past . '" aria-label="' . e(date('j F Y', $ts)) . '"><b>'
        . date('j', $ts) . '</b><span>' . date('M', $ts) . '</span></span>';
}

/** Coloured status pill for approval, attendance, visibility and roles. */
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
    return '<span class="badge ' . ($map[$status] ?? '') . '">' . e($status) . '</span>';
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

/** Pagination bar ("Showing 11–20 of 42" + page links). */
function render_pagination(array $p, string $noun = 'results', string $param = 'page'): string
{
    if ($p['total'] === 0) {
        return '';
    }
    $from = $p['offset'] + 1;
    $to   = min($p['total'], $p['offset'] + $p['per_page']);
    $html = '<nav class="pagination" aria-label="Pagination"><span>Showing ' . $from . '–' . $to
          . ' of ' . $p['total'] . ' ' . e($noun) . '</span>';
    if ($p['pages'] > 1) {
        $html .= '<div class="pagination-links">';
        $prev = $p['page'] - 1;
        $html .= '<a class="page-link' . ($prev < 1 ? ' is-disabled' : '') . '" href="' . e(url_with([$param => $prev])) . '" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>';
        $start = max(1, $p['page'] - 2);
        $end   = min($p['pages'], $start + 4);
        $start = max(1, $end - 4);
        for ($i = $start; $i <= $end; $i++) {
            $current = $i === $p['page'];
            $html .= '<a class="page-link' . ($current ? ' is-current' : '') . '" href="' . e(url_with([$param => $i])) . '"'
                   . ($current ? ' aria-current="page"' : '') . '>' . $i . '</a>';
        }
        $next = $p['page'] + 1;
        $html .= '<a class="page-link' . ($next > $p['pages'] ? ' is-disabled' : '') . '" href="' . e(url_with([$param => $next])) . '" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>';
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
