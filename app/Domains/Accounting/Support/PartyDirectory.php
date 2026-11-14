<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\CRM\Models\Customer;
use App\Domains\HRMS\Models\Employee;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Platform\Models\Transporter;

/**
 * Every kind of party a ledger line can belong to — customers, suppliers,
 * employees, transporters — with their names and a picker list, so bill-wise
 * reports and voucher forms treat them all the same way.
 *
 * A transporter that is linked to a vendor record (transporters.vendor_id) is
 * the same party as that vendor; it resolves to the vendor so its bills and
 * payments land on one account.
 */
class PartyDirectory
{
    public const LABELS = [
        JournalEntry::PARTY_CUSTOMER => 'Customer',
        JournalEntry::PARTY_VENDOR => 'Supplier',
        JournalEntry::PARTY_EMPLOYEE => 'Employee',
        JournalEntry::PARTY_TRANSPORTER => 'Transporter',
    ];

    /** Transporter has no tenant scope of its own, so filter explicitly. */
    private static function transporters()
    {
        return Transporter::query()->where('tenant_id', tenant_id());
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::LABELS);
    }

    public static function isType(?string $type): bool
    {
        return $type !== null && isset(self::LABELS[$type]);
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? ucfirst($type);
    }

    public function name(string $type, int $id): string
    {
        $name = match ($type) {
            JournalEntry::PARTY_CUSTOMER => Customer::query()->whereKey($id)->value('name'),
            JournalEntry::PARTY_VENDOR => Vendor::query()->whereKey($id)->value('name'),
            JournalEntry::PARTY_EMPLOYEE => Employee::query()->whereKey($id)->value('full_name'),
            JournalEntry::PARTY_TRANSPORTER => self::transporters()->whereKey($id)->value('name'),
            default => null,
        };

        return $name ?? self::label($type) . " #{$id}";
    }

    public function exists(string $type, int $id): bool
    {
        return match ($type) {
            JournalEntry::PARTY_CUSTOMER => Customer::query()->whereKey($id)->exists(),
            JournalEntry::PARTY_VENDOR => Vendor::query()->whereKey($id)->exists(),
            JournalEntry::PARTY_EMPLOYEE => Employee::query()->whereKey($id)->exists(),
            JournalEntry::PARTY_TRANSPORTER => self::transporters()->whereKey($id)->exists(),
            default => false,
        };
    }

    /**
     * The party a line should actually be tagged with: a transporter linked to
     * a vendor is that vendor.
     *
     * @return array{0: string, 1: int}
     */
    public function canonical(string $type, int $id): array
    {
        if ($type === JournalEntry::PARTY_TRANSPORTER) {
            $vendorId = self::transporters()->whereKey($id)->value('vendor_id');
            if ($vendorId) {
                return [JournalEntry::PARTY_VENDOR, (int) $vendorId];
            }
        }

        return [$type, $id];
    }

    /**
     * Picker list: "type:id" => "Name (Type)", optionally one type only.
     *
     * @return array<string, string>
     */
    public function options(?string $onlyType = null): array
    {
        $options = [];

        $add = function (string $type, iterable $rows) use (&$options) {
            foreach ($rows as $id => $name) {
                $options["{$type}:{$id}"] = $name . ' (' . self::label($type) . ')';
            }
        };

        if ($onlyType === null || $onlyType === JournalEntry::PARTY_CUSTOMER) {
            $add(JournalEntry::PARTY_CUSTOMER, Customer::query()->orderBy('name')->pluck('name', 'id'));
        }
        if ($onlyType === null || $onlyType === JournalEntry::PARTY_VENDOR) {
            $add(JournalEntry::PARTY_VENDOR, Vendor::query()->orderBy('name')->pluck('name', 'id'));
        }
        if ($onlyType === null || $onlyType === JournalEntry::PARTY_EMPLOYEE) {
            $add(JournalEntry::PARTY_EMPLOYEE, Employee::query()->orderBy('full_name')->pluck('full_name', 'id'));
        }
        if ($onlyType === null || $onlyType === JournalEntry::PARTY_TRANSPORTER) {
            // Linked transporters are offered as their vendor instead.
            $add(JournalEntry::PARTY_TRANSPORTER, self::transporters()->whereNull('vendor_id')->orderBy('name')->pluck('name', 'id'));
        }

        return $options;
    }

    /**
     * Search all party types by name, for the voucher party picker.
     *
     * @return list<array{id: string, text: string}>
     */
    public function search(string $term, int $perType = 15): array
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($term)) . '%';
        $results = [];

        $add = function (string $type, iterable $rows) use (&$results) {
            foreach ($rows as $id => $name) {
                $results[] = ['id' => "{$type}:{$id}", 'text' => $name . ' (' . self::label($type) . ')'];
            }
        };

        $add(JournalEntry::PARTY_CUSTOMER, Customer::query()->where('name', 'like', $like)->orderBy('name')->limit($perType)->pluck('name', 'id'));
        $add(JournalEntry::PARTY_VENDOR, Vendor::query()->where('name', 'like', $like)->orderBy('name')->limit($perType)->pluck('name', 'id'));
        $add(JournalEntry::PARTY_EMPLOYEE, Employee::query()->where('full_name', 'like', $like)->orderBy('full_name')->limit($perType)->pluck('full_name', 'id'));
        $add(JournalEntry::PARTY_TRANSPORTER, self::transporters()->whereNull('vendor_id')->where('name', 'like', $like)->orderBy('name')->limit($perType)->pluck('name', 'id'));

        return $results;
    }
}
