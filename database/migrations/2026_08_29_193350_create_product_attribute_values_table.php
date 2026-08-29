<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->nullable();
            $table->text('text_value')->nullable();
            $table->integer('integer_value')->nullable();
            $table->decimal('float_value', 12, 4)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->json('json_value')->nullable();
            $table->enum('origin', [
                'manager',
                'ai_research',
                'ai_vision',
                'ai_generated_copy',
                'human_edit',
            ]);
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->text('evidence_quote')->nullable();
            $table->enum('confidence_tier', ['high', 'medium', 'low']);
            $table->enum('review_status', ['pending', 'approved', 'rejected', 'conflicted'])->default('pending');
            $table->uuid('conflict_group_id')->nullable()->index();
            $table->boolean('is_current')->default(true);
            $table->foreignId('previous_value_id')->nullable()->constrained('product_attribute_values')->nullOnDelete();
            $table->text('regeneration_feedback')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'attribute_id', 'is_current'], 'pav_product_attr_current_idx');
            $table->index(['product_id', 'review_status'], 'pav_product_review_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
