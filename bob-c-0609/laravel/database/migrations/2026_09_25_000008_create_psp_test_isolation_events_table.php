<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psp_test_isolation_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type');
            $table->string('connection_code');
            $table->string('merchant_id')->nullable();
            $table->string('test_site');
            $table->string('actor');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['connection_code', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psp_test_isolation_events');
    }
};
