<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)],
            'lines.*.grams' => ['required', 'numeric', 'gt:0', 'lt:1000000'],
            // optional: which rack slot the container goes into (re-loads the slot)
            'lines.*.slot_id' => ['nullable', 'integer', Rule::exists('station_slots', 'id')],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
