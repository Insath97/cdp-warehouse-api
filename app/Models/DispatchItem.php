<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_dispatch_id',
        'stock_bag_id',
        'selling_price',
        'bag_weight',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'bag_weight' => 'decimal:2',
    ];

    /**
     * Relationship with StockDispatch.
     */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(StockDispatch::class, 'stock_dispatch_id');
    }

    /**
     * Relationship with StockBag.
     */
    public function stockBag(): BelongsTo
    {
        return $this->belongsTo(StockBag::class, 'stock_bag_id');
    }

    /**
     * Relationship with User creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with User updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
