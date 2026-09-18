<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            // Capped at the DB level too (not just validation), matching
            // the 500-character limit enforced by SubmitProviderProfileRequest.
            $table->string('other_service_details', 500)->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn('other_service_details');
        });
    }
};
