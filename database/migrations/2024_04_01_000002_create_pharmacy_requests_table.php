<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_queue_id')->constrained('opd_queue')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('prescribed_by')->constrained('users')->cascadeOnDelete();
            $table->string('prescriber_name', 150)->nullable();
            $table->date('request_date');
            $table->text('clinical_notes')->nullable();
            $table->boolean('is_external')->default(false)->comment('External prescription (medicine not in inventory)');
            $table->text('external_notes')->nullable()->comment('Reason for external prescription');
            $table->timestamps();

            $table->index('opd_queue_id');
            $table->index(['patient_id', 'request_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_requests');
    }
};
