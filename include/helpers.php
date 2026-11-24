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
