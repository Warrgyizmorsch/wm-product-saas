<?php

$filePath = 'c:/xampp/htdocs/new erp/wm-product-saas/ERP_Master_REST_API_Postman_Collection.json';
$data = json_decode(file_get_contents($filePath), true);

echo "Total top-level folders: " . count($data['item']) . "\n\n";

$totalRequests = 0;

function printItems($items, $prefix = "") {
    global $totalRequests;
    foreach ($items as $item) {
        if (isset($item['item'])) {
            echo $prefix . "[Folder] " . $item['name'] . " (" . count($item['item']) . " items)\n";
            printItems($item['item'], $prefix . "  ");
        } else {
            $method = $item['request']['method'] ?? 'GET';
            $url = is_array($item['request']['url']) ? ($item['request']['url']['raw'] ?? '') : ($item['request']['url'] ?? '');
            echo $prefix . "- [" . $method . "] " . $item['name'] . " -> " . $url . "\n";
            $totalRequests++;
        }
    }
}

printItems($data['item']);
echo "\nTotal Requests in Postman Collection: " . $totalRequests . "\n";
