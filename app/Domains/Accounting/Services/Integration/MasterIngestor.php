<?php

namespace App\Domains\Accounting\Services\Integration;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\IntegrationConflict;
use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRecord;
use App\Domains\Accounting\Models\IntegrationRef;
use App\Domains\Accounting\Models\LedgerGroup;
use Illuminate\Support\Facades\DB;

/**
 * Ledger groups and ledgers. Existing records are found by ref, then by name, so
 * the tenant's default chart isn't duplicated. Accounts are never made children
 * of a control account: posting to a parent account is refused, which would stop
 * every automatic posting to Accounts Receivable.
 */
class MasterIngestor
{
    public function __construct(
        private readonly ConflictRecorder $conflicts,
        private readonly IntegrationRefRepositoryInterface $refs,
    ) {
    }

    public function ingestGroup(IntegrationConnection $connection, array $item): array
    {
        $externalId = $this->required($item, 'external_id');
        $name = $this->required($item, 'name');

        return DB::transaction(function () use ($connection, $item, $externalId, $name) {
            $hash = hash('sha256', json_encode([$name, $item['parent_name'] ?? null, $item['nature'] ?? null]));
            $ref = $this->refs->lockOrCreate($connection, IntegrationRef::ENTITY_GROUP, $externalId, IntegrationRef::DIRECTION_INBOUND);
            $group = $ref->local_id ? LedgerGroup::query()->withoutGlobalScope('company')->find($ref->local_id) : null;

            if ($group !== null && $ref->payload_hash === $hash) {
                return $this->result(IntegrationRecord::OUTCOME_UNCHANGED, $ref);
            }

            $parentId = $this->groupIdByName($connection, $item['parent_name'] ?? null);
            $outcome = IntegrationRecord::OUTCOME_UPDATED;

            if ($group === null) {
                $group = $this->findGroupByName($connection, $name);
                $outcome = $group !== null ? IntegrationRecord::OUTCOME_UPDATED : IntegrationRecord::OUTCOME_CREATED;
            }

            if ($group === null) {
                $nature = $item['nature'] ?? null;
                if (! in_array($nature, ChartOfAccount::TYPES, true)) {
                    throw new IntegrationRecordException(
                        IntegrationRecordException::UNMAPPED_GROUP,
                        "Group '{$name}' has no known nature. It must sit under a predefined group, or send nature as one of: ".implode(', ', ChartOfAccount::TYPES).'.'
                    );
                }

                $group = LedgerGroup::create([
                    'tenant_id' => $connection->tenant_id,
                    'company_id' => $connection->company_id,
                    'code' => $this->generatedCode($connection, 'G', $externalId),
                    'name' => $name,
                    'parent_id' => $parentId,
                    'nature' => $nature,
                    'is_active' => true,
                ]);
            } elseif (! $group->is_system) {
                $group->update(['name' => $name, 'parent_id' => $parentId ?? $group->parent_id]);
            }

            $ref->update([
                'external_name' => $name,
                'local_type' => IntegrationRef::LOCAL_GROUP,
                'local_id' => $group->id,
                'payload_hash' => $hash,
                'meta' => ['root_group' => $item['root_group'] ?? null],
                'status' => IntegrationRef::STATUS_SYNCED,
                'last_error' => null,
                'last_synced_at' => now(),
            ]);

            return $this->result($outcome, $ref);
        });
    }

    public function ingestLedger(IntegrationConnection $connection, array $item, bool $force = false): array
    {
        $externalId = $this->required($item, 'external_id');
        $name = $this->required($item, 'name');

        return DB::transaction(function () use ($connection, $item, $externalId, $name, $force) {
            $type = $item['type'] ?? null;
            $hash = hash('sha256', json_encode([$name, $item['group_name'] ?? $item['group_external_id'] ?? null, $type, (bool) ($item['is_active'] ?? true)]));

            // A ledger the ERP pushed earlier comes back with Tally's own id. Adopt
            // that ref, or a pushed customer would come back as a new account.
            $ref = $this->refs->adoptByName($connection, $externalId, $name)
                ?? $this->refs->lockOrCreate($connection, IntegrationRef::ENTITY_LEDGER, $externalId, IntegrationRef::DIRECTION_INBOUND);

            if ($ref->local_type !== null && $ref->local_type !== IntegrationRef::LOCAL_ACCOUNT) {
                // A party master (customer, vendor...) belongs to its own module.
                $ref->update(['external_name' => $name, 'payload_hash' => $hash, 'status' => IntegrationRef::STATUS_SYNCED, 'last_synced_at' => now()]);

                return $this->result(IntegrationRecord::OUTCOME_UNCHANGED, $ref);
            }

            $account = $ref->local_id ? $this->account($ref->local_id) : null;

            if ($account === null) {
                return $this->linkOrCreate($connection, $ref, $item, $externalId, $name, $hash, $force);
            }

            if ($ref->payload_hash === $hash) {
                return $this->result(IntegrationRecord::OUTCOME_UNCHANGED, $ref);
            }

            // Somebody mapped this by hand, so the two names differ on purpose.
            if (! empty($ref->meta['manual'])) {
                $this->stamp($ref, $account, $name, $hash);

                return $this->result(IntegrationRecord::OUTCOME_UNCHANGED, $ref);
            }

            if ($this->conflicts->keptLocal($connection, IntegrationRef::ENTITY_LEDGER, $externalId, $hash)) {
                return $this->result(IntegrationRecord::OUTCOME_UNCHANGED, $ref);
            }

            if ($account->is_system) {
                if ($account->name === $name && ($type === null || $type === $account->type)) {
                    $this->stamp($ref, $account, $name, $hash);

                    return $this->result(IntegrationRecord::OUTCOME_UNCHANGED, $ref);
                }

                return $this->conflicts->record(
                    $connection, IntegrationRef::ENTITY_LEDGER, $externalId, IntegrationConflict::REASON_SYSTEM_ACCOUNT,
                    "'{$account->name}' is a system account, so the sync won't change it to match '{$name}'.",
                    $item, $hash, $ref,
                );
            }

            if ($type !== null && $type !== $account->type && (! $force || $account->journalEntries()->exists())) {
                return $this->conflicts->record(
                    $connection, IntegrationRef::ENTITY_LEDGER, $externalId, IntegrationConflict::REASON_TYPE_CHANGE,
                    "'{$account->name}' is {$account->type} here but {$type} in the source. Changing the type of an account would move its balance between reports.",
                    $item, $hash, $ref,
                );
            }

            if (! $force && $ref->local_hash !== null && $ref->local_hash !== $this->localHash($account)) {
                return $this->conflicts->record(
                    $connection, IntegrationRef::ENTITY_LEDGER, $externalId, IntegrationConflict::REASON_CHANGED_ON_BOTH_SIDES,
                    "'{$account->name}' was changed here and in the source since the last sync.",
                    $item, $hash, $ref,
                );
            }

            $account->update(array_filter([
                'name' => $name,
                'ledger_group_id' => $this->groupIdByName($connection, $item['group_name'] ?? null) ?? $account->ledger_group_id,
                'is_active' => (bool) ($item['is_active'] ?? true),
                'type' => $type,
                'subtype' => $item['subtype'] ?? null,
            ], fn ($value) => $value !== null));

            $this->stamp($ref, $account->fresh(), $name, $hash);

            return $this->result(IntegrationRecord::OUTCOME_UPDATED, $ref);
        });
    }

    private function linkOrCreate(IntegrationConnection $connection, IntegrationRef $ref, array $item, string $externalId, string $name, string $hash, bool $force): array
    {
        $type = $item['type'] ?? null;
        $existing = ChartOfAccount::query()
            ->withoutGlobalScope('company')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing !== null) {
            $wrongType = $type !== null && $type !== $existing->type;
            $isParent = $existing->children()->exists();

            if (($wrongType || $isParent) && ! $force) {
                return $this->conflicts->record(
                    $connection, IntegrationRef::ENTITY_LEDGER, $externalId, IntegrationConflict::REASON_TYPE_CHANGE,
                    $isParent
                        ? "'{$existing->name}' already exists here as a group account, which can't take postings."
                        : "'{$existing->name}' already exists here as {$existing->type}, but the source says {$type}.",
                    $item, $hash, $ref,
                );
            }

            if (! $wrongType && ! $isParent) {
                $this->stamp($ref, $existing, $name, $hash);

                return $this->result(IntegrationRecord::OUTCOME_UPDATED, $ref);
            }
        }

        if (! in_array($type, ChartOfAccount::TYPES, true)) {
            throw new IntegrationRecordException(
                IntegrationRecordException::UNMAPPED_GROUP,
                "Ledger '{$name}' has no known account type. Sync its group first, or send type as one of: ".implode(', ', ChartOfAccount::TYPES).'.'
            );
        }

        $subtype = $item['subtype'] ?? null;
        if ($subtype !== null && ! in_array($subtype, ChartOfAccount::SUBTYPES_BY_TYPE[$type], true)) {
            throw new IntegrationRecordException(IntegrationRecordException::INVALID, "Subtype '{$subtype}' doesn't belong to type '{$type}'.");
        }

        $account = ChartOfAccount::create([
            'tenant_id' => $connection->tenant_id,
            'company_id' => $connection->company_id,
            'code' => $this->generatedCode($connection, 'L', $externalId),
            'name' => $name,
            'type' => $type,
            'subtype' => $subtype,
            'normal_balance' => in_array($type, [ChartOfAccount::TYPE_ASSET, ChartOfAccount::TYPE_EXPENSE], true)
                ? ChartOfAccount::BALANCE_DEBIT
                : ChartOfAccount::BALANCE_CREDIT,
            'is_cash_or_bank' => (bool) ($item['is_cash_or_bank'] ?? false),
            'ledger_group_id' => $this->groupIdByName($connection, $item['group_name'] ?? null),
            'is_active' => (bool) ($item['is_active'] ?? true),
        ]);

        $this->stamp($ref, $account, $name, $hash);

        return $this->result(IntegrationRecord::OUTCOME_CREATED, $ref);
    }

    private function stamp(IntegrationRef $ref, ChartOfAccount $account, string $name, string $hash): void
    {
        $ref->update([
            'external_name' => $name,
            'local_type' => IntegrationRef::LOCAL_ACCOUNT,
            'local_id' => $account->id,
            'payload_hash' => $hash,
            'local_hash' => $this->localHash($account),
            'status' => IntegrationRef::STATUS_SYNCED,
            'last_error' => null,
            'last_synced_at' => now(),
        ]);
    }

    public function localHash(ChartOfAccount $account): string
    {
        return hash('sha256', json_encode([$account->name, $account->type, (bool) $account->is_active]));
    }

    private function account(int $id): ?ChartOfAccount
    {
        return ChartOfAccount::query()->withoutGlobalScope('company')->find($id);
    }

    private function groupIdByName(IntegrationConnection $connection, ?string $name): ?int
    {
        return $name === null ? null : $this->findGroupByName($connection, $name)?->id;
    }

    private function findGroupByName(IntegrationConnection $connection, string $name): ?LedgerGroup
    {
        return LedgerGroup::query()->withoutGlobalScope('company')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    /** Deterministic, so a retried create can't produce a second account. */
    private function generatedCode(IntegrationConnection $connection, string $kind, string $externalId): string
    {
        return 'X'.$connection->id.$kind.'-'.substr(hash('sha256', $externalId), 0, 10);
    }

    private function required(array $item, string $key): string
    {
        $value = trim((string) ($item[$key] ?? ''));

        if ($value === '') {
            throw new IntegrationRecordException(IntegrationRecordException::INVALID, "{$key} is required.");
        }

        return mb_substr($value, 0, $key === 'external_id' ? 191 : 255);
    }

    private function result(string $outcome, IntegrationRef $ref): array
    {
        return ['outcome' => $outcome, 'local_type' => $ref->local_type, 'local_id' => $ref->local_id];
    }
}
