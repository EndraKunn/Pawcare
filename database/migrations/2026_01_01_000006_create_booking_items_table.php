<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            // Snapshot harga saat booking dibuat.
            $table->decimal('price', 12, 2);
            // Jumlah hari/sesi (tambahan dari ERD, sesuai mockup "x 3 hari").
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['booking_id', 'service_id']);
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
