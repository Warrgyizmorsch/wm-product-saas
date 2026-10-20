<?php

namespace App\Domains\Platform\Exceptions;

use RuntimeException;

class UsageLimitExceededException extends RuntimeException
{
    public static function forUsers(int $limit): self
    {
        return new self("This tenant's plan allows a maximum of {$limit} users. Upgrade the plan or remove an existing user before adding another.");
    }

    public static function forResource(string $label, int $limit, bool $monthly = false): self
    {
        $cap = $monthly ? "{$limit} {$label} this month" : "{$limit} {$label}";

        return new self("Your plan allows up to {$cap}. Upgrade the plan to add more.");
    }
}
