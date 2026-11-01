<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'duration_minutes')) {
            Schema::table('services', function (Blueprint $table) {
                $table->unsignedSmallInteger('duration_minutes')->default(60)->after('unit');
            });
        }

        Schema::table('business_operating_hours', function (Blueprint $table) {
            if (! Schema::hasColumn('business_operating_hours', 'slot_duration_minutes')) {
                $table->unsignedSmallInteger('slot_duration_minutes')->default(60)->after('closes_at');
            }
            if (! Schema::hasColumn('business_operating_hours', 'slot_capacity')) {
                $table->unsignedSmallInteger('slot_capacity')->default(5)->after('slot_duration_minutes');
            }
            if (! Schema::hasColumn('business_operating_hours', 'break_starts_at')) {
                $table->time('break_starts_at')->nullable()->after('slot_capacity');
            }
            if (! Schema::hasColumn('business_operating_hours', 'break_ends_at')) {
                $table->time('break_ends_at')->nullable()->after('break_starts_at');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'booking_end_time')) {
                $table->time('booking_end_time')->nullable()->after('booking_time');
            }
            if (! Schema::hasColumn('bookings', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->default(60)->after('booking_end_time');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'duration_minutes')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn(['booking_end_time', 'duration_minutes']);
            });
        }

        if (Schema::hasColumn('business_operating_hours', 'slot_duration_minutes')) {
            Schema::table('business_operating_hours', function (Blueprint $table) {
                $table->dropColumn(['slot_duration_minutes', 'slot_capacity', 'break_starts_at', 'break_ends_at']);
            });
        }

        if (Schema::hasColumn('services', 'duration_minutes')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('duration_minutes');
            });
        }
    }
};
