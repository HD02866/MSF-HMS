<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_request_id')->constrained('pharmacy_requests')->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('medicine_name', 200);
            $table->string('dosage', 100)->nullable()->comment('e.g. 500mg, 10ml');
            $table->string('frequency', 100)->nullable()->comment('e.g. Twice daily, Every 8 hours');
            $table->string('duration', 100)->nullable()->comment('e.g. 5 days, 2 weeks');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('pharmacy_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_request_items');
    }
};
