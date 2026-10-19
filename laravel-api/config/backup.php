<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup Storage Path
    |--------------------------------------------------------------------------
    | Direktori penyimpanan privat untuk arsip backup dan file checksum pendamping.
    | Berada di luar web root publik (storage/app/backups).
    */
    'path' => env('BACKUP_STORAGE_PATH', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | Attachments Source Path
    |--------------------------------------------------------------------------
    | Direktori berkas lampiran privat aplikasi yang diarsipkan bersama database.
    */
    'attachments_path' => env('BACKUP_ATTACHMENTS_PATH', storage_path('app/attachments')),

    /*
    |--------------------------------------------------------------------------
    | Drill Isolated Attachments Path
    |--------------------------------------------------------------------------
    | Direktori terisolasi khusus untuk ekstraksi lampiran saat restore drill.
    | Berbeda dari direktori lampiran aplikasi aktif.
    */
    'drill_attachments_path' => env('BACKUP_DRILL_ATTACHMENTS_PATH', storage_path('app/restore-drill-attachments')),

    /*
    |--------------------------------------------------------------------------
    | Encryption Passphrase
    |--------------------------------------------------------------------------
    | Kunci/passphrase rahasia untuk enkripsi ZIP AES-256.
    | Bersumber dari environment atau secret prompt CLI; tidak masuk ke git.
    */
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | Database Binaries
    |--------------------------------------------------------------------------
    | Lokasi binary CLI mysqldump dan mysql.
    */
    'mysqldump_binary' => env('MYSQLDUMP_PATH', 'C:\xampp\mysql\bin\mysqldump.exe'),
    'mysql_binary' => env('MYSQL_PATH', 'C:\xampp\mysql\bin\mysql.exe'),

    /*
    |--------------------------------------------------------------------------
    | Target Disposable Allowlist untuk Restore Drill (DEC-18)
    |--------------------------------------------------------------------------
    | Hanya database dalam daftar ini yang diizinkan menjadi target restore drill.
    */
    'disposable_databases' => [
        'school_restore_drill',
        'school_restore_drill_uat',
        ':memory:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Operasional yang Dilindungi Keras
    |--------------------------------------------------------------------------
    | Database yang MUTLAK DILARANG dijadikan target restore drill apapun alasannya.
    */
    'forbidden_databases' => [
        'backdata_administrations',
    ],
];
