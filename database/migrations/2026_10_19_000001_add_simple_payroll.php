<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('erp_employees', fn (Blueprint $t) => $t->decimal('monthly_salary', 24, 6)->default(0));
        Schema::create('erp_payrolls', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('erp_employees')->restrictOnDelete();
            $t->string('month', 7);
            foreach (['salary', 'allowance', 'deduction', 'net'] as $column) { $t->decimal($column, 24, 6)->default(0); }
            $t->date('paid_on')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
            $t->unique(['employee_id', 'month']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Keep employee salary and payment history.');
    }
};
