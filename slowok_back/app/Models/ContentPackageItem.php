<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPackageItem extends Model
{
    protected $table = 'content_package_item';
    protected $primaryKey = 'item_id';

    protected $fillable = [
        'package_id',
        'assignable_type',
        'assignable_id',
        'sort_order',
    ];

    protected $appends = ['assignable_title'];

    public function package()
    {
        return $this->belongsTo(ContentPackage::class, 'package_id', 'package_id');
    }

    public function screeningTest()
    {
        return $this->belongsTo(ScreeningTest::class, 'assignable_id', 'test_id');
    }

    public function learningContent()
    {
        return $this->belongsTo(LearningContent::class, 'assignable_id', 'content_id');
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class, 'assignable_id', 'challenge_id');
    }

    public function getAssignableTitleAttribute(): ?string
    {
        return match ($this->assignable_type) {
            'screening_test' => $this->screeningTest?->title,
            'learning_content' => $this->learningContent?->title,
            'challenge' => $this->challenge?->title,
            default => null,
        };
    }
}
