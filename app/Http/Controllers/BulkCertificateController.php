<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkCertificate\StoreBulkCertificateRequest;
use App\Models\BulkCertificateJob;
use App\Models\CertificateTemplate;
use App\Services\BulkCertificateService;
use Illuminate\Http\Request;

class BulkCertificateController extends Controller
{
    protected $bulkService;

    public function __construct(BulkCertificateService $bulkService)
    {
        $this->bulkService = $bulkService;
    }

    public function create()
    {
        $templates = CertificateTemplate::active()->get();
        return view('bulk-certificates.create', compact('templates'));
    }

    public function store(StoreBulkCertificateRequest $request)
    {
        $job = $this->bulkService->create($request->validated());

        // Dispatch job to process bulk certificates
        dispatch(new \App\Jobs\ProcessBulkCertificates($job));

        return redirect()
            ->route('bulk-certificates.show', $job)
            ->with('success', 'Bulk certificate generation started.');
    }

    public function show(BulkCertificateJob $job)
    {
        return view('bulk-certificates.show', compact('job'));
    }

    public function downloadTemplate(CertificateTemplate $template)
    {
        return $this->bulkService->generateTemplateFile($template);
    }

    public function status(BulkCertificateJob $job)
    {
        return response()->json([
            'status' => $job->status,
            'progress' => $job->getProgress(),
            'processed' => $job->processed_records,
            'failed' => $job->failed_records,
            'total' => $job->total_records,
            'errors' => $job->error_log,
        ]);
    }

    public function downloadErrors(BulkCertificateJob $job)
    {
        return $this->bulkService->generateErrorReport($job);
    }

    public function cancel(BulkCertificateJob $job)
    {
        $this->bulkService->cancel($job);

        return redirect()
            ->route('bulk-certificates.show', $job)
            ->with('success', 'Job cancelled successfully.');
    }
}
