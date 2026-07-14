<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_request_id')->constrained('pharmacy_requests')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('patient_name', 255);
            $table->string('card_number', 50);
            $table->string('event', 30)->comment('pharmacy_submitted, pharmacy_dispensed');
            $table->json('medicine_names')->nullable();
            $table->timestamp('notified_at');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index('room_id');
            $table->index(['room_id', 'is_read']);
            $table->index(['room_id', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_notifications');
    }
};
