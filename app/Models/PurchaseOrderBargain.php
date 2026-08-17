<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderBargain extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id',
        'user_id',
        'action',
        'purchase_price_per_kg',
        'total_sales_price',
        'note',
    ];

    protected $casts = [
        'purchase_price_per_kg' => 'decimal:2',
        'total_sales_price' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    /**
     * Relationship with PurchaseOrder.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * Relationship with User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
