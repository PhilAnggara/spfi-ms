<?php

namespace App\Http\Requests;

use App\Enums\CountTagCondition;
use App\Models\CountTagSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNonFgCountTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-count-tag-non-fg') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'location_id' => ['required', 'integer', 'exists:count_tag_locations,id'],
            'section_id' => [
                'required',
                'integer',
                Rule::exists('count_tag_sections', 'id')->where(
                    fn ($query) => $query->where('location_id', (int) $this->input('location_id'))
                ),
            ],
            'qty' => ['required', 'numeric', 'gt:0'],
            'count_tag_date' => ['required', 'date'],
            'tran_date' => ['required', 'date'],
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
            'item_id.required' => 'Scan or look up a product first.',
            'location_id.required' => 'Select a location.',
            'section_id.required' => 'Select a section.',
            'section_id.exists' => 'The selected section does not belong to the chosen location.',
            'qty.gt' => 'Quantity must be greater than zero.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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
        });
    }
}
