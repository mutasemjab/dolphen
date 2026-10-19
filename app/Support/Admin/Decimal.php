<?php

namespace App\Support\Admin;

use Illuminate\Validation\ValidationException;

/** Decimal strings throughout; never convert quantities or money to binary floats. */
final class Decimal
{
    public static function of($value, int $scale = 6): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages(['amount' => __('erp.invalid_decimal')]);
        }
        if (! preg_match('/^-?\d{1,12}(\.\d{1,6})?$/D', (string) $value)) {
            throw ValidationException::withMessages(['amount' => __('erp.invalid_decimal')]);
        }

        return bcadd((string) $value, '0', $scale);
    }

    public static function add($a, $b): string
    {
        return bcadd((string) $a, (string) $b, 6);
    }

    public static function sub($a, $b): string
    {
        return bcsub((string) $a, (string) $b, 6);
    }

    public static function mul($a, $b): string
    {
        return bcmul((string) $a, (string) $b, 6);
    }

    public static function div($a, $b): string
    {
        self::check(bccomp((string) $b, '0', 6) !== 0, 'invalid_decimal');

        return bcdiv((string) $a, (string) $b, 6);
    }

    public static function cmp($a, $b = '0'): int
    {
        return bccomp((string) $a, (string) $b, 6);
    }

    public static function neg($a): string
    {
        return bcsub('0', (string) $a, 6);
    }

    public static function sum(iterable $values): string
    {
        $sum = '0.000000';
        foreach ($values as $value) {
            $sum = self::add($sum, $value);
        }

        return $sum;
    }

    public static function check(bool $condition, string $message, array $replace = []): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['document' => __('erp.'.$message, $replace)]);
        }
    }
}
