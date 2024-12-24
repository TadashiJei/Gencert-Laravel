<?php

namespace App\Services;

use App\Models\SignatureSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class SignatureService
{
    public function create(array $data)
    {
        /** @var UploadedFile $file */
        $file = $data['signature'];
        $path = $file->store('signatures', 'public');

        $signature = SignatureSetting::create([
            'user_id' => auth()->id(),
            'title' => $data['title'],
            'signature_path' => $path,
            'mime_type' => $file->getMimeType(),
            'is_default' => $data['is_default'] ?? false,
            'position' => $data['position'] ?? ['x' => 0, 'y' => 0],
        ]);

        if ($signature->is_default) {
            $signature->makeDefault();
        }

        return $signature;
    }

    public function update(SignatureSetting $signature, array $data)
    {
        $updateData = [
            'title' => $data['title'],
            'position' => $data['position'] ?? $signature->position,
        ];

        if (isset($data['signature'])) {
            // Delete old file
            Storage::disk('public')->delete($signature->signature_path);

            /** @var UploadedFile $file */
            $file = $data['signature'];
            $updateData['signature_path'] = $file->store('signatures', 'public');
            $updateData['mime_type'] = $file->getMimeType();
        }

        $signature->update($updateData);

        if (isset($data['is_default']) && $data['is_default']) {
            $signature->makeDefault();
        }

        return $signature;
    }

    public function delete(SignatureSetting $signature)
    {
        Storage::disk('public')->delete($signature->signature_path);
        $signature->delete();
    }

    public function validateImage($file)
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/svg+xml'];
        
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('Invalid file type. Only JPG, PNG and SVG files are allowed.');
        }

        if ($file->getSize() > 2048 * 1024) { // 2MB
            throw new \Exception('File size too large. Maximum size is 2MB.');
        }

        return true;
    }

    public function optimizeImage(UploadedFile $file)
    {
        // Implement image optimization logic here
        // Could use libraries like Intervention Image
        return $file;
    }
}
