<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * All five columns are nullable: existing rows (created before A-3)
     * have none of this consent recorded, and that must stay representable
     * rather than forcing a fabricated backfill value.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('age_confirmed_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version')->nullable();
            $table->timestamp('privacy_acknowledged_at')->nullable();
            $table->string('privacy_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'age_confirmed_at',
                'terms_accepted_at',
                'terms_version',
                'privacy_acknowledged_at',
                'privacy_version',
            ]);
        });
    }
};
