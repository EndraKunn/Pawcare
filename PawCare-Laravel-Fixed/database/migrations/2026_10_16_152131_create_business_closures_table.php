<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_closures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->date('closed_date');
            $table->string('reason', 255)->nullable();

            $table->timestamps();

            $table->unique(
                ['business_id', 'closed_date'],
                'business_closed_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_closures');
    }
};