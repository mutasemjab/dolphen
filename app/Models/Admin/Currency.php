<?php

namespace App\Models\Admin;

use App\Services\Admin\Context;

class Currency extends Record
{
    protected $table = 'erp_currencies';
    protected $casts = ['exchange_rate' => 'decimal:6', 'decimals' => 'integer', 'active' => 'boolean'];

    private static ?array $cache = null;

    /** All currencies keyed by code, loaded once per request. */
    public static function map(): array
    {
        return self::$cache ??= self::orderBy('id')->get()->keyBy('code')->all();
    }

    public static function forget(): void { self::$cache = null; }

    public static function base(): string { return Context::setting('currency', 'JOD'); }

    public static function decimalsFor(?string $code): int
    {
        $currency = self::map()[$code ?? self::base()] ?? null;
        return $currency ? max(0, min(6, $currency->decimals)) : (int) Context::setting('currency_decimals', '3');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forget());
    }
}
