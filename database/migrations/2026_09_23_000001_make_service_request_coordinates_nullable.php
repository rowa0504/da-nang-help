<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->change();
            $table->decimal('lng', 10, 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Customer-submitted coordinates are being retired in favor of a
        // future Google Maps integration that will backfill lat/lng later,
        // so rows created after this migration may legitimately have null
        // coordinates. Silently defaulting them to 0,0 here to satisfy a
        // restored NOT NULL constraint would fabricate location data, so
        // this refuses to run instead — the caller must backfill or remove
        // those rows first.
        $hasNullCoordinates = DB::table('service_requests')
            ->whereNull('lat')
            ->orWhereNull('lng')
            ->exists();

        if ($hasNullCoordinates) {
            throw new RuntimeException(
                'Cannot roll back make_service_request_coordinates_nullable: '
                .'service_requests contains rows with a null lat or lng. '
                .'Backfill or remove those rows before restoring the NOT NULL constraint.'
            );
        }

        Schema::table('service_requests', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable(false)->change();
            $table->decimal('lng', 10, 7)->nullable(false)->change();
        });
    }
};
