<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opd_clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_queue_id')->constrained('opd_queue')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('created_by')->constrained('users');

            // Clinical note fields — all nullable so partial saves are possible
            $table->text('chief_complaint')->nullable();
            $table->text('history')->nullable();
            $table->text('physical_examination')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('follow_up_instructions')->nullable();

            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
            $table->index('opd_queue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opd_clinical_notes');
    }
};
