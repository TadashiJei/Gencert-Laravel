<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\EmailLog;
use App\Mail\CertificateGenerated;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailService
{
    public function sendCertificate(Certificate $certificate, $options = [])
    {
        $trackingId = Str::uuid();
        
        // Create email log entry
        $emailLog = EmailLog::create([
            'certificate_id' => $certificate->id,
            'recipient_email' => $certificate->recipient_email,
            'subject' => 'Your Certificate is Ready!',
            'status' => 'queued',
            'tracking_id' => $trackingId,
        ]);

        try {
            // Send email with tracking
            $email = new CertificateGenerated($certificate, $trackingId);
            
            if (!empty($options['schedule_time'])) {
                Mail::to($certificate->recipient_email)
                    ->later($options['schedule_time'], $email);
                
                return $emailLog;
            }

            Mail::to($certificate->recipient_email)
                ->queue($email);

            $emailLog->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return $emailLog;
        } catch (\Exception $e) {
            $emailLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function scheduleReminder(Certificate $certificate, $scheduleTime)
    {
        return $this->sendCertificate($certificate, [
            'schedule_time' => $scheduleTime,
        ]);
    }

    public function trackOpen($trackingId, $request)
    {
        $emailLog = EmailLog::where('tracking_id', $trackingId)->first();
        
        if ($emailLog) {
            $emailLog->markAsOpened(
                $request->ip(),
                $request->userAgent()
            );
        }
    }

    public function trackClick($trackingId, $request)
    {
        $emailLog = EmailLog::where('tracking_id', $trackingId)->first();
        
        if ($emailLog) {
            $emailLog->markAsClicked(
                $request->ip(),
                $request->userAgent()
            );
        }
    }

    public function getEmailStats($certificateId)
    {
        $log = EmailLog::where('certificate_id', $certificateId)
            ->select([
                'status',
                'sent_at',
                'opened_at',
                'clicked_at',
                'error_message',
            ])
            ->first();

        if (!$log) {
            return [
                'status' => 'not_sent',
                'sent_at' => null,
                'opened_at' => null,
                'clicked_at' => null,
                'error_message' => null,
            ];
        }

        return $log->toArray();
    }

    public function resendEmail(Certificate $certificate)
    {
        return $this->sendCertificate($certificate);
    }
}
