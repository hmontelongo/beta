<?php

use App\Enums\ScrapePhase;
use App\Enums\ScrapeRunStatus;
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
        Schema::create('scrape_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('search_query_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(ScrapeRunStatus::Pending->value);
            $table->string('phase')->default(ScrapePhase::Discover->value);
            $table->json('stats')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['platform_id', 'status']);
            $table->index('search_query_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_runs');
    }
};
