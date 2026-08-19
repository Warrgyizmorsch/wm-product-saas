<?php

namespace App\Domains\Platform\Exceptions;

use RuntimeException;

class UsageLimitExceededException extends RuntimeException
{
    public static function forUsers(int $limit): self
    {
        return new self("This tenant's plan allows a maximum of {$limit} users. Upgrade the plan or remove an existing user before adding another.");
    }
}
