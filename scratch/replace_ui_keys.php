<?php

$dirs = ['sales', 'crm'];

foreach ($dirs as $dir) {
    $target = __DIR__ . '/../resources/views/modules/' . $dir;
    if (!is_dir($target)) continue;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
    foreach ($files as $f) {
        if ($f->isDir() || !str_ends_with($f->getFilename(), '.blade.php')) continue;
        $content = file_get_contents($f->getPathname());
        $newContent = str_replace(
            ['__(\'ui.sales\')', '__("ui.sales")', '__(\'ui.filter\')', '__("ui.filter")'],
            ['__(\'crm.sales\')', '__("crm.sales")', '__(\'crm.filter\')', '__("crm.filter")'],
            $content
        );
        if ($newContent !== $content) {
            file_put_contents($f->getPathname(), $newContent);
            echo "Updated " . $f->getFilename() . "\n";
        }
    }
}
