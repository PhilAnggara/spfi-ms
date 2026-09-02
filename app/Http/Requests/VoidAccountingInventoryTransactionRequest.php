<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidAccountingInventoryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('void-accounting-inventory') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
