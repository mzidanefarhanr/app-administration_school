<?php

namespace App\Support;

/**
 * Katalog kemampuan bernama (named abilities).
 *
 * Kemampuan didefinisikan di kode sebagai referensi, tetapi pemetaan
 * peran → kemampuan disimpan di database (tabel abilities / ability_role)
 * agar Admin Sistem dapat mengaturnya tanpa mengubah kode.
 *
 * Aturan: jangan memeriksa type_user_id atau nama peran di controller baru.
 * Periksa kemampuan bernama via Gate/Policy terpusat (lihat AccessServiceProvider).
 */
final class AbilityCatalog
{
    /**
     * Daftar kemampuan inti beserta grup dan sifat sensitif.
     *
     * @return array<string, array{group: string, description: string, sensitive: bool}>
     */
    public static function definitions(): array
    {
        return [
            // System / akses
            'roles.view' => self::def('system', 'Melihat daftar peran.', false),
            'roles.manage' => self::def('system', 'Membuat/mengubah/menghapus peran.', true),
            'abilities.manage' => self::def('system', 'Mengatur kemampuan tiap peran.', true),
            'users.view' => self::def('system', 'Melihat daftar akun.', false),
            'users.manage' => self::def('system', 'Membuat/mengubah/menghapus akun.', true),
            'access.assign' => self::def('system', 'Menetapkan peran kepada pengguna.', true),
            'audit.view' => self::def('system', 'Melihat log aktivitas/audit.', true),
            'backup.manage' => self::def('system', 'Mengelola dan mengunduh backup arsip sistem.', true),

            // Data referensi/master (modul yang sudah tersedia)
            'master.view' => self::def('master', 'Melihat data referensi (gender, agama, dll.).', false),
            'master.manage' => self::def('master', 'Mengubah data referensi.', false),

            // Siswa (modul siswa belum tersedia penuh; kemampuan disiapkan)
            'students.view' => self::def('students', 'Melihat data siswa.', false),
            'students.manage' => self::def('students', 'Menambah/mengubah/menghapus data siswa.', false),
            'students.export' => self::def('students', 'Mengekspor data siswa sesuai lingkup akses.', true),
            'students.import' => self::def('students', 'Mengimpor data siswa dari berkas CSV tervalidasi.', true),

            // Pegawai
            'employees.view' => self::def('employees', 'Melihat data pegawai.', false),
            'employees.manage' => self::def('employees', 'Menambah/mengubah/menghapus data pegawai.', false),
            'employees.export' => self::def('employees', 'Mengekspor data pegawai sesuai lingkup akses.', true),
            'employees.import' => self::def('employees', 'Mengimpor data pegawai dari berkas CSV tervalidasi.', true),

            // Surat (belum tersedia)
            'letters.view' => self::def('letters', 'Melihat surat.', false),
            'letters.manage' => self::def('letters', 'Mengelola surat.', false),
            'letters.approve' => self::def('letters', 'Menyetujui surat.', true),
            'letters.sign' => self::def('letters', 'Menandatangani dokumen surat.', true),

            // Izin (belum tersedia)
            'leaves.view' => self::def('leaves', 'Melihat izin.', false),
            'leaves.manage' => self::def('leaves', 'Mengelola izin.', false),
            'leaves.approve' => self::def('leaves', 'Menyetujui izin.', true),

            // Pembelajaran (belum tersedia)
            'teaching.view' => self::def('teaching', 'Melihat jadwal/jurnal pembelajaran.', false),
            'teaching.manage' => self::def('teaching', 'Mengelola jadwal/jurnal pembelajaran.', false),
        ];
    }

    /**
     * Konfigurasi peran awal (DEC-03, keputusan pemilik 2026-10-15).
     *
     * Ini konfigurasi AWAL, bukan aturan yang dikunci. Admin Sistem dapat
     * mengubahnya melalui UI pengelolaan peran/kemampuan.
     *
     * Nama tampilan peran memakai English (DEC-14). `slug` (identifier) tetap.
     *
     * @return array<string, array{name: string, description: string, protected: bool, abilities: string[]}>
     */
    public static function initialRoles(): array
    {
        return [
            'admin-sistem' => [
                'name' => 'System Administrator',
                'description' => 'Manages accounts, roles, abilities, and system configuration.',
                'protected' => true, // tidak boleh dihapus / kehilangan akses pengelolaan
                'abilities' => [
                    'roles.view', 'roles.manage', 'abilities.manage',
                    'users.view', 'users.manage', 'access.assign', 'audit.view', 'backup.manage',
                    'master.view', 'master.manage',
                    'students.view', 'students.manage', 'students.export', 'students.import',
                    'employees.view', 'employees.manage', 'employees.export', 'employees.import',
                    'letters.view', 'letters.manage',
                    'leaves.view', 'leaves.manage',
                    'teaching.view', 'teaching.manage',
                ],
                // Perhatikan: admin TIDAK diberi letters.approve, letters.sign,
                // atau leaves.approve secara default (sesuai DEC-03).
            ],
            'operator-sekolah' => [
                'name' => 'School Operator',
                'description' => 'Manages school operational data (students, employees, master).',
                'protected' => false,
                'abilities' => [
                    'master.view', 'master.manage',
                    'students.view', 'students.manage', 'students.export', 'students.import',
                    'employees.view', 'employees.manage', 'employees.export', 'employees.import',
                    'letters.view', 'letters.manage',
                    'leaves.view', 'leaves.manage',
                    'teaching.view',
                ],
            ],
            'kepala-sekolah' => [
                'name' => 'Principal',
                'description' => 'Views data, approves letters/leaves, and signs documents.',
                'protected' => false,
                'abilities' => [
                    'master.view',
                    'students.view', 'students.export',
                    'employees.view', 'employees.export',
                    'letters.view', 'letters.approve', 'letters.sign',
                    'leaves.view', 'leaves.approve',
                    'teaching.view',
                    'audit.view',
                ],
            ],
            'wakil-kesiswaan' => [
                'name' => 'Vice Principal for Student Affairs',
                'description' => 'Scope: class data, scheduling, teacher assessment. '
                    .'No export, approval, or signing by default.',
                'protected' => false,
                'abilities' => [
                    'master.view',
                    'students.view', 'students.manage',
                    'employees.view',
                    'teaching.view', 'teaching.manage',
                    'leaves.view',
                    'letters.view',
                ],
            ],
        ];
    }

    /**
     * @return array{group: string, description: string, sensitive: bool}
     */
    private static function def(string $group, string $description, bool $sensitive): array
    {
        return ['group' => $group, 'description' => $description, 'sensitive' => $sensitive];
    }
}
