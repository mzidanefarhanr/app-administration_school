<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Batas impor siswa (DEC-25)
    |--------------------------------------------------------------------------
    |
    | Berkas di atas batas ini ditolak (422), bukan dipotong. Commit berjalan
    | dalam satu transaksi: bila satu baris gagal, tidak ada baris yang ditulis.
    |
    */
    'disk' => env('IMPORTS_DISK', 'local'),

    'students' => [
        'template_version' => 1,
        'max_rows' => (int) env('IMPORTS_STUDENTS_MAX_ROWS', 2000),
        'max_size_kb' => (int) env('IMPORTS_STUDENTS_MAX_SIZE_KB', 5120),
        'batch_ttl_minutes' => (int) env('IMPORTS_STUDENTS_TTL_MINUTES', 120),
        'retention_days' => 30,
        'preview_rows' => 50,
        'error_rows' => 200,
    ],
];
