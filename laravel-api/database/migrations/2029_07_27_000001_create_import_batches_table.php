<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 05a (DEC-25): batch impor siswa.
     *
     * Menyimpan ringkasan dan status batch; isi baris tidak diduplikasi ke
     * tabel agar data pribadi tetap hanya di berkas privat.
     */
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('token', 36)->unique();
            $table->string('domain', 20)->default('students');
            $table->string('mode', 10);
            $table->string('status', 20)->default('invalid');
            $table->string('file_name');
            $table->string('file_hash', 64);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('committed_rows')->default(0);
            $table->string('errors_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'created_at']);
            $table->index(['domain', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
