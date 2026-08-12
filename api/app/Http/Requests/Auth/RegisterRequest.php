<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fullname' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    $existingUser = User::query()->where('email', $value)->first();

                    if (! $existingUser) {
                        return;
                    }

                    if (! is_null($existingUser->email_verified_at)) {
                        $fail('This email is already taken.');
                        return;
                    }

                    $fail('An account with this email already exists, but it has not been verified yet. Please log in to continue the verification process.');
                },
            ],
            'phone_number' => [
                'nullable',
                'string',
                'max:25',
                'required_without:email',
                Rule::unique('users', 'phone_number'),
                'regex:/^\+?[0-9]{7,25}$/',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(3),
            ],
            
        ];
    }

    // protected function prepareForValidation(): void
    // {
    //     if ($this->has('email')) {
    //         $this->merge(['email' => $this->email ? Str::lower(trim($this->email)) : null]);
    //     }

    //     if ($this->has('phone_number')) {
    //         $phone = trim($this->phone_number ?? '');
    //         $phone = $phone !== '' ? preg_replace('/[^\d+]/', '', $phone) : null;
    //         $this->merge(['phone_number' => $phone]);
    //     }
    // }

    public function messages(): array
    {
        return [
            'phone_number.regex' => 'The phone number must contain only digits and an optional leading +.',
            'email.required' => 'Email is required.',
            'phone_number.required_without' => 'Phone number or email is required.',
        ];
    }
}
