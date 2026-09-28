<?php
$c = json_decode(file_get_contents('ERP_Master_REST_API_Postman_Collection.json'), true);
function c($items) {
    $n = 0;
    foreach ($items as $x) {
        if (isset($x['request'])) $n++;
        if (isset($x['item'])) $n += c($x['item']);
    }
    return $n;
}
foreach ($c['item'] as $f) {
    echo sprintf("%-35s: %d endpoints\n", $f['name'], c($f['item'] ?? []));
}
echo "--------------------------------------------------------\n";
echo sprintf("%-35s: %d endpoints\n", "GRAND TOTAL", c($c['item']));
