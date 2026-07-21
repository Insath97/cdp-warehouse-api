<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_in_batch_id',
        'stock_bag_id',
        'item_type_id',
        'item_variety_id',
        'original_weight',
        'current_weight',
        'weight_difference',
        'weight_change_type',
        'moisture_percentage',
        'grade',
        'broken_percentage',
        'colour_quality',
        'inspection_result',
        'remarks',
        'inspected_by',
        'inspected_at',
    ];

    protected $casts = [
        'original_weight' => 'decimal:2',
        'current_weight' => 'decimal:2',
        'weight_difference' => 'decimal:2',
        'moisture_percentage' => 'decimal:2',
        'broken_percentage' => 'decimal:2',
        'inspected_at' => 'datetime',
    ];

    /**
     * Boot hook for auto-calculating weight difference and change type.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($inspection) {
            if (empty($inspection->inspected_at)) {
                $inspection->inspected_at = now();
            }

            if (isset($inspection->original_weight) && isset($inspection->current_weight)) {
                $orig = (float) $inspection->original_weight;
                $curr = (float) $inspection->current_weight;
                $diff = $curr - $orig;

                $inspection->weight_difference = $diff;

                if ($diff < -0.001) {
                    $inspection->weight_change_type = 'weight_loss';
                } elseif ($diff > 0.001) {
                    $inspection->weight_change_type = 'weight_gain';
                } else {
                    $inspection->weight_change_type = 'no_change';
                }
            }
        });
    }

    /* Relationships */

    public function stockInBatch(): BelongsTo
    {
        return $this->belongsTo(StockInBatch::class, 'stock_in_batch_id');
    }

    public function stockBag(): BelongsTo
    {
        return $this->belongsTo(StockBag::class, 'stock_bag_id');
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    public function itemVariety(): BelongsTo
    {
        return $this->belongsTo(ItemVariety::class, 'item_variety_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    /* Scopes */

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('remarks', 'like', "%{$search}%")
              ->orWhere('grade', 'like', "%{$search}%")
              ->orWhere('inspection_result', 'like', "%{$search}%")
              ->orWhereHas('stockInBatch', function (Builder $bq) use ($search) {
                  $bq->where('batch_number', 'like', "%{$search}%")
                    ->orWhere('grn_number', 'like', "%{$search}%");
              })
              ->orWhereHas('stockBag', function (Builder $bgq) use ($search) {
                  $bgq->where('bag_code', 'like', "%{$search}%");
              });
        });
    }

    public function scopeByResult(Builder $query, ?string $result): Builder
    {
        if (empty($result)) {
            return $query;
        }

        return $query->where('inspection_result', $result);
    }

    public function scopeByGrade(Builder $query, ?string $grade): Builder
    {
        if (empty($grade)) {
            return $query;
        }

        return $query->where('grade', $grade);
    }

    public function scopeByBatch(Builder $query, ?int $batchId): Builder
    {
        if (empty($batchId)) {
            return $query;
        }

        return $query->where('stock_in_batch_id', $batchId);
    }

    public function scopeByBag(Builder $query, ?int $bagId): Builder
    {
        if (empty($bagId)) {
            return $query;
        }

        return $query->where('stock_bag_id', $bagId);
    }
}
