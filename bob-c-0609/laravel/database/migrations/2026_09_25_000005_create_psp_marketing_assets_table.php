<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psp_marketing_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('asset_id')->unique();
            $table->string('title');
            $table->string('type');
            $table->text('explanation');
            $table->string('file_url')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('external_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->json('allowed_slots');
            $table->string('owner');
            $table->string('advertiser_id')->nullable();
            $table->json('fee_rate')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('upload_mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psp_marketing_assets');
    }
};
