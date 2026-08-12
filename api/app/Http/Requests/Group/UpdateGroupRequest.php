<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $groupId = $this->route('id');

        return [
            'group_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('groups', 'group_name')
                    ->where(fn ($query) => $query->where('owner_id', $this->user()->id))
                    ->ignore($groupId),
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
