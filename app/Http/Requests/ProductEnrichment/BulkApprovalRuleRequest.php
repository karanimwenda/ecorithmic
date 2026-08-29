<?php

namespace App\Http\Requests\ProductEnrichment;

use Illuminate\Foundation\Http\FormRequest;

class BulkApprovalRuleRequest extends FormRequest
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
            'rule' => ['required', 'array'],
            'rule.confidence_tier' => ['sometimes', 'string', 'in:high,medium,low'],
            'rule.category' => ['sometimes', 'string'],
            'rule.attribute_code' => ['sometimes', 'string'],
        ];
    }
}
