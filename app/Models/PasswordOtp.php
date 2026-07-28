<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordOtp extends Model
{
    use HasFactory;

    protected $table = 'password_otps';

    protected $fillable = [
        'user_id',
        'identifier',
        'otp_code',
        'reset_token',
        'channel',
        'expires_at',
        'verified_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /* Relationships */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* Helper Methods */

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    /* Scopes */

    public function scopeValidOtp(Builder $query, string $identifier, string $otpCode): Builder
    {
        return $query->where('identifier', $identifier)
            ->where('otp_code', $otpCode)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now());
    }
}
