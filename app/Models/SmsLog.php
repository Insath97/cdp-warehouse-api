<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    use HasFactory;

    protected $table = 'sms_logs';

    protected $fillable = [
        'supplier_id',
        'buyer_id',
        'user_id',
        'phone_number',
        'message',
        'status',
        'transaction_id',
        'sent_by',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relationship to Supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relationship to Buyer
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /**
     * Relationship to User (Recipient)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship to User (Sender)
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /* Scopes */

    /**
     * Scope a query to search SMS logs by phone number, message, transaction_id, or related user/supplier/buyer details.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('phone_number', 'like', "%{$search}%")
              ->orWhere('message', 'like', "%{$search}%")
              ->orWhere('transaction_id', 'like', "%{$search}%")
              ->orWhereHas('sender', function (Builder $uq) use ($search) {
                  $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
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

    /**
     * Scope a query to filter by supplier.
     */
    public function scopeBySupplier(Builder $query, ?int $supplierId): Builder
    {
        if (empty($supplierId)) {
            return $query;
        }

        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope a query to filter by buyer.
     */
    public function scopeByBuyer(Builder $query, ?int $buyerId): Builder
    {
        if (empty($buyerId)) {
            return $query;
        }

        return $query->where('buyer_id', $buyerId);
    }

    /**
     * Scope a query to filter by sender user.
     */
    public function scopeBySender(Builder $query, ?int $userId): Builder
    {
        if (empty($userId)) {
            return $query;
        }

        return $query->where('sent_by', $userId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if (!empty($startDate)) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if (!empty($endDate)) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }
}
