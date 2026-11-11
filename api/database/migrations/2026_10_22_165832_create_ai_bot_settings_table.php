<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bot_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->json('criteria')->nullable();
            $table->json('product_filters')->nullable();
            $table->json('country_filters')->nullable();
            $table->json('buyer_type_filters')->nullable();
            $table->integer('min_quantity')->nullable();
            $table->integer('max_quantity')->nullable();
            $table->decimal('min_order_value', 12, 2)->nullable();
            $table->decimal('max_order_value', 12, 2)->nullable();
            $table->json('keywords_include')->nullable();
            $table->json('keywords_exclude')->nullable();
            $table->json('allowed_dosage_forms')->nullable();
            $table->boolean('only_new_buyers')->default(true);
            $table->boolean('skip_personal')->default(true);
            $table->boolean('skip_job_vendor')->default(true);
            $table->boolean('auto_draft')->default(true);
            $table->boolean('auto_prioritize')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bot_settings');
    }
};
