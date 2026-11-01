<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        // Privat: dipakai untuk bukti pembayaran (tidak bisa diakses langsung lewat URL).
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL', 'http://localhost').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
        // Foto unggahan (sampul bisnis & foto hewan). Langsung di public/uploads,
        // jadi tidak membutuhkan `storage:link` (aman untuk Windows tanpa hak admin).
        'pawcare_uploads' => [
            'driver' => 'local',
            'root' => public_path('uploads'),
            'url' => env('APP_URL', 'http://localhost').'/uploads',
            'visibility' => 'public',
            'throw' => false,
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
