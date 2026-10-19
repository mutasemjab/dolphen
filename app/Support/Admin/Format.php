<?php

namespace App\Support\Admin;

use App\Services\Admin\Context;

class Format
{
    public static function number($value, int $scale = 6): string
    {
        $value = (string) $value;
        $rounded = bcadd($value, (bccomp($value, '0', 6) < 0 ? '-' : '').'0.'.str_repeat('0', $scale).'5', $scale);
        [$whole, $fraction] = array_pad(explode('.', $rounded), 2, '');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);
        return $whole.($scale ? '.'.$fraction : '');
    }
    public static function money($value): string
    {
        return self::number($value, (int) Context::setting('currency_decimals', '3'));
    }
}
