<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // A boolean export flag implied a decision that had not been made.
            // Review status records who decided what, and whether anyone did.
            $table->dropColumn('export_eligible');
            $table->string('review_status')->default('unreviewed')->index();
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'reviewed_at']);
            $table->boolean('export_eligible')->default(true);
        });
    }
};
