<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaruan visual: menambah kolom foto (nullable) tanpa mengubah/menghapus data lama.
 * - businesses.cover_photo : path relatif foto sampul di public/uploads
 * - pets.photo             : path relatif foto hewan di public/uploads
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('businesses', 'cover_photo')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->string('cover_photo', 255)->nullable()->after('description');
            });
        }

        if (! Schema::hasColumn('pets', 'photo')) {
            Schema::table('pets', function (Blueprint $table) {
                $table->string('photo', 255)->nullable()->after('gender');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('businesses', 'cover_photo')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('cover_photo');
            });
        }

        if (Schema::hasColumn('pets', 'photo')) {
            Schema::table('pets', function (Blueprint $table) {
                $table->dropColumn('photo');
            });
        }
    }
};
