<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'stock_in_batch_id',
        'supplier_id',
        'warehouse_id',
        'branch_id',
        'receipt_date',
        'total_bags',
        'total_weight',
        'total_amount',
        'status',
        'notes',
        'created_by',
        'printed_at',
        'printed_by',
    ];

    protected $casts = [
        'receipt_date' => 'datetime',
        'printed_at' => 'datetime',
        'total_bags' => 'integer',
        'total_weight' => 'float',
        'total_amount' => 'float',
    ];

    /* Relationships */

    public function stockInBatch(): BelongsTo
    {
        return $this->belongsTo(StockInBatch::class, 'stock_in_batch_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    /* Scopes */

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('receipt_number', 'like', "%{$term}%")
              ->orWhereHas('stockInBatch', function ($b) use ($term) {
                  $b->where('batch_number', 'like', "%{$term}%")
                    ->orWhere('grn_number', 'like', "%{$term}%");
              })
              ->orWhereHas('supplier', function ($s) use ($term) {
                  $s->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
              });
        });
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
