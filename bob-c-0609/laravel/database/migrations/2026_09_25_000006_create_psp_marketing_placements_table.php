<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psp_marketing_placements', function (Blueprint $table): void {
            $table->id();
            $table->string('placement_id')->unique();
            $table->string('asset_id');
            $table->string('slot');
            $table->string('merchant_id');
            $table->string('site');
            $table->string('page_or_flow_step');
            $table->timestamp('active_from');
            $table->timestamp('active_to')->nullable();
            $table->string('switched_on_by');
            $table->timestamps();
            $table->index(['asset_id', 'merchant_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psp_marketing_placements');
    }
};
