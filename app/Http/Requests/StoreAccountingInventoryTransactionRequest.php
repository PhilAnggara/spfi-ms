<?php

namespace App\Http\Requests;

use App\Models\AccountingInventoryTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountingInventoryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-accounting-inventory') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'doc_type' => ['required', Rule::in(AccountingInventoryTransaction::MANUAL_DOC_TYPES)],
            'doc_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('accounting_inventory_transactions', 'doc_number')
                    ->where(fn ($query) => $query->where('doc_type', strtoupper((string) $this->input('doc_type')))),
            ],
            'doc_date' => ['required', 'date'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.direction' => ['required', 'in:in,out'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.00001'],
            'lines.*.unit_of_measure_id' => ['nullable', 'integer', 'exists:unit_of_measures,id'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
