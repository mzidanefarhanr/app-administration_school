<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('name'); // Nama
            $table->string('nik')->unique()->nullable(); // NIK
            $table->string('birthplace')->nullable(); // Tempat Lahir
            $table->date('birthdate')->nullable(); // Tanggal Lahir
            $table->integer('age')->nullable(); // Umur
            $table->unsignedBigInteger('gender_id')->nullable();
            $table->foreign('gender_id')->references('id')->on('genders'); // Jenis Kelamin
            $table->unsignedBigInteger('religion_id')->nullable();
            $table->foreign('religion_id')->references('id')->on('religions'); // Agama
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->foreign('education_level_id')->references('id')->on('education_levels'); // Pendidikan
            $table->unsignedBigInteger('blood_type_id')->nullable();
            $table->foreign('blood_type_id')->references('id')->on('blood_types'); // Golongan Darah
            $table->unsignedBigInteger('profession_id')->nullable();
            $table->foreign('profession_id')->references('id')->on('professions'); // Profesi/Pekerjaan
            $table->unsignedBigInteger('village_id')->nullable();
            $table->foreign('village_id')->references('id')->on('villages'); // Desa -> Kelurahan -> Kecamatan -> Kabupaten/Kota -> Provinsi
            $table->string('zip_code')->nullable(); // Kode Pos
            $table->string('rt_num')->nullable(); // RT
            $table->string('rw_num')->nullable(); // RW
            $table->longText('address')->nullable(); //Alamat
            $table->string('kk')->nullable(); // Kartu Keluarga
            $table->string('akta')->nullable(); // AKTA
            $table->string('nis')->unique()->nullable(); // NIS
            $table->string('nisn')->unique()->nullable(); // NISN
            $table->integer('child_order_to')->nullable(); // anak ke-
            $table->integer('child_order_total')->nullable(); // dari
            $table->unsignedBigInteger('family_status_id')->nullable();
            $table->foreign('family_status_id')->references('id')->on('family_statuses'); // Status Keluarga
            $table->string('hp_num')->nullable(); // Nomor HP
            $table->string('history_illness')->nullable(); // Riwayat Penyakit
            $table->integer('body_height')->nullable(); // Tinggi Badan
            $table->integer('body_weight')->nullable(); // Berat Badan
            $table->string('last_education_school_id')->nullable();
            $table->foreign('last_education_school_id')->references('npsn')->on('education_schools'); // Asal Sekolah
            $table->year('certificate_year')->nullable(); // Tahun Ijazah
            $table->string('certificate_num')->nullable(); // Nomor Ijazah
            $table->string('latest_education_school_id')->nullable();
            $table->foreign('latest_education_school_id')->references('npsn')->on('education_schools'); // Sekolah Sekarang
            // $table->unsignedBigInteger('latest_school_rombel_id');
            // $table->foreign('latest_school_rombel_id')->references('id')->on('school_rombels'); // Rombel
            // $table->unsignedBigInteger('student_status_id')->nullable();
            // $table->foreign('student_status_id')->references('id')->on('student_statuses'); // Status Keaktifan Siswa
            // $table->unsignedBigInteger('student_entry_id')->nullable();
            // $table->foreign('student_entry_id')->references('id')->on('student_entries'); // Status Masuk Siswa
            // $table->date('latest_student_entry_date')->nullable(); // Tanggal Masuk Siswa
            // $table->string('mutation_education_school_id')->nullable();
            // $table->foreign('mutation_education_school_id')->references('npsn')->on('education_schools'); // Asal Mutasi Sekolah
            // $table->date('latest_student_exit_date')->nullable(); // Tanggal Keluar Siswa
            // $table->string('latest_student_exit_school_id')->nullable();
            // $table->foreign('latest_student_exit_school_id')->references('npsn')->on('education_schools'); // Sekolah Keluar Siswa
            // $table->unsignedBigInteger('student_major_id')->nullable();
            // $table->foreign('student_major_id')->references('id')->on('student_majors'); // Jurusan

            $table->string('father_name')->nullable(); // Nama Ayah
            $table->string('father_nik')->nullable(); // NIK Ayah
            $table->string('father_birthplace')->nullable(); // Tempat Lahir Ayah
            $table->date('father_birthdate')->nullable(); // Tanggal Lahir Ayah
            $table->unsignedBigInteger('father_education_level_id')->nullable();
            $table->foreign('father_education_level_id')->references('id')->on('education_levels'); // Pendidikan Ayah
            $table->unsignedBigInteger('father_profession_id')->nullable();
            $table->foreign('father_profession_id')->references('id')->on('professions'); // Profesi/Pekerjaan Ayah
            $table->string('father_income')->nullable(); // Pemasukan Ayah
            $table->string('father_hp_num')->nullable(); // Nomor HP Ayah

            $table->string('mother_name')->nullable(); // Nama Ibu
            $table->string('mother_nik')->nullable(); // NIK Ibu
            $table->string('mother_birthplace')->nullable(); // Tempat Lahir Ibu
            $table->date('mother_birthdate')->nullable(); // Tanggal Lahir Ibu
            $table->unsignedBigInteger('mother_education_level_id')->nullable();
            $table->foreign('mother_education_level_id')->references('id')->on('education_levels'); // Pendidikan Ibu
            $table->unsignedBigInteger('mother_profession_id')->nullable();
            $table->foreign('mother_profession_id')->references('id')->on('professions'); // Profesi/Pekerjaan Ibu
            $table->string('mother_income')->nullable(); // Pemasukan Ibu
            $table->string('mother_hp_num')->nullable(); // Nomor HP Ibu

            $table->string('guardian_name')->nullable(); // Nama Wali
            $table->string('guardian_nik')->nullable(); // NIK Wali
            $table->string('guardian_birthplace')->nullable(); // Tempat Lahir Wali
            $table->date('guardian_birthdate')->nullable(); // Tanggal Lahir Wali
            $table->unsignedBigInteger('guardian_education_level_id')->nullable();
            $table->foreign('guardian_education_level_id')->references('id')->on('education_levels'); // Pendidikan Wali
            $table->unsignedBigInteger('guardian_profession_id')->nullable();
            $table->foreign('guardian_profession_id')->references('id')->on('professions'); // Profesi/Pekerjaan Wali
            $table->string('guardian_income')->nullable(); // Pemasukan Wali
            $table->string('guardian_hp_num')->nullable(); // Nomor HP Wali

            $table->boolean('check_ppdbbersama')->nullable(); // PPDB Bersama
            $table->boolean('check_kjp')->nullable(); // KJP
            $table->boolean('check_bpms')->nullable(); // BPMS
            $table->boolean('check_pip')->nullable(); // PIP

            $table->softDeletes();
            $table->index('deleted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
