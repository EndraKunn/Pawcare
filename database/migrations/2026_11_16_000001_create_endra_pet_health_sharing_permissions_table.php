<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            if (! Schema::hasColumn('pets', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('age');
            }
            if (! Schema::hasColumn('pets', 'weight')) {
                $table->decimal('weight', 5, 2)->nullable()->after('birth_date')->comment('berat badan dalam kg');
            }
        });

        if (! Schema::hasTable('pet_health_sharing_permissions')) {
            Schema::create('pet_health_sharing_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pet_health_record_id')->constrained('pet_health_records')->cascadeOnDelete();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('granted_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->unique(['pet_health_record_id', 'business_id'], 'pet_health_share_rec_biz_unique');
                $table->index(['business_id', 'revoked_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_health_sharing_permissions');

        Schema::table('pets', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('pets', 'birth_date')) {
                $drop[] = 'birth_date';
            }
            if (Schema::hasColumn('pets', 'weight')) {
                $drop[] = 'weight';
            }
            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
