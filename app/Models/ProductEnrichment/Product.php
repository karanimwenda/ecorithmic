<?php

namespace App\Models\ProductEnrichment;

use Database\Factories\ProductEnrichment\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $sku
 * @property int|null $first_import_id
 * @property int|null $last_import_id
 *
 * @method static ProductFactory factory($count = null, $state = [])
 */
#[Fillable(['sku', 'first_import_id', 'last_import_id'])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('original');
        $this->addMediaCollection('primary-ecommerce');
        $this->addMediaCollection('social-square');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Conversions are handled explicitly by GenerateImageVariants job,
        // not via automatic medialibrary conversions, so each variant gets
        // its own media row with independent review_status/quality_flags.
    }

    /** @return HasMany<ProductAttributeValue, $this> */
    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /** @return HasMany<ProductAttributeValue, $this> */
    public function currentAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class)->where('is_current', true);
    }

    /** @return HasMany<Source, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    /** @return HasMany<ReviewEvent, $this> */
    public function reviewEvents(): HasMany
    {
        return $this->hasMany(ReviewEvent::class);
    }

    /** @return BelongsTo<Import, $this> */
    public function firstImport(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'first_import_id');
    }

    /** @return BelongsTo<Import, $this> */
    public function lastImport(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'last_import_id');
    }
}
