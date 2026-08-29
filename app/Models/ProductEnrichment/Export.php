<?php

namespace App\Models\ProductEnrichment;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $requested_by
 * @property string $status
 * @property array<mixed>|null $summary
 * @property string|null $spreadsheet_path
 * @property string|null $images_path
 * @property string|null $manifest_path
 */
#[Fillable(['requested_by', 'status', 'summary', 'spreadsheet_path', 'images_path', 'manifest_path'])]
class Export extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'summary' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
