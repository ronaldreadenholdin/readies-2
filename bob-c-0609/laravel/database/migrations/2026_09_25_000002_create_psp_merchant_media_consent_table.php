<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psp_merchant_media_consent', function (Blueprint $table): void {
            $table->id();
            $table->string('merchant_id');
            $table->string('media_id');
            $table->string('slot');
            $table->boolean('approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'media_id', 'slot'], 'psp_media_consent_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psp_merchant_media_consent');
    }
};
