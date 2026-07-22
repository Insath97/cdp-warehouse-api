<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'buyer_id',
        'stock_dispatch_id',
        'invoice_date',
        'due_date',
        'sub_total',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'payment_status',
        'payment_method',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'sub_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Boot model for sequential invoice number auto-generation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $datePrefix = date('Ymd');
                $countToday = self::whereDate('created_at', date('Y-m-d'))->count() + 1;
                $invoice->invoice_number = 'INV-' . $datePrefix . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /* Relationships */

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(StockDispatch::class, 'stock_dispatch_id');
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
            $q->where('invoice_number', 'like', "%{$search}%")
              ->orWhere('payment_method', 'like', "%{$search}%")
              ->orWhere('notes', 'like', "%{$search}%")
              ->orWhereHas('buyer', function (Builder $bq) use ($search) {
                  $bq->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
              });
        });
    }

    public function scopeByPaymentStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('payment_status', $status);
    }
}
