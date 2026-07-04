<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('assigned_by')->constrained('users');
            $table->date('visit_date')->index();
            $table->time('visit_time');
            $table->unsignedInteger('queue_number')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 50)->default('Assigned');
            $table->timestamps();

            $table->index('patient_id');
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
