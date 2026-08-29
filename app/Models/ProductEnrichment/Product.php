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
 * @property array<string, bool>|null $enrichment_flags
 * @property bool $is_ungrounded
 *
 * @method static ProductFactory factory($count = null, $state = [])
 */
#[Fillable(['sku', 'first_import_id', 'last_import_id', 'enrichment_flags', 'is_ungrounded'])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'enrichment_flags' => 'array',
            'is_ungrounded' => 'boolean',
        ];
    }

    /**
     * Mark a named enrichment job as complete and return true if all named jobs are now done.
     * Used by ResearchProduct and AnalyzeProductPhoto to gate GenerateProductCopy dispatch (T103).
     *
     * @param  string[]  $requiredFlags  All flags that must be set before copy generation runs.
     */
    public function markEnrichmentFlag(string $flag, array $requiredFlags): bool
    {
        /** @var array<string, bool> $flags */
        $flags = $this->enrichment_flags ?? [];
        $flags[$flag] = true;
        $this->update(['enrichment_flags' => $flags]);

        return array_all($requiredFlags, fn ($required) => ! empty($flags[$required]));
    }

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
