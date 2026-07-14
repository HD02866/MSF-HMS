<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();

            // Linked to the specific test within the request — never overwrites
            $table->foreignId('lab_request_id')->constrained('lab_requests')->cascadeOnDelete();
            $table->foreignId('lab_request_test_id')->constrained('lab_request_tests')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('performed_by')->constrained('users');

            // Result fields
            $table->text('result');               // The actual measured/observed result
            $table->text('remarks')->nullable();  // Reference range, interpretation notes, etc.
            $table->date('result_date');

            $table->timestamps();

            // A test within a request can only have one result record
            // (new readings must create a new request)
            $table->unique(['lab_request_test_id'], 'unique_result_per_test');

            $table->index(['lab_request_id']);
            $table->index(['patient_id', 'result_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_results');
    }
};
