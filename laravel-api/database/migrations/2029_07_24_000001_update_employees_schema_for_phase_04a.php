<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations (Subphase 04a, DEC-04).
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->unique('user_id');
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->timestamp('user_id_resolved_at')->nullable()->after('user_id');
                $table->timestamp('account_unlinked_at')->nullable()->after('user_id_resolved_at');
                $table->unsignedInteger('version')->default(1)->after('account_unlinked_at');
            });

            DB::statement('ALTER TABLE employees MODIFY user_nik VARCHAR(255) NULL');
            DB::statement('ALTER TABLE employees MODIFY subject_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite DDL is transactional; keep the table rebuild atomic so a failed
            // copy never leaves employees empty.
            DB::beginTransaction();

            try {
                Schema::dropIfExists('employees_temp');
                DB::statement('CREATE TEMPORARY TABLE employees_temp AS SELECT * FROM employees');
                Schema::dropIfExists('employees');

                Schema::create('employees', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->unique('user_id');
                    $table->timestamp('user_id_resolved_at')->nullable();
                    $table->timestamp('account_unlinked_at')->nullable();
                    $table->unsignedInteger('version')->default(1);

                    $table->string('user_nik')->nullable();
                    $table->foreign('user_nik')->references('nik')->on('users');

                    $table->unsignedBigInteger('employee_status_id');
                    $table->foreign('employee_status_id')->references('id')->on('employee_statuses');

                    $table->unsignedBigInteger('employee_type_id');
                    $table->foreign('employee_type_id')->references('id')->on('employee_types');

                    $table->unsignedBigInteger('employment_id');
                    $table->foreign('employment_id')->references('id')->on('employments');

                    $table->unsignedBigInteger('subject_id')->nullable();
                    $table->foreign('subject_id')->references('id')->on('subjects');

                    $table->unsignedBigInteger('religion_id');
                    $table->foreign('religion_id')->references('id')->on('religions');

                    $table->unsignedBigInteger('district_id');
                    $table->foreign('district_id')->references('id')->on('districts');

                    $table->unsignedBigInteger('marital_status_id');
                    $table->foreign('marital_status_id')->references('id')->on('marital_statuses');

                    $table->unsignedBigInteger('gender_id');
                    $table->foreign('gender_id')->references('id')->on('genders');

                    $table->string('nuptk')->nullable();
                    $table->string('npwp')->nullable();
                    $table->string('no_kk')->nullable();
                    $table->string('place_of_birth')->nullable();
                    $table->date('date_of_birth')->nullable();
                    $table->text('address')->nullable();
                    $table->string('rt', 5)->nullable();
                    $table->string('rw', 5)->nullable();
                    $table->string('zip_code', 10)->nullable();
                    $table->string('wa_number')->nullable();
                    $table->string('email')->nullable();

                    $table->string('appointment_certificate')->nullable();
                    $table->date('tmt_employee');
                    $table->string('certificate_of_teaching_hours')->nullable();
                    $table->string('biological_mothers_name')->nullable();
                    $table->string('partners_name')->nullable();

                    $table->string('elementary_school_name')->nullable();
                    $table->date('elementary_school_entry')->nullable();
                    $table->date('elementary_school_graduation')->nullable();
                    $table->string('nisn')->nullable();
                    $table->decimal('elementary_school_passing_grade', 5, 2)->nullable();

                    $table->string('junior_high_school_name')->nullable();
                    $table->date('junior_high_school_entry')->nullable();
                    $table->date('junior_high_school_graduation')->nullable();
                    $table->decimal('junior_high_school_passing_grade', 5, 2)->nullable();

                    $table->string('senior_high_school_name')->nullable();
                    $table->date('senior_high_school_entry')->nullable();
                    $table->date('senior_high_school_graduation')->nullable();
                    $table->decimal('senior_high_school_passing_grade', 5, 2)->nullable();

                    $table->string('bachelor_campus_name')->nullable();
                    $table->string('bachelor_major')->nullable();
                    $table->string('bachelor_faculty')->nullable();
                    $table->date('bachelor_entry')->nullable();
                    $table->date('bachelor_graduation')->nullable();
                    $table->string('bachelor_nim')->nullable();
                    $table->decimal('bachelor_passing_grade', 4, 2)->nullable();

                    $table->string('master_campus_name')->nullable();
                    $table->string('master_major')->nullable();
                    $table->string('master_faculty')->nullable();
                    $table->date('master_entry')->nullable();
                    $table->date('master_graduation')->nullable();
                    $table->string('master_nim')->nullable();
                    $table->decimal('master_passing_grade', 4, 2)->nullable();

                    $table->date('exit_date')->nullable();
                    $table->boolean('check_dapodik')->default(false);
                    $table->boolean('government_certificate')->default(false);
                    $table->softDeletes();
                    $table->index('deleted_at');
                    $table->timestamps();

                    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                });

                DB::statement('INSERT INTO employees (
                id, user_id, user_id_resolved_at, account_unlinked_at, version, user_nik,
                employee_status_id, employee_type_id, employment_id, subject_id, religion_id, district_id, marital_status_id, gender_id,
                nuptk, npwp, no_kk, place_of_birth, date_of_birth, address, rt, rw, zip_code, wa_number, email,
                appointment_certificate, tmt_employee, certificate_of_teaching_hours, biological_mothers_name, partners_name,
                elementary_school_name, elementary_school_entry, elementary_school_graduation, nisn, elementary_school_passing_grade,
                junior_high_school_name, junior_high_school_entry, junior_high_school_graduation, junior_high_school_passing_grade,
                senior_high_school_name, senior_high_school_entry, senior_high_school_graduation, senior_high_school_passing_grade,
                bachelor_campus_name, bachelor_major, bachelor_faculty, bachelor_entry, bachelor_graduation, bachelor_nim, bachelor_passing_grade,
                master_campus_name, master_major, master_faculty, master_entry, master_graduation, master_nim, master_passing_grade,
                exit_date, check_dapodik, government_certificate, deleted_at, created_at, updated_at
            ) SELECT
                id, NULL, NULL, NULL, 1, user_nik,
                employee_status_id, employee_type_id, employment_id, subject_id, religion_id, district_id, marital_status_id, gender_id,
                nuptk, npwp, no_kk, place_of_birth, date_of_birth, address, rt, rw, zip_code, wa_number, email,
                appointment_certificate, tmt_employee, certificate_of_teaching_hours, biological_mothers_name, partners_name,
                elementary_school_name, elementary_school_entry, elementary_school_graduation, nisn, elementary_school_passing_grade,
                junior_high_school_name, junior_high_school_entry, junior_high_school_graduation, junior_high_school_passing_grade,
                senior_high_school_name, senior_high_school_entry, senior_high_school_graduation, senior_high_school_passing_grade,
                bachelor_campus_name, bachelor_major, bachelor_faculty, bachelor_entry, bachelor_graduation, bachelor_nim, bachelor_passing_grade,
                master_campus_name, master_major, master_faculty, master_entry, master_graduation, master_nim, master_passing_grade,
                exit_date, check_dapodik, government_certificate, deleted_at, created_at, updated_at
            FROM employees_temp');

                Schema::dropIfExists('employees_temp');

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Fails closed before touching the schema when any employee row has a NULL
     * user_nik, a NULL subject_id, or a linked user_id: those rows cannot be
     * represented under the pre-04a constraints and dropping user_id would
     * silently discard account links. No partial rollback is attempted.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        $this->assertRollbackSafe();

        if ($driver === 'mysql') {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropUnique(['user_id']);
                $table->dropColumn(['user_id', 'user_id_resolved_at', 'account_unlinked_at', 'version']);
            });

            DB::statement('ALTER TABLE employees MODIFY user_nik VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE employees MODIFY subject_id BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'sqlite') {
            DB::beginTransaction();

            try {
                Schema::dropIfExists('employees_temp');
                DB::statement('CREATE TEMPORARY TABLE employees_temp AS SELECT * FROM employees');
                Schema::dropIfExists('employees');

                Schema::create('employees', function (Blueprint $table) {
                    $table->id();
                    $table->string('user_nik');
                    $table->foreign('user_nik')->references('nik')->on('users');

                    $table->unsignedBigInteger('employee_status_id');
                    $table->foreign('employee_status_id')->references('id')->on('employee_statuses');

                    $table->unsignedBigInteger('employee_type_id');
                    $table->foreign('employee_type_id')->references('id')->on('employee_types');

                    $table->unsignedBigInteger('employment_id');
                    $table->foreign('employment_id')->references('id')->on('employments');

                    $table->unsignedBigInteger('subject_id');
                    $table->foreign('subject_id')->references('id')->on('subjects');

                    $table->unsignedBigInteger('religion_id');
                    $table->foreign('religion_id')->references('id')->on('religions');

                    $table->unsignedBigInteger('district_id');
                    $table->foreign('district_id')->references('id')->on('districts');

                    $table->unsignedBigInteger('marital_status_id');
                    $table->foreign('marital_status_id')->references('id')->on('marital_statuses');

                    $table->unsignedBigInteger('gender_id');
                    $table->foreign('gender_id')->references('id')->on('genders');

                    $table->string('nuptk')->nullable();
                    $table->string('npwp')->nullable();
                    $table->string('no_kk')->nullable();
                    $table->string('place_of_birth')->nullable();
                    $table->date('date_of_birth')->nullable();
                    $table->text('address')->nullable();
                    $table->string('rt', 5)->nullable();
                    $table->string('rw', 5)->nullable();
                    $table->string('zip_code', 10)->nullable();
                    $table->string('wa_number')->nullable();
                    $table->string('email')->nullable();

                    $table->string('appointment_certificate')->nullable();
                    $table->date('tmt_employee');
                    $table->string('certificate_of_teaching_hours')->nullable();
                    $table->string('biological_mothers_name')->nullable();
                    $table->string('partners_name')->nullable();

                    $table->string('elementary_school_name')->nullable();
                    $table->date('elementary_school_entry')->nullable();
                    $table->date('elementary_school_graduation')->nullable();
                    $table->string('nisn')->nullable();
                    $table->decimal('elementary_school_passing_grade', 5, 2)->nullable();

                    $table->string('junior_high_school_name')->nullable();
                    $table->date('junior_high_school_entry')->nullable();
                    $table->date('junior_high_school_graduation')->nullable();
                    $table->decimal('junior_high_school_passing_grade', 5, 2)->nullable();

                    $table->string('senior_high_school_name')->nullable();
                    $table->date('senior_high_school_entry')->nullable();
                    $table->date('senior_high_school_graduation')->nullable();
                    $table->decimal('senior_high_school_passing_grade', 5, 2)->nullable();

                    $table->string('bachelor_campus_name')->nullable();
                    $table->string('bachelor_major')->nullable();
                    $table->string('bachelor_faculty')->nullable();
                    $table->date('bachelor_entry')->nullable();
                    $table->date('bachelor_graduation')->nullable();
                    $table->string('bachelor_nim')->nullable();
                    $table->decimal('bachelor_passing_grade', 4, 2)->nullable();

                    $table->string('master_campus_name')->nullable();
                    $table->string('master_major')->nullable();
                    $table->string('master_faculty')->nullable();
                    $table->date('master_entry')->nullable();
                    $table->date('master_graduation')->nullable();
                    $table->string('master_nim')->nullable();
                    $table->decimal('master_passing_grade', 4, 2)->nullable();

                    $table->date('exit_date')->nullable();
                    $table->boolean('check_dapodik')->default(false);
                    $table->boolean('government_certificate')->default(false);
                    $table->softDeletes();
                    $table->index('deleted_at');
                    $table->timestamps();
                });

                DB::statement('INSERT INTO employees (
                id, user_nik, employee_status_id, employee_type_id, employment_id, subject_id, religion_id, district_id, marital_status_id, gender_id,
                nuptk, npwp, no_kk, place_of_birth, date_of_birth, address, rt, rw, zip_code, wa_number, email,
                appointment_certificate, tmt_employee, certificate_of_teaching_hours, biological_mothers_name, partners_name,
                elementary_school_name, elementary_school_entry, elementary_school_graduation, nisn, elementary_school_passing_grade,
                junior_high_school_name, junior_high_school_entry, junior_high_school_graduation, junior_high_school_passing_grade,
                senior_high_school_name, senior_high_school_entry, senior_high_school_graduation, senior_high_school_passing_grade,
                bachelor_campus_name, bachelor_major, bachelor_faculty, bachelor_entry, bachelor_graduation, bachelor_nim, bachelor_passing_grade,
                master_campus_name, master_major, master_faculty, master_entry, master_graduation, master_nim, master_passing_grade,
                exit_date, check_dapodik, government_certificate, deleted_at, created_at, updated_at
            ) SELECT
                id, user_nik, employee_status_id, employee_type_id, employment_id, subject_id, religion_id, district_id, marital_status_id, gender_id,
                nuptk, npwp, no_kk, place_of_birth, date_of_birth, address, rt, rw, zip_code, wa_number, email,
                appointment_certificate, tmt_employee, certificate_of_teaching_hours, biological_mothers_name, partners_name,
                elementary_school_name, elementary_school_entry, elementary_school_graduation, nisn, elementary_school_passing_grade,
                junior_high_school_name, junior_high_school_entry, junior_high_school_graduation, junior_high_school_passing_grade,
                senior_high_school_name, senior_high_school_entry, senior_high_school_graduation, senior_high_school_passing_grade,
                bachelor_campus_name, bachelor_major, bachelor_faculty, bachelor_entry, bachelor_graduation, bachelor_nim, bachelor_passing_grade,
                master_campus_name, master_major, master_faculty, master_entry, master_graduation, master_nim, master_passing_grade,
                exit_date, check_dapodik, government_certificate, deleted_at, created_at, updated_at
            FROM employees_temp');

                Schema::dropIfExists('employees_temp');

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                throw $e;
            }
        }
    }

    /**
     * Refuse destructive rollback before any schema change when data cannot be
     * expressed under the pre-04a constraints. Failing closed avoids partially
     * applied DDL and silent loss of account links on MariaDB/MySQL.
     */
    private function assertRollbackSafe(): void
    {
        $nullNik = DB::table('employees')->whereNull('user_nik')->count();
        $nullSubject = DB::table('employees')->whereNull('subject_id')->count();
        $linked = DB::table('employees')->whereNotNull('user_id')->count();

        if ($nullNik > 0 || $nullSubject > 0 || $linked > 0) {
            throw new RuntimeException(sprintf(
                'Refusing to roll back employees schema (04a): %d row(s) have NULL user_nik, %d row(s) have NULL subject_id, %d row(s) keep a linked user_id. Resolve or archive these rows explicitly before rollback.',
                $nullNik,
                $nullSubject,
                $linked
            ));
        }
    }
};
