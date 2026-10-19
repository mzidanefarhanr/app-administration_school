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
     * Fase 02a: Izinkan actor sistem/CLI pada tabel activities dengan membuat
     * user_id nullable, mempertahankan kompatibilitas riwayat audit lama.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE activities MODIFY user_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'sqlite') {
            Schema::dropIfExists('activities_temp');
            DB::statement('CREATE TEMPORARY TABLE activities_temp AS SELECT * FROM activities');
            Schema::dropIfExists('activities');

            Schema::create('activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->json('new')->nullable();
                $table->json('delete')->nullable();
                $table->string('table_name');
                $table->unsignedBigInteger('record_id');
                $table->enum('information', ['Created', 'Updated', 'Deleted', 'Rollbacked_Created', 'Rollbacked_Updated', 'Rollbacked_Deleted']);
                $table->timestamps();
            });

            DB::statement('INSERT INTO activities SELECT * FROM activities_temp');
            Schema::dropIfExists('activities_temp');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE activities MODIFY user_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
