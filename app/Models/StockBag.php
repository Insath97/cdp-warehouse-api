<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBag extends Model
{
    use HasFactory;

    protected $fillable = [
        'bag_code',
        'bag_number',
        'stock_in_batch_id',
        'stock_in_batch_item_id',
        'branch_id',
        'warehouse_id',
        'supplier_id',
        'item_type_id',
        'item_variety_id',
        'bag_weight',
        'unit_price',
        'selling_price',
        'total_price',
        'total_sales_amount',
        'status',
        'barcode_code',
        'qr_code',
        'location_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'bag_number' => 'integer',
        'bag_weight' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'total_sales_amount' => 'decimal:2',
    ];

    /**
     * Boot model for auto-generating bag_number, bag_code, barcode_code, and qr_code.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bag) {
            // Auto sequential bag number per batch if not provided
            if (empty($bag->bag_number) && $bag->stock_in_batch_id) {
                $maxNum = self::where('stock_in_batch_id', $bag->stock_in_batch_id)->max('bag_number');
                $bag->bag_number = ($maxNum ?? 0) + 1;
            }

            // Auto generate bag_code if empty
            if (empty($bag->bag_code)) {
                $datePrefix = date('Ymd');
                $randomStr = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                $bag->bag_code = 'BAG-' . $datePrefix . '-' . str_pad($bag->bag_number, 4, '0', STR_PAD_LEFT) . '-' . $randomStr;
            }

            // Auto generate barcode if empty
            if (empty($bag->barcode_code)) {
                $bag->barcode_code = $bag->bag_code;
            }

            // Auto generate QR code if empty
            if (empty($bag->qr_code)) {
                $bag->qr_code = 'QR-' . $bag->bag_code;
            }

            // Auto calculate total_price and total_sales_amount
            $weight = (float) ($bag->bag_weight ?? 0);
            $uPrice = (float) ($bag->unit_price ?? 0);
            $sPrice = (float) ($bag->selling_price ?? 0);

            if (empty($bag->total_price) || $bag->total_price <= 0) {
                $bag->total_price = $weight * $uPrice;
            }

            if (empty($bag->total_sales_amount) || $bag->total_sales_amount <= 0) {
                $bag->total_sales_amount = $weight * $sPrice;
            }
        });
    }

    /* Relationships */

    public function stockInBatch(): BelongsTo
    {
        return $this->belongsTo(StockInBatch::class, 'stock_in_batch_id');
    }

    public function stockInBatchItem(): BelongsTo
    {
        return $this->belongsTo(StockInBatchItem::class, 'stock_in_batch_item_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    public function itemVariety(): BelongsTo
    {
        return $this->belongsTo(ItemVariety::class, 'item_variety_id');
    }

    public function qualityInspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class, 'stock_bag_id');
    }

    public function dispatchItems(): HasMany
    {
        return $this->hasMany(DispatchItem::class, 'stock_bag_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /* Scopes */

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('bag_code', 'like', "%{$search}%")
              ->orWhere('barcode_code', 'like', "%{$search}%")
              ->orWhere('qr_code', 'like', "%{$search}%")
              ->orWhere('location_id', 'like', "%{$search}%")
              ->orWhere('notes', 'like', "%{$search}%")
              ->orWhereHas('stockInBatch', function (Builder $bq) use ($search) {
                  $bq->where('batch_number', 'like', "%{$search}%");
              });
        });
    }

    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeByBatch(Builder $query, ?int $batchId): Builder
    {
        if (empty($batchId)) {
            return $query;
        }

        return $query->where('stock_in_batch_id', $batchId);
    }

    public function scopeByWarehouse(Builder $query, ?int $warehouseId): Builder
    {
        if (empty($warehouseId)) {
            return $query;
        }

        return $query->where('warehouse_id', $warehouseId);
    }
}
