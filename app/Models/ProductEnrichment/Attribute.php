<?php

namespace App\Models\ProductEnrichment;

use Database\Factories\ProductEnrichment\AttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property bool $is_required
 * @property bool $is_unique
 * @property bool $is_ai_enrichable
 * @property string|null $validation
 * @property int $position
 *
 * @method static AttributeFactory factory($count = null, $state = [])
 */
#[Fillable(['code', 'name', 'type', 'is_required', 'is_unique', 'is_ai_enrichable', 'validation', 'position'])]
#[Table(name: 'attributes')]
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_ai_enrichable' => 'boolean',
        ];
    }

    /** @return HasMany<AttributeOption, $this> */
    public function attributeOptions(): HasMany
    {
        return $this->hasMany(AttributeOption::class);
    }

    /** @return HasMany<ProductAttributeValue, $this> */
    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
