<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-stock-adjustment') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sa_number' => ['nullable', 'string', 'max:50'],
            'sa_number_suggested' => ['nullable', 'string', 'max:50'],
            'sa_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id', 'distinct'],
            'items.*.new_balance' => ['required', 'numeric', 'min:0'],
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
            'confirmed.accepted' => 'Please confirm that you want to post this stock adjustment.',
            'items.required' => 'Add at least one item line.',
            'items.*.item_id.distinct' => 'Each item can only appear once in the adjustment.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = collect($this->input('items', []));
            $hasDelta = $items->contains(function ($line): bool {
                return isset($line['item_id'], $line['new_balance']);
            });

            if (! $hasDelta) {
                $validator->errors()->add('items', 'Add at least one item line.');
            }
        });
    }
}
