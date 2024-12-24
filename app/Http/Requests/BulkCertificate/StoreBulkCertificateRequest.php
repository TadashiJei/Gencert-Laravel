<?php

namespace App\Http\Requests\BulkCertificate;

use Illuminate\Foundation\Http\FormRequest;

class StoreBulkCertificateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'template_id' => ['required', 'exists:certificate_templates,id'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10MB max
            'field_mapping' => ['required', 'array'],
            'field_mapping.*' => ['required', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'template_id.required' => 'Please select a template',
            'template_id.exists' => 'Selected template does not exist',
            'file.required' => 'Please upload a CSV file',
            'file.mimes' => 'File must be a CSV file',
            'file.max' => 'File size must not exceed 10MB',
            'field_mapping.required' => 'Field mapping is required',
        ];
    }
}
