<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('source_locale', 5);
            $table->string('address_text', 500);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('urgency')->default('normal');
            $table->string('status')->default('open');
            $table->string('moderation_status')->default('visible');
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(
                ['category_id', 'area_id', 'status', 'moderation_status'],
                'service_requests_feed_index'
            );
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
