<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatTypingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                Rule::notIn([(int) $this->user()->id]),
            ],
            'typing' => ['required', 'boolean'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'typing' => $this->boolean('typing'),
        ]);
    }
}
