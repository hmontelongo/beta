<?php

use App\Enums\ScrapeJobStatus;
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
            $table->string('target_url');
            $table->json('filters')->nullable();
            $table->string('status')->default(ScrapeJobStatus::Pending->value);
            $table->unsignedInteger('properties_found')->default(0);
            $table->unsignedInteger('properties_new')->default(0);
            $table->unsignedInteger('properties_updated')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
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
