<?php
/**
 * Reports screen shared by admin/reports.php and users/reports.php.
 *
 * Expects:
 *   $rf            filter from report_filter()
 *   $rp_events_url base URL of the participants page, the event id is appended
 *   $rp_intro      sentence under the heading
 *   $rp_organisers [user_id => name] to offer an organiser filter (admin only), or []
 */
$rp_summary    = report_summary($conn, $rf);
$rp_months     = report_registrations_by_month($conn, $rf);
$rp_categories = report_by_category($conn, $rf);
$rp_ratings    = report_rating_distribution($conn, $rf);
$rp_events     = report_events($conn, $rf);
$rp_shown      = array_slice($rp_events, 0, 50);

$rp_rating_items = [];
foreach ($rp_ratings as $stars => $count) {
    $rp_rating_items[] = ['label' => _n('{n} star', '{n} stars', $stars), 'count' => $count,
                          'html' => $stars . ' <i class="fa-solid fa-star" aria-hidden="true"></i><span class="sr-only"> ' . t('stars') . '</span>'];
}
$rp_query = array_filter(['range' => $rf['preset'], 'from' => $rf['preset'] === 'custom' ? $rf['from'] : null,
                          'to' => $rf['preset'] === 'custom' ? $rf['to'] : null, 'organiser' => $_GET['organiser'] ?? null]);
?>

<div class="page-header">
    <div>
        <h1><?= t('Reports') ?></h1>
        <p><?= e($rp_intro) ?> <span class="report-range"><?= e($rf['label']) ?>.</span></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="?<?= e(http_build_query($rp_query + ['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> <?= t('Export CSV') ?></a>
        <button type="button" class="btn btn-secondary" data-print><i class="fa-solid fa-print"></i> <?= t('Print or save PDF') ?></button>
    </div>
</div>

<form class="card report-filters" method="GET" aria-label="<?= t('Report filters') ?>">
    <div class="field">
        <label for="rp-range"><?= t('Period') ?></label>
        <select id="rp-range" name="range" data-autosubmit>
            <?php foreach (report_preset_labels() as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= $rf['preset'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
            <option value="custom"<?= $rf['preset'] === 'custom' ? ' selected' : '' ?>><?= t('Custom dates') ?></option>
        </select>
    </div>
    <div class="field">
        <label for="rp-from"><?= t('From') ?></label>
        <input id="rp-from" type="date" name="from" value="<?= e($rf['preset'] === 'custom' ? $rf['from'] : '') ?>" data-custom-range>
    </div>
    <div class="field">
        <label for="rp-to"><?= t('To') ?></label>
        <input id="rp-to" type="date" name="to" value="<?= e($rf['preset'] === 'custom' ? $rf['to'] : '') ?>" data-custom-range>
    </div>
    <?php if ($rp_organisers): ?>
        <div class="field">
            <label for="rp-org"><?= t('Organiser') ?></label>
            <select id="rp-org" name="organiser" data-autosubmit>
                <option value=""><?= t('Everyone') ?></option>
                <?php foreach ($rp_organisers as $id => $name): ?>
                    <option value="<?= (int) $id ?>"<?= (int) $rf['organiser'] === (int) $id ? ' selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="report-filters-actions">
        <button class="btn btn-primary" type="submit"><?= t('Apply') ?></button>
        <?php if ($rp_query !== ['range' => '12m']): ?><a class="btn btn-ghost" href="?"><?= t('Reset') ?></a><?php endif; ?>
    </div>
</form>

<?php if ($rp_summary['events'] === 0): ?>
    <section class="card">
        <div class="empty-state">
            <i class="fa-solid fa-chart-column"></i>
            <h3><?= t('No events in this period') ?></h3>
            <p><?= t('Choose a longer period, or come back once events have registrations.') ?></p>
            <a class="btn btn-secondary" href="?range=all"><?= t('Show all time') ?></a>
        </div>
    </section>
<?php else: ?>

<div class="stats report-kpis">
    <div class="stat">
        <span class="stat-icon is-sky"><i class="fa-solid fa-calendar-days"></i></span>
        <span><span class="stat-value"><?= $rp_summary['events'] ?></span><br>
        <span class="stat-label"><?= t('Events, {n} still to come', ['n' => $rp_summary['upcoming']]) ?></span></span>
    </div>
    <div class="stat">
        <span class="stat-icon"><i class="fa-solid fa-ticket"></i></span>
        <span><span class="stat-value"><?= $rp_summary['registrations'] ?></span><br>
        <span class="stat-label"><?= e(_n('Registrations from {n} person', 'Registrations from {n} people', $rp_summary['people'])) ?><?= $rp_summary['pending'] ? ', ' . t('{n} pending', ['n' => $rp_summary['pending']]) : '' ?></span></span>
    </div>
    <div class="stat">
        <span class="stat-icon is-green"><i class="fa-solid fa-user-check"></i></span>
        <span><span class="stat-value"><?= e(format_rate($rp_summary['attendance_rate'])) ?></span><br>
        <span class="stat-label"><?= $rp_summary['approved_held']
            ? t('Turned up: {attended} of {approved} approved', ['attended' => $rp_summary['attended'], 'approved' => $rp_summary['approved_held']])
            : t('Attendance shows once events take place') ?></span></span>
    </div>
    <div class="stat">
        <span class="stat-icon is-amber"><i class="fa-solid fa-star"></i></span>
        <span><span class="stat-value"><?= $rp_summary['rating_avg'] !== null ? e(number_format($rp_summary['rating_avg'], 1)) : '–' ?></span><br>
        <span class="stat-label"><?= $rp_summary['rating_count'] ? e(_n('Average of {n} rating', 'Average of {n} ratings', $rp_summary['rating_count'])) : t('No ratings yet') ?></span></span>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?= t('Registrations per month') ?></h2>
            <p class="card-subtitle"><?= t('By the day people registered. Approval rate {rate}.', ['rate' => format_rate($rp_summary['approval_rate'])]) ?><?= $rp_summary['waiting'] ? ' ' . e(_n('{n} person is waiting on a waitlist now.', '{n} people are waiting on a waitlist now.', $rp_summary['waiting'])) : '' ?></p>
        </div>
    </div>
    <div class="card-body">
        <?= render_column_chart($rp_months, 'registrations', __('Registrations per month')) ?>
        <?php if ($rp_months): ?>
            <details class="viz-table">
                <summary><?= t('Show as table') ?></summary>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th scope="col"><?= t('Month') ?></th><th scope="col" class="num"><?= t('Registrations') ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($rp_months as $m): ?>
                            <tr><td><?= e($m['label']) ?></td><td class="num"><?= $m['count'] ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    </div>
</section>

<div class="grid-2">
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= t('Who registers') ?></h2>
                <p class="card-subtitle"><?= t('Registrations by participant category') ?></p>
            </div>
        </div>
        <div class="card-body"><?= render_bar_list($rp_categories, 'registrations') ?></div>
    </section>
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= t('Ratings') ?></h2>
                <p class="card-subtitle"><?= t('How participants rated the events') ?></p>
            </div>
        </div>
        <div class="card-body"><?= render_bar_list($rp_rating_items, 'ratings') ?></div>
    </section>
</div>

<section class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?= t('Events') ?></h2>
            <p class="card-subtitle"><?= t('Most registrations first.') ?><?= count($rp_events) > count($rp_shown) ? ' ' . t('Showing {shown} of {total}; the CSV export has them all.', ['shown' => count($rp_shown), 'total' => count($rp_events)]) : '' ?></p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table report-table">
            <thead>
            <tr>
                <th scope="col"><?= t('Event') ?></th>
                <th scope="col" class="num"><?= t('Registrations') ?></th>
                <th scope="col"><?= t('Capacity filled') ?></th>
                <th scope="col"><?= t('Attendance') ?></th>
                <th scope="col" class="num"><?= t('Rating') ?></th>
                <th scope="col" class="num"><?= t('Waitlist') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rp_shown as $ev): ?>
                <tr>
                    <td>
                        <div class="event-cell">
                            <?= datestub($ev['event_date']) ?>
                            <div>
                                <a class="event-cell-title" href="<?= e($rp_events_url . $ev['event_id']) ?>"><?= e($ev['event_name']) ?></a>
                                <div class="event-cell-meta"><?= e(ldate('j M Y', $ev['event_date'])) ?><?= $rp_organisers && $ev['organiser'] ? ' · ' . e($ev['organiser']) : '' ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="num"><?= $ev['registrations'] ?><?= $ev['capacity'] ? ' <span class="cell-muted">/ ' . $ev['capacity'] . '</span>' : '' ?></td>
                    <td><?= $ev['capacity'] ? render_rate_meter($ev['fill_rate']) : '<span class="cell-muted">' . t('No limit') . '</span>' ?></td>
                    <td><?= $ev['held'] ? ($ev['approved'] ? render_rate_meter($ev['attendance_rate']) : '<span class="cell-muted">' . t('Nobody approved') . '</span>') : '<span class="cell-muted">' . t('Not held yet') . '</span>' ?></td>
                    <td class="num"><?= $ev['rating_avg'] !== null ? e(number_format($ev['rating_avg'], 1)) . ' <span class="cell-muted">(' . $ev['rating_count'] . ')</span>' : '<span class="cell-muted">–</span>' ?></td>
                    <td class="num"><?= $ev['waiting'] ?: '<span class="cell-muted">–</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<p class="report-footnote">
    <?= t('Registrations count pending and approved places. Attendance compares checked-in participants with approved ones, for events that have already taken place.') ?>
    <?= t('Generated {date}.', ['date' => ldate('j M Y, g:i A')]) ?>
</p>
<?php endif; ?>
