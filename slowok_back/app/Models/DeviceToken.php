<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $table = 'device_token';
    protected $primaryKey = 'token_id';
    protected $fillable = ['account_id', 'fcm_token', 'device_type'];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }
}
