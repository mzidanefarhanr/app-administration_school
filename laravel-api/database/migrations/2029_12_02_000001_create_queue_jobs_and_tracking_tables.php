<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Subfase 12b (DEC-46 D2, D3, D8):
     * Tabel antrean database (jobs, job_batches) dan tabel pelacakan eksekusi pekerjaan latar belakang (job_execution_statuses).
     */
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable('job_execution_statuses')) {
            Schema::create('job_execution_statuses', function (Blueprint $table) {
                $table->id();
                $table->uuid('job_id')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 64)->index();
                $table->string('status', 32)->default('pending')->index(); // pending, processing, completed, failed
                $table->unsignedTinyInteger('progress_percentage')->default(0);
                $table->unsignedInteger('processed_records')->default(0);
                $table->unsignedInteger('total_records')->default(0);
                $table->string('idempotency_key', 128)->nullable()->unique();
                $table->text('error_message')->nullable();
                $table->json('result_payload')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_execution_statuses');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
    }
};
