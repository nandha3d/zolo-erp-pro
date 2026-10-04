<?php

namespace App\Support;

use InvalidArgumentException;

/** Exact arithmetic at the existing ledger's four-decimal precision. */
final class LedgerAmount
{
    public static function units(mixed $value): int
    {
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidArgumentException('Ledger amounts must be finite.');
            }
            $value = number_format($value, 4, '.', '');
        }
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('Invalid ledger amount.');
        }
        if (!preg_match('/^(-?)(\d{1,14})(?:\.(\d{1,4}))?$/D', (string) $value, $parts)) {
            throw new InvalidArgumentException('Ledger amounts require at most four decimals and fourteen integer digits.');
        }
        $units = (int) $parts[2] * 10000 + (int) str_pad($parts[3] ?? '', 4, '0');
        return ($parts[1] === '-' ? -1 : 1) * $units;
    }

    public static function decimal(int $units): string
    {
        if (abs($units) > 999999999999999999) {
            throw new InvalidArgumentException('Ledger amount exceeds decimal(18,4).');
        }
        return ($units < 0 ? '-' : '').intdiv(abs($units), 10000).'.'.str_pad((string) (abs($units) % 10000), 4, '0', STR_PAD_LEFT);
    }
}
