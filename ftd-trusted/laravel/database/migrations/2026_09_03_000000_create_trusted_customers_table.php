<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_customers', function (Blueprint $table) {
            $table->id();
            $table->string('merchant', 64)->default('default')->index();
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable()->index();
            $table->string('card_first6_last4', 10)->nullable()->index();
            $table->date('birthday')->nullable()->index();
            $table->string('full_name')->nullable()->index();
            $table->string('biz', 64)->nullable()->index();
            $table->unsignedInteger('successful_payments')->default(0);
            $table->string('last_provider', 32)->nullable();
            $table->timestamp('last_paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_customers');
    }
};
