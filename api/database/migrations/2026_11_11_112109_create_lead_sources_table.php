<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('connector_type');
            $table->string('integration_status')->default('not_configured');
            $table->json('config')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();

            $table->unique('connector_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sources');
    }
};
