<?php

namespace App\Models\ProductEnrichment;

use Carbon\CarbonImmutable;
use Database\Factories\ProductEnrichment\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property string $url
 * @property string $domain
 * @property string $trust_classification
 * @property bool $is_usable
 * @property CarbonImmutable|null $fetched_at
 *
 * @method static SourceFactory factory($count = null, $state = [])
 */
#[Fillable(['product_id', 'url', 'domain', 'trust_classification', 'is_usable', 'fetched_at'])]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_usable' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductAttributeValue, $this> */
    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
