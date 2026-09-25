<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psp_waiting_media', function (Blueprint $table): void {
            $table->id();
            $table->string('media_id')->unique();
            $table->string('title');
            $table->string('type');
            $table->string('file_url')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('format')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('owner');
            $table->text('rights_or_licence')->nullable();
            $table->string('status')->default('draft');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psp_waiting_media');
    }
};
