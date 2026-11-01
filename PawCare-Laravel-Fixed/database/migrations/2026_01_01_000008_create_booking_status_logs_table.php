<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['Menunggu', 'Dikonfirmasi', 'Dalam Proses', 'Selesai', 'Dibatalkan']);
            $table->text('note')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['booking_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_logs');
    }
};
