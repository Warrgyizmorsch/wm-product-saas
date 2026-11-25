<?php

namespace App\Domains\Production\Services;

use InvalidArgumentException;

/**
 * UR-12 (M-24): thrown when an order is released / scheduled while none of its planned material has been issued to production yet.
 * It is a SOFT stop: the caller asks the user "The materials are not released. Do you still want to schedule it?" and, on OK,
 * releases again with $force = true (even with 0 issued).
 */
class MaterialsNotReleasedException extends InvalidArgumentException
{
}
