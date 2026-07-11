<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psp_test_harnesses', function (Blueprint $table) {
            $table->id();
            $table->string('psp_code')->index();
            $table->string('psp_name');
            $table->json('test_categories')->nullable();
            $table->json('flagged_items')->nullable();
            $table->json('certification_checklist')->nullable();
            $table->unsignedTinyInteger('readiness_score')->default(0);
            $table->boolean('all_green')->default(false);
            $table->text('bob_recommendations')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psp_test_harnesses');
    }
};
