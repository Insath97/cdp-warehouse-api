<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockInBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_in_batch_id',
        'item_type_id',
        'item_variety_id',
        'quantity_bags',
        'unit_weight',
        'total_weight',
        'unit_price',
        'total_price',
        'remaining_quantity_bags',
        'remaining_weight',
        'notes',
    ];

    protected $casts = [
        'quantity_bags' => 'integer',
        'unit_weight' => 'decimal:2',
        'total_weight' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'remaining_quantity_bags' => 'integer',
        'remaining_weight' => 'decimal:2',
    ];

    /**
     * Relationship with parent StockInBatch.
     */
    public function stockInBatch(): BelongsTo
    {
        return $this->belongsTo(StockInBatch::class, 'stock_in_batch_id');
    }

    /**
     * Relationship with ItemType.
     */
    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    /**
     * Relationship with ItemVariety.
     */
    public function itemVariety(): BelongsTo
    {
        return $this->belongsTo(ItemVariety::class, 'item_variety_id');
    }

    /**
     * Relationship with StockBags.
     */
    public function bags(): HasMany
    {
        return $this->hasMany(StockBag::class, 'stock_in_batch_item_id');
    }
}
