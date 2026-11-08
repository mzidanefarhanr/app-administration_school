<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ResetStudentsEmployeesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'school:reset-students-employees 
                            {--force : Jalankan penghapusan tanpa prompt konfirmasi}
                            {--no-backup : Lewati pembuatan arsip backup sebelum menghapus}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kosongkan data siswa, guru, dan pegawai secara aman dan bersih untuk persiapan impor baru.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('  PERINTAH PENGOSONGAN DATA SISWA, GURU & PEGAWAI (SEKOLARIO)   ');
        $this->info('================================================================');

        $driver = DB::getDriverName();

        // Hitung data yang ada saat ini
        $counts = [
            'students' => DB::table('students')->count(),
            'student_rombels' => DB::table('student_rombels')->count(),
            'student_mutations' => Schema::hasTable('student_mutations') ? DB::table('student_mutations')->count() : 0,
            'employees' => DB::table('employees')->count(),
            'school_rombels_wali_kelas' => DB::table('school_rombels')->whereNotNull('class_teach_employee_id')->count(),
        ];

        $this->table(
            ['Tabel / Objek Data', 'Jumlah Baris Saat Ini'],
            [
                ['Data Siswa (students)', $counts['students']],
                ['Keanggotaan Rombel Siswa (student_rombels)', $counts['student_rombels']],
                ['Log Mutasi Siswa (student_mutations)', $counts['student_mutations']],
                ['Data Guru & Pegawai (employees)', $counts['employees']],
                ['Relasi Wali Kelas Rombel Terisi', $counts['school_rombels_wali_kelas']],
            ]
        );

        if (! $this->option('force')) {
            $confirm = $this->confirm(
                'PERINGATAN: Seluruh data di atas akan dihapus secara permanen agar siap diimpor dari nol. Lanjutkan?',
                false
            );

            if (! $confirm) {
                $this->warn('Operasi dibatalkan.');
                return 0;
            }
        }

        // 1. BUAT BACKUP ARSIP TERLEBIH DAHULU
        if (! $this->option('no-backup')) {
            $this->info("\n[1/3] Membuat berkas backup pengaman...");
            $backupDir = storage_path('app/backups');
            if (! File::exists($backupDir)) {
                File::makeDirectory($backupDir, 0755, true);
            }

            $timestamp = Carbon::now()->format('Y-m-d_His');
            $backupFile = "{$backupDir}/backup_before_reset_{$timestamp}.json";

            $backupPayload = [
                'created_at' => Carbon::now()->toIso8601String(),
                'students' => DB::table('students')->get(),
                'student_rombels' => DB::table('student_rombels')->get(),
                'student_mutations' => Schema::hasTable('student_mutations') ? DB::table('student_mutations')->get() : [],
                'employees' => DB::table('employees')->get(),
                'school_rombels_wali_kelas' => DB::table('school_rombels')
                    ->whereNotNull('class_teach_employee_id')
                    ->select('id', 'name', 'class_teach_employee_id')
                    ->get(),
            ];

            File::put($backupFile, json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("✓ Backup berhasil disimpan di: {$backupFile}");
        }

        // 2. EKSEKUSI PENGOSONGAN DATA SECARA TERURUT & AMAN
        $this->info("\n[2/3] Mengosongkan data secara terurut dan aman...");

        try {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            }

            // A. Relasi Guru/Pegawai
            $this->line('  - Melepaskan penugasan wali kelas pada school_rombels...');
            DB::table('school_rombels')->update(['class_teach_employee_id' => null]);

            if (Schema::hasTable('teaching_schedule_exceptions')) {
                DB::table('teaching_schedule_exceptions')->delete();
            }
            if (Schema::hasTable('teaching_journals')) {
                DB::table('teaching_journals')->delete();
            }
            if (Schema::hasTable('teaching_schedules')) {
                DB::table('teaching_schedules')->delete();
            }
            if (Schema::hasTable('teaching_assignments')) {
                DB::table('teaching_assignments')->delete();
            }
            if (Schema::hasTable('letter_dispositions')) {
                DB::table('letter_dispositions')->whereNotNull('recipient_employee_id')->update(['recipient_employee_id' => null]);
            }
            if (Schema::hasTable('leave_decisions')) {
                DB::table('leave_decisions')->delete();
            }
            if (Schema::hasTable('leave_requests')) {
                DB::table('leave_requests')->delete();
            }

            // B. Tabel Guru & Pegawai
            $this->line('  - Menghapus data guru dan pegawai (employees)...');
            DB::table('employees')->delete();

            // C. Relasi & Log Siswa
            if (Schema::hasTable('student_mutations')) {
                $this->line('  - Menghapus log mutasi siswa (student_mutations)...');
                DB::table('student_mutations')->delete();
            }
            if (Schema::hasTable('student_locked_corrections')) {
                DB::table('student_locked_corrections')->delete();
            }
            if (Schema::hasTable('student_progression_batches')) {
                DB::table('student_progression_batches')->delete();
            }
            if (Schema::hasTable('data_quality_reviews')) {
                DB::table('data_quality_reviews')->delete();
            }

            $this->line('  - Menghapus keanggotaan rombel siswa (student_rombels)...');
            DB::table('student_rombels')->delete();

            // D. Tabel Utama Siswa
            $this->line('  - Menghapus data siswa (students)...');
            DB::table('students')->delete();

            // 3. RESET AUTO-INCREMENT
            $this->info("\n[3/3] Mereset nilai Auto-Increment...");
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE students AUTO_INCREMENT = 1');
                DB::statement('ALTER TABLE student_rombels AUTO_INCREMENT = 1');
                if (Schema::hasTable('student_mutations')) {
                    DB::statement('ALTER TABLE student_mutations AUTO_INCREMENT = 1');
                }
                DB::statement('ALTER TABLE employees AUTO_INCREMENT = 1');
                if (Schema::hasTable('teaching_assignments')) {
                    DB::statement('ALTER TABLE teaching_assignments AUTO_INCREMENT = 1');
                }
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }

            $this->info("\n✓ SEMUA DATA SISWA, GURU, DAN PEGAWAI BERHASIL DIKOSONGKAN SECARA BERSIH.");
            $this->info("✓ Database sekarang bersih dan siap untuk proses impor data baru.");
            return 0;

        } catch (\Throwable $e) {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }
            $this->error('Terjadi kesalahan saat mengosongkan data: ' . $e->getMessage());
            return 1;
        }
    }
}
