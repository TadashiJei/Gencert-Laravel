<?php

namespace App\Http\Controllers;

use App\Http\Requests\Signature\{
    StoreSignatureRequest,
    UpdateSignatureRequest
};
use App\Models\SignatureSetting;
use App\Services\SignatureService;
use Illuminate\Http\Request;

class SignatureController extends Controller
{
    protected $signatureService;

    public function __construct(SignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
    }

    public function index()
    {
        $signatures = SignatureSetting::where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('signatures.index', compact('signatures'));
    }

    public function store(StoreSignatureRequest $request)
    {
        $signature = $this->signatureService->create($request->validated());

        return redirect()
            ->route('signatures.index')
            ->with('success', 'Signature uploaded successfully.');
    }

    public function update(UpdateSignatureRequest $request, SignatureSetting $signature)
    {
        $this->signatureService->update($signature, $request->validated());

        return redirect()
            ->route('signatures.index')
            ->with('success', 'Signature updated successfully.');
    }

    public function destroy(SignatureSetting $signature)
    {
        $this->signatureService->delete($signature);

        return redirect()
            ->route('signatures.index')
            ->with('success', 'Signature deleted successfully.');
    }

    public function makeDefault(SignatureSetting $signature)
    {
        $signature->makeDefault();

        return redirect()
            ->route('signatures.index')
            ->with('success', 'Default signature updated successfully.');
    }

    public function updatePosition(Request $request, SignatureSetting $signature)
    {
        $validated = $request->validate([
            'position' => ['required', 'array'],
            'position.x' => ['required', 'numeric'],
            'position.y' => ['required', 'numeric'],
        ]);

        $signature->update(['position' => $validated['position']]);

        return response()->json(['message' => 'Position updated successfully']);
    }
}
