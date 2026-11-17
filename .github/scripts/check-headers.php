<?php
/**
 * CI check: every PHP file the module ships carries its licence header.
 *
 * Usage: php check-headers.php <module root>
 */
declare(strict_types=1);

$root = rtrim((string) realpath($argv[1] ?? '.'), '/');
$failed = false;
$count = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $path = $file->getPathname();
    $relative = substr($path, strlen($root) + 1);
    if (!preg_match('/\.php$/', $path) || preg_match('#^(vendor|dev|\.github)/#', $relative) || $relative === '.php-cs-fixer.dist.php') {
        continue;
    }
    ++$count;
    $head = (string) file_get_contents($path, false, null, 0, 2000);
    if (strpos($head, 'Academic Free License') === false) {
        printf("FAIL %s has no licence header\n", $relative);
        $failed = true;
    }
}
printf("ok   %d PHP file(s) read\n", $count);

exit($failed ? 1 : 0);
