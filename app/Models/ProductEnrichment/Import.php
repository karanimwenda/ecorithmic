<?php

namespace App\Models\ProductEnrichment;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ProductEnrichment\ImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $spreadsheet_path
 * @property string $spreadsheet_original_filename
 * @property string|null $archive_path
 * @property int $row_count
 * @property int $matched_row_count
 * @property int $unmatched_row_count
 * @property int $unmatched_photo_count
 * @property int $duplicate_sku_count
 * @property int $unreadable_file_count
 * @property float $estimated_cost_usd
 * @property float $processing_cost_usd
 * @property float|null $cost_cap_usd
 * @property string $status
 * @property array<mixed>|null $validation_report
 * @property int|null $confirmed_by
 * @property CarbonImmutable|null $confirmed_at
 *
 * @method static ImportFactory factory($count = null, $state = [])
 */
#[Fillable([
    'spreadsheet_path', 'spreadsheet_original_filename', 'archive_path',
    'row_count', 'matched_row_count', 'unmatched_row_count', 'unmatched_photo_count',
    'duplicate_sku_count', 'unreadable_file_count', 'estimated_cost_usd',
    'processing_cost_usd', 'cost_cap_usd', 'status', 'validation_report',
    'confirmed_by', 'confirmed_at',
])]
class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'validation_report' => 'array',
            'estimated_cost_usd' => 'decimal:4',
            'processing_cost_usd' => 'decimal:4',
            'cost_cap_usd' => 'decimal:4',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'last_import_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Accumulate processing cost, and halt if the cap is reached.
     * Returns true if the import was halted.
     */
    public function accumulateCost(float $amount): bool
    {
        $this->increment('processing_cost_usd', $amount);
        $this->refresh();

        if ($this->cost_cap_usd !== null && (float) $this->processing_cost_usd >= (float) $this->cost_cap_usd) {
            if ($this->status === 'processing') {
                $this->update(['status' => 'halted_cost_cap']);
            }

            return true;
        }

        return false;
    }

    /** Whether the cap has been reached (or the import was halted for cost). */
    public function isHaltedForCost(): bool
    {
        return $this->status === 'halted_cost_cap';
    }
}
