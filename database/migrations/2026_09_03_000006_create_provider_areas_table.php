<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_areas', function (Blueprint $table) {
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();

            $table->primary(['provider_profile_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_areas');
    }
};
