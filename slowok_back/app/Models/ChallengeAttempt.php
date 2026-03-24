<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeAttempt extends Model
{
    use HasFactory;
    protected $table = 'challenge_attempt';
    protected $primaryKey = 'attempt_id';
    protected $fillable = ['profile_id', 'challenge_id', 'score', 'is_passed', 'answers', 'time_spent'];
    protected $casts = ['is_passed' => 'boolean', 'answers' => 'array'];

    public function profile() { return $this->belongsTo(UserProfile::class, 'profile_id', 'profile_id'); }
    public function challenge() { return $this->belongsTo(Challenge::class, 'challenge_id', 'challenge_id'); }
}
