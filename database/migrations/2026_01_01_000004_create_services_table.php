<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->string('name', 100);
            // Kategori & satuan ditambahkan agar sesuai dengan tampilan mockup (Penitipan/Grooming/Perawatan, per hari/sesi).
            $table->string('category', 30)->default('Penitipan');
            $table->string('unit', 20)->default('sesi');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
