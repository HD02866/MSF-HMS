<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('generic_name', 200)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('form', 50)->nullable()->comment('Tablet, Capsule, Syrup, Injection, etc.');
            $table->string('unit', 50)->default('pieces');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->unsignedInteger('quantity_in_stock')->default(0);
            $table->unsignedInteger('minimum_stock_level')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
