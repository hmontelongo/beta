<?php

use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
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
        Schema::create('scrape_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scrape_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('discovered_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('scrape_jobs')->nullOnDelete();
            $table->string('job_type')->default(ScrapeJobType::Listing->value);
            $table->string('target_url');
            $table->json('filters')->nullable();
            $table->string('status')->default(ScrapeJobStatus::Pending->value);
            $table->unsignedInteger('total_results')->nullable();
            $table->unsignedInteger('total_pages')->nullable();
            $table->unsignedInteger('current_page')->nullable();
            $table->unsignedInteger('properties_found')->default(0);
            $table->unsignedInteger('properties_new')->default(0);
            $table->unsignedInteger('properties_updated')->default(0);
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('scrape_run_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_jobs');
    }
};
