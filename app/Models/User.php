<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'user_scope',
        'branch_id',
        'warehouse_id',
        'is_active',
        'can_login',
        'password_change_count',
        'last_login_at',
        'last_login_ip',
        'email_verified_at',
        'email_verification_token',
        'email_verification_token_expires_at',
        'enable_email_notification',
        'enable_system_notification',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_token_expires_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'can_login' => 'boolean',
            'enable_email_notification' => 'boolean',
            'enable_system_notification' => 'boolean',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'user_scope' => $this->user_scope,
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
        ];
    }

    /* Relationships */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /* Scope Helpers */

    public function isGlobal(): bool
    {
        return $this->user_scope === 'global' || $this->hasRole('Super Admin') || $this->hasRole('System Admin');
    }

    public function isBranchScoped(): bool
    {
        return $this->user_scope === 'branch';
    }

    public function isWarehouseScoped(): bool
    {
        return $this->user_scope === 'warehouse';
    }

    /**
     * Check if user has reached self-service password change limit.
     */
    public function hasReachedPasswordChangeLimit(): bool
    {
        $limit = (int) SystemSetting::get('staff_password_change_limit', 3);
        if ($limit <= 0) {
            return false;
        }
        return $this->password_change_count >= $limit;
    }

    /**
     * Get accessible warehouse IDs for the current user based on user scope.
     * Returns null if user is global (unrestricted access), or an array of warehouse IDs.
     */
    public function getAccessibleWarehouseIds(): ?array
    {
        if ($this->isGlobal()) {
            return null;
        }

        if ($this->isWarehouseScoped() && $this->warehouse_id) {
            return [$this->warehouse_id];
        }

        if ($this->isBranchScoped() && $this->branch_id) {
            return Warehouse::where('branch_id', $this->branch_id)->pluck('id')->toArray();
        }

        return [];
    }

    /**
     * Filter query based on the logged-in user's access scope.
     */
    public function scopeAccessibleBy(Builder $query, User $authUser): Builder
    {
        if ($authUser->isGlobal()) {
            return $query;
        }

        if ($authUser->isBranchScoped()) {
            return $query->where('branch_id', $authUser->branch_id);
        }

        if ($authUser->isWarehouseScoped()) {
            return $query->where('warehouse_id', $authUser->warehouse_id);
        }

        return $query->where('id', $authUser->id);
    }

    /**
     * Search scope by name, username, email, phone, branch name, or warehouse name.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('username', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhereHas('branch', function (Builder $bq) use ($search) {
                  $bq->where('name', 'like', "%{$search}%");
              })
              ->orWhereHas('warehouse', function (Builder $wq) use ($search) {
                  $wq->where('name', 'like', "%{$search}%");
              });
        });
    }

    /* Helper Methods */

    public function canLogin(): bool
    {
        return $this->is_active && $this->can_login;
    }

    public function updateLastLogin($ipAddress = null)
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);
    }

    public function generateEmailVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->update([
            'email_verification_token' => $token,
            'email_verification_token_expires_at' => now()->addHours(24),
        ]);

        return $token;
    }

    public function markEmailAsVerified()
    {
        $this->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'email_verification_token_expires_at' => null,
        ]);
    }

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }
}
