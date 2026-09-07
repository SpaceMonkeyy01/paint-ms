<?php

namespace App\Http\Requests\Store;

use App\Enums\IssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'issue_type' => ['required', Rule::enum(IssueType::class)],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)],
            'lines.*.grams' => ['required', 'numeric', 'gt:0', 'lt:1000000'],
            'remarks' => ['nullable', 'string', 'max:1000', 'required_if:issue_type,variance'],
            'authorized_by' => ['nullable', 'string', 'max:255', 'required_if:issue_type,variance'],
        ];
    }

    public function messages(): array
    {
        return [
            'remarks.required_if' => 'Over-BOM issue needs a reason.',
            'authorized_by.required_if' => 'Over-BOM issue needs an authoriser.',
        ];
    }
}
