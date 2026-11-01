<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'last_message_at']);
                $table->index(['customer_id', 'last_message_at']);
                $table->index('booking_id');
            });
        }

        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
                $table->index(['conversation_id', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
