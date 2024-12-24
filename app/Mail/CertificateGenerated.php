<?php

namespace App\Mail;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class CertificateGenerated extends Mailable
{
    use Queueable, SerializesModels;

    public $certificate;
    protected $trackingId;

    public function __construct(Certificate $certificate, $trackingId = null)
    {
        $this->certificate = $certificate;
        $this->trackingId = $trackingId;
    }

    public function build()
    {
        $mail = $this->subject('Your Certificate is Ready!')
            ->view('emails.certificate-generated')
            ->with([
                'trackingPixel' => $this->getTrackingPixel(),
                'downloadUrl' => $this->getTrackingUrl('download'),
                'viewUrl' => $this->getTrackingUrl('view'),
            ]);

        if ($this->certificate->file_path && Storage::exists($this->certificate->file_path)) {
            $mail->attach(Storage::path($this->certificate->file_path), [
                'as' => "certificate.{$this->certificate->format}",
                'mime' => $this->getMimeType($this->certificate->format),
            ]);
        }

        return $mail;
    }

    protected function getMimeType($format)
    {
        return match ($format) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }

    protected function getTrackingPixel()
    {
        if (!$this->trackingId) {
            return '';
        }

        $url = URL::signedRoute('email.track.open', ['tracking_id' => $this->trackingId]);
        return '<img src="' . $url . '" alt="" width="1" height="1" style="display:none;">';
    }

    protected function getTrackingUrl($action)
    {
        if (!$this->trackingId) {
            return $action === 'download' 
                ? route('certificates.download', $this->certificate)
                : route('certificates.show', $this->certificate);
        }

        return URL::signedRoute('email.track.click', [
            'tracking_id' => $this->trackingId,
            'action' => $action,
            'certificate' => $this->certificate->id
        ]);
    }
}
