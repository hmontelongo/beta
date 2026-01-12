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
        Schema::table('discovered_listings', function (Blueprint $table) {
            $table->foreignId('scrape_run_id')->nullable()->after('batch_id')
                ->constrained('scrape_runs')->nullOnDelete();
            $table->text('preview_title')->nullable()->after('external_id');
            $table->string('preview_price')->nullable()->after('preview_title');
            $table->string('preview_location')->nullable()->after('preview_price');
            $table->string('preview_image', 500)->nullable()->after('preview_location');

            $table->index('scrape_run_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discovered_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scrape_run_id');
            $table->dropColumn(['preview_title', 'preview_price', 'preview_location', 'preview_image']);
        });
    }
};
