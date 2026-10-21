<?php

namespace App\Domains\Platform\Exceptions;

use RuntimeException;

class UsageLimitExceededException extends RuntimeException
{
    public static function forUsers(int $limit): self
    {
        return new self("This tenant's plan allows a maximum of {$limit} users. Upgrade the plan or remove an existing user before adding another.");
    }

    public static function forStorage(int $limitMb): self
    {
        $cap = $limitMb >= 1024 ? rtrim(rtrim(number_format($limitMb / 1024, 1), '0'), '.').' GB' : $limitMb.' MB';

        return new self("Your plan's {$cap} of file storage is full. Delete files you no longer need or upgrade the plan to upload more.");
    }

    public static function forResource(string $label, int $limit, bool $monthly = false): self
    {
        $cap = $monthly ? "{$limit} {$label} this month" : "{$limit} {$label}";

        return new self("Your plan allows up to {$cap}. Upgrade the plan to add more.");
    }
}
