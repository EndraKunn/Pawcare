<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'capacity')) {
                $table->unsignedSmallInteger('capacity')->default(5)->after('duration_minutes')
                    ->comment('kapasitas kuota layanan per slot waktu');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('services', 'capacity')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('capacity');
            });
        }
    }
};
