<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psp_media_impressions', function (Blueprint $table): void {
            $table->id();
            $table->string('impression_id')->unique();
            $table->string('media_id')->nullable();
            $table->string('advertiser_id')->nullable();
            $table->string('owner');
            $table->string('merchant_id');
            $table->string('slot');
            $table->string('payment_attempt_id');
            $table->string('merchant_reference');
            $table->timestamp('shown_at');
            $table->unsignedInteger('visible_duration_seconds')->default(0);
            $table->boolean('completed')->default(false);
            $table->boolean('clicked')->default(false);
            $table->string('payment_outcome')->nullable();
            $table->string('variant')->default('none');
            $table->timestamps();
            $table->unique(['payment_attempt_id', 'slot'], 'psp_media_impression_attempt_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psp_media_impressions');
    }
};
