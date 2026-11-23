<?php

namespace App\Domains\Party\Services;

use App\Domains\Party\Contracts\PartyHistoryProvider;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PartyHistoryService
{
    /** @param iterable<PartyHistoryProvider> $providers */
    public function __construct(
        private iterable $providers,
        private AccessService $access
    ) {
    }

    /**
     * Merged, newest-first history of one party. Rows whose source record the user
     * may not view are dropped.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function entries(string $partyType, int $partyId, ?User $user): Collection
    {
        $allowed = [];
        $rows = collect();

        foreach ($this->providers as $provider) {
            if ($provider->partyType() !== $partyType) {
                continue;
            }
            foreach ($provider->entries($partyId) as $row) {
                $perm = $row['permission'] ?? null;
                if ($perm && $user) {
                    $allowed[$perm] ??= $this->access->allows($user, $perm, ['tenant_id' => $user->tenant_id]);
                    if (! $allowed[$perm]) {
                        continue;
                    }
                }
                $rows->push($row);
            }
        }

        return $rows
            ->sortByDesc(fn ($r) => optional($r['date'] ?? null)->getTimestamp() ?? 0)
            ->values();
    }

    public function paginate(Collection $entries, int $page, int $perPage = 15, array $query = []): LengthAwarePaginator
    {
        return (new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'history_page', 'query' => $query]
        ))->fragment('history-pane');
    }
}
