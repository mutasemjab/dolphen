<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('erp_classifications', function (Blueprint $t) {
            $t->id(); $t->string('kind', 20); $t->string('name_ar', 200); $t->string('name_en', 200);
            $t->boolean('active')->default(true); $t->timestamps(); $t->index('kind');
        });
        foreach (['erp_products', 'erp_partners'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->foreignId('classification_id')->nullable()->constrained('erp_classifications')->restrictOnDelete());
        }
        Schema::create('erp_work_shifts', function (Blueprint $t) {
            $t->id(); $t->string('name_ar', 200); $t->string('name_en', 200);
            $t->time('starts_at'); $t->time('ends_at'); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::table('erp_employees', function (Blueprint $t) {
            $t->foreignId('shift_id')->nullable()->constrained('erp_work_shifts')->restrictOnDelete();
            $t->string('username', 100)->nullable()->unique(); $t->string('password')->nullable();
        });
        Schema::table('erp_attendance', function (Blueprint $t) {
            foreach (['scheduled_start', 'scheduled_end', 'check_in_at', 'check_out_at'] as $column) { $t->dateTime($column)->nullable(); }
            foreach (['in_latitude', 'in_longitude', 'out_latitude', 'out_longitude'] as $column) { $t->decimal($column, 10, 7)->nullable(); }
            $t->unsignedInteger('in_accuracy')->nullable(); $t->unsignedInteger('out_accuracy')->nullable();
            $t->string('source', 20)->default('admin');
        });
        Schema::table('erp_payrolls', function (Blueprint $t) {
            foreach (['overtime_pay', 'absence_deduction', 'late_deduction', 'early_deduction', 'advance_deduction'] as $column) { $t->decimal($column, 24, 6)->default(0); }
            $t->json('details')->nullable();
        });
        Schema::create('erp_employee_advances', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('erp_employees')->restrictOnDelete();
            $t->date('date'); $t->decimal('amount', 24, 6); $t->uuid('request_id')->unique();
            $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('erp_payroll_advances', function (Blueprint $t) {
            $t->id(); $t->foreignId('payroll_id')->constrained('erp_payrolls')->cascadeOnDelete();
            $t->foreignId('advance_id')->constrained('erp_employee_advances')->restrictOnDelete();
            $t->decimal('amount', 24, 6); $t->unique(['payroll_id', 'advance_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Keep attendance, employee accounts and salary advance history.');
    }
};
