<?php
/**
 * MyEvent - Syntax check every PHP file (command line only).
 *
 *   php tools/lint.php
 *
 * Runs "php -l" on each .php file outside vendor/ and exits with status 1 on any error.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$skip = ['vendor', '.git', 'storage', 'uploads', 'node_modules'];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    static fn(SplFileInfo $f) => !($f->isDir() && in_array($f->getFilename(), $skip, true))
));
foreach ($it as $file) {
    if ($file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
sort($files);

$failed = 0;
foreach ($files as $file) {
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $failed++;
        echo implode("\n", $out), "\n";
    }
}

printf("%d PHP files checked, %d with errors.\n", count($files), $failed);
exit($failed ? 1 : 0);
