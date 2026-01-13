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
        Schema::create('ai_enrichments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, processing, completed, failed

            // AI Analysis Results
            $table->json('validated_data')->nullable();
            $table->json('extracted_tags')->nullable();
            $table->text('improved_description')->nullable();
            $table->json('address_verification')->nullable();
            $table->unsignedTinyInteger('quality_score')->nullable();
            $table->json('quality_issues')->nullable();
            $table->string('suggested_property_type')->nullable();
            $table->json('confidence_scores')->nullable();

            // Processing metadata
            $table->json('ai_response_raw')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('listing_id');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_enrichments');
    }
};
