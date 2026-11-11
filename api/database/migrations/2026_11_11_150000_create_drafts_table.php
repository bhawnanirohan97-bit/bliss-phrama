<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();

            // Drafts are evidence of what the system suggested at a point in
            // time. Regenerating creates a new row; nothing is overwritten.
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            // The assessment this draft was written from. This makes it
            // possible to skip duplicate drafting when the same assessment is
            // passed to the generator twice, and to trace the wording back to
            // the exact band and matched SKU.
            $table->foreignId('lead_assessment_id')->nullable()->constrained('lead_assessments')->nullOnDelete();

            $table->string('channel', 16);
            $table->string('language', 8)->default('en');

            $table->string('template_id', 64);
            $table->string('template_version', 16);

            // Email carries a subject; the other channels leave it null.
            $table->string('subject')->nullable();
            $table->text('body');

            $table->string('fact_check_status', 16);
            $table->json('fact_check_detail');

            // Drafts are deterministic in v1. These columns exist so an AI
            // provider can be recorded later without a schema change.
            $table->string('provider', 32)->default('template');
            $table->string('model_version', 64)->nullable();

            // Approval and sending are human acts recorded after the fact.
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->string('channel_ref')->nullable();

            $table->timestamps();

            $table->index(['lead_id', 'channel', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
