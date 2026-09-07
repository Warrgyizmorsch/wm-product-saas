<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$bill = \App\Domains\Purchase\Models\VendorBill::with(['items.grnItem', 'vendor'])->find(7);
$bill->gst_type = 'rcm_igst';
$bill->tax_amount = 750.00;
$bill->cgst_amount = 0.00;
$bill->sgst_amount = 0.00;
$bill->igst_amount = 750.00;

echo "Testing RCM Inter-State IGST on Bill #7 ({$bill->bill_number})..." . PHP_EOL;

$listener = app(\App\Domains\Accounting\Listeners\PostPurchaseBillJournal::class);
$event = new \App\Domains\Purchase\Events\BillPosted($bill);

try {
    $listener->handle($event);
    echo "SUCCESS! Handle completed cleanly." . PHP_EOL;
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}

$journals = \App\Domains\Accounting\Models\Journal::where('reference_type', 'vendor_bill')
    ->where('reference_id', 7)
    ->with(['entries.account'])
    ->get();

foreach ($journals as $j) {
    echo "Journal ID: {$j->id} | Number: {$j->journal_number} | Date: {$j->journal_date}" . PHP_EOL;
    foreach ($j->entries as $line) {
        $accCode = $line->account ? $line->account->code : 'NO_CODE';
        $accName = $line->account ? $line->account->name : 'NO_NAME';
        echo "  Account [{$accCode}] {$accName} | Debit: {$line->debit} | Credit: {$line->credit} | Desc: {$line->description}" . PHP_EOL;
    }
    // Clean up test entry so DB remains unchanged for user
    $j->entries()->delete();
    $j->delete();
    echo "Cleaned test journal entry ID {$j->id}" . PHP_EOL;
}
