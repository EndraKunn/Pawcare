<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('business_closures', 'is_active')) return;
        Schema::table('business_closures', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
        });
    }
    public function down(): void
    {
        if (Schema::hasColumn('business_closures', 'is_active')) {
            Schema::table('business_closures', fn (Blueprint $table) => $table->dropColumn('is_active'));
        }
    }
};
