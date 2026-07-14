<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_request_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_request_id')->constrained('consultation_requests')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients');

            $table->string('patient_name');
            $table->string('card_number');
            $table->string('event', 50);
            // consultation_request_sent | consultation_request_accepted | consultation_request_rejected | consultation_request_completed
            $table->string('destination', 100);
            $table->string('priority', 20)->default('Normal');

            $table->dateTime('notified_at');
            $table->boolean('is_read')->default(false);

            $table->timestamps();

            $table->index(['room_id', 'is_read', 'created_at']);
            $table->index(['destination', 'is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_request_notifications');
    }
};
