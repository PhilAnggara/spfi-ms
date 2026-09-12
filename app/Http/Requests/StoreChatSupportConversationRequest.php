<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatSupportConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('chat-support-operate') === true;
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
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a user to open their SPFI-MS chat.',
            'user_id.exists' => 'The selected user was not found.',
        ];
    }
}
