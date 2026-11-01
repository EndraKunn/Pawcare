<?php

if (! function_exists('rupiah')) {
    /** Format angka menjadi "Rp 85.000". */
    function rupiah(float|int|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}

if (! function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: '?';
    }
}

if (! function_exists('pawcare_img')) {
    /** URL foto statis (public/images/pawcare/<slot>.*) atau ilustrasi fallback. */
    function pawcare_img(string $slot, string $fallbackFile): string
    {
        return \App\Support\PawcareImage::resolve($slot, $fallbackFile);
    }
}

if (! function_exists('pawcare_fallback')) {
    function pawcare_fallback(string $fallbackFile): string
    {
        return \App\Support\PawcareImage::fallback($fallbackFile);
    }
}
