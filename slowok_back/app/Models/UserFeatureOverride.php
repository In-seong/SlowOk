<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFeatureOverride extends Model
{
    protected $table = 'user_feature_override';
    protected $primaryKey = 'override_id';
    protected $fillable = ['profile_id', 'feature_key', 'enabled'];
    protected $casts = ['enabled' => 'boolean'];

    public function profile()
    {
        return $this->belongsTo(UserProfile::class, 'profile_id', 'profile_id');
    }
}
