<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentAssignment extends Model
{
    protected $table = 'content_assignment';
    protected $primaryKey = 'assignment_id';

    protected $fillable = [
        'profile_id',
        'assignable_type',
        'assignable_id',
        'assigned_by',
        'assigned_at',
        'due_date',
        'note',
        'status',
        'sort_order',
        'allow_retry',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'due_date' => 'date',
        'allow_retry' => 'boolean',
    ];

    public function profile()
    {
        return $this->belongsTo(UserProfile::class, 'profile_id', 'profile_id');
    }

    public function assigner()
    {
        return $this->belongsTo(Account::class, 'assigned_by', 'account_id');
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

    protected $appends = ['assignable_title'];

    public function getAssignableTitleAttribute(): ?string
    {
        return match ($this->assignable_type) {
            'screening_test' => $this->screeningTest?->title,
            'learning_content' => $this->learningContent?->title,
            'challenge' => $this->challenge
                ? '[' . ($this->challenge->challenge_type ?? '') . ' #' . ($this->challenge->sort_order ?? 0) . '] ' . $this->challenge->title
                : null,
            default => null,
        };
    }
}
