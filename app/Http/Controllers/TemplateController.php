namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $templates = Template::where(function($query) {
            $query->where('user_id', Auth::id())
                  ->orWhere('is_public', true);
        })->with(['user', 'versions' => function($query) {
            $query->latest()->first();
        }])->latest()->paginate(10);

        return view('templates.index', compact('templates'));
    }

    public function create()
    {
        return view('templates.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'is_public' => 'boolean',
            'content' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $template = Template::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'is_public' => $validated['is_public'] ?? false,
                'user_id' => Auth::id(),
            ]);

            $template->versions()->create([
                'content' => $validated['content'],
                'version' => '1.0.0',
                'variables' => $validated['variables'] ?? [],
                'created_by' => Auth::id(),
            ]);

            DB::commit();
            return redirect()->route('templates.show', $template)
                           ->with('success', 'Template created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create template.');
        }
    }

    public function show(Template $template)
    {
        if (!$template->is_public && $template->user_id !== Auth::id()) {
            abort(403);
        }

        $template->load(['versions' => function($query) {
            $query->with('creator')->latest();
        }, 'user']);

        return view('templates.show', compact('template'));
    }

    public function edit(Template $template)
    {
        if ($template->user_id !== Auth::id()) {
            abort(403);
        }

        return view('templates.edit', compact('template'));
    }

    public function update(Request $request, Template $template)
    {
        if ($template->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'is_public' => 'boolean',
            'content' => 'required|string',
            'change_notes' => 'nullable|string',
            'variables' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $template->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'is_public' => $validated['is_public'] ?? false,
            ]);

            // Create new version
            $latestVersion = $template->latestVersion();
            $newVersion = $this->incrementVersion($latestVersion->version);
            
            $template->versions()->create([
                'content' => $validated['content'],
                'version' => $newVersion,
                'change_notes' => $validated['change_notes'],
                'variables' => $validated['variables'] ?? [],
                'created_by' => Auth::id(),
            ]);

            DB::commit();
            return redirect()->route('templates.show', $template)
                           ->with('success', 'Template updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update template.');
        }
    }

    public function destroy(Template $template)
    {
        if ($template->user_id !== Auth::id()) {
            abort(403);
        }

        $template->delete();
        return redirect()->route('templates.index')
                        ->with('success', 'Template deleted successfully.');
    }

    private function incrementVersion($version)
    {
        $parts = explode('.', $version);
        $parts[2] = (int)$parts[2] + 1;
        return implode('.', $parts);
    }
}
