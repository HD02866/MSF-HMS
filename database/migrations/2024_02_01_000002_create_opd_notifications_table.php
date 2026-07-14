<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opd_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_queue_id')->constrained('opd_queue')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('patient_id')->constrained('patients');
            $table->string('patient_name', 255);
            $table->string('card_number', 50);
            $table->unsignedInteger('queue_number');
            $table->dateTime('assignment_time');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['room_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opd_notifications');
    }
};
