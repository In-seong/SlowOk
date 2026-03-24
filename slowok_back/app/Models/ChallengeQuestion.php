<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeQuestion extends Model
{
    use HasFactory;
    protected $table = 'challenge_question';
    protected $primaryKey = 'question_id';
    protected $fillable = ['challenge_id', 'content', 'question_type', 'options', 'correct_answer', 'image_url', 'order', 'match_pairs', 'accept_answers'];
    protected $hidden = ['correct_answer'];
    protected $casts = ['options' => 'array', 'match_pairs' => 'array', 'accept_answers' => 'array'];

    public function challenge() { return $this->belongsTo(Challenge::class, 'challenge_id', 'challenge_id'); }
}
