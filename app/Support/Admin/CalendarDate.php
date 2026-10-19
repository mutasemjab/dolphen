<?php

namespace App\Support\Admin;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/** Persist calendar dates as YYYY-MM-DD on both MySQL and SQLite. */
class CalendarDate implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return $value === null ? null : Carbon::parse($value)->startOfDay();
    }
    public function set($model, string $key, $value, array $attributes)
    {
        return $value === null ? null : Carbon::parse($value)->format('Y-m-d');
    }
}
