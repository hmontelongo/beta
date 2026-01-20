<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service');
            $table->string('operation');
            $table->string('model')->nullable();

            // Claude tokens
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_creation_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);

            // ZenRows credits
            $table->unsignedInteger('credits_used')->default(0);

            // Cost (USD cents)
            $table->unsignedInteger('cost_cents')->default(0);

            // Optional metadata (e.g., URL for ZenRows)
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['service', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
    }
};
