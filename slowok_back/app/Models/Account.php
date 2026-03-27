<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Account extends Authenticatable
{
    use HasApiTokens, HasFactory;

    const ROLE_USER = 'USER';
    const ROLE_ADMIN = 'ADMIN';
    const ROLE_MASTER = 'MASTER';
    const ROLE_TEST = 'TEST';

    protected $table = 'account';
    protected $primaryKey = 'account_id';

    protected $fillable = [
        'username',
        'password_hash',
        'role',
        'institution_id',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isMaster(): bool
    {
        return $this->role === self::ROLE_MASTER;
    }

    public function isTest(): bool
    {
        return $this->role === self::ROLE_TEST;
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function isAdminLevel(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MASTER, self::ROLE_TEST]);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class, 'institution_id', 'institution_id');
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'account_id', 'account_id');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'account_id', 'account_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'account_id', 'account_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(
            AdminPermission::class,
            'admin_permission_grant',
            'account_id',
            'permission_id'
        )->withPivot('granted_by')->withTimestamps();
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isMaster()) {
            return true;
        }

        return $this->permissions()->where('permission_key', $key)->exists();
    }
}
