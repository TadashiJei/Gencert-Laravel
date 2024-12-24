<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CertificateResource;
use App\Models\Certificate;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $certificates = Certificate::with(['template', 'user'])
            ->when($request->user->cannot('viewAny', Certificate::class), function ($query) use ($request) {
                $query->where('user_id', $request->user->id);
            })
            ->when($request->filled('template_id'), function ($query) use ($request) {
                $query->where('template_id', $request->template_id);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate();

        return CertificateResource::collection($certificates);
    }

    public function store(Request $request)
    {
        $request->validate([
            'template_id' => ['required', 'exists:templates,id'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_email' => ['required', 'email'],
            'format' => ['required', 'in:pdf,png,svg'],
        ]);

        $template = Template::findOrFail($request->template_id);

        if (!$template->is_public && $template->user_id !== $request->user->id) {
            throw ValidationException::withMessages([
                'template_id' => ['You do not have access to this template.'],
            ]);
        }

        $certificate = Certificate::create([
            'template_id' => $request->template_id,
            'user_id' => $request->user->id,
            'recipient_name' => $request->recipient_name,
            'recipient_email' => $request->recipient_email,
            'format' => $request->format,
            'status' => 'pending',
        ]);

        // Queue certificate generation
        // This should be implemented based on your certificate generation logic
        
        return new CertificateResource($certificate);
    }

    public function show(Request $request, Certificate $certificate)
    {
        if ($request->user->cannot('view', $certificate)) {
            abort(403);
        }

        return new CertificateResource($certificate->load(['template', 'user']));
    }

    public function destroy(Request $request, Certificate $certificate)
    {
        if ($request->user->cannot('delete', $certificate)) {
            abort(403);
        }

        if ($certificate->file_path && Storage::exists($certificate->file_path)) {
            Storage::delete($certificate->file_path);
        }

        $certificate->delete();

        return response()->json(['message' => 'Certificate deleted successfully']);
    }

    public function download(Request $request, Certificate $certificate)
    {
        if ($request->user->cannot('view', $certificate)) {
            abort(403);
        }

        if (!$certificate->file_path || !Storage::exists($certificate->file_path)) {
            return response()->json(['message' => 'Certificate file not found'], 404);
        }

        return Storage::download($certificate->file_path, "certificate.{$certificate->format}");
    }
}
