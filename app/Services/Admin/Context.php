<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Support\Admin\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class Context
{
    public static function authorize(Admin $actor, string $permission): void
    {
        Gate::forUser($actor)->authorize('erp.'.$permission);
    }

    public static function setting(string $key, string $default = ''): string
    {
        return (string) (DB::table('erp_settings')->where('key', $key)->value('value') ?? $default);
    }

    public static function lock(): void
    {
        D::check(DB::transactionLevel() > 0, 'transaction_required');
        D::check((bool) DB::table('erp_settings')->where('key', 'posting_mutex')->lockForUpdate()->first(), 'setup_required');
    }

    public static function number(string $type, string $date): string
    {
        self::lock();
        $year = (int) substr($date, 0, 4);
        DB::table('erp_sequences')->insertOrIgnore(['type' => $type, 'year' => $year, 'next_number' => 1]);
        $row = DB::table('erp_sequences')->where('type', $type)->where('year', $year)->lockForUpdate()->first();
        DB::table('erp_sequences')->where('id', $row->id)->increment('next_number');
        $prefix = ['sales_invoice' => 'SI', 'purchase_invoice' => 'PI', 'adjustment' => 'ST', 'proforma_invoice' => 'PF', 'receipt_voucher' => 'GR', 'issue_voucher' => 'GI'][$type];
        return $prefix.'-'.$year.'-'.str_pad((string) $row->next_number, 6, '0', STR_PAD_LEFT);
    }

    public static function audit(Admin $actor, string $action, $subject, array $details = []): void
    {
        Log::info($action, ['admin_id' => $actor->id, 'record_id' => $subject->id] + $details);
    }
}
