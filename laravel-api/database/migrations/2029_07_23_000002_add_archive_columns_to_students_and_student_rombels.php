<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Subfase 03b (DEC-21):
     * 1. Tambah kolom archive_reason pada students.
     * 2. Tambah kolom archived_at dan archive_reason pada student_rombels untuk
     *    pengarsipan administratif terpisah dari mutasi/tanggal keluar.
     * 3. Perbarui trigger single active rombel untuk memeriksa archived_at IS NULL
     *    dan menolak keanggotaan aktif baru untuk siswa terarsip.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // 1. Tambah archive_reason pada students
        if ($driver === 'mysql') {
            Schema::table('students', function (Blueprint $table) {
                $table->string('archive_reason')->nullable()->after('version');
            });
        } elseif ($driver === 'sqlite') {
            Schema::table('students', function (Blueprint $table) {
                $table->string('archive_reason')->nullable();
            });
        }

        // 2. Tambah archived_at dan archive_reason pada student_rombels
        if ($driver === 'mysql') {
            Schema::table('student_rombels', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('latest_information');
                $table->string('archive_reason')->nullable()->after('archived_at');
                $table->index('archived_at');
            });
        } elseif ($driver === 'sqlite') {
            Schema::table('student_rombels', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable();
                $table->string('archive_reason')->nullable();
                $table->index('archived_at');
            });
        }

        // 3. Perbarui trigger single active rombel
        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_student_rombels_single_active_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_student_rombels_single_active_update');

            DB::unprepared("
                CREATE TRIGGER trg_student_rombels_single_active_insert
                BEFORE INSERT ON student_rombels
                FOR EACH ROW
                BEGIN
                    DECLARE v_year_id BIGINT UNSIGNED;
                    DECLARE v_conflict_count INT DEFAULT 0;
                    DECLARE v_locked_id BIGINT UNSIGNED;
                    DECLARE v_student_deleted_at TIMESTAMP;
                    DECLARE v_eff_student_id BIGINT UNSIGNED;
                    DECLARE v_eff_student_nik VARCHAR(255);

                    -- Hanya evaluasi jika keanggotaan berstatus aktif (bukan soft delete, bukan archived_at, bukan keluar)
                    IF NEW.deleted_at IS NULL AND NEW.archived_at IS NULL AND (NEW.student_status_id = 1 OR NEW.student_status_id IS NULL) AND NEW.latest_student_exit_date IS NULL THEN
                        SET v_eff_student_id = NEW.student_id;
                        SET v_eff_student_nik = NEW.student_nik;

                        IF v_eff_student_id IS NULL AND v_eff_student_nik IS NOT NULL THEN
                            SELECT id INTO v_eff_student_id FROM students WHERE nik = v_eff_student_nik LIMIT 1;
                        END IF;

                        IF v_eff_student_nik IS NULL AND v_eff_student_id IS NOT NULL THEN
                            SELECT nik INTO v_eff_student_nik FROM students WHERE id = v_eff_student_id LIMIT 1;
                        END IF;

                        -- Kunci baris siswa (InnoDB row lock) dan periksa apakah siswa terarsip (DEC-21)
                        IF v_eff_student_id IS NOT NULL THEN
                            SELECT id, deleted_at INTO v_locked_id, v_student_deleted_at FROM students WHERE id = v_eff_student_id FOR UPDATE;
                            IF v_student_deleted_at IS NOT NULL THEN
                                SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'Cannot create active rombel membership for an archived student.';
                            END IF;
                        END IF;

                        SELECT school_year_id INTO v_year_id FROM school_rombels WHERE id = NEW.school_rombel;

                        IF v_year_id IS NOT NULL THEN
                            SELECT COUNT(*) INTO v_conflict_count
                            FROM student_rombels sr
                            INNER JOIN school_rombels r ON r.id = sr.school_rombel
                            WHERE r.school_year_id = v_year_id
                              AND sr.deleted_at IS NULL
                              AND sr.archived_at IS NULL
                              AND (sr.student_status_id = 1 OR sr.student_status_id IS NULL)
                              AND sr.latest_student_exit_date IS NULL
                              AND (
                                  (v_eff_student_id IS NOT NULL AND sr.student_id = v_eff_student_id)
                                  OR (v_eff_student_nik IS NOT NULL AND sr.student_nik = v_eff_student_nik)
                                  OR (v_eff_student_id IS NOT NULL AND sr.student_id IS NULL AND sr.student_nik IS NOT NULL
                                      AND sr.student_nik = v_eff_student_nik)
                                  OR (v_eff_student_nik IS NOT NULL AND sr.student_id IS NOT NULL AND sr.student_nik IS NULL
                                      AND sr.student_id = v_eff_student_id)
                              );

                            IF v_conflict_count > 0 THEN
                                SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'Student already has an active rombel membership in this academic year.';
                            END IF;
                        END IF;
                    END IF;
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_student_rombels_single_active_update
                BEFORE UPDATE ON student_rombels
                FOR EACH ROW
                BEGIN
                    DECLARE v_year_id BIGINT UNSIGNED;
                    DECLARE v_conflict_count INT DEFAULT 0;
                    DECLARE v_locked_id BIGINT UNSIGNED;
                    DECLARE v_student_deleted_at TIMESTAMP;
                    DECLARE v_eff_student_id BIGINT UNSIGNED;
                    DECLARE v_eff_student_nik VARCHAR(255);

                    -- Hanya evaluasi jika keanggotaan berstatus aktif (bukan soft delete, bukan archived_at, bukan keluar)
                    IF NEW.deleted_at IS NULL AND NEW.archived_at IS NULL AND (NEW.student_status_id = 1 OR NEW.student_status_id IS NULL) AND NEW.latest_student_exit_date IS NULL THEN
                        SET v_eff_student_id = NEW.student_id;
                        SET v_eff_student_nik = NEW.student_nik;

                        IF v_eff_student_id IS NULL AND v_eff_student_nik IS NOT NULL THEN
                            SELECT id INTO v_eff_student_id FROM students WHERE nik = v_eff_student_nik LIMIT 1;
                        END IF;

                        IF v_eff_student_nik IS NULL AND v_eff_student_id IS NOT NULL THEN
                            SELECT nik INTO v_eff_student_nik FROM students WHERE id = v_eff_student_id LIMIT 1;
                        END IF;

                        -- Kunci baris siswa (InnoDB row lock) dan periksa apakah siswa terarsip (DEC-21)
                        IF v_eff_student_id IS NOT NULL THEN
                            SELECT id, deleted_at INTO v_locked_id, v_student_deleted_at FROM students WHERE id = v_eff_student_id FOR UPDATE;
                            IF v_student_deleted_at IS NOT NULL THEN
                                SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'Cannot create active rombel membership for an archived student.';
                            END IF;
                        END IF;

                        SELECT school_year_id INTO v_year_id FROM school_rombels WHERE id = NEW.school_rombel;

                        IF v_year_id IS NOT NULL THEN
                            SELECT COUNT(*) INTO v_conflict_count
                            FROM student_rombels sr
                            INNER JOIN school_rombels r ON r.id = sr.school_rombel
                            WHERE r.school_year_id = v_year_id
                              AND sr.id != NEW.id
                              AND sr.deleted_at IS NULL
                              AND sr.archived_at IS NULL
                              AND (sr.student_status_id = 1 OR sr.student_status_id IS NULL)
                              AND sr.latest_student_exit_date IS NULL
                              AND (
                                  (v_eff_student_id IS NOT NULL AND sr.student_id = v_eff_student_id)
                                  OR (v_eff_student_nik IS NOT NULL AND sr.student_nik = v_eff_student_nik)
                                  OR (v_eff_student_id IS NOT NULL AND sr.student_id IS NULL AND sr.student_nik IS NOT NULL
                                      AND sr.student_nik = v_eff_student_nik)
                                  OR (v_eff_student_nik IS NOT NULL AND sr.student_id IS NOT NULL AND sr.student_nik IS NULL
                                      AND sr.student_id = v_eff_student_id)
                              );

                            IF v_conflict_count > 0 THEN
                                SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'Student already has an active rombel membership in this academic year.';
                            END IF;
                        END IF;
                    END IF;
                END
            ");
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_student_rombels_single_active_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_student_rombels_single_active_update');

            DB::unprepared("
                CREATE TRIGGER trg_student_rombels_single_active_insert
                BEFORE INSERT ON student_rombels
                FOR EACH ROW
                WHEN NEW.deleted_at IS NULL AND NEW.archived_at IS NULL AND (NEW.student_status_id = 1 OR NEW.student_status_id IS NULL) AND NEW.latest_student_exit_date IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'Cannot create active rombel membership for an archived student.')
                    WHERE EXISTS (
                        SELECT 1 FROM students s
                        WHERE (
                            (NEW.student_id IS NOT NULL AND s.id = NEW.student_id)
                            OR (NEW.student_nik IS NOT NULL AND s.nik = NEW.student_nik)
                        ) AND s.deleted_at IS NOT NULL
                    );

                    SELECT RAISE(ABORT, 'Student already has an active rombel membership in this academic year.')
                    WHERE EXISTS (
                        SELECT 1
                        FROM student_rombels sr
                        JOIN school_rombels r_existing ON r_existing.id = sr.school_rombel
                        JOIN school_rombels r_new ON r_new.id = NEW.school_rombel
                        WHERE r_existing.school_year_id = r_new.school_year_id
                          AND sr.deleted_at IS NULL
                          AND sr.archived_at IS NULL
                          AND (sr.student_status_id = 1 OR sr.student_status_id IS NULL)
                          AND sr.latest_student_exit_date IS NULL
                          AND (
                              (NEW.student_id IS NOT NULL AND sr.student_id = NEW.student_id)
                              OR (NEW.student_nik IS NOT NULL AND sr.student_nik = NEW.student_nik)
                              OR (NEW.student_id IS NOT NULL AND sr.student_id IS NULL AND sr.student_nik IS NOT NULL
                                  AND sr.student_nik = (SELECT nik FROM students WHERE id = NEW.student_id))
                              OR (NEW.student_nik IS NOT NULL AND sr.student_id IS NOT NULL AND sr.student_nik IS NULL
                                  AND sr.student_id = (SELECT id FROM students WHERE nik = NEW.student_nik))
                          )
                    );
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_student_rombels_single_active_update
                BEFORE UPDATE ON student_rombels
                FOR EACH ROW
                WHEN NEW.deleted_at IS NULL AND NEW.archived_at IS NULL AND (NEW.student_status_id = 1 OR NEW.student_status_id IS NULL) AND NEW.latest_student_exit_date IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'Cannot create active rombel membership for an archived student.')
                    WHERE EXISTS (
                        SELECT 1 FROM students s
                        WHERE (
                            (NEW.student_id IS NOT NULL AND s.id = NEW.student_id)
                            OR (NEW.student_nik IS NOT NULL AND s.nik = NEW.student_nik)
                        ) AND s.deleted_at IS NOT NULL
                    );

                    SELECT RAISE(ABORT, 'Student already has an active rombel membership in this academic year.')
                    WHERE EXISTS (
                        SELECT 1
                        FROM student_rombels sr
                        JOIN school_rombels r_existing ON r_existing.id = sr.school_rombel
                        JOIN school_rombels r_new ON r_new.id = NEW.school_rombel
                        WHERE sr.id != NEW.id
                          AND r_existing.school_year_id = r_new.school_year_id
                          AND sr.deleted_at IS NULL
                          AND sr.archived_at IS NULL
                          AND (sr.student_status_id = 1 OR sr.student_status_id IS NULL)
                          AND sr.latest_student_exit_date IS NULL
                          AND (
                              (NEW.student_id IS NOT NULL AND sr.student_id = NEW.student_id)
                              OR (NEW.student_nik IS NOT NULL AND sr.student_nik = NEW.student_nik)
                              OR (NEW.student_id IS NOT NULL AND sr.student_id IS NULL AND sr.student_nik IS NOT NULL
                                  AND sr.student_nik = (SELECT nik FROM students WHERE id = NEW.student_id))
                              OR (NEW.student_nik IS NOT NULL AND sr.student_id IS NOT NULL AND sr.student_nik IS NULL
                                  AND sr.student_id = (SELECT id FROM students WHERE nik = NEW.student_nik))
                          )
                    );
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('student_rombels', function (Blueprint $table) {
                $table->dropIndex(['archived_at']);
                $table->dropColumn(['archived_at', 'archive_reason']);
            });

            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('archive_reason');
            });
        } elseif ($driver === 'sqlite') {
            Schema::table('student_rombels', function (Blueprint $table) {
                $table->dropColumn(['archived_at', 'archive_reason']);
            });

            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('archive_reason');
            });
        }
    }
};
