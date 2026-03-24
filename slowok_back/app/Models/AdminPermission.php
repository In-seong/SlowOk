<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPermission extends Model
{
    protected $table = 'admin_permission';
    protected $primaryKey = 'permission_id';

    protected $fillable = [
        'permission_key',
        'permission_name',
        'description',
        'category',
    ];

    public function accounts()
    {
        return $this->belongsToMany(
            Account::class,
            'admin_permission_grant',
            'permission_id',
            'account_id'
        )->withPivot('granted_by')->withTimestamps();
    }
}
