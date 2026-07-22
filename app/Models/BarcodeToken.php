<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarcodeToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode_token_batch_id',
        'token_code',
        'status',
        'used_at',
        'used_by',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    /**
     * EAN-13 Check Digit Calculation Logic.
     * Weighs odd index digits (1-based) as 1 and even index digits as 3.
     */
    public static function calculateEanCheckDigit(string $number): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $number[$i];
            if ($i % 2 === 1) {
                $sum += $digit * 3;
            } else {
                $sum += $digit * 1;
            }
        }
        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Safely auto-generates a unique 13-digit EAN-13 compliant code.
     * Uses prefix '999' for custom store barcodes.
     */
    public static function generateUniqueCode(): string
    {
        $prefix = '999';

        do {
            $randomPart = str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
            $base = $prefix . $randomPart;
            $checkDigit = self::calculateEanCheckDigit($base);
            $code = $base . $checkDigit;
        } while (self::where('token_code', $code)->exists());

        return $code;
    }

    /* Relationships */

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BarcodeTokenBatch::class, 'barcode_token_batch_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /* Scopes */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'unused');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('token_code', 'like', "%{$search}%")
              ->orWhere('status', 'like', "%{$search}%");
        });
    }
}
