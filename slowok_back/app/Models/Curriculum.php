<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Curriculum extends Model
{
    use HasFactory;
    protected $table = 'curriculum';
    protected $primaryKey = 'curriculum_id';
    protected $fillable = ['profile_id', 'category_id', 'current_level', 'recommended_path'];
    protected $casts = ['recommended_path' => 'array'];

    public function profile() { return $this->belongsTo(UserProfile::class, 'profile_id', 'profile_id'); }
    public function category() { return $this->belongsTo(LearningCategory::class, 'category_id', 'category_id'); }
}
