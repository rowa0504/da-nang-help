<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('users')->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->string('currency', 3);
            $table->text('message');
            $table->string('source_locale', 5);
            $table->dateTime('available_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['service_request_id', 'provider_id'], 'offers_request_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
