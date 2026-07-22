<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockDispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatch_number',
        'warehouse_id',
        'branch_id',
        'buyer_id',
        'dispatch_type',
        'dispatch_date',
        'delivery_note_reference',
        'vehicle_id',
        'vehicle_log_id',
        'total_bags',
        'total_weight',
        'total_sales_amount',
        'status',
        'gate_pass_number',
        'gate_exit_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dispatch_date' => 'date:Y-m-d',
        'gate_exit_at' => 'datetime',
        'total_bags' => 'integer',
        'total_weight' => 'decimal:2',
        'total_sales_amount' => 'decimal:2',
    ];

    /**
     * Boot model for sequential dispatch number auto-generation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($dispatch) {
            if (empty($dispatch->dispatch_number)) {
                $datePrefix = date('Ymd');
                $countToday = self::whereDate('created_at', date('Y-m-d'))->count() + 1;
                $dispatch->dispatch_number = 'DSP-' . $datePrefix . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /* Relationships */

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function vehicleLog(): BelongsTo
    {
        return $this->belongsTo(VehicleLog::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispatchItem::class, 'stock_dispatch_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'stock_dispatch_id');
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
            $q->where('dispatch_number', 'like', "%{$search}%")
              ->orWhere('delivery_note_reference', 'like', "%{$search}%")
              ->orWhere('gate_pass_number', 'like', "%{$search}%")
              ->orWhere('notes', 'like', "%{$search}%")
              ->orWhereHas('buyer', function (Builder $bq) use ($search) {
                  $bq->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
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

    public function scopeByWarehouse(Builder $query, ?int $warehouseId): Builder
    {
        if (empty($warehouseId)) {
            return $query;
        }

        return $query->where('warehouse_id', $warehouseId);
    }
}
