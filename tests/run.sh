#!/usr/bin/env bash
# =====================================================================
# MyEvent - run every check, the same as CI does.
#
#   bash tests/run.sh          lint + PHPUnit + end-to-end
#   bash tests/run.sh --fast   lint + PHPUnit only (no web server needed)
#
# PHP defaults to XAMPP's php.exe; override with PHP=/path/to/php.
# PHPUnit uses its own database (myevent_test) and never touches your data.
# The end-to-end checks need Apache and MySQL running (see tests/e2e.sh).
# =====================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -z "${PHP:-}" ]; then
    if [ -x /c/xampp/php/php.exe ]; then PHP=/c/xampp/php/php.exe; else PHP=php; fi
fi
export PHP

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/ is missing: run 'composer install' first (see README, Testing)." >&2
    exit 1
fi

echo "== Lint"
"$PHP" tools/lint.php

echo
echo "== Translations"
"$PHP" tools/i18n.php

echo
echo "== PHPUnit"
"$PHP" vendor/bin/phpunit

if [ "${1:-}" != "--fast" ]; then
    echo
    echo "== End-to-end"
    bash tests/e2e.sh
fi

echo
echo "All checks passed."
