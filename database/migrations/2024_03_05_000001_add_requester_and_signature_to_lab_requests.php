<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_requests', function (Blueprint $table) {
            // Free-text name of the requesting doctor / nurse — denormalised so the
            // printed form always shows the name even if the user account changes.
            $table->string('requester_name', 150)->nullable()->after('requested_by');

            // Base64-encoded PNG data URL of the handwritten canvas signature.
            // Stored as LONGTEXT to accommodate the full data URI.
            $table->longText('signature_data')->nullable()->after('requester_name');
        });
    }

    public function down(): void
    {
        Schema::table('lab_requests', function (Blueprint $table) {
            $table->dropColumn(['requester_name', 'signature_data']);
        });
    }
};
