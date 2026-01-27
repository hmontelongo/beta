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
        Schema::table('api_usage_logs', function (Blueprint $table) {
            $table->string('entity_type')->nullable()->after('metadata');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            $table->string('job_class')->nullable()->after('entity_id');
            $table->string('error_type')->nullable()->after('job_class');
            $table->unsignedInteger('duration_ms')->nullable()->after('error_type');
            $table->boolean('success')->default(true)->after('duration_ms');

            $table->index(['entity_type', 'entity_id']);
            $table->index('success');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_usage_logs', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id']);
            $table->dropIndex(['success']);

            $table->dropColumn([
                'entity_type',
                'entity_id',
                'job_class',
                'error_type',
                'duration_ms',
                'success',
            ]);
        });
    }
};
