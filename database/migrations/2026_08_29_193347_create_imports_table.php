<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('spreadsheet_path');
            $table->string('spreadsheet_original_filename');
            $table->string('archive_path')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('matched_row_count')->default(0);
            $table->unsignedInteger('unmatched_row_count')->default(0);
            $table->unsignedInteger('unmatched_photo_count')->default(0);
            $table->unsignedInteger('duplicate_sku_count')->default(0);
            $table->unsignedInteger('unreadable_file_count')->default(0);
            $table->decimal('estimated_cost_usd', 10, 4)->default(0);
            $table->decimal('processing_cost_usd', 10, 4)->default(0);
            $table->decimal('cost_cap_usd', 10, 4)->nullable();
            $table->enum('status', [
                'pending_validation',
                'rejected',
                'confirmed',
                'processing',
                'completed',
                'halted_cost_cap',
            ])->default('pending_validation');
            $table->json('validation_report')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
