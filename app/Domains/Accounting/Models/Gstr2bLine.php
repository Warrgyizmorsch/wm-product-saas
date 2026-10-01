<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Domains\Purchase\Models\VendorBill;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One supplier document reported in GSTR-2B, and how it matched the books. */
class Gstr2bLine extends BaseModel
{
    public const MATCHED = 'matched';
    public const MISMATCH = 'mismatch';
    public const MISSING_IN_BOOKS = 'missing_in_books';
    public const NOTE = 'note'; // credit/debit notes — shown for manual review

    protected $table = 'gstr2b_lines';

    protected $fillable = [
        'tenant_id', 'gstr2b_import_id', 'section', 'document_type', 'supplier_gstin', 'supplier_name',
        'document_number', 'normalized_number', 'document_date', 'supplier_period', 'supplier_filed_on',
        'place_of_supply', 'reverse_charge', 'itc_available', 'itc_reason', 'taxable_value', 'igst', 'cgst',
        'sgst', 'cess', 'total_value', 'match_status', 'vendor_bill_id', 'differences',
    ];

    protected $casts = [
        'document_date' => 'date',
        'supplier_filed_on' => 'date',
        'reverse_charge' => 'boolean',
        'taxable_value' => 'float',
        'igst' => 'float',
        'cgst' => 'float',
        'sgst' => 'float',
        'cess' => 'float',
        'total_value' => 'float',
        'differences' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Gstr2bImport::class, 'gstr2b_import_id');
    }

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id')->withoutGlobalScopes();
    }

    public function totalTax(): float
    {
        return round($this->igst + $this->cgst + $this->sgst + $this->cess, 2);
    }
}
