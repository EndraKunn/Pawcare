<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            // restrictOnDelete: histori transaksi tidak ikut terhapus bila master data dihapus.
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('pet_id')->constrained('pets')->restrictOnDelete();
            $table->date('booking_date');
            $table->time('booking_time');
            $table->enum('status', ['Menunggu', 'Dikonfirmasi', 'Dalam Proses', 'Selesai', 'Dibatalkan'])->default('Menunggu');
            $table->decimal('total_price', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('booking_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
