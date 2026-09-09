<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastComment extends Model
{
    protected $fillable = [
        'tenant_id',
        'broadcast_id',
        'employee_id',
        'parent_id',
        'comment_text',
        'is_pinned',
        'status',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class, 'broadcast_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BroadcastComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(BroadcastComment::class, 'parent_id')->with('employee');
    }
}
