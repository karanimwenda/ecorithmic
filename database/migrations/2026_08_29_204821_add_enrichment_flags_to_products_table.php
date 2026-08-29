<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Tracks completion of per-product background enrichment jobs
            // so GenerateProductCopy only dispatches when both ResearchProduct
            // and AnalyzeProductPhoto have finished (T103 / plan.md job chaining).
            $table->json('enrichment_flags')->nullable()->after('last_import_id');
            // Marks products for which no web source was found (FR-010)
            $table->boolean('is_ungrounded')->default(false)->after('enrichment_flags');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['enrichment_flags', 'is_ungrounded']);
        });
    }
};
