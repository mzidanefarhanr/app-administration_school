<?php

return [
    /*
    | Batas baris ekspor pegawai (DEC-24 D8).
    |
    | Hasil filter yang melebihi batas DITOLAK 422 (bukan dipotong), agar operator
    | mempersempit filter terlebih dahulu.
    */
    'export_max_rows' => env('EMPLOYEES_EXPORT_MAX_ROWS', 10000),
];
