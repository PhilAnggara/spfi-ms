<?php

namespace App\Http\Requests;

use App\Enums\CountTagCondition;
use App\Models\CountTagSection;
use App\Models\NonFgCountTag;
use App\Support\CountTagPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNonFgCountTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update-count-tag-non-fg') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        if ($this->input('section_id') === '' || $this->input('section_id') === null) {
            $merged['section_id'] = null;
        }

        $period = trim((string) $this->input('count_tag_date', ''));
        if ($period !== '') {
            try {
                $merged['count_tag_date'] = CountTagPeriod::toEndOfMonth($period);
            } catch (\Throwable) {
                // Leave as-is; validation will fail.
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:count_tag_locations,id'],
            'section_id' => [
                'nullable',
                'integer',
                Rule::exists('count_tag_sections', 'id')->where(
                    fn ($query) => $query->where('location_id', (int) $this->input('location_id'))
                ),
            ],
            'qty' => ['required', 'numeric', 'gt:0'],
            'count_tag_date' => ['required', 'date'],
            'row' => ['nullable', 'integer', 'min:1'],
            'col' => ['nullable', 'integer', 'min:1'],
            'level' => ['nullable', 'integer', 'min:1'],
            'condition' => ['nullable', 'string', Rule::enum(CountTagCondition::class)],
            'size' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_id.required' => 'Select a location.',
            'section_id.exists' => 'The selected section does not belong to the chosen location.',
            'qty.gt' => 'Quantity must be greater than zero.',
            'count_tag_date.required' => 'Select a period month.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validatePeriodNotFuture($validator);
            $this->validateSectionBounds($validator);
            $this->validateUniqueInPeriod($validator);
        });
    }

    private function validatePeriodNotFuture(Validator $validator): void
    {
        $period = trim((string) $this->input('count_tag_date'));
        if ($period === '') {
            return;
        }

        if (CountTagPeriod::isFutureMonth($period)) {
            $validator->errors()->add('count_tag_date', 'Period month cannot be in the future.');
        }
    }

    private function validateSectionBounds(Validator $validator): void
    {
        $sectionId = (int) $this->input('section_id');
        if ($sectionId <= 0) {
            return;
        }

        $section = CountTagSection::query()->find($sectionId);
        if ($section === null) {
            return;
        }

        $row = $this->input('row');
        if ($row !== null && $row !== '' && $section->max_row > 0 && (int) $row > $section->max_row) {
            $validator->errors()->add('row', "Row may not be greater than {$section->max_row} for this section.");
        }

        $col = $this->input('col');
        if ($col !== null && $col !== '' && $section->max_column > 0 && (int) $col > $section->max_column) {
            $validator->errors()->add('col', "Column may not be greater than {$section->max_column} for this section.");
        }
    }

    private function validateUniqueInPeriod(Validator $validator): void
    {
        /** @var NonFgCountTag|null $tag */
        $tag = $this->route('nonFgCountTag');
        if (! $tag instanceof NonFgCountTag) {
            return;
        }

        $periodDate = trim((string) $this->input('count_tag_date'));
        if ($periodDate === '') {
            return;
        }

        try {
            $period = Carbon::parse($periodDate);
        } catch (\Throwable) {
            return;
        }

        $exists = NonFgCountTag::query()
            ->where('item_id', $tag->item_id)
            ->where('id', '<>', $tag->id)
            ->whereYear('count_tag_date', $period->year)
            ->whereMonth('count_tag_date', $period->month)
            ->exists();

        if ($exists) {
            $validator->errors()->add(
                'count_tag_date',
                'This item already has another count tag for that period.'
            );
        }
    }
}
