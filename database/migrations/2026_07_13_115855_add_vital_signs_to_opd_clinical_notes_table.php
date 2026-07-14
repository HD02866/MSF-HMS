<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opd_clinical_notes', function (Blueprint $table) {
            $table->decimal('temperature', 4, 1)->nullable()->after('follow_up_instructions');
            $table->integer('systolic_bp')->nullable()->after('temperature');
            $table->integer('diastolic_bp')->nullable()->after('systolic_bp');
            $table->integer('pulse_rate')->nullable()->after('diastolic_bp');
            $table->integer('respiratory_rate')->nullable()->after('pulse_rate');
            $table->decimal('spo2', 4, 1)->nullable()->after('respiratory_rate');
            $table->decimal('weight', 5, 1)->nullable()->after('spo2');
            $table->decimal('height', 5, 1)->nullable()->after('weight');
            $table->decimal('bmi', 4, 1)->nullable()->after('height');
            $table->decimal('random_blood_sugar', 5, 1)->nullable()->after('bmi');
        });
    }

    public function down(): void
    {
        Schema::table('opd_clinical_notes', function (Blueprint $table) {
            $table->dropColumn([
                'temperature', 'systolic_bp', 'diastolic_bp', 'pulse_rate',
                'respiratory_rate', 'spo2', 'weight', 'height', 'bmi', 'random_blood_sugar',
            ]);
        });
    }
};
