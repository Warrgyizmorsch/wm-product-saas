<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfileUpdateRequest extends BaseModel
{
    protected $table = 'employee_profile_update_requests';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'user_id',
        'changes',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function getChangesAttribute($value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($decoded)) {
            return [];
        }

        $emp = $this->employee;
        $formatted = [];

        foreach ($decoded as $key => $data) {
            if (is_array($data)) {
                $old = $data['old'] ?? null;
                $new = $data['new'] ?? ($data['value'] ?? '—');
                if ((empty($old) || $old === '—') && $emp) {
                    $rawOld = $emp->{$key} ?? null;
                    if (!empty($rawOld)) {
                        $old = (string) $rawOld;
                    }
                }
                $formatted[$key] = [
                    'old'      => !empty($old) ? $old : '—',
                    'new'      => !empty($new) ? $new : '—',
                    'label'    => $data['label'] ?? ucwords(str_replace('_', ' ', (string) $key)),
                    'is_image' => !empty($data['is_image']) || $key === 'photo',
                ];
            } else {
                $old = $emp ? ($emp->{$key} ?? '—') : '—';
                $formatted[$key] = [
                    'old'      => !empty($old) ? (string) $old : '—',
                    'new'      => !empty($data) ? (string) $data : '—',
                    'label'    => ucwords(str_replace('_', ' ', (string) $key)),
                    'is_image' => $key === 'photo',
                ];
            }
        }

        return $formatted;
    }

    public function getFormattedChangesAttribute(): array
    {
        return $this->changes;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }
}
