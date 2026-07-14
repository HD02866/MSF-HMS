<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_request_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_request_id')->constrained('lab_requests')->cascadeOnDelete();
            $table->string('test_name', 150);
            $table->timestamps();

            $table->index('lab_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_request_tests');
    }
};
