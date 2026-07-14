<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_queue_id')->constrained('opd_queue');
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('destination', 100);
            $table->text('reason');
            $table->text('diagnosis');
            $table->string('doctor_nurse_name', 255);
            $table->text('signature_data')->nullable();
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
