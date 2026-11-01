<?php

namespace App\Support;

/**
 * Pencari gambar PawCare.
 *
 * Urutan pencarian untuk setiap gambar:
 *   1. Foto unggahan pengguna (public/uploads/...), bila ada di database & file-nya ada.
 *   2. Foto statis lokal di public/images/pawcare/<slot>.(webp|jpg|jpeg|png).
 *   3. Ilustrasi fallback bawaan di public/images/pawcare/fallback/*.svg.
 * Di sisi browser, komponen <x-photo> menambahkan onerror -> fallback jika URL gagal dimuat.
 */
class PawcareImage
{
    public const EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png'];

    public const COVER_COUNT = 5;

    public const SPECIES = [
        'Anjing' => 'anjing', 'Kucing' => 'kucing', 'Kelinci' => 'kelinci',
        'Hamster' => 'hamster', 'Burung' => 'burung', 'Reptil' => 'reptil',
    ];

    public const CATEGORIES = [
        'Penitipan' => 'penitipan', 'Grooming' => 'grooming', 'Perawatan' => 'perawatan',
    ];

    public static function root(): string
    {
        return public_path('images/pawcare');
    }

    /** URL foto statis untuk $slot (mis. "hero/hero"), atau null jika file belum ditaruh. */
    public static function photo(string $slot): ?string
    {
        foreach (self::EXTENSIONS as $ext) {
            $relative = 'images/pawcare/'.$slot.'.'.$ext;
            $file = public_path($relative);
            if (is_file($file)) {
                return asset($relative).'?v='.filemtime($file);
            }
        }

        return null;
    }

    public static function fallback(string $file): string
    {
        return asset('images/pawcare/fallback/'.$file);
    }

    /** Foto statis jika ada, kalau tidak ilustrasi fallback. */
    public static function resolve(string $slot, string $fallbackFile): string
    {
        return self::photo($slot) ?? self::fallback($fallbackFile);
    }

    /** URL foto unggahan (disk pawcare_uploads), atau null jika kosong/file hilang. */
    public static function upload(?string $path): ?string
    {
        if (! $path || str_contains($path, '..')) {
            return null;
        }
        $relative = 'uploads/'.ltrim($path, '/');
        $file = public_path($relative);

        return is_file($file) ? asset($relative).'?v='.filemtime($file) : null;
    }

    public static function coverIndex(int $businessId): int
    {
        return ($businessId % self::COVER_COUNT) + 1;
    }

    public static function speciesSlug(?string $species): string
    {
        return self::SPECIES[$species] ?? 'anjing';
    }

    public static function categorySlug(?string $category): ?string
    {
        return self::CATEGORIES[$category] ?? null;
    }

    /**
     * Daftar slot gambar statis (dipakai perintah `php artisan pawcare:images`).
     *
     * @return array<int,array{slot:string,fallback:string,label:string}>
     */
    public static function slots(): array
    {
        $slots = [['slot' => 'hero/hero', 'fallback' => 'hero.svg', 'label' => 'Foto utama beranda']];
        foreach (self::CATEGORIES as $name => $slug) {
            $slots[] = ['slot' => 'services/'.$slug, 'fallback' => 'service-'.$slug.'.svg', 'label' => 'Layanan '.$name];
        }
        for ($i = 1; $i <= self::COVER_COUNT; $i++) {
            $slots[] = ['slot' => 'businesses/cover-'.$i, 'fallback' => 'cover-'.$i.'.svg', 'label' => 'Foto sampul bisnis #'.$i];
        }
        foreach (self::SPECIES as $name => $slug) {
            $slots[] = ['slot' => 'pets/'.$slug, 'fallback' => 'pet-'.$slug.'.svg', 'label' => 'Foto hewan: '.$name];
        }

        return $slots;
    }
}
