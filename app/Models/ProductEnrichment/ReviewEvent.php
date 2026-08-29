<?php

namespace App\Models\ProductEnrichment;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ProductEnrichment\ReviewEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $product_attribute_value_id
 * @property string $action
 * @property int $actor_id
 * @property string|null $feedback_text
 * @property array<mixed>|null $bulk_rule
 * @property CarbonImmutable $created_at
 *
 * @method static ReviewEventFactory factory($count = null, $state = [])
 */
#[Fillable(['product_id', 'product_attribute_value_id', 'action', 'actor_id', 'feedback_text', 'bulk_rule', 'created_at'])]
#[WithoutTimestamps]
class ReviewEvent extends Model
{
    /** @use HasFactory<ReviewEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'bulk_rule' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductAttributeValue, $this> */
    public function productAttributeValue(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeValue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
