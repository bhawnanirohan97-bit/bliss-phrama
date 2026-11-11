<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_lead_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('payload');
            $table->string('outcome')->default('received');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('external_lead_id');
            $table->index('outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
