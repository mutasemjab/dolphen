<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('erp_products', fn (Blueprint $t) => $t->foreignId('warehouse_id')->nullable()->constrained('erp_warehouses')->restrictOnDelete());
        // With a single warehouse the assignment is unambiguous; otherwise each product is assigned on its next edit.
        $warehouses = DB::table('erp_warehouses')->pluck('id');
        if ($warehouses->count() === 1) { DB::table('erp_products')->whereNull('warehouse_id')->update(['warehouse_id' => $warehouses->first()]); }

        // Links a stock voucher to its invoice, and a sales invoice to the pro forma it was converted from.
        Schema::table('erp_documents', fn (Blueprint $t) => $t->foreignId('source_id')->nullable()->constrained('erp_documents')->restrictOnDelete());

        Schema::table('erp_employees', fn (Blueprint $t) => $t->string('worker_type', 20)->default('local'));
        Schema::table('erp_payrolls', fn (Blueprint $t) => $t->decimal('holiday_overtime_pay', 24, 6)->default(0));
    }

    public function down(): void
    {
        throw new RuntimeException('Keep product warehouses, worker types and payroll history.');
    }
};
