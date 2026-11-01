<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 07b (DEC-33): disposisi surat masuk per penerima (profil pegawai).
     * Tanpa soft delete; completed/cancelled tetap menjadi history.
     */
    public function up(): void
    {
        Schema::create('letter_dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained('letters')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('recipient_employee_id')->constrained('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('due_date');
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->text('follow_up_note')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['letter_id', 'status'], 'letter_dispositions_letter_status_index');
            $table->index(['recipient_employee_id', 'status'], 'letter_dispositions_recipient_status_index');
            $table->index(['due_date', 'status'], 'letter_dispositions_due_status_index');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_dispositions');
    }
};
