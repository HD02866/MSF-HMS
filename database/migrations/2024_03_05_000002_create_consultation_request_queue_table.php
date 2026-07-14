<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_request_queue', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_request_id')->unique()->constrained('consultation_requests')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');

            $table->string('status', 30)->default('Pending');
            // Pending | Accepted | Rejected | Completed | Cancelled

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_notes')->nullable();

            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_request_queue');
    }
};
