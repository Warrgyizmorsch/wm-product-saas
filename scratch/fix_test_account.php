<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Services\QuotationService;

// Clean up orphaned empty "New Client" Customer #11 if exists
$dummy = Customer::where('name', 'New Client')->whereNull('email')->whereNull('phone')->first();
if ($dummy) {
    echo "Deleting empty dummy Customer #{$dummy->id}...\n";
    $dummy->delete();
}

$quotation = Quotation::find(10);
if ($quotation) {
    echo "Re-running handleQuotationStatusChange for Quotation #{$quotation->id} ({$quotation->quotation_number})...\n";
    app(QuotationService::class)->handleQuotationStatusChange($quotation, 'Accepted');
    echo "Done!\n";
}

$deal = CrmDeal::find(11);
if ($deal && $deal->crm_account_id) {
    $acc = CrmAccount::find($deal->crm_account_id);
    if ($acc) {
        echo "ACCOUNT #{$acc->id}:\n";
        echo "  Name: {$acc->name}\n";
        echo "  Email: {$acc->email}\n";
        echo "  Phone: {$acc->phone}\n";
        echo "  GSTIN: {$acc->gstin}\n";
        echo "  Customer ID: {$acc->customer_id}\n";
    }
    if ($acc && $acc->customer_id) {
        $cust = Customer::find($acc->customer_id);
        if ($cust) {
            echo "CUSTOMER #{$cust->id}:\n";
            echo "  Name: {$cust->name}\n";
            echo "  Email: {$cust->email}\n";
            echo "  Phone: {$cust->phone}\n";
            echo "  GSTIN: {$cust->gstin}\n";
        }
    }
}
