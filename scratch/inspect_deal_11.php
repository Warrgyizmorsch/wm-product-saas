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

$deal = CrmDeal::with(['account', 'lead', 'quotations'])->find(11);
echo "DEAL #11:\n";
echo "Title: " . $deal->title . "\n";
echo "Stage: " . $deal->stage . "\n";
echo "crm_account_id: " . var_export($deal->crm_account_id, true) . "\n";
echo "lead_id on deal: " . var_export($deal->lead_id, true) . "\n";

$lead = Lead::where('crm_deal_id', 11)->orWhere(function($q) use ($deal) {
    if (!empty($deal->lead_id)) $q->where('id', $deal->lead_id);
})->first();

if ($lead) {
    echo "LINKED LEAD #{$lead->id}:\n";
    echo "  Company Name: " . $lead->company_name . "\n";
    echo "  Contact Person: " . $lead->contact_person . "\n";
    echo "  Email: " . $lead->email . "\n";
    echo "  Company Email: " . $lead->company_email . "\n";
    echo "  Phone: " . $lead->phone . "\n";
    echo "  Company Phone: " . $lead->company_phone . "\n";
    echo "  GSTIN: " . $lead->gstin . "\n";
    echo "  Address: " . $lead->address . "\n";
    echo "  City: " . $lead->city . "\n";
    echo "  State: " . $lead->state . "\n";
    echo "  Country: " . $lead->country . "\n";
    echo "  crm_account_id on lead: " . var_export($lead->crm_account_id, true) . "\n";
} else {
    echo "NO LINKED LEAD FOUND FOR DEAL #11\n";
}

$acc = CrmAccount::where('name', 'New Client')->orWhere('id', $deal->crm_account_id)->first();
if ($acc) {
    echo "CRM ACCOUNT #{$acc->id} ({$acc->name}):\n";
    echo "  Email: " . $acc->email . "\n";
    echo "  Phone: " . $acc->phone . "\n";
    echo "  GSTIN: " . $acc->gstin . "\n";
    echo "  Customer ID: " . $acc->customer_id . "\n";
}

$cust = Customer::where('name', 'New Client')->orWhere(function($q) use ($acc) {
    if ($acc && $acc->customer_id) $q->where('id', $acc->customer_id);
})->first();

if ($cust) {
    echo "CUSTOMER #{$cust->id} ({$cust->name}):\n";
    echo "  Email: " . $cust->email . "\n";
    echo "  Phone: " . $cust->phone . "\n";
    echo "  GSTIN: " . $cust->gstin . "\n";
}
