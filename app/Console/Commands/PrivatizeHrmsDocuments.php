<?php

namespace App\Console\Commands;

use App\Domains\HRMS\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off: move HRMS employee documents uploaded before they were stored
 * privately from the public disk (reachable at /storage/...) to the local disk.
 * Safe to run more than once.
 */
class PrivatizeHrmsDocuments extends Command
{
    protected $signature = 'hrms:privatize-documents {--dry-run : List what would move without moving it}';

    protected $description = 'Move HRMS employee KYC and HR documents from the public disk to the private local disk';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $local = Storage::disk('local');
        $moved = 0;
        $missing = 0;

        Document::withoutGlobalScopes()->withTrashed()->orderBy('id')->chunkById(200, function ($documents) use ($public, $local, &$moved, &$missing) {
            foreach ($documents as $document) {
                $path = (string) $document->file_path;

                if ($path === '' || $local->exists($path)) {
                    continue;
                }

                if (! $public->exists($path)) {
                    $missing++;
                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("would move #{$document->id} {$path}");
                    $moved++;
                    continue;
                }

                $stream = $public->readStream($path);
                $local->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($local->exists($path) && $local->size($path) === $public->size($path)) {
                    $public->delete($path);
                    $moved++;
                }
            }
        });

        $this->info(($this->option('dry-run') ? 'Would move' : 'Moved')." {$moved} document(s); {$missing} missing on the public disk.");

        return self::SUCCESS;
    }
}
