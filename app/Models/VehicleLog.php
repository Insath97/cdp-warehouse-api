<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VehicleLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'log_number',
        'vehicle_id',
        'log_type',
        'direction',
        'entry_time',
        'exit_time',
        'driver_name',
        'driver_phone',
        'driver_nic',
        'purpose',
        'notes',
        'entry_license_plate_image',
        'entry_vehicle_image',
        'entry_document',
        'exit_license_plate_image',
        'exit_vehicle_image',
        'exit_document',
        'logged_by',
    ];

    protected $casts = [
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
    ];

    protected $appends = [
        'entry_license_plate_image_url',
        'entry_vehicle_image_url',
        'entry_document_url',
        'exit_license_plate_image_url',
        'exit_vehicle_image_url',
        'exit_document_url',
    ];

    /**
     * Relationship with Vehicle.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Relationship with the User who created the log.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    /**
     * Get URL helper.
     */
    private function getFileUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return url(Storage::url($path));
    }

    // Accessors for public URLs
    public function getEntryLicensePlateImageUrlAttribute(): ?string
    {
        return $this->getFileUrl($this->entry_license_plate_image);
    }

    public function getEntryVehicleImageUrlAttribute(): ?string
    {
        return $this->getFileUrl($this->entry_vehicle_image);
    }

    public function getEntryDocumentUrlAttribute(): ?string
    {
        return $this->getFileUrl($this->entry_document);
    }

    public function getExitLicensePlateImageUrlAttribute(): ?string
    {
        return $this->getFileUrl($this->exit_license_plate_image);
    }

    public function getExitVehicleImageUrlAttribute(): ?string
    {
        return $this->getFileUrl($this->exit_vehicle_image);
    }

    public function getExitDocumentUrlAttribute(): ?string
    {
        return $this->getFileUrl($this->exit_document);
    }

    /**
     * Scope to search vehicle logs by driver name, nic, log number, or vehicle number.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('driver_name', 'like', "%{$search}%")
              ->orWhere('driver_nic', 'like', "%{$search}%")
              ->orWhere('log_number', 'like', "%{$search}%")
              ->orWhereHas('vehicle', function (Builder $vq) use ($search) {
                  $vq->where('vehicle_number', 'like', "%{$search}%");
              });
        });
    }
}
