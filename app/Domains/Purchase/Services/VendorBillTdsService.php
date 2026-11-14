<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Accounting\Services\Tds\TdsCalculatorInterface;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\VendorBill;

/**
 * Fills a new vendor bill's TDS from the vendor's TDS setup, and takes the
 * TDS off what the bill leaves payable: the vendor is owed the bill less the
 * tax withheld (the rest is owed to the government).
 *
 * Runs from VendorBill's creating hook, so every path that makes a bill
 * (bill screen, GRN auto-bill, service bill, API) gets it. A bill created
 * with tds_overridden keeps the TDS it was given (or none).
 */
class VendorBillTdsService
{
    public function __construct(
        private readonly TdsCalculatorInterface $calculator,
    ) {
    }

    /**
     * TDS fields for VendorBill::create() from the bill form: the section picked
     * (else the vendor's), and a TDS amount typed over the calculated one.
     */
    public static function fromInput(array $input): array
    {
        $fields = ['tds_section_id' => filled($input['tds_section_id'] ?? null) ? (int) $input['tds_section_id'] : null];

        if (isset($input['tds_amount']) && $input['tds_amount'] !== '' && ! empty($input['tds_overridden'])) {
            $fields['tds_amount'] = round((float) $input['tds_amount'], 2);
            $fields['tds_overridden'] = true;
        }

        return $fields;
    }

    public function apply(VendorBill $bill): void
    {
        if (! $bill->tds_overridden) {
            $this->calculate($bill);
        } elseif ((float) $bill->tds_amount > 0 && $bill->tds_section_id) {
            // Typed by hand: keep the amount, record what it was charged on.
            $base = round(self::baseOf($bill), 2);
            $bill->tds_base_amount = $base;
            $bill->tds_rate = $base > 0 ? round((float) $bill->tds_amount / $base * 100, 3) : 0;
            $bill->tds_reason ??= \App\Domains\Accounting\Models\TdsDeduction::REASON_NORMAL;
        } else {
            $bill->tds_amount = 0;
        }

        $tds = round((float) ($bill->tds_amount ?? 0), 4);

        if ($tds > 0) {
            $due = $bill->due_amount !== null
                ? (float) $bill->due_amount
                : (float) $bill->grand_total - (float) ($bill->paid_amount ?? 0);
            $bill->due_amount = max(0.0, round($due - $tds, 4));
        }
    }

    /** Validation for the TDS block of the vendor master. */
    public static function vendorRules(): array
    {
        return [
            'tds_applicable' => ['nullable', 'boolean'],
            'tds_section_id' => ['nullable', 'integer', 'exists:tds_sections,id', 'required_if:tds_applicable,1'],
            'deductee_type' => ['nullable', 'in:' . implode(',', array_keys(\App\Domains\Accounting\Models\TdsSection::DEDUCTEE_TYPES))],
            'ldc_number' => ['nullable', 'string', 'max:30'],
            'ldc_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_with:ldc_number'],
            'ldc_valid_from' => ['nullable', 'date', 'required_with:ldc_number'],
            'ldc_valid_to' => ['nullable', 'date', 'after_or_equal:ldc_valid_from', 'required_with:ldc_number'],
            'ldc_limit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** Active sections for the bill and vendor forms. */
    public function sectionOptions(): \Illuminate\Support\Collection
    {
        // Tenants created before TDS existed get the starter sections on first use.
        if (tenant_id() !== null) {
            app(\App\Domains\Accounting\Services\Tds\TdsSectionService::class)->provisionDefaultsIfMissing((int) tenant_id());
        }

        return \App\Domains\Accounting\Models\TdsSection::query()->active()->nonSalary()->orderBy('code')->orderBy('name')->get(['id', 'code', 'name']);
    }

    /**
     * What the bill form shows while it is being filled in.
     *
     * @return array{section_id: ?int, rate: float, amount: float, note: string}
     */
    public function preview(int $vendorId, float $base, string $date, ?int $sectionId): array
    {
        $vendor = Vendor::query()->find($vendorId);
        $result = $vendor ? $this->calculator->forBill($vendor, $base, $date, $sectionId) : null;

        $note = match ($result?->reason) {
            null => $vendor?->tds_applicable ? 'Vendor has no TDS section set.' : '',
            \App\Domains\Accounting\Models\TdsDeduction::REASON_BELOW_THRESHOLD => 'Below the threshold - no TDS yet.',
            \App\Domains\Accounting\Models\TdsDeduction::REASON_NO_PAN => 'No PAN on the vendor: higher rate (206AA).',
            \App\Domains\Accounting\Models\TdsDeduction::REASON_LOWER_CERT => 'Lower deduction certificate ' . $vendor->ldc_number . '.',
            default => $result->catchupBase > 0 ? 'Yearly limit crossed: includes ' . number_format($result->catchupBase, 2) . ' of earlier bills.' : '',
        };

        return [
            'section_id' => $result?->sectionId,
            'rate' => $result?->rate ?? 0.0,
            'amount' => $result?->amount ?? 0.0,
            'note' => $note,
        ];
    }

    /** Bill value before GST: TDS is never charged on the GST part (CBDT circular 23/2017). */
    public static function baseOf(VendorBill $bill): float
    {
        if ($bill->subtotal !== null) {
            return max(0.0, (float) $bill->subtotal - (float) ($bill->discount_amount ?? 0) + (float) ($bill->freight_amount ?? 0));
        }

        return max(0.0, (float) $bill->grand_total - (float) ($bill->tax_amount ?? 0) - (float) ($bill->adjustment ?? 0));
    }

    private function calculate(VendorBill $bill): void
    {
        $vendor = $bill->vendor_id
            ? Vendor::query()->withoutGlobalScopes()->where('tenant_id', $bill->tenant_id)->find($bill->vendor_id)
            : null;

        $result = $vendor
            ? $this->calculator->forBill($vendor, self::baseOf($bill), $bill->bill_date ?? now(), $bill->tds_section_id, $bill->id)
            : null;

        $bill->tds_section_id = $result?->sectionId;
        $bill->tds_base_amount = $result?->base ?? 0;
        $bill->tds_rate = $result?->rate ?? 0;
        $bill->tds_amount = $result?->amount ?? 0;
        $bill->tds_catchup_base = $result?->catchupBase ?? 0;
        $bill->tds_reason = $result?->reason;
    }
}
