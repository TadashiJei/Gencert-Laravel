<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class EmailTrackingController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function trackOpen(Request $request, $trackingId)
    {
        $this->emailService->trackOpen($trackingId, $request);

        // Return a 1x1 transparent GIF
        $response = Response::make(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200);
        $response->header('Content-Type', 'image/gif');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        
        return $response;
    }

    public function trackClick(Request $request, $trackingId)
    {
        $this->emailService->trackClick($trackingId, $request);

        $certificate = Certificate::findOrFail($request->certificate);
        
        // Redirect to the appropriate URL based on the action
        return redirect()->to(
            $request->action === 'download'
                ? route('certificates.download', $certificate)
                : route('certificates.show', $certificate)
        );
    }

    public function resend(Request $request, Certificate $certificate)
    {
        try {
            $this->emailService->resendEmail($certificate);
            
            return response()->json([
                'success' => true,
                'message' => 'Email has been resent successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend email: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function stats(Certificate $certificate)
    {
        return response()->json(
            $this->emailService->getEmailStats($certificate->id)
        );
    }
}
