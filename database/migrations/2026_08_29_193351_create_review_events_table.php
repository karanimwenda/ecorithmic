<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_attribute_value_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('action', ['approve', 'reject', 'edit', 'regenerate', 'approve_product', 'bulk_approve']);
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->text('feedback_text')->nullable();
            $table->json('bulk_rule')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_events');
    }
};
