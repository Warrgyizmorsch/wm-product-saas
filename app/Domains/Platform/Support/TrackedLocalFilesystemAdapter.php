<?php

namespace App\Domains\Platform\Support;

use App\Domains\Platform\Models\TenantFile;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Throwable;

/**
 * The local disk driver, plus: deleting, deleting a directory or moving a file
 * updates tenant_files straight away, so tenant storage usage drops the moment
 * a file is removed — whichever module deletes it. Registered for every
 * 'local'-driver disk in AppServiceProvider.
 *
 * Ledger updates never break the file operation itself; if one fails,
 * tenant:storage-sync corrects the count later.
 */
class TrackedLocalFilesystemAdapter extends LocalFilesystemAdapter
{
    private string $trackedDisk = 'local';

    public function trackAs(string $disk): static
    {
        $this->trackedDisk = $disk;

        return $this->diskName($disk);
    }

    public function delete($paths)
    {
        $paths = is_array($paths) ? $paths : func_get_args();
        $deleted = parent::delete($paths);

        $this->ledger(fn ($files) => $files->whereIn('path', array_map(fn ($p) => ltrim((string) $p, '/'), $paths))->delete());

        return $deleted;
    }

    public function deleteDirectory($directory)
    {
        $deleted = parent::deleteDirectory($directory);
        $prefix = trim((string) $directory, '/');

        if ($deleted && $prefix !== '') {
            $this->ledger(fn ($files) => $files->where('path', 'like', addcslashes($prefix, '%_\\').'/%')->delete());
        }

        return $deleted;
    }

    public function move($from, $to)
    {
        $moved = parent::move($from, $to);

        if ($moved) {
            $this->ledger(fn ($files) => $files->where('path', ltrim($from, '/'))->update(['path' => ltrim($to, '/')]));
        }

        return $moved;
    }

    private function ledger(callable $change): void
    {
        try {
            $change(TenantFile::query()->withoutGlobalScope('tenant')->where('disk', $this->trackedDisk));
        } catch (Throwable) {
            // e.g. before the tenant_files migration has run — the nightly sync recounts.
        }
    }
}
