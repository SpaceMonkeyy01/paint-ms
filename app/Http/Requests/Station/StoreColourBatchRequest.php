<?php

namespace App\Http\Requests\Station;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreColourBatchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'colour_ref' => ['required', 'string', 'max:60'],   // "PANTONE 7463 C", "cool gray 3 C"
            'notes' => ['nullable', 'string', 'max:1000'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)],
            'components.*.grams' => ['required', 'numeric', 'gt:0', 'lt:1000000'],
        ];
    }
}
