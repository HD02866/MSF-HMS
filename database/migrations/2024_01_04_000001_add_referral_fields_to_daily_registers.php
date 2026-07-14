<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_registers', function (Blueprint $table) {
            // Who referred the patient (for referral_accident and referral_sick_leave)
            $table->string('referred_from', 100)->nullable()->after('department_name');
            // Number of days given (for referral_sick_leave)
            $table->unsignedSmallInteger('days_given')->nullable()->after('referred_from');
        });
    }

    public function down(): void
    {
        Schema::table('daily_registers', function (Blueprint $table) {
            $table->dropColumn(['referred_from', 'days_given']);
        });
    }
};
