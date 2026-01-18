<?php

use App\Enums\DiscoveredListingStatus;
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
        Schema::create('discovered_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scrape_run_id')->nullable()->constrained('scrape_runs')->nullOnDelete();
            $table->string('url');
            $table->string('external_id')->nullable();
            $table->text('preview_title')->nullable();
            $table->string('preview_price')->nullable();
            $table->string('preview_location')->nullable();
            $table->string('preview_image', 500)->nullable();
            $table->string('batch_id')->nullable();
            $table->string('status')->default(DiscoveredListingStatus::Pending->value);
            $table->integer('priority')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->unique(['platform_id', 'url']);
            $table->index(['status', 'priority']);
            $table->index('batch_id');
            $table->index('scrape_run_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discovered_listings');
    }
};
