<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->unsignedBigInteger('record_id');
            $table->string('record_type', 20);
            $table->foreignId('patient_id')->constrained('patients');
            $table->string('patient_name', 255);
            $table->string('card_number', 50);
            $table->foreignId('opd_room_id')->constrained('rooms');
            $table->boolean('is_read')->default(false);
            $table->timestamp('notified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_notifications');
    }
};
