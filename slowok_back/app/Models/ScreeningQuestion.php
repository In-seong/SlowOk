<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreeningQuestion extends Model
{
    use HasFactory;
    protected $table = 'screening_question';
    protected $primaryKey = 'question_id';
    protected $fillable = ['test_id', 'content', 'question_type', 'sub_domain', 'options', 'correct_answer', 'order'];
    protected $casts = ['options' => 'array'];

    public function test() { return $this->belongsTo(ScreeningTest::class, 'test_id', 'test_id'); }
}
