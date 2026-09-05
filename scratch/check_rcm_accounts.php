<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$accounts = \App\Domains\Accounting\Models\ChartOfAccount::where('name', 'like', '%RCM%')->get();
foreach ($accounts as $acc) {
    echo "ID: {$acc->id} | Code: {$acc->code} | Name: {$acc->name} | Type: {$acc->type}" . PHP_EOL;
}
