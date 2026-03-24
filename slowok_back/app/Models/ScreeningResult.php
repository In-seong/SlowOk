<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreeningResult extends Model
{
    use HasFactory;
    protected $table = 'screening_result';
    protected $primaryKey = 'result_id';
    protected $fillable = ['profile_id', 'test_id', 'score', 'level', 'analysis', 'sub_scores'];
    protected $casts = ['analysis' => 'array', 'sub_scores' => 'array'];

    public function profile() { return $this->belongsTo(UserProfile::class, 'profile_id', 'profile_id'); }
    public function test() { return $this->belongsTo(ScreeningTest::class, 'test_id', 'test_id'); }
}
