<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add column if it doesn't exist
        if (! Schema::hasColumn('platforms', 'slug')) {
            Schema::table('platforms', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('name');
            });
        }

        // Populate slug from name for existing records with empty slugs
        DB::table('platforms')
            ->whereNull('slug')
            ->orWhere('slug', '')
            ->get()
            ->each(function ($platform) {
                DB::table('platforms')
                    ->where('id', $platform->id)
                    ->update(['slug' => Str::slug($platform->name)]);
            });

        // Try to add unique constraint (may already exist)
        try {
            Schema::table('platforms', function (Blueprint $table) {
                $table->unique('slug');
            });
        } catch (\Exception $e) {
            // Index already exists, ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
