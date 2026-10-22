<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExternalWorkerPayGrade extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_external_worker_pay_grades';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'hourly_rate',
        'description',
        'active',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $model): void {
            $hasActive = DB::table('production_external_worker_allocations')
                ->where('pay_grade_id', $model->id)
                ->where('active', 1)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasActive) {
                throw new InvalidArgumentException("Cannot delete pay grade '{$model->name}' because it has active external worker allocations. Deactivate all referencing allocations before deleting.");
            }
        });
    }

    protected $casts = [
        'hourly_rate' => 'decimal:4',
        'active'      => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function allocations(): HasMany
    {
        return $this->hasMany(ExternalWorkerAllocation::class, 'pay_grade_id');
    }

    // ─── Scopes & Helpers ─────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
