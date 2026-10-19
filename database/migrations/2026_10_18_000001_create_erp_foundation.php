<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Small business: invoices, stock quantities, and employees.
// Databases that already ran the earlier version retain their historical tables.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('erp_settings', function (Blueprint $t) {
            $t->string('key')->primary(); $t->text('value')->nullable(); $t->timestamps();
        });
        Schema::create('erp_sequences', function (Blueprint $t) {
            $t->id(); $t->string('type', 50); $t->unsignedSmallInteger('year');
            $t->unsignedBigInteger('next_number')->default(1); $t->unique(['type', 'year']);
        });
        foreach (['units', 'warehouses'] as $name) {
            Schema::create('erp_'.$name, function (Blueprint $t) {
                $t->id(); $t->string('code', 50)->unique(); $t->string('name_ar'); $t->string('name_en');
                $t->boolean('active')->default(true); $t->text('notes')->nullable(); $t->timestamps();
            });
        }
        Schema::create('erp_partners', function (Blueprint $t) {
            $t->id(); $t->string('code', 50)->unique(); $t->string('kind', 20)->index();
            $t->string('name_ar'); $t->string('name_en'); $t->string('phone')->nullable();
            $t->text('address')->nullable(); $t->boolean('active')->default(true); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('erp_products', function (Blueprint $t) {
            $t->id(); $t->string('sku', 80)->unique(); $t->string('name_ar'); $t->string('name_en');
            foreach (['base_unit_id', 'purchase_unit_id', 'sales_unit_id'] as $column) {
                $t->foreignId($column)->constrained('erp_units')->restrictOnDelete();
            }
            $t->string('item_type', 30)->default('finished');
            $t->decimal('cost', 24, 6)->default(0); $t->decimal('selling_price', 24, 6)->default(0);
            $t->decimal('minimum_stock', 24, 6)->default(0); $t->boolean('active')->default(true);
            $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('erp_documents', function (Blueprint $t) {
            $t->id(); $t->string('number', 80)->unique(); $t->string('type', 40);
            $t->string('status', 30)->default('posted'); $t->date('date');
            $t->foreignId('partner_id')->nullable()->constrained('erp_partners')->restrictOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained('erp_warehouses')->restrictOnDelete();
            $t->string('currency', 3);
            foreach (['subtotal', 'tax_total', 'total', 'paid'] as $column) { $t->decimal($column, 24, 6)->default(0); }
            $t->text('notes')->nullable(); $t->json('metadata')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('admins')->restrictOnDelete();
            $t->timestamps(); $t->index(['type', 'status', 'date']);
        });
        Schema::create('erp_document_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('document_id')->constrained('erp_documents')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('erp_products')->restrictOnDelete();
            $t->foreignId('unit_id')->constrained('erp_units')->restrictOnDelete();
            foreach (['quantity', 'base_quantity', 'unit_price', 'net', 'total'] as $column) { $t->decimal($column, 24, 6); }
            $t->timestamps();
        });
        Schema::create('erp_stock_movements', function (Blueprint $t) {
            $t->id(); $t->string('event_key', 140)->unique();
            $t->foreignId('document_id')->constrained('erp_documents')->restrictOnDelete();
            $t->foreignId('line_id')->nullable()->constrained('erp_document_lines')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('erp_products')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained('erp_warehouses')->restrictOnDelete();
            $t->foreignId('unit_id')->constrained('erp_units')->restrictOnDelete();
            $t->date('date'); $t->string('kind', 40); $t->decimal('entered_quantity', 24, 6); $t->decimal('quantity', 24, 6);
            // Compatibility columns for databases initialized by the earlier version.
            $t->decimal('value', 24, 6)->default(0); $t->decimal('unit_cost', 24, 6)->default(0);
            $t->timestamps(); $t->index(['product_id', 'warehouse_id', 'date']);
        });
        Schema::create('erp_employees', function (Blueprint $t) {
            $t->id(); $t->string('code', 50)->unique(); $t->string('name_ar'); $t->string('name_en');
            $t->string('job_title')->nullable(); $t->string('phone')->nullable(); $t->date('hire_date');
            $t->boolean('active')->default(true); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('erp_attendance', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('erp_employees')->restrictOnDelete();
            $t->date('date'); $t->time('check_in')->nullable(); $t->time('check_out')->nullable();
            $t->string('status', 20); $t->text('notes')->nullable(); $t->timestamps(); $t->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Invoice and stock records must not be deleted by rollback.');
    }
};
