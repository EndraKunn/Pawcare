<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 100);
            $table->enum('species', ['Anjing', 'Kucing', 'Kelinci', 'Hamster', 'Burung', 'Reptil']);
            $table->string('breed', 100)->nullable();
            $table->unsignedSmallInteger('age')->nullable()->comment('umur dalam bulan');
            $table->enum('gender', ['Jantan', 'Betina']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
