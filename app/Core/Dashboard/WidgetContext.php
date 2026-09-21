<?php

namespace App\Core\Dashboard;

use App\Models\User;

/** What a widget's data callback may depend on: who is looking, at which tenant, over what period. */
final class WidgetContext
{
    public const DEFAULT_LIMIT = 8;

    public function __construct(
        public readonly User $user,
        public readonly int $tenantId,
        public readonly Period $period,
        /** Every company and branch of the tenant, instead of the selected one. */
        public readonly bool $consolidated = false,
        /** How many rows a list widget shows. */
        public readonly int $limit = self::DEFAULT_LIMIT,
        /** Cost-centre filter of the Accounting dashboard. */
        public readonly ?int $costCenterId = null,
    ) {
    }
}
