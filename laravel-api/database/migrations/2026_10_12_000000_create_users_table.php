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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('nik')->unique();
            $table->unsignedBigInteger('type_user_id');
            $table->foreign('type_user_id')->references('id')->on('type_users');
            $table->unsignedBigInteger('status_user_id');
            $table->foreign('status_user_id')->references('id')->on('status_users');
            $table->boolean('first_login_at');
            $table->boolean('status_active');
            $table->softDeletes();
            $table->index('deleted_at');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
