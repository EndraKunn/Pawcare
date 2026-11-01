<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            // Pemilik tidak boleh dihapus selama masih memiliki bisnis (histori transaksi aman).
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('business_name', 100);
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            // Info rekening tujuan transfer (tambahan dari ERD, dipakai pada halaman pembayaran).
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
