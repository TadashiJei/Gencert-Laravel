<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Mail\CertificateGenerated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCertificateEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $certificate;

    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function handle()
    {
        Mail::to($this->certificate->recipient_email)
            ->send(new CertificateGenerated($this->certificate));

        $this->certificate->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
