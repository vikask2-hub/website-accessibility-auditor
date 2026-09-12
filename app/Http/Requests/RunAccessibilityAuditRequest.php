<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunAccessibilityAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('url'));

        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $this->merge(['url' => $url]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'audit_name' => ['nullable', 'string', 'max:80'],
            'include_passed' => ['nullable', Rule::in(['1'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'url.required' => 'Enter a website URL to audit.',
            'url.url' => 'Enter a valid public HTTP or HTTPS URL.',
            'url.max' => 'The website URL is too long.',
            'audit_name.max' => 'Keep the audit name within 80 characters.',
        ];
    }
}
