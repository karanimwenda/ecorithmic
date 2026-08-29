<?php

namespace App\Models\ProductEnrichment;

use Database\Factories\ProductEnrichment\ProductAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A customized Media model that exposes the extra columns added to the `media`
 * table by this feature: review_status, quality_flags, and is_primary.
 *
 * @property string $review_status
 * @property array<string, bool>|null $quality_flags
 * @property bool $is_primary
 *
 * @method static ProductAssetFactory factory($count = null, $state = [])
 */
class ProductAsset extends Media
{
    /** @use HasFactory<ProductAssetFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'quality_flags' => 'array',
            'is_primary' => 'boolean',
        ]);
    }
}
