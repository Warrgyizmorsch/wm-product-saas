<?php

$filterKey = [
    'en' => 'Filter',
    'hi' => 'फ़िल्टर',
    'bg' => 'Филтър'
];

foreach (['en', 'hi', 'bg'] as $lang) {
    $filePath = __DIR__ . "/../lang/{$lang}/crm.php";
    $data = require $filePath;
    if (!isset($data['filter'])) {
        $data['filter'] = $filterKey[$lang];
        $export = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        file_put_contents($filePath, $export);
    }
}

echo "Added filter key to crm lang files\n";
