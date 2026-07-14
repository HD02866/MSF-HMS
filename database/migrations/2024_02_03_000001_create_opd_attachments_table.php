<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opd_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_queue_id')->constrained('opd_queue')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('uploaded_by')->constrained('users');

            $table->string('original_name', 255);   // original filename shown to user
            $table->string('stored_path', 500);      // path inside public/opd-attachments/
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');  // bytes
            $table->string('type', 30)->default('document');
            // type: image | pdf | document | other

            $table->timestamps();

            $table->index(['opd_queue_id']);
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opd_attachments');
    }
};
