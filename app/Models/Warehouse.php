<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'contact_person',
        'phone_primary',
        'phone_secondary',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'capacity_mt',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity_mt' => 'decimal:2',
    ];

    /**
     * Scope a query to only include active warehouses.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search warehouses by name, code, contact person, phone, or city.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('contact_person', 'like', "%{$search}%")
              ->orWhere('phone_primary', 'like', "%{$search}%")
              ->orWhere('city', 'like', "%{$search}%")
              ->orWhereHas('branch', function (Builder $bq) use ($search) {
                  $bq->where('name', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Get the branch that owns the warehouse.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
