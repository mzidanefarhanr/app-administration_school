<?php

use App\Services\StudentBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Subfase 03a (DEC-20):
     * 1. Tambah kolom student_id additive pada student_rombels (FK ke students.id).
     * 2. Buat student_nik nullable agar siswa tanpa NIK dapat memiliki rombel.
     * 3. Backfill aman kecocokan NIK tunggal tanpa merusak data ambigu/kosong.
     * 4. Enforce maksimal 1 rombel aktif per siswa per tahun ajaran via DB trigger.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('student_rombels', function (Blueprint $table) {
                $table->unsignedBigInteger('student_id')->nullable()->after('version');
                $table->index('student_id');
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->onUpdate('cascade')
                    ->onDelete('restrict');
            });

            DB::statement('ALTER TABLE student_rombels MODIFY student_nik VARCHAR(255) NULL');
        } elseif ($driver === 'sqlite') {
            Schema::dropIfExists('student_rombels_temp');
            DB::statement('CREATE TEMPORARY TABLE student_rombels_temp AS SELECT * FROM student_rombels');
            Schema::dropIfExists('student_rombels');

            Schema::create('student_rombels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('version')->default(1);
                $table->unsignedBigInteger('student_id')->nullable();
                $table->string('student_nik')->nullable();
                $table->unsignedBigInteger('school_rombel');
                $table->unsignedBigInteger('student_status_id')->nullable();
                $table->unsignedBigInteger('student_entry_id')->nullable();
                $table->date('latest_student_entry_date')->nullable();
                $table->string('mutation_education_school_id')->nullable();
                $table->date('latest_student_exit_date')->nullable();
                $table->string('latest_student_exit_school_id')->nullable();
                $table->date('latest_student_graduation_date')->nullable();
                $table->string('latest_information')->nullable();
                $table->boolean('check_dapodik')->nullable();
                $table->string('informations_dapodik')->nullable();
                $table->softDeletes();
                $table->index('deleted_at');
                $table->timestamps();

                $table->index('student_id');
                $table->foreign('student_id')->references('id')->on('students')->onUpdate('cascade')->onDelete('restrict');
                $table->foreign('student_nik')->references('nik')->on('students');
                $table->foreign('school_rombel')->references('id')->on('school_rombels');
                $table->foreign('student_status_id')->references('id')->on('student_statuses');
                $table->foreign('student_entry_id')->references('id')->on('student_entries');
                $table->foreign('mutation_education_school_id')->references('npsn')->on('education_schools');
                $table->foreign('latest_student_exit_school_id')->references('npsn')->on('education_schools');
            });

            DB::statement('INSERT INTO student_rombels (id, version, student_nik, school_rombel, student_status_id, student_entry_id, latest_student_entry_date, mutation_education_school_id, latest_student_exit_date, latest_student_exit_school_id, latest_student_graduation_date, latest_information, check_dapodik, informations_dapodik, deleted_at, created_at, updated_at) SELECT id, version, student_nik, school_rombel, student_status_id, student_entry_id, latest_student_entry_date, mutation_education_school_id, latest_student_exit_date, latest_student_exit_school_id, latest_student_graduation_date, latest_information, check_dapodik, informations_dapodik, deleted_at, created_at, updated_at FROM student_rombels_temp');
            Schema::dropIfExists('student_rombels_temp');
        }

        // Backfill kecocokan NIK tunggal
        $backfillService = new StudentBackfillService;
        $backfillService->backfill();

        // Validasi precondition sebelum trigger dipasang
        $conflicts = $backfillService->findConflictingActiveMemberships();
        if (! empty($conflicts)) {
            throw new RuntimeException(
                'Cannot apply single active rombel constraint: existing data contains '.count($conflicts).' conflicting active rombel memberships in the same academic year.'
            );
        }

        // Pasang trigger penegakan 1 rombel aktif per siswa per tahun
        if ($driver === 'mysql') {
            DB::unprepared("
                CREATE TRIGGER trg_student_rombels_single_active_insert
                BEFORE INSERT ON student_rombels
                FOR EACH ROW
                BEGIN
                    DECLARE v_year_id BIGINT UNSIGNED;
                    DECLARE v_conflict_count INT DEFAULT 0;

                    IF NEW.deleted_at IS NULL AND (NEW.student_status_id = 1 OR NEW.student_status_id IS NULL) AND NEW.latest_student_exit_date IS NULL THEN
                        SELECT school_year_id INTO v_year_id FROM school_rombels WHERE id = NEW.school_rombel;

                        IF v_year_id IS NOT NULL THEN
                            SELECT COUNT(*) INTO v_conflict_count
                            FROM student_rombels sr
                            INNER JOIN school_rombels r ON r.id = sr.school_rombel
                            WHERE r.school_year_id = v_year_id
                              AND sr.deleted_at IS NULL
                              AND (sr.student_status_id = 1 OR sr.student_status_id IS NULL)
                              AND sr.latest_student_exit_date IS NULL
                              AND (
                                  (NEW.student_id IS NOT NULL AND sr.student_id = NEW.student_id)
                                  OR (NEW.student_id IS NULL AND NEW.student_nik IS NOT NULL AND sr.student_nik = NEW.student_nik)
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

                    IF NEW.deleted_at IS NULL AND (NEW.student_status_id = 1 OR NEW.student_status_id IS NULL) AND NEW.latest_student_exit_date IS NULL THEN
                        SELECT school_year_id INTO v_year_id FROM school_rombels WHERE id = NEW.school_rombel;

                        IF v_year_id IS NOT NULL THEN
                            SELECT COUNT(*) INTO v_conflict_count
                            FROM student_rombels sr
                            INNER JOIN school_rombels r ON r.id = sr.school_rombel
                            WHERE r.school_year_id = v_year_id
                              AND sr.id != NEW.id
                              AND sr.deleted_at IS NULL
                              AND (sr.student_status_id = 1 OR sr.student_status_id IS NULL)
                              AND sr.latest_student_exit_date IS NULL
                              AND (
                                  (NEW.student_id IS NOT NULL AND sr.student_id = NEW.student_id)
                                  OR (NEW.student_id IS NULL AND NEW.student_nik IS NOT NULL AND sr.student_nik = NEW.student_nik)
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
            DB::unprepared("
                CREATE TRIGGER trg_student_rombels_single_active_insert
                BEFORE INSERT ON student_rombels
                FOR EACH ROW
                WHEN NEW.deleted_at IS NULL AND (NEW.student_status_id = 1 OR NEW.student_status_id IS NULL) AND NEW.latest_student_exit_date IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'Student already has an active rombel membership in this academic year.')
                    WHERE EXISTS (
                        SELECT 1
                        FROM student_rombels sr
                        JOIN school_rombels r_existing ON r_existing.id = sr.school_rombel
                        JOIN school_rombels r_new ON r_new.id = NEW.school_rombel
                        WHERE r_existing.school_year_id = r_new.school_year_id
                          AND sr.deleted_at IS NULL
                          AND (sr.student_status_id = 1 OR sr.student_status_id IS NULL)
                          AND sr.latest_student_exit_date IS NULL
                          AND (
                              (NEW.student_id IS NOT NULL AND sr.student_id = NEW.student_id)
                              OR (NEW.student_id IS NULL AND NEW.student_nik IS NOT NULL AND sr.student_nik = NEW.student_nik)
                          )
                    );
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_student_rombels_single_active_update
                BEFORE UPDATE ON student_rombels
                FOR EACH ROW
                WHEN NEW.deleted_at IS NULL AND (NEW.student_status_id = 1 OR NEW.student_status_id IS NULL) AND NEW.latest_student_exit_date IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'Student already has an active rombel membership in this academic year.')
                    WHERE EXISTS (
                        SELECT 1
                        FROM student_rombels sr
                        JOIN school_rombels r_existing ON r_existing.id = sr.school_rombel
                        JOIN school_rombels r_new ON r_new.id = NEW.school_rombel
                        WHERE sr.id != NEW.id
                          AND r_existing.school_year_id = r_new.school_year_id
                          AND sr.deleted_at IS NULL
                          AND (sr.student_status_id = 1 OR sr.student_status_id IS NULL)
                          AND sr.latest_student_exit_date IS NULL
                          AND (
                              (NEW.student_id IS NOT NULL AND sr.student_id = NEW.student_id)
                              OR (NEW.student_id IS NULL AND NEW.student_nik IS NOT NULL AND sr.student_nik = NEW.student_nik)
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
            DB::unprepared('DROP TRIGGER IF EXISTS trg_student_rombels_single_active_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_student_rombels_single_active_update');

            Schema::table('student_rombels', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
                $table->dropIndex(['student_id']);
                $table->dropColumn('student_id');
            });
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_student_rombels_single_active_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_student_rombels_single_active_update');

            Schema::dropIfExists('student_rombels_temp');
            DB::statement('CREATE TEMPORARY TABLE student_rombels_temp AS SELECT * FROM student_rombels');
            Schema::dropIfExists('student_rombels');

            Schema::create('student_rombels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('version')->default(1);
                $table->string('student_nik');
                $table->unsignedBigInteger('school_rombel');
                $table->unsignedBigInteger('student_status_id')->nullable();
                $table->unsignedBigInteger('student_entry_id')->nullable();
                $table->date('latest_student_entry_date')->nullable();
                $table->string('mutation_education_school_id')->nullable();
                $table->date('latest_student_exit_date')->nullable();
                $table->string('latest_student_exit_school_id')->nullable();
                $table->date('latest_student_graduation_date')->nullable();
                $table->string('latest_information')->nullable();
                $table->boolean('check_dapodik')->nullable();
                $table->string('informations_dapodik')->nullable();
                $table->softDeletes();
                $table->index('deleted_at');
                $table->timestamps();

                $table->foreign('student_nik')->references('nik')->on('students');
                $table->foreign('school_rombel')->references('id')->on('school_rombels');
                $table->foreign('student_status_id')->references('id')->on('student_statuses');
                $table->foreign('student_entry_id')->references('id')->on('student_entries');
                $table->foreign('mutation_education_school_id')->references('npsn')->on('education_schools');
                $table->foreign('latest_student_exit_school_id')->references('npsn')->on('education_schools');
            });

            DB::statement('INSERT INTO student_rombels (id, version, student_nik, school_rombel, student_status_id, student_entry_id, latest_student_entry_date, mutation_education_school_id, latest_student_exit_date, latest_student_exit_school_id, latest_student_graduation_date, latest_information, check_dapodik, informations_dapodik, deleted_at, created_at, updated_at) SELECT id, version, student_nik, school_rombel, student_status_id, student_entry_id, latest_student_entry_date, mutation_education_school_id, latest_student_exit_date, latest_student_exit_school_id, latest_student_graduation_date, latest_information, check_dapodik, informations_dapodik, deleted_at, created_at, updated_at FROM student_rombels_temp WHERE student_nik IS NOT NULL');
            Schema::dropIfExists('student_rombels_temp');
        }
    }
};
