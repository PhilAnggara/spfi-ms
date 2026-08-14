<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-permissions') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \Spatie\Permission\Models\Permission $permission */
        $permission = $this->route('permission');

        return [
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('permissions', 'name')->ignore($permission->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Permission name must be kebab-case (e.g. view-products).',
        ];
    }
}
