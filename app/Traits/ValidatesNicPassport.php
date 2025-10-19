<?php

namespace App\Traits;

trait ValidatesNicPassport
{
    /**
     * Validate Sri Lankan NIC number with comprehensive checks
     * Supports both old format (9 digits + V/X) and new format (12 digits)
     * Validates year and day values
     */
    public static function isValidNic(string $nic): bool
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
                if (!static::isLeapYear($fullYear)) {
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
                if (!static::isLeapYear($year)) {
                    return false;
                }
            }

            // Validate that the day doesn't exceed days in year
            $actualDay = $days > 500 ? $days - 500 : $days;
            $daysInYear = static::isLeapYear($year) ? 366 : 365;
            
            if ($actualDay > $daysInYear) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Validate passport number format
     * Basic validation for international passport numbers
     */
    public static function isValidPassport(string $passport): bool
    {
        $passport = strtoupper(trim($passport));
        
        // Basic passport validation: 6-15 alphanumeric characters
        // Can contain letters and numbers, no spaces or special characters
        return preg_match('/^[A-Z0-9]{6,15}$/', $passport);
    }

    /**
     * Check if a year is a leap year
     */
    public static function isLeapYear(int $year): bool
    {
        return (($year % 4 == 0) && ($year % 100 != 0)) || ($year % 400 == 0);
    }

    /**
     * Extract gender from NIC number
     * Returns 'male', 'female', or null if invalid
     */
    public static function getGenderFromNic(string $nic): ?string
    {
        $nic = strtoupper(trim($nic));

        // Old format
        if (preg_match('/^([0-9]{2})([0-9]{3})([0-9]{4})([VX])$/', $nic, $matches)) {
            $days = (int)$matches[2];
            return $days <= 366 ? 'male' : 'female';
        }

        // New format
        if (preg_match('/^([0-9]{4})([0-9]{3})([0-9]{5})$/', $nic, $matches)) {
            $days = (int)$matches[2];
            return $days <= 366 ? 'male' : 'female';
        }

        return null;
    }

    /**
     * Extract birth year from NIC number
     * Returns the birth year or null if invalid
     */
    public static function getBirthYearFromNic(string $nic): ?int
    {
        $nic = strtoupper(trim($nic));

        // Old format
        if (preg_match('/^([0-9]{2})([0-9]{3})([0-9]{4})([VX])$/', $nic, $matches)) {
            $year = (int)$matches[1];
            // Assume 1900s or 2000s based on reasonable range
            return $year >= 0 && $year <= 25 ? 2000 + $year : 1900 + $year;
        }

        // New format
        if (preg_match('/^([0-9]{4})([0-9]{3})([0-9]{5})$/', $nic, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Validate NIC or passport based on student type
     */
    public static function validateNicOrPassport(string $value, bool $isLocal): bool
    {
        if ($isLocal) {
            return static::isValidNic($value);
        } else {
            return static::isValidPassport($value);
        }
    }
}