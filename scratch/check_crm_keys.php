<?php

$en = require __DIR__ . '/../lang/en/crm.php';
$hi = require __DIR__ . '/../lang/hi/crm.php';
$bg = require __DIR__ . '/../lang/bg/crm.php';

$missingDetails = [];

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
            if (preg_match_all("/__\(['\"]crm\.([a-zA-Z0-9_\.]+)['\"]\)/", $line, $m)) {
                foreach ($m[1] as $k) {
                    $parts = explode('.', $k);
                    
                    // Check EN
                    $valEn = $en;
                    foreach ($parts as $p) {
                        $valEn = is_array($valEn) && isset($valEn[$p]) ? $valEn[$p] : null;
                    }
                    if ($valEn === null) {
                        $relPath = str_replace(realpath(__DIR__ . '/..') . '/', '', $f->getRealPath());
                        $missingDetails[$k][] = "$relPath: line " . ($lineNum + 1);
                    }
                }
            }
        }
    }
}

foreach ($missingDetails as $k => $locs) {
    $missingDetails[$k] = array_unique($locs);
}

echo json_encode($missingDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
