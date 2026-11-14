<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->boolean('pre_order_enabled')->default(false);
            $table->unsignedSmallInteger('pre_order_max_days')->default(180);
            $table->unsignedSmallInteger('pre_order_slot_capacity')->default(5);
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['pre_order_enabled', 'pre_order_max_days', 'pre_order_slot_capacity']);
        });
    }
};
