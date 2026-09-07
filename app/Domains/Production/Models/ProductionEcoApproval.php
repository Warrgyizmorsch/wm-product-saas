<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEcoApproval extends BaseModel
{
    use HasFactory;

    protected $table = 'production_eco_approvals';

    protected $fillable = [
        'tenant_id',
        'eco_id',
        'user_id',
        'action',
        'comments',
    ];

    public function eco(): BelongsTo
    {
        return $this->belongsTo(ProductionEco::class, 'eco_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
