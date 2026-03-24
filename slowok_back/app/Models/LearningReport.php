<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningReport extends Model
{
    use HasFactory;
    protected $table = 'learning_report';
    protected $primaryKey = 'report_id';
    protected $fillable = ['profile_id', 'period', 'summary'];
    protected $casts = ['summary' => 'array'];

    public function profile() { return $this->belongsTo(UserProfile::class, 'profile_id', 'profile_id'); }
}
