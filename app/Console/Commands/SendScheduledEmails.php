<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledEmails extends Command
{
    protected $signature = 'emails:send-scheduled';
    protected $description = 'Send scheduled certificate emails';

    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    public function handle()
    {
        $this->info('Starting to process scheduled emails...');

        try {
            // Get certificates that need to be sent
            $certificates = Certificate::where('status', 'generated')
                ->whereNull('sent_at')
                ->get();

            if ($certificates->isEmpty()) {
                $this->info('No scheduled emails to send.');
                return 0;
            }

            $this->info("Found {$certificates->count()} certificates to send.");
            $sent = 0;
            $failed = 0;

            foreach ($certificates as $certificate) {
                try {
                    $this->emailService->sendCertificate($certificate);
                    $sent++;
                    
                    $this->info("Sent certificate to: {$certificate->recipient_email}");
                } catch (\Exception $e) {
                    $failed++;
                    Log::error("Failed to send certificate {$certificate->id}: " . $e->getMessage());
                    $this->error("Failed to send to {$certificate->recipient_email}: {$e->getMessage()}");
                }
            }

            $this->info("Completed processing scheduled emails.");
            $this->info("Successfully sent: {$sent}");
            $this->info("Failed: {$failed}");

            return 0;
        } catch (\Exception $e) {
            Log::error("Error in scheduled email command: " . $e->getMessage());
            $this->error("An error occurred: {$e->getMessage()}");
            return 1;
        }
    }
}
