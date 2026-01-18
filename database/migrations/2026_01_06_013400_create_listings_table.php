<?php

use App\Enums\ListingStatus;
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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discovered_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->string('original_url');
            $table->string('status')->default(ListingStatus::Active->value);
            $table->json('operations');
            $table->json('external_codes')->nullable();
            $table->json('raw_data');
            $table->json('data_quality')->nullable();
            $table->string('ai_status')->default('pending');
            $table->string('dedup_status')->default('pending');
            $table->timestamp('ai_processed_at')->nullable();
            $table->timestamp('dedup_checked_at')->nullable();
            $table->timestamp('scraped_at');
            $table->timestamps();

            $table->unique(['platform_id', 'external_id']);
            $table->index(['ai_status', 'created_at']);
            $table->index(['dedup_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
