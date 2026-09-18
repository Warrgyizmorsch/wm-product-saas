<?php

$langEn = [];
$langHi = [];
$langBg = [];

foreach (['crm', 'sales', 'inventory', 'common'] as $domain) {
    if (file_exists(__DIR__ . "/../lang/en/{$domain}.php")) {
        $langEn[$domain] = require __DIR__ . "/../lang/en/{$domain}.php";
    }
    if (file_exists(__DIR__ . "/../lang/hi/{$domain}.php")) {
        $langHi[$domain] = require __DIR__ . "/../lang/hi/{$domain}.php";
    }
    if (file_exists(__DIR__ . "/../lang/bg/{$domain}.php")) {
        $langBg[$domain] = require __DIR__ . "/../lang/bg/{$domain}.php";
    }
}

$missingKeys = [];

$dirs = ['sales', 'crm'];

foreach ($dirs as $dir) {
    $target = __DIR__ . '/../resources/views/modules/' . $dir;
    if (!is_dir($target)) continue;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
    foreach ($files as $f) {
        if ($f->isDir() || !str_ends_with($f->getFilename(), '.blade.php')) continue;
        $content = file_get_contents($f->getPathname());
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            // match __('domain.key.subkey')
            if (preg_match_all("/__\(['\"]([a-zA-Z0-9_]+)\.([a-zA-Z0-9_\.]+)['\"]\)/", $line, $m)) {
                foreach ($m[1] as $idx => $domain) {
                    $key = $m[2][$idx];
                    $fullKey = $domain . '.' . $key;
                    
                    $parts = explode('.', $key);
                    
                    foreach (['en' => $langEn, 'hi' => $langHi, 'bg' => $langBg] as $lang => $dict) {
                        if (!isset($dict[$domain])) {
                            $missingKeys[$lang][$fullKey][] = str_replace(realpath(__DIR__ . '/..') . '/', '', $f->getRealPath()) . ': line ' . ($lineNum + 1);
                            continue;
                        }

                        $val = $dict[$domain];
                        foreach ($parts as $p) {
                            $val = is_array($val) && isset($val[$p]) ? $val[$p] : null;
                        }

                        if ($val === null || is_array($val)) {
                            // If it's an array, maybe it's accessed dynamically in code, but if missing/null it's an issue
                            if ($val === null) {
                                $missingKeys[$lang][$fullKey][] = str_replace(realpath(__DIR__ . '/..') . '/', '', $f->getRealPath()) . ': line ' . ($lineNum + 1);
                            }
                        }
                    }
                }
            }
        }
    }
}

foreach ($missingKeys as $lang => $keys) {
    foreach ($keys as $k => $locs) {
        $missingKeys[$lang][$k] = array_unique($locs);
    }
}

echo json_encode($missingKeys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
