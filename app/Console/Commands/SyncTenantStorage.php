<?php

namespace App\Console\Commands;

use App\Domains\Platform\Models\TenantFile;
use App\Domains\Platform\Services\TenantStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Reconciles tenant_files with the disks (see config/tenant_storage.php):
 * drops records whose file is gone (deletes aren't hooked) and records files
 * uploaded before tracking existed. Safe to re-run; scheduled daily.
 */
class SyncTenantStorage extends Command
{
    protected $signature = 'tenant:storage-sync
        {--prune-only : Only drop records of deleted files}';

    protected $description = 'Recount tenant file storage: drop deleted files, record untracked ones';

    public function handle(TenantStorage $storage): int
    {
        $pruned = 0;
        TenantFile::query()->withoutGlobalScope('tenant')->chunkById(500, function ($files) use (&$pruned) {
            foreach ($files as $file) {
                if (! Storage::disk($file->disk)->exists($file->path)) {
                    $file->delete();
                    $pruned++;
                }
            }
        });
        $this->info("Dropped {$pruned} record(s) of deleted files.");

        if ($this->option('prune-only')) {
            return self::SUCCESS;
        }

        $added = 0;
        foreach (config('tenant_storage.sources', []) as $source) {
            $added += $this->backfill($storage, $source);
        }
        $this->info("Recorded {$added} untracked file(s).");

        return self::SUCCESS;
    }

    /** @param array{table: string, column: string, json?: bool, parent?: array{table: string, key: string}} $source */
    private function backfill(TenantStorage $storage, array $source): int
    {
        ['table' => $table, 'column' => $column] = $source;
        $parent = $source['parent'] ?? null;

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        if ($parent !== null) {
            if (! Schema::hasTable($parent['table']) || ! Schema::hasColumn($parent['table'], 'tenant_id')) {
                $this->warn("Skipped {$table}.{$column}: {$parent['table']} has no tenant_id.");

                return 0;
            }
            $query = DB::table($table)
                ->join($parent['table'], "{$parent['table']}.id", '=', "{$table}.{$parent['key']}")
                ->select("{$table}.id", "{$table}.{$column} as stored_path", "{$parent['table']}.tenant_id");
        } elseif (Schema::hasColumn($table, 'tenant_id')) {
            $query = DB::table($table)->select('id', "{$column} as stored_path", 'tenant_id');
        } else {
            $this->warn("Skipped {$table}.{$column}: no tenant_id.");

            return 0;
        }

        $added = 0;
        $query->whereNotNull("{$table}.{$column}")->where("{$table}.{$column}", '!=', '')
            ->orderBy("{$table}.id")
            ->chunk(500, function ($rows) use ($storage, $source, &$added) {
                foreach ($rows as $row) {
                    if ($row->tenant_id === null) {
                        continue;
                    }

                    $paths = ($source['json'] ?? false) ? (array) json_decode($row->stored_path, true) : [$row->stored_path];

                    foreach ($paths as $path) {
                        $path = is_string($path) ? $this->normalize($path) : null;
                        if ($path === null) {
                            continue;
                        }

                        foreach (config('tenant_storage.disks', ['public']) as $disk) {
                            if (! Storage::disk($disk)->exists($path)) {
                                continue;
                            }
                            $known = TenantFile::query()->withoutGlobalScope('tenant')->where('disk', $disk)->where('path', $path)->exists();
                            if (! $known) {
                                $storage->record((int) $row->tenant_id, $disk, $path, (int) Storage::disk($disk)->size($path));
                                $added++;
                            }
                            break;
                        }
                    }
                }
            });

        return $added;
    }

    /** Stored value → disk-relative path; null for URLs and data blobs. */
    private function normalize(string $path): ?string
    {
        $path = trim($path);

        if ($path === '' || str_starts_with($path, 'data:') || preg_match('#^https?://#i', $path)) {
            return null;
        }

        return ltrim(preg_replace('#^/?storage/#', '', $path), '/');
    }
}
