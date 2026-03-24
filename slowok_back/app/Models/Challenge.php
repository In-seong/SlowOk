<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    use HasFactory, Traits\BelongsToInstitution;
    protected $table = 'challenge';
    protected $primaryKey = 'challenge_id';
    protected $fillable = ['category_id', 'title', 'challenge_type', 'difficulty_level', 'institution_id', 'is_active'];

    public function category() { return $this->belongsTo(LearningCategory::class, 'category_id', 'category_id'); }
    public function attempts() { return $this->hasMany(ChallengeAttempt::class, 'challenge_id', 'challenge_id'); }
    public function questions() { return $this->hasMany(ChallengeQuestion::class, 'challenge_id', 'challenge_id')->orderBy('order'); }
}
