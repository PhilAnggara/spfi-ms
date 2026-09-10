<?php

namespace App\Http\Requests;

use App\Enums\ScreenMessageAudienceType;
use App\Enums\ScreenMessageDisplayMode;
use App\Enums\ScreenMessageTheme;
use App\Support\ScreenMessageAccess;
use App\Support\ScreenMessageHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreScreenMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ScreenMessageAccess::canCreate($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:8000'],
            'display_mode' => ['required', 'string', Rule::in(ScreenMessageDisplayMode::values())],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'audience_type' => ['required', 'string', Rule::in(ScreenMessageAudienceType::values())],
            'theme' => ['required', 'string', Rule::in(ScreenMessageTheme::values())],
            'allow_reply' => ['sometimes', 'boolean'],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please enter a message title.',
            'body.required' => 'Please enter the message body.',
            'display_mode.required' => 'Please choose how the overlay should behave.',
            'audience_type.required' => 'Please choose who should receive the message.',
            'theme.required' => 'Please choose a message theme.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mode = ScreenMessageDisplayMode::tryFrom((string) $this->input('display_mode'));
            $audience = ScreenMessageAudienceType::tryFrom((string) $this->input('audience_type'));
            $targetIds = $this->input('target_ids', []);

            if (ScreenMessageHtml::isEmpty($this->input('body'))) {
                $validator->errors()->add('body', 'Please enter the message body.');
            }

            if ($mode?->requiresDuration() && blank($this->input('duration_seconds'))) {
                $validator->errors()->add('duration_seconds', 'Duration is required for auto-close messages.');
            }

            if ($mode === ScreenMessageDisplayMode::Permanent && ! ScreenMessageAccess::canCreatePermanent($this->user())) {
                $validator->errors()->add('display_mode', 'You do not have permission to create permanent screen messages.');
            }

            if ($audience && ! ScreenMessageAccess::canTargetAudience($this->user(), $audience)) {
                $validator->errors()->add('audience_type', 'You do not have permission to target this audience.');
            }

            if (in_array($audience, [ScreenMessageAudienceType::Users, ScreenMessageAudienceType::Departments], true)
                && (! is_array($targetIds) || $targetIds === [])) {
                $validator->errors()->add('target_ids', 'Select at least one target.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $duration = $this->input('duration_seconds');

        $this->merge([
            'allow_reply' => $this->boolean('allow_reply'),
            'duration_seconds' => ($duration === '' || $duration === null) ? null : $duration,
            'theme' => $this->input('theme') ?: ScreenMessageTheme::Default->value,
            'body' => ScreenMessageHtml::sanitize($this->input('body')),
        ]);
    }
}
