<?php
/**
 * MyEvent - Translation checker (command line only).
 *
 *   php tools/i18n.php              list wrapped strings missing from lang/ms.php, and unused translations
 *   php tools/i18n.php --missing    print the missing ones as PHP array lines, ready to paste and translate
 *
 * A "wrapped string" is the literal first argument of __(), t(), th() or the two first arguments of _n().
 * Exit status 1 when something is missing (so CI can fail on it).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Every literal passed to __(), t(), th() and _n() in the application's PHP files.
 * @return array<string, list<string>> text => ["file:line", ...]
 */
function i18n_scan(string $root): array
{
    $keys = [];
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static fn(SplFileInfo $f) => !($f->isDir() && in_array($f->getFilename(), ['vendor', '.git', 'tests', 'storage', 'uploads', 'lang', 'node_modules'], true))
    ));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $rel = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
        $tokens = token_get_all((string) file_get_contents($path));
        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            $tok = $tokens[$i];
            if (!is_array($tok) || $tok[0] !== T_STRING || !in_array($tok[1], ['__', 't', 'th', '_n'], true)) {
                continue;
            }
            // Skip method calls / definitions: ->t(  ::t(  function t(
            $p = $i - 1;
            while ($p >= 0 && is_array($tokens[$p]) && $tokens[$p][0] === T_WHITESPACE) {
                $p--;
            }
            if ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (($tokens[$j] ?? null) !== '(') {
                continue;
            }
            $args = $tok[1] === '_n' ? 2 : 1;
            $k = $j + 1;
            for ($a = 0; $a < $args; $a++) {
                while ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                    $k++;
                }
                $arg = $tokens[$k] ?? null;
                if (!is_array($arg) || $arg[0] !== T_CONSTANT_ENCAPSED_STRING) {
                    break; // not a literal (a variable); nothing to check
                }
                $text = eval('return ' . $arg[1] . ';');
                $keys[$text][] = $rel . ':' . $arg[2];
                $k++;
                while ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                    $k++;
                }
                if (($tokens[$k] ?? null) !== ',') {
                    break;
                }
                $k++;
            }
        }
    }
    ksort($keys);
    return $keys;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $root = dirname(__DIR__);
    $keys = i18n_scan($root);
    $ms = require $root . '/lang/ms.php';
    $missing = array_diff_key($keys, $ms);
    $unused = array_diff_key($ms, $keys);

    if (in_array('--missing', $argv, true)) {
        foreach ($missing as $text => $where) {
            echo '    ' . var_export($text, true) . " => '',\n";
        }
        exit($missing ? 1 : 0);
    }

    printf("%d strings in the code, %d translated to Malay.\n", count($keys), count($keys) - count($missing));
    foreach ($missing as $text => $where) {
        echo "MISSING  $text   ({$where[0]})\n";
    }
    foreach ($unused as $text => $_) {
        echo "UNUSED   $text\n";
    }
    exit($missing ? 1 : 0);
}
