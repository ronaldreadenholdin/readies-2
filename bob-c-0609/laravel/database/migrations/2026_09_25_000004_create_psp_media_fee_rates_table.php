<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psp_media_fee_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('media_id');
            $table->string('advertiser_id');
            $table->string('billable_event');
            $table->string('currency', 3);
            $table->decimal('amount', 12, 4);
            $table->decimal('merchant_revenue_share_percent', 5, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psp_media_fee_rates');
    }
};
