<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
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
            $customer = $quotation->customer;
            
            if (!$customer) {
                $lead = null;
                if ($leadId) {
                    $lead = Lead::find($leadId);
                }
                if (!$lead && $quotation->lead_id) {
                    $lead = Lead::find($quotation->lead_id);
                }

                if ($lead) {
                    if ($lead->email) {
                        $customer = Customer::where('email', $lead->email)->first();
                    }
                    if (!$customer && $lead->phone) {
                        $customer = Customer::where('phone', $lead->phone)->first();
                    }

                    if (!$customer) {
                        Customer::create([
                            'tenant_id' => $lead->tenant_id,
                            'name' => $lead->company_name ?: ($lead->contact_person ?: 'Converted Lead'),
                            'email' => $lead->email,
                            'phone' => $lead->phone,
                            'status' => 'active',
                        ]);
                    } else {
                        $customer->update(['status' => 'active']);
                    }
                }
            } else {
                $customer->update(['status' => 'active']);
            }
        }
    }

    public function delete(Quotation $quotation): bool
    {
        return $this->quotations->delete($quotation);
    }
}
