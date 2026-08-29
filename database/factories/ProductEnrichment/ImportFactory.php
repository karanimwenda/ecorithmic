<?php

namespace Database\Factories\ProductEnrichment;

use App\Models\ProductEnrichment\Import;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    public function definition(): array
    {
        return [
            'spreadsheet_path' => 'imports/spreadsheet_'.$this->faker->uuid().'.xlsx',
            'spreadsheet_original_filename' => $this->faker->word().'.xlsx',
            'archive_path' => 'imports/archive_'.$this->faker->uuid().'.zip',
            'row_count' => 20,
            'matched_row_count' => 17,
            'unmatched_row_count' => 3,
            'unmatched_photo_count' => 0,
            'duplicate_sku_count' => 0,
            'unreadable_file_count' => 0,
            'estimated_cost_usd' => $this->faker->randomFloat(4, 0.01, 5.00),
            'processing_cost_usd' => 0,
            'cost_cap_usd' => null,
            'status' => 'pending_validation',
            'validation_report' => [
                'matched' => 17,
                'unmatched_rows' => 3,
                'unmatched_photos' => 0,
                'duplicate_skus' => [],
                'unreadable' => [],
            ],
        ];
    }

    public function pendingValidation(): static
    {
        return $this->state(['status' => 'pending_validation']);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed']);
    }

    public function processing(): static
    {
        return $this->state(['status' => 'processing']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }

    public function rejected(): static
    {
        return $this->state(['status' => 'rejected']);
    }

    public function haltedCostCap(): static
    {
        return $this->state(['status' => 'halted_cost_cap']);
    }

    public function withCostCap(float $cap): static
    {
        return $this->state(['cost_cap_usd' => $cap]);
    }
}
