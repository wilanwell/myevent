<?php
/**
 * MyEvent - Switch the interface language (POST from the EN | BM switcher).
 * Remembers the choice in the session, a one-year cookie and, when signed in, the account.
 */
require_once __DIR__ . '/../config.php';

require_post();

$locale = (string) ($_POST['locale'] ?? '');
if (!isset(LOCALES[$locale])) {
    $locale = default_locale();
}

$_SESSION['locale'] = $locale;
setcookie(LOCALE_COOKIE, $locale, [
    'expires'  => time() + 365 * 86400,
    'path'     => '/',
    'secure'   => IS_HTTPS,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if ($uid = current_user_id()) {
    $stmt = $conn->prepare('UPDATE users SET locale = ? WHERE user_id = ?');
    $stmt->bind_param('si', $locale, $uid);
    $stmt->execute();
    $stmt->close();
}

// Back to the page the switch was pressed on, but only a page of this site.
$back = (string) ($_POST['return'] ?? '');
$basePath = (string) parse_url(BASE_URL, PHP_URL_PATH);
if ($back === '' || $back[0] !== '/' || str_starts_with($back, '//') || str_contains($back, '\\')
    || !str_starts_with($back, $basePath) || preg_match('/[\r\n]/', $back)) {
    $back = $basePath . 'index.php';
}
header('Location: ' . $back, true, 303);
exit;
