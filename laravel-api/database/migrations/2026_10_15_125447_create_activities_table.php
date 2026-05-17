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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('before')->nullable();         // Terisi saat update
            $table->json('after')->nullable();          // Terisi saat update
            $table->json('new')->nullable();            // Terisi HANYA saat created
            $table->json('delete')->nullable();         // Terisi HANYA saat deleted
            $table->string('table_name');               // Untuk menyimpan nama tabel (misal: users).
            $table->unsignedBigInteger('record_id');    // Untuk menyimpan ID dari data yang diubah. Tanpa ini, kamu tidak tahu baris mana yang harus di-update saat rollback.
            $table->enum('information', ['Created', 'Updated', 'Deleted', 'Rollbacked_Created', 'Rollbacked_Updated', 'Rollbacked_Deleted']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
