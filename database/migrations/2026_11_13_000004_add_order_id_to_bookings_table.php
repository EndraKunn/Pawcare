<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'order_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreignId('order_id')->nullable()->after('id')->constrained('orders')->nullOnDelete();
                $table->index(['order_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'order_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign(['order_id']);
                $table->dropIndex(['order_id', 'status']);
                $table->dropColumn('order_id');
            });
        }
    }
};
