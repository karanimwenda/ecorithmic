<?php

namespace App\Models\ProductEnrichment;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ProductEnrichment\ProductAttributeValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property int $attribute_id
 * @property string|null $locale
 * @property string|null $text_value
 * @property int|null $integer_value
 * @property float|null $float_value
 * @property bool|null $boolean_value
 * @property array<mixed>|null $json_value
 * @property string $origin
 * @property int|null $source_id
 * @property string|null $evidence_quote
 * @property string $confidence_tier
 * @property string $review_status
 * @property string|null $conflict_group_id
 * @property bool $is_current
 * @property int|null $previous_value_id
 * @property string|null $regeneration_feedback
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 *
 * @method static ProductAttributeValueFactory factory($count = null, $state = [])
 */
#[Fillable([
    'product_id', 'attribute_id', 'locale',
    'text_value', 'integer_value', 'float_value', 'boolean_value', 'json_value',
    'origin', 'source_id', 'evidence_quote', 'confidence_tier',
    'review_status', 'conflict_group_id', 'is_current', 'previous_value_id',
    'regeneration_feedback', 'approved_by', 'approved_at',
])]
class ProductAttributeValue extends Model
{
    /** @use HasFactory<ProductAttributeValueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'json_value' => 'array',
            'boolean_value' => 'boolean',
            'is_current' => 'boolean',
            'approved_at' => 'datetime',
            'float_value' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return BelongsTo<self, $this> */
    public function previousValue(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_value_id');
    }

    /** @return HasMany<self, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'previous_value_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<ReviewEvent, $this> */
    public function reviewEvents(): HasMany
    {
        return $this->hasMany(ReviewEvent::class);
    }

    /** Retrieve the human-readable value based on the attribute type. */
    public function getDisplayValue(): mixed
    {
        return match ($this->attribute?->type) {
            'boolean' => $this->boolean_value,
            'price' => $this->float_value,
            'select', 'text' => $this->text_value,
            'textarea' => $this->text_value,
            'json' => $this->json_value,
            default => $this->text_value ?? $this->integer_value ?? $this->float_value,
        };
    }
}
