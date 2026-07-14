<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opd_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained('visits')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('room_id')->constrained('rooms');
            $table->unsignedInteger('queue_number');
            $table->dateTime('arrived_at');
            $table->dateTime('called_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status', 30)->default('Waiting');
            // Statuses: Waiting | Called | In Consultation | Completed | Transferred | Cancelled
            $table->timestamps();

            $table->index(['room_id', 'status']);
            $table->index(['room_id', 'arrived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opd_queue');
    }
};
