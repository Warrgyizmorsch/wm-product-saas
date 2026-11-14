<?php

namespace App\Domains\Accounting\Services\Integration;

use RuntimeException;

class IdempotencyKeyReusedException extends RuntimeException
{
}
