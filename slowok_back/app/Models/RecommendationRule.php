<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationRule extends Model
{
    use Traits\BelongsToInstitution;

    protected $table = 'recommendation_rule';
    protected $primaryKey = 'rule_id';

    protected $fillable = [
        'category_id',
        'score_min',
        'score_max',
        'package_id',
        'institution_id',
        'is_active',
    ];

    protected $casts = [
        'score_min' => 'integer',
        'score_max' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(LearningCategory::class, 'category_id', 'category_id');
    }

    public function package()
    {
        return $this->belongsTo(ContentPackage::class, 'package_id', 'package_id');
    }
}
