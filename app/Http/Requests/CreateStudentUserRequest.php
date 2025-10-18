<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateStudentUserRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'local' => 'required|boolean',
            'passport_nic' => 'required|string|unique:students,passport_nic|max:20',
             function ($attribute, $value, $fail) {
                    // Only validate NIC format for local students
                    if ($this->input('local') == true || $this->input('local') === '1') {
                        if (!$this->isValidNic($value)) {
                            $fail('The '.$attribute.' must be a valid Sri Lankan NIC number.');
                        }
                    }
                    // For foreign students, any passport format is accepted
                },
        ];
    }


    
    /**
     * Validate Sri Lankan NIC number with comprehensive checks
     * Supports both old format (9 digits + V/X) and new format (12 digits)
     * Validates year and day values
     */
    private function isValidNic(string $nic): bool
    {
        $nic = strtoupper(trim($nic));

        // Old NIC format: YYDDDSSSSX (e.g., 923456789V)
        // YY = Year (last 2 digits)
        // DDD = Days from Jan 1st (1-366 for males, 501-866 for females)
        // SSSS = Serial number
        // X = V or X
        if (preg_match('/^([0-9]{2})([0-9]{3})([0-9]{4})([VX])$/', $nic, $matches)) {
            $year = (int)$matches[1];
            $days = (int)$matches[2];
            $serial = $matches[3];
            $suffix = $matches[4];

            // Validate days
            // Male: 1-366, Female: 501-866
            $isValidMale = ($days >= 1 && $days <= 366);
            $isValidFemale = ($days >= 501 && $days <= 866);
            
            if (!($isValidMale || $isValidFemale)) {
                return false;
            }

            // Check if it's a leap year for day 366 or 866
            if (($days == 366 || $days == 866)) {
                // For old format, assume 1900s or 2000s based on reasonable range
                $fullYear = $year >= 0 && $year <= 25 ? 2000 + $year : 1900 + $year;
                if (!$this->isLeapYear($fullYear)) {
                    return false;
                }
            }

            return true;
        }

        // New NIC format: YYYYDDDSSSSS (e.g., 199923456789)
        // YYYY = Full year
        // DDD = Days from Jan 1st (1-366 for males, 501-866 for females)
        // SSSSS = Serial number
        if (preg_match('/^([0-9]{4})([0-9]{3})([0-9]{5})$/', $nic, $matches)) {
            $year = (int)$matches[1];
            $days = (int)$matches[2];
            $serial = $matches[3];

            // Validate year (reasonable range: 1900-2025)
            if ($year < 1900 || $year > date('Y')) {
                return false;
            }

            // Validate days
            // Male: 1-366, Female: 501-866
            $isValidMale = ($days >= 1 && $days <= 366);
            $isValidFemale = ($days >= 501 && $days <= 866);
            
            if (!($isValidMale || $isValidFemale)) {
                return false;
            }

            // Check if day 366 or 866 is valid (leap year check)
            if (($days == 366 || $days == 866)) {
                if (!$this->isLeapYear($year)) {
                    return false;
                }
            }

            // Validate that the day doesn't exceed days in year
            $actualDay = $days > 500 ? $days - 500 : $days;
            $daysInYear = $this->isLeapYear($year) ? 366 : 365;
            
            if ($actualDay > $daysInYear) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Check if a year is a leap year
     */
    private function isLeapYear(int $year): bool
    {
        return (($year % 4 == 0) && ($year % 100 != 0)) || ($year % 400 == 0);
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'passport_nic.required' => 'NIC or Passport number is required.',
            'passport_nic.unique' => 'This NIC/Passport number is already registered.',
        ];
    }
}
