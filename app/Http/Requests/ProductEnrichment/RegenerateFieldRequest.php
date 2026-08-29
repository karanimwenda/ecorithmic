<?php

namespace App\Http\Requests\ProductEnrichment;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateFieldRequest extends FormRequest
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
            'feedback' => ['required', 'string', 'max:1000'],
        ];
    }
}
