<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('proof_path')->nullable();
            $table->enum('status', ['Belum Dibayar', 'Menunggu Verifikasi', 'Dibayar', 'Ditolak'])->default('Belum Dibayar');
            // Alasan penolakan (tambahan dari ERD, diwajibkan oleh aturan penolakan bukti pembayaran).
            $table->text('reject_reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique('booking_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
