<?php

namespace App\Models;

use App\Enums\CountTagCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NonFgCountTag extends Model
{
    /** @use HasFactory<\Database\Factories\NonFgCountTagFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'non_fg_count_tags';

    protected $fillable = [
        'legacy_id',
        'count_tag_number',
        'count_tag_date',
        'item_id',
        'item_code',
        'item_category_id',
        'unit_of_measure_id',
        'uom_code',
        'location_id',
        'location_name',
        'section_id',
        'section_code',
        'row',
        'col',
        'level',
        'size',
        'condition',
        'qty',
        'tran_date',
        'group_name',
        'created_by',
        'created_by_name',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'legacy_id' => 'integer',
            'count_tag_date' => 'date',
            'item_id' => 'integer',
            'item_category_id' => 'integer',
            'unit_of_measure_id' => 'integer',
            'location_id' => 'integer',
            'section_id' => 'integer',
            'row' => 'integer',
            'col' => 'integer',
            'level' => 'integer',
            'condition' => CountTagCondition::class,
            'qty' => 'decimal:5',
            'tran_date' => 'date',
            'created_by' => 'integer',
            'meta' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(CountTagLocation::class, 'location_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CountTagSection::class, 'section_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
