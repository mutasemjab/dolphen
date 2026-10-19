<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::table('admins')->select('username')->groupBy('username')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Existing administrator usernames contain duplicates. Resolve them before adding the uniqueness constraint; no accounts were modified.');
        }
        Schema::table('admins', fn (Blueprint $t) => $t->unique('username', 'admins_username_factory_unique'));
    }
    public function down(): void
    {
        Schema::table('admins', fn (Blueprint $t) => $t->dropUnique('admins_username_factory_unique'));
    }
};
