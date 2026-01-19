<?php

use App\Enums\PropertyStatus;
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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('address');
            $table->string('interior_number')->nullable();
            $table->string('colonia');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('property_type');
            $table->string('property_subtype')->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->unsignedTinyInteger('half_bathrooms')->nullable();
            $table->unsignedTinyInteger('parking_spots')->nullable();
            $table->decimal('lot_size_m2', 10, 2)->nullable();
            $table->decimal('built_size_m2', 10, 2)->nullable();
            $table->unsignedInteger('age_years')->nullable();
            $table->json('amenities')->nullable();
            $table->text('description')->nullable();
            $table->json('ai_unification')->nullable();
            $table->timestamp('ai_unified_at')->nullable();
            $table->boolean('needs_reanalysis')->default(false);
            $table->json('discrepancies')->nullable();
            $table->string('status')->default(PropertyStatus::Unverified->value);
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->unsignedInteger('listings_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
