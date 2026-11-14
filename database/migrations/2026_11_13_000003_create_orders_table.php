<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 64);
            $table->char('request_hash', 64);
            $table->string('status', 40)->default('Menunggu Pembayaran');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'idempotency_key'], 'orders_customer_idempotency_unique');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
