<?php

namespace App\Services\Tax;

use Illuminate\Validation\ValidationException;

final class Gstin
{
    public static function normalize(mixed $value): string
    {
        $value = is_string($value) ? strtoupper(trim($value)) : '';
        if (!preg_match('/^(0[1-9]|[12][0-9]|3[0-8])[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/D', $value)) {
            throw ValidationException::withMessages(['gstin' => 'Enter a valid 15-character GSTIN. Format does not verify registration.']);
        }
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'; $sum = 0; $factor = 2;
        for ($i = 13; $i >= 0; $i--) {
            $n = strpos($alphabet, $value[$i]) * $factor;
            $sum += intdiv($n, 36) + $n % 36; $factor = $factor === 2 ? 1 : 2;
        }
        if ($alphabet[(36 - $sum % 36) % 36] !== $value[14]) {
            throw ValidationException::withMessages(['gstin' => 'GSTIN checksum is invalid.']);
        }
        return $value;
    }

    public static function state(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^(0[1-9]|[12][0-9]|3[0-8])$/D', $value)) {
            throw ValidationException::withMessages(['state_code' => 'Select a valid Indian state or union territory code.']);
        }
        return $value;
    }
}
