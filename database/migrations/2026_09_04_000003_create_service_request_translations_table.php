<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('description');
            $table->string('source_hash', 64);
            $table->string('translation_status')->default('pending');
            $table->timestamp('translated_at')->nullable();
            $table->timestamps();

            $table->unique(['service_request_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_translations');
    }
};
