<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldCertificates extends Command
{
    protected $signature = 'certificates:cleanup {--days=30 : Number of days to keep certificates}';
    protected $description = 'Clean up old certificates and their files';

    public function handle()
    {
        $days = $this->option('days');
        $date = now()->subDays($days);

        $this->info("Cleaning up certificates older than {$days} days...");

        $certificates = Certificate::where('created_at', '<', $date)->get();
        $totalCount = $certificates->count();
        $deletedCount = 0;

        if ($totalCount === 0) {
            $this->info('No old certificates found.');
            return 0;
        }

        foreach ($certificates as $certificate) {
            if ($certificate->file_path && Storage::exists($certificate->file_path)) {
                Storage::delete($certificate->file_path);
            }
            $certificate->delete();
            $deletedCount++;

            if ($deletedCount % 100 === 0) {
                $this->info("Processed {$deletedCount}/{$totalCount} certificates...");
            }
        }

        $this->info("Successfully deleted {$deletedCount} certificates.");
        return 0;
    }
}
