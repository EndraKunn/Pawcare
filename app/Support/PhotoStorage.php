<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Penyimpanan foto unggahan (sampul bisnis & foto hewan) di public/uploads. */
class PhotoStorage
{
    public const DISK = 'pawcare_uploads';

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** Aturan validasi bersama. SVG dan file teks yang menyamar sebagai gambar ditolak. */
    public static function rules(int $maxKb): array
    {
        return [
            'nullable',
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:'.$maxKb,
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $realPath = $value->getRealPath();
                if ($realPath && is_file($realPath) && filesize($realPath) > 0) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $actualMime = $finfo->file($realPath) ?: '';

                    if (! in_array($actualMime, self::ALLOWED_MIMES, true)) {
                        $fail('File yang diunggah harus berupa gambar JPG, PNG, atau WebP yang valid.');
                    }
                }
            },
        ];
    }

    /** Simpan dengan nama acak; mengembalikan path relatif, mis. "pets/abc123.jpg". */
    public static function store(UploadedFile $file, string $directory): string
    {
        $path = Storage::disk(self::DISK)->putFile($directory, $file);

        if ($path === false) {
            throw new \RuntimeException('Foto gagal disimpan. Periksa izin tulis folder public/uploads.');
        }

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path && ! str_contains($path, '..')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
