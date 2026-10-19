<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('erp_employees', fn (Blueprint $t) => $t->decimal('annual_leave_days', 12, 6)->default(0));
        Schema::create('erp_leave_balances', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('erp_employees')->restrictOnDelete();
            $t->unsignedSmallInteger('year'); $t->decimal('allowance', 12, 6); $t->timestamps(); $t->unique(['employee_id', 'year']);
        });
        Schema::create('erp_leave_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('erp_employees')->restrictOnDelete();
            $t->uuid('request_id')->unique(); $t->string('type', 20); $t->string('status', 20)->default('pending');
            $t->date('start_date'); $t->date('end_date'); $t->time('from_time')->nullable(); $t->time('to_time')->nullable();
            $t->unsignedSmallInteger('year'); $t->decimal('charged_days', 12, 6); $t->json('days'); $t->text('reason');
            $t->foreignId('reviewed_by')->nullable()->constrained('admins')->restrictOnDelete();
            $t->dateTime('reviewed_at')->nullable(); $t->text('review_note')->nullable(); $t->timestamps();
            $t->index(['employee_id', 'year', 'status']);
        });
    }

    public function down(): void { throw new RuntimeException('Keep annual leave balances and request history.'); }
};
