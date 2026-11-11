<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_bot_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_bot_settings', 'reject_domestic')) {
                $table->boolean('reject_domestic')->default(true)->after('skip_job_vendor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_bot_settings', function (Blueprint $table) {
            if (Schema::hasColumn('ai_bot_settings', 'reject_domestic')) {
                $table->dropColumn('reject_domestic');
            }
        });
    }
};
