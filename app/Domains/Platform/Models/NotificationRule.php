<?php

namespace App\Domains\Platform\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRule extends BaseModel
{
    use HasFactory, BelongsToCompany;

    protected $table = 'notification_rules';

    public bool $sharedAcrossCompanies = true;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'event_key',
        'module',
        'description',
        'recipient_roles',
        'recipient_user_ids',
        'notify_creator',
        'notify_assigned_user',
        'title_template',
        'body_template',
        'action_route',
        'icon_class',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'recipient_roles' => 'array',
        'recipient_user_ids' => 'array',
        'notify_creator' => 'boolean',
        'notify_assigned_user' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent(Builder $query, string $eventKey): Builder
    {
        return $query->where('event_key', $eventKey);
    }

    /**
     * Render a template string replacing {{variable_name}} with actual data.
     */
    public static function interpolate(string $template, array $data): string
    {
        foreach ($data as $key => $val) {
            if (is_scalar($val) || is_null($val)) {
                $template = str_replace(['{{' . $key . '}}', '{{ ' . $key . ' }}'], (string) ($val ?? ''), $template);
            }
        }
        return $template;
    }

    public function renderTitle(array $data): string
    {
        return self::interpolate($this->title_template, $data);
    }

    public function renderBody(array $data): string
    {
        return self::interpolate($this->body_template, $data);
    }
}
