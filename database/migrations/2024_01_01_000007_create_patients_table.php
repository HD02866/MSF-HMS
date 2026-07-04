<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('card_number', 50)->unique();
            $table->foreignId('patient_type_id')->constrained('patient_types');
            $table->foreignId('relationship_type_id')->nullable()->constrained('relationship_types');
            $table->string('employee_no', 50)->nullable()->index();
            $table->string('insurance_no', 100)->nullable()->index();
            $table->unsignedInteger('dependent_no')->nullable();
            $table->string('full_name');
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth');
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('woreda', 100)->nullable();
            $table->string('kebele', 100)->nullable();
            $table->string('house_no', 50)->nullable();
            $table->string('status', 20)->default('Active');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
