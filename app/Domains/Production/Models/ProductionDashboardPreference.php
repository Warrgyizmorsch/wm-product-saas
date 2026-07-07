<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionDashboardPreference extends BaseModel
{
    use HasFactory;

    protected $table = 'production_dashboard_preferences';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'dashboard_type',
        'widgets',
        'default_filters',
        'layout',
    ];

    protected $casts = [
        'widgets'         => 'array',
        'default_filters' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
