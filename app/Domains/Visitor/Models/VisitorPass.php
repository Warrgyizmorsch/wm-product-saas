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
        'visitor_type',
        'entry_type',
        'status',
        'expected_arrival_at',
        'arrived_at',
        'host_notified_at',
        'check_in_at',
        'meeting_started_at',
        'check_out_at',
        'expected_duration_minutes',
        'accompanying_count',
        'accompanying_names',
        'vehicle_type',
        'vehicle_number',
        'parking_slot',
        'id_verification_status',
        'id_verified_by',
        'qr_token',
        'badge_printed',
        'badge_number',
        'badge_returned',
        'badge_returned_at',
        'nda_safety_acknowledged',
        'restricted_area_access',
        'gate_pass_reference',
        'source_module',
        'source_reference_id',
        'source_reference_no',
        'rejection_reason',
        'denied_reason',
        'incident_reported',
        'incident_details',
        'gate_number',
        'fee_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_arrival_at'       => 'datetime',
            'arrived_at'                => 'datetime',
            'host_notified_at'          => 'datetime',
            'check_in_at'               => 'datetime',
            'meeting_started_at'        => 'datetime',
            'check_out_at'              => 'datetime',
            'badge_returned_at'         => 'datetime',
            'badge_printed'             => 'boolean',
            'badge_returned'            => 'boolean',
            'nda_safety_acknowledged'   => 'boolean',
            'restricted_area_access'    => 'boolean',
            'incident_reported'         => 'boolean',
            'expected_duration_minutes' => 'integer',
            'accompanying_count'        => 'integer',
            'fee_amount'                => 'decimal:2',
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

    public function linkedLead(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CRM\Models\Lead::class, 'source_reference_id');
    }

    public function isOverstayed(): bool
    {
        if ($this->status === 'Checked-Out' || !$this->check_in_at) {
            return false;
        }
        $expectedMinutes = $this->expected_duration_minutes ?: 60;
        return now()->diffInMinutes($this->check_in_at) > $expectedMinutes;
    }
}
