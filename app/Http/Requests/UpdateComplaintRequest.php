<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintRequest extends FormRequest
{
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
            'description' => 'sometimes|required|string|max:1000',
            'status' => 'sometimes|required|in:pending,resolved,rejected',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => 'The complaint description is required.',
            'description.max' => 'The complaint description may not be greater than 1000 characters.',
            'status.in' => 'The status must be one of: pending, resolved, rejected.',
        ];
    }
}
