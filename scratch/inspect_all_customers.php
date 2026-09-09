<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\CrmAccount;

echo "ALL CUSTOMERS:\n";
foreach (Customer::all() as $c) {
    echo "ID: {$c->id} | Name: {$c->name} | Email: {$c->email} | Phone: {$c->phone} | GSTIN: {$c->gstin}\n";
}

echo "\nALL ACCOUNTS:\n";
foreach (CrmAccount::all() as $a) {
    echo "ID: {$a->id} | Name: {$a->name} | Email: {$a->email} | Phone: {$a->phone} | GSTIN: {$a->gstin} | CustomerID: {$a->customer_id}\n";
}
