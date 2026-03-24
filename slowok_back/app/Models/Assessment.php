<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    use HasFactory;
    protected $table = 'assessment';
    protected $primaryKey = 'assessment_id';
    protected $fillable = ['profile_id', 'category_id', 'type', 'score', 'feedback'];
    protected $casts = ['feedback' => 'array'];

    public function profile() { return $this->belongsTo(UserProfile::class, 'profile_id', 'profile_id'); }
    public function category() { return $this->belongsTo(LearningCategory::class, 'category_id', 'category_id'); }
}
