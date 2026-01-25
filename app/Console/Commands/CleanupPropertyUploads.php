<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class CleanupPropertyUploads extends Command
{
    protected $signature = 'property:cleanup-uploads {--hours=24 : Delete files older than this many hours}';

    protected $description = 'Clean up orphaned temporary property upload files';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = Carbon::now()->subHours($hours);
        $deletedCount = 0;
        $deletedSize = 0;

        $disk = Storage::disk('local');
        $directory = 'temp-property-uploads';

        if (! $disk->exists($directory)) {
            $this->info('No temp uploads directory found.');

            return self::SUCCESS;
        }

        $files = $disk->files($directory);

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));

            if ($lastModified->lt($cutoff)) {
                $size = $disk->size($file);
                $disk->delete($file);
                $deletedCount++;
                $deletedSize += $size;
            }
        }

        if ($deletedCount > 0) {
            $sizeFormatted = number_format($deletedSize / 1024 / 1024, 2);
            $this->info("Deleted {$deletedCount} orphaned files ({$sizeFormatted} MB).");
        } else {
            $this->info('No orphaned files found.');
        }

        return self::SUCCESS;
    }
}
