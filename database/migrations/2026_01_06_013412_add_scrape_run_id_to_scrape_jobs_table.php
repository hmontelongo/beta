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
        Schema::table('scrape_jobs', function (Blueprint $table) {
            $table->foreignId('scrape_run_id')->nullable()->after('platform_id')->constrained()->nullOnDelete();
            $table->index('scrape_run_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scrape_jobs', function (Blueprint $table) {
            $table->dropForeign(['scrape_run_id']);
            $table->dropIndex(['scrape_run_id']);
            $table->dropColumn('scrape_run_id');
        });
    }
};
