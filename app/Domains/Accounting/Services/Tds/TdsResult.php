<?php

namespace App\Domains\Accounting\Services\Tds;

/** The TDS one document carries. amount is 0 when it falls below the threshold. */
final class TdsResult
{
    public function __construct(
        public readonly int $sectionId,
        public readonly float $base,
        public readonly float $catchupBase,
        public readonly float $rate,
        public readonly float $amount,
        public readonly string $reason,
    ) {
    }
}
