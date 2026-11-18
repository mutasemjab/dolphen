<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class HrSettings
{
    public const DEFAULTS = [
        'latitude' => '', 'longitude' => '', 'radius_meters' => 100,
        'work_days' => [0, 1, 2, 3, 4, 6], 'grace_minutes' => 10,
        'overtime_hour_rate' => '0', 'late_hour_rate' => '0',
        // Empty rates fall back to the matching local / regular rate, see overtimeRates().
        'holiday_overtime_hour_rate' => '', 'foreign_overtime_hour_rate' => '', 'foreign_holiday_overtime_hour_rate' => '',
        // Official holidays: [['date' => 'Y-m-d', 'name' => '...'], ...]
        'holidays' => [], 'employee_holiday_punch' => false,
    ];

    public static function get(): array
    {
        return array_replace(self::DEFAULTS, json_decode(Context::setting('hr_policy', '{}'), true) ?: []);
    }

    public static function save(array $settings): void
    {
        DB::table('erp_settings')->updateOrInsert(['key' => 'hr_policy'], ['value' => json_encode($settings), 'updated_at' => now()]);
    }

    /** @return string[] Official holiday dates (Y-m-d). */
    public static function holidayDates(?array $policy = null): array
    {
        return array_values(array_filter(array_column(($policy ?? self::get())['holidays'] ?? [], 'date')));
    }

    /** A day off for overtime purposes: outside the weekly work days, or an official holiday. */
    public static function isDayOff(string $date, ?array $policy = null): bool
    {
        $policy ??= self::get();
        return !in_array(\Carbon\Carbon::parse($date, 'Asia/Amman')->dayOfWeek, $policy['work_days']) || in_array($date, self::holidayDates($policy), true);
    }

    /**
     * Hourly overtime rates for a worker type. A blank holiday rate uses the regular overtime rate,
     * and a blank foreign rate uses the matching local rate.
     * @return array{regular: string, holiday: string}
     */
    public static function overtimeRates(array $policy, string $workerType): array
    {
        $pick = fn ($value, $fallback) => ($value ?? '') === '' ? (string) $fallback : (string) $value;
        $regular = $pick($policy['overtime_hour_rate'] ?? '0', '0');
        $holiday = $pick($policy['holiday_overtime_hour_rate'] ?? '', $regular);
        if ($workerType === 'foreign') {
            return ['regular' => $pick($policy['foreign_overtime_hour_rate'] ?? '', $regular), 'holiday' => $pick($policy['foreign_holiday_overtime_hour_rate'] ?? '', $holiday)];
        }
        return ['regular' => $regular, 'holiday' => $holiday];
    }
}
