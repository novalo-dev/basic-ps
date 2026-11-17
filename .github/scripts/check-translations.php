<?php
/**
 * CI check: the translation catalogues are valid XML, every locale holds the same
 * strings as en-US for each domain, and every string the code translates in the
 * module's own domains is in the en-US catalogue.
 *
 * Usage: php check-translations.php <module root>
 */
declare(strict_types=1);

$root = rtrim((string) realpath($argv[1] ?? '.'), '/');
$failed = false;
$catalogues = [];

foreach (glob($root . '/translations/*/*.xlf') ?: [] as $file) {
    $xml = @simplexml_load_file($file);
    if ($xml === false) {
        printf("FAIL %s is not valid XML\n", substr($file, strlen($root) + 1));
        $failed = true;
        continue;
    }
    $locale = basename(dirname($file));
    $domain = preg_replace('/\.[a-z]{2}-[A-Z]{2}\.xlf$/', '', basename($file));
    $sources = [];
    foreach ($xml->xpath('//*[local-name()="trans-unit"]/*[local-name()="source"]') ?: [] as $source) {
        $sources[] = (string) $source;
    }
    $catalogues[$domain][$locale] = $sources;
}

foreach ($catalogues as $domain => $locales) {
    if (!isset($locales['en-US'])) {
        printf("FAIL %s has no en-US catalogue\n", $domain);
        $failed = true;
        continue;
    }
    foreach ($locales as $locale => $sources) {
        $missing = array_diff($locales['en-US'], $sources);
        $extra = array_diff($sources, $locales['en-US']);
        foreach ($missing as $text) {
            printf("FAIL %s %s lacks \"%s\"\n", $domain, $locale, $text);
            $failed = true;
        }
        foreach ($extra as $text) {
            printf("FAIL %s %s has \"%s\", unknown in en-US\n", $domain, $locale, $text);
            $failed = true;
        }
    }
    printf("ok   %s: %d string(s) in %s\n", $domain, count($locales['en-US']), implode(', ', array_keys($locales)));
}

// trans('text', [...], 'Modules.Editionbasic.X') and the controllers' trans('text', 'domain')
// or translate('text')
$pattern = '/->trans\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,\s*(?:\[[^\]]*\]\s*,\s*)?[\'"](Modules\.Editionbasic\.[A-Za-z]+)[\'"]/s';
$checked = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $path = $file->getPathname();
    if (!preg_match('/\.php$/', $path) || preg_match('#/(vendor|dev|\.github)/#', $path)) {
        continue;
    }
    $source = (string) file_get_contents($path);
    preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);
    // The controllers' translate('text'), which always uses the Admin domain.
    preg_match_all('/->translate\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*\)/s', $source, $adminMatches, PREG_SET_ORDER);
    foreach ($adminMatches as $match) {
        $matches[] = [$match[0], $match[1], $match[2], 'Modules.Editionbasic.Admin'];
    }
    foreach ($matches as $match) {
        $text = stripslashes($match[2]);
        ++$checked;
        $domain = str_replace('.', '', $match[3]);
        if (!in_array($text, $catalogues[$domain]['en-US'] ?? [], true)) {
            printf("FAIL %s translates \"%s\" (%s), missing from its en-US catalogue\n", substr($path, strlen($root) + 1), $text, $match[3]);
            $failed = true;
        }
    }
}

printf("ok   %d translated string(s) in the code checked\n", $checked);

exit($failed ? 1 : 0);
