<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_requests', function (Blueprint $table) {
            $table->id();

            // Encounter linkage — every lab request belongs to one OPD encounter
            $table->foreignId('opd_queue_id')->constrained('opd_queue')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('requested_by')->constrained('users');

            // Request metadata
            $table->date('request_date');
            $table->enum('priority', ['Normal', 'Urgent'])->default('Normal');

            // Clinical context that accompanies the request
            $table->text('clinical_notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes for common lookups
            $table->index(['opd_queue_id']);
            $table->index(['patient_id', 'request_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_requests');
    }
};
