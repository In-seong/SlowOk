<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningContent extends Model
{
    use HasFactory, Traits\BelongsToInstitution;
    protected $table = 'learning_content';
    protected $primaryKey = 'content_id';
    protected $fillable = ['category_id', 'title', 'content_type', 'content_data', 'difficulty_level', 'institution_id', 'is_active'];
    protected $casts = ['content_data' => 'array'];

    public function category() { return $this->belongsTo(LearningCategory::class, 'category_id', 'category_id'); }
    public function progress() { return $this->hasMany(LearningProgress::class, 'content_id', 'content_id'); }
}
