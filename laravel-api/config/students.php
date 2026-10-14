<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Batas baris ekspor siswa
    |--------------------------------------------------------------------------
    |
    | Jumlah maksimum baris per unduhan CSV siswa. Bila hasil filter melebihi
    | batas ini, ekspor DITOLAK (422) dengan pesan agar pengguna mempersempit
    | filter — bukan memotong hasil secara diam-diam.
    |
    */
    'export_max_rows' => env('STUDENTS_EXPORT_MAX_ROWS', 10000),
];
