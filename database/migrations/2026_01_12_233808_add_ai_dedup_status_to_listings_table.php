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
        Schema::table('listings', function (Blueprint $table) {
            $table->string('ai_status')->default('pending')->after('data_quality');
            $table->string('dedup_status')->default('pending')->after('ai_status');
            $table->timestamp('ai_processed_at')->nullable()->after('dedup_status');
            $table->timestamp('dedup_checked_at')->nullable()->after('ai_processed_at');

            $table->index(['ai_status', 'created_at']);
            $table->index(['dedup_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['ai_status', 'created_at']);
            $table->dropIndex(['dedup_status', 'created_at']);
            $table->dropColumn(['ai_status', 'dedup_status', 'ai_processed_at', 'dedup_checked_at']);
        });
    }
};
