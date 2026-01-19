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
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('search_url');
            $table->boolean('is_active')->default(true);
            $table->string('run_frequency')->default('none');
            $table->string('schedule_type')->default('interval');
            $table->unsignedSmallInteger('interval_value')->default(1);
            $table->string('interval_unit')->default('hours');
            $table->time('scheduled_time')->nullable();
            $table->unsignedTinyInteger('scheduled_day')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('auto_enabled')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['platform_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
