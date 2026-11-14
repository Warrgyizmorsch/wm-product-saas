<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Exceptions\UsageLimitExceededException;
use App\Domains\Platform\Models\TenantFile;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Per-tenant file storage: every upload goes through here so it is checked
 * against the tenant's allowance (tenants.max_storage_mb, else the plan's)
 * and recorded in tenant_files, which usage is summed from.
 *
 * Call sites use the UploadedFile macros (storeTenantFile / storeTenantFileAs,
 * same arguments as store / storeAs) or tenant_storage()->put() for generated
 * content. Deletes aren't hooked — tenant:storage-sync drops records whose
 * file is gone, so usage can only be overstated until the next sync.
 */
class TenantStorage
{
    /** @var array<int, Tenant|null> */
    private array $tenants = [];

    /**
     * $file->store($path, $options), checked and recorded. $options is a disk
     * name or an options array; 'tenant_id' in it overrides the current tenant.
     *
     * @throws UsageLimitExceededException
     */
    public function store(UploadedFile $file, string $path = '', array|string $options = [], ?string $name = null): string|false
    {
        $options = is_string($options) ? ['disk' => $options] : $options;
        $tenantId = $options['tenant_id'] ?? tenant_id();
        unset($options['tenant_id']);

        $size = (int) $file->getSize();
        if ($tenantId !== null) {
            $this->assertCanStore((int) $tenantId, $size);
        }

        $stored = $name === null ? $file->store($path, $options) : $file->storeAs($path, $name, $options);

        if ($stored !== false && $tenantId !== null) {
            $this->record((int) $tenantId, $options['disk'] ?? config('filesystems.default'), $stored, $size);
        }

        return $stored;
    }

    /**
     * Storage::disk($disk)->put($path, $contents), checked and recorded.
     *
     * @throws UsageLimitExceededException
     */
    public function put(string $disk, string $path, mixed $contents, ?int $tenantId = null): bool
    {
        $tenantId ??= tenant_id();
        $size = is_string($contents) ? strlen($contents) : 0;

        if ($tenantId !== null) {
            // Overwriting a recorded file only adds the difference.
            $existing = $this->files()->where('disk', $disk)->where('path', $path)->value('size') ?? 0;
            $this->assertCanStore((int) $tenantId, max(0, $size - $existing));
        }

        $ok = Storage::disk($disk)->put($path, $contents);

        if ($ok && $tenantId !== null) {
            $this->record((int) $tenantId, $disk, $path, $size ?: (int) Storage::disk($disk)->size($path));
        }

        return $ok;
    }

    public function record(int $tenantId, string $disk, string $path, int $size): void
    {
        $this->files()->updateOrCreate(['disk' => $disk, 'path' => $path], ['tenant_id' => $tenantId, 'size' => $size]);
    }

    /** Stop counting a file (call after deleting it, where convenient). */
    public function forget(string $disk, ?string $path): void
    {
        if ($path) {
            $this->files()->where('disk', $disk)->where('path', $path)->delete();
        }
    }

    /** Allowance in bytes, or null when unlimited. The tenant's own column wins over its plan's. */
    public function limitBytes(Tenant $tenant): ?int
    {
        $mb = $tenant->max_storage_mb ?? $tenant->planCatalog?->max_storage_mb;

        return $mb === null ? null : (int) $mb * 1024 * 1024;
    }

    public function usedBytes(Tenant $tenant): int
    {
        return (int) $this->files()->where('tenant_id', $tenant->id)->sum('size');
    }

    /** @return array{used: int, limit: ?int, percent: ?float} bytes */
    public function usage(Tenant $tenant): array
    {
        $limit = $this->limitBytes($tenant);
        $used = $this->usedBytes($tenant);

        return [
            'used' => $used,
            'limit' => $limit,
            'percent' => $limit === null || $limit === 0 ? null : min(100, round(($used / $limit) * 100, 1)),
        ];
    }

    /**
     * @throws UsageLimitExceededException
     */
    public function assertCanStore(int $tenantId, int $bytes): void
    {
        $tenant = $this->tenants[$tenantId] ??= Tenant::query()->with('planCatalog')->find($tenantId);
        $limit = $tenant === null ? null : $this->limitBytes($tenant);

        if ($limit !== null && $this->usedBytes($tenant) + $bytes > $limit) {
            throw UsageLimitExceededException::forStorage(intdiv($limit, 1024 * 1024));
        }
    }

    /** Human size: 1.4 GB, 320 MB, 12 KB. */
    public static function format(?int $bytes): string
    {
        if ($bytes === null) {
            return 'Unlimited';
        }

        foreach (['GB' => 1024 ** 3, 'MB' => 1024 ** 2, 'KB' => 1024] as $unit => $size) {
            if ($bytes >= $size) {
                return rtrim(rtrim(number_format($bytes / $size, 1), '0'), '.').' '.$unit;
            }
        }

        return $bytes.' B';
    }

    private function files()
    {
        return TenantFile::query()->withoutGlobalScope('tenant');
    }
}
