<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['receipt', 'adjust'])],
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)],
            // receipts are positive grams; adjustments are signed (+/−)
            'grams' => [
                'required', 'numeric', 'not_in:0', 'gt:-1000000', 'lt:1000000',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->input('type') === 'receipt' && (float) $value <= 0) {
                        $fail('Receipts must be positive grams — use an adjustment for corrections.');
                    }
                },
            ],
            'rate' => ['nullable', 'numeric', 'gte:0'], // optional purchase-rate override on receipts
            'external_ref' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000', 'required_if:type,adjust'],
        ];
    }

    public function messages(): array
    {
        return ['remarks.required_if' => 'Adjustments need a reason.'];
    }
}
