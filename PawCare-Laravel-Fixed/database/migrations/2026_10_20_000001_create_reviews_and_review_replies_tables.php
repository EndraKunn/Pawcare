<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->unique()->constrained('bookings')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'rating']);
                $table->index('customer_id');
            });
        }

        if (! Schema::hasTable('review_replies')) {
            Schema::create('review_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_id')->unique()->constrained('reviews')->cascadeOnDelete();
                $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
                $table->text('reply');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_replies');
        Schema::dropIfExists('reviews');
    }
};
