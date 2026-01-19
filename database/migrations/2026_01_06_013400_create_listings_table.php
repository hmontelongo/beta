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
            $table->foreignId('listing_group_id')->nullable()->constrained()->nullOnDelete();
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
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('geocode_status')->nullable();
            $table->timestamp('geocoded_at')->nullable();
            $table->string('dedup_status')->default('pending');
            $table->timestamp('dedup_checked_at')->nullable();
            $table->boolean('is_primary_in_group')->default(false);
            $table->timestamp('scraped_at');
            $table->timestamps();

            $table->unique(['platform_id', 'external_id']);
            $table->index(['dedup_status', 'created_at']);
            $table->index(['geocode_status', 'created_at']);
            $table->index(['latitude', 'longitude']);
            $table->index('listing_group_id');
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
