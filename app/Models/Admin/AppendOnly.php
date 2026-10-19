<?php

namespace App\Models\Admin;

trait AppendOnly
{
    protected static function bootAppendOnly(): void
    {
        static::updating(function () { throw new \LogicException('Posted ledger records are append-only. Use a reversal.'); });
        static::deleting(function () { throw new \LogicException('Posted ledger records are append-only. Use a reversal.'); });
    }
}
