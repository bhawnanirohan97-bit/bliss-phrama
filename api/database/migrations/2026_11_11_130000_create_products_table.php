<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable()->index();

            // Brand and generic are kept separate because buyers search by
            // either. A buyer may write "Letroheal" (brand) or "Letrozole"
            // (generic) for the same SKU.
            $table->string('brand')->nullable()->index();
            $table->string('generic_name')->nullable()->index();

            $table->string('strength', 64)->nullable();
            $table->string('dosage_form', 64)->nullable()->index();
            $table->string('packaging')->nullable();
            $table->string('moq')->nullable();

            $table->string('product_type')->default('formulation')->index();

            // Service listings live in the same source but are never matchable.
            $table->boolean('is_service')->default(false)->index();

            // Export eligibility is per SKU and per destination; the country
            // rules are a separate table. This flag only records that the SKU
            // is exportable at all.
            $table->boolean('export_eligible')->default(true);

            // This operator is a third-party brand exporter: it does not
            // manufacture. Drafts must never imply ownership of the brand.
            $table->string('principal')->nullable();
            $table->string('authorisation_status')->default('unknown');

            // Normalised token string used for matching buyer enquiries.
            $table->text('search_text');

            $table->string('source_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
