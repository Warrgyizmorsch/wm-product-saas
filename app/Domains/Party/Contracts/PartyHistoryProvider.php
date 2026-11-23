<?php

namespace App\Domains\Party\Contracts;

use Illuminate\Support\Collection;

/**
 * A module's contribution to a party's single history timeline.
 *
 * Each row is a plain array: type, type_label, number, title, amount, status,
 * date (Carbon|null), url, permission (the view permission of the source record).
 */
interface PartyHistoryProvider
{
    /** 'customer' or 'vendor'. */
    public function partyType(): string;

    /** @return Collection<int, array<string, mixed>> */
    public function entries(int $partyId): Collection;
}
