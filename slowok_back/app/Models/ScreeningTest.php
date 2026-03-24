<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreeningTest extends Model
{
    use HasFactory, Traits\BelongsToInstitution;
    protected $table = 'screening_test';
    protected $primaryKey = 'test_id';
    protected $fillable = ['title', 'description', 'test_type', 'sub_domains', 'category_id', 'question_count', 'time_limit', 'institution_id', 'is_active'];
    protected $casts = ['sub_domains' => 'array'];

    public function category() { return $this->belongsTo(LearningCategory::class, 'category_id', 'category_id'); }
    public function questions() { return $this->hasMany(ScreeningQuestion::class, 'test_id', 'test_id'); }
    public function results() { return $this->hasMany(ScreeningResult::class, 'test_id', 'test_id'); }
}
