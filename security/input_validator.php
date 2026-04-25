<?php
/**
 * security/input_validator.php
 * Validator — static helpers for sanitising and validating common inputs.
 */
declare(strict_types=1);

class Validator
{
    /**
     * Trim and limit a string, stripping tags.
     */
    public static function sanitizeString(string $input, int $maxLen = 255): string
    {
        $clean = strip_tags(trim($input));
        return mb_substr($clean, 0, $maxLen, 'UTF-8');
    }

    /**
     * Validate an email address.
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate an Indian mobile number (10 digits, optional +91 or 0 prefix).
     */
    public static function validatePhone(string $phone): bool
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '91') && strlen($phone) === 12) {
            $phone = substr($phone, 2);
        }
        if (str_starts_with($phone, '0') && strlen($phone) === 11) {
            $phone = substr($phone, 1);
        }
        return strlen($phone) === 10 && ctype_digit($phone);
    }

    /**
     * Validate a 6-digit Indian pincode.
     */
    public static function validatePincode(string $pin): bool
    {
        return (bool)preg_match('/^\d{6}$/', trim($pin));
    }

    /**
     * Validate and return a float price (≥ 0).
     */
    public static function validatePrice(mixed $price): float
    {
        $f = filter_var($price, FILTER_VALIDATE_FLOAT);
        if ($f === false || $f < 0) return 0.0;
        return round($f, 2);
    }
}
