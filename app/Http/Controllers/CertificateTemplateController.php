<?php

namespace App\Http\Controllers;

use App\Http\Requests\CertificateTemplate\{
    StoreCertificateTemplateRequest,
    UpdateCertificateTemplateRequest
};
use App\Models\CertificateTemplate;
use App\Services\CertificateTemplateService;
use Illuminate\Http\Request;

class CertificateTemplateController extends Controller
{
    protected $templateService;

    public function __construct(CertificateTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    public function index(Request $request)
    {
        $templates = CertificateTemplate::with(['settings', 'user'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('is_active', $status === 'active');
            })
            ->latest()
            ->paginate(10);

        return view('certificate-templates.index', compact('templates'));
    }

    public function create()
    {
        return view('certificate-templates.create');
    }

    public function store(StoreCertificateTemplateRequest $request)
    {
        $template = $this->templateService->create($request->validated());

        return redirect()
            ->route('certificate-templates.edit', $template)
            ->with('success', 'Template created successfully.');
    }

    public function edit(CertificateTemplate $template)
    {
        $template->load(['settings']);
        return view('certificate-templates.edit', compact('template'));
    }

    public function update(UpdateCertificateTemplateRequest $request, CertificateTemplate $template)
    {
        $this->templateService->update($template, $request->validated());

        return redirect()
            ->route('certificate-templates.edit', $template)
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(CertificateTemplate $template)
    {
        $this->templateService->delete($template);

        return redirect()
            ->route('certificate-templates.index')
            ->with('success', 'Template deleted successfully.');
    }

    public function preview(CertificateTemplate $template)
    {
        $preview = $this->templateService->generatePreview($template);
        return response()->json(['preview' => $preview]);
    }

    public function duplicate(CertificateTemplate $template)
    {
        $newTemplate = $this->templateService->duplicate($template);

        return redirect()
            ->route('certificate-templates.edit', $newTemplate)
            ->with('success', 'Template duplicated successfully.');
    }

    public function toggleStatus(CertificateTemplate $template)
    {
        $template->update(['is_active' => !$template->is_active]);

        return redirect()
            ->back()
            ->with('success', 'Template status updated successfully.');
    }
}
