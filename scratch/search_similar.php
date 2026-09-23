<?php
$en = include 'lang/en/production.php';
foreach ($en as $k => $v) {
    if (stripos($v, 'history') !== false || stripos($v, 'dispatch board') !== false || stripos($v, 'due date') !== false || stripos($k, 'due_date') !== false) {
        echo "$k => $v\n";
    }
}
