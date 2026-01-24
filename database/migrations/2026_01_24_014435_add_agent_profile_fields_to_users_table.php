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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('whatsapp');
            $table->string('business_name')->nullable()->after('avatar_path');
            $table->string('tagline')->nullable()->after('business_name');
            $table->string('brand_color', 7)->nullable()->after('tagline');
            $table->text('default_whatsapp_message')->nullable()->after('brand_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_path',
                'business_name',
                'tagline',
                'brand_color',
                'default_whatsapp_message',
            ]);
        });
    }
};
