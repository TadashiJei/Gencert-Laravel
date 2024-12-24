<?php

namespace App\Http\Requests\Signature;

use Illuminate\Foundation\Http\FormRequest;

class StoreSignatureRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'file', 'mimes:jpeg,png,svg', 'max:2048'], // 2MB max
            'is_default' => ['boolean'],
            'position' => ['nullable', 'array'],
            'position.x' => ['required_with:position', 'numeric'],
            'position.y' => ['required_with:position', 'numeric'],
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'Signature title is required',
            'signature.required' => 'Signature file is required',
            'signature.mimes' => 'Signature must be a JPEG, PNG, or SVG file',
            'signature.max' => 'Signature file size must not exceed 2MB',
        ];
    }
}
