<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpeningBalanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-opening-balance-correction') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'obc_number' => ['nullable', 'string', 'max:50'],
            'obc_number_suggested' => ['nullable', 'string', 'max:50'],
            'period_month' => ['required', 'date_format:Y-m'],
            'reason' => ['required', 'string', 'max:2000'],
            'allow_negative_balance' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id', 'distinct'],
            'items.*.new_beginning' => ['required', 'numeric', 'min:0'],
            'items.*.wh_code' => ['nullable', 'string', 'max:20'],
            'confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Please confirm that you understand this will rebuild stock movements from the period start.',
            'items.required' => 'Add at least one item line.',
            'items.*.item_id.distinct' => 'Each item can only appear once in the correction.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'allow_negative_balance' => $this->boolean('allow_negative_balance'),
        ]);
    }
}
