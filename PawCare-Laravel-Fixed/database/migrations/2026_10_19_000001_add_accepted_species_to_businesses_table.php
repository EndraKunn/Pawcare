<?php

use App\Models\Pet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('businesses', 'accepted_species')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->json('accepted_species')->nullable()->after('description');
            });
        }

        // Backfill data jenis hewan yang diterima pada bisnis yang sudah ada tanpa merusak data lama.
        $businesses = DB::table('businesses')->get(['id', 'business_name', 'description', 'accepted_species']);

        foreach ($businesses as $biz) {
            if ($biz->accepted_species !== null && $biz->accepted_species !== '') {
                continue;
            }

            $serviceTexts = DB::table('services')
                ->where('business_id', $biz->id)
                ->pluck('name')
                ->implode(' ');

            $haystack = mb_strtolower($biz->business_name.' '.($biz->description ?? '').' '.$serviceTexts);

            $detected = [];
            foreach (Pet::SPECIES as $sp) {
                if (str_contains($haystack, mb_strtolower($sp))) {
                    $detected[] = $sp;
                }
            }

            if (empty($detected)) {
                $bookedSpecies = DB::table('bookings')
                    ->join('pets', 'pets.id', '=', 'bookings.pet_id')
                    ->where('bookings.business_id', $biz->id)
                    ->distinct()
                    ->pluck('pets.species')
                    ->filter(fn ($sp) => in_array($sp, Pet::SPECIES, true))
                    ->values()
                    ->all();

                $detected = ! empty($bookedSpecies)
                    ? array_values(array_intersect(Pet::SPECIES, $bookedSpecies))
                    : ['Anjing', 'Kucing'];
            }

            DB::table('businesses')
                ->where('id', $biz->id)
                ->update(['accepted_species' => json_encode(array_values($detected), JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('businesses', 'accepted_species')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('accepted_species');
            });
        }
    }
};
