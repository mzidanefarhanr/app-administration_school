<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 08a (DEC-35): riwayat keputusan/pembatalan izin yang immutable.
     * Tanpa updated_at; model menolak update/delete.
     */
    public function up(): void
    {
        Schema::create('leave_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('action', 20);
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['leave_request_id', 'created_at'], 'leave_decisions_request_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_decisions');
    }
};
