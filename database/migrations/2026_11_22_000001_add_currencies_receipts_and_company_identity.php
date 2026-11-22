<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('erp_currencies', function (Blueprint $t) {
            $t->id(); $t->string('code', 3)->unique(); $t->string('name_ar'); $t->string('name_en'); $t->string('symbol', 10)->nullable();
            $t->unsignedTinyInteger('decimals')->default(2);
            // Value of one unit in the base currency (the `currency` setting, JOD).
            $t->decimal('exchange_rate', 24, 6)->default(1); $t->boolean('active')->default(true); $t->timestamps();
        });
        $now = now();
        DB::table('erp_currencies')->insert([
            ['code' => 'JOD', 'name_ar' => 'دينار أردني', 'name_en' => 'Jordanian Dinar', 'symbol' => 'د.أ', 'decimals' => 3, 'exchange_rate' => '1', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'USD', 'name_ar' => 'دولار أمريكي', 'name_en' => 'US Dollar', 'symbol' => '$', 'decimals' => 2, 'exchange_rate' => '0.709', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        // Rate of the document currency to the base currency on the document date. Existing documents are in the base currency.
        Schema::table('erp_documents', fn (Blueprint $t) => $t->decimal('exchange_rate', 24, 6)->default(1));

        foreach (['company_name_ar' => 'مصنع عايده الحواجرة للبلاستيك', 'company_name_en' => 'Aida Al-Hawajreh Plastic Factory', 'tax_number' => '100335976'] as $key => $value) {
            DB::table('erp_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Keep currencies and multi-currency documents.');
    }
};
