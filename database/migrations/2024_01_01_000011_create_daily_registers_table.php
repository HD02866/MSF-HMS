<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients');
            $table->string('register_type', 50); // family|employee|os|referral_accident|referral_sick_leave
            $table->date('record_date')->index();
            $table->string('department_name', 100)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('patient_id');
            $table->index('register_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_registers');
    }
};
