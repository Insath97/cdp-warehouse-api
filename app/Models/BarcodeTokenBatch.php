<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarcodeTokenBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'item_type_id',
        'item_variety_id',
        'token_type',
        'quantity_requested',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
    ];

    protected $appends = ['quantity_used'];

    /**
     * Boot model for auto sequential batch number generation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($batch) {
            if (empty($batch->batch_number)) {
                $datePrefix = date('Ymd');
                $countToday = self::whereDate('created_at', date('Y-m-d'))->count() + 1;
                $batch->batch_number = 'TKB-' . $datePrefix . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Accessor to dynamically calculate the number of used tokens.
     */
    public function getQuantityUsedAttribute(): int
    {
        return $this->tokens()->where('status', 'used')->count();
    }

    /* Relationships */

    public function tokens(): HasMany
    {
        return $this->hasMany(BarcodeToken::class, 'barcode_token_batch_id');
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    public function itemVariety(): BelongsTo
    {
        return $this->belongsTo(ItemVariety::class, 'item_variety_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* Scopes */

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('batch_number', 'like', "%{$search}%")
              ->orWhere('notes', 'like', "%{$search}%")
              ->orWhereHas('itemVariety', function (Builder $vq) use ($search) {
                  $vq->where('name', 'like', "%{$search}%");
              });
        });
    }
}
