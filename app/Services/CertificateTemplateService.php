<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CertificateTemplateService
{
    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $template = CertificateTemplate::create([
                'user_id' => auth()->id(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'content' => $data['content'],
                'orientation' => $data['orientation'] ?? 'landscape',
                'paper_size' => $data['paper_size'] ?? 'a4',
                'custom_fields' => $data['custom_fields'] ?? [],
                'is_active' => true,
            ]);

            $template->settings()->create([
                'enable_qr_code' => $data['enable_qr_code'] ?? false,
                'qr_code_settings' => $data['qr_code_settings'] ?? null,
                'enable_expiration' => $data['enable_expiration'] ?? false,
                'expiration_days' => $data['expiration_days'] ?? null,
                'enable_auto_renewal' => $data['enable_auto_renewal'] ?? false,
                'renewal_days_before' => $data['renewal_days_before'] ?? null,
                'enable_revocation' => $data['enable_revocation'] ?? false,
                'enable_multi_language' => $data['enable_multi_language'] ?? false,
                'supported_languages' => $data['supported_languages'] ?? ['en'],
            ]);

            return $template;
        });
    }

    public function update(CertificateTemplate $template, array $data)
    {
        return DB::transaction(function () use ($template, $data) {
            $template->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'content' => $data['content'],
                'orientation' => $data['orientation'] ?? $template->orientation,
                'paper_size' => $data['paper_size'] ?? $template->paper_size,
                'custom_fields' => $data['custom_fields'] ?? $template->custom_fields,
            ]);

            $template->settings()->update([
                'enable_qr_code' => $data['enable_qr_code'] ?? false,
                'qr_code_settings' => $data['qr_code_settings'] ?? null,
                'enable_expiration' => $data['enable_expiration'] ?? false,
                'expiration_days' => $data['expiration_days'] ?? null,
                'enable_auto_renewal' => $data['enable_auto_renewal'] ?? false,
                'renewal_days_before' => $data['renewal_days_before'] ?? null,
                'enable_revocation' => $data['enable_revocation'] ?? false,
                'enable_multi_language' => $data['enable_multi_language'] ?? false,
                'supported_languages' => $data['supported_languages'] ?? ['en'],
            ]);

            return $template;
        });
    }

    public function delete(CertificateTemplate $template)
    {
        return DB::transaction(function () use ($template) {
            // Delete related files if any
            if ($template->background_image) {
                Storage::delete($template->background_image);
            }

            $template->delete();
        });
    }

    public function duplicate(CertificateTemplate $template)
    {
        return DB::transaction(function () use ($template) {
            $newTemplate = $template->replicate();
            $newTemplate->name = "{$template->name} (Copy)";
            $newTemplate->save();

            $newSettings = $template->settings->replicate();
            $newSettings->template_id = $newTemplate->id;
            $newSettings->save();

            return $newTemplate;
        });
    }

    public function generatePreview(CertificateTemplate $template)
    {
        // Implement preview generation logic
        // This could use a PDF generation service or HTML to Image conversion
        return [
            'html' => $template->content,
            // Add more preview data as needed
        ];
    }
}
