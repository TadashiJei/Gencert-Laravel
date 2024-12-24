<?php

namespace App\Jobs;

use App\Models\Template;
use App\Services\CertificateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBulkCertificates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $templateId;
    protected $format;
    protected $recipientsData;
    protected $userId;

    public function __construct($templateId, $format, array $recipientsData, $userId)
    {
        $this->templateId = $templateId;
        $this->format = $format;
        $this->recipientsData = $recipientsData;
        $this->userId = $userId;
    }

    public function handle(CertificateService $certificateService)
    {
        $template = Template::findOrFail($this->templateId);
        $totalRecipients = count($this->recipientsData);
        $processedCount = 0;

        Log::info("Starting bulk certificate generation for {$totalRecipients} recipients");

        foreach ($this->recipientsData as $recipientData) {
            try {
                $certificate = $certificateService->generate(
                    $template,
                    $this->userId,
                    $recipientData['name'],
                    $recipientData['email'],
                    $recipientData['data'] ?? [],
                    $this->format
                );

                if ($certificate) {
                    $processedCount++;
                    // Send email notification
                    SendCertificateEmail::dispatch($certificate);
                }
            } catch (\Exception $e) {
                Log::error("Error processing certificate for {$recipientData['email']}: " . $e->getMessage());
                continue;
            }
        }

        Log::info("Completed bulk certificate generation. Processed: {$processedCount}/{$totalRecipients}");
    }
}
