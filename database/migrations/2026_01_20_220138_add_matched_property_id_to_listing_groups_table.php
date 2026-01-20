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
        Schema::table('listing_groups', function (Blueprint $table) {
            $table->foreignId('matched_property_id')
                ->nullable()
                ->after('property_id')
                ->constrained('properties')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listing_groups', function (Blueprint $table) {
            $table->dropForeign(['matched_property_id']);
            $table->dropColumn('matched_property_id');
        });
    }
};
