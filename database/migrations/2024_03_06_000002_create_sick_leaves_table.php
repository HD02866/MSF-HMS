<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sick_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_queue_id')->constrained('opd_queue');
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('employee_name', 255);
            $table->unsignedSmallInteger('days');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('diagnosis');
            $table->text('recommendation')->nullable();
            $table->text('signature_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sick_leaves');
    }
};
