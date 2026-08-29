<?php

namespace App\Models\ProductEnrichment;

use Database\Factories\ProductEnrichment\AttributeOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $attribute_id
 * @property string $admin_name
 * @property int $sort_order
 *
 * @method static AttributeOptionFactory factory($count = null, $state = [])
 */
#[Fillable(['attribute_id', 'admin_name', 'sort_order'])]
class AttributeOption extends Model
{
    /** @use HasFactory<AttributeOptionFactory> */
    use HasFactory;

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
