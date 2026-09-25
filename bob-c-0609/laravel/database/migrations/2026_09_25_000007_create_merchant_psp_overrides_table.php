<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_psp_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('merchant_id');
            $table->string('connection_code');
            $table->unsignedInteger('from_position');
            $table->unsignedInteger('to_position');
            $table->text('reason');
            $table->string('overridden_by');
            $table->timestamp('overridden_at');
            $table->string('trial_state')->nullable();
            $table->unsignedInteger('trial_position')->nullable();
            $table->text('trial_reason')->nullable();
            $table->timestamps();
            $table->index(['merchant_id', 'connection_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_psp_overrides');
    }
};
