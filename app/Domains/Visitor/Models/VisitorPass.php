<?php

namespace App\Domains\Visitor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;

class VisitorPass extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'visitor_passes';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'pass_number',
        'visitor_id',
        'host_user_id',
        'department_id',
        'purpose',
        'entry_type',
        'status',
        'expected_arrival_at',
        'check_in_at',
        'check_out_at',
        'qr_token',
        'badge_printed',
        'rejection_reason',
        'gate_number',
        'fee_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_arrival_at' => 'datetime',
            'check_in_at'         => 'datetime',
            'check_out_at'        => 'datetime',
            'badge_printed'       => 'boolean',
            'fee_amount'          => 'decimal:2',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class, 'visitor_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function belongings(): HasMany
    {
        return $this->hasMany(VisitorBelonging::class, 'visitor_pass_id');
    }
}
