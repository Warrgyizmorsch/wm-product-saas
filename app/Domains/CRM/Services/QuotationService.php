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
        $lead = $leadId ? Lead::find($leadId) : ($quotation->lead_id ? Lead::find($quotation->lead_id) : null);

        // 1. Automatic Deal Stage Transition when Quotation is Sent
        if (in_array($status, ['Quotation Sent', 'Sent'])) {
            $deal = $quotation->crm_deal_id ? CrmDeal::find($quotation->crm_deal_id) : null;
            $leadObj = $lead;
            if ($leadId) {
                $leadObj = Lead::find($leadId);
            }
            if (!$leadObj && $quotation->lead_id) {
                $leadObj = Lead::find($quotation->lead_id);
            }
            if (!$deal && $leadObj && $leadObj->crm_deal_id) {
                $deal = CrmDeal::find($leadObj->crm_deal_id);
            }

            if ($deal && !in_array($deal->stage, ['Won', 'Closed Won', 'Closed Lost'])) {
                // Determine if this is a first-time quotation or a revised quotation
                $isRevision = ($quotation->revision_number > 0)
                    || !empty($quotation->parent_id)
                    || Quotation::query()
                        ->where(function($q) use ($deal, $quotation) {
                            $q->where('crm_deal_id', $deal->id);
                            if ($quotation->lead_id) {
                                $q->orWhere('lead_id', $quotation->lead_id);
                            }
                        })
                        ->where('id', '!=', $quotation->id)
                        ->where('id', '<', $quotation->id)
                        ->exists();

                $newStage = $isRevision ? 'Negotiation' : 'Proposal';
                $newProbability = $isRevision ? 80 : 60;
                $oldStage = $deal->stage;

                $deal->update([
                    'stage'       => $newStage,
                    'probability' => $newProbability,
                ]);

                if ($leadObj && $oldStage !== $newStage) {
                    LeadHistory::logEvent(
                        $leadObj, 'deal_stage_updated', $oldStage, $newStage,
                        "Deal '{$deal->title}' stage updated to '{$newStage}' because Quotation {$quotation->quotation_number} status changed to '{$status}'"
                    );
                }
            }
        }

        // 2. Customer Acceptance when Quotation is Accepted
        // Note: Customer conversion is deferred until the user explicitly clicks "Convert to Customer".
        if ($status === 'Accepted') {
            $deal = $quotation->crm_deal_id ? CrmDeal::find($quotation->crm_deal_id) : null;
            if (!$lead && $deal) {
                if (!empty($deal->lead_id)) {
                    $lead = Lead::find($deal->lead_id);
                }
                if (!$lead) {
                    $lead = Lead::where('crm_deal_id', $deal->id)->first();
                }
            }
            if ($lead) {
                LeadHistory::logEvent(
                    $lead, 'quotation_accepted', $lead->status, $lead->status,
                    "Quotation {$quotation->quotation_number} was accepted. Ready for customer conversion."
                );
            }
        }
    }

    public function delete(Quotation $quotation): bool
    {
        return $this->quotations->delete($quotation);
    }
}
