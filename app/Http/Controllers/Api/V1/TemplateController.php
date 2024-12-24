<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TemplateResource;
use App\Models\Template;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = Template::with('user')
            ->withCount('certificates')
            ->when($request->user->cannot('viewAny', Template::class), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('user_id', $request->user->id)
                        ->orWhere('is_public', true);
                });
            })
            ->latest()
            ->paginate();

        return TemplateResource::collection($templates);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'is_public' => ['boolean'],
        ]);

        $template = Template::create([
            'user_id' => $request->user->id,
            'name' => $request->name,
            'description' => $request->description,
            'content' => $request->content,
            'is_public' => $request->is_public ?? false,
        ]);

        return new TemplateResource($template);
    }

    public function show(Request $request, Template $template)
    {
        if (!$template->is_public && $request->user->cannot('view', $template)) {
            abort(403);
        }

        return new TemplateResource($template->load('user')->loadCount('certificates'));
    }

    public function update(Request $request, Template $template)
    {
        if ($request->user->cannot('update', $template)) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'is_public' => ['boolean'],
        ]);

        $template->update([
            'name' => $request->name,
            'description' => $request->description,
            'content' => $request->content,
            'is_public' => $request->is_public ?? $template->is_public,
        ]);

        return new TemplateResource($template);
    }

    public function destroy(Request $request, Template $template)
    {
        if ($request->user->cannot('delete', $template)) {
            abort(403);
        }

        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }
}
