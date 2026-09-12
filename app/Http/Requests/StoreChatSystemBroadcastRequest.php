<?php

namespace App\Http\Requests;

use App\Enums\ChatSystemBroadcastAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChatSystemBroadcastRequest extends FormRequest
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
            'audience' => ['required', Rule::enum(ChatSystemBroadcastAudience::class)],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['integer', 'min:1'],
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip,rar,ppt,pptx',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audience.required' => 'Please choose who should receive this broadcast.',
            'body.max' => 'Message text may not be greater than 5000 characters.',
            'attachment.max' => 'Attachments may not be greater than 10MB.',
            'attachment.mimes' => 'This file type is not allowed.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $body = trim((string) $this->input('body', ''));
            $hasAttachment = $this->hasFile('attachment');

            if ($body === '' && ! $hasAttachment) {
                $validator->errors()->add('body', 'Please enter a message or attach a file.');
            }

            $audience = ChatSystemBroadcastAudience::tryFrom((string) $this->input('audience'));
            $targetIds = $this->input('target_ids', []);

            if (in_array($audience, [ChatSystemBroadcastAudience::Departments, ChatSystemBroadcastAudience::Users], true)
                && (! is_array($targetIds) || $targetIds === [])) {
                $validator->errors()->add('target_ids', 'Select at least one target.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $targetIds = $this->input('target_ids');

        if (is_string($targetIds)) {
            $decoded = json_decode($targetIds, true);
            $targetIds = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $targetIds, -1, PREG_SPLIT_NO_EMPTY);
        }

        if ($this->has('body') && is_string($this->input('body'))) {
            $this->merge([
                'body' => trim($this->input('body')),
            ]);
        }

        if (is_array($targetIds)) {
            $this->merge([
                'target_ids' => array_values(array_unique(array_map('intval', $targetIds))),
            ]);
        }
    }
}
