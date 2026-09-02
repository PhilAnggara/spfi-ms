<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EncodeAccountingInventoryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('encode-accounting-inventory') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.direction' => ['required', 'in:in,out'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.00001'],
            'lines.*.unit_of_measure_id' => ['nullable', 'integer', 'exists:unit_of_measures,id'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.amount' => ['required', 'numeric', 'min:0'],
            'lines.*.prefill_quantity' => ['nullable', 'numeric'],
            'lines.*.prefill_unit_cost' => ['nullable', 'numeric'],
        ];
    }
}
