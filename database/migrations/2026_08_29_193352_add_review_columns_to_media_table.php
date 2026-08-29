<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->enum('review_status', ['pending', 'approved', 'rejected'])->default('pending')->after('uuid');
            $table->json('quality_flags')->nullable()->after('review_status');
            $table->boolean('is_primary')->default(false)->after('quality_flags');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'quality_flags', 'is_primary']);
        });
    }
};
