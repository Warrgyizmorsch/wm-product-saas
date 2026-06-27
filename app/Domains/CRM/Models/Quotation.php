<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends BaseModel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected function quotationNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => 'QT-' . $value,
            set: fn ($value) => str_replace('QT-', '', $value),
        );
    }

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'sales_person_id',
        'quotation_number',
        'quotation_date',
        'expiry_date',
        'status',
        'subtotal',
        'tax',
        'discount',
        'total_amount',
        'terms_conditions',
        'notes',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'expiry_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sales_person_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }
}
