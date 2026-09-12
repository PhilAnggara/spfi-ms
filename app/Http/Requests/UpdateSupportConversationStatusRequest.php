<?php

namespace App\Http\Requests;

use App\Enums\SupportConversationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportConversationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('chat-support-operate') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SupportConversationStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Support status is required.',
        ];
    }
}
