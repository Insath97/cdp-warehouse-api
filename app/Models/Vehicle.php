<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_number',
        'driver_name',
        'driver_phone',
        'driver_nic',
        'vehicle_type',
        'ownership_type',
        'supplier_id',
        'availability_status',
        'tare_weight',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tare_weight' => 'decimal:2',
    ];

    /**
     * Relationship with Supplier (if ownership_type is supplier).
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Relationship with user creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with user updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include active vehicles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include company owned vehicles.
     */
    public function scopeOwn(Builder $query): Builder
    {
        return $query->where('ownership_type', 'own');
    }

    /**
     * Scope a query to only include available vehicles.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('availability_status', 'available');
    }

    public function scopeByOwnershipType(Builder $query, ?string $type): Builder
    {
        if (empty($type)) {
            return $query;
        }

        return $query->where('ownership_type', $type);
    }

    public function scopeByAvailabilityStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('availability_status', $status);
    }

    /**
     * Scope a query to search vehicles by vehicle number, driver name, phone, or NIC.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('vehicle_number', 'like', "%{$search}%")
              ->orWhere('driver_name', 'like', "%{$search}%")
              ->orWhere('driver_phone', 'like', "%{$search}%")
              ->orWhere('driver_nic', 'like', "%{$search}%")
              ->orWhereHas('supplier', function (Builder $sq) use ($search) {
                  $sq->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
              });
        });
    }
}
