<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('specialty', 200);
            $table->string('npi_number', 10)->nullable();
            $table->text('license_number_encrypted')->nullable();
            $table->string('license_number_mask', 20)->nullable();
            $table->string('license_state', 2)->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('credentialing_status', 30)->default('NOT_SUBMITTED');
            $table->timestamp('activated_at')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('doctor_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('doctor_id');
            $table->foreign('doctor_id')->references('id')->on('doctors')->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->integer('file_size');
            $table->string('mime_type', 50);
            $table->string('checksum', 64);
            $table->uuid('uploaded_by');
            $table->foreign('uploaded_by')->references('id')->on('users');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->unique(['doctor_id', 'document_type', 'checksum']);
        });

        Schema::create('credentialing_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('doctor_id');
            $table->foreign('doctor_id')->references('id')->on('doctors')->cascadeOnDelete();
            $table->string('status', 30);
            $table->uuid('assigned_reviewer')->nullable();
            $table->foreign('assigned_reviewer')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('resolved_at')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('credentialing_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('case_id');
            $table->foreign('case_id')->references('id')->on('credentialing_cases')->cascadeOnDelete();
            $table->string('action', 30);
            $table->uuid('actor_id');
            $table->foreign('actor_id')->references('id')->on('users');
            $table->text('notes')->nullable();
            $table->timestamp('timestamp')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentialing_actions');
        Schema::dropIfExists('credentialing_cases');
        Schema::dropIfExists('doctor_documents');
        Schema::dropIfExists('doctors');
    }
};
