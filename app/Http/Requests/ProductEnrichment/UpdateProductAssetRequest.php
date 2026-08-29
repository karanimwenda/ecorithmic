<?php

namespace App\Http\Requests\ProductEnrichment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductAssetRequest extends FormRequest
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
            'is_primary' => ['required', 'boolean'],
        ];
    }
}
