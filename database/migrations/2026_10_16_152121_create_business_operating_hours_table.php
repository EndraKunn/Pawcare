<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_operating_hours', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            // 1 = Senin, 7 = Minggu
            $table->unsignedTinyInteger('day_of_week');

            $table->boolean('is_closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['business_id', 'day_of_week'],
                'business_day_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_operating_hours');
    }
};