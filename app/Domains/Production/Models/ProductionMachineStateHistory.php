<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMachineStateHistory extends BaseModel
{
    use HasFactory;

    protected $table = 'production_machine_state_histories';

    protected $fillable = [
        'tenant_id',
        'machine_id',
        'state',
        'reason',
        'started_at',
        'ended_at',
        'duration_seconds',
        'changed_by',
        'remarks',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
