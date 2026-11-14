<?php

namespace App\Domains\Accounting\Services\Tds;

use App\Domains\Inventory\Models\Vendor;

/**
 * What other modules (Purchase) call to know how much TDS a vendor bill
 * carries, without reading the Accounting TDS tables themselves.
 */
interface TdsCalculatorInterface
{
    /**
     * @param float $base  bill value before GST (TDS is never on the GST part)
     * @param ?int $sectionId  section chosen on the bill; defaults to the vendor's
     * @param ?int $excludeBillId  the bill itself, when recalculating it
     */
    public function forBill(Vendor $vendor, float $base, \DateTimeInterface|string $date, ?int $sectionId = null, ?int $excludeBillId = null): ?TdsResult;
}
