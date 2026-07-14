<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_notifications', function (Blueprint $table) {
            $table->id();

            // The lab request this notification is about
            $table->foreignId('lab_request_id')->constrained('lab_requests')->cascadeOnDelete();

            // The OPD room that originally sent the request (notification recipient)
            $table->foreignId('room_id')->constrained('rooms');

            // Denormalised fields so the notification can be displayed without extra joins
            $table->foreignId('patient_id')->constrained('patients');
            $table->string('patient_name', 255);
            $table->string('card_number', 50);

            // What triggered this notification
            $table->string('event', 30);
            // Events: lab_received | lab_completed

            // The list of test names at the time of notification (stored as JSON array)
            $table->json('test_names');

            // Notification timestamp
            $table->dateTime('notified_at');

            $table->boolean('is_read')->default(false);

            $table->timestamps();

            $table->index(['room_id', 'is_read']);
            $table->index(['room_id', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_notifications');
    }
};
