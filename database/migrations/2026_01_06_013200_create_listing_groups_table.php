<?php

use App\Enums\ListingGroupStatus;
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
        Schema::create('listing_groups', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default(ListingGroupStatus::PendingReview->value);
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('match_score', 5, 2)->nullable();
            $table->json('ai_analysis')->nullable();
            $table->timestamp('ai_processed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_groups');
    }
};
