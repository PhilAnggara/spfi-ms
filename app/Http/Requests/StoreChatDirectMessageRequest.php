<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChatDirectMessageRequest extends FormRequest
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
            'user_id.required' => 'Please select a user to chat with.',
            'user_id.exists' => 'The selected user was not found.',
            'user_id.not_in' => 'You cannot start a conversation with yourself.',
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
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body') && is_string($this->input('body'))) {
            $this->merge([
                'body' => trim($this->input('body')),
            ]);
        }
    }
}
