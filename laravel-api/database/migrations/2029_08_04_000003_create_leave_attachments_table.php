<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 08a (DEC-35): lampiran privat izin.
     * Soft delete agar file dan metadata bertahan sampai keputusan retensi DEC-13.
     */
    public function up(): void
    {
        Schema::create('leave_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('original_name', 255);
            $table->string('path', 255)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['leave_request_id', 'deleted_at'], 'leave_attachments_request_deleted_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_attachments');
    }
};
