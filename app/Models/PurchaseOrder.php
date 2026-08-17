<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'warehouse_id',
        'item_variety_id',
        'variety_type',
        'purchase_price_per_kg',
        'market_price_per_kg',
        'number_of_bags',
        'total_weights',
        'total_sales_price',
        'total_market_price',
        'status',
        'payment_status',
        'payment_proof_document',
        'created_by',
        'updated_by',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'purchase_price_per_kg' => 'decimal:2',
        'market_price_per_kg' => 'decimal:2',
        'number_of_bags' => 'integer',
        'total_weights' => 'decimal:2',
        'total_sales_price' => 'decimal:2',
        'total_market_price' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    /**
     * Boot model for auto po number generation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($po) {
            if (empty($po->po_number)) {
                $datePrefix = date('Ymd');
                $countToday = self::whereDate('created_at', date('Y-m-d'))->count() + 1;
                $po->po_number = 'PO-' . $datePrefix . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);
            }
        });
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
     * Relationship with ItemVariety.
     */
    public function itemVariety(): BelongsTo
    {
        return $this->belongsTo(ItemVariety::class);
    }

    /**
     * Relationship with Creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with Updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship with Verifier.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Relationship with Bargains.
     */
    public function bargains(): HasMany
    {
        return $this->hasMany(PurchaseOrderBargain::class, 'purchase_order_id');
    }

    /**
     * Relationship with Latest Bargain.
     */
    public function latestBargain(): HasOne
    {
        return $this->hasOne(PurchaseOrderBargain::class, 'purchase_order_id')->latestOfMany();
    }

    /**
     * Check if it is the creator's turn to take action in the bargaining loop.
     */
    public function isWaitingForCreator(): bool
    {
        if ($this->status !== 'price_suggested') {
            return false;
        }

        $latest = $this->latestBargain;
        return $latest && (int) $latest->user_id !== (int) $this->created_by;
    }

    /**
     * Check if it is the approver's turn to take action in the bargaining loop.
     */
    public function isWaitingForApprover(): bool
    {
        if ($this->status === 'pending_approval') {
            return true;
        }

        if ($this->status !== 'price_suggested') {
            return false;
        }

        $latest = $this->latestBargain;
        return $latest && (int) $latest->user_id === (int) $this->created_by;
    }

    /**
     * Scope a query to search purchase orders.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('po_number', 'like', "%{$search}%")
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
