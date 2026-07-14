<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('opd_queue_id')->constrained('opd_queue')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('requested_by')->constrained('users');

            $table->string('requester_name')->nullable();
            $table->text('signature_data')->nullable();

            $table->string('destination', 100);
            $table->text('reason');
            $table->text('clinical_summary')->nullable();
            $table->enum('priority', ['Normal', 'Urgent'])->default('Normal');
            $table->date('request_date');

            $table->timestamps();

            $table->index(['opd_queue_id']);
            $table->index(['patient_id', 'request_date']);
            $table->index(['destination', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_requests');
    }
};
