<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class HrSettings
{
    public const DEFAULTS = [
        'latitude' => '', 'longitude' => '', 'radius_meters' => 100,
        'work_days' => [0, 1, 2, 3, 4, 6], 'grace_minutes' => 10,
        'overtime_hour_rate' => '0', 'late_hour_rate' => '0',
    ];

    public static function get(): array
    {
        return array_replace(self::DEFAULTS, json_decode(Context::setting('hr_policy', '{}'), true) ?: []);
    }

    public static function save(array $settings): void
    {
        DB::table('erp_settings')->updateOrInsert(['key' => 'hr_policy'], ['value' => json_encode($settings), 'updated_at' => now()]);
    }
}
