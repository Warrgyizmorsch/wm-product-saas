<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBomApproval extends BaseModel
{
    protected $table = 'production_bom_approvals';

    // Disabling updated_at since it is a log table with useCurrent() on created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'bom_id',
        'user_id',
        'action',
        'comments',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'bom_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
