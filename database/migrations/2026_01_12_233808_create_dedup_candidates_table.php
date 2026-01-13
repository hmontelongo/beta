<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dedup_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_a_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('listing_b_id')->constrained('listings')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, confirmed_match, confirmed_different, needs_review

            // Matching scores
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->decimal('coordinate_score', 5, 4)->nullable();
            $table->decimal('address_score', 5, 4)->nullable();
            $table->decimal('features_score', 5, 4)->nullable();
            $table->decimal('overall_score', 5, 4)->nullable();

            // AI verification (for edge cases)
            $table->boolean('ai_verified')->default(false);
            $table->string('ai_verdict')->nullable(); // match, different, uncertain
            $table->text('ai_reasoning')->nullable();
            $table->json('ai_response_raw')->nullable();

            // Resolution
            $table->foreignId('resolved_property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['listing_a_id', 'listing_b_id']);
            $table->index(['status', 'overall_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dedup_candidates');
    }
};
