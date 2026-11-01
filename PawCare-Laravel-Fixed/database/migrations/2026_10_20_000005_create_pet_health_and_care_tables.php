<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pets', 'share_health_with_business')) {
            Schema::table('pets', function (Blueprint $table) {
                $table->boolean('share_health_with_business')->default(true)->after('archived_at');
            });
        }

        if (! Schema::hasTable('pet_health_records')) {
            Schema::create('pet_health_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
                $table->string('record_type', 30); // vaccination, allergy, condition, medication, note
                $table->string('title', 150);
                $table->date('recorded_date')->nullable();
                $table->date('next_due_date')->nullable();
                $table->text('notes')->nullable();
                $table->string('attachment_path', 255)->nullable();
                $table->string('attachment_name', 150)->nullable();
                $table->timestamps();

                $table->index(['pet_id', 'record_type']);
                $table->index('customer_id');
            });
        }

        if (! Schema::hasTable('pet_care_notes')) {
            Schema::create('pet_care_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('pet_id')->constrained('pets')->restrictOnDelete();
                $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->text('care_summary');
                $table->text('recommendations')->nullable();
                $table->boolean('visible_to_customer')->default(true);
                $table->timestamps();

                $table->index(['pet_id', 'business_id']);
                $table->index('booking_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_care_notes');
        Schema::dropIfExists('pet_health_records');

        if (Schema::hasColumn('pets', 'share_health_with_business')) {
            Schema::table('pets', function (Blueprint $table) {
                $table->dropColumn('share_health_with_business');
            });
        }
    }
};
