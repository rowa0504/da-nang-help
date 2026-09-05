<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('offer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('users')->restrictOnDelete();
            $table->decimal('agreed_price', 12, 2);
            $table->string('currency', 3);
            $table->string('status')->default('assigned');
            $table->timestamp('provider_completed_at')->nullable();
            $table->timestamp('customer_confirmed_at')->nullable();
            $table->timestamp('auto_confirm_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('service_request_id');
            $table->unique('offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_jobs');
    }
};
