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
        Schema::table('properties', function (Blueprint $table) {
            // Ownership - nullable for scraped properties
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();

            // Source tracking
            $table->string('source_type')->default('scraped')->after('user_id');

            // Operation type (rent/sale) - direct field for native properties
            $table->string('operation_type')->nullable()->after('source_type');

            // Native property pricing
            $table->decimal('price', 14, 2)->nullable()->after('operation_type');
            $table->string('price_currency', 3)->default('MXN')->after('price');

            // Collaboration settings
            $table->boolean('is_collaborative')->default(false)->after('price_currency');
            $table->decimal('commission_split', 5, 2)->nullable()->after('is_collaborative');

            // Original agent description (what they typed)
            $table->text('original_description')->nullable()->after('description');

            // Soft delete for unpublishing
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['user_id', 'source_type']);
            $table->index(['source_type', 'is_collaborative']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'source_type']);
            $table->dropIndex(['source_type', 'is_collaborative']);

            $table->dropForeign(['user_id']);

            $table->dropColumn([
                'user_id',
                'source_type',
                'operation_type',
                'price',
                'price_currency',
                'is_collaborative',
                'commission_split',
                'original_description',
                'deleted_at',
            ]);
        });
    }
};
