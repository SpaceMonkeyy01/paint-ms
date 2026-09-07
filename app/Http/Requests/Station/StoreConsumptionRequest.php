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
            'readings.*.start_wt' => ['required', 'numeric', 'gte:0', 'lt:1000000'],
            'readings.*.end_wt' => ['required', 'numeric', 'gte:0', 'lte:readings.*.start_wt'],
            'readings.*.wastage' => ['nullable', 'numeric', 'gte:0', 'lt:1000000'],
            'readings.*.colour_batch_id' => [
                'nullable', 'integer',
                Rule::exists('colour_batches', 'id')->where('order_id', $orderId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'readings.*.end_wt.lte' => 'End weight cannot be above start weight.',
            'readings.*.colour_batch_id.exists' => 'That colour batch belongs to a different order.',
        ];
    }
}
