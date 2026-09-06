<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('service_jobs')->restrictOnDelete();
            $table->foreignId('rater_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('ratee_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Phase 7 is one-directional (Customer -> Provider) reviews only, so a
            // single-column UNIQUE on job_id directly expresses "one review per
            // job". If Provider -> Customer reviews are added later, drop this
            // and switch to a composite (job_id, rater_id) UNIQUE instead.
            $table->unique('job_id', 'reviews_job_id_unique');
        });

        // Belt-and-suspenders alongside the CreateReviewRequest validation —
        // protects direct Action calls and anything else that bypasses the
        // Form Request. MySQL 8.0.16+ enforces CHECK constraints.
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
