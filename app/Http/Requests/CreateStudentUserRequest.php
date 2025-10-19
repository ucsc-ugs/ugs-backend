<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Traits\ValidatesNicPassport;

class CreateStudentUserRequest extends FormRequest
{
    use ValidatesNicPassport;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'local' => 'required|boolean',
            'passport_nic' => [
                'required',
                'string',
                'unique:students,passport_nic',
                'max:20',
                function ($attribute, $value, $fail) {
                    // Only validate NIC format for local students
                    if ($this->input('local') == true || $this->input('local') === '1') {
                        if (!static::isValidNic($value)) {
                            $fail('The NIC number must be a valid Sri Lankan NIC number (format: 9 digits + V/X or 12 digits).');
                        }
                    } else {
                        // For foreign students, validate passport format (basic validation)
                        if (!static::isValidPassport($value)) {
                            $fail('The passport number must be a valid format (6-15 alphanumeric characters).');
                        }
                    }
                }
            ],
        ];
    }


    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'passport_nic.required' => 'NIC or Passport number is required.',
            'passport_nic.unique' => 'This NIC/Passport number is already registered.',
            'passport_nic.max' => 'NIC/Passport number cannot exceed 20 characters.',
            'local.required' => 'Please specify if you are a local or foreign student.',
            'local.boolean' => 'Student type must be either local or foreign.',
            'email.unique' => 'This email address is already registered.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'passport_nic' => 'NIC/Passport number',
            'local' => 'student type',
        ];
    }
}
