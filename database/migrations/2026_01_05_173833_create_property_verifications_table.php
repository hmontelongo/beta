<?php

use App\Enums\VerificationStatus;
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
        Schema::create('property_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('phone');
            $table->text('message_sent');
            $table->timestamp('message_sent_at')->nullable();
            $table->text('response_raw')->nullable();
            $table->json('response_parsed')->nullable();
            $table->timestamp('response_at')->nullable();
            $table->string('status')->default(VerificationStatus::Pending->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_verifications');
    }
};
