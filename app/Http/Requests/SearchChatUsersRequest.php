<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchChatUsersRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'for_broadcast' => ['sometimes', 'boolean'],
            'for_support_search' => ['sometimes', 'boolean'],
        ];
    }
}
