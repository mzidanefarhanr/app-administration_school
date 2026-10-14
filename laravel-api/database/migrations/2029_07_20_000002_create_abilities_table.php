<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 01a — katalog kemampuan bernama (abilities) dan pivot role→ability.
 *
 * Kemampuan didefinisikan sebagai nama (mis. "students.view", "letters.approve")
 * sehingga kode memeriksa kemampuan, bukan angka type_user_id atau nama peran.
 * Admin Sistem dapat menyesuaikan pemetaan peran→kemampuan tanpa mengubah kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abilities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // contoh: students.view
            $table->string('group')->nullable();       // contoh: students, letters, system
            $table->string('description')->nullable();
            // is_sensitive: kemampuan ekspor / persetujuan / penandatanganan /
            // pengelolaan akses agar mudah diaudit dan tidak diberikan diam-diam.
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();
        });

        Schema::create('ability_role', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->unsignedBigInteger('ability_id');
            $table->foreign('ability_id')->references('id')->on('abilities')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['role_id', 'ability_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ability_role');
        Schema::dropIfExists('abilities');
    }
};
