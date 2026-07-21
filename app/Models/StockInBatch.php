<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockInBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'supplier_id',
        'warehouse_id',
        'vehicle_id',
        'vehicle_log_id',
        'grn_number',
        'received_date',
        'gross_weight',
        'tare_weight',
        'net_weight',
        'total_bags',
        'total_amount',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'received_date' => 'date:Y-m-d',
        'gross_weight' => 'decimal:2',
        'tare_weight' => 'decimal:2',
        'net_weight' => 'decimal:2',
        'total_bags' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Boot model for auto batch number generation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($batch) {
            if (empty($batch->batch_number)) {
                $datePrefix = date('Ymd');
                $countToday = self::whereDate('created_at', date('Y-m-d'))->count() + 1;
                $batch->batch_number = 'STK-' . $datePrefix . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Relationship with StockInBatchItems.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockInBatchItem::class, 'stock_in_batch_id');
    }

    /**
     * Relationship with Supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relationship with Warehouse.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relationship with Vehicle.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Relationship with VehicleLog.
     */
    public function vehicleLog(): BelongsTo
    {
        return $this->belongsTo(VehicleLog::class);
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

    /**
     * Scope a query to search batches.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('batch_number', 'like', "%{$search}%")
              ->orWhere('grn_number', 'like', "%{$search}%")
              ->orWhere('notes', 'like', "%{$search}%")
              ->orWhereHas('supplier', function (Builder $sq) use ($search) {
                  $sq->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }
}
