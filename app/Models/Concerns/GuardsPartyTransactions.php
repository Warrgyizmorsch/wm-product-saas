<?php

namespace App\Models\Concerns;

use App\Services\PartyTransactionGuard;

/**
 * Refuse to CREATE a document for an inactive or unverified customer/vendor
 * (FRS 2.3, C-16). The model lists the party columns it guards:
 *
 *     protected function guardedParties(): array
 *     {
 *         return ['customer_id' => ['customer', 'sales order']];
 *     }
 *
 * Only creation is checked, so existing documents can still be edited, received,
 * billed and paid after a party is made inactive.
 */
trait GuardsPartyTransactions
{
    public static function bootGuardsPartyTransactions(): void
    {
        static::creating(function ($document): void {
            $guard = app(PartyTransactionGuard::class);

            if ($guard->enabled()) {
                foreach ($document->guardedParties() as $field => [$type, $label]) {
                    $guard->assertCanTransact($type, $document->getAttribute($field), $document->tenant_id, $label, $field);
                }
            }

            foreach ($document->guardedPartyBranches() as $field => [$type, $partyField, $label]) {
                $expectedPartyId = $partyField ? $document->getAttribute($partyField) : null;
                $guard->assertCanUseBranch($type, $expectedPartyId, $document->getAttribute($field), $document->tenant_id, $label, $field);
            }
        });
    }

    /** @return array<string, array{0: string, 1: string}> column => [party type, document label] */
    abstract protected function guardedParties(): array;

    /**
     * Override on document models that carry PartyBranch foreign keys.
     *
     * @return array<string, array{0: 'customer'|'vendor', 1: ?string, 2: string}> branch_col => [expected party type, party id col on document (or null), document label]
     */
    protected function guardedPartyBranches(): array
    {
        return [];
    }
}
