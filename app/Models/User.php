<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
        'lang',
        'role',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if the user is a staff member.
     */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * Check if the user is a warehouse manager.
     */
    public function isWarehouse(): bool
    {
        return $this->role === 'warehouse';
    }

    /**
     * Check if the user is a branch staff member.
     */
    public function isBranch(): bool
    {
        return $this->role === 'branch';
    }

    /**
     * Check if the user can access warehouse module.
     * admin, warehouse, branch roles are allowed.
     */
    public function canAccessWarehouse(): bool
    {
        return in_array($this->role, ['admin', 'warehouse', 'branch']);
    }

    /**
     * Check if the user can manage warehouse (create receipts, dispatches, stocktakes).
     * admin and warehouse roles are allowed.
     */
    public function canManageWarehouse(): bool
    {
        return in_array($this->role, ['admin', 'warehouse']);
    }

    /**
     * Get role display label in Chinese.
     */
    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'admin'     => '總管理員',
            'staff'     => '門市員工',
            'warehouse' => '倉庫管理員',
            'branch'    => '分店人員',
            default     => $this->role,
        };
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLogs::class, 'user_id');
    }

    public function rememberDevices(): HasMany
    {
        return $this->hasMany(RememberDevice::class, 'user_id');
    }

    /**
     * Branches associated with this user (for branch role).
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches', 'user_id', 'branch_id');
    }
}
