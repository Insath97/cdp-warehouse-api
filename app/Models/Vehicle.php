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
              ->orWhere('driver_nic', 'like', "%{$search}%");
        });
    }
}
