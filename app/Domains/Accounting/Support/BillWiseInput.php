<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\BillReference;
use App\Domains\Accounting\Models\ChartOfAccount;
use Illuminate\Validation\ValidationException;

/**
 * Reads the party and bill-wise details keyed on a voucher / journal line
 * (Tally's allocation box) and turns them into journal lines tagged with the
 * party plus 'bill_allocations' hints for the register.
 *
 * Each item may carry:
 *   party            "type:id" (see PartyDirectory)
 *   bill_allocations JSON list of {ref_type, bill_reference_id?, ref_number?,
 *                    bill_date?, due_date?, amount}
 * Whatever a line's allocations don't cover goes On Account.
 */
class BillWiseInput
{
    public function __construct(
        private readonly PartyDirectory $parties,
        private readonly \App\Domains\Accounting\Services\BillWise\BillAllocationService $register,
    ) {
    }

    /** Validation rules to merge into a store() request. */
    public static function rules(): array
    {
        return [
            'items.*.party' => ['nullable', 'string', 'max:40'],
            'items.*.bill_allocations' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * The voucher-level party ("type:id") onto every bill-wise line that has
     * no party of its own. Lines keep a party picked in their own box.
     *
     * @param array<int|string, array<string, mixed>> $items
     * @return array<int|string, array<string, mixed>>
     */
    public function withVoucherParty(array $items, string $party): array
    {
        [$type, $id] = array_pad(explode(':', $party, 2), 2, null);
        if (! PartyDirectory::isType($type) || ! ctype_digit((string) $id) || ! $this->parties->exists($type, (int) $id)) {
            throw ValidationException::withMessages(['party' => 'The selected party is invalid.']);
        }

        $billWise = array_map('intval', array_keys($this->register->controlAccounts((int) require_tenant_id())));

        foreach ($items as $key => $item) {
            if (trim((string) ($item['party'] ?? '')) === '' && in_array((int) ($item['chart_of_account_id'] ?? 0), $billWise, true)) {
                $items[$key]['party'] = "{$type}:{$id}";
            }
        }

        return $items;
    }

    /**
     * @param array<int|string, array<string, mixed>> $items validated items
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} [lines, hints]
     */
    public function apply(array $items): array
    {
        $items = array_values($items);
        // The register's own list (Receivable / Payable are always bill-wise).
        $billWise = array_map('intval', array_keys($this->register->controlAccounts((int) require_tenant_id())));

        $lines = [];
        $hints = [];

        foreach ($items as $index => $item) {
            $party = trim((string) ($item['party'] ?? ''));
            $allocations = json_decode((string) ($item['bill_allocations'] ?? ''), true) ?: [];
            unset($item['party'], $item['bill_allocations']);

            $accountId = (int) $item['chart_of_account_id'];
            $amount = round(abs((float) ($item['debit'] ?? 0) - (float) ($item['credit'] ?? 0)), 2);

            if ($party !== '') {
                [$type, $id] = array_pad(explode(':', $party, 2), 2, null);
                if (! PartyDirectory::isType($type) || ! ctype_digit((string) $id) || ! $this->parties->exists($type, (int) $id)) {
                    $this->fail($index, 'The selected party is invalid.');
                }

                [$type, $id] = $this->parties->canonical($type, (int) $id);
                $item['party_type'] = $type;
                $item['party_id'] = $id;

                if (in_array($accountId, $billWise, true)) {
                    foreach ($this->hintsFor($index, $accountId, $type, $id, $amount, $allocations) as $hint) {
                        $hints[] = $hint;
                    }
                }
            } elseif ($allocations !== []) {
                $this->fail($index, 'Choose the party before picking bills.');
            } elseif (in_array($accountId, $billWise, true)) {
                // Like Tally's Sundry Creditors / Debtors: an untagged line here
                // would be missing from every party's ledger and outstanding.
                $name = ChartOfAccount::whereKey($accountId)->value('name');
                $this->fail($index, "{$name} is kept party by party: click \"Pick party & bills\" and choose the party.");
            }

            $lines[] = $item;
        }

        return [$lines, $hints];
    }

    /** @return list<array<string, mixed>> */
    private function hintsFor(int $index, int $accountId, string $partyType, int $partyId, float $lineAmount, array $allocations): array
    {
        $hints = [];
        $total = 0.0;

        foreach ($allocations as $row) {
            if (! is_array($row)) {
                continue;
            }

            $refType = (string) ($row['ref_type'] ?? '');
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            if (! in_array($refType, [BillAllocation::NEW_REF, BillAllocation::AGAINST_REF, BillAllocation::ADVANCE, BillAllocation::ON_ACCOUNT], true)) {
                $this->fail($index, 'Unknown bill-wise type.');
            }

            $hint = [
                'ref_type' => $refType,
                'line_index' => $index,
                'account_id' => $accountId,
                'party_type' => $partyType,
                'party_id' => $partyId,
                'amount' => $amount,
            ];

            if ($refType === BillAllocation::AGAINST_REF) {
                $billId = (int) ($row['bill_reference_id'] ?? 0);
                $belongs = BillReference::query()->whereKey($billId)
                    ->where('party_type', $partyType)->where('party_id', $partyId)
                    ->where('control_account_id', $accountId)->exists();
                if (! $belongs) {
                    $this->fail($index, 'A picked bill does not belong to this party and ledger.');
                }
                $hint['bill_reference_id'] = $billId;
            }

            if ($refType === BillAllocation::NEW_REF) {
                $ref = trim((string) ($row['ref_number'] ?? ''));
                if ($ref === '') {
                    $this->fail($index, 'A New Ref needs a bill / reference number.');
                }
                $hint['ref_number'] = mb_substr($ref, 0, 100);
                $hint['bill_date'] = $this->date($row['bill_date'] ?? null);
                $hint['due_date'] = $this->date($row['due_date'] ?? null) ?? $hint['bill_date'];
                $hint = array_filter($hint, fn ($v) => $v !== null);
            }

            $total += $amount;
            $hints[] = $hint;
        }

        if ($total - $lineAmount > 0.005) {
            $this->fail($index, 'Bill-wise amounts (' . number_format($total, 2) . ') are more than the line amount (' . number_format($lineAmount, 2) . ').');
        }

        return $hints;
    }

    public function partyName(string $type, int $id): string
    {
        return $this->parties->name($type, $id);
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function fail(int $index, string $message): never
    {
        throw ValidationException::withMessages(['items' => 'Line ' . ($index + 1) . ': ' . $message]);
    }
}
