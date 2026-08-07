<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderItem;
use App\Domains\CRM\Models\LeadHistory;
use App\Domains\CRM\Repositories\QuotationRepository;
use App\Domains\Inventory\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class QuotationService
{
    public function __construct(
        private readonly QuotationRepository $quotations,
    ) {}

    public function latest(): Collection
    {
        return $this->quotations->latest();
    }

    public function find(int $id): ?Quotation
    {
        return $this->quotations->find($id);
    }

    public function getNextQuotationNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "{$year}-";

        $latest = Quotation::query()
            ->whereNull('parent_id')
            ->where('quotation_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($latest) {
            $rawNum = $latest->getRawOriginal('quotation_number');
            $lastNumStr = str_replace($prefix, '', $rawNum);
            $nextNum = intval($lastNumStr) + 1;
        }
        
        return 'QT-' . $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data, array $items): Quotation
    {
        return DB::transaction(function () use ($data, $items) {
            if (empty($data['quotation_number'])) {
                $data['quotation_number'] = $this->getNextQuotationNumber();
            }

            $subtotal = 0;
            $tax = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $taxRate = floatval($item['tax_rate'] ?? 0);
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;

                $amount = $qty * $price;
                $itemTax = $amount * ($taxRate / 100);

                $subtotal += $amount;
                $tax += $itemTax;

                $itemName = $item['item_name'] ?? 'Product/Service';
                if ($productId) {
                    $product = Product::find($productId);
                    if ($product) {
                        $itemName = $product->name;
                    }
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'item_name' => $itemName,
                    'description' => $item['description'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'amount' => $amount,
                ];
            }

            $discount = floatval($data['discount'] ?? 0);
            $totalAmount = $subtotal + $tax - $discount;

            $data['subtotal'] = $subtotal;
            $data['tax'] = $tax;
            $data['total_amount'] = $totalAmount;
            $data['is_current'] = true;
            $data['revision_number'] = 0;

            $quotation = $this->quotations->create($data);
            $quotation->items()->createMany($itemsData);

            return $quotation;
        });
    }

    public function update(Quotation $quotation, array $data, array $items): Quotation
    {
        return DB::transaction(function () use ($quotation, $data, $items) {
            $rootParentId = $quotation->parent_id ?: $quotation->id;

            $latestRevision = Quotation::query()
                ->where(function ($query) use ($rootParentId) {
                    $query->where('parent_id', $rootParentId)
                          ->orWhere('id', $rootParentId);
                })
                ->max('revision_number') ?? 0;

            $newRevisionNumber = $latestRevision + 1;
            $rawNum = $quotation->getRawOriginal('quotation_number');
            $baseNum = explode('-R', $rawNum)[0];
            $newQuotationNumber = $baseNum . '-R' . $newRevisionNumber;

            $subtotal = 0;
            $tax = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $taxRate = floatval($item['tax_rate'] ?? 0);
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;

                $amount = $qty * $price;
                $itemTax = $amount * ($taxRate / 100);

                $subtotal += $amount;
                $tax += $itemTax;

                $itemName = $item['item_name'] ?? 'Product/Service';
                if ($productId) {
                    $product = Product::find($productId);
                    if ($product) {
                        $itemName = $product->name;
                    }
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'item_name' => $itemName,
                    'description' => $item['description'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'amount' => $amount,
                ];
            }

            $discount = floatval($data['discount'] ?? 0);
            $totalAmount = $subtotal + $tax - $discount;

            $revData = [
                'tenant_id' => $quotation->tenant_id,
                'lead_id' => $data['lead_id'] ?? $quotation->lead_id,
                'crm_account_id' => $data['crm_account_id'] ?? $quotation->crm_account_id,
                'crm_deal_id' => $data['crm_deal_id'] ?? $quotation->crm_deal_id,
                'sales_person_id' => $data['sales_person_id'] ?? $quotation->sales_person_id,
                'quotation_number' => $newQuotationNumber,
                'quotation_date' => $data['quotation_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'status' => $data['status'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total_amount' => $totalAmount,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'notes' => $data['notes'] ?? null,
                'parent_id' => $rootParentId,
                'revision_number' => $newRevisionNumber,
                'is_current' => true,
            ];

            Quotation::query()
                ->where(function ($query) use ($rootParentId) {
                    $query->where('parent_id', $rootParentId)
                          ->orWhere('id', $rootParentId);
                })
                ->update(['is_current' => false]);

            $newQuotation = $this->quotations->create($revData);
            $newQuotation->items()->createMany($itemsData);

            return $newQuotation;
        });
    }

    public function handleQuotationStatusChange(Quotation $quotation, string $status, ?int $leadId = null): void
    {
        if ($status === 'Accepted') {
            $tenantId = $quotation->tenant_id ?? (tenant_id() ?? 1);
            $deal = $quotation->crm_deal_id ? CrmDeal::find($quotation->crm_deal_id) : null;
            $account = $quotation->crm_account_id ? CrmAccount::find($quotation->crm_account_id) : ($deal ? $deal->account : null);
            $lead = null;
            if ($leadId) {
                $lead = Lead::find($leadId);
            }
            if (!$lead && $quotation->lead_id) {
                $lead = Lead::find($quotation->lead_id);
            }
            if (!$lead && $account) {
                $lead = Lead::where('crm_account_id', $account->id)->first();
            }

            // Determine if B2B or B2C
            $isB2B = $lead ? ($lead->lead_type === 'b2b' || !empty($lead->company_name) || !empty($lead->gstin)) : true;
            $customer = null;

            if ($isB2B) {
                // ========== B2B PRIORITY MATCHING ==========
                $gstin = $lead ? $lead->gstin : ($account ? $account->gstin : null);
                $compEmail = $lead ? ($lead->company_email ?: $lead->email) : ($account ? $account->email : null);
                $compPhone = $lead ? ($lead->company_phone ?: $lead->phone) : ($account ? $account->phone : null);
                $compName = $lead ? $lead->company_name : ($account ? $account->name : 'New Client');

                // Priority 1: GSTIN
                if (!empty($gstin)) {
                    $customer = Customer::where('tenant_id', $tenantId)->where('gstin', $gstin)->first();
                }
                // Priority 2: Company Email
                if (!$customer && !empty($compEmail)) {
                    $customer = Customer::where('tenant_id', $tenantId)->whereRaw('LOWER(email) = ?', [strtolower($compEmail)])->first();
                }
                // Priority 3: Company Phone
                if (!$customer && !empty($compPhone)) {
                    $cleanP = preg_replace('/[^0-9]/', '', $compPhone);
                    if (!empty($cleanP)) {
                        $customer = Customer::where('tenant_id', $tenantId)->where(function($q) use ($compPhone, $cleanP) {
                            $q->where('phone', 'like', "%{$compPhone}%")
                              ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ["%{$cleanP}%"]);
                        })->first();
                    }
                }
                // Priority 4: Company Name
                if (!$customer && !empty($compName) && strlen($compName) >= 3) {
                    $cleanComp = trim(preg_replace('/(pvt|ltd|private|limited|inc|corp|co)/i', '', $compName));
                    if (!empty($cleanComp)) {
                        $customer = Customer::where('tenant_id', $tenantId)->where('name', 'like', "%{$cleanComp}%")->first();
                    }
                }

                if (!$customer) {
                    $customer = Customer::create([
                        'tenant_id' => $tenantId,
                        'name'      => $compName,
                        'email'     => $compEmail,
                        'phone'     => $compPhone,
                        'gstin'     => $gstin,
                        'status'    => 'active',
                    ]);
                } else {
                    $customer->update([
                        'status' => 'active',
                        'gstin'  => $customer->gstin ?: $gstin,
                    ]);
                }
            } else {
                // ========== B2C PRIORITY MATCHING ==========
                $personName = $lead ? ($lead->contact_person ?: $lead->company_name) : 'Individual Customer';
                $personEmail = $lead ? $lead->email : null;
                $personPhone = $lead ? $lead->phone : null;

                // Priority 1: Email
                if (!empty($personEmail)) {
                    $customer = Customer::where('tenant_id', $tenantId)->whereRaw('LOWER(email) = ?', [strtolower($personEmail)])->first();
                }
                // Priority 2: Mobile Phone
                if (!$customer && !empty($personPhone)) {
                    $cleanP = preg_replace('/[^0-9]/', '', $personPhone);
                    if (!empty($cleanP)) {
                        $customer = Customer::where('tenant_id', $tenantId)->where(function($q) use ($personPhone, $cleanP) {
                            $q->where('phone', 'like', "%{$personPhone}%")
                              ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ["%{$cleanP}%"]);
                        })->first();
                    }
                }
                // Priority 3: Person Name
                if (!$customer && !empty($personName)) {
                    $customer = Customer::where('tenant_id', $tenantId)->where('name', 'like', "%{$personName}%")->first();
                }

                if (!$customer) {
                    $customer = Customer::create([
                        'tenant_id' => $tenantId,
                        'name'      => $personName,
                        'email'     => $personEmail,
                        'phone'     => $personPhone,
                        'status'    => 'active',
                    ]);
                } else {
                    $customer->update(['status' => 'active']);
                }
            }

            // Sync CrmAccount
            if (!$account) {
                $compName = $lead ? ($lead->company_name ?: ($lead->contact_person ?: 'New Client')) : 'New Client';
                $account = CrmAccount::where('tenant_id', $tenantId)->where('customer_id', $customer->id)->first();
                if (!$account) {
                    $account = CrmAccount::create([
                        'tenant_id'   => $tenantId,
                        'customer_id' => $customer->id,
                        'name'        => $compName,
                        'email'       => $customer->email,
                        'phone'       => $customer->phone,
                        'status'      => 'active',
                        'owner_id'    => auth()->id() ?: 1,
                    ]);
                }
            } else {
                if (!$account->customer_id) {
                    $account->update(['customer_id' => $customer->id]);
                }
            }

            // 3. Sync or Find CRM Contact in `crm_contacts` table
            $companyName = $customer->name;
            $contactEmail = $lead ? ($lead->email ?: $customer->email) : $customer->email;
            $contactPhone = $lead ? ($lead->phone ?: $customer->phone) : $customer->phone;

            $contact = null;
            $contactName = $lead ? ($lead->contact_person ?: $companyName) : $companyName;
            if ($contactName) {
                $contact = CrmContact::where('crm_account_id', $account->id)->where('name', $contactName)->first();
                if (!$contact) {
                    $contact = CrmContact::create([
                        'tenant_id'      => $tenantId,
                        'crm_account_id' => $account->id,
                        'name'           => $contactName,
                        'role'           => 'Purchase Decision Maker',
                        'email'          => $contactEmail,
                        'phone'          => $contactPhone,
                        'mobile'         => $contactPhone,
                        'is_primary'     => $account->contacts()->count() === 0,
                        'status'         => 'active',
                    ]);
                }
            }

            // 4. Update or Create Deal in `crm_deals` table
            $deal = null;
            if ($quotation->crm_deal_id) {
                $deal = CrmDeal::find($quotation->crm_deal_id);
            }
            if ($deal) {
                $deal->update([
                    'stage'        => 'Won',
                    'actual_value' => $quotation->total_amount,
                    'probability'  => 100,
                    'closing_date' => now(),
                ]);
            } else {
                $dealTitle = $lead ? ($lead->requirement ?: "Project for {$companyName}") : "Quotation {$quotation->quotation_number}";
                $deal = CrmDeal::create([
                    'tenant_id'       => $tenantId,
                    'crm_account_id'  => $account->id,
                    'crm_contact_id'  => $contact ? $contact->id : null,
                    'title'           => $dealTitle,
                    'estimated_value' => $quotation->total_amount,
                    'actual_value'    => $quotation->total_amount,
                    'stage'           => 'Won',
                    'closing_date'    => now(),
                    'lead_source'     => $lead ? ($lead->source ?: 'Quotation Accepted') : 'Quotation Accepted',
                    'probability'     => 100,
                    'owner_id'        => auth()->id(),
                ]);
            }

            // 5. Update Quotation with crm_account_id and crm_deal_id
            $quotation->update([
                'crm_account_id' => $account->id,
                'crm_deal_id'    => $deal->id,
            ]);

            // 6. Update Lead status to 'Won' (Converted)
            if ($lead) {
                $lead->update([
                    'status'         => 'Won',
                    'converted_at'   => now(),
                    'crm_account_id' => $account->id,
                    'crm_contact_id' => $contact ? $contact->id : null,
                    'crm_deal_id'    => $deal->id,
                    'is_customer'    => true,
                ]);
            }

            // 7. Check Company Setting for Auto SO Generation (Default false)
            $autoCreateSo = config('settings.auto_create_so_on_quote_accept', false);
            if ($autoCreateSo) {
                $existingSo = SalesOrder::where('quotation_id', $quotation->id)->first();
                if (!$existingSo) {
                    $so = SalesOrder::create([
                        'tenant_id'       => $tenantId,
                        'quotation_id'    => $quotation->id,
                        'crm_deal_id'     => $deal->id,
                        'customer_id'     => $customer->id,
                        'so_number'       => 'SO-' . date('Y') . '-' . str_pad((string)(SalesOrder::whereYear('created_at', date('Y'))->count() + 1), 4, '0', STR_PAD_LEFT),
                        'order_date'      => now(),
                        'status'          => 'Pending',
                        'subtotal'        => $quotation->subtotal,
                        'tax'             => $quotation->tax,
                        'discount'        => $quotation->discount,
                        'total_amount'    => $quotation->total_amount,
                    ]);

                    foreach ($quotation->items as $qItem) {
                        SalesOrderItem::create([
                            'tenant_id'      => $tenantId,
                            'sales_order_id' => $so->id,
                            'product_id'     => $qItem->product_id,
                            'quantity'       => $qItem->quantity,
                            'unit_price'     => $qItem->unit_price,
                            'total_price'    => $qItem->amount,
                        ]);
                    }
                }
            }
        }
    }

    public function delete(Quotation $quotation): bool
    {
        return $this->quotations->delete($quotation);
    }
}
