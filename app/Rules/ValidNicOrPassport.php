<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Traits\ValidatesNicPassport;

class ValidNicOrPassport implements ValidationRule
{
    use ValidatesNicPassport;

    protected bool $isLocal;

    public function __construct(bool $isLocal)
    {
        $this->isLocal = $isLocal;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!static::validateNicOrPassport($value, $this->isLocal)) {
            if ($this->isLocal) {
                $fail('The :attribute must be a valid Sri Lankan NIC number (format: 9 digits + V/X or 12 digits).');
            } else {
                $fail('The :attribute must be a valid passport number (6-15 alphanumeric characters).');
            }
        }
    }
}