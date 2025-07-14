<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateComplaintRequest extends FormRequest
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
            'exam_id' => 'nullable|exists:exams,id',
            'description' => 'required|string|max:1000',
            'organization_id' => 'nullable|exists:organizations,id',
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
            'exam_id.exists' => 'The selected exam does not exist.',
            'description.required' => 'The complaint description is required.',
            'description.max' => 'The complaint description may not be greater than 1000 characters.',
            'organization_id.exists' => 'The selected organization does not exist.',
        ];
    }
}
