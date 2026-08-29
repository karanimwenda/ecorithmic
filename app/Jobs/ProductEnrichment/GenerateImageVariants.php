<?php

namespace App\Jobs\ProductEnrichment;

use App\Models\ProductEnrichment\Import;
use App\Models\ProductEnrichment\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Generates platform-ready image variants for a product (FR-015).
 * Each variant is registered as its own media item (not a medialibrary conversion)
 * so it gets independent review_status and quality_flags columns (research.md §4).
 */
class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /** Primary e-commerce variant: 1200x1200, white background. */
    private const array PRIMARY_ECOMMERCE = ['width' => 1200, 'height' => 1200, 'collection' => 'primary-ecommerce'];

    /** Social square variant: 800x800, white background. */
    private const array SOCIAL_SQUARE = ['width' => 800, 'height' => 800, 'collection' => 'social-square'];

    /** Minimum resolution for a variant to be considered acceptable. */
    private const int MIN_ACCEPTABLE_WIDTH = 400;

    /** Luminance stddev threshold above which a background is considered "busy". */
    private const float BUSY_BACKGROUND_THRESHOLD = 25.0;

    public function __construct(
        public readonly Product $product,
        public readonly ?Import $import = null,
    ) {}

    public function handle(): void
    {
        $originalAsset = $this->product->getMedia('original')
            ->where('is_primary', true)
            ->first();

        if (! $originalAsset) {
            return;
        }

        $sourcePath = $originalAsset->getPath();

        if (! file_exists($sourcePath)) {
            return;
        }

        // Compute quality flags for the original
        $qualityFlags = $this->computeQualityFlags($sourcePath);
        $originalAsset->update(['quality_flags' => $qualityFlags]);

        foreach ([self::PRIMARY_ECOMMERCE, self::SOCIAL_SQUARE] as $spec) {
            $this->generateVariant($sourcePath, $spec, $qualityFlags);
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Non-blocking — missing variants are flagged, not fatal (FR-010)
    }

    /**
     * @param  array{width: int, height: int, collection: string}  $spec
     * @param  array<string, bool>  $qualityFlags
     */
    private function generateVariant(string $sourcePath, array $spec, array $qualityFlags): void
    {
        $outputPath = sys_get_temp_dir().'/pe_variant_'.$this->product->id.'_'.$spec['collection'].'_'.uniqid().'.jpg';

        try {
            // FR-015: Fit::Contain — never crop into the subject
            Image::load($sourcePath)
                ->fit(Fit::Contain, $spec['width'], $spec['height'])
                ->background('ffffff')
                ->save($outputPath);

            // Detect low-resolution flag for this variant
            $variantFlags = array_merge($qualityFlags, [
                'low_resolution' => $this->isLowResolution($outputPath, $spec['width'], $spec['height']),
            ]);

            $media = $this->product->addMedia($outputPath)
                ->toMediaCollection($spec['collection']);

            $media->update(['quality_flags' => $variantFlags, 'review_status' => 'pending']);
        } finally {
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    /**
     * @return array<string, bool>
     */
    private function computeQualityFlags(string $imagePath): array
    {
        return [
            'low_resolution' => $this->isSourceLowResolution($imagePath),
            'busy_background' => $this->hasBusyBackground($imagePath),
        ];
    }

    private function isSourceLowResolution(string $imagePath): bool
    {
        [$width] = getimagesize($imagePath) ?: [0, 0];

        return $width < self::MIN_ACCEPTABLE_WIDTH;
    }

    private function isLowResolution(string $imagePath, int $targetWidth, int $targetHeight): bool
    {
        [$width, $height] = getimagesize($imagePath) ?: [0, 0];

        return $width < $targetWidth || $height < $targetHeight;
    }

    /**
     * Samples the border pixels and computes luminance stddev.
     * A high stddev indicates a "busy" (non-uniform) background (research.md §5).
     */
    private function hasBusyBackground(string $imagePath): bool
    {
        $imageInfo = getimagesize($imagePath);
        if (! $imageInfo) {
            return false;
        }

        [$width, $height, $type] = $imageInfo;

        $image = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($imagePath),
            IMAGETYPE_PNG => imagecreatefrompng($imagePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($imagePath),
            default => false,
        };

        if (! $image) {
            return false;
        }

        $borderWidth = max(1, (int) ($width * 0.05));
        $borderHeight = max(1, (int) ($height * 0.05));

        $luminances = [];

        // Sample top and bottom borders
        for ($x = 0; $x < $width; $x += max(1, (int) ($width / 20))) {
            for ($y = 0; $y < $borderHeight; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                if ($rgb !== false) {
                    $luminances[] = $this->luminance($rgb);
                }
            }
            for ($y = $height - $borderHeight; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                if ($rgb !== false) {
                    $luminances[] = $this->luminance($rgb);
                }
            }
        }

        // Sample left and right borders
        for ($y = 0; $y < $height; $y += max(1, (int) ($height / 20))) {
            for ($x = 0; $x < $borderWidth; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                if ($rgb !== false) {
                    $luminances[] = $this->luminance($rgb);
                }
            }
            for ($x = $width - $borderWidth; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                if ($rgb !== false) {
                    $luminances[] = $this->luminance($rgb);
                }
            }
        }

        if (count($luminances) < 2) {
            return false;
        }

        $mean = array_sum($luminances) / count($luminances);
        $variance = array_sum(array_map(fn ($l) => ($l - $mean) ** 2, $luminances)) / count($luminances);

        return sqrt($variance) > self::BUSY_BACKGROUND_THRESHOLD;
    }

    private function luminance(int $rgb): float
    {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return 0.299 * $r + 0.587 * $g + 0.114 * $b;
    }
}
