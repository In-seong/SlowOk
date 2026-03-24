<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningProgress extends Model
{
    use HasFactory;
    protected $table = 'learning_progress';
    protected $primaryKey = 'progress_id';
    protected $fillable = ['profile_id', 'content_id', 'status', 'score', 'attempts'];

    public function profile() { return $this->belongsTo(UserProfile::class, 'profile_id', 'profile_id'); }
    public function content() { return $this->belongsTo(LearningContent::class, 'content_id', 'content_id'); }
}
