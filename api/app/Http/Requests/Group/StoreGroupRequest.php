<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups', 'group_name')->where(fn ($query) => $query->where('owner_id', $this->user()->id)),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'group_name.unique' => 'You already have a group with this name.',
        ];
    }
}
