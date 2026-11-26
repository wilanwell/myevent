<?php
/**
 * MyEvent - Translations (English and Bahasa Melayu).
 *
 * The English text is the key, as with gettext:
 *     __('Log in')                                  plain text (escape it yourself)
 *     t('Hello, {name}', ['name' => $name])         escaped, ready for HTML
 *     _n('{n} place left', '{n} places left', $n)   singular / plural
 *     ldate('l, j F Y', $ts)                         date() with translated day and month names
 *
 * Translations live in lang/<locale>.php as ['English text' => 'Terjemahan'].
 * A missing translation simply shows the English text. tests/Unit/TranslationTest.php
 * fails when a wrapped string has no Malay translation, or a translation is no longer used.
 *
 * Which language: the account's choice (users.locale), else the visitor's choice
 * (session / cookie), else the browser's Accept-Language, else APP_LOCALE (default en).
 */

const LOCALES = ['en' => 'English', 'ms' => 'Bahasa Melayu'];
const LOCALE_COOKIE = 'myevent_lang';

/** Language used for this request (or for the email currently being written). */
function current_locale(): string
{
    return $GLOBALS['__locale'] ?? default_locale();
}

function default_locale(): string
{
    $fallback = (string) env('APP_LOCALE', 'en');
    return isset(LOCALES[$fallback]) ? $fallback : 'en';
}

function set_locale(string $locale): void
{
    $GLOBALS['__locale'] = isset(LOCALES[$locale]) ? $locale : default_locale();
}

/** Run $fn in another language (e.g. to write an email in the recipient's language). */
function with_locale(?string $locale, callable $fn)
{
    $previous = current_locale();
    set_locale($locale ?: default_locale());
    try {
        return $fn();
    } finally {
        set_locale($previous);
    }
}

/** Pick the language for a web request (see the file header for the order). */
function detect_locale(?string $accountLocale = null): string
{
    foreach ([$accountLocale, $_SESSION['locale'] ?? null, $_COOKIE[LOCALE_COOKIE] ?? null] as $choice) {
        if (is_string($choice) && isset(LOCALES[$choice])) {
            return $choice;
        }
    }
    return locale_from_accept_language((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) ?? default_locale();
}

/** The first supported language in an Accept-Language header, honouring q values. */
function locale_from_accept_language(string $header): ?string
{
    $ranked = [];
    foreach (explode(',', $header) as $i => $part) {
        $bits = explode(';', trim($part));
        $tag = strtolower(trim($bits[0]));
        $q = 1.0;
        foreach (array_slice($bits, 1) as $param) {
            if (preg_match('/^\s*q=([0-9.]+)\s*$/', $param, $m)) {
                $q = (float) $m[1];
            }
        }
        $base = substr($tag, 0, 2);
        if ($q > 0 && isset(LOCALES[$base])) {
            $ranked[] = [$q, -$i, $base];
        }
    }
    if (!$ranked) {
        return null;
    }
    rsort($ranked);
    return $ranked[0][2];
}

/** The translation table for a language (cached). */
function translations(string $locale): array
{
    static $cache = [];
    if (!isset($cache[$locale])) {
        $file = __DIR__ . '/../lang/' . $locale . '.php';
        $cache[$locale] = $locale !== 'en' && is_file($file) ? (array) require $file : [];
    }
    return $cache[$locale];
}

/** Translate $text and fill in {placeholders}. Returns plain text. */
function __(string $text, array $params = []): string
{
    $out = translations(current_locale())[$text] ?? $text;
    if ($params) {
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs['{' . $k . '}'] = (string) $v;
        }
        $out = strtr($out, $pairs);
    }
    return $out;
}

/** Translate and escape for HTML (the text and every parameter). */
function t(string $text, array $params = []): string
{
    return e(__($text, $params));
}

/**
 * Translate with HTML parameters: the text is escaped, parameters are inserted as given.
 * Use only with parameters you built safely, e.g. th('Hi {name}', ['name' => '<b>' . e($name) . '</b>']).
 */
function th(string $text, array $params = []): string
{
    $out = e(__($text));
    $pairs = [];
    foreach ($params as $k => $v) {
        $pairs['{' . $k . '}'] = (string) $v;
    }
    return strtr($out, $pairs);
}

/** Singular or plural; {n} is replaced by the number. Malay uses one form for both. */
function _n(string $singular, string $plural, int $n, array $params = []): string
{
    return __($n === 1 ? $singular : $plural, $params + ['n' => $n]);
}

/** date() with day and month names (and AM/PM) in the current language. */
function ldate(string $format, $time = null): string
{
    $ts = $time === null ? time() : (is_int($time) ? $time : strtotime((string) $time));
    $out = date($format, $ts);
    if (current_locale() !== 'ms') {
        return $out;
    }
    static $names = [
        // Longest first, so "Monday" is replaced before "Mon".
        'Monday' => 'Isnin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Khamis',
        'Friday' => 'Jumaat', 'Saturday' => 'Sabtu', 'Sunday' => 'Ahad',
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Mac', 'April' => 'April', 'June' => 'Jun',
        'July' => 'Julai', 'August' => 'Ogos', 'September' => 'September', 'October' => 'Oktober',
        'November' => 'November', 'December' => 'Disember',
        'Mon' => 'Isn', 'Tue' => 'Sel', 'Wed' => 'Rab', 'Thu' => 'Kha', 'Fri' => 'Jum', 'Sat' => 'Sab', 'Sun' => 'Ahd',
        'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mac', 'Apr' => 'Apr', 'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul',
        'Aug' => 'Ogo', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Dis',
        'AM' => 'PG', 'PM' => 'PTG',
    ];
    // Only whole words, so "Mac" is never turned into something else.
    return preg_replace_callback('/\b(' . implode('|', array_map('preg_quote', array_keys($names))) . ')\b/',
        static fn($m) => $names[$m[1]], $out);
}

/** <html lang="..."> value. */
function html_lang(): string
{
    return current_locale();
}
