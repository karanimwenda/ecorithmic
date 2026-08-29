<?php

namespace App\Http\Requests\ProductEnrichment;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'spreadsheet' => ['required', 'file', 'mimes:xlsx,csv'],
            // 1GB max (FR-005): 1,048,576 KB
            'archive' => ['required', 'file', 'mimes:zip', 'max:1048576'],
            'cost_cap_usd' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
