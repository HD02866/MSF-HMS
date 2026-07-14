<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_queue', function (Blueprint $table) {
            $table->id();

            // One queue entry per lab request (1-to-1)
            $table->foreignId('lab_request_id')->unique()->constrained('lab_requests')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');

            // Queue tracking
            $table->string('status', 30)->default('Pending');
            // Statuses: Pending | Received | Processing | Completed | Cancelled

            // Who last touched this entry
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // Timestamps for each status transition
            $table->dateTime('received_at')->nullable();
            $table->dateTime('processing_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            // Queue ordering: Urgent requests bubble up, then FIFO within same priority
            $table->index(['status', 'created_at']);
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_queue');
    }
};
