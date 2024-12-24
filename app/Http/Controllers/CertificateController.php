namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Template;
use App\Services\CertificateService;
use App\Services\CsvImportService;
use App\Jobs\ProcessBulkCertificates;
use App\Jobs\SendCertificateEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class CertificateController extends Controller
{
    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->middleware('auth');
        $this->certificateService = $certificateService;
    }

    public function index()
    {
        $certificates = Certificate::with(['template', 'user'])
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('certificates.index', compact('certificates'));
    }

    public function create()
    {
        $templates = Template::where(function($query) {
            $query->where('user_id', Auth::id())
                  ->orWhere('is_public', true);
        })->get();

        return view('certificates.create', compact('templates'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:templates,id',
            'recipient_name' => 'required|string|max:255',
            'recipient_email' => 'nullable|email|max:255',
            'data' => 'required|array',
            'format' => 'required|in:pdf,png,svg',
        ]);

        $certificate = Certificate::create([
            'template_id' => $validated['template_id'],
            'user_id' => Auth::id(),
            'recipient_name' => $validated['recipient_name'],
            'recipient_email' => $validated['recipient_email'],
            'data' => $validated['data'],
            'format' => $validated['format'],
        ]);

        try {
            $this->certificateService->generateCertificate($certificate);
            return redirect()->route('certificates.show', $certificate)
                           ->with('success', 'Certificate generated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate certificate: ' . $e->getMessage());
        }
    }

    public function show(Certificate $certificate)
    {
        if ($certificate->user_id !== Auth::id()) {
            abort(403);
        }

        return view('certificates.show', compact('certificate'));
    }

    public function destroy(Certificate $certificate)
    {
        if ($certificate->user_id !== Auth::id()) {
            abort(403);
        }

        if ($certificate->file_path) {
            Storage::delete($certificate->file_path);
        }

        $certificate->delete();
        return redirect()->route('certificates.index')
                        ->with('success', 'Certificate deleted successfully.');
    }

    public function download(Certificate $certificate)
    {
        if ($certificate->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$certificate->file_path || !Storage::exists($certificate->file_path)) {
            return back()->with('error', 'Certificate file not found.');
        }

        return Storage::download(
            $certificate->file_path,
            Str::slug($certificate->recipient_name) . '.' . $certificate->format
        );
    }

    public function bulkGenerate(Request $request, CsvImportService $csvImportService)
    {
        $request->validate([
            'template_id' => 'required|exists:templates,id',
            'format' => 'required|in:pdf,png,svg',
            'data_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        try {
            $result = $csvImportService->process($request->file('data_file'));

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'CSV validation failed',
                    'errors' => $result['errors'],
                ], 422);
            }

            ProcessBulkCertificates::dispatch(
                $request->template_id,
                $request->format,
                $result['records'],
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk certificate generation has been queued.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process CSV file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function preview(Request $request)
    {
        $certificate = null;

        if ($request->route('certificate')) {
            $certificate = $request->route('certificate');
            return response($certificate->getPreviewContent(), 200)
                ->header('Content-Type', $this->getContentType($certificate->format));
        }

        $request->validate([
            'template_id' => 'required|exists:templates,id',
            'recipient_name' => 'required|string|max:255',
            'data' => 'array',
        ]);

        $template = Template::findOrFail($request->template_id);
        $previewContent = $this->certificateService->generatePreview(
            $template,
            $request->recipient_name,
            $request->data ?? []
        );

        return response()->json([
            'success' => true,
            'previewUrl' => 'data:text/html;base64,' . base64_encode($previewContent),
        ]);
    }

    protected function getContentType($format)
    {
        return match ($format) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
