<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 01a — tabel peran (roles).
 *
 * Additive. Tidak mengubah tabel users/type_users yang sudah ada pada database
 * pemilik. Peran di sini adalah konsep baru yang terpisah dari type_users legacy
 * agar pemetaan lama dapat dilakukan lewat keputusan eksplisit (lihat command
 * roles:backfill-legacy), bukan asumsi ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            // is_protected menandai peran inti yang tidak boleh dihapus
            // (mis. Admin Sistem) untuk mencegah kehilangan akses pengelolaan.
            $table->boolean('is_protected')->default(false);
            $table->unsignedBigInteger('version')->default(1);
            $table->softDeletes();
            $table->index('deleted_at');
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
