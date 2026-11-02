<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 07c (DEC-34): kop, penandatangan, dan aset privat per profil sekolah.
     * Additive: tabel baru; satu settings per school_profile (tahun ajaran).
     * Tanpa soft delete; settings tahun lama tetap disimpan.
     */
    public function up(): void
    {
        Schema::create('letter_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_profile_id')->unique()
                ->constrained('school_profiles')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);

            $table->string('government_line', 150)->nullable();
            $table->string('school_name', 150)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->string('contact_line', 150)->nullable();
            $table->string('place_of_issue', 100)->nullable();

            $table->string('logo_path', 255)->nullable();
            $table->string('logo_mime', 50)->nullable();
            $table->unsignedInteger('logo_size_bytes')->nullable();
            $table->char('logo_sha256', 64)->nullable();

            $table->string('signatory_name', 150)->nullable();
            $table->string('signatory_title', 150)->nullable();
            $table->string('signatory_identifier_label', 30)->nullable();
            $table->string('signatory_identifier', 50)->nullable();

            $table->string('signature_path', 255)->nullable();
            $table->string('signature_mime', 50)->nullable();
            $table->unsignedInteger('signature_size_bytes')->nullable();
            $table->char('signature_sha256', 64)->nullable();

            $table->string('stamp_path', 255)->nullable();
            $table->string('stamp_mime', 50)->nullable();
            $table->unsignedInteger('stamp_size_bytes')->nullable();
            $table->char('stamp_sha256', 64)->nullable();

            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_settings');
    }
};
