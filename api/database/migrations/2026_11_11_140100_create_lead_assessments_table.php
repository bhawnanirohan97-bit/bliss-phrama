<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_assessments', function (Blueprint $table) {
            $table->id();

            // Assessments are append-only: this row is the evidence for the
            // band, and it is never edited. A reassessment is a new row.
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            $table->string('policy_version', 32);

            // The chosen catalogue SKU, if any. A SKU that is later deleted
            // must not delete the assessment that named it.
            $table->foreignId('sku_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('match_state', 16);
            $table->decimal('match_score', 4, 3)->nullable();

            // Component points, 0 to their PRD weight.
            $table->unsignedTinyInteger('product_match');
            $table->unsignedTinyInteger('commercial_value');
            $table->unsignedTinyInteger('destination');
            $table->unsignedTinyInteger('buyer_type_intent');
            $table->unsignedTinyInteger('completeness');
            $table->unsignedTinyInteger('overall_score');

            $table->string('band', 16)->index();
            $table->unsignedTinyInteger('band_rank')->index();

            $table->string('destination_country', 2)->nullable();
            $table->string('destination_class', 32);
            $table->unsignedBigInteger('requested_quantity')->nullable();
            $table->string('requested_quantity_unit', 16)->nullable();

            $table->string('buyer_type', 32);
            $table->string('purchase_intent', 32);

            // Arrays are stored as JSON so the assessment is self-contained
            // evidence even if the enums or rules change later.
            $table->json('gates');
            $table->json('reasons');
            $table->json('missing_information');
            $table->json('risk_flags');
            $table->json('candidates');

            $table->text('explanation');

            $table->timestamps();

            $table->index(['lead_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_assessments');
    }
};
