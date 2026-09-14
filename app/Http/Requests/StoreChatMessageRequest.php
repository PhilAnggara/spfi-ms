<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Conversation $conversation */
        $conversation = $this->route('conversation');

        if ($this->user() === null || ! $conversation instanceof Conversation) {
            return false;
        }

        return app(ChatService::class)->canAccessConversation($conversation, $this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'as_system' => ['sometimes', 'boolean'],
            'attachment' => [
                'nullable',
                'file',
                'max:102400',
                'mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm,m4v,avi,3gp,mkv,pdf,doc,docx,xls,xlsx,csv,txt,zip,rar,ppt,pptx',
            ],
            'attachment_width' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'attachment_height' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.max' => 'Message text may not be greater than 5000 characters.',
            'attachment.max' => 'Attachments may not be greater than 100MB.',
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
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body') && is_string($this->input('body'))) {
            $this->merge([
                'body' => trim($this->input('body')),
            ]);
        }

        if ($this->has('as_system')) {
            $this->merge([
                'as_system' => $this->boolean('as_system'),
            ]);
        }
    }
}
