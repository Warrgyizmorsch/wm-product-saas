<?php

namespace App\Domains\Platform\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoRequest extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'company_name',
        'team_size',
        'modules',
        'preferred_demo_date',
        'preferred_demo_time',
        'preferred_demo_at',
        'notes',
        'status',
        'admin_notes',
        'ip_address',
        'user_agent',
        'converted_tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'preferred_demo_date' => 'date',
            'preferred_demo_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_NEW => [
                'label' => 'New Request',
                'badge' => 'primary',
            ],
            self::STATUS_CONTACTED => [
                'label' => 'Contacted',
                'badge' => 'info',
            ],
            self::STATUS_SCHEDULED => [
                'label' => 'Demo Scheduled',
                'badge' => 'warning',
            ],
            self::STATUS_COMPLETED => [
                'label' => 'Completed',
                'badge' => 'success',
            ],
            self::STATUS_CANCELLED => [
                'label' => 'Cancelled / Junk',
                'badge' => 'secondary',
            ],
        ];
    }

    public function convertedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'converted_tenant_id');
    }
}
