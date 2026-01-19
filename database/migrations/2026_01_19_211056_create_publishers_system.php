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
        // 1. Drop foreign keys and columns from listings
        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
            $table->dropConstrainedForeignId('agency_id');
        });

        // 2. Drop old tables
        Schema::dropIfExists('agents');
        Schema::dropIfExists('agencies');

        // 3. Create publishers table
        Schema::create('publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('unknown');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->json('platform_profiles')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('publishers')->nullOnDelete();
            $table->timestamps();

            $table->index('phone');
            $table->index('name');
            $table->index('type');
        });

        // 4. Create property_publisher pivot table
        Schema::create('property_publisher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publisher_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'publisher_id']);
        });

        // 5. Add publisher_id to listings
        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('publisher_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove publisher_id from listings
        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publisher_id');
        });

        // Drop new tables
        Schema::dropIfExists('property_publisher');
        Schema::dropIfExists('publishers');

        // Recreate old tables
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('platform_profiles')->nullable();
            $table->timestamps();
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->json('platform_profiles')->nullable();
            $table->timestamps();
        });

        // Re-add columns to listings
        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->after('agent_id')->constrained()->nullOnDelete();
        });
    }
};
