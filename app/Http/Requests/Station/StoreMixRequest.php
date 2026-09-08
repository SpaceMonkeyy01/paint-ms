<?php

namespace App\Http\Requests\Station;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMixRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')],
            'colour_ref' => ['required', 'string', 'max:60'],
            'hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)],
            'components.*.grams' => ['required', 'numeric', 'gt:0', 'lt:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'components.required' => 'Add at least one component to the mix.',
            'hex.regex' => 'HEX must look like #1A2B3C.',
        ];
    }
}
