<?php

namespace App\Http\Requests\Station;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsumptionRequest extends FormRequest
{
    public function rules(): array
    {
        $orderId = $this->route('order')->id;

        return [
            'readings' => ['required', 'array', 'min:1'],
            'readings.*.slot' => ['required', 'string', 'max:10', 'regex:/^[WPA]\d{2}$/'],
            'readings.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)],
            // one of grams / litres per line; litres needs density (enforced in LedgerService, rule 7)
            'readings.*.grams' => ['nullable', 'required_without:readings.*.litres', 'numeric', 'gt:0', 'lt:1000000'],
            'readings.*.litres' => ['nullable', 'numeric', 'gt:0', 'lt:1000'],
            'readings.*.colour_batch_id' => [
                'nullable', 'integer',
                Rule::exists('colour_batches', 'id')->where('order_id', $orderId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'readings.*.grams.required_without' => 'Enter grams (or litres, for items with density).',
            'readings.*.colour_batch_id.exists' => 'That colour batch belongs to a different order.',
        ];
    }
}
