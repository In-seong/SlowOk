<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPermissionGrant extends Model
{
    protected $table = 'admin_permission_grant';
    protected $primaryKey = 'grant_id';

    protected $fillable = [
        'account_id',
        'permission_id',
        'granted_by',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }

    public function permission()
    {
        return $this->belongsTo(AdminPermission::class, 'permission_id', 'permission_id');
    }

    public function granter()
    {
        return $this->belongsTo(Account::class, 'granted_by', 'account_id');
    }
}
