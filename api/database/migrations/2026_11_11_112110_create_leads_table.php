<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_lead_id');
            $table->string('query_type', 8)->nullable();
            $table->string('status')->default('received');
            $table->timestamp('arrived_at')->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['lead_source_id', 'external_lead_id']);
            $table->index('status');
            $table->index('arrived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
