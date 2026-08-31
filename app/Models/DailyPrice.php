<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'item_variety_id',
        'buying_price',
        'selling_price',
        'created_by',
        'updated_by',
    ];

    /**
     * Boot the model to auto-set today's date if not provided.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($dailyPrice) {
            if (empty($dailyPrice->date)) {
                $dailyPrice->date = \Carbon\Carbon::today()->toDateString();
            }
        });
    }

    protected $casts = [
        'date' => 'date:Y-m-d',
        'buying_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    /**
     * Relationship with ItemVariety.
     */
    public function itemVariety(): BelongsTo
    {
        return $this->belongsTo(ItemVariety::class, 'item_variety_id');
    }

    /**
     * Relationship with Creator (User).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with Updater (User).
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to search daily prices by item variety name or code.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->whereHas('itemVariety', function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query by specific date.
     */
    public function scopeByDate(Builder $query, ?string $date): Builder
    {
        if (empty($date)) {
            return $query;
        }

        return $query->whereDate('date', $date);
    }

    /**
     * Scope a query by date range.
     */
    public function scopeDateRange(Builder $query, ?string $fromDate, ?string $toDate): Builder
    {
        if (!empty($fromDate)) {
            $query->whereDate('date', '>=', $fromDate);
        }

        if (!empty($toDate)) {
            $query->whereDate('date', '<=', $toDate);
        }

        return $query;
    }

    /**
     * Scope a query by item variety.
     */
    public function scopeByItemVariety(Builder $query, ?int $varietyId): Builder
    {
        if (empty($varietyId)) {
            return $query;
        }

        return $query->where('item_variety_id', $varietyId);
    }
}
