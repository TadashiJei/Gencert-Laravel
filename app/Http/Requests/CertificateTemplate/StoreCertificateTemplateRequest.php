<?php

namespace App\Http\Requests\CertificateTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateTemplateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'orientation' => ['nullable', 'string', 'in:landscape,portrait'],
            'paper_size' => ['nullable', 'string', 'in:a4,letter,legal'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*' => ['required', 'string'],

            // Optional features
            'enable_qr_code' => ['boolean'],
            'qr_code_settings' => ['nullable', 'array'],
            'qr_code_settings.position' => ['required_with:qr_code_settings', 'array'],
            'qr_code_settings.position.x' => ['required_with:qr_code_settings.position', 'numeric'],
            'qr_code_settings.position.y' => ['required_with:qr_code_settings.position', 'numeric'],
            'qr_code_settings.size' => ['nullable', 'integer', 'min:50', 'max:300'],

            'enable_expiration' => ['boolean'],
            'expiration_days' => ['required_if:enable_expiration,true', 'nullable', 'integer', 'min:1'],

            'enable_auto_renewal' => ['boolean'],
            'renewal_days_before' => ['required_if:enable_auto_renewal,true', 'nullable', 'integer', 'min:1'],

            'enable_revocation' => ['boolean'],

            'enable_multi_language' => ['boolean'],
            'supported_languages' => ['required_if:enable_multi_language,true', 'array'],
            'supported_languages.*' => ['required', 'string', 'size:2'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Template name is required',
            'content.required' => 'Template content is required',
            'expiration_days.required_if' => 'Expiration days is required when expiration is enabled',
            'renewal_days_before.required_if' => 'Renewal days is required when auto-renewal is enabled',
            'supported_languages.required_if' => 'At least one language must be selected when multi-language is enabled',
            'supported_languages.*.size' => 'Language code must be 2 characters (e.g., en, es, fr)',
        ];
    }
}
