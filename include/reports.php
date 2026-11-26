<?php
/**
 * MyEvent - Reports and analytics (shared by the admin and organiser report pages).
 *
 * Every query takes a filter from report_filter():
 *   from        events on or after this date (Y-m-d) or null
 *   to          events on or before this date (Y-m-d) or null
 *   organiser   only events created by this user, or null for all events
 *
 * Definitions used throughout:
 *   registrations   Pending + Approved (rejected ones never held a place)
 *   attendance      Present / Approved, counted only for events that have started (event_date <= today)
 *   fill rate       registrations / capacity, for events with a capacity
 */

const REPORT_PRESETS = [
    '30d' => ['Last 30 days', '-30 days'],
    '90d' => ['Last 90 days', '-90 days'],
    '12m' => ['Last 12 months', '-12 months'],
    'all' => ['All time', null],
];

/**
 * Read the date filter from the query string.
 * Presets count back from today and include every later (upcoming) event.
 */
function report_filter(array $query, ?int $organiser = null): array
{
    $preset = (string) ($query['range'] ?? '12m');
    $from = $to = null;

    if ($preset === 'custom') {
        $from = valid_date($query['from'] ?? null) ? $query['from'] : null;
        $to   = valid_date($query['to'] ?? null) ? $query['to'] : null;
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        if (!$from && !$to) {
            $preset = 'all';
        }
    } elseif (isset(REPORT_PRESETS[$preset])) {
        $offset = REPORT_PRESETS[$preset][1];
        $from = $offset ? date('Y-m-d', strtotime($offset)) : null;
    } else {
        $preset = '12m';
        $from = date('Y-m-d', strtotime('-12 months'));
    }

    if ($preset === 'custom') {
        $label = match (true) {
            $from && $to => 'Events from ' . date('j M Y', strtotime($from)) . ' to ' . date('j M Y', strtotime($to)),
            (bool) $from => 'Events from ' . date('j M Y', strtotime($from)),
            default      => 'Events up to ' . date('j M Y', strtotime($to)),
        };
    } elseif ($from) {
        $label = 'Events since ' . date('j M Y', strtotime($from)) . ', including upcoming ones';
    } else {
        $label = 'All events';
    }

    return ['preset' => $preset, 'from' => $from, 'to' => $to, 'organiser' => $organiser, 'label' => $label];
}

/** WHERE clause for the events table (alias e) plus its bind types and values. */
function report_where(array $f): array
{
    $sql = ['1 = 1'];
    $types = '';
    $params = [];
    if (!empty($f['from'])) {
        $sql[] = 'e.event_date >= ?';
        $types .= 's';
        $params[] = $f['from'];
    }
    if (!empty($f['to'])) {
        $sql[] = 'e.event_date <= ?';
        $types .= 's';
        $params[] = $f['to'];
    }
    if (!empty($f['organiser'])) {
        $sql[] = 'e.created_by = ?';
        $types .= 'i';
        $params[] = (int) $f['organiser'];
    }
    return [implode(' AND ', $sql), $types, $params];
}

/** Safe ratio as a percentage (0-100), or null when there is nothing to divide by. */
function report_rate(int $part, int $whole): ?float
{
    return $whole > 0 ? round($part / $whole * 100, 1) : null;
}

/** Headline numbers for the KPI tiles. */
function report_summary(mysqli $conn, array $f): array
{
    [$where, $types, $params] = report_where($f);

    $row = db_all($conn,
        "SELECT COUNT(DISTINCT e.event_id) AS events,
                COUNT(DISTINCT CASE WHEN e.event_date >= CURDATE() THEN e.event_id END) AS upcoming,
                COUNT(r.reg_id) AS submitted,
                SUM(r.approval_status IN ('Pending', 'Approved')) AS registrations,
                SUM(r.approval_status = 'Approved') AS approved,
                SUM(r.approval_status = 'Pending') AS pending,
                SUM(r.approval_status = 'Rejected') AS rejected,
                SUM(r.approval_status = 'Approved' AND e.event_date <= CURDATE()) AS approved_held,
                SUM(r.approval_status = 'Approved' AND e.event_date <= CURDATE() AND r.attendance_status = 'Present') AS attended,
                COUNT(DISTINCT CASE WHEN r.approval_status <> 'Rejected' THEN r.user_id END) AS people
         FROM events e LEFT JOIN registrations r ON r.event_id = e.event_id
         WHERE $where", $types, $params)[0];

    $rating = db_all($conn,
        "SELECT AVG(f.rating) AS avg, COUNT(*) AS count FROM feedback f JOIN events e ON e.event_id = f.event_id WHERE $where",
        $types, $params)[0];
    $waiting = (int) db_value($conn,
        "SELECT COUNT(*) FROM waitlist w JOIN events e ON e.event_id = w.event_id WHERE $where AND e.event_date >= CURDATE()",
        $types, $params);

    $n = static fn($v) => (int) ($v ?? 0);
    return [
        'events'          => $n($row['events']),
        'upcoming'        => $n($row['upcoming']),
        'registrations'   => $n($row['registrations']),
        'approved'        => $n($row['approved']),
        'pending'         => $n($row['pending']),
        'rejected'        => $n($row['rejected']),
        'people'          => $n($row['people']),
        'approved_held'   => $n($row['approved_held']),
        'attended'        => $n($row['attended']),
        'attendance_rate' => report_rate($n($row['attended']), $n($row['approved_held'])),
        'approval_rate'   => report_rate($n($row['approved']), $n($row['approved']) + $n($row['rejected'])),
        'rating_avg'      => $rating['count'] ? round((float) $rating['avg'], 2) : null,
        'rating_count'    => (int) $rating['count'],
        'waiting'         => $waiting,
    ];
}

/**
 * Registrations per month (by the day people registered), with empty months filled in.
 * Shows at most the last $maxMonths months that have data.
 * @return list<array{month:string, label:string, count:int}>
 */
function report_registrations_by_month(mysqli $conn, array $f, int $maxMonths = 24): array
{
    [$where, $types, $params] = report_where($f);
    $rows = db_all($conn,
        "SELECT DATE_FORMAT(r.registration_date, '%Y-%m') AS month, COUNT(*) AS count
         FROM registrations r JOIN events e ON e.event_id = r.event_id
         WHERE $where AND r.approval_status <> 'Rejected'
         GROUP BY month ORDER BY month", $types, $params);
    if (!$rows) {
        return [];
    }

    $counts = array_column($rows, 'count', 'month');
    $last = new DateTimeImmutable(array_key_last($counts) . '-01');
    $first = new DateTimeImmutable(array_key_first($counts) . '-01');
    $earliest = $last->modify('-' . ($maxMonths - 1) . ' months');
    if ($first < $earliest) {
        $first = $earliest;
    }

    $out = [];
    for ($m = $first; $m <= $last; $m = $m->modify('+1 month')) {
        $key = $m->format('Y-m');
        $out[] = ['month' => $key, 'label' => $m->format('M Y'), 'count' => (int) ($counts[$key] ?? 0)];
    }
    return $out;
}

/** Registrations by participant category (Student / Staff / Public), largest first. */
function report_by_category(mysqli $conn, array $f): array
{
    [$where, $types, $params] = report_where($f);
    return array_map(static fn($r) => ['label' => $r['category'], 'count' => (int) $r['count']], db_all($conn,
        "SELECT u.category, COUNT(*) AS count
         FROM registrations r JOIN events e ON e.event_id = r.event_id JOIN users u ON u.user_id = r.user_id
         WHERE $where AND r.approval_status <> 'Rejected'
         GROUP BY u.category ORDER BY count DESC, u.category", $types, $params));
}

/** How many ratings of each star value: [5 => n, 4 => n, ... 1 => n]. */
function report_rating_distribution(mysqli $conn, array $f): array
{
    [$where, $types, $params] = report_where($f);
    $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach (db_all($conn,
        "SELECT f.rating, COUNT(*) AS count FROM feedback f JOIN events e ON e.event_id = f.event_id
         WHERE $where GROUP BY f.rating", $types, $params) as $r) {
        if (isset($dist[(int) $r['rating']])) {
            $dist[(int) $r['rating']] = (int) $r['count'];
        }
    }
    return $dist;
}

/**
 * One row per event with its numbers, most registrations first.
 * Rates are null when they do not apply (no capacity, not held yet, nobody approved).
 */
function report_events(mysqli $conn, array $f, ?int $limit = null): array
{
    [$where, $types, $params] = report_where($f);
    $sql = "SELECT e.event_id, e.event_name, e.event_date, e.max_participants, MAX(u.name) AS organiser,
                   SUM(r.approval_status IN ('Pending', 'Approved')) AS registrations,
                   SUM(r.approval_status = 'Approved') AS approved,
                   SUM(r.approval_status = 'Approved' AND r.attendance_status = 'Present') AS attended,
                   (SELECT COUNT(*) FROM waitlist w WHERE w.event_id = e.event_id) AS waiting,
                   (SELECT AVG(fb.rating) FROM feedback fb WHERE fb.event_id = e.event_id) AS rating_avg,
                   (SELECT COUNT(*) FROM feedback fb WHERE fb.event_id = e.event_id) AS rating_count
            FROM events e
            LEFT JOIN registrations r ON r.event_id = e.event_id
            LEFT JOIN users u ON u.user_id = e.created_by
            WHERE $where
            GROUP BY e.event_id
            ORDER BY registrations DESC, e.event_date DESC, e.event_id DESC";
    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, $limit);
    }

    $today = date('Y-m-d');
    return array_map(static function (array $r) use ($today) {
        $regs = (int) $r['registrations'];
        $approved = (int) $r['approved'];
        $attended = (int) $r['attended'];
        $capacity = (int) $r['max_participants'];
        $held = $r['event_date'] <= $today;
        return [
            'event_id'        => (int) $r['event_id'],
            'event_name'      => $r['event_name'],
            'event_date'      => $r['event_date'],
            'organiser'       => $r['organiser'],
            'capacity'        => $capacity,
            'registrations'   => $regs,
            'approved'        => $approved,
            'attended'        => $attended,
            'waiting'         => (int) $r['waiting'],
            'held'            => $held,
            'fill_rate'       => $capacity > 0 ? report_rate($regs, $capacity) : null,
            'attendance_rate' => $held ? report_rate($attended, $approved) : null,
            'rating_avg'      => $r['rating_count'] ? round((float) $r['rating_avg'], 2) : null,
            'rating_count'    => (int) $r['rating_count'],
        ];
    }, db_all($conn, $sql, $types, $params));
}

/** Send the per-event report as a CSV download and stop. */
function report_export_csv(array $events, array $f, string $name): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . safe_filename($name . '_' . date('Y-m-d')) . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly
    fputcsv($out, csv_row([$f['label']]));
    fputcsv($out, ['Event', 'Date', 'Organiser', 'Capacity', 'Registrations', 'Approved', 'Attended',
                   'Fill rate %', 'Attendance rate %', 'Waitlist', 'Average rating', 'Ratings']);
    foreach ($events as $ev) {
        fputcsv($out, csv_row([
            $ev['event_name'], $ev['event_date'], $ev['organiser'] ?? '', $ev['capacity'] ?: 'Unlimited',
            $ev['registrations'], $ev['approved'], $ev['held'] ? $ev['attended'] : '',
            $ev['fill_rate'] ?? '', $ev['attendance_rate'] ?? '', $ev['waiting'],
            $ev['rating_avg'] ?? '', $ev['rating_count'],
        ]));
    }
    fclose($out);
    exit;
}

/** Percentage for display: "82%" or an en dash when not applicable. */
function format_rate(?float $rate): string
{
    return $rate === null ? '–' : rtrim(rtrim(number_format($rate, 1), '0'), '.') . '%';
}

/* ---------------------------------------------------------------------
   Charts (plain HTML + CSS, no JavaScript library; hover text via data-tip)
   --------------------------------------------------------------------- */

/** A round axis maximum above $max (1, 2, 5 x 10^n steps, at most 5 gridlines). */
function report_axis(int $max): array
{
    if ($max <= 0) {
        return ['max' => 1, 'ticks' => [0, 1]];
    }
    $raw = $max / 4;
    $magnitude = 10 ** floor(log10($raw));
    $step = 1;
    foreach ([1, 2, 5, 10] as $m) {
        if ($m * $magnitude >= $raw) {
            $step = (int) max(1, $m * $magnitude);
            break;
        }
    }
    $top = (int) (ceil($max / $step) * $step);
    if ($top === $max) {
        $top += $step; // headroom for the value label above the tallest column
    }
    return ['max' => $top, 'ticks' => range(0, $top, $step)];
}

/**
 * Column chart for a time series: [['label' => 'Jan 2026', 'count' => 4], ...].
 * The highest and the latest columns carry their value as a direct label.
 */
function render_column_chart(array $points, string $noun, string $caption): string
{
    if (!$points) {
        return '<p class="viz-empty">No ' . e($noun) . ' in this period.</p>';
    }
    $counts = array_column($points, 'count');
    $axis = report_axis(max($counts));
    $peak = array_search(max($counts), $counts, true);
    $last = count($points) - 1;
    $every = (int) max(1, ceil(count($points) / 12)); // keep x labels from colliding

    $grid = '';
    foreach ($axis['ticks'] as $t) {
        $grid .= '<div class="viz-gridline" style="bottom:' . round($t / $axis['max'] * 100, 3) . '%"><span>' . $t . '</span></div>';
    }
    $cols = '';
    $labels = '';
    foreach ($points as $i => $p) {
        $h = round($p['count'] / $axis['max'] * 100, 3);
        $tip = $p['label'] . ': ' . $p['count'] . ' ' . $noun;
        $value = ($i === $peak || $i === $last) && $p['count'] > 0 ? '<b class="viz-value">' . $p['count'] . '</b>' : '';
        $cols .= '<div class="viz-col" tabindex="0" data-tip="' . e($tip) . '" aria-label="' . e($tip) . '">'
               . '<span class="viz-bar" style="height:' . $h . '%">' . $value . '</span></div>';
        $short = substr($p['label'], 0, 3) . (str_starts_with($p['label'], 'Jan') || $i === 0 ? ' ’' . substr($p['label'], -2) : '');
        $labels .= '<span' . ($i % $every ? ' class="is-skipped"' : '') . '>' . e($short) . '</span>';
    }

    $summary = $caption . ': ' . count($points) . ' months from ' . $points[0]['label'] . ' to ' . $points[$last]['label']
             . ', highest ' . $points[$peak]['count'] . ' in ' . $points[$peak]['label'] . '.';
    return '<figure class="viz viz-columns" aria-label="' . e($summary) . '">'
         . '<div class="viz-plot">' . $grid . '<div class="viz-cols">' . $cols . '</div></div>'
         . '<div class="viz-xlabels" aria-hidden="true">' . $labels . '</div>'
         . '<figcaption class="sr-only">' . e($summary) . '</figcaption></figure>';
}

/**
 * Horizontal bars with the label and value written out: [['label' => 'Student', 'count' => 12], ...].
 * Shares show each bar's part of the total.
 */
function render_bar_list(array $items, string $noun): string
{
    $total = array_sum(array_column($items, 'count'));
    if ($total === 0) {
        return '<p class="viz-empty">No ' . e($noun) . ' yet.</p>';
    }
    $max = max(array_column($items, 'count'));
    $html = '<ul class="viz viz-bars">';
    foreach ($items as $item) {
        $share = round($item['count'] / $total * 100);
        $tip = $item['label'] . ': ' . $item['count'] . ' ' . $noun . ' (' . $share . '%)';
        $html .= '<li tabindex="0" data-tip="' . e($tip) . '">'
               . '<span class="viz-bars-label">' . ($item['html'] ?? e($item['label'])) . '</span>'
               . '<span class="viz-bars-track"><span class="viz-bar" style="width:' . round($item['count'] / $max * 100, 3) . '%"></span></span>'
               . '<span class="viz-bars-value">' . $item['count'] . ' <small>' . $share . '%</small></span></li>';
    }
    return $html . '</ul>';
}

/** Small inline meter for a rate in a table cell. */
function render_rate_meter(?float $rate): string
{
    if ($rate === null) {
        return '<span class="cell-muted">–</span>';
    }
    return '<span class="meter" title="' . e(format_rate($rate)) . '"><span class="meter-track"><span class="meter-fill" style="width:'
         . min(100, $rate) . '%"></span></span><span class="meter-value">' . e(format_rate($rate)) . '</span></span>';
}
