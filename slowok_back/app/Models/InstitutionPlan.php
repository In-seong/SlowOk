<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionPlan extends Model
{
    protected $table = 'institution_plan';
    protected $primaryKey = 'institution_plan_id';
    protected $fillable = ['institution_id', 'plan_id', 'started_at', 'expires_at'];
    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class, 'institution_id', 'institution_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }

    /**
     * 현재 유효한 플랜인지
     */
    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
