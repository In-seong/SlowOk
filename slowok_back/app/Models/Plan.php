<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plan';
    protected $primaryKey = 'plan_id';
    protected $fillable = ['name', 'description', 'price', 'sort_order', 'is_active'];
    protected $casts = [
        'price' => 'integer',
        'is_active' => 'boolean',
    ];

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'plan_feature', 'plan_id', 'feature_id')
            ->withTimestamps();
    }
}
