<?php

namespace App\Models;

use App\Core\Database\BaseModel;

/**
 * Append-only audit row for customer / vendor verification (FRS 2.3).
 */
class PartyVerificationLog extends BaseModel
{
    protected $table = 'party_verification_logs';

    protected $fillable = [
        'tenant_id',
        'party_type',
        'party_id',
        'action',
        'actor_id',
        'from_status',
        'to_status',
        'remarks',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
